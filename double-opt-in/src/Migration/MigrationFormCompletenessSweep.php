<?php
/**
 * Migration: Form Completeness Sweep
 *
 * @package Forge12\DoubleOptIn\Migration
 * @since   4.5.0
 */

namespace Forge12\DoubleOptIn\Migration;

use Forge12\DoubleOptIn\FormSettings\FormSettingsDTO;
use Forge12\DoubleOptIn\FormSettings\FormSettingsService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One-shot sweep that auto-disables every form whose DOI configuration
 * is enabled-but-incomplete at the moment this migration runs.
 *
 * Background (plan/doi-completeness-gate.md §2.4): the completeness-gate
 * in the REST controllers (§2.2 + §2.3) prevents NEW forms from entering
 * the broken state, but sites that have been running the plugin for a
 * while may have existing forms that became incomplete over time (a
 * form field was deleted, a custom template was removed, etc.). This
 * migration catches them once at upgrade time.
 *
 * What it does:
 *  - Walks every post_meta row with key `f12-cf7-doubleoptin` (the
 *    storage key {@see FormSettingsService::META_KEY}).
 *  - Skips already-disabled forms — they are by definition not broken
 *    in the user-visible sense.
 *  - For each enabled form, builds a {@see FormSettingsDTO} from the
 *    stored array and calls {@see FormSettingsDTO::getMissingRequiredFields()}.
 *  - When non-empty, flips `enable` to 0 in-place and fires
 *    `f12_doi_form_auto_disabled_incomplete` so listeners (e.g. the
 *    AdminNoticeIncompleteForms service) can surface the change to the
 *    site admin.
 *  - Stores the affected-forms list in
 *    `f12_doi_completeness_sweep_affected` so the admin notice has a
 *    rendering source.
 *
 * Idempotency: the {@see MigrationRegistry} guarantees this `up()`
 * method runs exactly once per site via the
 * `f12_doi_applied_migrations` option. A second run would simply find
 * no enabled-incomplete forms (since the first pass disabled them) and
 * be a no-op, but the registry skips it entirely.
 *
 * @internal Not part of any public API. Migration logic only.
 */
class MigrationFormCompletenessSweep implements MigrationInterface {

	/**
	 * Option key used by the admin-notice surface to read the list of
	 * forms that this migration auto-disabled. Stays around until the
	 * admin explicitly dismisses the notice.
	 */
	public const AFFECTED_FORMS_OPTION = 'f12_doi_completeness_sweep_affected';

	/**
	 * {@inheritdoc}
	 */
	public function getId(): string {
		return 'core_20260512_form_completeness_sweep';
	}

	/**
	 * {@inheritdoc}
	 */
	public function getDescription(): string {
		return 'Auto-disable existing DOI forms whose configuration is incomplete (see plan/doi-completeness-gate.md §2.4).';
	}

	/**
	 * {@inheritdoc}
	 */
	public function up( \wpdb $wpdb ): void {
		$metaKey = FormSettingsService::META_KEY;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
				$metaKey
			)
		);

		if ( empty( $rows ) ) {
			return;
		}

		$affected = array();

		foreach ( $rows as $row ) {
			$data = maybe_unserialize( $row->meta_value );
			if ( ! is_array( $data ) ) {
				continue;
			}

			// Skip already-disabled forms — they cannot be the
			// "enabled-but-broken" bug-state by definition.
			$isEnabled = ! empty( $data['enable'] ) || ! empty( $data['enabled'] );
			if ( ! $isEnabled ) {
				continue;
			}

			$dto     = FormSettingsDTO::fromArray( $data );
			$missing = $dto->getMissingRequiredFields();
			if ( empty( $missing ) ) {
				continue;
			}

			// Flip the stored value to disabled. We mutate the stored
			// array directly rather than re-serialising the DTO so
			// extension-data fields contributed by addons via the
			// f12_doi_settings_dto_* filters survive untouched.
			$data['enable']   = 0;
			$data['enabled']  = false;
			update_post_meta( (int) $row->post_id, $metaKey, $data );

			$affected[] = array(
				'form_id' => (int) $row->post_id,
				'missing' => array_values( $missing ),
			);

			/**
			 * Fired by the completeness-sweep migration AND by the
			 * controller-side auto-disable path (plan §2.2). Listeners
			 * should be additive and tolerate multiple firings for the
			 * same form_id over time.
			 *
			 * @since 4.5.0
			 *
			 * @param string $formId  Form ID as a string (composite IDs
			 *                        for Elementor — the migration always
			 *                        passes the bare post_id since that
			 *                        is the storage key).
			 * @param array  $missing Stable required-field IDs.
			 */
			do_action( 'f12_doi_form_auto_disabled_incomplete', (string) $row->post_id, $missing );
		}

		if ( ! empty( $affected ) ) {
			// Persist the list so the admin-notice surface can render
			// it on the next admin page-load. Cleared on dismiss
			// (handled by AdminNoticeIncompleteForms).
			update_option( self::AFFECTED_FORMS_OPTION, $affected, false );
		}
	}
}
