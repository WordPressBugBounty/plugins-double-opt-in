<?php
/**
 * OptIn Expired Event
 *
 * @package Forge12\DoubleOptIn\Events\Lifecycle
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Events\Lifecycle;

use Forge12\DoubleOptIn\EventSystem\Event;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OptInExpiredEvent
 *
 * Dispatched when opt-in records are cleaned up by the cron job.
 */
class OptInExpiredEvent extends Event {

	private string $cleanupType;
	private int $rowsDeleted;
	private \DateTimeImmutable $threshold;

	/**
	 * Constructor.
	 *
	 * @param string             $cleanupType  The type: 'confirmed' or 'unconfirmed'.
	 * @param int                $rowsDeleted  Number of records deleted.
	 * @param \DateTimeImmutable $threshold    The cutoff date/time.
	 */
	public function __construct(
		string $cleanupType,
		int $rowsDeleted,
		\DateTimeImmutable $threshold
	) {
		parent::__construct();
		$this->cleanupType = $cleanupType;
		$this->rowsDeleted = $rowsDeleted;
		$this->threshold   = $threshold;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getWordPressHookName(): string {
		return 'f12_cf7_doubleoptin_expired';
	}

	public function getCleanupType(): string {
		return $this->cleanupType;
	}

	public function getRowsDeleted(): int {
		return $this->rowsDeleted;
	}

	public function getThreshold(): \DateTimeImmutable {
		return $this->threshold;
	}
}
