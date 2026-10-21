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
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ds\_la\_roster\_%' OR option_name LIKE '\_transient\_ds\_la\_roster\_%' OR option_name LIKE '\_transient\_timeout\_ds\_la\_roster\_%'" );
wp_clear_scheduled_hook( 'ds_la_roster_sync' );
wp_clear_scheduled_hook( 'ds_la_roster_continue' );
// LeagueApps Team Schedule: one stored schedule per program, its locks and its refresh job.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ds\_la\_sched\_%' OR option_name LIKE '\_transient\_ds\_la\_sched\_%' OR option_name LIKE '\_transient\_timeout\_ds\_la\_sched\_%'" );
wp_unschedule_hook( 'ds_la_schedule_refresh' );
// LeagueApps Calendar: schedule stores, merged copies, locks and its refresh job.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_ds\_calendar\_%' OR option_name LIKE '\_transient\_timeout\_ds\_calendar\_%' OR option_name LIKE 'ds\_calendar\_lock\_%'" );
wp_unschedule_hook( 'ds_calendar_refresh' ); // every queued refresh, whatever its site-id arguments
