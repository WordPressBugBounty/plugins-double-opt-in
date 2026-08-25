<?php
/**
 * Result value object for a health check.
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
 * Immutable outcome of one {@see HealthCheckInterface::run()}.
 *
 * The three status values mirror WordPress' own Site Health vocabulary
 * (`good` / `recommended` / `critical`) so the mapping into
 * `site_status_tests` stays a straight pass-through — no translation
 * table that could drift.
 */
final class HealthCheckResult {

	public const STATUS_GOOD        = 'good';
	public const STATUS_RECOMMENDED = 'recommended';
	public const STATUS_CRITICAL    = 'critical';

	/**
	 * One of the STATUS_* constants.
	 *
	 * @var string
	 */
	private $status;

	/**
	 * Headline shown in the Site Health accordion row.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Plain-text explanation. Escaped by the renderer — pass text, not
	 * HTML, so the same string can be reused in the admin notice and in
	 * the Site Health → Info tab without double-escaping surprises.
	 *
	 * @var string
	 */
	private $description;

	/**
	 * Optional call to action.
	 *
	 * @var string
	 */
	private $actionLabel;

	/**
	 * Optional target for the call to action.
	 *
	 * @var string
	 */
	private $actionUrl;

	/**
	 * Compact machine value for the Site Health → Info export, e.g.
	 * `missing` / `ok`. Kept separate from the description so support
	 * can scan a report without reading prose.
	 *
	 * @var string
	 */
	private $debugValue;

	public function __construct(
		string $status,
		string $label,
		string $description = '',
		string $debugValue = '',
		string $actionLabel = '',
		string $actionUrl = ''
	) {
		$allowed      = array( self::STATUS_GOOD, self::STATUS_RECOMMENDED, self::STATUS_CRITICAL );
		$this->status = in_array( $status, $allowed, true ) ? $status : self::STATUS_RECOMMENDED;

		$this->label       = $label;
		$this->description = $description;
		$this->debugValue  = $debugValue !== '' ? $debugValue : $this->status;
		$this->actionLabel = $actionLabel;
		$this->actionUrl   = $actionUrl;
	}

	public function getStatus(): string {
		return $this->status;
	}

	public function getLabel(): string {
		return $this->label;
	}

	public function getDescription(): string {
		return $this->description;
	}

	public function getDebugValue(): string {
		return $this->debugValue;
	}

	public function getActionLabel(): string {
		return $this->actionLabel;
	}

	public function getActionUrl(): string {
		return $this->actionUrl;
	}

	public function isCritical(): bool {
		return $this->status === self::STATUS_CRITICAL;
	}

	public function isGood(): bool {
		return $this->status === self::STATUS_GOOD;
	}
}
