<?php
/**
 * Health check: every configured acceptance field still exists.
 *
 * @package Forge12\DoubleOptIn\Health
 * @since   5.4.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Health;

use Forge12\DoubleOptIn\Consent\ConsentGate;
use Forge12\DoubleOptIn\Container\Container;
use Forge12\DoubleOptIn\FormSettings\FormSettingsService;
use Forge12\DoubleOptIn\Integration\FormIntegrationRegistry;
use Forge12\DoubleOptIn\Integration\SubmittedContent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finds forms whose `consent_field` points at a field that is not on the
 * form any more.
 *
 * Such a form keeps accepting registrations — {@see ConsentGate} refuses
 * to punish a visitor for a settings mistake — but every opt-in it
 * creates carries a `consent_text` that nobody was ever required to
 * confirm. The record looks like proof and is not one, which is the worst
 * of the three possible states, and nothing in the admin surfaced it
 * before this check: the banner on the settings tab only appears if
 * someone opens that particular form.
 *
 * A field goes stale by being renamed or deleted in the form builder
 * while the opt-in settings keep the old name — nothing warns the admin
 * at that moment, because the two live in different plugins.
 *
 * Cost: one `getForms()` + `getFormFields()` per integration and one
 * post-meta read per form. That is too much for every admin page load, so
 * the outcome is cached for six hours and flushed whenever form settings
 * are saved ({@see self::flush()}).
 */
final class StaleConsentFieldCheck implements HealthCheckInterface {

	private const CACHE_KEY = 'f12_doi_stale_consent_fields';
	private const CACHE_TTL = 21600; // 6h.

	/**
	 * How many offending forms to name in the description before
	 * summarising the rest.
	 */
	private const NAME_LIMIT = 3;

	public function getId(): string {
		return 'f12_doi_consent_field_exists';
	}

	public function getLabel(): string {
		return __( 'Consent acceptance fields', 'double-opt-in' );
	}

	public function getPackage(): string {
		return 'core';
	}

	/**
	 * Drop the cached scan. Called after form settings change, because
	 * the most common reason to open those settings is to fix exactly
	 * this — and a six-hour-old "still broken" is a bad answer then.
	 */
	public static function flush(): void {
		delete_transient( self::CACHE_KEY );
	}

	public function run(): HealthCheckResult {
		$stale = $this->findStaleForms();

		if ( $stale === array() ) {
			return new HealthCheckResult(
				HealthCheckResult::STATUS_GOOD,
				__( 'Every configured acceptance field exists', 'double-opt-in' ),
				__( 'Each form that requires a consent checkbox points at a field that is actually on the form, so the stored consent is backed by a confirmation.', 'double-opt-in' ),
				'ok'
			);
		}

		$names = array();
		foreach ( array_slice( $stale, 0, self::NAME_LIMIT ) as $form ) {
			/* translators: 1: form title, 2: configured field name */
			$names[] = sprintf( __( '“%1$s” (field “%2$s”)', 'double-opt-in' ), $form['title'], $form['field'] );
		}

		$listed = implode( ', ', $names );
		if ( count( $stale ) > self::NAME_LIMIT ) {
			/* translators: 1: comma-separated form list, 2: number of further forms */
			$listed = sprintf( __( '%1$s and %2$d more', 'double-opt-in' ), $listed, count( $stale ) - self::NAME_LIMIT );
		}

		return new HealthCheckResult(
			HealthCheckResult::STATUS_RECOMMENDED,
			/* translators: %d: number of affected forms */
			sprintf( _n( '%d form has an acceptance field that no longer exists', '%d forms have an acceptance field that no longer exists', count( $stale ), 'double-opt-in' ), count( $stale ) ),
			sprintf(
				/* translators: %s: list of affected forms */
				__( 'These forms store a consent text as proof, but the field the visitor was supposed to tick is not on the form: %s. Submissions are still accepted — a settings mistake must not take your registrations offline — but the stored consent is not provable. Pick a field that exists, or clear the setting.', 'double-opt-in' ),
				$listed
			),
			'stale:' . count( $stale ),
			__( 'Review form settings', 'double-opt-in' ),
			admin_url( 'admin.php?page=f12-doi-admin#/forms' )
		);
	}

	/**
	 * @return array<int,array{title:string,field:string}>
	 */
	private function findStaleForms(): array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$stale = $this->scan();
		set_transient( self::CACHE_KEY, $stale, self::CACHE_TTL );

		return $stale;
	}

	/**
	 * @return array<int,array{title:string,field:string}>
	 */
	private function scan(): array {
		$stale = array();

		try {
			$registry = FormIntegrationRegistry::getInstance();
			$settings = Container::getInstance()->get( FormSettingsService::class );
		} catch ( \Throwable $e ) {
			return $stale;
		}

		if ( ! $settings instanceof FormSettingsService ) {
			return $stale;
		}

		foreach ( $registry->getAvailable() as $integration ) {
			try {
				$forms = $integration->getForms();
			} catch ( \Throwable $e ) {
				continue;
			}

			foreach ( $forms as $form ) {
				$formId = isset( $form['id'] ) ? (int) $form['id'] : 0;
				if ( $formId <= 0 ) {
					continue;
				}

				try {
					$field = (string) ( $settings->getSettings( $formId )->consentField ?? '' );
					if ( $field === '' ) {
						continue;
					}

					$known = ConsentGate::normalizeFieldNames( $integration->getFormFields( $formId ) );
					// An integration that cannot list its fields tells us
					// nothing — reporting those would flood the check with
					// forms that are perfectly fine.
					if ( $known === array() ) {
						continue;
					}

					if ( SubmittedContent::matchFieldName( $field, $known ) !== '' ) {
						continue;
					}
				} catch ( \Throwable $e ) {
					continue;
				}

				$stale[] = array(
					'title' => (string) ( $form['title'] ?? ( '#' . $formId ) ),
					'field' => $field,
				);
			}
		}

		return $stale;
	}
}
