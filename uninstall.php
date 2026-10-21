<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

delete_option( 'ds_toolkit_settings' );
delete_option( 'ds_toolkit_assets_version' );
delete_transient( 'ds_toolkit_latest_release' );
// Tripwire -> Design Shop HQ link: this site's key, its queue and its cron events.
delete_option( 'ds_hq_link' );
delete_option( 'ds_hq_link_lock' );
delete_option( 'ds_hq_update' );
delete_option( 'ds_hq_update_lock' );
delete_transient( 'ds_hq_link_status_checked' );
delete_transient( 'ds_hq_link_ping' );
foreach ( array( 'ds_hq_link_checkin', 'ds_hq_link_flush', 'ds_hq_link_enroll', 'ds_hq_update_run' ) as $ds_hook ) {
	wp_clear_scheduled_hook( $ds_hook );
}
global $wpdb;
// LeagueApps Programs: feed copies, locks, known sports / seasons / levels, the key list.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_ds\_programs\_%' OR option_name LIKE '\_transient\_timeout\_ds\_programs\_%'" );
// LeagueApps Roster: the sealed private keys, the stored public rosters, tokens, locks and the sync job.
delete_option( 'ds_la_private_keys' );
delete_option( 'ds_la_key_audit' );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ds\_la\_roster\_%' OR option_name LIKE '\_transient\_ds\_la\_roster\_%' OR option_name LIKE '\_transient\_timeout\_ds\_la\_roster\_%'" );
wp_clear_scheduled_hook( 'ds_la_roster_sync' );
wp_clear_scheduled_hook( 'ds_la_roster_continue' );
wp_unschedule_hook( 'ds_la_roster_photos' );
// LeagueApps Roster photos: uploads/ds-la-roster/<site>/ (lists are ds_la_roster_ph_<site>, removed above).
$ds_up = wp_upload_dir( null, false );
foreach ( glob( trailingslashit( $ds_up['basedir'] ) . 'ds-la-roster/*', GLOB_ONLYDIR ) ?: array() as $ds_dir ) {
	foreach ( glob( $ds_dir . '/*' ) ?: array() as $ds_f ) {
		if ( is_file( $ds_f ) ) { @unlink( $ds_f ); } // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	@rmdir( $ds_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
}
@rmdir( trailingslashit( $ds_up['basedir'] ) . 'ds-la-roster' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
// LeagueApps Team Schedule: one stored schedule per program, its locks and its refresh job.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ds\_la\_sched\_%' OR option_name LIKE '\_transient\_ds\_la\_sched\_%' OR option_name LIKE '\_transient\_timeout\_ds\_la\_sched\_%'" );
wp_unschedule_hook( 'ds_la_schedule_refresh' );
// LeagueApps Calendar: schedule stores, merged copies, locks and its refresh job.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_ds\_calendar\_%' OR option_name LIKE '\_transient\_timeout\_ds\_calendar\_%' OR option_name LIKE 'ds\_calendar\_lock\_%'" );
wp_unschedule_hook( 'ds_calendar_refresh' ); // every queued refresh, whatever its site-id arguments
