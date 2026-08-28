<?php
/**
 * The consent gate — may this submission become an opt-in?
 *
 * @package Forge12\DoubleOptIn\Consent
 * @since   5.4.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Consent;

use Forge12\DoubleOptIn\Integration\SubmittedContent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decides whether a submission carries the acceptance the admin asked for.
 *
 * A form can name one of its fields as the acceptance field
 * (`consent_field`). When it does, the visitor has to have confirmed it —
 * otherwise the opt-in would ship a `consent_text` snapshot as proof of an
 * agreement nobody ever gave. That is fabricated audit evidence, and GDPR
 * Art. 7 is exactly about not producing it.
 *
 * ## Why this is its own class
 *
 * The check used to live inside `AbstractFormIntegration`, which meant it
 * only ran for the integrations that extend it — CF7, WPForms, Gravity
 * Forms, Avada. Elementor implements `FormIntegrationInterface` directly
 * and submits through `OptInFrontend::maybeCreateOptIn()`, and so did the
 * CF7/Avada legacy shims. For those the checkbox was recorded but never
 * enforced. Both paths now call in here.
 *
 * ## Three states, not two
 *
 * The old check knew only "passed" and "rejected", and that conflated two
 * very different situations:
 *
 * - the visitor did not tick the box  → the visitor's doing, reject
 * - the configured field is not on the form at all → the admin's doing
 *
 * Rejecting the second case takes a site's registrations offline for a
 * mistake the visitor cannot see or fix. It has happened: before 5.3.2 the
 * settings ran the field name through `sanitize_key()`, a form field named
 * `Datenschutz` was stored as `datenschutz`, matched nothing at submit
 * time, and every single submission was refused with "consent not given"
 * until the customer reported the dead form (2026-08-27).
 *
 * So {@see self::FIELD_UNKNOWN} exists and never rejects. The opt-in is
 * created, the mismatch is logged, and the admin hears about it through
 * the Site Health check and the banner on the form's settings tab — where
 * the person who can actually fix it will see it.
 *
 * ## What "known fields" buys us
 *
 * An unticked HTML checkbox is not submitted at all, so "absent from the
 * payload" on its own cannot tell an unticked box from a field that no
 * longer exists. The form's own field inventory
 * ({@see \Forge12\DoubleOptIn\Integration\FormIntegrationInterface::getFormFields()})
 * settles it: a field the form declares but the payload omits is an
 * unticked box. Callers that cannot supply the inventory get the
 * conservative reading — nothing is rejected unless the payload itself
 * proves the field was there and empty.
 */
final class ConsentGate {

	/** Gate disabled, or the visitor confirmed. Create the opt-in. */
	public const PASSED = 'passed';

	/** The field belongs to this form and was not confirmed. Reject. */
	public const NOT_GIVEN = 'not_given';

	/** The configured field is not on this form. Do NOT reject; warn the admin. */
	public const FIELD_UNKNOWN = 'field_unknown';

	/**
	 * Evaluate the gate.
	 *
	 * @param string             $consentField The configured acceptance field. Empty = gate off.
	 * @param mixed              $content      The submitted values, in any integration's shape.
	 * @param array<mixed,mixed> $knownFields  The form's field inventory, `name => label`
	 *                                         or a plain list of names. Empty when unknown.
	 *
	 * @return string One of the three class constants.
	 */
	public static function evaluate( string $consentField, $content, array $knownFields = array() ): string {
		$consentField = trim( $consentField );
		if ( $consentField === '' ) {
			return self::PASSED;
		}

		// Which key does the payload actually use? Settings written before
		// 5.3.2 are lowercased, the payload is not.
		$resolved = self::resolveInPayload( $content, $consentField );
		$lookup   = $resolved !== '' ? $resolved : $consentField;

		if ( ! empty( SubmittedContent::findValue( $content, $lookup ) ) ) {
			return self::PASSED;
		}

		if ( self::mirrorSaysTicked( $content, $lookup ) ) {
			return self::PASSED;
		}

		// The payload carries the key but nothing in it — the field was on
		// the form and the visitor left it alone. No inventory needed.
		if ( $resolved !== '' ) {
			return self::NOT_GIVEN;
		}

		// Not in the payload. Only the form's own definition can say
		// whether that is an unticked checkbox or a stale setting.
		if ( SubmittedContent::matchFieldName( $consentField, self::normalizeFieldNames( $knownFields ) ) !== '' ) {
			return self::NOT_GIVEN;
		}

		return self::FIELD_UNKNOWN;
	}

	/**
	 * May a `NOT_GIVEN` verdict actually reject this submission?
	 *
	 * The one method here that touches WordPress. It exists as an escape
	 * hatch for the 5.4.0 rollout: enforcement is on by default, and a
	 * site that hits an edge case nobody anticipated can switch it off per
	 * form without downgrading or losing the rest of the release.
	 *
	 * Not a setting on purpose — a checkbox in the UI would invite people
	 * to turn the gate off to make an inconvenient rejection go away, and
	 * the rejection is the point.
	 *
	 * @param int    $formId      The form being submitted.
	 * @param string $integration The integration identifier.
	 */
	public static function isEnforced( int $formId, string $integration = '' ): bool {
		if ( ! function_exists( 'apply_filters' ) ) {
			return true;
		}

		/**
		 * Filter whether the consent gate may reject a submission whose
		 * acceptance field was not confirmed.
		 *
		 * Returning false accepts the submission and logs a warning — the
		 * stored consent proof is then not backed by a confirmation.
		 *
		 * @since 5.4.0
		 *
		 * @param bool   $enforce     Whether to reject. Default true.
		 * @param int    $formId      The form being submitted.
		 * @param string $integration The integration identifier.
		 */
		return (bool) apply_filters( 'f12_doi_enforce_consent_gate', true, $formId, $integration );
	}

	/**
	 * Reduce a field inventory to a plain list of field names.
	 *
	 * Every integration's `getFormFields()` returns `name => label`, but a
	 * caller that already has a list should not have to flip it.
	 *
	 * @param array<mixed,mixed> $fields
	 *
	 * @return array<int,string>
	 */
	public static function normalizeFieldNames( array $fields ): array {
		if ( $fields === array() ) {
			return array();
		}

		$isList = array_keys( $fields ) === range( 0, count( $fields ) - 1 );
		$names  = $isList ? array_values( $fields ) : array_keys( $fields );

		$out = array();
		foreach ( $names as $name ) {
			if ( is_scalar( $name ) && (string) $name !== '' ) {
				$out[] = (string) $name;
			}
		}

		return $out;
	}

	/**
	 * The canonical key the payload uses for this field, or '' when the
	 * payload does not carry it at all.
	 *
	 * @param mixed  $content
	 * @param string $consentField
	 */
	private static function resolveInPayload( $content, string $consentField ): string {
		$names  = array();
		$levels = self::payloadLevels( $content );
		foreach ( $levels as $level ) {
			foreach ( array_keys( $level ) as $key ) {
				$names[] = (string) $key;
			}
		}

		return SubmittedContent::matchFieldName( $consentField, $names );
	}

	/**
	 * WPForms ships every checkbox twice: the bare id holds the joined
	 * display labels, and `field_{id}` holds the structured record. When
	 * the labels are empty the joined string is empty too, so a ticked box
	 * reads as untouched — the `value_raw` array in the mirror is the
	 * honest signal. Reported 2026-05-13.
	 *
	 * @param mixed  $content
	 * @param string $consentField
	 */
	private static function mirrorSaysTicked( $content, string $consentField ): bool {
		$mirror = SubmittedContent::findValue( $content, 'field_' . $consentField );
		if ( ! is_array( $mirror ) ) {
			return false;
		}

		$candidates = array(
			$mirror['value_raw'] ?? null,
			$mirror['value'] ?? null,
		);

		foreach ( $candidates as $candidate ) {
			if ( is_array( $candidate ) ) {
				foreach ( $candidate as $entry ) {
					if ( is_scalar( $entry ) && (string) $entry !== '' ) {
						return true;
					}
				}
				continue;
			}
			if ( is_scalar( $candidate ) && (string) $candidate !== '' ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Top level plus the unwrapped field map, in probe order.
	 *
	 * @param mixed $content
	 *
	 * @return array<int,array<mixed,mixed>>
	 */
	private static function payloadLevels( $content ): array {
		if ( ! is_array( $content ) ) {
			return array();
		}

		$levels = array( $content );
		$fields = SubmittedContent::unwrapFields( $content );
		if ( $fields !== $content ) {
			$levels[] = $fields;
		}

		return $levels;
	}
}
