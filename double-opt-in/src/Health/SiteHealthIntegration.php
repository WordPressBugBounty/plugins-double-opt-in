<?php
/**
 * Surfaces health check results where operators actually look.
 *
 * @package Forge12\DoubleOptIn\Health
 * @since   5.3.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Health;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Three surfaces, one source of truth:
 *
 *  - **Site Health → Status** (`site_status_tests`): the canonical place
 *    a WordPress operator — or a support agent talking one through a
 *    problem — goes to ask "is anything wrong?".
 *  - **Site Health → Info** (`debug_information`): the exportable
 *    report. WordPress' own support handbook points users at it when
 *    asked to send diagnostics, which makes it the cheapest possible
 *    channel for us to receive them.
 *  - **Admin notice**: because a broken precondition should not wait
 *    for someone to think of opening Site Health.
 *
 * All three read the same {@see HealthCheckRegistry}, so a check that
 * is registered once shows up everywhere without further work.
 */
final class SiteHealthIntegration {

	/**
	 * Site Health test ids must be globally unique across all plugins.
	 */
	private const TEST_PREFIX = 'f12_doi_';

	/**
	 * @var HealthCheckRegistry
	 */
	private $registry;

	public function __construct( HealthCheckRegistry $registry ) {
		$this->registry = $registry;
	}

	public function register(): void {
		add_filter( 'site_status_tests', array( $this, 'addStatusTests' ) );
		add_filter( 'debug_information', array( $this, 'addDebugInformation' ) );
		add_action( 'admin_notices', array( $this, 'renderCriticalNotice' ) );
	}

	/**
	 * Contribute one direct test per registered check.
	 *
	 * Direct rather than async: every check we ship is a single indexed
	 * lookup or an option read. Async would add a REST round-trip for
	 * work measured in microseconds.
	 *
	 * @param array<string,array<string,mixed>> $tests
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function addStatusTests( $tests ) {
		if ( ! is_array( $tests ) ) {
			return $tests;
		}
		if ( ! isset( $tests['direct'] ) || ! is_array( $tests['direct'] ) ) {
			$tests['direct'] = array();
		}

		foreach ( $this->registry->all() as $id => $check ) {
			$testId = $this->testId( $id );

			$tests['direct'][ $testId ] = array(
				'label' => $check->getLabel(),
				'test'  => function () use ( $id ) {
					return $this->renderTest( $id );
				},
			);
		}

		return $tests;
	}

	/**
	 * Map one HealthCheckResult onto the array shape WP_Site_Health
	 * expects. Status values are shared vocabulary, so this is a
	 * pass-through rather than a translation.
	 *
	 * @return array<string,mixed>
	 */
	private function renderTest( string $id ): array {
		$results = $this->registry->runAll();
		$result  = $results[ $id ] ?? null;

		if ( ! $result instanceof HealthCheckResult ) {
			return array(
				'label'  => __( 'Double Opt-In', 'double-opt-in' ),
				'status' => HealthCheckResult::STATUS_RECOMMENDED,
				'badge'  => $this->badge(),
				'test'   => $this->testId( $id ),
			);
		}

		$test = array(
			'label'       => $result->getLabel(),
			'status'      => $result->getStatus(),
			'badge'       => $this->badge(),
			'description' => sprintf( '<p>%s</p>', esc_html( $result->getDescription() ) ),
			'test'        => $this->testId( $id ),
		);

		if ( $result->getActionUrl() !== '' && $result->getActionLabel() !== '' ) {
			$test['actions'] = sprintf(
				'<p><a href="%s">%s</a></p>',
				esc_url( $result->getActionUrl() ),
				esc_html( $result->getActionLabel() )
			);
		}

		return $test;
	}

	/**
	 * Shared badge so every DOI test groups under one heading in the
	 * Site Health UI. Colour is WordPress' own "not performance, not
	 * security" neutral.
	 *
	 * @return array<string,string>
	 */
	private function badge(): array {
		return array(
			'label' => __( 'Double Opt-In', 'double-opt-in' ),
			'color' => 'purple',
		);
	}

	private function testId( string $id ): string {
		return strpos( $id, self::TEST_PREFIX ) === 0 ? $id : self::TEST_PREFIX . $id;
	}

	/**
	 * Add a "Double Opt-In" section to Site Health → Info.
	 *
	 * This is what a customer exports and pastes into a support ticket,
	 * so it carries versions alongside the check outcomes. No personal
	 * data — table names and version strings only (see rules/gdpr.md).
	 *
	 * @param array<string,mixed> $info
	 *
	 * @return array<string,mixed>
	 */
	public function addDebugInformation( $info ) {
		if ( ! is_array( $info ) ) {
			return $info;
		}

		$fields = array(
			'core_version'     => array(
				'label' => __( 'Core version', 'double-opt-in' ),
				'value' => defined( 'FORGE12_OPTIN_VERSION' ) ? FORGE12_OPTIN_VERSION : __( 'unknown', 'double-opt-in' ),
			),
			'core_api_version' => array(
				'label' => __( 'Core API version', 'double-opt-in' ),
				'value' => defined( 'F12_DOI_CORE_API_VERSION' ) ? F12_DOI_CORE_API_VERSION : __( 'unknown', 'double-opt-in' ),
			),
		);

		foreach ( $this->registry->runAll() as $id => $result ) {
			$check = $this->registry->all()[ $id ] ?? null;
			$name  = $check instanceof HealthCheckInterface
				? sprintf( '%s (%s)', $check->getLabel(), $check->getPackage() )
				: $id;

			$fields[ $id ] = array(
				'label' => $name,
				'value' => $result->getDebugValue(),
				'debug' => $result->getStatus(),
			);
		}

		$info['f12-double-opt-in'] = array(
			'label'       => __( 'Double Opt-In', 'double-opt-in' ),
			'description' => __( 'Runtime preconditions checked by the Double Opt-In plugin family. Send this section along when you contact support.', 'double-opt-in' ),
			'fields'      => $fields,
		);

		return $info;
	}

	/**
	 * Nag on the screens an operator passes through anyway.
	 *
	 * Deliberately not dismissible: the condition is a hard failure,
	 * and a dismissed notice would hide it until the next customer
	 * complains. It disappears by itself the moment the check passes.
	 */
	public function renderCriticalNotice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! $this->isRelevantScreen() ) {
			return;
		}

		$criticals = $this->registry->criticals();
		if ( empty( $criticals ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p><strong>';
		echo esc_html__( 'Double Opt-In has detected a problem that will affect your visitors.', 'double-opt-in' );
		echo '</strong></p><ul style="list-style:disc;margin-left:1.5em">';

		foreach ( $criticals as $result ) {
			printf(
				'<li><strong>%s</strong><br>%s</li>',
				esc_html( $result->getLabel() ),
				esc_html( $result->getDescription() )
			);
		}

		printf(
			'</ul><p><a class="button button-primary" href="%s">%s</a></p></div>',
			esc_url( admin_url( 'site-health.php' ) ),
			esc_html__( 'Open Site Health', 'double-opt-in' )
		);
	}

	/**
	 * Dashboard, plugins list and the plugin's own screens. Not every
	 * admin page — a site-wide red banner for a scoped problem trains
	 * people to ignore banners.
	 */
	private function isRelevantScreen(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		if ( in_array( $screen->id, array( 'dashboard', 'plugins', 'plugins-network' ), true ) ) {
			return true;
		}

		return strpos( $screen->id, 'f12-doi' ) !== false
			|| strpos( $screen->id, 'f12-cf7-doubleoptin' ) !== false;
	}
}
