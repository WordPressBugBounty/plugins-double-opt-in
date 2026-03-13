<?php

namespace forge12\contactform7\CF7DoubleOptIn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prevent double-registration when Free + Pro are both active
if ( ! function_exists( __NAMESPACE__ . '\\f12_doi_avada_deprecation_maybe_show_notice' ) ) {

	add_action( 'admin_notices', __NAMESPACE__ . '\\f12_doi_avada_deprecation_maybe_show_notice' );
	add_action( 'admin_init', __NAMESPACE__ . '\\f12_doi_avada_deprecation_handle_dismiss' );

	/**
	 * Show Avada deprecation notice if conditions are met.
	 */
	function f12_doi_avada_deprecation_maybe_show_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Only show when Avada / Fusion Builder is active
		if ( ! class_exists( 'Fusion_Form_Builder' ) ) {
			return;
		}

		// Don't show if already dismissed
		if ( get_option( 'f12_doi_avada_deprecation_dismissed', false ) ) {
			return;
		}

		// Don't show if Pro is active (Avada stays in Pro)
		if ( apply_filters( 'f12_doi_is_pro_active', false ) ) {
			return;
		}

		$upgrade_url = apply_filters( 'f12_doi_upgrade_url', 'https://www.forge12.com/product/contact-form-7-double-opt-in/' );
		$dismiss_url = wp_nonce_url( add_query_arg( 'f12_doi_avada_deprecation_dismiss', '1' ), 'doi_avada_deprecation_dismiss' );

		?>
		<div class="notice notice-warning is-dismissible f12-doi-avada-deprecation-notice">
			<p>
				<strong><?php _e( 'Avada Forms Support will become a Pro Feature', 'double-opt-in' ); ?></strong>
			</p>
			<p>
				<?php _e( 'Starting with version 3.8.0, the Avada Forms integration will only be available in the Pro version of Double Opt-In. Contact Form 7 support remains free.', 'double-opt-in' ); ?>
			</p>
			<p>
				<a href="<?php echo esc_url( $upgrade_url ); ?>"
				   target="_blank"
				   class="button button-primary">
					<?php _e( 'Upgrade to Pro', 'double-opt-in' ); ?>
				</a>
				<a href="<?php echo esc_url( $dismiss_url ); ?>"
				   class="button">
					<?php _e( "Don't show again", 'double-opt-in' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Handle dismiss action for Avada deprecation notice.
	 */
	function f12_doi_avada_deprecation_handle_dismiss() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['f12_doi_avada_deprecation_dismiss'] ) ) {
			return;
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'doi_avada_deprecation_dismiss' ) ) {
			return;
		}

		update_option( 'f12_doi_avada_deprecation_dismissed', true );

		wp_safe_redirect( remove_query_arg( [ 'f12_doi_avada_deprecation_dismiss', '_wpnonce' ] ) );
		exit;
	}
}
