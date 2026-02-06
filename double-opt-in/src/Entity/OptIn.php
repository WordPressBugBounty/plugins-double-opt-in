<?php
/**
 * OptIn Entity
 *
 * Pure data object with no database logic or side effects.
 *
 * @package Forge12\DoubleOptIn\Entity
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Entity;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OptIn
 *
 * Immutable-style entity representing an opt-in record.
 * Use withX() methods to create modified copies.
 */
class OptIn {

	private int $id = 0;
	private int $formId = 0;
	private bool $confirmed = false;
	private string $content = '';
	private string $hash = '';
	private int $createTime = 0;
	private int $updateTime = 0;
	private int $optOutTime = 0;
	private string $ipRegister = '';
	private string $ipConfirmation = '';
	private string $ipOptOut = '';
	private string $files = '';
	private int $category = 0;
	private string $form = '';
	private string $mailOptIn = '';
	private string $email = '';
	private string $consentText = '';
	private int $reminderSentAt = 0;
	private string $mailReminder = '';

	/**
	 * Private constructor - use static factory methods.
	 */
	private function __construct() {
	}

	/**
	 * Parse a timestamp value from the database.
	 *
	 * Supports both Unix timestamps (numeric strings) and ISO 8601 datetime strings.
	 *
	 * @param mixed $value The timestamp value from the database.
	 *
	 * @return int The Unix timestamp.
	 */
	private static function parseTimestamp( $value ): int {
		if ( empty( $value ) || $value === '0' ) {
			return 0;
		}
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}
		$ts = strtotime( $value );
		return $ts !== false ? $ts : 0;
	}

	/**
	 * Create an OptIn entity from a database row array.
	 *
	 * @param array $data The database row.
	 *
	 * @return self
	 */
	public static function fromArray( array $data ): self {
		$optIn = new self();

		$optIn->id             = (int) ( $data['id'] ?? 0 );
		$optIn->formId         = (int) ( $data['cf_form_id'] ?? 0 );
		$optIn->confirmed      = (bool) ( $data['doubleoptin'] ?? false );
		$optIn->content        = (string) ( $data['content'] ?? '' );
		$optIn->hash           = (string) ( $data['hash'] ?? '' );
		$optIn->createTime     = self::parseTimestamp( $data['createtime'] ?? 0 );
		$optIn->updateTime     = self::parseTimestamp( $data['updatetime'] ?? 0 );
		$optIn->optOutTime     = self::parseTimestamp( $data['optouttime'] ?? 0 );
		$optIn->ipRegister     = (string) ( $data['ipaddr_register'] ?? '' );
		$optIn->ipConfirmation = (string) ( $data['ipaddr_confirmation'] ?? '' );
		$optIn->ipOptOut       = (string) ( $data['ipaddr_optout'] ?? '' );
		$optIn->files          = (string) ( $data['files'] ?? '' );
		$optIn->category       = (int) ( $data['category'] ?? 0 );
		$optIn->form           = (string) ( $data['form'] ?? '' );
		$optIn->mailOptIn      = (string) ( $data['mail_optin'] ?? '' );
		$optIn->email          = (string) ( $data['email'] ?? '' );
		$optIn->consentText    = (string) ( $data['consent_text'] ?? '' );
		$optIn->reminderSentAt = self::parseTimestamp( $data['reminder_sent_at'] ?? 0 );
		$optIn->mailReminder   = (string) ( $data['mail_reminder'] ?? '' );

		return $optIn;
	}

	/**
	 * Create a new empty OptIn entity.
	 *
	 * @return self
	 */
	public static function create(): self {
		$optIn             = new self();
		$optIn->createTime = time();
		return $optIn;
	}

	/**
	 * Convert entity to database array format.
	 *
	 * @return array
	 */
	public function toArray(): array {
		return [
			'id'                   => $this->id,
			'cf_form_id'           => $this->formId,
			'doubleoptin'          => (int) $this->confirmed,
			'content'              => $this->content,
			'hash'                 => $this->hash,
			'createtime'           => $this->createTime > 0 ? gmdate( 'Y-m-d H:i:s', $this->createTime ) : '',
			'updatetime'           => $this->updateTime > 0 ? gmdate( 'Y-m-d H:i:s', $this->updateTime ) : '',
			'optouttime'           => $this->optOutTime > 0 ? gmdate( 'Y-m-d H:i:s', $this->optOutTime ) : '',
			'ipaddr_register'      => $this->ipRegister,
			'ipaddr_confirmation'  => $this->ipConfirmation,
			'ipaddr_optout'        => $this->ipOptOut,
			'files'                => $this->files,
			'category'             => $this->category,
			'form'                 => $this->form,
			'mail_optin'           => $this->mailOptIn,
			'email'                => $this->email,
			'consent_text'         => $this->consentText,
			'reminder_sent_at'     => $this->reminderSentAt > 0 ? gmdate( 'Y-m-d H:i:s', $this->reminderSentAt ) : '',
			'mail_reminder'        => $this->mailReminder,
		];
	}

	/**
	 * Convert to array for database insert (excludes id).
	 *
	 * @return array
	 */
	public function toInsertArray(): array {
		$data = $this->toArray();
		unset( $data['id'] );
		return $data;
	}

	// =========================================================================
	// GETTERS
	// =========================================================================

	public function getId(): int {
		return $this->id;
	}

	public function getFormId(): int {
		return $this->formId;
	}

	public function isConfirmed(): bool {
		return $this->confirmed;
	}

	public function getContent(): string {
		return $this->content;
	}

	/**
	 * Get content as unserialized array.
	 *
	 * @return array
	 */
	public function getContentArray(): array {
		$data = maybe_unserialize( $this->content );
		return is_array( $data ) ? $data : [];
	}

	public function getHash(): string {
		return $this->hash;
	}

	public function getCreateTime(): int {
		return $this->createTime;
	}

	/**
	 * Get formatted create time.
	 *
	 * @param string $format The date format (default: WordPress setting).
	 *
	 * @return string
	 */
	public function getCreateTimeFormatted( string $format = '' ): string {
		if ( empty( $format ) ) {
			$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		}
		return $this->createTime > 0
			? wp_date( $format, $this->createTime )
			: '';
	}

	public function getUpdateTime(): int {
		return $this->updateTime;
	}

	/**
	 * Get formatted update time.
	 *
	 * @param string $format The date format.
	 *
	 * @return string
	 */
	public function getUpdateTimeFormatted( string $format = '' ): string {
		if ( empty( $format ) ) {
			$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		}
		return $this->updateTime > 0
			? wp_date( $format, $this->updateTime )
			: '';
	}

	public function getOptOutTime(): int {
		return $this->optOutTime;
	}

	/**
	 * Get create time as ISO 8601 string.
	 *
	 * @return string
	 */
	public function getCreateTimeISO(): string {
		return $this->createTime > 0 ? gmdate( 'c', $this->createTime ) : '';
	}

	/**
	 * Get update time as ISO 8601 string.
	 *
	 * @return string
	 */
	public function getUpdateTimeISO(): string {
		return $this->updateTime > 0 ? gmdate( 'c', $this->updateTime ) : '';
	}

	/**
	 * Get opt-out time as ISO 8601 string.
	 *
	 * @return string
	 */
	public function getOptOutTimeISO(): string {
		return $this->optOutTime > 0 ? gmdate( 'c', $this->optOutTime ) : '';
	}

	public function getIpRegister(): string {
		return $this->ipRegister;
	}

	public function getIpConfirmation(): string {
		return $this->ipConfirmation;
	}

	public function getIpOptOut(): string {
		return $this->ipOptOut;
	}

	public function getFiles(): string {
		return $this->files;
	}

	/**
	 * Get files as unserialized array.
	 *
	 * @return array
	 */
	public function getFilesArray(): array {
		$data = maybe_unserialize( $this->files );
		return is_array( $data ) ? $data : [];
	}

	public function getCategory(): int {
		return $this->category;
	}

	public function getForm(): string {
		return $this->form;
	}

	public function getMailOptIn(): string {
		return $this->mailOptIn;
	}

	public function getEmail(): string {
		return $this->email;
	}

	public function getConsentText(): string {
		return $this->consentText;
	}

	public function getReminderSentAt(): int {
		return $this->reminderSentAt;
	}

	/**
	 * Get formatted reminder sent time.
	 *
	 * @param string $format The date format (default: WordPress setting).
	 *
	 * @return string
	 */
	public function getReminderSentAtFormatted( string $format = '' ): string {
		if ( empty( $format ) ) {
			$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		}
		return $this->reminderSentAt > 0
			? wp_date( $format, $this->reminderSentAt )
			: '';
	}

	/**
	 * Get reminder sent time as ISO 8601 string.
	 *
	 * @return string
	 */
	public function getReminderSentAtISO(): string {
		return $this->reminderSentAt > 0 ? gmdate( 'c', $this->reminderSentAt ) : '';
	}

	public function getMailReminder(): string {
		return $this->mailReminder;
	}

	/**
	 * Check if a reminder has already been sent.
	 *
	 * @return bool
	 */
	public function hasReminderBeenSent(): bool {
		return $this->reminderSentAt > 0;
	}

	// =========================================================================
	// BUSINESS LOGIC
	// =========================================================================

	/**
	 * Check if this opt-in has been opted out.
	 *
	 * @return bool
	 */
	public function isOptedOut(): bool {
		return ! $this->confirmed
			&& ! empty( $this->ipOptOut )
			&& $this->optOutTime > 0;
	}

	/**
	 * Check if this is a new (unsaved) entity.
	 *
	 * @return bool
	 */
	public function isNew(): bool {
		return $this->id === 0;
	}

	// =========================================================================
	// IMMUTABLE SETTERS (return new instance)
	// =========================================================================

	public function withId( int $id ): self {
		$clone     = clone $this;
		$clone->id = $id;
		return $clone;
	}

	public function withFormId( int $formId ): self {
		$clone         = clone $this;
		$clone->formId = $formId;
		return $clone;
	}

	public function withConfirmed( bool $confirmed ): self {
		$clone            = clone $this;
		$clone->confirmed = $confirmed;
		return $clone;
	}

	public function withContent( string $content ): self {
		$clone          = clone $this;
		$clone->content = $content;
		return $clone;
	}

	/**
	 * Set content from array (will be serialized).
	 *
	 * @param array $data The data to serialize.
	 *
	 * @return self
	 */
	public function withContentArray( array $data ): self {
		$clone          = clone $this;
		$clone->content = maybe_serialize( $data );
		return $clone;
	}

	public function withHash( string $hash ): self {
		$clone       = clone $this;
		$clone->hash = $hash;
		return $clone;
	}

	public function withCreateTime( int $time ): self {
		$clone             = clone $this;
		$clone->createTime = $time;
		return $clone;
	}

	public function withUpdateTime( int $time ): self {
		$clone             = clone $this;
		$clone->updateTime = $time;
		return $clone;
	}

	public function withOptOutTime( int $time ): self {
		$clone             = clone $this;
		$clone->optOutTime = $time;
		return $clone;
	}

	public function withIpRegister( string $ip ): self {
		$clone             = clone $this;
		$clone->ipRegister = $ip;
		return $clone;
	}

	public function withIpConfirmation( string $ip ): self {
		$clone                 = clone $this;
		$clone->ipConfirmation = $ip;
		return $clone;
	}

	public function withIpOptOut( string $ip ): self {
		$clone           = clone $this;
		$clone->ipOptOut = $ip;
		return $clone;
	}

	public function withFiles( string $files ): self {
		$clone        = clone $this;
		$clone->files = $files;
		return $clone;
	}

	/**
	 * Set files from array (will be serialized).
	 *
	 * @param array $files The files array.
	 *
	 * @return self
	 */
	public function withFilesArray( array $files ): self {
		$clone        = clone $this;
		$clone->files = maybe_serialize( $files );
		return $clone;
	}

	public function withCategory( int $category ): self {
		$clone           = clone $this;
		$clone->category = $category;
		return $clone;
	}

	public function withForm( string $form ): self {
		$clone       = clone $this;
		$clone->form = $form;
		return $clone;
	}

	public function withMailOptIn( string $mail ): self {
		$clone            = clone $this;
		$clone->mailOptIn = $mail;
		return $clone;
	}

	public function withEmail( string $email ): self {
		$clone        = clone $this;
		$clone->email = $email;
		return $clone;
	}

	public function withConsentText( string $consentText ): self {
		$clone              = clone $this;
		$clone->consentText = $consentText;
		return $clone;
	}

	public function withReminderSentAt( int $time ): self {
		$clone                 = clone $this;
		$clone->reminderSentAt = $time;
		return $clone;
	}

	public function withMailReminder( string $mail ): self {
		$clone               = clone $this;
		$clone->mailReminder = $mail;
		return $clone;
	}

	// =========================================================================
	// MUTABLE SETTERS (for BC compatibility)
	// =========================================================================

	/**
	 * @internal Use withX() methods for new code.
	 */
	public function setId( int $id ): void {
		$this->id = $id;
	}

	/**
	 * @internal Use withX() methods for new code.
	 */
	public function setConfirmed( bool $confirmed ): void {
		$this->confirmed = $confirmed;
	}

	/**
	 * @internal Use withX() methods for new code.
	 */
	public function setHash( string $hash ): void {
		$this->hash = $hash;
	}

	/**
	 * @internal Use withX() methods for new code.
	 */
	public function setUpdateTime( int $time ): void {
		$this->updateTime = $time;
	}

	/**
	 * @internal Use withX() methods for new code.
	 */
	public function setIpConfirmation( string $ip ): void {
		$this->ipConfirmation = $ip;
	}
}
