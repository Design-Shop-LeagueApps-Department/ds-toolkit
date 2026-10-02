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
