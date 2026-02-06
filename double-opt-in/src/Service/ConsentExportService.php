<?php
/**
 * Consent Export Service
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
 * Class ConsentExportService
 *
 * Handles formatting and export of consent records for GDPR compliance.
 */
class ConsentExportService {

	private LoggerInterface $logger;
	private OptInRepositoryInterface $repository;

	public function __construct( LoggerInterface $logger, OptInRepositoryInterface $repository ) {
		$this->logger     = $logger;
		$this->repository = $repository;
	}

	/**
	 * Export a single consent record by OptIn ID.
	 *
	 * @param int $id The OptIn ID.
	 *
	 * @return array|null
	 */
	public function exportSingle( int $id ): ?array {
		$optIn = $this->repository->findById( $id );
		if ( ! $optIn ) {
			return null;
		}

		return [ $this->formatRecord( $optIn ) ];
	}

	/**
	 * Export all consent records for an email address.
	 *
	 * @param string $email The email address.
	 *
	 * @return array
	 */
	public function exportByEmail( string $email ): array {
		$optIns = $this->repository->findByEmail( $email );

		return array_map( [ $this, 'formatRecord' ], $optIns );
	}

	/**
	 * Export all consent records.
	 *
	 * @return array
	 */
	public function exportAll(): array {
		$optIns = $this->repository->findAll( [
			'perPage' => 999999,
			'page'    => 1,
		] );

		return array_map( [ $this, 'formatRecord' ], $optIns );
	}

	/**
	 * Format an OptIn entity into a structured export array.
	 *
	 * @param OptIn $optIn The OptIn entity.
	 *
	 * @return array
	 */
	public function formatRecord( OptIn $optIn ): array {
		$dateFormat = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		return [
			'id'              => $optIn->getId(),
			'email'           => $optIn->getEmail(),
			'form_id'         => $optIn->getFormId(),
			'confirmed'       => $optIn->isConfirmed() ? 'Yes' : 'No',
			'opted_out'       => $optIn->isOptedOut() ? 'Yes' : 'No',
			'consent_text'    => $optIn->getConsentText(),
			'registration_date' => $optIn->getCreateTime() > 0
				? wp_date( $dateFormat, $optIn->getCreateTime() )
				: '',
			'confirmation_date' => $optIn->getUpdateTime() > 0 && $optIn->isConfirmed()
				? wp_date( $dateFormat, $optIn->getUpdateTime() )
				: '',
			'optout_date'     => $optIn->getOptOutTime() > 0
				? wp_date( $dateFormat, $optIn->getOptOutTime() )
				: '',
			'registration_ip'   => $optIn->getIpRegister(),
			'confirmation_ip'   => $optIn->getIpConfirmation(),
			'optout_ip'         => $optIn->getIpOptOut(),
			'hash'              => $optIn->getHash(),
		];
	}

	/**
	 * Convert records to CSV string.
	 *
	 * @param array $records The formatted records.
	 *
	 * @return string CSV content with UTF-8 BOM.
	 */
	public function toCsv( array $records ): string {
		if ( empty( $records ) ) {
			return '';
		}

		$output = fopen( 'php://temp', 'r+' );

		// UTF-8 BOM
		fwrite( $output, "\xEF\xBB\xBF" );

		// Header row
		fputcsv( $output, array_keys( $records[0] ) );

		// Data rows
		foreach ( $records as $record ) {
			fputcsv( $output, $record );
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );

		return $csv;
	}

	/**
	 * Convert records to JSON string.
	 *
	 * @param array $records The formatted records.
	 *
	 * @return string Pretty-printed JSON.
	 */
	public function toJson( array $records ): string {
		return wp_json_encode( $records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}
}
