<?php
/**
 * Confirmation mails that did not reach the mail server recently.
 *
 * A failed send used to leave an opt-in silently "unconfirmed"; site owners
 * found out through complaints. Since 5.8 every send is recorded
 * (OptInMailTracker) and this check counts the failures of the last seven
 * days, linking to the filtered opt-in list.
 *
 * @package Forge12\DoubleOptIn\Health
 * @since   5.8.0
 */

declare( strict_types=1 );

namespace Forge12\DoubleOptIn\Health;

use Forge12\DoubleOptIn\Repository\OptInMailStatusRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MailFailuresHealthCheck implements HealthCheckInterface {

	public const CACHE_KEY = 'f12_doi_mail_failures_7d';

	private const WINDOW = 7 * 86400;

	/** @var OptInMailStatusRepository */
	private $repository;

	public function __construct( OptInMailStatusRepository $repository ) {
		$this->repository = $repository;
	}

	public function getId(): string {
		return 'f12_doi_mail_failures';
	}

	public function getLabel(): string {
		return __( 'Confirmation mail delivery', 'double-opt-in' );
	}

	public function getPackage(): string {
		return 'core';
	}

	public function run(): HealthCheckResult {
		$failed = $this->failedLastWeek();

		if ( $failed === 0 ) {
			return new HealthCheckResult(
				HealthCheckResult::STATUS_GOOD,
				__( 'Confirmation mails reach the mail server', 'double-opt-in' ),
				__( 'No confirmation mail failed in the last seven days.', 'double-opt-in' ),
				'0'
			);
		}

		return new HealthCheckResult(
			HealthCheckResult::STATUS_RECOMMENDED,
			sprintf(
				/* translators: %d: number of failed confirmation mails */
				_n( '%d confirmation mail could not be sent', '%d confirmation mails could not be sent', $failed, 'double-opt-in' ),
				$failed
			),
			__( 'In the last seven days WordPress could not hand these confirmation mails to the mail server, so the visitors never received a link. The opt-in list shows the error for each one. Check the mail settings of your site, for example with an SMTP plugin, then resend the confirmation from the opt-in.', 'double-opt-in' ),
			(string) $failed,
			__( 'Show the affected opt-ins', 'double-opt-in' ),
			admin_url( 'admin.php?page=f12-doi-admin#/optins?mail=failed' )
		);
	}

	private function failedLastWeek(): int {
		$cached = get_transient( self::CACHE_KEY );
		if ( $cached !== false ) {
			return (int) $cached;
		}
		$count = $this->repository->countFailedSince( self::WINDOW );
		set_transient( self::CACHE_KEY, $count, 3600 );
		return $count;
	}
}
