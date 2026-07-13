<?php
/**
 * Semver Constraint Matcher
 *
 * Minimal semver constraint evaluator used to compare an addon's declared
 * core-version requirement (e.g. "^4.3") against the core's actual API
 * version (e.g. "4.3.0"). Does not require composer/semver — keeps the
 * Core plugin's dependency surface minimal.
 *
 * @package Forge12\DoubleOptIn\Versioning
 * @since   4.3.0
 */

namespace Forge12\DoubleOptIn\Versioning;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SemverConstraint
 *
 * @api
 *
 * Supports a deliberately small constraint grammar — enough to express
 * the dependency shapes addons actually need, no more:
 *
 *  - "^X.Y"         — caret: >=X.Y.0 and <(X+1).0.0
 *  - "^X.Y.Z"       — caret: >=X.Y.Z and <(X+1).0.0
 *  - "~X.Y"         — tilde: >=X.Y.0 and <X.(Y+1).0
 *  - "~X.Y.Z"       — tilde: >=X.Y.Z and <X.(Y+1).0
 *  - ">=X.Y[.Z]"    — inclusive lower bound
 *  - "<=X.Y[.Z]"    — inclusive upper bound
 *  - ">X.Y[.Z]"     — exclusive lower bound
 *  - "<X.Y[.Z]"     — exclusive upper bound
 *  - "=X.Y[.Z]"     — exact match (alias: no operator, e.g. "X.Y.Z")
 *
 * Unsupported, by design: OR clauses, pre-release suffixes, wildcard
 * ranges, spaces in constraints. If an addon needs those, it has picked
 * the wrong dependency language.
 */
final class SemverConstraint {

	/**
	 * Check if a version satisfies a constraint.
	 *
	 * @param string $current    Current version (e.g. "4.3.0").
	 * @param string $constraint Constraint (e.g. "^4.3").
	 * @return bool True if $current satisfies $constraint. Returns false
	 *              for malformed constraints — callers should treat a
	 *              false return as "does not match" without needing to
	 *              distinguish "malformed" from "not matching".
	 */
	public static function matches( string $current, string $constraint ): bool {
		$current    = trim( $current );
		$constraint = trim( $constraint );

		if ( $current === '' || $constraint === '' ) {
			return false;
		}

		// Caret: ^X.Y or ^X.Y.Z
		if ( preg_match( '/^\^(\d+)\.(\d+)(?:\.(\d+))?$/', $constraint, $m ) ) {
			$min      = $m[1] . '.' . $m[2] . '.' . ( $m[3] ?? '0' );
			$maxMajor = (int) $m[1] + 1;
			$max      = $maxMajor . '.0.0';

			return version_compare( $current, $min, '>=' )
				&& version_compare( $current, $max, '<' );
		}

		// Tilde: ~X.Y or ~X.Y.Z
		if ( preg_match( '/^~(\d+)\.(\d+)(?:\.(\d+))?$/', $constraint, $m ) ) {
			$min      = $m[1] . '.' . $m[2] . '.' . ( $m[3] ?? '0' );
			$maxMinor = (int) $m[2] + 1;
			$max      = $m[1] . '.' . $maxMinor . '.0';

			return version_compare( $current, $min, '>=' )
				&& version_compare( $current, $max, '<' );
		}

		// Comparison operator: >=, <=, >, <, =, or no operator (exact)
		if ( preg_match( '/^(>=|<=|>|<|=)?\s*(\d+\.\d+(?:\.\d+)?)$/', $constraint, $m ) ) {
			$op      = $m[1] !== '' ? $m[1] : '=';
			$version = $m[2];

			return version_compare( $current, $version, $op );
		}

		return false;
	}
}
