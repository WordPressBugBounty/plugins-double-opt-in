<?php
/**
 * OptIn Repository Implementation
 *
 * @package Forge12\DoubleOptIn\Repository
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Repository;

use Forge12\DoubleOptIn\Entity\OptIn;
use Forge12\Shared\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OptInRepository
 *
 * Database operations for OptIn entities using WordPress wpdb.
 */
class OptInRepository implements OptInRepositoryInterface {

	private \wpdb $wpdb;
	private string $table;
	private LoggerInterface $logger;

	/**
	 * Constructor.
	 *
	 * @param \wpdb           $wpdb   The WordPress database instance.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct( \wpdb $wpdb, LoggerInterface $logger ) {
		$this->wpdb   = $wpdb;
		$this->table  = $wpdb->prefix . 'f12_cf7_doubleoptin';
		$this->logger = $logger;
	}

	/**
	 * {@inheritdoc}
	 */
	public function findById( int $id ): ?OptIn {
		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( ! $row ) {
			$this->logger->debug( 'OptIn not found by ID', [
				'plugin' => 'double-opt-in',
				'id'     => $id,
			] );
			return null;
		}

		return OptIn::fromArray( $row );
	}

	/**
	 * {@inheritdoc}
	 */
	public function findByHash( string $hash ): ?OptIn {
		$hash = sanitize_text_field( $hash );

		$row = $this->wpdb->get_row(
			$this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE hash = %s", $hash ),
			ARRAY_A
		);

		if ( ! $row ) {
			$this->logger->debug( 'OptIn not found by hash', [
				'plugin' => 'double-opt-in',
				'hash'   => $hash,
			] );
			return null;
		}

		return OptIn::fromArray( $row );
	}

	/**
	 * {@inheritdoc}
	 */
	public function findByEmail( string $email ): array {
		$email = sanitize_email( $email );

		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare( "SELECT * FROM {$this->table} WHERE email = %s ORDER BY id DESC", $email ),
			ARRAY_A
		);

		return array_map( [ OptIn::class, 'fromArray' ], $rows ?: [] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function findConfirmedByEmail( string $email ): array {
		$email = sanitize_email( $email );

		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE email = %s AND doubleoptin = 1 ORDER BY id DESC",
				$email
			),
			ARRAY_A
		);

		return array_map( [ OptIn::class, 'fromArray' ], $rows ?: [] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function findUnconfirmedByEmail( string $email ): array {
		$email = sanitize_email( $email );

		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE email = %s AND doubleoptin = 0 ORDER BY id DESC",
				$email
			),
			ARRAY_A
		);

		return array_map( [ OptIn::class, 'fromArray' ], $rows ?: [] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function findByCategory( int $categoryId, array $options = [] ): array {
		$perPage = max( 1, (int) ( $options['perPage'] ?? 10 ) );
		$page    = max( 1, (int) ( $options['page'] ?? 1 ) );
		$orderBy = in_array( $options['orderBy'] ?? 'id', [ 'id', 'createtime', 'updatetime', 'email' ], true )
			? $options['orderBy']
			: 'id';
		$order   = strtoupper( $options['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';
		$keyword = $options['keyword'] ?? '';
		$offset  = ( $page - 1 ) * $perPage;

		$where  = 'WHERE category = %d';
		$params = [ $categoryId ];

		if ( ! empty( $keyword ) ) {
			$where   .= ' AND content LIKE %s';
			$params[] = '%%' . $this->wpdb->esc_like( $keyword ) . '%%';
		}

		$query = $this->wpdb->prepare(
			"SELECT * FROM {$this->table} {$where} ORDER BY {$orderBy} {$order} LIMIT %d OFFSET %d",
			array_merge( $params, [ $perPage, $offset ] )
		);

		$rows = $this->wpdb->get_results( $query, ARRAY_A );

		$this->logger->debug( 'OptIns fetched by category', [
			'plugin'   => 'double-opt-in',
			'category' => $categoryId,
			'count'    => count( $rows ?: [] ),
		] );

		return array_map( [ OptIn::class, 'fromArray' ], $rows ?: [] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function countByCategory( int $categoryId, string $keyword = '' ): int {
		$where  = 'WHERE category = %d';
		$params = [ $categoryId ];

		if ( ! empty( $keyword ) ) {
			$where   .= ' AND content LIKE %s';
			$params[] = '%%' . $this->wpdb->esc_like( $keyword ) . '%%';
		}

		$query = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table} {$where}",
			$params
		);

		return (int) $this->wpdb->get_var( $query );
	}

	/**
	 * {@inheritdoc}
	 */
	public function countByFormId( int $formId ): int {
		return (int) $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table} WHERE cf_form_id = %d",
				$formId
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function save( OptIn $optIn ): OptIn {
		if ( $optIn->isNew() ) {
			return $this->insert( $optIn );
		}

		return $this->update( $optIn );
	}

	/**
	 * Insert a new OptIn.
	 *
	 * @param OptIn $optIn The OptIn entity.
	 *
	 * @return OptIn The entity with ID and hash.
	 */
	private function insert( OptIn $optIn ): OptIn {
		$data = $optIn->toInsertArray();

		$result = $this->wpdb->insert( $this->table, $data );

		if ( $result === false ) {
			$this->logger->error( 'Failed to insert OptIn', [
				'plugin' => 'double-opt-in',
				'error'  => $this->wpdb->last_error,
			] );
			throw new \RuntimeException( 'Failed to save OptIn: ' . $this->wpdb->last_error );
		}

		$id = $this->wpdb->insert_id;
		$optIn->setId( $id );

		// Generate cryptographically secure hash
		$hash = bin2hex( random_bytes( 32 ) );
		$this->wpdb->update( $this->table, [ 'hash' => $hash ], [ 'id' => $id ] );
		$optIn->setHash( $hash );

		$this->logger->info( 'OptIn created', [
			'plugin' => 'double-opt-in',
			'id'     => $id,
			'hash'   => $hash,
		] );

		return $optIn;
	}

	/**
	 * Update an existing OptIn.
	 *
	 * @param OptIn $optIn The OptIn entity.
	 *
	 * @return OptIn
	 */
	private function update( OptIn $optIn ): OptIn {
		$data = $optIn->toArray();
		$id   = $data['id'];
		unset( $data['id'] );

		$result = $this->wpdb->update( $this->table, $data, [ 'id' => $id ] );

		if ( $result === false ) {
			$this->logger->error( 'Failed to update OptIn', [
				'plugin' => 'double-opt-in',
				'id'     => $id,
				'error'  => $this->wpdb->last_error,
			] );
			throw new \RuntimeException( 'Failed to update OptIn: ' . $this->wpdb->last_error );
		}

		$this->logger->info( 'OptIn updated', [
			'plugin' => 'double-opt-in',
			'id'     => $id,
		] );

		return $optIn;
	}

	/**
	 * {@inheritdoc}
	 */
	public function delete( OptIn $optIn ): bool {
		$result = $this->wpdb->delete( $this->table, [ 'id' => $optIn->getId() ] );

		if ( $result === false ) {
			$this->logger->error( 'Failed to delete OptIn', [
				'plugin' => 'double-opt-in',
				'id'     => $optIn->getId(),
				'error'  => $this->wpdb->last_error,
			] );
			return false;
		}

		$this->logger->info( 'OptIn deleted', [
			'plugin' => 'double-opt-in',
			'id'     => $optIn->getId(),
		] );

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteByHash( string $hash ): int {
		$hash = sanitize_text_field( $hash );

		$result = $this->wpdb->delete( $this->table, [ 'hash' => $hash ] );

		if ( $result === false ) {
			$this->logger->error( 'Failed to delete OptIn by hash', [
				'plugin' => 'double-opt-in',
				'hash'   => $hash,
				'error'  => $this->wpdb->last_error,
			] );
			return 0;
		}

		$this->logger->info( 'OptIn deleted by hash', [
			'plugin'  => 'double-opt-in',
			'hash'    => $hash,
			'deleted' => $result,
		] );

		return (int) $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function bulkUpdateCategory( int $fromCategoryId, int $toCategoryId ): int {
		$result = $this->wpdb->update(
			$this->table,
			[ 'category' => $toCategoryId ],
			[ 'category' => $fromCategoryId ]
		);

		$this->logger->info( 'Bulk category update', [
			'plugin'  => 'double-opt-in',
			'from'    => $fromCategoryId,
			'to'      => $toCategoryId,
			'updated' => $result,
		] );

		return $result === false ? 0 : (int) $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteOlderThan( int $timestamp, bool $confirmed ): int {
		$dateTime = gmdate( 'Y-m-d H:i:s', $timestamp );
		$status   = $confirmed ? 1 : 0;

		$result = $this->wpdb->query(
			$this->wpdb->prepare(
				"DELETE FROM {$this->table} WHERE createtime < %s AND doubleoptin = %d",
				$dateTime,
				$status
			)
		);

		$this->logger->notice( 'OptIns older than threshold deleted', [
			'plugin'    => 'double-opt-in',
			'threshold' => $dateTime,
			'confirmed' => $confirmed,
			'deleted'   => $result,
		] );

		return $result === false ? 0 : (int) $result;
	}

	/**
	 * {@inheritdoc}
	 */
	public function findEligibleForReminder( int $delaySeconds, int $safetyFloorSeconds = 0, int $limit = 50 ): array {
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $delaySeconds );
		$floor  = $safetyFloorSeconds > 0
			? gmdate( 'Y-m-d H:i:s', time() - $safetyFloorSeconds )
			: '1970-01-01 00:00:00';

		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$this->table}
			 WHERE doubleoptin = 0
			   AND (reminder_sent_at IS NULL OR reminder_sent_at = '')
			   AND email IS NOT NULL AND email != ''
			   AND (optouttime IS NULL OR optouttime = '' OR optouttime = '0')
			   AND createtime < %s
			   AND createtime > %s
			 ORDER BY createtime ASC
			 LIMIT %d",
			$cutoff,
			$floor,
			$limit
		);

		$rows = $this->wpdb->get_results( $sql, ARRAY_A );

		$this->logger->debug( 'OptIns eligible for reminder fetched', [
			'plugin' => 'double-opt-in',
			'count'  => count( $rows ?: [] ),
			'cutoff' => $cutoff,
			'floor'  => $floor,
		] );

		return array_map( [ OptIn::class, 'fromArray' ], $rows ?: [] );
	}

	/**
	 * Find all OptIns with pagination and filtering.
	 *
	 * @param array    $options       Query options (perPage, page, order, keyword, cf_form_id).
	 * @param int|null $numberOfPages Reference to store total page count.
	 *
	 * @return array<OptIn>
	 */
	public function findAll( array $options = [], ?int &$numberOfPages = null ): array {
		$perPage  = max( 1, (int) ( $options['perPage'] ?? 10 ) );
		$page     = max( 1, (int) ( $options['page'] ?? 1 ) );
		$order    = strtoupper( $options['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';
		$keyword  = $options['keyword'] ?? '';
		$formId   = $options['cf_form_id'] ?? '';
		$offset   = ( $page - 1 ) * $perPage;

		$where  = [];
		$params = [];

		if ( ! empty( $keyword ) ) {
			$where[]  = 'content LIKE %s';
			$params[] = '%%' . $this->wpdb->esc_like( $keyword ) . '%%';
		}

		if ( ! empty( $formId ) ) {
			$where[]  = 'cf_form_id = %d';
			$params[] = (int) $formId;
		}

		$whereClause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';

		// Count total for pagination
		if ( $numberOfPages !== null ) {
			$countQuery = "SELECT COUNT(*) FROM {$this->table} {$whereClause}";
			if ( ! empty( $params ) ) {
				$countQuery = $this->wpdb->prepare( $countQuery, $params );
			}
			$totalItems    = (int) $this->wpdb->get_var( $countQuery );
			$numberOfPages = $totalItems > 0 ? (int) ceil( $totalItems / $perPage ) : 0;

			if ( $numberOfPages === 0 ) {
				return [];
			}
		}

		// Main query
		$query = "SELECT * FROM {$this->table} {$whereClause} ORDER BY id {$order} LIMIT %d OFFSET %d";
		$params[] = $perPage;
		$params[] = $offset;

		$rows = $this->wpdb->get_results(
			$this->wpdb->prepare( $query, $params ),
			ARRAY_A
		);

		$this->logger->debug( 'OptIns fetched', [
			'plugin' => 'double-opt-in',
			'count'  => count( $rows ?: [] ),
			'page'   => $page,
		] );

		return array_map( [ OptIn::class, 'fromArray' ], $rows ?: [] );
	}

	/**
	 * Update category for a single OptIn by ID.
	 *
	 * @param int $optInId    The OptIn ID.
	 * @param int $categoryId The new category ID.
	 *
	 * @return bool
	 */
	public function updateCategoryById( int $optInId, int $categoryId ): bool {
		$result = $this->wpdb->update(
			$this->table,
			[ 'category' => $categoryId ],
			[ 'id' => $optInId ]
		);

		$this->logger->info( 'OptIn category updated', [
			'plugin'   => 'double-opt-in',
			'optInId'  => $optInId,
			'category' => $categoryId,
			'result'   => $result,
		] );

		return $result !== false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function existsByEmailAndFormId( string $email, int $formId, bool $confirmedOnly = true ): bool {
		$sql = "SELECT COUNT(*) FROM {$this->table} WHERE email = %s AND cf_form_id = %d";

		if ( $confirmedOnly ) {
			$sql .= " AND doubleoptin = 1";
		}

		// Opt-Outs release the email (non-opted-out entries only)
		$sql .= " AND (optouttime IS NULL OR optouttime = '' OR optouttime = '0')";

		$count = (int) $this->wpdb->get_var(
			$this->wpdb->prepare( $sql, sanitize_email( $email ), $formId )
		);

		return $count > 0;
	}

	/**
	 * Get the logger instance.
	 *
	 * @return LoggerInterface
	 */
	public function getLogger(): LoggerInterface {
		return $this->logger;
	}
}
