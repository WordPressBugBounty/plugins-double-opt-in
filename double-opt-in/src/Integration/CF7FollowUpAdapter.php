<?php
/**
 * Follow-up adapter for Contact Form 7.
 *
 * @package Forge12\DoubleOptIn\Integration
 * @since   5.6.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Integration;

use Forge12\DoubleOptIn\FollowUp\FollowUpAction;
use Forge12\DoubleOptIn\FollowUp\FollowUpAdapterInterface;
use Forge12\DoubleOptIn\FollowUp\FollowUpAttempt;
use Forge12\DoubleOptIn\FollowUp\FollowUpResult;
use Forge12\DoubleOptIn\FollowUp\FollowUpStatus;
use forge12\contactform7\CF7DoubleOptIn\OptIn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CF7 has one observable result per submission: `get_status()` of the
 * WPCF7_Submission. Mail 2 and storage extensions (Flamingo, CFDB7) run
 * inside that same submission and report nothing of their own, so the
 * follow-up is one action, `cf7:submission`.
 *
 * CF7 itself stores no submissions — no entry is promised here.
 */
final class CF7FollowUpAdapter implements FollowUpAdapterInterface {

	public const ACTION_ID = 'cf7:submission';

	/** @var CF7Integration */
	private $integration;

	public function __construct( CF7Integration $integration ) {
		$this->integration = $integration;
	}

	public function getIntegration(): string {
		return 'cf7';
	}

	/**
	 * {@inheritdoc}
	 */
	public function planActions( OptIn $optIn ): array {
		return array(
			new FollowUpAction( self::ACTION_ID, FollowUpAction::KIND_MAIL, 'Contact Form 7 mail' ),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function execute( OptIn $optIn, array $actions, FollowUpAttempt $attempt ): array {
		$result = AbstractFormIntegration::runAsReplay(
			function () use ( $optIn ) {
				return $this->integration->replaySubmission( $optIn );
			}
		);

		$out = array();
		foreach ( $actions as $action ) {
			$out[ $action->getId() ] = $result;
		}
		return $out;
	}

	/**
	 * Map CF7's submission status. Pure — unit-tested directly.
	 */
	public static function mapStatus( string $status ): FollowUpResult {
		switch ( $status ) {
			case 'mail_sent':
				return FollowUpResult::succeeded( 'cf7_mail_sent' );
			case 'mail_failed':
				// Mail 1 was not handed over (CF7 sends Mail 2 only after
				// Mail 1 succeeded). Storage extensions on wpcf7_submit may
				// still have written a record, so no automatic retry.
				return FollowUpResult::failedPermanent( 'mail_handoff_failed' );
			case 'spam':
				return FollowUpResult::failedPermanent( 'spam_rejected' );
			case 'validation_failed':
			case 'acceptance_missing':
				return FollowUpResult::failedPermanent( 'validation_failed' );
			case 'aborted':
				return FollowUpResult::failedPermanent( 'submission_aborted' );
			default:
				return FollowUpResult::unknown( 'action_outcome_unknown' );
		}
	}

	/**
	 * {@inheritdoc}
	 *
	 * Pending attachment copies go only after the mail that attaches
	 * them was handed over.
	 */
	public function onSettled( OptIn $optIn, array $statusByAction ): void {
		if ( ( $statusByAction[ self::ACTION_ID ] ?? '' ) === FollowUpStatus::SUCCEEDED ) {
			$this->integration->processFilesOnConfirm( '', $optIn );
		}
	}
}
