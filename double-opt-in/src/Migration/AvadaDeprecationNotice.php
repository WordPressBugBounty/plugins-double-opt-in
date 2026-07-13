<?php
/**
 * Avada Deprecation Notice
 *
 * In Core 4.99 this class shows an admin warning on every admin page for
 * installations where Avada is detected AND a DOI setting exists for any
 * Avada form. The notice lets the user claim a free grandfather license
 * for the paid `double-opt-in-avada` addon that ships in Phase 2.
 *
 * In Core 5.0+ the same class stays active — at this point Avada is no
 * longer bundled in Core, so the notice becomes an error-level prompt
 * if the user has not yet claimed or installed the addon.
 *
 * Removal planned: Core 6.0 (one major version after the addon has been
 * the only supported path).
 *
 * @package Forge12\DoubleOptIn\Migration
 * @since   4.99.0
 */

namespace Forge12\DoubleOptIn\Migration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AvadaDeprecationNotice {

	/** Option name for storing the grandfather license key after a successful claim. */
	public const OPTION_GRANDFATHER_LICENSE = 'f12_doi_avada_grandfather_license';

	/** Option name recording that the user dismissed the pre-5.0 notice. */
	private const OPTION_DISMISSED = 'f12_doi_avada_deprecation_dismissed';

	/** Option name recording the last known Avada-usage detection result (cached for 24h). */
	private const TRANSIENT_AVADA_USED = 'f12_doi_avada_in_use';

	/** The REST endpoint Core exposes for client-side claim requests. */
	private const REST_NAMESPACE = 'f12-doi/v1';
	private const REST_ROUTE     = '/avada-migration/claim';

	public static function register(): void {
		add_action( 'admin_notices', array( self::class, 'renderNotice' ) );
		add_action( 'wp_ajax_f12_doi_avada_dismiss', array( self::class, 'handleDismiss' ) );
		add_action( 'rest_api_init', array( self::class, 'registerRestRoute' ) );
	}

	/**
	 * Decide whether the notice should appear at all on this request.
	 */
	public static function shouldRender(): bool {
		// Already claimed? Silence the notice.
		if ( get_option( self::OPTION_GRANDFATHER_LICENSE, '' ) !== '' ) {
			return false;
		}

		// Dismissed pre-5.0 AND we are still pre-5.0? Silence.
		if ( get_option( self::OPTION_DISMISSED, false ) && ! self::isCore5OrLater() ) {
			return false;
		}

		// Avada not installed or not actively used with DOI? Silence.
		if ( ! self::isAvadaInUseWithDoi() ) {
			return false;
		}

		return current_user_can( 'manage_options' );
	}

	public static function renderNotice(): void {
		if ( ! self::shouldRender() ) {
			return;
		}

		$isPostRemoval = self::isCore5OrLater();
		$class         = $isPostRemoval ? 'notice notice-error' : 'notice notice-warning';
		$title         = $isPostRemoval
			? __( 'Avada DOI support has moved to a paid addon', 'double-opt-in' )
			: __( 'Avada DOI support is moving to a paid addon', 'double-opt-in' );

		$body = $isPostRemoval
			? __( 'Your Avada forms with Double Opt-In configured are currently not sending verification emails. Install the free Double Opt-In Avada grandfather license to restore this functionality. This one-time offer is available for existing free-plugin users.', 'double-opt-in' )
			: __( 'In the next major release, Avada integration will move out of the free plugin and into a separate paid addon. Existing free-plugin users with Avada forms qualify for a free, permanent grandfather license. Claim it now so your forms keep working after the update.', 'double-opt-in' );

		$nonce = wp_create_nonce( 'f12-doi-avada-migration' );

		?>
		<div class="<?php echo esc_attr( $class ); ?>" id="f12-doi-avada-notice">
			<p><strong><?php echo esc_html( $title ); ?></strong></p>
			<p><?php echo esc_html( $body ); ?></p>
			<p>
				<button type="button"
						class="button button-primary"
						id="f12-doi-avada-claim"
						data-nonce="<?php echo esc_attr( $nonce ); ?>">
					<?php esc_html_e( 'Claim free Avada grandfather license', 'double-opt-in' ); ?>
				</button>
				<?php if ( ! $isPostRemoval ) : ?>
					<button type="button"
							class="button"
							id="f12-doi-avada-dismiss"
							data-nonce="<?php echo esc_attr( wp_create_nonce( 'f12-doi-avada-dismiss' ) ); ?>">
						<?php esc_html_e( 'Dismiss until update', 'double-opt-in' ); ?>
					</button>
				<?php endif; ?>
				<a href="https://forge12.com/double-opt-in/avada-migration"
					target="_blank"
					rel="noopener">
					<?php esc_html_e( 'Learn more', 'double-opt-in' ); ?>
				</a>
			</p>
			<div id="f12-doi-avada-result" style="margin-top: 10px;"></div>
		</div>
		<script>
		(function () {
			const ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			const restUrl = <?php echo wp_json_encode( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) ); ?>;
			const restNonce = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>;

			const claim = document.getElementById('f12-doi-avada-claim');
			const dismiss = document.getElementById('f12-doi-avada-dismiss');
			const result = document.getElementById('f12-doi-avada-result');

			if (claim) {
				claim.addEventListener('click', async function () {
					claim.disabled = true;
					result.textContent = <?php echo wp_json_encode( __( 'Requesting grandfather license…', 'double-opt-in' ) ); ?>;
					try {
						const response = await fetch(restUrl, {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
								'X-WP-Nonce': restNonce
							}
						});
						const data = await response.json();
						if (data.success) {
							result.innerHTML = <?php echo wp_json_encode( __( '<strong>Success.</strong> Your Avada grandfather license has been activated. The Avada addon plugin will be installed automatically on your next update.', 'double-opt-in' ) ); ?>;
							document.getElementById('f12-doi-avada-notice').className = 'notice notice-success';
						} else {
							result.textContent = data.message || <?php echo wp_json_encode( __( 'Could not claim the grandfather license. Please contact support.', 'double-opt-in' ) ); ?>;
							claim.disabled = false;
						}
					} catch (e) {
						result.textContent = <?php echo wp_json_encode( __( 'Network error while claiming license. Please try again.', 'double-opt-in' ) ); ?>;
						claim.disabled = false;
					}
				});
			}

			if (dismiss) {
				dismiss.addEventListener('click', async function () {
					const body = new URLSearchParams();
					body.set('action', 'f12_doi_avada_dismiss');
					body.set('_wpnonce', dismiss.getAttribute('data-nonce'));
					await fetch(ajaxUrl, { method: 'POST', body: body });
					document.getElementById('f12-doi-avada-notice').remove();
				});
			}
		})();
		</script>
		<?php
	}

	public static function handleDismiss(): void {
		check_ajax_referer( 'f12-doi-avada-dismiss' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}
		update_option( self::OPTION_DISMISSED, true );
		wp_send_json_success();
	}

	public static function registerRestRoute(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'handleClaimRequest' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * REST callback — server-side wrapper around the Forge12 grandfather endpoint.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public static function handleClaimRequest( $request ) {
		$client   = new GrandfatherLicenseClient();
		$response = $client->claimAvada(
			home_url(),
			get_option( 'admin_email', '' ),
			self::buildAttestation()
		);

		if ( $response['success'] ) {
			update_option( self::OPTION_GRANDFATHER_LICENSE, $response['licenseKey'] );
		}

		return new \WP_REST_Response( $response );
	}

	/**
	 * Build the self-reported attestation payload that accompanies the claim.
	 *
	 * @return array<string,mixed>
	 */
	private static function buildAttestation(): array {
		return array(
			'avadaDetected'            => self::isAvadaActive(),
			'doiConfiguredOnAvadaForm' => self::doiConfiguredOnAvadaForm(),
			'lastActivity'             => gmdate( 'Y-m-d' ),
		);
	}

	/**
	 * Cache-backed check: does this site have at least one Avada form with
	 * DOI settings configured?
	 */
	public static function isAvadaInUseWithDoi(): bool {
		$cached = get_transient( self::TRANSIENT_AVADA_USED );
		if ( $cached === 'yes' ) {
			return true;
		}
		if ( $cached === 'no' ) {
			return false;
		}

		$result = self::isAvadaActive() && self::doiConfiguredOnAvadaForm();
		set_transient( self::TRANSIENT_AVADA_USED, $result ? 'yes' : 'no', DAY_IN_SECONDS );

		return $result;
	}

	public static function isAvadaActive(): bool {
		return class_exists( '\\Fusion_Forms' ) || function_exists( 'fusion_builder_init' );
	}

	/**
	 * Expensive query — only call via the cached isAvadaInUseWithDoi().
	 *
	 * Counts posts of post_type `fusion_form` that have a DOI settings
	 * meta entry. A single row is enough to qualify the site for the notice.
	 */
	public static function doiConfiguredOnAvadaForm(): bool {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE p.post_type = %s
			   AND pm.meta_key = %s
			   AND pm.meta_value != ''
			 LIMIT 1",
			'fusion_form',
			'f12-cf7-doubleoptin'
		);

		return (int) $wpdb->get_var( $sql ) > 0;
	}

	private static function isCore5OrLater(): bool {
		if ( ! defined( 'FORGE12_OPTIN_VERSION' ) ) {
			return false;
		}
		return version_compare( FORGE12_OPTIN_VERSION, '5.0.0', '>=' );
	}
}
