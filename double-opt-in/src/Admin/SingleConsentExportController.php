<?php
/**
 * Single Consent Export Controller
 *
 * Handles the single-record consent export (JSON/CSV) from the opt-in detail view.
 * The bulk export (all records, by email) is a Pro-only feature.
 *
 * @package Forge12\DoubleOptIn\Admin
 * @since   3.6.0
 */

namespace Forge12\DoubleOptIn\Admin;

use Forge12\DoubleOptIn\Entity\OptIn;
use Forge12\DoubleOptIn\Repository\OptInRepositoryInterface;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SingleConsentExportController {

	private LoggerInterface $logger;
	private OptInRepositoryInterface $repository;

	public function __construct( LoggerInterface $logger, OptInRepositoryInterface $repository ) {
		$this->logger     = $logger;
		$this->repository = $repository;
	}

	/**
	 * Register the AJAX action.
	 *
	 * Only registers if the Pro plugin has not already registered a handler.
	 *
	 * @return void
	 */
	public function registerActions(): void {
		// Register at default priority (10). The Pro plugin registers at priority 5
		// and removes this handler, providing extended export features.
		add_action( 'wp_ajax_doi_export_consent', [ $this, 'handleExport' ] );
	}

	/**
	 * Handle the single-record export request.
	 *
	 * @return void
	 */
	public function handleExport(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'double-opt-in' ), 403 );
		}

		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_REQUEST['_wpnonce'] ), 'doi_consent_export' ) ) {
			wp_die( __( 'Security check failed.', 'double-opt-in' ), 403 );
		}

		$scope = isset( $_REQUEST['scope'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['scope'] ) ) : '';

		// Free version only supports single-record export
		if ( $scope !== 'single' ) {
			wp_die( __( 'Bulk export requires the Pro version.', 'double-opt-in' ) );
		}

		$id = isset( $_REQUEST['id'] ) ? absint( $_REQUEST['id'] ) : 0;
		if ( $id <= 0 ) {
			wp_die( __( 'No records found.', 'double-opt-in' ) );
		}

		$optIn = $this->repository->findById( $id );
		if ( ! $optIn ) {
			wp_die( __( 'No records found.', 'double-opt-in' ) );
		}

		$record = $this->formatRecord( $optIn );
		$format = isset( $_REQUEST['format'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['format'] ) ) : 'csv';
		$format = in_array( $format, [ 'csv', 'json' ], true ) ? $format : 'csv';

		$filename = sanitize_file_name( 'consent-export-' . gmdate( 'Y-m-d-His' ) );

		if ( $format === 'json' ) {
			$content = wp_json_encode( [ $record ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '.json"' );
		} else {
			$content = $this->toCsv( $record );
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '.csv"' );
		}

		header( 'Content-Length: ' . strlen( $content ) );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo $content;
		exit;
	}

	private function formatRecord( OptIn $optIn ): array {
		$dateFormat = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		return [
			'id'                => $optIn->getId(),
			'email'             => $optIn->getEmail(),
			'form_id'           => $optIn->getFormId(),
			'confirmed'         => $optIn->isConfirmed() ? 'Yes' : 'No',
			'opted_out'         => $optIn->isOptedOut() ? 'Yes' : 'No',
			'consent_text'      => $optIn->getConsentText(),
			'registration_date' => $optIn->getCreateTime() > 0
				? wp_date( $dateFormat, $optIn->getCreateTime() )
				: '',
			'confirmation_date' => $optIn->getUpdateTime() > 0 && $optIn->isConfirmed()
				? wp_date( $dateFormat, $optIn->getUpdateTime() )
				: '',
			'optout_date'       => $optIn->getOptOutTime() > 0
				? wp_date( $dateFormat, $optIn->getOptOutTime() )
				: '',
			'registration_ip'   => $optIn->getIpRegister(),
			'confirmation_ip'   => $optIn->getIpConfirmation(),
			'optout_ip'         => $optIn->getIpOptOut(),
			'hash'              => $optIn->getHash(),
		];
	}

	private function toCsv( array $record ): string {
		$output = fopen( 'php://temp', 'r+' );
		fwrite( $output, "\xEF\xBB\xBF" );
		fputcsv( $output, array_keys( $record ) );
		fputcsv( $output, $record );
		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );

		return $csv;
	}
}
