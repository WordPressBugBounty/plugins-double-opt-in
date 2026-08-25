<?php
namespace forge12\contactform7\CF7DoubleOptIn;

use Forge12\Shared\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registriert alle Cronjobs für das Plugin.
 */
function add_cron_jobs() {
	$logger = Logger::getInstance();

	// 🔹 Daily Telemetry Job — abgeschafft, und bestehende Planungen aufräumen.
	//
	// Der Job hat täglich an einen Endpoint gepostet, den es nicht mehr gibt:
	// silentshield.forge12.com trägt ein Zertifikat für einen fremden Host
	// (CN=portal.silentchat.de, abgelaufen am 14.07.2026) und liefert auf dem
	// Telemetrie-Pfad 404. Übertragen wurde deshalb ohnehin nichts — WordPress
	// prüft das Zertifikat und die Verbindung scheiterte.
	//
	// Das hat allerdings einen zweiten Fehler verdeckt: der Job wurde
	// unabhängig von der Einstellung "telemetry" geplant, und send_snapshot()
	// hat sie nie geprüft. Wer Telemetrie abgeschaltet hatte, hätte also
	// gesendet, sobald der Endpoint zurückkommt. Solange es kein funktionierendes
	// Ziel gibt, wird gar nicht mehr geplant; die Zähler laufen weiter lokal,
	// weil die Review-Notice sie braucht.
	//
	// Aufräumen ist Pflicht, nicht Kosmetik: auf bestehenden Installationen
	// steht der Job bereits im WP-Cron und bliebe dort für immer stehen.
	if ( wp_next_scheduled( 'f12_cf7_doubleoptin_daily_telemetry' ) ) {
		wp_clear_scheduled_hook( 'f12_cf7_doubleoptin_daily_telemetry' );
		$logger->info( "Cron event removed", [
			'plugin' => 'double-opt-in',
			'job'    => 'f12_cf7_doubleoptin_daily_telemetry',
			'reason' => 'telemetry transport retired — no reachable endpoint',
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
