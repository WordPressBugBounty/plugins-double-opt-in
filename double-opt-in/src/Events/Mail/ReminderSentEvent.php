<?php
/**
 * Reminder Sent Event
 *
 * @package Forge12\DoubleOptIn\Events\Mail
 * @since   3.4.0
 */

namespace Forge12\DoubleOptIn\Events\Mail;

use Forge12\DoubleOptIn\EventSystem\Event;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ReminderSentEvent
 *
 * Dispatched after a reminder email is sent to an unconfirmed opt-in.
 */
class ReminderSentEvent extends Event {

	private int $optInId;
	private string $recipient;
	private string $subject;
	private bool $success;
	private string $trigger;

	/**
	 * Constructor.
	 *
	 * @param int    $optInId   The opt-in record ID.
	 * @param string $recipient The recipient email.
	 * @param string $subject   The email subject.
	 * @param bool   $success   Whether sending was successful.
	 * @param string $trigger   The trigger source: 'cron' or 'manual'.
	 */
	public function __construct(
		int $optInId,
		string $recipient,
		string $subject,
		bool $success,
		string $trigger = 'cron'
	) {
		parent::__construct();
		$this->optInId   = $optInId;
		$this->recipient = $recipient;
		$this->subject   = $subject;
		$this->success   = $success;
		$this->trigger   = $trigger;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getWordPressHookName(): string {
		return 'f12_cf7_doubleoptin_reminder_sent';
	}

	public function getOptInId(): int {
		return $this->optInId;
	}

	public function getRecipient(): string {
		return $this->recipient;
	}

	public function getSubject(): string {
		return $this->subject;
	}

	public function wasSuccessful(): bool {
		return $this->success;
	}

	public function getTrigger(): string {
		return $this->trigger;
	}
}
