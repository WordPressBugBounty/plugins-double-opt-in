<?php
/**
 * Correlation data for one execution attempt.
 *
 * @package Forge12\DoubleOptIn\FollowUp
 * @since   5.6.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\FollowUp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One attempt groups the actions claimed together in a single run.
 *
 * The attempt id is for correlation only (logs, audit, replay ticket).
 * It is NOT the idempotency key — that is (opt-in id, action id).
 */
final class FollowUpAttempt {

	public const TRIGGER_CONFIRM = 'confirm';
	public const TRIGGER_CRON    = 'cron';
	public const TRIGGER_MANUAL  = 'manual';
	public const TRIGGER_LEGACY  = 'legacy_hook';

	/** @var string */
	private $id;

	/** @var string */
	private $trigger;

	/** @var int */
	private $optInId;

	public function __construct( string $id, string $trigger, int $optInId ) {
		$this->id      = $id;
		$this->trigger = $trigger;
		$this->optInId = $optInId;
	}

	public function getId(): string {
		return $this->id;
	}

	public function getTrigger(): string {
		return $this->trigger;
	}

	public function getOptInId(): int {
		return $this->optInId;
	}
}
