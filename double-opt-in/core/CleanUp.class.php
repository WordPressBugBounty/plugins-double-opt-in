<?php

namespace forge12\contactform7\CF7DoubleOptIn {

	use Forge12\DoubleOptIn\Container\Container;
	use Forge12\DoubleOptIn\EventSystem\EventDispatcherInterface;
	use Forge12\DoubleOptIn\Events\Lifecycle\OptInDeletedEvent;
	use Forge12\DoubleOptIn\Events\Lifecycle\OptInExpiredEvent;
	use Forge12\Shared\LoggerInterface;

	if(!defined('ABSPATH')){
        exit;
    }
    /**
     * This class will handle the clean up of the database
     * as defined by the user settings.
     */
    class CleanUp
    {
	    private LoggerInterface $logger;

	    public function __construct(LoggerInterface $logger)
	    {
		    $this->logger = $logger;

		    $this->logger->info(
			    'Daily opt-in cleaner initialized.',
			    [
				    'plugin' => 'double-opt-in',
				    'class'  => static::class,
			    ]
		    );

		    $this->registerHooks();
	    }

	    /**
	     * Register all WordPress hooks for the opt-in cleanup.
	     */
	    private function registerHooks(): void
	    {
		    add_action('dailyOptinClear', [$this, 'handleDailyOptinCleanup']);

		    $this->logger->debug(
			    'Daily opt-in cleanup hook registered.',
			    [
				    'plugin' => 'double-opt-in',
				    'hook'   => 'dailyOptinClear',
				    'handler'=> 'handleDailyOptinCleanup',
			    ]
		    );
	    }

	    /**
	     * Central dispatcher for daily opt-in cleanup.
	     * Keeps cron infrastructure separated from business logic.
	     */
	    public function handleDailyOptinCleanup(): void
	    {
		    $this->logger->info(
			    'Executing daily opt-in cleanup.',
			    [
				    'plugin' => 'double-opt-in',
			    ]
		    );

		    $this->removeUnconfirmedOptins();
		    $this->removeConfirmedOptins();

		    $this->logger->info(
			    'Daily opt-in cleanup finished.',
			    [
				    'plugin' => 'double-opt-in',
			    ]
		    );
	    }

		public function get_logger(): LoggerInterface{
			return $this->logger;
		}

        /**
         * Delete the given hash entry
         */
	    public function deleteByHash(string $hash): int|false{
		    // Authorization check (fail fast)
		    if (!current_user_can('manage_options')) {
			    $this->get_logger()->warning('Unauthorized attempt to delete opt-in entry.', [
				    'plugin'              => 'double-opt-in',
				    'method'              => __METHOD__,
				    'user_id'             => get_current_user_id(),
				    'required_capability' => 'manage_options',
			    ]);

			    return false;
		    }

		    // Input validation
		    $hash = trim($hash);
		    if ($hash === '') {
			    $this->get_logger()->error('Deletion aborted due to invalid hash value.', [
				    'plugin' => 'double-opt-in',
				    'method' => __METHOD__,
			    ]);

			    return false;
		    }

		    global $wpdb;

		    // Log the start of the deletion attempt.
		    $this->get_logger()->info( 'Attempting to delete opt-in entry by hash.', [
			    'plugin' => 'double-opt-in',
			    'hash'   => $hash,
		    ] );

		    $table_name = $wpdb->prefix . 'f12_cf7_doubleoptin';

		    $this->get_logger()->info('Deleting opt-in entry by hash.', [
			    'plugin' => 'double-opt-in',
			    'method' => __METHOD__,
			    'hash'   => $hash,
		    ]);

		    $sql = $wpdb->prepare(
			    "DELETE FROM {$table_name} WHERE hash = %s",
			    $hash
		    );

		    $result = $wpdb->query($sql);

		    if ($result === false) {
			    $this->get_logger()->error('Database error while deleting opt-in entry.', [
				    'plugin' => 'double-opt-in',
				    'method' => __METHOD__,
				    'hash'   => $hash,
				    'error'  => $wpdb->last_error,
			    ]);

			    return false;
		    }

		    if ($result === 0) {
			    $this->get_logger()->notice('No opt-in entry found for provided hash.', [
				    'plugin' => 'double-opt-in',
				    'method' => __METHOD__,
				    'hash'   => $hash,
			    ]);

			    return 0;
		    }

		    $this->get_logger()->notice('Opt-in entry successfully deleted.', [
			    'plugin'       => 'double-opt-in',
			    'method'       => __METHOD__,
			    'hash'         => $hash,
			    'rows_deleted' => $result,
		    ]);

		    // Dispatch typed event for new event-driven architecture
		    $this->dispatchOptInDeletedEvent( $hash, 'manual', get_current_user_id() );

		    return $result;
	    }

        /**
         * Delete all Rows from the Database which are older than the given timestamp and where the optin value matches as defined.
         * @param $timestamp
         * @param int $optin
         */
	    private function removeOlderThan(int $timestamp, int $optin = 0): void
	    {
		    // Defensive validation: timestamp must be plausible
		    if ($timestamp <= 0) {
			    $this->get_logger()->warning(
				    'Aborted opt-in cleanup due to invalid timestamp.',
				    [
					    'plugin'    => 'double-opt-in',
					    'timestamp' => $timestamp,
				    ]
			    );
			    return;
		    }

		    // Normalize opt-in status (allow only 0 or 1)
		    $optin = ($optin === 1) ? 1 : 0;

		    $statusLabel = ($optin === 1) ? 'confirmed' : 'unconfirmed';

		    $this->get_logger()->info(
			    'Starting cleanup of opt-in entries older than timestamp.',
			    [
				    'plugin'    => 'double-opt-in',
				    'timestamp' => $timestamp,
				    'status'    => $statusLabel,
			    ]
		    );

		    global $wpdb;
		    $tableName   = $wpdb->prefix . 'f12_cf7_doubleoptin';
		    $dateTime    = gmdate('Y-m-d H:i:s', $timestamp);

		    // Prepare SQL securely (OWASP-compliant)
		    $sql = $wpdb->prepare("DELETE FROM {$tableName} WHERE createtime < %s AND doubleoptin = %d",
			    $dateTime,
			    $optin
		    );

		    $this->get_logger()->debug(
			    'Executing opt-in cleanup query.',
			    [
				    'plugin'   => 'double-opt-in',
				    'sql'      => $sql,
				    'datetime' => $dateTime,
			    ]
		    );

		    $result = $wpdb->query($sql);

		    if ($result === false) {
			    $this->get_logger()->error(
				    'Database error while removing old opt-in entries.',
				    [
					    'plugin'      => 'double-opt-in',
					    'wpdb_error'  => $wpdb->last_error,
					    'status'      => $statusLabel,
				    ]
			    );
			    return;
		    }

		    $this->get_logger()->notice(
			    'Opt-in cleanup completed successfully.',
			    [
				    'plugin'        => 'double-opt-in',
				    'rows_deleted'  => (int) $result,
				    'status'        => $statusLabel,
				    'older_than'    => $dateTime,
			    ]
		    );

		    // Dispatch typed event for new event-driven architecture
		    if ( (int) $result > 0 ) {
			    $this->dispatchOptInExpiredEvent( $statusLabel, (int) $result, $timestamp );
		    }
	    }

        /**
         * Delete all DOI entries
         */
	    public function reset(): bool{
		    // Authorization check (fail fast)
		    if (!current_user_can('manage_options')) {
			    $this->get_logger()->warning('Unauthorized attempt to reset double-opt-in table.', [
				    'plugin'  => 'double-opt-in',
				    'method'  => __METHOD__,
				    'user_id' => get_current_user_id(),
			    ]);

			    return false;
		    }
		    global $wpdb;

		    $table_name = $wpdb->prefix . 'f12_cf7_doubleoptin';

		    $this->get_logger()->critical('Confirmed full reset of double-opt-in table requested.', [
			    'plugin' => 'double-opt-in',
			    'method' => __METHOD__,
			    'table'  => $table_name,
			    'note'   => 'This operation will permanently delete ALL records.',
		    ]);

		    // TRUNCATE is faster and safer for full table resets
		    $sql = "TRUNCATE TABLE {$table_name}";

		    $result = $wpdb->query($sql);

		    if ($result === false) {
			    $this->get_logger()->error('Failed to reset double-opt-in table.', [
				    'plugin' => 'double-opt-in',
				    'method' => __METHOD__,
				    'table'  => $table_name,
				    'error'  => $wpdb->last_error,
			    ]);

			    return false;
		    }

		    $this->get_logger()->notice('Double-opt-in table has been fully reset.', [
			    'plugin' => 'double-opt-in',
			    'method' => __METHOD__,
			    'table'  => $table_name,
		    ]);

		    return true;
	    }

        /**
         * Clear all unconfirmed database entries if the period selected
         * is reached.
         *
         * Use force to delete confirmed opt-ins in the database settings.
         */
	    public function removeUnconfirmedOptins(bool $force = false): void
	    {
		    $logger = $this->get_logger();

		    $logger->info('Starting unconfirmed opt-in cleanup process.', [
			    'plugin' => 'double-opt-in',
			    'force_mode' => $force,
		    ]);

		    $settings = CF7DoubleOptIn::getInstance()->getSettings();

		    $deleteAfter = isset($settings['delete_unconfirmed'])
			    ? (int) $settings['delete_unconfirmed']
			    : 0;
		    $period = isset($settings['delete_unconfirmed_period'])
			    ? (string) $settings['delete_unconfirmed_period']
			    : '';

		    $logger->debug('Loaded cleanup configuration.', [
			    'plugin' => 'double-opt-in',
			    'delete_unconfirmed' => $deleteAfter,
			    'period'             => $period,
		    ]);

		    // Guard clause: feature disabled and not forced
		    if (!$force && $deleteAfter <= 0) {
			    $logger->notice('Unconfirmed opt-in cleanup is disabled. Aborting.', [
				    'plugin' => 'double-opt-in',
			    ]);
			    return;
		    }

		    // Log the configuration settings for unconfirmed opt-in removal.
		    $this->get_logger()->debug( 'Checking plugin settings for unconfirmed opt-in removal.', [
			    'plugin' => 'double-opt-in',
			    'delete_unconfirmed' => $settings['delete_unconfirmed'] ?? 'not set',
		    ] );

		    // Validate period
		    $allowedPeriods = ['days', 'weeks', 'months', 'years'];
		    if (!in_array($period, $allowedPeriods, true)) {
			    // When force mode is enabled without valid period, delete ALL entries
			    if ($force) {
				    $logger->info('Force mode enabled without valid period - deleting ALL unconfirmed opt-ins.', [
					    'plugin' => 'double-opt-in',
				    ]);
				    // Use current time + 1 day to ensure all entries are deleted
				    $timestamp = time() + 86400;
				    $this->removeOlderThan($timestamp, 0);
				    return;
			    }
			    $logger->error('Invalid cleanup period configuration.', [
				    'plugin' => 'double-opt-in',
				    'period' => $period,
				    'allowed' => $allowedPeriods,
			    ]);
			    return;
		    }

		    $deleteAfter = max(0, (int)$deleteAfter);

		    $timestamp = strtotime(sprintf('-%d %s', $deleteAfter, $period));

		    if ($timestamp === false) {
			    $logger->error('Failed to calculate cleanup timestamp.', [
				    'plugin' => 'double-opt-in',
				    'delete_unconfirmed' => $deleteAfter,
				    'period' => $period,
			    ]);
			    return;
		    }

		    $logger->info('Removing unconfirmed opt-ins older than threshold.', [
			    'plugin' => 'double-opt-in',
			    'threshold_datetime' => date('Y-m-d H:i:s', $timestamp),
			    'threshold_timestamp' => $timestamp,
		    ]);

		    $this->removeOlderThan($timestamp, 0);

		    $logger->notice('Unconfirmed opt-in cleanup completed successfully.', [
			    'plugin' => 'double-opt-in',
		    ]);
	    }

        /**
         * Clear all confimred database entries if the period selected
         * is reached.
         *
         * Use force to delete confirmed opt-ins in the database settings.
         */
	    public function removeConfirmedOptins(bool $force = false): void
	    {
		    $this->get_logger()->info('Starting removal process for confirmed opt-ins.', [
			    'plugin'     => 'double-opt-in',
			    'force_mode' => $force,
		    ]);
		    $settings = CF7DoubleOptIn::getInstance()->getSettings();

		    // Normalize and validate configuration values (settings are returned as strings)
		    $deleteAmount = isset($settings['delete']) ? (int) $settings['delete'] : 0;
		    $deletePeriod = isset($settings['delete_period']) ? trim((string) $settings['delete_period']) : '';

		    $this->get_logger()->debug('Loaded confirmed opt-in cleanup configuration.', [
			    'plugin'        => 'double-opt-in',
			    'delete_amount' => $deleteAmount,
			    'delete_period' => $deletePeriod ?: 'not set',
		    ]);

		    // Guard clause: cleanup disabled and not forced
		    if ($deleteAmount <= 0 && !$force) {
			    $this->get_logger()->notice(
				    'Confirmed opt-in removal is disabled via configuration and not running in force mode. Aborting.',
				    ['plugin' => 'double-opt-in']
			    );
			    return;
		    }

		    // Validate period to avoid invalid strtotime() behavior
		    $allowedPeriods = ['days', 'weeks', 'months', 'years'];
		    if (!in_array($deletePeriod, $allowedPeriods, true)) {
			    // When force mode is enabled without valid period, delete ALL entries
			    if ($force) {
				    $this->get_logger()->info('Force mode enabled without valid period - deleting ALL confirmed opt-ins.', [
					    'plugin' => 'double-opt-in',
				    ]);
				    // Use current time + 1 day to ensure all entries are deleted
				    $timestamp = time() + 86400;
				    $this->removeOlderThan($timestamp, 1);
				    return;
			    }
			    $this->get_logger()->error('Invalid delete period configured for confirmed opt-ins.', [
				    'plugin' => 'double-opt-in',
				    'period' => $deletePeriod,
			    ]);
			    return;
		    }

		    $this->get_logger()->info('Calculating cutoff timestamp for confirmed opt-in removal.', [
			    'plugin'        => 'double-opt-in',
			    'age_threshold' => sprintf('%d %s', $deleteAmount, $deletePeriod),
		    ]);

		    $timestamp = strtotime(sprintf('-%d %s', $deleteAmount, $deletePeriod));

		    if ($timestamp === false) {
			    $this->get_logger()->error('Failed to calculate timestamp for confirmed opt-in cleanup.', [
				    'plugin'        => 'double-opt-in',
				    'delete_amount' => $deleteAmount,
				    'delete_period' => $deletePeriod,
			    ]);
			    return;
		    }

		    $this->get_logger()->debug('Calculated cutoff timestamp for confirmed opt-ins.', [
			    'plugin'    => 'double-opt-in',
			    'timestamp' => $timestamp,
			    'datetime'  => date('Y-m-d H:i:s', $timestamp),
		    ]);

		    // Status "1" = confirmed opt-ins
		    $this->removeOlderThan($timestamp, 1);

		    $this->get_logger()->notice('Confirmed opt-in removal process completed successfully.', [
			    'plugin' => 'double-opt-in',
		    ]);
	    }

	    /**
	     * Dispatch OptInDeletedEvent via the new event system.
	     *
	     * @param string   $hash      The hash of the deleted opt-in.
	     * @param string   $reason    The reason for deletion.
	     * @param int|null $deletedBy The user ID who deleted (null for system).
	     *
	     * @since 4.0.0
	     */
	    private function dispatchOptInDeletedEvent( string $hash, string $reason, ?int $deletedBy = null ): void {
		    try {
			    $container = Container::getInstance();
			    if ( $container->has( EventDispatcherInterface::class ) ) {
				    $dispatcher = $container->get( EventDispatcherInterface::class );
				    $event = new OptInDeletedEvent( $hash, $reason, $deletedBy );
				    $dispatcher->dispatch( $event );

				    $this->get_logger()->debug( 'OptInDeletedEvent dispatched', [
					    'plugin' => 'double-opt-in',
					    'hash'   => $hash,
					    'reason' => $reason,
				    ] );
			    }
		    } catch ( \Exception $e ) {
			    $this->get_logger()->warning( 'Failed to dispatch OptInDeletedEvent', [
				    'plugin' => 'double-opt-in',
				    'error'  => $e->getMessage(),
			    ] );
		    }
	    }

	    /**
	     * Dispatch OptInExpiredEvent via the new event system.
	     *
	     * @param string $cleanupType  The type of cleanup (confirmed/unconfirmed).
	     * @param int    $rowsDeleted  Number of rows deleted.
	     * @param int    $cutoffTime   The cutoff timestamp.
	     *
	     * @since 4.0.0
	     */
	    private function dispatchOptInExpiredEvent( string $cleanupType, int $rowsDeleted, int $cutoffTime ): void {
		    try {
			    $container = Container::getInstance();
			    if ( $container->has( EventDispatcherInterface::class ) ) {
				    $dispatcher = $container->get( EventDispatcherInterface::class );
				    // Convert timestamp to DateTimeImmutable as expected by OptInExpiredEvent
				    $threshold = ( new \DateTimeImmutable() )->setTimestamp( $cutoffTime );
				    $event = new OptInExpiredEvent( $cleanupType, $rowsDeleted, $threshold );
				    $dispatcher->dispatch( $event );

				    $this->get_logger()->debug( 'OptInExpiredEvent dispatched', [
					    'plugin'       => 'double-opt-in',
					    'cleanup_type' => $cleanupType,
					    'rows_deleted' => $rowsDeleted,
				    ] );
			    }
		    } catch ( \Exception $e ) {
			    $this->get_logger()->warning( 'Failed to dispatch OptInExpiredEvent', [
				    'plugin' => 'double-opt-in',
				    'error'  => $e->getMessage(),
			    ] );
		    }
	    }

    }
}