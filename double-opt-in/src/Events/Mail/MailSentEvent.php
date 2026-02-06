<?php
/**
 * Mail Sent Event
 *
 * @package Forge12\DoubleOptIn\Events\Mail
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Events\Mail;

use Forge12\DoubleOptIn\EventSystem\Event;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MailSentEvent
 *
 * Dispatched after an opt-in verification email is sent.
 */
class MailSentEvent extends Event {

	private int $optInId;
	private string $recipient;
	private string $subject;
	private bool $success;
	private string $mailType;

	/**
	 * Constructor.
	 *
	 * @param int    $optInId   The opt-in record ID.
	 * @param string $recipient The recipient email.
	 * @param string $subject   The email subject.
	 * @param bool   $success   Whether sending was successful.
	 * @param string $mailType  The type: 'optin' or 'confirmation'.
	 */
	public function __construct(
		int $optInId,
		string $recipient,
		string $subject,
		bool $success,
		string $mailType = 'optin'
	) {
		parent::__construct();
		$this->optInId   = $optInId;
		$this->recipient = $recipient;
		$this->subject   = $subject;
		$this->success   = $success;
		$this->mailType  = $mailType;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getWordPressHookName(): string {
		return 'f12_cf7_doubleoptin_mail_sent';
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

	public function getMailType(): string {
		return $this->mailType;
	}
}
