<?php
namespace forge12\contactform7\CF7DoubleOptIn;

use Forge12\Shared\Logger;

/**
 * Registriert alle Cronjobs für das Plugin.
 */
function add_cron_jobs() {
	$logger = Logger::getInstance();

	// 🔹 Daily Telemetry Job
	if ( ! wp_next_scheduled( 'f12_cf7_doubleoptin_daily_telemetry' ) ) {
		wp_schedule_event( time(), 'daily', 'f12_cf7_doubleoptin_daily_telemetry' );
		$logger->info( "Cron event scheduled", [
			'plugin'   => 'double-opt-in',
			'job'      => 'f12_cf7_doubleoptin_daily_telemetry',
			'interval' => 'daily'
		] );
	} else {
		$logger->debug( "Cron event already scheduled", [
			'plugin' => 'double-opt-in',
			'job'    => 'f12_cf7_doubleoptin_daily_telemetry',
			'interval' => 'daily',
		] );
	}

	// Add cron
	if ( ! wp_next_scheduled( 'dailyOptinClear' ) ) {
		wp_schedule_event( time(), 'daily', 'dailyOptinClear' );
		$logger->info( 'Cron event scheduled', [
			'plugin' => 'double-opt-in',
			'job'    => 'dailyOptinClear',
			'interval' => 'daily',
		] );
	} else {
		$logger->debug( 'Cron event already scheduled', [
			'plugin' => 'double-opt-in',
			'job'    => 'dailyOptinClear',
			'interval' => 'daily',
		] );
	}
}
