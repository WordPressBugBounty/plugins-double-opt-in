<?php
/**
 * OptIn Deleted Event
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
 * Class OptInDeletedEvent
 *
 * Dispatched when an opt-in record is deleted.
 */
class OptInDeletedEvent extends Event {

	private string $hash;
	private string $email;
	private string $deletedBy;
	private int $rowsDeleted;

	/**
	 * Constructor.
	 *
	 * @param string $hash        The opt-in hash.
	 * @param string $email       The subscriber email.
	 * @param string $deletedBy   Who deleted it (admin, cron, user).
	 * @param int    $rowsDeleted Number of rows deleted.
	 */
	public function __construct(
		string $hash,
		string $email,
		string $deletedBy,
		int $rowsDeleted = 1
	) {
		parent::__construct();
		$this->hash        = $hash;
		$this->email       = $email;
		$this->deletedBy   = $deletedBy;
		$this->rowsDeleted = $rowsDeleted;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getWordPressHookName(): string {
		return 'f12_cf7_doubleoptin_deleted';
	}

	public function getHash(): string {
		return $this->hash;
	}

	public function getEmail(): string {
		return $this->email;
	}

	public function getDeletedBy(): string {
		return $this->deletedBy;
	}

	public function getRowsDeleted(): int {
		return $this->rowsDeleted;
	}
}
