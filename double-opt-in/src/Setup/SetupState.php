<?php
/**
 * Where the setup wizard stands, and what has been entered so far.
 *
 * Every step saves immediately, so leaving the wizard halfway loses nothing.
 * Forms are only switched on when the wizard finishes: the completeness gate
 * needs recipient, subject and body together, and the body only exists after
 * step 3.
 *
 * A missing option means the site was installed before the wizard existed.
 * Such sites never see the wizard unless someone starts it from the settings.
 *
 * @package Forge12\DoubleOptIn\Setup
 * @since   5.7.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Setup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SetupState {

	public const OPTION = 'f12_doi_setup';

	public const PENDING = 'pending';
	public const SKIPPED = 'skipped';
	public const DONE    = 'done';

	/** Number of steps with input; the summary after them is not counted. */
	public const STEPS = 4;

	public function exists(): bool {
		return is_array( get_option( self::OPTION, null ) );
	}

	/**
	 * @return array{status: string, step: int, draft: array<string, mixed>, updated_at: int}
	 */
	public function get(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$status = isset( $stored['status'] ) && in_array( $stored['status'], array( self::PENDING, self::SKIPPED, self::DONE ), true )
			? (string) $stored['status']
			: self::PENDING;

		return array(
			'status'     => $status,
			'step'       => max( 0, min( self::STEPS, (int) ( $stored['step'] ?? 0 ) ) ),
			'draft'      => isset( $stored['draft'] ) && is_array( $stored['draft'] ) ? $stored['draft'] : array(),
			'updated_at' => (int) ( $stored['updated_at'] ?? 0 ),
		);
	}

	public function status(): string {
		return $this->get()['status'];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function draft(): array {
		return $this->get()['draft'];
	}

	/**
	 * Start tracking a fresh installation. Does nothing when a state exists.
	 */
	public function initialize(): void {
		if ( $this->exists() ) {
			return;
		}
		$this->write( self::PENDING, 0, array() );
	}

	/**
	 * Store the values of one step and move the pointer past it.
	 *
	 * @param int                  $completedStep Zero-based index of the step just saved.
	 * @param array<string, mixed> $values        Draft values of that step.
	 */
	public function saveStep( int $completedStep, array $values ): void {
		$state = $this->get();
		$next  = max( $state['step'], min( self::STEPS, $completedStep + 1 ) );
		$this->write( $state['status'] === self::DONE ? self::DONE : self::PENDING, $next, array_merge( $state['draft'], $values ) );
	}

	/**
	 * Merge values into the draft without moving the step pointer.
	 *
	 * @param array<string, mixed> $values
	 */
	public function remember( array $values ): void {
		$state = $this->get();
		$this->write( $state['status'], $state['step'], array_merge( $state['draft'], $values ) );
	}

	public function skip(): void {
		$state = $this->get();
		if ( $state['status'] === self::DONE ) {
			return;
		}
		$this->write( self::SKIPPED, $state['step'], $state['draft'] );
	}

	public function complete(): void {
		$state = $this->get();
		$this->write( self::DONE, self::STEPS, $state['draft'] );
	}

	/**
	 * Run the wizard again. The draft is kept so the fields come back filled in.
	 */
	public function restart(): void {
		$this->write( self::PENDING, 0, $this->get()['draft'] );
	}

	/**
	 * @param array<string, mixed> $draft
	 */
	private function write( string $status, int $step, array $draft ): void {
		update_option(
			self::OPTION,
			array(
				'status'     => $status,
				'step'       => $step,
				'draft'      => $draft,
				'updated_at' => time(),
			),
			false
		);
	}
}
