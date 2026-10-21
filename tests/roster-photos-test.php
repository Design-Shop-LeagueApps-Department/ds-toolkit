<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * LeagueApps Roster photos (DS_Roster_Photos): only LeagueApps' photo address is ever fetched,
 * only for teams a block shows, re-encoded into the site's own folder, retried after a network
 * failure but not after a 404 or a non-image, cleaned up when players leave or nobody shows the
 * team, removed with the key. Stub-style, no WordPress needed:
 *
 *   php tests/roster-photos-test.php
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'MINUTE_IN_SECONDS', 60 ); define( 'HOUR_IN_SECONDS', 3600 ); define( 'DAY_IN_SECONDS', 86400 );
define( 'AUTH_KEY', 'a' ); define( 'SECURE_AUTH_KEY', 'b' ); define( 'LOGGED_IN_KEY', 'c' ); define( 'DB_NAME', 'wp_t' ); $table_prefix = 'wp_';
$UP = sys_get_temp_dir() . '/ds-photos-test-' . getmypid();
$GLOBALS['opts'] = array(); $GLOBALS['tr'] = array(); $GLOBALS['cron'] = array(); $GLOBALS['fetched'] = array(); $GLOBALS['answers'] = array();
// get_option reads a (possibly stale) object cache first, like WordPress with memcached.
$GLOBALS['oc'] = array();
function get_option( $k, $d = false ) { if ( array_key_exists( $k, $GLOBALS['oc'] ) ) { return $GLOBALS['oc'][ $k ]; } return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function wp_cache_delete( $k, $g = '' ) { unset( $GLOBALS['oc'][ $k ] ); return true; }
function wp_cache_get( $k, $g = '' ) { return 'notoptions' === $k ? ( $GLOBALS['notoptions'] ?? false ) : false; }
function wp_cache_set( $k, $v, $g = '' ) { if ( 'notoptions' === $k ) { $GLOBALS['notoptions'] = $v; } return true; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_transient( $k ) { return $GLOBALS['tr'][ $k ] ?? false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['tr'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['tr'][ $k ] ); return true; }
function __( $s, $d = '' ) { return $s; }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function get_current_user_id() { return 1; }
function is_wp_error( $e ) { return $e instanceof WP_Error; }
class WP_Error { private $c, $m; function __construct( $c = '', $m = '' ) { $this->c = $c; $this->m = $m; } function get_error_message() { return $this->m; } function get_error_code() { return $this->c; } }
function wp_next_scheduled( $h ) { foreach ( $GLOBALS['cron'] as $c ) { if ( $c[1] === $h ) { return $c[0]; } } return false; }
function wp_schedule_single_event( $t, $h ) { $GLOBALS['cron'][] = array( $t, $h ); return true; }
function wp_upload_dir( $a = null, $b = true ) { global $UP; return array( 'basedir' => $UP, 'baseurl' => 'https://example.org/wp-content/uploads' ); }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0755, true ); }
function wp_tempnam( $p ) { return tempnam( sys_get_temp_dir(), $p ); }
function wp_remote_get( $url, $args ) { $GLOBALS['fetched'][] = $url; $GLOBALS['last_args'] = $args; return array_shift( $GLOBALS['answers'] ) ?? new WP_Error( 'x', 'no answer' ); }
function wp_remote_retrieve_response_code( $r ) { return $r['code'] ?? 0; }
function wp_remote_retrieve_body( $r ) { return $r['body'] ?? ''; }
// A minimal image editor over GD, like WP_Image_Editor_GD: load, resize, save as JPEG.
class Ed { private $im; function __construct( $f ) { $this->im = @imagecreatefromstring( file_get_contents( $f ) ); } function ok() { return (bool) $this->im; }
  function resize( $w, $h, $c ) { $sw = imagesx( $this->im ); $sh = imagesy( $this->im ); if ( $sw <= $w && $sh <= $h ) { return true; } $r = min( $w / $sw, $h / $sh ); $n = imagecreatetruecolor( (int) ( $sw * $r ), (int) ( $sh * $r ) ); imagecopyresampled( $n, $this->im, 0, 0, 0, 0, (int) ( $sw * $r ), (int) ( $sh * $r ), $sw, $sh ); $this->im = $n; return true; }
  function set_quality( $q ) {} function save( $f, $m ) { return imagejpeg( $this->im, $f, 85 ) ? array( 'path' => $f ) : new WP_Error( 'save', '' ); } }
function wp_get_image_editor( $f ) { $e = new Ed( $f ); return $e->ok() ? $e : new WP_Error( 'image_no_editor', '' ); }
require __DIR__ . '/../includes/class-ds-roster-data.php';
require __DIR__ . '/../includes/class-ds-roster-photos.php';

$fails = 0;
function chk( $l, $g, $w ) { global $fails; $ok = ( $g === $w ); if ( ! $ok ) { $fails++; } printf( "  %-74s %s\n", $l, $ok ? 'ok' : 'FAIL got=' . var_export( $g, true ) ); }
$png = function ( $w, $h ) { $im = imagecreatetruecolor( $w, $h ); imagefill( $im, 0, 0, imagecolorallocate( $im, 20, 120, 60 ) ); ob_start(); imagepng( $im ); return ob_get_clean(); };
$S3 = 'https://svite-league-apps-img.s3.amazonaws.com/';

echo "photo address\n";
chk( 'LeagueApps photo accepted, ?type= dropped', DS_Roster_Data::photo_url( $S3 . 'profile-abc123def-s3.jpg?type=normal' ), $S3 . 'profile-abc123def-s3.jpg' );
chk( 'another host refused', DS_Roster_Data::photo_url( 'https://evil.example.com/profile-abc123def-s3.jpg' ), '' );
chk( 'plain http refused', DS_Roster_Data::photo_url( 'http://svite-league-apps-img.s3.amazonaws.com/profile-abc123def-s3.jpg' ), '' );
chk( 'another file on the same storage refused', DS_Roster_Data::photo_url( $S3 . 'logo-123.png' ), '' );
chk( 'path tricks refused', DS_Roster_Data::photo_url( $S3 . 'profile-abc/../../x-s3.jpg' ), '' );
chk( 'a look-alike host refused', DS_Roster_Data::photo_url( 'https://svite-league-apps-img.s3.amazonaws.com.evil.io/profile-abc123def-s3.jpg' ), '' );
$row = array( 'teamId' => 7, 'role' => 'PLAYER', 'registrationStatus' => 'SPOT_RESERVED', 'firstName' => 'Ana', 'lastName' => 'Ruiz', 'photo' => $S3 . 'profile-p1aaaa-s3.jpg?type=normal' );
chk( 'the roster row keeps the photo ADDRESS only', DS_Roster_Data::public_row( $row )['ph'], $S3 . 'profile-p1aaaa-s3.jpg' );

// A synced store with one team of three (two with photos) and another team nobody shows.
$GLOBALS['opts']['ds_la_private_keys'] = array( '33682' => array( 'client_id' => 'c', 'key' => 'x', 'print' => 'p' ) );
$m = function ( $f, $ph, $r = 'player' ) use ( $S3 ) { return array( 'f' => $f, 'l' => 'X', 'r' => $r, 'pos' => '', 'gy' => '', 'ht' => '', 'cap' => false, 'ph' => $ph ? $S3 . 'profile-' . $ph . '-s3.jpg' : '' ); };
$GLOBALS['opts']['ds_la_roster_33682'] = array( 'teams' => array(
	'7' => array( 'name' => '15 Gold', 'program' => 'P', 'm' => array( 1 => $m( 'Ana', 'p1aaaa' ), 2 => $m( 'Bea', '' ), 3 => $m( 'Coach Cal', 'c3cccc', 'coach' ) ) ),
	'8' => array( 'name' => '16 Gold', 'program' => 'P', 'm' => array( 4 => $m( 'Dee', 'p4dddd' ) ) ),
) );

echo "only the team a block shows\n";
$GLOBALS['answers'] = array( array( 'code' => 200, 'body' => $png( 150, 150 ) ), array( 'code' => 200, 'body' => $png( 120, 150 ) ) );
DS_Roster_Photos::want( '33682', '7', true );
chk( 'picked in the builder: this team\'s 2 photos fetched at once, nothing else', $GLOBALS['fetched'], array( $S3 . 'profile-p1aaaa-s3.jpg', $S3 . 'profile-c3cccc-s3.jpg' ) );
chk( 'requests: TLS verified, no redirects, size-capped', array( $GLOBALS['last_args']['sslverify'], $GLOBALS['last_args']['redirection'], $GLOBALS['last_args']['limit_response_size'] ), array( true, 0, 300000 ) );
$loc = DS_Roster_Photos::local( '33682', array( $S3 . 'profile-p1aaaa-s3.jpg', $S3 . 'profile-p4dddd-s3.jpg' ) );
chk( 'local URLs for the downloaded ones only', array_keys( $loc ), array( $S3 . 'profile-p1aaaa-s3.jpg' ) );
$file = $UP . '/ds-la-roster/33682/' . basename( reset( $loc ) );
$i = getimagesize( $file );
chk( 're-encoded as a JPEG in the site\'s own folder, 150 px', array( $i['mime'], $i[0] ), array( 'image/jpeg', 150 ) );
chk( 'the folder has an index.php (no listing)', file_exists( $UP . '/ds-la-roster/33682/index.php' ), true );
$GLOBALS['fetched'] = array(); $GLOBALS['cron'] = array();
DS_Roster_Photos::want( '33682', '7', false );
chk( 'a visitor view with everything present: no request, no job', array( count( $GLOBALS['fetched'] ), count( $GLOBALS['cron'] ) ), array( 0, 0 ) );

echo "failures\n";
$GLOBALS['opts']['ds_la_roster_33682']['teams']['7']['m'][5] = $m( 'Eve', 'p5eeee' );
$GLOBALS['opts']['ds_la_roster_33682']['teams']['7']['m'][6] = $m( 'Fay', 'p6ffff' );
$GLOBALS['opts']['ds_la_roster_33682']['teams']['7']['m'][7] = $m( 'Gil', 'p7gggg' );
$GLOBALS['answers'] = array( new WP_Error( 'http_request_failed', 'timed out' ), array( 'code' => 404, 'body' => '' ), array( 'code' => 200, 'body' => '<?php echo "not an image";' ) );
$GLOBALS['fetched'] = array(); $GLOBALS['cron'] = array();
DS_Roster_Photos::want( '33682', '7', false );
chk( 'a visitor view with photos missing: no request, the background job is queued', array( count( $GLOBALS['fetched'] ), $GLOBALS['cron'][0][1] ?? '' ), array( 0, 'ds_la_roster_photos' ) );
DS_Roster_Photos::run();
$mf = DS_Roster_Photos::manifest( '33682' );
$h  = function ( $id ) use ( $S3 ) { return DS_Roster_Photos::hash( $S3 . 'profile-' . $id . '-s3.jpg' ); };
chk( 'timeout: not recorded, asked again next run', isset( $mf['files'][ $h( 'p5eeee' ) ] ), false );
chk( '404 and a non-image: recorded as unavailable, not asked again', array( $mf['files'][ $h( 'p6ffff' ) ]['f'] ?? 'x', $mf['files'][ $h( 'p7gggg' ) ]['f'] ?? 'x' ), array( '', '' ) );
chk( 'nothing but images ever written to the folder', array_values( array_filter( scandir( $UP . '/ds-la-roster/33682' ), function ( $f ) { return ! preg_match( '/^(\.|\.\.|index\.php|[0-9a-f]{32}\.jpg)$/', $f ); } ) ), array() );
$GLOBALS['answers'] = array( array( 'code' => 200, 'body' => $png( 2000, 2000 ) ) ); $GLOBALS['fetched'] = array();
DS_Roster_Photos::run();
chk( 'an oversized image is refused', DS_Roster_Photos::manifest( '33682' )['files'][ $h( 'p5eeee' ) ]['f'] ?? 'x', '' );

echo "stale object cache (WP Engine memcached per container)\n";
$before = count( $GLOBALS['opts']['ds_la_roster_ph_33682']['files'] );
$GLOBALS['opts']['ds_la_roster_ph_33682']['teams']['7']['seen'] = time() - 2 * DAY_IN_SECONDS;
$GLOBALS['oc']['ds_la_roster_ph_33682'] = array( 'teams' => array(), 'files' => array() ); // what a stale container sees
$GLOBALS['fetched'] = array(); $GLOBALS['answers'] = array();
DS_Roster_Photos::want( '33682', '7', false );
chk( 'a page view on a container with a stale copy never wipes the photo list', array( count( $GLOBALS['opts']['ds_la_roster_ph_33682']['files'] ), $GLOBALS['opts']['ds_la_roster_ph_33682']['teams']['7']['seen'] >= time() - 5 ), array( $before, true ) );
unset( $GLOBALS['oc']['ds_la_roster_ph_33682'] );

echo "clean-up\n";
unset( $GLOBALS['opts']['ds_la_roster_33682']['teams']['7']['m'][1] ); // Ana left the team
DS_Roster_Photos::run();
chk( 'a player who left: photo file and entry deleted', array( file_exists( $file ), isset( DS_Roster_Photos::manifest( '33682' )['files'][ $h( 'p1aaaa' ) ] ) ), array( false, false ) );
$mf = DS_Roster_Photos::manifest( '33682' ); $mf['teams']['7']['seen'] = time() - 31 * DAY_IN_SECONDS; update_option( 'ds_la_roster_ph_33682', $mf );
DS_Roster_Photos::run();
chk( 'a team no block has shown for a month is forgotten, its photos deleted', array( DS_Roster_Photos::manifest( '33682' )['teams'], count( glob( $UP . '/ds-la-roster/33682/*.jpg' ) ) ), array( array(), 0 ) );
$GLOBALS['answers'] = array( array( 'code' => 200, 'body' => $png( 150, 150 ) ) );
DS_Roster_Photos::after_save( 1, true, array( (object) array( 'settings' => (object) array( 'type' => 'ds-la-roster', 'show_photos' => 'yes', 'team' => '33682:8' ) ), (object) array( 'settings' => (object) array( 'type' => 'ds-la-roster', 'show_photos' => 'no', 'team' => '33682:7' ) ) ) );
chk( 'saving a page: only blocks with photos on want their team', array_keys( DS_Roster_Photos::manifest( '33682' )['teams'] ), array( 8 ) );
DS_Roster_Data::delete_key( '33682' );
chk( 'removing the key deletes the folder and the list', array( is_dir( $UP . '/ds-la-roster/33682' ), get_option( 'ds_la_roster_ph_33682', 'gone' ) ), array( false, 'gone' ) );

@rmdir( $UP . '/ds-la-roster' ); @rmdir( $UP );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
