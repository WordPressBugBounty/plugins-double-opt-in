<?php
/**
 * "Many of your opt-ins are not confirmed" — the numbers behind the
 * dashboard card.
 *
 * Looks at the last 30 days minus the last 24 hours (those can still be
 * confirmed) and speaks up only when there is enough data and a real
 * share is stuck. Also counts the confirmation mails that failed in the
 * same period, which is the first thing to fix.
 *
 * @package Forge12\DoubleOptIn\Service
 * @since   5.8.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Service;

use Forge12\DoubleOptIn\Repository\OptInMailStatusRepository;
use Forge12\DoubleOptIn\Repository\OptInStatsRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StuckOptInsReport {

	public const WINDOW_DAYS = 30;

	public const GRACE_SECONDS = 86400;

	public const MIN_OPTINS = 10;

	public const MIN_SHARE = 30;

	/** @var OptInStatsRepository */
	private $stats;

	/** @var OptInMailStatusRepository */
	private $mailStatus;

	/** @var callable(): int */
	private $clock;

	public function __construct( OptInStatsRepository $stats, OptInMailStatusRepository $mailStatus, ?callable $clock = null ) {
		$this->stats      = $stats;
		$this->mailStatus = $mailStatus;
		$this->clock      = $clock ?? 'time';
	}

	/**
	 * The report, or null when there is nothing worth saying.
	 *
	 * @return array{days: int, total: int, unconfirmed: int, share: int, mailFailed: int}|null
	 */
	public function report(): ?array {
		$now    = (int) call_user_func( $this->clock );
		$window = $this->stats->confirmationWindow( $now - self::WINDOW_DAYS * 86400, $now - self::GRACE_SECONDS );

		if ( $window['total'] < self::MIN_OPTINS ) {
			return null;
		}

		$share = (int) round( $window['unconfirmed'] * 100 / $window['total'] );
		if ( $share < self::MIN_SHARE ) {
			return null;
		}

		return array(
			'days'        => self::WINDOW_DAYS,
			'total'       => $window['total'],
			'unconfirmed' => $window['unconfirmed'],
			'share'       => $share,
			'mailFailed'  => $this->mailStatus->countFailedSince( self::WINDOW_DAYS * 86400 ),
		);
	}

	/**
	 * `f12_doi_rest_dashboard_stats`: add `stuckOptins`.
	 *
	 * @param mixed $data Dashboard payload.
	 *
	 * @return mixed
	 */
	public function addToDashboard( $data ) {
		if ( is_array( $data ) ) {
			$data['stuckOptins'] = $this->report();
		}
		return $data;
	}
}
