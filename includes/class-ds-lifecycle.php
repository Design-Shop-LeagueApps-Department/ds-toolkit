<?php
if ( ! defined( 'ABSPATH' ) && ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

/**
 * What the toolkit leaves on a site, in ONE list, and how it is taken away again.
 *
 *  - Deactivating (Plugins > Deactivate, `wp plugin deactivate`) stops everything that would keep running
 *    without the plugin: its scheduled jobs and the Origin Guard mu-plugin (a must-use plugin stays active
 *    whatever happens to the plugin that wrote it). Settings and data stay, so switching the plugin back on
 *    restores the site as it was: every job re-schedules itself and Origin Guard is written again.
 *    WordPress does not run this during a plugin UPDATE, so updates are unaffected.
 *  - Deleting the plugin (uninstall.php) does the same and then removes the toolkit's own records: settings,
 *    security state, keys, logs, caches, downloaded roster photos.
 *
 * Until the 2026-11 audit there was no deactivation step at all, and uninstall left the active mu-plugin, the
 * Tripwire jobs and about fifteen options behind.
 *
 * A new option, transient or scheduled job belongs in one of the lists below, in the same change that adds it
 * (tests/lifecycle-test.php fails when the code schedules a job this file does not know).
 *
 * Deliberately NOT removed, because it is the site's own content rather than the plugin's records: the Partner
 * role and the accounts that hold it (removing the role would lock partners out), Theme Setting design values
 * (ds_button_style, the social card), ds_blueprint_version and ds_site_launched (facts about the site), Table
 * module posts, and anything Beaver Builder saved in a layout.
 */
class DS_Toolkit_Lifecycle {

	/** Every WP-Cron hook a feature schedules. */
	const CRON_HOOKS = array(
		'ds_tripwire_daily', 'ds_tripwire_content', 'ds_tripwire_mailcheck',
		'ds_hq_link_enroll', 'ds_hq_link_checkin', 'ds_hq_link_flush', 'ds_hq_update_run',
		'ds_la_roster_sync', 'ds_la_roster_continue', 'ds_la_roster_photos',
		'ds_calendar_refresh',
		'ds_table_sync', 'ds_table_refresh',
	);

	/**
	 * Hooks of code that no longer ships (the Schedule block, retired in 1.10.44 and removed in the 2026-11
	 * audit). Nothing schedules them now; a job queued by an old version is still cleared with the rest.
	 */
	const RETIRED_CRON_HOOKS = array( 'ds_la_schedule_refresh' );

	/** Options removed on uninstall, by exact name. */
	const OPTIONS = array(
		'ds_toolkit_settings', 'ds_toolkit_assets_version', 'ds_toolkit_assets_print', 'ds_toolkit_update_check',
		'ds_hq_link', 'ds_hq_link_lock', 'ds_hq_update', 'ds_hq_update_lock',
		'ds_tripwire_state', 'ds_tripwire_lock', 'ds_tripwire_last_notify', 'ds_tripwire_last_skip', 'ds_tripwire_prelaunch_quiet',
		'ds_mailcheck_result', 'ds_mailcheck_to', 'ds_mailcheck_token',
		'ds_bot_shield_events', 'ds_bot_shield_iplog', 'ds_bot_shield_totals',
		'ds_origin_guard_state', 'ds_partner_role_state',
		'ds_la_private_keys', 'ds_la_key_audit', 'ds_programs_ledger',
		'ds_academy_tutorials_backup', 'ds_school_emblems_index_last',
	);

	/** Options removed on uninstall, by prefix (one row per site, team, program or token). */
	const OPTION_PREFIXES = array( 'ds_la_roster_', 'ds_la_sched_', 'ds_calendar_lock_', 'ds_mailcheck_claim_', 'ds_table_last_' );

	/** Transients removed on uninstall, by prefix. */
	const TRANSIENT_PREFIXES = array(
		'ds_programs_', 'ds_la_roster_', 'ds_la_sched_', 'ds_calendar_', 'ds_hq_link_', 'ds_toolkit_latest_release',
		'ds_tripwire_', 'ds_table_', 'ds_academy_', 'ds_school_emblems_',
	);

	/** Plugins > Deactivate. */
	public static function deactivate() {
		foreach ( array_merge( self::CRON_HOOKS, self::RETIRED_CRON_HOOKS ) as $hook ) {
			wp_unschedule_hook( $hook ); // every event of the hook, whatever its arguments
		}
		self::remove_origin_guard();
	}

	/**
	 * The Origin Guard mu-plugin, if it is ours. Its saved fingerprint goes too, so the installer writes the file
	 * again the first time the plugin is active (it only writes when the fingerprint differs).
	 */
	public static function remove_origin_guard() {
		$dir  = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : ( defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR . '/mu-plugins' : '' );
		$path = $dir . '/ds-origin-guard.php';
		if ( '' !== $dir && is_file( $path ) ) {
			$head = (string) file_get_contents( $path, false, null, 0, 512 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( false !== strpos( $head, 'DS Origin Guard' ) ) { // never a stranger's file
				@unlink( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
		}
		delete_option( 'ds_origin_guard_state' );
	}

	/** Plugins > Delete (called from uninstall.php, where the plugin itself is not loaded). */
	public static function uninstall() {
		global $wpdb;
		self::deactivate();
		foreach ( self::OPTIONS as $name ) {
			delete_option( $name );
		}
		$like = array();
		foreach ( self::OPTION_PREFIXES as $pre ) {
			$like[] = $wpdb->prepare( 'option_name LIKE %s', $wpdb->esc_like( $pre ) . '%' );
		}
		foreach ( self::TRANSIENT_PREFIXES as $pre ) {
			$like[] = $wpdb->prepare( 'option_name LIKE %s', $wpdb->esc_like( '_transient_' . $pre ) . '%' );
			$like[] = $wpdb->prepare( 'option_name LIKE %s', $wpdb->esc_like( '_transient_timeout_' . $pre ) . '%' );
		}
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE " . implode( ' OR ', $like ) ); // phpcs:ignore WordPress.DB.PreparedSQL -- each part prepared above
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush(); // the rows above were deleted under the object cache
		}
		self::remove_roster_photos();
	}

	/** uploads/ds-la-roster/<site>/: the photos the Roster block downloaded (never in the Media Library). */
	private static function remove_roster_photos() {
		$up   = wp_upload_dir( null, false );
		$root = trailingslashit( $up['basedir'] ) . 'ds-la-roster';
		foreach ( glob( $root . '/*', GLOB_ONLYDIR ) ?: array() as $dir ) {
			foreach ( glob( $dir . '/*' ) ?: array() as $file ) {
				if ( is_file( $file ) ) {
					@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				}
			}
			@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		@rmdir( $root ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
}
