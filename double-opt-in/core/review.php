<?php
add_action( 'admin_notices', 'f12_cf7_doubleoptin_maybe_show_review_notice' );
add_action( 'admin_init', 'f12_cf7_doubleoptin_handle_review_actions' );

/**
 * Prüft, ob die Review-Notice angezeigt werden soll.
 */
function f12_cf7_doubleoptin_maybe_show_review_notice() {
	// Nur Admins sehen die Notice
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$installed_at  = (int) get_option( 'f12_cf7_doubleoptin_installed_at', time() );
	$optin_counters = get_option( 'f12_cf7_doubleoptin_telemetry_counters', [] );
	$confirmed_optins = isset( $optin_counters['confirmed_optins'] ) ? (int) $optin_counters['confirmed_optins'] : 0;

	$dismissed     = get_option( 'f12_cf7_doubleoptin_review_dismissed', false );
	$remind_later  = (int) get_option( 'f12_cf7_doubleoptin_review_remind_later', 0 );
	$remind_count  = (int) get_option( 'f12_cf7_doubleoptin_review_remind_count', 0 );

	// Bedingungen:
	// - mindestens 10 Tage installiert
	// - mindestens 20 Spamversuche blockiert
	if ( ( time() - $installed_at ) < DAY_IN_SECONDS * 10 ) {
		return;
	}

	if ( $confirmed_optins < 3 ) {
		return;
	}

	// Bereits dauerhaft dismissed?
	if ( $dismissed ) {
		return;
	}

	// Reminder-Delay aktiv?
	if ( $remind_later > 0 && ( time() < $remind_later ) ) {
		return;
	}

	// Maximal 2 Wiederholungen
	if ( $remind_count >= 2 ) {
		return;
	}

	?>
	<div class="notice notice-info is-dismissible f12-cf7-captcha-review-notice">
		<p>
			<?php printf(
				__(
					'<strong>Double Opt-In for WordPress</strong> has already confirmed <strong>%d email subscriptions</strong>. Would you support us with a quick review? ❤️',
					'double-opt-in'
				),
				$confirmed_optins
			); ?>
		</p>
		<p>
			<a href="https://wordpress.org/support/plugin/double-opt-in/reviews/#new-post"
			   target="_blank"
			   class="button button-primary">
				<?php _e( 'Leave a review now', 'double-opt-in' ); ?>
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
 * Verarbeitet Klicks auf die Links der Review-Notice.
 */
function f12_cf7_doubleoptin_handle_review_actions() {
	// Only process if user is admin
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Verify nonce for CSRF protection
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'doi_review_action' ) ) {
		return;
	}

	if ( isset( $_GET['f12_cf7_doubleoptin_review_dismissed'] ) ) {
		update_option( 'f12_cf7_doubleoptin_review_dismissed', true );
	}

	if ( isset( $_GET['f12_cf7_doubleoptin_review_remind'] ) ) {
		$remind_count = (int) get_option( 'f12_cf7_doubleoptin_review_remind_count', 0 );
		update_option( 'f12_cf7_doubleoptin_review_remind_later', time() + DAY_IN_SECONDS * 7 ); // 7 Tage warten
		update_option( 'f12_cf7_doubleoptin_review_remind_count', $remind_count + 1 );
	}
}
