<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Player photos for the LeagueApps Roster block, downloaded ONLY for teams a block actually shows.
 *
 * LeagueApps keeps one profile photo per person on its own S3 storage, 150x150 at most (the
 * export's `photo` is the "-s3" size; "-s2" is 75, "-s1" is 40, and nothing larger is public).
 * A big club has hundreds (Austin Junior VB: 1,693 of 2,814 roster rows), so nothing is fetched
 * for the club as a whole: a team is "wanted" when a Roster block with photos on renders it or a
 * page holding one is saved, and only that team's photos are fetched.
 *
 *  - In the builder (the moment the team is picked and the block saved) the team's photos are
 *    fetched right away, up to BUILDER_BUDGET seconds; visitors never wait: for them a missing
 *    photo only queues the background job.
 *  - Only LeagueApps' photo address is accepted (DS_Roster_Data::photo_url()), over verified TLS,
 *    no redirects, at most MAX_BYTES; the image is re-encoded through WordPress's image editor
 *    (proves it is an image, strips metadata) into uploads/ds-la-roster/<site>/, never the Media
 *    Library.
 *  - Nothing is downloaded twice: a photo is filed under a hash of its LeagueApps address, so the same
 *    team on another page, or a player on two teams, finds it already there; a file already in the
 *    folder is taken back without a request even if the list was lost; and one fetch runs at a time
 *    per site (an atomic database lock), so the builder, the publish hook and the background job never
 *    fetch the same photo at once.
 *  - Each wanted team records the pages that show it. Publishing a page whose block no longer shows
 *    photos (or no longer has the block) takes that page off; a team no page shows any more has its
 *    photos deleted at once, so switching photos off is enough to take them down. A team nobody has
 *    viewed for FORGET (90 days: a page deleted without being saved) is forgotten too.
 *  - After each hourly roster sync a separate job re-checks wanted teams (new players, changed photos);
 *    the photo of someone no shown team has any more (they left) is deleted. Removing the key deletes the
 *    site's folder. The folder is public like the rest of uploads (not listed in the Media Library):
 *    file names are opaque UUIDs (see hash()), so they can neither be guessed nor traced back to a person.
 */
class DS_Roster_Photos {

	const OPT            = 'ds_la_roster_ph_';   // + site id, autoload no
	const HOOK           = 'ds_la_roster_photos';
	const DIR            = 'ds-la-roster';
	const MAX_BYTES      = 300000;
	const MAX_DIM        = 600;
	const OUT            = 150;
	const FORGET         = 90 * DAY_IN_SECONDS;  // a team no page has shown for this long is forgotten
	const RETRY_GONE     = 30 * DAY_IN_SECONDS;  // a photo LeagueApps did not have is asked for again after this
	const LOCK_TTL       = 60;
	const BUDGET         = 20;
	const BUILDER_BUDGET = 8;
	const NAME_KEY_OPT   = 'ds_la_roster_photo_key'; // per-install secret for file names, autoload no
	const OLD_GRACE      = 2 * DAY_IN_SECONDS;     // a file moved to its new name keeps the old one this long

	/** @var string|null the file-name secret, read once per request */
	private static $name_key = null;

	/** Folder for a site's photos: array( path, url ). */
	public static function dir( $site_id ) {
		$u = wp_upload_dir( null, false );
		$s = preg_replace( '/\D+/', '', (string) $site_id );
		return array( trailingslashit( $u['basedir'] ) . self::DIR . '/' . $s, trailingslashit( $u['baseurl'] ) . self::DIR . '/' . $s );
	}

	/** The site's photo list. $fresh = from the database (always, when it is about to be changed and saved). */
	public static function manifest( $site_id, $fresh = false ) {
		$m = $fresh ? DS_Roster_Data::fresh_option( self::OPT . $site_id, array() ) : get_option( self::OPT . $site_id, array() );
		$m = is_array( $m ) ? $m : array();
		return array( 'teams' => (array) ( $m['teams'] ?? array() ), 'files' => (array) ( $m['files'] ?? array() ) );
	}

	private static function save( $site_id, array $m ) {
		update_option( self::OPT . $site_id, $m, false );
	}

	/**
	 * A photo's file name (without .jpg): a UUID, never anything a visitor can work back to a person.
	 * LeagueApps' photo address carries the person's sequential numeric user id (profile-<8 digits>-s3.jpg),
	 * so a plain hash of it (what 1.10.41-1.17.2 used) could be reversed by hashing every id in range. This
	 * is HMAC-SHA256 under a random per-install secret, cut to 128 bits and shaped as an RFC 9562 version 8
	 * UUID. It is stable for an address, so a photo is still never downloaded twice.
	 */
	public static function hash( $url ) {
		$b    = substr( hash_hmac( 'sha256', (string) $url, self::name_key(), true ), 0, 16 );
		$b[6] = chr( ( ord( $b[6] ) & 0x0f ) | 0x80 ); // version 8 (custom)
		$b[8] = chr( ( ord( $b[8] ) & 0x3f ) | 0x80 ); // RFC 9562 variant
		$h    = bin2hex( $b );
		return substr( $h, 0, 8 ) . '-' . substr( $h, 8, 4 ) . '-' . substr( $h, 12, 4 ) . '-' . substr( $h, 16, 4 ) . '-' . substr( $h, 20 );
	}

	/** The 1.10.41-1.17.2 name (plain sha256 of the address). Only used to move those files to their new name. */
	public static function legacy_hash( $url ) {
		return substr( hash( 'sha256', (string) $url ), 0, 32 );
	}

	/**
	 * The install's secret for file names, made once. add_option() so two requests racing to make it both end
	 * up with the one that was saved. If the database cannot save it, this request uses its own: the names
	 * then differ per request, which costs a download, never a leak.
	 */
	private static function name_key() {
		if ( null !== self::$name_key ) {
			return self::$name_key;
		}
		$v = (string) DS_Roster_Data::fresh_option( self::NAME_KEY_OPT, '' );
		if ( ! preg_match( '/^[0-9a-f]{64}$/', $v ) ) {
			$new = bin2hex( random_bytes( 32 ) );
			if ( '' === $v ) {
				add_option( self::NAME_KEY_OPT, $new, '', false );
			} else {
				update_option( self::NAME_KEY_OPT, $new, false ); // damaged value
			}
			$v = (string) DS_Roster_Data::fresh_option( self::NAME_KEY_OPT, '' );
			if ( ! preg_match( '/^[0-9a-f]{64}$/', $v ) ) {
				$v = $new;
			}
		}
		return self::$name_key = hex2bin( $v );
	}

	/** Forget the remembered secret (tests: a second install in the same process). */
	public static function reset_name_key() {
		self::$name_key = null;
	}

	/** Every photo address in a team, by member. */
	private static function team_urls( $site_id, $team_id ) {
		$t    = DS_Roster_Data::team( $site_id . ':' . $team_id );
		$urls = array();
		foreach ( array_merge( (array) ( $t['players'] ?? array() ), (array) ( $t['coaches'] ?? array() ) ) as $p ) {
			if ( ! empty( $p['ph'] ) ) {
				$urls[] = (string) $p['ph'];
			}
		}
		return $urls;
	}

	/** photo address => local URL, for the photos already downloaded. */
	public static function local( $site_id, array $urls ) {
		$m   = self::manifest( $site_id );
		$out = array();
		list( , $base ) = self::dir( $site_id );
		foreach ( $urls as $u ) {
			// The old name until the background job has moved a 1.17.2 file, so photos never blink out on update.
			$f = $m['files'][ self::hash( $u ) ]['f'] ?? ( $m['files'][ self::legacy_hash( $u ) ]['f'] ?? '' );
			if ( '' !== $f ) {
				$out[ $u ] = $base . '/' . rawurlencode( $f );
			}
		}
		return $out;
	}

	/**
	 * A block shows this team with photos: remember it, and get what is missing. $now = in the
	 * builder (fetch right away, briefly); otherwise only queue the background job.
	 */
	public static function want( $site_id, $team_id, $now = false, $post_id = 0 ) {
		$site_id = preg_replace( '/\D+/', '', (string) $site_id );
		$team_id = preg_replace( '/\D+/', '', (string) $team_id );
		$post_id = (int) $post_id;
		if ( '' === $site_id || '' === $team_id ) {
			return;
		}
		$m    = self::manifest( $site_id );
		$seen = (int) ( $m['teams'][ $team_id ]['seen'] ?? 0 );
		$new  = $post_id && ! isset( $m['teams'][ $team_id ]['by'][ $post_id ] );
		if ( $new || time() - $seen > DAY_IN_SECONDS ) { // one write a day per team at most, not one per page view
			$m = self::manifest( $site_id, true );
			$m['teams'][ $team_id ]['seen'] = time();
			if ( $post_id ) {
				$m['teams'][ $team_id ]['by'][ $post_id ] = time();
			}
			self::save( $site_id, $m );
		}
		$missing = array_diff( array_map( array( __CLASS__, 'hash' ), self::team_urls( $site_id, $team_id ) ), array_keys( $m['files'] ) );
		if ( ! $missing ) {
			return;
		}
		if ( $now ) {
			self::fetch_team( $site_id, $team_id, microtime( true ) + self::BUILDER_BUDGET );
			$m       = self::manifest( $site_id );
			$missing = array_diff( array_map( array( __CLASS__, 'hash' ), self::team_urls( $site_id, $team_id ) ), array_keys( $m['files'] ) );
		}
		if ( $missing && function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time(), self::HOOK );
		}
	}

	/** After the hourly roster sync: check the wanted teams in a separate request a minute later. */
	public static function queue() {
		foreach ( DS_Roster_Data::sites_with_keys() as $sid ) {
			if ( self::manifest( $sid )['teams'] ) {
				if ( ! wp_next_scheduled( self::HOOK ) ) {
					wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::HOOK );
				}
				return;
			}
		}
	}

	/** Background job: fetch what wanted teams lack, forget old teams, delete unused photos. */
	public static function run() {
		$deadline = microtime( true ) + min( self::BUDGET, DS_Roster_Data::budget() ); // inside the host's time limit
		foreach ( DS_Roster_Data::sites_with_keys() as $sid ) {
			$m = self::manifest( $sid, true );
			if ( ! $m['teams'] && ! $m['files'] ) {
				continue;
			}
			foreach ( $m['teams'] as $tid => $t ) {
				if ( time() - (int) ( $t['seen'] ?? 0 ) > self::FORGET ) {
					unset( $m['teams'][ $tid ] ); // no page has shown it for 90 days
				}
			}
			self::save( $sid, $m );
			foreach ( array_keys( $m['teams'] ) as $tid ) {
				if ( microtime( true ) > $deadline ) {
					break;
				}
				self::fetch_team( $sid, (string) $tid, $deadline );
			}
			self::gc( $sid );
		}
		if ( microtime( true ) > $deadline && function_exists( 'wp_schedule_single_event' ) && ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::HOOK ); // more to fetch: carry on shortly
		}
	}

	private static function fetch_team( $site_id, $team_id, $deadline ) {
		$lock = self::OPT . 'lock_' . $site_id;
		if ( ! DS_Roster_Data::lock( $lock, self::LOCK_TTL ) ) {
			return; // another request is fetching this site's photos right now: it will get these too
		}
		list( $dir ) = self::dir( $site_id );
		$m = self::manifest( $site_id, true );
		foreach ( self::team_urls( $site_id, $team_id ) as $url ) {
			if ( microtime( true ) > $deadline ) {
				break;
			}
			$h = self::hash( $url );
			if ( isset( $m['files'][ $h ] ) && ( '' !== (string) $m['files'][ $h ]['f'] || time() - (int) $m['files'][ $h ]['t'] < self::RETRY_GONE ) ) {
				continue; // already here, or LeagueApps did not have it this month: not asked again
			}
			$on_disk = $dir . '/' . $h . '.jpg';
			// Saved by 1.10.41-1.17.2 under the old name: copied to the new one, no request to LeagueApps. The old
			// file is kept OLD_GRACE for cached pages that still link it, then gc() deletes it. A photo LeagueApps
			// did not have keeps that answer and its date.
			$old = self::legacy_hash( $url );
			$was = $dir . '/' . $old . '.jpg';
			$had = $m['files'][ $old ] ?? null;
			if ( is_array( $had ) && '' === (string) ( $had['f'] ?? '' ) ) {
				$m = self::manifest( $site_id, true );
				$m['files'][ $h ] = array( 'f' => '', 't' => (int) ( $had['t'] ?? time() ) );
				unset( $m['files'][ $old ] );
				self::save( $site_id, $m );
				continue;
			}
			if ( is_file( $was ) && @getimagesize( $was ) && @copy( $was, $on_disk ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
				$m = self::manifest( $site_id, true );
				$m['files'][ $h ]   = array( 'f' => $h . '.jpg', 't' => time() );
				$m['files'][ $old ] = array( 'f' => $old . '.jpg', 't' => (int) ( $had['t'] ?? time() ), 'old' => time() );
				self::save( $site_id, $m );
				continue;
			}
			// Already in the folder but missing from the list (the list was lost or reset): take it back, no request.
			if ( is_file( $on_disk ) && @getimagesize( $on_disk ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
				$m = self::manifest( $site_id, true );
				$m['files'][ $h ] = array( 'f' => $h . '.jpg', 't' => time() );
				self::save( $site_id, $m );
				continue;
			}
			$keys = DS_Roster_Data::fresh_option( DS_Roster_Data::KEYS_OPT, array() );
			if ( ! is_array( $keys ) || ! isset( $keys[ $site_id ] ) ) {
				break; // the key was removed while this ran: the folder was purged, do not recreate it
			}
			$file = self::download( $site_id, $url );
			if ( null === $file ) {
				continue; // LeagueApps did not answer: asked again next run
			}
			$m = self::manifest( $site_id, true );
			$m['files'][ $h ] = array( 'f' => $file, 't' => time() ); // '' = gone or not an image: not asked again until the address changes
			self::save( $site_id, $m );
		}
		DS_Roster_Data::unlock( $lock );
	}

	/**
	 * Download one photo and re-encode it into the site's folder. Returns the file name; '' when it is
	 * gone (403/404) or not a usable image; null when LeagueApps did not answer (try again later).
	 */
	public static function download( $site_id, $url ) {
		$url = DS_Roster_Data::photo_url( $url );
		if ( '' === $url ) {
			return '';
		}
		$res = wp_remote_get( $url, array(
			'timeout'             => 8,
			'redirection'         => 0,
			'sslverify'           => true,
			'reject_unsafe_urls'  => true,
			'limit_response_size' => self::MAX_BYTES,
		) );
		if ( is_wp_error( $res ) ) {
			return null;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( 200 !== $code ) {
			return in_array( $code, array( 403, 404, 410 ), true ) ? '' : null;
		}
		$body = (string) wp_remote_retrieve_body( $res );
		$info = strlen( $body ) < self::MAX_BYTES ? @getimagesizefromstring( $body ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! $info || ! in_array( $info['mime'] ?? '', array( 'image/png', 'image/jpeg' ), true ) || $info[0] > self::MAX_DIM || $info[1] > self::MAX_DIM || $info[0] < 16 || $info[1] < 16 ) {
			return '';
		}
		list( $dir ) = self::dir( $site_id );
		if ( ! wp_mkdir_p( $dir ) ) {
			return '';
		}
		// The folder guard is an index.html: a PHP file under uploads is what the fleet's own malware scanners
		// look for. Our earlier index.php (exactly these two lines) is replaced.
		if ( ! file_exists( $dir . '/index.html' ) ) {
			file_put_contents( $dir . '/index.html', '' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		if ( is_file( $dir . '/index.php' ) && "<?php\n// Silence is golden.\n" === (string) @file_get_contents( $dir . '/index.php' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
			@unlink( $dir . '/index.php' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		// tempnam() + get_temp_dir(), not wp_tempnam(): that one lives in wp-admin/includes/file.php, which
		// WP-cron does not load (the background job died there).
		$tmp = @tempnam( get_temp_dir(), 'dslaph' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( ! $tmp ) {
			return null;
		}
		file_put_contents( $tmp, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$name = self::hash( $url ) . '.jpg';
		$ok   = false;
		// GD first: Imagick skips the resize of an image that is already 150 px or smaller and then saves it with
		// its original EXIF/XMP, while GD always re-encodes from pixels, so nothing but the picture is kept.
		$gd_first = function ( $editors ) { return array_values( array_unique( array_merge( array( 'WP_Image_Editor_GD' ), (array) $editors ) ) ); };
		add_filter( 'wp_image_editors', $gd_first );
		$ed   = function_exists( 'wp_get_image_editor' ) ? wp_get_image_editor( $tmp ) : new WP_Error( 'no_editor', '' );
		remove_filter( 'wp_image_editors', $gd_first );
		if ( ! is_wp_error( $ed ) ) {
			$ed->resize( self::OUT, self::OUT, false ); // never larger than LeagueApps' 150
			$ed->set_quality( 85 );
			$ok = ! is_wp_error( $ed->save( $dir . '/' . $name, 'image/jpeg' ) ); // re-encoded: metadata dropped
		}
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		return $ok ? $name : '';
	}

	/** Delete photos no wanted team uses any more (players who left, teams nobody shows). */
	public static function gc( $site_id ) {
		$st = DS_Roster_Data::store( $site_id, true );
		if ( empty( $st['teams'] ) || ! empty( $st['partial'] ) ) {
			return; // a roster being rebuilt (or not there yet) says nothing about who left
		}
		$m    = self::manifest( $site_id, true );
		$keep = array();
		foreach ( array_keys( $m['teams'] ) as $tid ) {
			foreach ( self::team_urls( $site_id, (string) $tid ) as $u ) {
				$nh          = self::hash( $u );
				$keep[ $nh ] = true;
				if ( ! isset( $m['files'][ $nh ] ) ) {
					$keep[ self::legacy_hash( $u ) ] = true; // a 1.17.2 file not moved yet: it is still the photo
				}
			}
		}
		list( $dir ) = self::dir( $site_id );
		foreach ( $m['files'] as $h => $f ) {
			if ( ! empty( $f['old'] ) && time() - (int) $f['old'] < self::OLD_GRACE ) {
				continue; // moved to its new name: the old file stays a while for cached pages
			}
			if ( ! isset( $keep[ $h ] ) ) {
				if ( '' !== (string) ( $f['f'] ?? '' ) ) {
					@unlink( $dir . '/' . basename( (string) $f['f'] ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				}
				unset( $m['files'][ $h ] );
			}
		}
		self::save( $site_id, $m );
	}

	/** The key was removed: every photo and the list go. */
	public static function purge( $site_id ) {
		$site_id = preg_replace( '/\D+/', '', (string) $site_id );
		list( $dir ) = self::dir( $site_id );
		foreach ( glob( $dir . '/*' ) ?: array() as $f ) {
			if ( is_file( $f ) ) {
				@unlink( $f ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			}
		}
		@rmdir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		delete_option( self::OPT . $site_id );
	}

	/**
	 * A page was published in the builder (fl_builder_after_save_layout): every Roster block on it with
	 * photos on wants its team; a team this page wanted before and does not any more (photos switched off,
	 * the team changed, the block removed) loses this page, and a team no page wants then has its photos
	 * deleted straight away. A draft save changes nothing: the published page still shows what it showed.
	 */
	public static function after_save( $post_id, $publish, $data ) {
		if ( ! $publish ) {
			return;
		}
		$post_id = (int) $post_id;
		$wanted  = array(); // site => team => true
		foreach ( (array) $data as $n ) {
			$s = is_object( $n ) ? ( $n->settings ?? null ) : null;
			if ( $s && 'ds-la-roster' === ( $s->type ?? '' ) && 'yes' === ( $s->show_photos ?? 'no' ) && preg_match( '/^(\d+):(\d+)$/', (string) ( $s->team ?? '' ), $m ) ) {
				$wanted[ $m[1] ][ $m[2] ] = true;
			}
		}
		foreach ( DS_Roster_Data::sites_with_keys() as $sid ) {
			$m       = self::manifest( $sid, true );
			$changed = false;
			foreach ( $m['teams'] as $tid => $t ) {
				if ( $post_id && isset( $t['by'][ $post_id ] ) && empty( $wanted[ $sid ][ $tid ] ) ) {
					unset( $m['teams'][ $tid ]['by'][ $post_id ] );
					if ( empty( $m['teams'][ $tid ]['by'] ) ) {
						unset( $m['teams'][ $tid ] ); // no page shows these photos any more: they go now
					}
					$changed = true;
				}
			}
			if ( $changed ) {
				self::save( $sid, $m );
				self::gc( $sid );
			}
		}
		foreach ( $wanted as $sid => $teams ) {
			foreach ( array_keys( $teams ) as $tid ) {
				self::want( (string) $sid, (string) $tid, false, $post_id );
			}
		}
	}
}
