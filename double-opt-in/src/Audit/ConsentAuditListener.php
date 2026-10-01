<?php
/**
 * Writes consent changes that happen after the confirmation to the audit log.
 *
 * @package Forge12\DoubleOptIn\Audit
 * @since   5.8.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Audit;

use Forge12\DoubleOptIn\EventSystem\EventDispatcherInterface;
use Forge12\DoubleOptIn\Events\Lifecycle\OptInReOptedInEvent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A re-opt-in used to overwrite the confirmation IP of the original
 * record while keeping its confirmation time, so the proof mixed two
 * events. Since 5.8 the original confirmation stays untouched and the
 * renewed consent is its own audit entry.
 *
 * No address and no IP in the entry: the audit log is not covered by the
 * privacy eraser. The opt-in id ties it to the record.
 */
class ConsentAuditListener {

	/** @var callable(string, string, string, array<string, mixed>):mixed */
	private $log;

	/**
	 * @param callable|null $log Defaults to AuditLogger::log.
	 */
	public function __construct( ?callable $log = null ) {
		$this->log = $log ?? array( AuditLogger::class, 'log' );
	}

	public function register( EventDispatcherInterface $dispatcher ): void {
		$dispatcher->addListener( OptInReOptedInEvent::class, array( $this, 'onReOptedIn' ) );
	}

	public function onReOptedIn( OptInReOptedInEvent $event ): void {
		call_user_func(
			$this->log,
			AuditLogger::TYPE_CONSENT,
			AuditLogger::SEVERITY_INFO,
			sprintf(
				/* translators: %d: opt-in ID */
				__( 'Consent for opt-in #%d given again from the opt-out list.', 'double-opt-in' ),
				$event->getOptInId()
			),
			array(
				'optin_id' => $event->getOptInId(),
				'form_id'  => $event->getFormId(),
				'action'   => 're_opt_in',
			)
		);
	}
}
