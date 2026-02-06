<?php
/**
 * OptIn Repository Interface
 *
 * @package Forge12\DoubleOptIn\Repository
 * @since   4.0.0
 */

namespace Forge12\DoubleOptIn\Repository;

use Forge12\DoubleOptIn\Entity\OptIn;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface OptInRepositoryInterface
 *
 * Contract for OptIn data access operations.
 */
interface OptInRepositoryInterface {

	/**
	 * Find an OptIn by its ID.
	 *
	 * @param int $id The OptIn ID.
	 *
	 * @return OptIn|null
	 */
	public function findById( int $id ): ?OptIn;

	/**
	 * Find an OptIn by its hash.
	 *
	 * @param string $hash The unique hash.
	 *
	 * @return OptIn|null
	 */
	public function findByHash( string $hash ): ?OptIn;

	/**
	 * Find all OptIns by email address.
	 *
	 * @param string $email The email address.
	 *
	 * @return OptIn[]
	 */
	public function findByEmail( string $email ): array;

	/**
	 * Find confirmed OptIns by email address.
	 *
	 * @param string $email The email address.
	 *
	 * @return OptIn[]
	 */
	public function findConfirmedByEmail( string $email ): array;

	/**
	 * Find unconfirmed OptIns by email address.
	 *
	 * @param string $email The email address.
	 *
	 * @return OptIn[]
	 */
	public function findUnconfirmedByEmail( string $email ): array;

	/**
	 * Find OptIns by category with pagination.
	 *
	 * @param int   $categoryId The category ID (0 for all).
	 * @param array $options    Options: perPage, page, orderBy, order, keyword.
	 *
	 * @return OptIn[]
	 */
	public function findByCategory( int $categoryId, array $options = [] ): array;

	/**
	 * Count OptIns by category.
	 *
	 * @param int    $categoryId The category ID (0 for all).
	 * @param string $keyword    Optional search keyword.
	 *
	 * @return int
	 */
	public function countByCategory( int $categoryId, string $keyword = '' ): int;

	/**
	 * Count OptIns by form ID.
	 *
	 * @param int $formId The form ID.
	 *
	 * @return int
	 */
	public function countByFormId( int $formId ): int;

	/**
	 * Save an OptIn (insert or update).
	 *
	 * @param OptIn $optIn The OptIn entity.
	 *
	 * @return OptIn The saved entity with ID.
	 * @throws \RuntimeException If save fails.
	 */
	public function save( OptIn $optIn ): OptIn;

	/**
	 * Delete an OptIn.
	 *
	 * @param OptIn $optIn The OptIn to delete.
	 *
	 * @return bool True on success.
	 */
	public function delete( OptIn $optIn ): bool;

	/**
	 * Delete an OptIn by hash.
	 *
	 * @param string $hash The hash.
	 *
	 * @return int Number of deleted rows.
	 */
	public function deleteByHash( string $hash ): int;

	/**
	 * Bulk update category for multiple OptIns.
	 *
	 * @param int $fromCategoryId The source category ID.
	 * @param int $toCategoryId   The target category ID.
	 *
	 * @return int Number of updated rows.
	 */
	public function bulkUpdateCategory( int $fromCategoryId, int $toCategoryId ): int;

	/**
	 * Delete OptIns older than a timestamp by confirmation status.
	 *
	 * @param int  $timestamp Cutoff timestamp.
	 * @param bool $confirmed True for confirmed, false for unconfirmed.
	 *
	 * @return int Number of deleted rows.
	 */
	public function deleteOlderThan( int $timestamp, bool $confirmed ): int;

	/**
	 * Find unconfirmed OptIns eligible for a reminder email.
	 *
	 * Returns entries that are unconfirmed, have no reminder sent yet,
	 * have a valid email, are not opted out, and were created at least
	 * $delaySeconds ago but not older than $safetyFloorSeconds.
	 *
	 * @param int $delaySeconds       Minimum age in seconds before reminder is sent.
	 * @param int $safetyFloorSeconds Maximum age in seconds (entries older than this are excluded).
	 * @param int $limit              Maximum number of results.
	 *
	 * @return OptIn[]
	 */
	public function findEligibleForReminder( int $delaySeconds, int $safetyFloorSeconds = 0, int $limit = 50 ): array;
}
