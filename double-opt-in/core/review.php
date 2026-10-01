<?php

namespace forge12\contactform7\CF7DoubleOptIn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent double-registration when Free + Pro are both active
if ( ! function_exists( __NAMESPACE__ . '\\f12_cf7_doubleoptin_maybe_show_review_notice' ) ) {

	add_action( 'admin_notices', __NAMESPACE__ . '\\f12_cf7_doubleoptin_maybe_show_review_notice' );
	add_action( 'admin_init', __NAMESPACE__ . '\\f12_cf7_doubleoptin_handle_review_actions' );

	/**
	 * Confirmed opt-ins a site needs before we ask for a review.
	 *
	 * History: 3 at first — a site that has barely started, asked to vouch for
	 * something it has not seen work yet. Then 25, which small sites rarely
	 * reach: nine ratings in four years, and the count is what wp.org ranks
	 * and visitors weigh. 10 (5.7.1) is past the "does it work at all" stage
	 * and still within reach of a small site's first weeks.
	 */
	function f12_cf7_doubleoptin_review_threshold(): int {
		/**
		 * Filter the number of confirmed opt-ins before the review request shows.
		 *
		 * @param int $threshold Default 10.
		 *
		 * @since 5.7.1
		 */
		return max( 1, (int) apply_filters( 'f12_doi_review_min_confirmed', 10 ) );
	}

	/**
	 * Whether the review request is due. Pure: every fact comes in.
	 *
	 * @param array{now:int, installedAt:int, confirmed:int, dismissed:bool, remindLater:int, remindCount:int, pagenow:string, page:string} $facts
	 */
	function f12_cf7_doubleoptin_review_is_due( array $facts ): bool {
		// Only where someone looks at the plugin: the dashboard, the plugins
		// screen and the plugin's own pages — not on every admin screen.
		$onScreen = in_array( $facts['pagenow'], array( 'index.php', 'plugins.php' ), true )
			|| ( $facts['pagenow'] === 'admin.php' && strpos( $facts['page'], 'f12-doi' ) === 0 );
		if ( ! $onScreen ) {
			return false;
		}
		if ( ( $facts['now'] - $facts['installedAt'] ) < DAY_IN_SECONDS * 10 ) {
			return false;
		}
		if ( $facts['confirmed'] < f12_cf7_doubleoptin_review_threshold() ) {
			return false;
		}
		if ( $facts['dismissed'] ) {
			return false;
		}
		if ( $facts['remindLater'] > 0 && $facts['now'] < $facts['remindLater'] ) {
			return false;
		}
		// Max 2 reminders.
		return $facts['remindCount'] < 2;
	}

	/**
	 * Show review notice if conditions are met.
	 */
	function f12_cf7_doubleoptin_maybe_show_review_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $pagenow;
		$optin_counters   = get_option( 'f12_cf7_doubleoptin_telemetry_counters', [] );
		$confirmed_optins = isset( $optin_counters['confirmed_optins'] ) ? (int) $optin_counters['confirmed_optins'] : 0;

		$due = f12_cf7_doubleoptin_review_is_due(
			array(
				'now'         => time(),
				'installedAt' => (int) get_option( 'f12_cf7_doubleoptin_installed_at', time() ),
				'confirmed'   => $confirmed_optins,
				'dismissed'   => (bool) get_option( 'f12_cf7_doubleoptin_review_dismissed', false ),
				'remindLater' => (int) get_option( 'f12_cf7_doubleoptin_review_remind_later', 0 ),
				'remindCount' => (int) get_option( 'f12_cf7_doubleoptin_review_remind_count', 0 ),
				'pagenow'     => isset( $pagenow ) ? (string) $pagenow : '',
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which admin page is open.
				'page'        => isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '',
			)
		);
		if ( ! $due ) {
			return;
		}

		// Claim this screen. The credit-link notice runs later on the same hook
		// and stands down when it sees this — asking a site owner for two
		// favours at once is how a plugin earns a one-star review, and the
		// review request is the more valuable of the two.
		$GLOBALS['f12_doi_review_notice_shown'] = true;

		?>
		<div class="notice notice-info is-dismissible f12-cf7-doubleoptin-review-notice">
			<p>
				<?php printf(
					wp_kses(
						/* translators: %d: number of confirmed opt-ins. */
						__(
							'<strong>Double Opt-In for WordPress</strong> has already confirmed <strong>%d email subscriptions</strong>. Would you support us with a quick review?',
							'double-opt-in'
						),
						array( 'strong' => array() )
					),
					(int) $confirmed_optins
				); ?>
			</p>
			<p>
				<a href="https://wordpress.org/support/plugin/double-opt-in/reviews/#new-post"
				   target="_blank"
				   class="button button-primary">
					<?php _e( 'Leave a review now', 'double-opt-in' ); ?>
				</a>
				<?php
				// The only path out of this notice used to be a public review.
				// Someone who is unhappy has no other outlet, so the feedback
				// lands as a one-star rating that nobody can answer. This gives
				// them somewhere to say it to us instead.
				?>
				<a href="<?php echo esc_url( get_feedback_url( 'review-notice' ) ); ?>"
				   target="_blank"
				   rel="noopener"
				   class="button">
					<?php esc_html_e( 'Something not working? Tell us', 'double-opt-in' ); ?>
				</a>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'f12_cf7_doubleoptin_review_remind', '1' ), 'doi_review_action' ) ); ?>"
				   class="button">
					<?php _e( 'Remind me later', 'double-opt-in' ); ?>
				</a>
				<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'f12_cf7_doubleoptin_review_dismissed', '1' ), 'doi_review_action' ) ); ?>"
				   class="button">
					<?php _e( "Don't ask again", 'double-opt-in' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle review notice actions (dismiss / remind later).
	 */
	function f12_cf7_doubleoptin_handle_review_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$is_dismiss = isset( $_GET['f12_cf7_doubleoptin_review_dismissed'] );
		$is_remind  = isset( $_GET['f12_cf7_doubleoptin_review_remind'] );

		if ( ! $is_dismiss && ! $is_remind ) {
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'doi_review_action' ) ) {
			return;
		}

		if ( $is_dismiss ) {
			update_option( 'f12_cf7_doubleoptin_review_dismissed', true );
		}

		if ( $is_remind ) {
			$remind_count = (int) get_option( 'f12_cf7_doubleoptin_review_remind_count', 0 );
			update_option( 'f12_cf7_doubleoptin_review_remind_later', time() + DAY_IN_SECONDS * 7 );
			update_option( 'f12_cf7_doubleoptin_review_remind_count', $remind_count + 1 );
		}

		// Redirect to remove query parameters from URL
		wp_safe_redirect( remove_query_arg( [ 'f12_cf7_doubleoptin_review_dismissed', 'f12_cf7_doubleoptin_review_remind', '_wpnonce' ] ) );
		exit;
	}
}
