<?php
/**
 * Admin Notice: Incomplete Forms Auto-Disabled
 *
 * @package Forge12\DoubleOptIn\Admin
 * @since   4.5.0
 */

namespace Forge12\DoubleOptIn\Admin;

use Forge12\DoubleOptIn\Migration\MigrationFormCompletenessSweep;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders an admin notice listing the forms that the
 * {@see MigrationFormCompletenessSweep} migration auto-disabled at the
 * last upgrade. Each entry links to the form-settings edit page so the
 * admin can complete the configuration and re-enable it manually.
 *
 * Listens on the option key {@see MigrationFormCompletenessSweep::AFFECTED_FORMS_OPTION}.
 * The migration writes the list once at upgrade; the notice stays
 * around across page loads until the admin explicitly dismisses it
 * (via a nonced query-arg link) — we deliberately do NOT auto-clear
 * the option after one render, because the migration is rare and the
 * admin may not catch the notice on the first admin page-load.
 *
 * Plan/doi-completeness-gate.md §2.4 (Admin-Notice mit Liste betroffener
 * Forms + Links zur Edit-Seite).
 */
class AdminNoticeIncompleteForms {

	private const DISMISS_QUERY_ARG = 'f12_doi_dismiss_incomplete_notice';
	private const DISMISS_NONCE     = 'f12_doi_dismiss_incomplete';

	/**
	 * Hook the notice into the WordPress admin lifecycle.
	 *
	 * Idempotent — calling it twice does not double-register thanks to
	 * WordPress's add_action de-duplication for identical callbacks.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'maybeDismiss' ) );
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * If the admin clicked the dismiss link, clear the option and
	 * redirect to drop the query args from the URL bar.
	 */
	public function maybeDismiss(): void {
		if ( ! isset( $_GET[ self::DISMISS_QUERY_ARG ] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::DISMISS_NONCE ) ) {
			return;
		}

		delete_option( MigrationFormCompletenessSweep::AFFECTED_FORMS_OPTION );

		$redirect = remove_query_arg( array( self::DISMISS_QUERY_ARG, '_wpnonce' ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render the notice if there is anything to show.
	 *
	 * Capability-gated to `manage_options` because non-admin users have
	 * no business seeing form-settings paths.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$affected = get_option( MigrationFormCompletenessSweep::AFFECTED_FORMS_OPTION, array() );
		if ( ! is_array( $affected ) || empty( $affected ) ) {
			return;
		}

		$items = array();
		foreach ( $affected as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$formId  = (int) ( $row['form_id'] ?? 0 );
			$missing = (array) ( $row['missing'] ?? array() );
			if ( $formId <= 0 ) {
				continue;
			}

			$title = get_the_title( $formId );
			if ( empty( $title ) ) {
				/* translators: %d: form/post ID */
				$title = sprintf( __( 'Form #%d', 'double-opt-in' ), $formId );
			}

			// Deep-link into the React SPA edit page. The
			// admin-page slug `doi-forms` is registered by
			// AdminPageController.
			$editUrl = admin_url( 'admin.php?page=doi-forms#/forms/' . $formId . '/edit' );

			$missingLabels = array_map( array( $this, 'labelForMissingField' ), $missing );

			$items[] = sprintf(
				'<li><a href="%s">%s</a> &mdash; %s: <code>%s</code></li>',
				esc_url( $editUrl ),
				esc_html( $title ),
				esc_html__( 'missing', 'double-opt-in' ),
				esc_html( implode( ', ', $missingLabels ) )
			);
		}

		if ( empty( $items ) ) {
			return;
		}

		$dismissUrl = wp_nonce_url(
			add_query_arg( self::DISMISS_QUERY_ARG, '1' ),
			self::DISMISS_NONCE
		);

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong></p><p>%s</p><ul>%s</ul><p><a href="%s">%s</a></p></div>',
			esc_html__( 'Double Opt-In: forms auto-disabled due to incomplete configuration', 'double-opt-in' ),
			esc_html__( 'The following forms had their Double Opt-In disabled during the upgrade because required fields were missing. Open each form to complete its configuration and re-enable it.', 'double-opt-in' ),
			implode( '', $items ), // each item is already escaped above
			esc_url( $dismissUrl ),
			esc_html__( 'Dismiss this notice', 'double-opt-in' )
		);
	}

	/**
	 * Map a stable missing-field ID to a translatable user-facing label.
	 *
	 * Mirrors the message map in FormSettingsValidator::messageForMissingField()
	 * but at the field-name level (the notice already says "missing"
	 * surrounding it).
	 */
	private function labelForMissingField( string $field ): string {
		$labels = apply_filters(
			'f12_doi_form_missing_field_labels',
			array(
				'recipient'        => __( 'Recipient field', 'double-opt-in' ),
				'subject'          => __( 'Subject', 'double-opt-in' ),
				'body_or_template' => __( 'Email body or template', 'double-opt-in' ),
			)
		);

		return $labels[ $field ] ?? $field;
	}
}
