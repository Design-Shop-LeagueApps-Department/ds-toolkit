<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Registers the in-house "LeagueApps Roster" Beaver Builder module (modules/ds-la-roster/):
 * one LeagueApps team's players and coaches as a designed roster.
 *
 * Opt-in on every blueprint like the other LeagueApps modules. Rosters need the club's PRIVATE
 * LeagueApps key (the public API has no roster), saved per site on the LeagueApps tab by an
 * administrator; DS_Roster_Data keeps it sealed and stores only the public fields.
 */
class DS_LA_Roster {

	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = $settings;
	}

	public function init() {
		require_once DS_TOOLKIT_PATH . 'includes/class-ds-programs-data.php';
		require_once DS_TOOLKIT_PATH . 'includes/class-ds-roster-data.php';
		add_action( 'init', array( $this, 'register_module' ), 20 );
		add_action( 'ds_la_roster_sync', array( 'DS_Roster_Data', 'cron' ) );
		add_action( 'ds_la_roster_continue', array( 'DS_Roster_Data', 'cron' ) ); // a long first sync carrying on
		add_action( 'init', array( $this, 'schedule' ) );
		add_action( 'admin_post_ds_la_roster_key', array( $this, 'save_key' ) );
		add_action( 'admin_post_ds_la_roster_key_delete', array( $this, 'delete_key' ) );
		add_action( 'admin_post_ds_la_roster_sync', array( $this, 'sync_now' ) );
	}

	public function register_module() {
		if ( class_exists( 'FLBuilder' ) && class_exists( 'FLBuilderModule' ) ) {
			require_once DS_TOOLKIT_PATH . 'modules/ds-la-roster/ds-la-roster.php';
		}
	}

	/** Hourly background sync, only while some site has a key. */
	public function schedule() {
		$has = (bool) DS_Roster_Data::sites_with_keys();
		$at  = wp_next_scheduled( 'ds_la_roster_sync' );
		if ( $has && ! $at ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', 'ds_la_roster_sync' );
		} elseif ( ! $has && $at ) {
			wp_clear_scheduled_hook( 'ds_la_roster_sync' );
		}
	}

	private function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Only an administrator can manage the LeagueApps private key.', 'ds-toolkit' ), 403 );
		}
		check_admin_referer( $action );
	}

	private function back( $msg, $ok = true ) {
		// The result is kept for this user for a minute, not put in the URL, so nobody can craft a
		// link that shows an administrator a made-up message on this screen.
		set_transient( 'ds_la_roster_msg_' . get_current_user_id(), array( 'm' => (string) $msg, 'ok' => (bool) $ok ), MINUTE_IN_SECONDS );
		$url = add_query_arg( array( 'page' => 'ds-toolkit', 'tab' => 'leagueapps' ), admin_url( 'options-general.php' ) );
		wp_safe_redirect( $url . '#ds-la-roster' );
		exit;
	}

	public function save_key() {
		$this->guard( 'ds_la_roster_key' );
		$site   = preg_replace( '/\D+/', '', (string) wp_unslash( $_POST['site_id'] ?? '' ) );
		$client = (string) wp_unslash( $_POST['client_id'] ?? '' );
		$known  = wp_list_pluck( DS_Programs_Data::configured_sites(), 'site_id' );
		if ( ! in_array( $site, array_map( 'strval', $known ), true ) ) {
			$this->back( __( 'Pick one of the sites saved above.', 'ds-toolkit' ), false );
		}
		$file = $_FILES['key_file'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- read as bytes, never stored as a file
		if ( ! $file || ! empty( $file['error'] ) || ! is_uploaded_file( $file['tmp_name'] ) || (int) $file['size'] > 20000 ) {
			$this->back( __( 'Choose the .p12 (or .pem) key file LeagueApps gave you.', 'ds-toolkit' ), false );
		}
		$bytes = (string) file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		@unlink( $file['tmp_name'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( '' === trim( $client ) && preg_match( '/^([A-Za-z0-9]{16,64})\.(p12|pem)$/i', (string) ( $file['name'] ?? '' ), $m ) ) {
			$client = $m[1]; // LeagueApps names the file after the client ID
		}
		$pem = DS_Roster_Data::pem_from_upload( $bytes );
		if ( is_wp_error( $pem ) ) {
			$this->back( $pem->get_error_message(), false );
		}
		$ok = DS_Roster_Data::save_key( $site, $client, $pem );
		if ( is_wp_error( $ok ) ) {
			$this->back( $ok->get_error_message(), false );
		}
		$this->schedule();
		// The first pull reads the whole club (Surfside: 13,668 registrations, about 50 s), longer than
		// a host lets one request run: it happens in the background, page by page.
		DS_Roster_Data::sync_soon();
		$this->back( __( 'Key saved. Rosters are syncing in the background; teams appear here within a few minutes (reload to check).', 'ds-toolkit' ) );
	}

	public function delete_key() {
		$this->guard( 'ds_la_roster_key_delete' );
		DS_Roster_Data::delete_key( (string) wp_unslash( $_POST['site_id'] ?? '' ) );
		$this->schedule();
		$this->back( __( 'Private key and stored rosters removed.', 'ds-toolkit' ) );
	}

	public function sync_now() {
		$this->guard( 'ds_la_roster_sync' );
		$site  = preg_replace( '/\D+/', '', (string) wp_unslash( $_POST['site_id'] ?? '' ) );
		$store = DS_Roster_Data::store( $site );
		$store['full'] = 0; // "Sync now" = a full rebuild, so removed players drop off too
		update_option( DS_Roster_Data::STORE_PRE . $site, $store, false );
		DS_Roster_Data::sync_soon();
		$this->back( __( 'Rosters are syncing in the background; reload in a minute or two to see the result.', 'ds-toolkit' ) );
	}
}
