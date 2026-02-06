<?php
/**
 * Privacy Integration
 *
 * Integrates with WordPress Privacy Tools (Data Export + Data Erasure).
 *
 * @package Forge12\DoubleOptIn\Service
 * @since   3.2.0
 */

namespace Forge12\DoubleOptIn\Service;

use Forge12\DoubleOptIn\Entity\OptIn;
use Forge12\DoubleOptIn\Repository\OptInRepositoryInterface;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PrivacyIntegration
 *
 * Registers the plugin with WordPress Privacy Tools for GDPR compliance.
 */
class PrivacyIntegration {

	private LoggerInterface $logger;
	private OptInRepositoryInterface $repository;

	public function __construct( LoggerInterface $logger, OptInRepositoryInterface $repository ) {
		$this->logger     = $logger;
		$this->repository = $repository;
	}

	/**
	 * Register privacy hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'registerExporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'registerEraser' ] );

		$this->logger->debug( 'Privacy integration hooks registered', [
			'plugin' => 'double-opt-in',
		] );
	}

	/**
	 * Register the personal data exporter.
	 *
	 * @param array $exporters Existing exporters.
	 *
	 * @return array
	 */
	public function registerExporter( array $exporters ): array {
		$exporters['double-opt-in'] = [
			'exporter_friendly_name' => __( 'Double Opt-In Records', 'double-opt-in' ),
			'callback'               => [ $this, 'exportPersonalData' ],
		];

		return $exporters;
	}

	/**
	 * Register the personal data eraser.
	 *
	 * @param array $erasers Existing erasers.
	 *
	 * @return array
	 */
	public function registerEraser( array $erasers ): array {
		$erasers['double-opt-in'] = [
			'eraser_friendly_name' => __( 'Double Opt-In Records', 'double-opt-in' ),
			'callback'             => [ $this, 'erasePersonalData' ],
		];

		return $erasers;
	}

	/**
	 * Export personal data for a given email.
	 *
	 * @param string $email The email address.
	 * @param int    $page  The current page (pagination).
	 *
	 * @return array WordPress privacy export response.
	 */
	public function exportPersonalData( string $email, int $page = 1 ): array {
		$optIns = $this->repository->findByEmail( $email );
		$items  = [];

		$dateFormat = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		foreach ( $optIns as $optIn ) {
			$data = [
				[
					'name'  => __( 'Email', 'double-opt-in' ),
					'value' => $optIn->getEmail(),
				],
				[
					'name'  => __( 'Confirmed', 'double-opt-in' ),
					'value' => $optIn->isConfirmed()
						? __( 'Yes', 'double-opt-in' )
						: __( 'No', 'double-opt-in' ),
				],
				[
					'name'  => __( 'Consent Text', 'double-opt-in' ),
					'value' => $optIn->getConsentText() ?: __( '(not recorded)', 'double-opt-in' ),
				],
				[
					'name'  => __( 'Registration Date', 'double-opt-in' ),
					'value' => $optIn->getCreateTime() > 0
						? wp_date( $dateFormat, $optIn->getCreateTime() )
						: '',
				],
				[
					'name'  => __( 'Confirmation Date', 'double-opt-in' ),
					'value' => $optIn->getUpdateTime() > 0 && $optIn->isConfirmed()
						? wp_date( $dateFormat, $optIn->getUpdateTime() )
						: '',
				],
				[
					'name'  => __( 'Opt-Out Date', 'double-opt-in' ),
					'value' => $optIn->getOptOutTime() > 0
						? wp_date( $dateFormat, $optIn->getOptOutTime() )
						: '',
				],
				[
					'name'  => __( 'Registration IP', 'double-opt-in' ),
					'value' => $optIn->getIpRegister(),
				],
				[
					'name'  => __( 'Confirmation IP', 'double-opt-in' ),
					'value' => $optIn->getIpConfirmation(),
				],
				[
					'name'  => __( 'Opt-Out IP', 'double-opt-in' ),
					'value' => $optIn->getIpOptOut(),
				],
				[
					'name'  => __( 'Form ID', 'double-opt-in' ),
					'value' => (string) $optIn->getFormId(),
				],
			];

			$items[] = [
				'group_id'    => 'double-opt-in',
				'group_label' => __( 'Double Opt-In Records', 'double-opt-in' ),
				'item_id'     => 'doi-' . $optIn->getId(),
				'data'        => $data,
			];
		}

		$this->logger->info( 'Personal data exported', [
			'plugin' => 'double-opt-in',
			'email'  => $email,
			'count'  => count( $items ),
		] );

		return [
			'data' => $items,
			'done' => true,
		];
	}

	/**
	 * Erase personal data for a given email.
	 *
	 * Anonymizes PII (email, IP addresses, form content, form HTML, mail body)
	 * while retaining the consent record (timestamps, confirmed status, consent
	 * text, form ID) as proof of consent per GDPR Art. 7.
	 *
	 * @param string $email The email address.
	 * @param int    $page  The current page (pagination).
	 *
	 * @return array WordPress privacy eraser response.
	 */
	public function erasePersonalData( string $email, int $page = 1 ): array {
		$optIns   = $this->repository->findByEmail( $email );
		$retained = 0;
		$messages = [];

		foreach ( $optIns as $optIn ) {
			$anonymized = $this->anonymizeOptIn( $optIn );

			try {
				$this->repository->save( $anonymized );
				$retained++;
			} catch ( \RuntimeException $e ) {
				$this->logger->error( 'Failed to anonymize OptIn', [
					'plugin' => 'double-opt-in',
					'id'     => $optIn->getId(),
					'error'  => $e->getMessage(),
				] );
			}
		}

		if ( $retained > 0 ) {
			$messages[] = sprintf(
				/* translators: %d: number of anonymized records */
				__( '%d opt-in record(s) anonymized. Consent proof retained per GDPR Art. 7.', 'double-opt-in' ),
				$retained
			);
		}

		$this->logger->info( 'Personal data anonymized', [
			'plugin'   => 'double-opt-in',
			'email'    => $email,
			'retained' => $retained,
		] );

		return [
			'items_removed'  => 0,
			'items_retained' => $retained,
			'messages'       => $messages,
			'done'           => true,
		];
	}

	/**
	 * Anonymize PII fields on an OptIn entity.
	 *
	 * Clears: email, IP addresses, form content, form HTML, mail body.
	 * Retains: ID, form ID, hash, timestamps, confirmed status, consent text, category.
	 *
	 * @param OptIn $optIn The original OptIn entity.
	 *
	 * @return OptIn The anonymized entity.
	 */
	private function anonymizeOptIn( OptIn $optIn ): OptIn {
		return $optIn
			->withEmail( 'anonymized-' . $optIn->getId() . '@deleted.invalid' )
			->withIpRegister( '0.0.0.0' )
			->withIpConfirmation( '0.0.0.0' )
			->withIpOptOut( '0.0.0.0' )
			->withContent( '' )
			->withForm( '' )
			->withMailOptIn( '' )
			->withFiles( '' );
	}
}
