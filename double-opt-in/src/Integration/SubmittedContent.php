<?php
/**
 * Reading the submitted fields out of a stored opt-in.
 *
 * @package Forge12\DoubleOptIn\Integration
 * @since   5.3.2
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one place that knows where each integration puts the values the
 * visitor typed.
 *
 * The `content` column of an opt-in row holds whatever the integration
 * handed to `createOptIn()`/`maybeCreateOptIn()`, and that is not the
 * same shape everywhere:
 *
 * | Integration              | Values live at                |
 * |-------------------------|-------------------------------|
 * | CF7, WPForms, Gravity   | `$content[<field>]`           |
 * | Elementor               | `$content['form_fields'][…]`  |
 * | Elementor (older Pro)   | `$content['fields'][…]`       |
 * | Avada                   | `$content['data'][…]`         |
 *
 * Elementor's shape is a consequence of `ElementorFrontend::onSubmit()`
 * storing the whole `$_POST` parameter dict — the form values sit one
 * level below `post_id`, `form_id` and the rest. Avada's is its own
 * `OnSubmit` overriding the flat content with a wrapper that also
 * carries `field_labels` and `field_types`.
 *
 * Before this class the unwrap chain was written out twice: in
 * `OptInFrontend::addPlaceholders()`, which knew all four, and in the
 * audit reader, which knew two. The gap between the two copies was the
 * customer report of 2026-08-27 — every Elementor opt-in claimed the
 * consent checkbox had not been ticked, because the reader looked only
 * at the top level and at `data`.
 *
 * Lookups probe the top level BEFORE unwrapping, so a flat form that
 * happens to own a field named `data` or `fields` still resolves to its
 * own value instead of being mistaken for a wrapper.
 */
final class SubmittedContent {

	/**
	 * Wrapper keys in probe order. `form_fields` first: an Elementor
	 * parameter dict can pick up a `data` sibling from a third-party
	 * filter, and the values the visitor typed are the Elementor ones.
	 *
	 * @var string[]
	 */
	private const WRAPPER_KEYS = array( 'form_fields', 'fields', 'data' );

	/**
	 * The level the submitted values actually live on.
	 *
	 * @param mixed $content Deserialised `content` column. Anything
	 *                       that is not an array yields an empty map —
	 *                       pre-4.0 rows can carry `content = ''`.
	 *
	 * @return array<string,mixed>
	 */
	public static function unwrapFields( $content ): array {
		if ( ! is_array( $content ) ) {
			return array();
		}

		foreach ( self::WRAPPER_KEYS as $key ) {
			// Only an array counts as a wrapper. A text field literally
			// named "data" must not turn its own value into the field map.
			if ( isset( $content[ $key ] ) && is_array( $content[ $key ] ) ) {
				return $content[ $key ];
			}
		}

		return $content;
	}

	/**
	 * Which wrapper the values were found under — for diagnostics only.
	 *
	 * @param mixed $content Deserialised `content` column.
	 */
	public static function describeShape( $content ): string {
		if ( ! is_array( $content ) ) {
			return 'top-level';
		}

		foreach ( self::WRAPPER_KEYS as $key ) {
			if ( isset( $content[ $key ] ) && is_array( $content[ $key ] ) ) {
				return $key;
			}
		}

		return 'top-level';
	}

	/**
	 * Read one submitted field, whatever shape it was stored in.
	 *
	 * Spelling is forgiving on purpose. Until 5.3.2 the form settings
	 * pushed the configured field name through `sanitize_key()`, which
	 * lowercases — so a site that configured Elementor's `Datenschutz`
	 * has `datenschutz` in post_meta while the submission carries the
	 * original spelling. Those rows have to keep resolving, or the fix
	 * would only help opt-ins created after the update.
	 *
	 * An exact match always wins over a differently-spelled one.
	 *
	 * @param mixed  $content   Deserialised `content` column.
	 * @param string $fieldName The configured field name.
	 *
	 * @return mixed The submitted value, or null when the field is absent.
	 */
	public static function findValue( $content, string $fieldName ) {
		if ( $fieldName === '' || ! is_array( $content ) ) {
			return null;
		}

		$levels = array( $content );
		$fields = self::unwrapFields( $content );
		if ( $fields !== $content ) {
			$levels[] = $fields;
		}

		foreach ( $levels as $level ) {
			if ( array_key_exists( $fieldName, $level ) ) {
				return $level[ $fieldName ];
			}
		}

		$needle = strtolower( $fieldName );
		foreach ( $levels as $level ) {
			foreach ( $level as $key => $value ) {
				if ( strtolower( (string) $key ) === $needle ) {
					return $value;
				}
			}
		}

		return null;
	}

	/**
	 * Did the visitor actually put something into this field?
	 *
	 * Used for the consent proof, so the bar is "not empty": an
	 * unticked checkbox reaches us as `''`, `'0'` or not at all
	 * depending on the form system, and none of those is consent.
	 *
	 * @param mixed  $content   Deserialised `content` column.
	 * @param string $fieldName The configured field name.
	 */
	public static function hasValue( $content, string $fieldName ): bool {
		return ! empty( self::findValue( $content, $fieldName ) );
	}

	/**
	 * Reconcile a stored field name with the names a form actually has.
	 *
	 * Answers "which of these fields did the admin mean?" for a value
	 * that may have been lowercased by the pre-5.3.2 sanitiser. Returns
	 * an empty string when the field is genuinely gone — the settings
	 * page then keeps warning about it, which is correct.
	 *
	 * @param string            $wanted         The stored field name.
	 * @param array<int,mixed>  $availableNames The live form's field names.
	 *
	 * @return string The canonical name, or '' when nothing matches.
	 */
	public static function matchFieldName( string $wanted, array $availableNames ): string {
		if ( $wanted === '' ) {
			return '';
		}

		$names = array();
		foreach ( $availableNames as $name ) {
			$names[] = (string) $name;
		}

		if ( in_array( $wanted, $names, true ) ) {
			return $wanted;
		}

		$needle = strtolower( $wanted );
		foreach ( $names as $name ) {
			if ( strtolower( $name ) === $needle ) {
				return $name;
			}
		}

		return '';
	}
}
