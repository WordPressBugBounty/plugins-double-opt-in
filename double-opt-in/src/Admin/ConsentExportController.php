<?php
/**
 * Consent Export Controller
 *
 * @package Forge12\DoubleOptIn\Admin
 * @since   3.2.0
 */

namespace Forge12\DoubleOptIn\Admin;

use Forge12\DoubleOptIn\Service\ConsentExportService;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ConsentExportController
 *
 * Handles AJAX requests for consent record exports.
 */
class ConsentExportController {

	private LoggerInterface $logger;
	private ConsentExportService $exportService;

	public function __construct( LoggerInterface $logger, ConsentExportService $exportService ) {
		$this->logger        = $logger;
		$this->exportService = $exportService;
	}

	/**
	 * Register AJAX actions.
	 *
	 * @return void
	 */
	public function registerActions(): void {
		add_action( 'wp_ajax_doi_export_consent', [ $this, 'handleExport' ] );
	}

	/**
	 * Handle the export AJAX request.
	 *
	 * @return void
	 */
	public function handleExport(): void {
		// Security checks
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'double-opt-in' ), 403 );
		}

		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpnonce'] ), 'doi_consent_export' ) ) {
			wp_die( __( 'Security check failed.', 'double-opt-in' ), 403 );
		}

		$format = isset( $_GET['format'] ) ? sanitize_text_field( wp_unslash( $_GET['format'] ) ) : 'csv';
		$scope  = isset( $_GET['scope'] ) ? sanitize_text_field( wp_unslash( $_GET['scope'] ) ) : 'all';

		// Validate format and scope against whitelist
		$allowed_formats = [ 'csv', 'json' ];
		$allowed_scopes  = [ 'all', 'single', 'email' ];
		$format = in_array( $format, $allowed_formats, true ) ? $format : 'csv';
		$scope  = in_array( $scope, $allowed_scopes, true ) ? $scope : 'all';

		$records = [];

		switch ( $scope ) {
			case 'single':
				$id      = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
				$records = $id > 0 ? $this->exportService->exportSingle( $id ) : [];
				break;

			case 'email':
				$email   = isset( $_GET['email'] ) ? sanitize_email( wp_unslash( $_GET['email'] ) ) : '';
				$records = ! empty( $email ) ? $this->exportService->exportByEmail( $email ) : [];
				break;

			case 'all':
			default:
				$records = $this->exportService->exportAll();
				break;
		}

		if ( empty( $records ) ) {
			wp_die( __( 'No records found.', 'double-opt-in' ) );
		}

		$this->logger->info( 'Consent export generated', [
			'plugin' => 'double-opt-in',
			'format' => $format,
			'scope'  => $scope,
			'count'  => count( $records ),
		] );

		$filename = sanitize_file_name( 'consent-export-' . gmdate( 'Y-m-d-His' ) );

		if ( $format === 'json' ) {
			$content = $this->exportService->toJson( $records );
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . esc_attr( $filename ) . '.json"' );
		} else {
			$content = $this->exportService->toCsv( $records );
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
}
