<?php
/**
 * Resend Confirmation Mail Controller
 *
 * @package Forge12\DoubleOptIn\Admin
 * @since   3.2.0
 */

namespace Forge12\DoubleOptIn\Admin;

use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\Repository\OptInRepositoryInterface;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ResendController
 *
 * Handles resending confirmation emails for unconfirmed opt-ins.
 */
class ResendController {

	private LoggerInterface $logger;

	public function __construct( LoggerInterface $logger ) {
		$this->logger = $logger;

		add_action( 'wp_ajax_doi_resend_optin_mail', [ $this, 'handleResend' ] );
		add_action( 'f12_cf7_doubleoptin_ui_view_optin_options', [ $this, 'renderResendButton' ] );
	}

	/**
	 * Render the resend button on the view-optin page.
	 *
	 * @param \forge12\contactform7\CF7DoubleOptIn\OptIn $optin The OptIn facade instance.
	 *
	 * @return void
	 */
	public function renderResendButton( $optin ): void {
		$is_pro       = apply_filters( 'f12_doi_is_pro_active', false );
		$is_confirmed = $optin->is_confirmed();
		$has_mail     = ! empty( $optin->get_mail_optin() );
		$disabled     = ! $is_pro || $is_confirmed || ! $has_mail;

		$nonce = wp_create_nonce( 'doi_resend_mail' );
		$id    = $optin->get_id();

		// Build tooltip explaining why the button is disabled
		$title = '';
		if ( ! $is_pro ) {
			$title = __( 'This feature is available in the Pro version.', 'double-opt-in' );
		} elseif ( $is_confirmed ) {
			$title = __( 'This Opt-In has already been confirmed.', 'double-opt-in' );
		} elseif ( ! $has_mail ) {
			$title = __( 'No mail body stored for this Opt-In.', 'double-opt-in' );
		}
		?>
		<button type="button"
		        class="button"
		        id="doi-resend-btn"
		        data-id="<?php echo esc_attr( $id ); ?>"
		        data-nonce="<?php echo esc_attr( $nonce ); ?>"
		        <?php echo $disabled ? 'disabled' : ''; ?>
		        <?php echo ! empty( $title ) ? 'title="' . esc_attr( $title ) . '"' : ''; ?>
		        style="<?php echo $disabled ? 'opacity:.6;cursor:not-allowed;' : ''; ?>"><?php
			_e( 'Resend Confirmation Mail', 'double-opt-in' );
		?></button><?php
		if ( ! $is_pro ) : ?>
			<span style="display:inline-block;background:linear-gradient(135deg,#e6a817,#d4941a);color:#fff;font-size:10px;font-weight:700;line-height:1;padding:3px 6px;border-radius:3px;letter-spacing:.5px;text-transform:uppercase;"
			      title="<?php esc_attr_e( 'This feature is available in the Pro version.', 'double-opt-in' ); ?>">PRO</span>
		<?php endif; ?>
		<span class="doi-tooltip">
			<span class="dashicons dashicons-info-outline"></span>
			<span class="doi-tooltip-text"><?php
				esc_html_e( 'Re-sends the original confirmation email with the opt-in link to the recipient.', 'double-opt-in' );
			?></span>
		</span>
		<span id="doi-resend-feedback" style="font-size:13px;"></span>
		<?php if ( $is_pro && ! $disabled ) : ?>
		<script>
		(function() {
			var btn = document.getElementById('doi-resend-btn');
			var feedback = document.getElementById('doi-resend-feedback');
			if (!btn || btn.disabled) return;

			btn.addEventListener('click', function() {
				btn.disabled = true;
				btn.style.opacity = '.6';
				feedback.textContent = '';

				var formData = new FormData();
				formData.append('action', 'doi_resend_optin_mail');
				formData.append('_wpnonce', btn.dataset.nonce);
				formData.append('optin_id', btn.dataset.id);

				fetch(ajaxurl, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin'
				})
				.then(function(response) { return response.json(); })
				.then(function(data) {
					if (data.success) {
						feedback.style.color = '#00a32a';
						feedback.textContent = data.data.message || 'Mail sent';
					} else {
						feedback.style.color = '#d63638';
						feedback.textContent = data.data.message || 'Error';
						btn.disabled = false;
						btn.style.opacity = '';
					}
				})
				.catch(function() {
					feedback.style.color = '#d63638';
					feedback.textContent = 'Request failed';
					btn.disabled = false;
					btn.style.opacity = '';
				});
			});
		})();
		</script>
		<?php endif; ?>
		<?php
	}

	/**
	 * Handle the AJAX request to resend a confirmation mail.
	 *
	 * @return void
	 */
	public function handleResend(): void {
		if ( ! apply_filters( 'f12_doi_is_pro_active', false ) ) {
			wp_send_json_error( [ 'message' => __( 'This feature requires the Pro version.', 'double-opt-in' ) ], 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to perform this action.', 'double-opt-in' ) ], 403 );
		}

		if ( ! check_ajax_referer( 'doi_resend_mail', '_wpnonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'double-opt-in' ) ], 403 );
		}

		$optinId = isset( $_POST['optin_id'] ) ? (int) $_POST['optin_id'] : 0;

		if ( $optinId <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Invalid Opt-In ID.', 'double-opt-in' ) ] );
		}

		try {
			$container  = Container::getInstance();
			$repository = $container->get( OptInRepositoryInterface::class );
			$entity     = $repository->findById( $optinId );
		} catch ( \Exception $e ) {
			$this->logger->error( 'Failed to load OptIn for resend', [
				'plugin' => 'double-opt-in',
				'id'     => $optinId,
				'error'  => $e->getMessage(),
			] );
			wp_send_json_error( [ 'message' => __( 'Failed to load Opt-In.', 'double-opt-in' ) ] );
		}

		if ( ! $entity ) {
			wp_send_json_error( [ 'message' => __( 'Opt-In not found.', 'double-opt-in' ) ] );
		}

		if ( $entity->isConfirmed() ) {
			wp_send_json_error( [ 'message' => __( 'This Opt-In is already confirmed.', 'double-opt-in' ) ] );
		}

		$body = $entity->getMailOptIn();
		if ( empty( $body ) ) {
			wp_send_json_error( [ 'message' => __( 'No mail body stored for this Opt-In.', 'double-opt-in' ) ] );
		}

		$email = $entity->getEmail();
		if ( empty( $email ) ) {
			wp_send_json_error( [ 'message' => __( 'No recipient email found.', 'double-opt-in' ) ] );
		}

		// Load subject from form settings with fallback
		$formId        = $entity->getFormId();
		$formParameter = \forge12\contactform7\CF7DoubleOptIn\CF7DoubleOptIn::getInstance()->getParameter( $formId );
		$subject       = ! empty( $formParameter['subject'] )
			? $formParameter['subject']
			: __( 'Please confirm your opt-in', 'double-opt-in' );

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		if ( ! empty( $formParameter['sender'] ) ) {
			if ( ! empty( $formParameter['sender_name'] ) ) {
				$headers[] = 'From: ' . $formParameter['sender_name'] . ' <' . $formParameter['sender'] . '>';
			} else {
				$headers[] = 'From: ' . $formParameter['sender'];
			}
		}

		$sent = wp_mail( $email, $subject, $body, $headers );

		if ( $sent ) {
			$this->logger->info( 'Confirmation mail resent', [
				'plugin'   => 'double-opt-in',
				'optin_id' => $optinId,
				'email'    => $email,
			] );
			wp_send_json_success( [ 'message' => __( 'Confirmation mail has been resent.', 'double-opt-in' ) ] );
		} else {
			$this->logger->error( 'Failed to resend confirmation mail', [
				'plugin'   => 'double-opt-in',
				'optin_id' => $optinId,
				'email'    => $email,
			] );
			wp_send_json_error( [ 'message' => __( 'Failed to send mail. Please check your mail configuration.', 'double-opt-in' ) ] );
		}
	}
}
