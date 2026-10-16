<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

delete_option( 'ds_toolkit_settings' );
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
// LeagueApps Calendar: schedule stores, merged copies, locks and its refresh job.
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_ds\_calendar\_%' OR option_name LIKE '\_transient\_timeout\_ds\_calendar\_%' OR option_name LIKE 'ds\_calendar\_lock\_%'" );
wp_unschedule_hook( 'ds_calendar_refresh' ); // every queued refresh, whatever its site-id arguments
