<?php
/**
 * Add-ons too old for this Core.
 *
 * Core 5.8 no longer ships the email template routes and the opt-out page
 * generator; the add-ons bring them since email editor 1.1.0 and opt-out
 * 1.4.0 (wordpress.org guideline 5). An older add-on keeps loading without
 * an error, but the email editor shows no templates and "Create opt-out
 * page" answers that the add-on is inactive. This check says why and what
 * to do.
 *
 * @package Forge12\DoubleOptIn\Health
 * @since   5.8.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Health;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AddonCompatibilityCheck implements HealthCheckInterface {

	/**
	 * Add-on version constant => [display name, minimum version, what breaks].
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	private static function requirements(): array {
		return array(
			'F12_DOI_EMAIL_EDITOR_VERSION' => array( __( 'Email Editor', 'double-opt-in' ), '1.1.0' ),
			'F12_DOI_OPT_OUT_VERSION'      => array( __( 'Opt-Out', 'double-opt-in' ), '1.4.0' ),
		);
	}

	/** @var callable(string):?string */
	private $versionOf;

	/**
	 * @param callable|null $versionOf Returns the value of a version constant,
	 *                                 or null when the add-on is not loaded (test seam).
	 */
	public function __construct( ?callable $versionOf = null ) {
		$this->versionOf = $versionOf ?? static function ( string $constant ): ?string {
			return defined( $constant ) ? (string) constant( $constant ) : null;
		};
	}

	public function getId(): string {
		return 'f12_doi_addon_compatibility';
	}

	public function getLabel(): string {
		return __( 'Double Opt-In add-on versions', 'double-opt-in' );
	}

	public function getPackage(): string {
		return 'core';
	}

	public function run(): HealthCheckResult {
		$outdated = array();
		foreach ( self::requirements() as $constant => $requirement ) {
			$installed = call_user_func( $this->versionOf, $constant );
			if ( $installed !== null && version_compare( $installed, $requirement[1], '<' ) ) {
				$outdated[] = sprintf(
					/* translators: 1: add-on name, 2: installed version, 3: required version */
					__( '%1$s %2$s (needs %3$s)', 'double-opt-in' ),
					$requirement[0],
					$installed,
					$requirement[1]
				);
			}
		}

		if ( $outdated === array() ) {
			return new HealthCheckResult(
				HealthCheckResult::STATUS_GOOD,
				__( 'Double Opt-In add-ons match this version', 'double-opt-in' ),
				'',
				'ok'
			);
		}

		return new HealthCheckResult(
			HealthCheckResult::STATUS_RECOMMENDED,
			__( 'Update the Double Opt-In add-ons', 'double-opt-in' ),
			sprintf(
				/* translators: %s: comma-separated list of add-ons with versions */
				__( 'These add-ons are older than this version of Double Opt-In expects: %s. Until they are updated, the email editor shows no templates and "Create opt-out page" does not work. Your templates, settings and opt-ins are not affected.', 'double-opt-in' ),
				implode( ', ', $outdated )
			),
			'outdated:' . count( $outdated ),
			__( 'Go to updates', 'double-opt-in' ),
			admin_url( 'update-core.php' )
		);
	}
}
