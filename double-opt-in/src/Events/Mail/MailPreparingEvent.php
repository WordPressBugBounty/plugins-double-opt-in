<?php
/**
 * Mail Preparing Event
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
 * Class MailPreparingEvent
 *
 * Dispatched before sending the opt-in verification email.
 * Listeners can modify the email content, subject, and recipients.
 */
class MailPreparingEvent extends Event {

	private int $optInId;
	private string $recipient;
	private string $subject;
	private string $body;
	private string $sender;
	private string $senderName;
	private array $headers = [];
	private array $attachments = [];
	private bool $shouldSend = true;

	/**
	 * Constructor.
	 *
	 * @param int    $optInId    The opt-in record ID.
	 * @param string $recipient  The recipient email.
	 * @param string $subject    The email subject.
	 * @param string $body       The email body.
	 * @param string $sender     The sender email.
	 * @param string $senderName The sender name.
	 */
	public function __construct(
		int $optInId,
		string $recipient,
		string $subject,
		string $body,
		string $sender,
		string $senderName
	) {
		parent::__construct();
		$this->optInId    = $optInId;
		$this->recipient  = $recipient;
		$this->subject    = $subject;
		$this->body       = $body;
		$this->sender     = $sender;
		$this->senderName = $senderName;
	}

	/**
	 * {@inheritdoc}
	 */
	public static function getWordPressHookName(): string {
		return 'f12_cf7_doubleoptin_mail_preparing';
	}

	// Getters
	public function getOptInId(): int {
		return $this->optInId;
	}

	public function getRecipient(): string {
		return $this->recipient;
	}

	public function getSubject(): string {
		return $this->subject;
	}

	public function getBody(): string {
		return $this->body;
	}

	public function getSender(): string {
		return $this->sender;
	}

	public function getSenderName(): string {
		return $this->senderName;
	}

	public function getHeaders(): array {
		return $this->headers;
	}

	public function getAttachments(): array {
		return $this->attachments;
	}

	public function shouldSend(): bool {
		return $this->shouldSend;
	}

	// Setters for modification by listeners
	public function setRecipient( string $recipient ): self {
		$this->recipient = $recipient;
		return $this;
	}

	public function setSubject( string $subject ): self {
		$this->subject = $subject;
		return $this;
	}

	public function setBody( string $body ): self {
		$this->body = $body;
		return $this;
	}

	public function setSender( string $sender ): self {
		$this->sender = $sender;
		return $this;
	}

	public function setSenderName( string $name ): self {
		$this->senderName = $name;
		return $this;
	}

	public function addHeader( string $header ): self {
		$this->headers[] = $header;
		return $this;
	}

	public function addAttachment( string $path ): self {
		$this->attachments[] = $path;
		return $this;
	}

	public function cancelSending(): self {
		$this->shouldSend = false;
		return $this;
	}
}
