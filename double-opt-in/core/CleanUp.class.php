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

		    // Pre-fetch the row before the DELETE so the pre-delete cascade
		    // hook can fire with the full payload. Listeners need
		    // content/files/cf_form_id to clean up form-system data
		    // (pre-doi-data-retention Step 1, 2026-05-08).
		    $row = $wpdb->get_row(
			    $wpdb->prepare( "SELECT id, hash, content, files, cf_form_id FROM {$table_name} WHERE hash = %s", $hash ),
			    ARRAY_A
		    );

		    $this->get_logger()->info('Deleting opt-in entry by hash.', [
			    'plugin' => 'double-opt-in',
			    'method' => __METHOD__,
			    'hash'   => $hash,
		    ]);

		    if ( is_array( $row ) ) {
			    /** @see f12_doi_optin_pre_delete in CleanUp::removeOlderThan for contract. */
			    do_action( 'f12_doi_optin_pre_delete', $row );
		    }

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
	    protected function removeOlderThan(int $timestamp, int $optin = 0): void
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

		    // Pre-fetch the rows that are about to be deleted so we can
		    // dispatch per-row events around the DELETE. Listeners need
		    // the hash for post-delete coordination (file-storage cascade
		    // from file-lifecycle Schritt 0) AND the full row payload
		    // for pre-delete cascade-cleanup of form-system data
		    // (pre-doi-data-retention Step 1, 2026-05-08): the integration
		    // listener reads content/files/cf_form_id to find what to
		    // delete in the form plugin's own storage (WPForms entries,
		    // GF entries, Avada/Elementor file URLs).
		    //
		    // ARRAY_A so $rowsToDelete elements are associative arrays —
		    // simpler for listeners than juggling stdClass.
		    $selectSql = $wpdb->prepare(
			    "SELECT id, hash, content, files, cf_form_id FROM {$tableName} WHERE createtime < %s AND doubleoptin = %d",
			    $dateTime,
			    $optin
		    );
		    $rowsToDelete = (array) $wpdb->get_results( $selectSql, ARRAY_A );

		    // Pre-delete cascade hook fires BEFORE the DELETE so listeners
		    // can read the row's content/files/cf_form_id and clean up
		    // their integration-specific side-effects in form-system
		    // storage. The post-delete event below is too late — the row
		    // is gone, listeners can't read its payload.
		    foreach ( $rowsToDelete as $row ) {
			    /**
			     * Fires per row BEFORE an opt-in is deleted (cron path).
			     *
			     * Listeners cascade-delete form-system data (entries +
			     * uploaded files in form-plugin storage). See
			     * plan/pre-doi-data-retention.md.
			     *
			     * @since 4.3.0
			     *
			     * @param array $row Associative row: id, hash, content,
			     *                   files, cf_form_id.
			     */
			    do_action( 'f12_doi_optin_pre_delete', $row );
		    }

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
				    'matched'  => count( $rowsToDelete ),
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

		    // Dispatch per-row OptInDeletedEvent for every actually-deleted
		    // hash. Reason carries cron context so listeners can distinguish
		    // expired vs manual deletion if needed (e.g. for audit logs).
		    if ( (int) $result > 0 ) {
			    $reason = 'cron_expired_' . $statusLabel;
			    foreach ( $rowsToDelete as $row ) {
				    $hash = is_object( $row ) ? ( $row->hash ?? '' ) : ( $row['hash'] ?? '' );
				    if ( $hash !== '' ) {
					    $this->dispatchOptInDeletedEvent( (string) $hash, $reason, null );
				    }
			    }

			    // Keep the legacy aggregate event for back-compat —
			    // existing listeners (audit dashboard, telemetry) expect it.
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

		    // Force-mode short-circuit: an explicit user-button click
		    // ("Delete Unconfirmed Only" on the Database Management page)
		    // means "delete every unconfirmed opt-in NOW", regardless of
		    // the configured retention window. Reported by user 2026-04-30:
		    // "Delete Unconfirmed Only doesn't work" — the previous flow
		    // fell through to the configured period below, so an install
		    // with `delete_unconfirmed=30` + `period=days` would only
		    // delete unconfirmed entries older than 30 days, leaving every
		    // recent pending opt-in behind. force=true is the explicit
		    // override; cron-driven calls (force=false) continue to honour
		    // the configured retention period.
		    if ($force) {
			    $logger->info('Force mode enabled - deleting ALL unconfirmed opt-ins regardless of period.', [
				    'plugin' => 'double-opt-in',
			    ]);
			    // time()+86400 ensures every row's createtime < cutoff.
			    $timestamp = time() + 86400;
			    $this->removeOlderThan($timestamp, 0);
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

		    // Force-mode short-circuit — same rationale as
		    // removeUnconfirmedOptins above. The "Delete Confirmed Only"
		    // admin button means "delete every confirmed opt-in NOW",
		    // not "respect the configured retention window".
		    if ($force) {
			    $this->get_logger()->info('Force mode enabled - deleting ALL confirmed opt-ins regardless of period.', [
				    'plugin' => 'double-opt-in',
			    ]);
			    $timestamp = time() + 86400;
			    $this->removeOlderThan($timestamp, 1);
			    return;
		    }

		    // Validate period to avoid invalid strtotime() behavior
		    $allowedPeriods = ['days', 'weeks', 'months', 'years'];
		    if (!in_array($deletePeriod, $allowedPeriods, true)) {
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
	    /**
	     * Public so other deletion sites (REST handler, addon cleanup
	     * actors) can route through the same event-dispatch pipeline
	     * — single integration point for OptIn-deletion listeners,
	     * including the file-storage cascade-cleanup.
	     */
	    public function dispatchOptInDeletedEvent( string $hash, string $reason, ?int $deletedBy = null ): void {
		    try {
			    $container = Container::getInstance();
			    if ( $container->has( EventDispatcherInterface::class ) ) {
				    $dispatcher = $container->get( EventDispatcherInterface::class );
				    // OptInDeletedEvent's third arg is `string $deletedBy`
				    // — cron-context callers pass null (no logged-in
				    // user). Map null to 'system' so the typed
				    // constructor doesn't fatal. Also coerce int
				    // (manual REST: get_current_user_id()) to string
				    // explicitly.
				    $deletedByLabel = $deletedBy !== null ? (string) $deletedBy : 'system';
				    $event = new OptInDeletedEvent( $hash, $reason, $deletedByLabel );
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