<?php
/**
 * Audit Logger
 *
 * @package Forge12\DoubleOptIn\Audit
 * @since   4.2.0
 */

namespace Forge12\DoubleOptIn\Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AuditLogger
 *
 * Logs audit events to a dedicated database table.
 */
class AuditLogger {

	/**
	 * Table name (without prefix).
	 */
	const TABLE_NAME = 'f12_cf7_doubleoptin_audit_log';

	/**
	 * Event types.
	 */
	const TYPE_SETTINGS   = 'settings';
	const TYPE_CRON       = 'cron';
	const TYPE_ACTIVATION = 'activation';
	const TYPE_RATE_LIMIT = 'rate_limit';
	const TYPE_API_ERROR  = 'api_error';
	const TYPE_DB_ERROR   = 'db_error';
	const TYPE_EMAIL      = 'email';
	const TYPE_AUTH       = 'auth';

	/**
	 * Severity levels.
	 */
	const SEVERITY_INFO     = 'info';
	const SEVERITY_WARNING  = 'warning';
	const SEVERITY_ERROR    = 'error';
	const SEVERITY_CRITICAL = 'critical';

	/**
	 * Log an audit event.
	 *
	 * @param string $type     Event type (see TYPE_* constants).
	 * @param string $severity Severity level (see SEVERITY_* constants).
	 * @param string $message  Human-readable event description.
	 * @param array  $details  Optional additional details.
	 *
	 * @return int|false The inserted row ID or false on failure.
	 */
	public static function log( string $type, string $severity, string $message, array $details = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_NAME;

		// Validate severity
		$validSeverities = array( self::SEVERITY_INFO, self::SEVERITY_WARNING, self::SEVERITY_ERROR, self::SEVERITY_CRITICAL );
		if ( ! in_array( $severity, $validSeverities, true ) ) {
			$severity = self::SEVERITY_INFO;
		}

		$result = $wpdb->insert(
			$table,
			array(
				'event_type' => sanitize_text_field( $type ),
				'severity'   => $severity,
				'message'    => sanitize_text_field( $message ),
				'user_id'    => get_current_user_id() ?: null,
				'details'    => ! empty( $details ) ? wp_json_encode( $details ) : null,
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Get audit events with filtering and pagination.
	 *
	 * @param array $args Query arguments.
	 *
	 * @return array { events: array, total: int, pages: int }
	 */
	public static function getEvents( array $args = array() ): array {
		global $wpdb;

		$defaults = array(
			'period'   => 30,
			'type'     => '',
			'severity' => '',
			'page'     => 1,
			'per_page' => 15,
		);

		$args = wp_parse_args( $args, $defaults );

		$table  = $wpdb->prefix . self::TABLE_NAME;
		$where  = array( '1=1' );
		$params = array();

		// Period filter
		if ( $args['period'] > 0 ) {
			$where[]  = 'created_at >= %s';
			$params[] = gmdate( 'Y-m-d H:i:s', strtotime( "-{$args['period']} days" ) );
		}

		// Type filter. Empty AND the literal "all" sentinel both mean
		// "no filter" — the React SPA's <Select> sends "all" as the
		// default-selected value, and pre-fix that was matched as
		// `event_type = 'all'` in the WHERE, returning zero rows even
		// when the dropdown was clearly at "All" (user-reported bug
		// 2026-04-30: "Audit log shows totals but no events listed").
		if ( ! empty( $args['type'] ) && $args['type'] !== 'all' ) {
			$where[]  = 'event_type = %s';
			$params[] = sanitize_text_field( $args['type'] );
		}

		// Severity filter — same sentinel handling as type.
		if ( ! empty( $args['severity'] ) && $args['severity'] !== 'all' ) {
			$where[]  = 'severity = %s';
			$params[] = sanitize_text_field( $args['severity'] );
		}

		$whereClause = implode( ' AND ', $where );

		// Count total
		$countQuery = "SELECT COUNT(*) FROM {$table} WHERE {$whereClause}";
		if ( ! empty( $params ) ) {
			$countQuery = $wpdb->prepare( $countQuery, $params );
		}
		$total = (int) $wpdb->get_var( $countQuery );

		// Get events
		$perPage = max( 1, (int) $args['per_page'] );
		$page    = max( 1, (int) $args['page'] );
		$offset  = ( $page - 1 ) * $perPage;

		$query    = "SELECT * FROM {$table} WHERE {$whereClause} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$params[] = $perPage;
		$params[] = $offset;

		$events = $wpdb->get_results( $wpdb->prepare( $query, $params ), ARRAY_A );

		// Parse details JSON
		foreach ( $events as &$event ) {
			$event['details'] = ! empty( $event['details'] ) ? json_decode( $event['details'], true ) : null;
			if ( $event['user_id'] ) {
				$user                  = get_userdata( (int) $event['user_id'] );
				$event['user_display'] = $user ? $user->display_name : __( 'Unknown', 'double-opt-in' );
			} else {
				$event['user_display'] = __( 'System', 'double-opt-in' );
			}
		}

		return array(
			'events' => $events ?: array(),
			'total'  => $total,
			'pages'  => (int) ceil( $total / $perPage ),
		);
	}

	/**
	 * Get summary counts by severity for a given period.
	 *
	 * @param int $period Days to look back.
	 *
	 * @return array { total, info, warning, error, critical }
	 */
	public static function getSummary( int $period = 30 ): array {
		global $wpdb;

		$table    = $wpdb->prefix . self::TABLE_NAME;
		$dateFrom = gmdate( 'Y-m-d H:i:s', strtotime( "-{$period} days" ) );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT severity, COUNT(*) as count FROM {$table} WHERE created_at >= %s GROUP BY severity",
				$dateFrom
			),
			ARRAY_A
		);

		$summary = array(
			'total'    => 0,
			'info'     => 0,
			'warning'  => 0,
			'error'    => 0,
			'critical' => 0,
		);

		foreach ( $results as $row ) {
			$sev = $row['severity'];
			$cnt = (int) $row['count'];
			if ( isset( $summary[ $sev ] ) ) {
				$summary[ $sev ] = $cnt;
			}
			$summary['total'] += $cnt;
		}

		return $summary;
	}

	/**
	 * Register WordPress hooks for automatic audit logging.
	 *
	 * @return void
	 */
	public static function registerHooks(): void {
		// Log settings changes
		add_action(
			'update_option_f12-doi-settings',
			function ( $old, $new ) {
				self::log( self::TYPE_SETTINGS, self::SEVERITY_INFO, __( 'Global settings updated.', 'double-opt-in' ) );
			},
			10,
			2
		);

		// Log form settings changes
		add_action(
			'f12_doi_form_settings_saved',
			function ( $formId ) {
				self::log(
					self::TYPE_SETTINGS,
					self::SEVERITY_INFO,
					sprintf(
						__( 'Form settings saved for form %s.', 'double-opt-in' ),
						$formId
					)
				);
			},
			10,
			1
		);

		// Log cron runs
		add_action(
			'f12_doi_cron_cleanup_done',
			function ( $counts ) {
				if ( is_array( $counts ) && array_sum( $counts ) > 0 ) {
					self::log( self::TYPE_CRON, self::SEVERITY_INFO, __( 'Scheduled cleanup completed.', 'double-opt-in' ), $counts );
				}
			}
		);

		// Log rate limit hits
		add_action(
			'f12_doi_rate_limit_hit',
			function ( $type, $identifier ) {
				self::log(
					self::TYPE_RATE_LIMIT,
					self::SEVERITY_WARNING,
					sprintf(
						__( 'Rate limit reached for %1$s: %2$s', 'double-opt-in' ),
						$type,
						$identifier
					)
				);
			},
			10,
			2
		);
	}
}
