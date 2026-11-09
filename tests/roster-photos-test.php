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
function add_option( $k, $v, $d = '', $a = null ) { if ( array_key_exists( $k, $GLOBALS['opts'] ) ) { return false; } $GLOBALS['opts'][ $k ] = $v; return true; }
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
function get_the_ID() { return 0; }
function wp_upload_dir( $a = null, $b = true ) { global $UP; return array( 'basedir' => $UP, 'baseurl' => 'https://example.org/wp-content/uploads' ); }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0755, true ); }
function get_temp_dir() { return sys_get_temp_dir() . '/'; } // wp-includes/functions.php (loaded in WP-cron). No wp_tempnam stub on purpose: it is not loaded in WP-cron.
function wp_remote_get( $url, $args ) { if ( isset( $GLOBALS['on_get'] ) ) { ( $GLOBALS['on_get'] )(); } $GLOBALS['fetched'][] = $url; $GLOBALS['last_args'] = $args; return array_shift( $GLOBALS['answers'] ) ?? new WP_Error( 'x', 'no answer' ); }
function wp_remote_retrieve_response_code( $r ) { return $r['code'] ?? 0; }
function wp_remote_retrieve_body( $r ) { return $r['body'] ?? ''; }
// A minimal image editor over GD, like WP_Image_Editor_GD: load, resize, save as JPEG.
class Ed { private $im; function __construct( $f ) { $this->im = @imagecreatefromstring( file_get_contents( $f ) ); } function ok() { return (bool) $this->im; }
  function resize( $w, $h, $c ) { $sw = imagesx( $this->im ); $sh = imagesy( $this->im ); if ( $sw <= $w && $sh <= $h ) { return true; } $r = min( $w / $sw, $h / $sh ); $n = imagecreatetruecolor( (int) ( $sw * $r ), (int) ( $sh * $r ) ); imagecopyresampled( $n, $this->im, 0, 0, 0, 0, (int) ( $sw * $r ), (int) ( $sh * $r ), $sw, $sh ); $this->im = $n; return true; }
  function set_quality( $q ) {} function save( $f, $m ) { return imagejpeg( $this->im, $f, 85 ) ? array( 'path' => $f ) : new WP_Error( 'save', '' ); } }
function add_filter( $h, $cb ) { if ( 'wp_image_editors' === $h ) { $GLOBALS['editors_seen'][] = $cb( array( 'WP_Image_Editor_Imagick', 'WP_Image_Editor_GD' ) ); } return true; }
function remove_filter( $h, $cb ) { return true; }
function wp_get_image_editor( $f ) { $e = new Ed( $f ); return $e->ok() ? $e : new WP_Error( 'image_no_editor', '' ); }
// A tiny options table for the atomic lock (INSERT IGNORE / compare-and-swap UPDATE / DELETE).
class FakeWpdb {
  public $options = 'wp_options'; public $rows = array();
  function prepare( $q, ...$a ) { return array( $q, $a ); }
  function query( $q ) { list( $sql, $a ) = $q;
    if ( 0 === strpos( $sql, 'INSERT IGNORE' ) ) { if ( isset( $this->rows[ $a[0] ] ) ) { return 0; } $this->rows[ $a[0] ] = $a[1]; return 1; }
    if ( 0 === strpos( $sql, 'UPDATE' ) ) { if ( ( $this->rows[ $a[1] ] ?? null ) === $a[2] ) { $this->rows[ $a[1] ] = $a[0]; return 1; } return 0; }
    if ( 0 === strpos( $sql, 'DELETE' ) ) { unset( $this->rows[ $a[0] ] ); return 1; } return 0; }
  function get_var( $q ) { return $this->rows[ $q[1][0] ] ?? null; }
}
$wpdb = new FakeWpdb();
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
chk( 'the folder has an index.html (no listing), never a PHP file in uploads', array( file_exists( $UP . '/ds-la-roster/33682/index.html' ), file_exists( $UP . '/ds-la-roster/33682/index.php' ) ), array( true, false ) );
chk( 'photos are re-encoded with GD first (Imagick keeps EXIF on a 150 px image it does not resize)', $GLOBALS['editors_seen'][0][0] ?? '', 'WP_Image_Editor_GD' );
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
chk( 'nothing but images ever written to the folder', array_values( array_filter( scandir( $UP . '/ds-la-roster/33682' ), function ( $f ) { return ! preg_match( '/^(\.|\.\.|index\.html|[0-9a-f]{8}-[0-9a-f]{4}-8[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.jpg)$/', $f ); } ) ), array() );
$GLOBALS['answers'] = array( array( 'code' => 200, 'body' => $png( 2000, 2000 ) ) ); $GLOBALS['fetched'] = array();
DS_Roster_Photos::run();
chk( 'an oversized image is refused', DS_Roster_Photos::manifest( '33682' )['files'][ $h( 'p5eeee' ) ]['f'] ?? 'x', '' );

echo "never downloaded twice\n";
$GLOBALS['fetched'] = array(); $GLOBALS['answers'] = array();
DS_Roster_Photos::after_save( 2, true, array( (object) array( 'settings' => (object) array( 'type' => 'ds-la-roster', 'show_photos' => 'yes', 'team' => '33682:7' ) ) ) );
DS_Roster_Photos::want( '33682', '7', true );
chk( 'the same team put on another page: no request', count( $GLOBALS['fetched'] ), 0 );
$GLOBALS['opts']['ds_la_roster_33682']['teams']['9'] = array( 'name' => '15 Gold Beach', 'program' => 'P', 'm' => array( 11 => $m( 'Ana', 'p1aaaa' ), 12 => $m( 'Coach Cal', 'c3cccc', 'coach' ) ) );
DS_Roster_Photos::want( '33682', '9', true );
chk( 'a team whose people are already on a shown team: no request', count( $GLOBALS['fetched'] ), 0 );
$keep = $GLOBALS['opts']['ds_la_roster_ph_33682'];
$GLOBALS['opts']['ds_la_roster_ph_33682'] = array( 'teams' => array( '7' => array( 'seen' => time() ) ), 'files' => array() ); // the list lost
$GLOBALS['fetched'] = array(); $GLOBALS['answers'] = array();
DS_Roster_Photos::want( '33682', '7', true );
$mf = DS_Roster_Photos::manifest( '33682' );
$asked_again = array_intersect( $GLOBALS['fetched'], array( $S3 . 'profile-p1aaaa-s3.jpg', $S3 . 'profile-c3cccc-s3.jpg' ) );
chk( 'list lost but the files are in the folder: taken back, not requested', array( count( $asked_again ), $mf['files'][ $h( 'p1aaaa' ) ]['f'] ?? '', $mf['files'][ $h( 'c3cccc' ) ]['f'] ?? '' ), array( 0, $h( 'p1aaaa' ) . '.jpg', $h( 'c3cccc' ) . '.jpg' ) );
$GLOBALS['opts']['ds_la_roster_ph_33682'] = $keep;
$GLOBALS['opts']['ds_la_roster_33682']['teams']['7']['m'][8] = $m( 'Hal', 'p8hhhh' );
$wpdb->rows['ds_la_roster_ph_lock_33682'] = (string) time(); // another request is fetching
$GLOBALS['answers'] = array( array( 'code' => 200, 'body' => $png( 150, 150 ) ) ); $GLOBALS['fetched'] = array();
DS_Roster_Photos::want( '33682', '7', true );
chk( 'another request already fetching this site: no second download of the same photo', count( $GLOBALS['fetched'] ), 0 );
unset( $wpdb->rows['ds_la_roster_ph_lock_33682'] );
DS_Roster_Photos::run();
chk( '... the photo arrives once the lock is free, lock released after', array( in_array( $S3 . 'profile-p8hhhh-s3.jpg', $GLOBALS['fetched'], true ), count( array_keys( $GLOBALS['fetched'], $S3 . 'profile-p8hhhh-s3.jpg', true ) ), isset( $wpdb->rows['ds_la_roster_ph_lock_33682'] ) ), array( true, 1, false ) );
$GLOBALS['fetched'] = array();
DS_Roster_Photos::run();
chk( '... and is never fetched again on later runs', in_array( $S3 . 'profile-p8hhhh-s3.jpg', $GLOBALS['fetched'], true ), false );
unset( $GLOBALS['opts']['ds_la_roster_33682']['teams']['9'] );
$mf = DS_Roster_Photos::manifest( '33682' ); unset( $mf['teams']['9'] ); update_option( 'ds_la_roster_ph_33682', $mf );

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
$mf = DS_Roster_Photos::manifest( '33682' ); $mf['teams']['7']['seen'] = time() - 60 * DAY_IN_SECONDS; update_option( 'ds_la_roster_ph_33682', $mf );
$files_before = count( glob( $UP . '/ds-la-roster/33682/*.jpg' ) );
DS_Roster_Photos::run();
chk( 'a team taken off its page 2 months ago keeps its photos (putting it back costs nothing)', array( array_keys( DS_Roster_Photos::manifest( '33682' )['teams'] ), count( glob( $UP . '/ds-la-roster/33682/*.jpg' ) ) ), array( array( 7 ), $files_before ) );
$mf = DS_Roster_Photos::manifest( '33682' ); $mf['teams']['7']['seen'] = time() - 366 * DAY_IN_SECONDS; update_option( 'ds_la_roster_ph_33682', $mf );
DS_Roster_Photos::run();
chk( 'a team no block has shown for a year is forgotten, its photos deleted', array( DS_Roster_Photos::manifest( '33682' )['teams'], count( glob( $UP . '/ds-la-roster/33682/*.jpg' ) ) ), array( array(), 0 ) );
$GLOBALS['answers'] = array( array( 'code' => 200, 'body' => $png( 150, 150 ) ) );
DS_Roster_Photos::after_save( 1, true, array( (object) array( 'settings' => (object) array( 'type' => 'ds-la-roster', 'show_photos' => 'yes', 'team' => '33682:8' ) ), (object) array( 'settings' => (object) array( 'type' => 'ds-la-roster', 'show_photos' => 'no', 'team' => '33682:7' ) ) ) );
chk( 'saving a page: only blocks with photos on want their team', array_keys( DS_Roster_Photos::manifest( '33682' )['teams'] ), array( 8 ) );
echo "audit fixes\n";
$GLOBALS['opts']['ds_la_roster_33682']['teams']['7']['m'] = array( 1 => $m( 'Ana', 'p1aaaa' ), 3 => $m( 'Coach Cal', 'c3cccc', 'coach' ) );
$blk = function ( $team, $photos ) { return (object) array( 'settings' => (object) array( 'type' => 'ds-la-roster', 'show_photos' => $photos, 'team' => '33682:' . $team ) ); };
$GLOBALS['answers'] = array_fill( 0, 4, array( 'code' => 200, 'body' => $png( 150, 150 ) ) ); // team 8 (still shown elsewhere) is re-checked too
DS_Roster_Photos::after_save( 101, true, array( $blk( 7, 'yes' ) ) );
DS_Roster_Photos::after_save( 102, true, array( $blk( 7, 'yes' ) ) );
DS_Roster_Photos::run();
// Team 7's own two photos on disk (team 8 is still shown by another page from an earlier check, and keeps its photo).
$jpgs = function () use ( $UP, $h ) { return count( array_filter( array( $h( 'p1aaaa' ), $h( 'c3cccc' ) ), function ( $x ) use ( $UP ) { return is_file( $UP . '/ds-la-roster/33682/' . $x . '.jpg' ); } ) ); };
chk( 'setup: two pages show team 7 with photos, its 2 photos are here', array( array_keys( DS_Roster_Photos::manifest( '33682' )['teams'][7]['by'] ?? array() ), $jpgs() ), array( array( 101, 102 ), 2 ) );
DS_Roster_Photos::after_save( 101, false, array( $blk( 7, 'no' ) ) );
chk( 'a DRAFT save with photos off changes nothing (the published page still shows them)', array_keys( DS_Roster_Photos::manifest( '33682' )['teams'][7]['by'] ), array( 101, 102 ) );
DS_Roster_Photos::after_save( 101, true, array( $blk( 7, 'no' ) ) );
chk( 'page 101 published with photos off: still shown by page 102, photos kept', array( array_keys( DS_Roster_Photos::manifest( '33682' )['teams'][7]['by'] ), $jpgs() ), array( array( 102 ), 2 ) );
DS_Roster_Photos::after_save( 102, true, array() ); // the block removed from page 102
chk( '... page 102 published without the block: no page shows them, photos deleted at once', array( isset( DS_Roster_Photos::manifest( '33682' )['teams'][7] ), $jpgs() ), array( false, 0 ) );
$GLOBALS['answers'] = array( array( 'code' => 200, 'body' => $png( 150, 150 ) ), array( 'code' => 200, 'body' => $png( 150, 150 ) ) );
DS_Roster_Photos::after_save( 103, true, array( $blk( 7, 'yes' ) ) ); DS_Roster_Photos::run();
$keep_store = $GLOBALS['opts']['ds_la_roster_33682'];
$GLOBALS['opts']['ds_la_roster_33682']['partial'] = array( 'teams' => array() );
unset( $GLOBALS['opts']['ds_la_roster_33682']['teams']['7']['m'][1] );
DS_Roster_Photos::gc( '33682' );
chk( 'while a roster rebuild is running, clean-up deletes nothing', $jpgs(), 2 );
$GLOBALS['opts']['ds_la_roster_33682'] = $keep_store;
$GLOBALS['cron'] = array(); DS_Roster_Photos::queue();
chk( 'after the hourly sync, photos are queued as their own job a minute later', array( $GLOBALS['cron'][0][1] ?? '', ( $GLOBALS['cron'][0][0] ?? 0 ) - time() ), array( 'ds_la_roster_photos', 60 ) );
$GLOBALS['opts']['ds_la_roster_33682']['teams']['7']['m'][9] = $m( 'Ivy', 'p9iiii' );
$mf = DS_Roster_Photos::manifest( '33682' ); $mf['files'][ $h( 'p9iiii' ) ] = array( 'f' => '', 't' => time() - 31 * DAY_IN_SECONDS ); update_option( 'ds_la_roster_ph_33682', $mf );
$GLOBALS['fetched'] = array(); $GLOBALS['answers'] = array( array( 'code' => 200, 'body' => $png( 150, 150 ) ) );
DS_Roster_Photos::run();
chk( 'a photo LeagueApps did not have a month ago is asked for again (and arrives)', array( in_array( $S3 . 'profile-p9iiii-s3.jpg', $GLOBALS['fetched'], true ), '' !== ( DS_Roster_Photos::manifest( '33682' )['files'][ $h( 'p9iiii' ) ]['f'] ?? '' ) ), array( true, true ) );
$GLOBALS['opts']['ds_la_roster_33682']['teams']['7']['m'][10] = $m( 'Jo', 'p10jjjj' );
$GLOBALS['opts']['ds_la_roster_33682']['teams']['7']['m'][11] = $m( 'Kai', 'p11kkkk' );
$GLOBALS['fetched'] = array(); $GLOBALS['answers'] = array( array( 'code' => 200, 'body' => $png( 150, 150 ) ), array( 'code' => 200, 'body' => $png( 150, 150 ) ) );
$GLOBALS['on_get'] = function () { unset( $GLOBALS['opts']['ds_la_private_keys']['33682'] ); unset( $GLOBALS['on_get'] ); };
DS_Roster_Photos::run();
chk( 'the key removed while photos download: no further download', count( $GLOBALS['fetched'] ), 1 );
$GLOBALS['opts']['ds_la_private_keys']['33682'] = array( 'client_id' => 'c', 'key' => 'x', 'print' => 'p' );

// LeagueApps photo addresses carry the person's sequential numeric user id (8 digits on HQ, 2026-11-09), so a
// public file name must never be derivable from it (VP Engineering: no enumerable user ids, UUIDs instead).
echo "file names: opaque UUIDs, never a LeagueApps user id\n";
$uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-8[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';
$addr = $S3 . 'profile-12345678-s3.jpg';
$n1   = DS_Roster_Photos::hash( $addr );
chk( 'a photo file name is a UUID (RFC 9562 version 8)', (bool) preg_match( $uuid, $n1 ), true );
chk( 'the same address always gets the same name (never downloaded twice)', DS_Roster_Photos::hash( $addr ), $n1 );
chk( 'the name holds neither the user id nor the plain hash anyone can recompute', array( false !== strpos( str_replace( '-', '', $n1 ), '12345678' ), str_replace( '-', '', $n1 ) === DS_Roster_Photos::legacy_hash( $addr ) ), array( false, false ) );
chk( 'the secret is made once per install and saved (64 hex chars)', (bool) preg_match( '/^[0-9a-f]{64}$/', (string) get_option( 'ds_la_roster_photo_key', '' ) ), true );
$sec = $GLOBALS['opts']['ds_la_roster_photo_key'];
unset( $GLOBALS['opts']['ds_la_roster_photo_key'] ); DS_Roster_Photos::reset_name_key();
$n2 = DS_Roster_Photos::hash( $addr );
chk( 'another install names the same photo differently (no match across sites)', $n1 !== $n2 && 1 === preg_match( $uuid, $n2 ), true );
$GLOBALS['opts']['ds_la_roster_photo_key'] = 'damaged'; DS_Roster_Photos::reset_name_key();
DS_Roster_Photos::hash( $addr );
chk( 'a damaged secret is replaced', (bool) preg_match( '/^[0-9a-f]{64}$/', $GLOBALS['opts']['ds_la_roster_photo_key'] ), true );
$GLOBALS['opts']['ds_la_roster_photo_key'] = $sec; DS_Roster_Photos::reset_name_key();
chk( 'with the saved secret back, the name is the first one again', DS_Roster_Photos::hash( $addr ), $n1 );

echo "1.17.2 files moved to their new names, without a request\n";
$dirp = $UP . '/ds-la-roster/33682';
wp_mkdir_p( $dirp );
$a12  = $S3 . 'profile-p12llll-s3.jpg'; $a13 = $S3 . 'profile-p13mmmm-s3.jpg';
$o12  = DS_Roster_Photos::legacy_hash( $a12 ); $o13 = DS_Roster_Photos::legacy_hash( $a13 );
$GLOBALS['opts']['ds_la_roster_33682']['teams']['12'] = array( 'name' => '17 Gold', 'program' => 'P', 'm' => array( 21 => $m( 'Lou', 'p12llll' ), 22 => $m( 'Mo', 'p13mmmm' ) ) );
imagejpeg( imagecreatetruecolor( 150, 150 ), $dirp . '/' . $o12 . '.jpg' );
$gone_t = time() - 5 * DAY_IN_SECONDS;
$mf = DS_Roster_Photos::manifest( '33682' );
$mf['teams']['12']     = array( 'seen' => time(), 'by' => array( 104 => time() ) );
$mf['files'][ $o12 ]   = array( 'f' => $o12 . '.jpg', 't' => time() - 9 * DAY_IN_SECONDS );
$mf['files'][ $o13 ]   = array( 'f' => '', 't' => $gone_t );
update_option( 'ds_la_roster_ph_33682', $mf );
$loc = DS_Roster_Photos::local( '33682', array( $a12 ) );
chk( 'right after the update the page still shows the photo (old name until moved)', basename( $loc[ $a12 ] ?? '' ), $o12 . '.jpg' );
DS_Roster_Photos::gc( '33682' );
chk( 'clean-up before the move never deletes a 1.17.2 photo still in use', array( is_file( $dirp . '/' . $o12 . '.jpg' ), isset( DS_Roster_Photos::manifest( '33682' )['files'][ $o13 ] ) ), array( true, true ) );
$GLOBALS['fetched'] = array(); $GLOBALS['answers'] = array();
DS_Roster_Photos::run();
$mf  = DS_Roster_Photos::manifest( '33682' ); $n12 = DS_Roster_Photos::hash( $a12 ); $n13 = DS_Roster_Photos::hash( $a13 );
chk( 'no request to LeagueApps for either', array_values( array_intersect( $GLOBALS['fetched'], array( $a12, $a13 ) ) ), array() );
chk( 'the photo is under its UUID name, the page now links that', array( is_file( $dirp . '/' . $n12 . '.jpg' ), $mf['files'][ $n12 ]['f'] ?? '', basename( DS_Roster_Photos::local( '33682', array( $a12 ) )[ $a12 ] ?? '' ) ), array( true, $n12 . '.jpg', $n12 . '.jpg' ) );
chk( 'a photo LeagueApps did not have keeps that answer and its date', array( $mf['files'][ $n13 ] ?? null, isset( $mf['files'][ $o13 ] ) ), array( array( 'f' => '', 't' => $gone_t ), false ) );
chk( 'the old file stays two days for cached pages', is_file( $dirp . '/' . $o12 . '.jpg' ), true );
$mf['files'][ $o12 ]['old'] = time() - 3 * DAY_IN_SECONDS; update_option( 'ds_la_roster_ph_33682', $mf );
DS_Roster_Photos::gc( '33682' );
chk( '... then the old file and its entry go, the new one stays', array( is_file( $dirp . '/' . $o12 . '.jpg' ), isset( DS_Roster_Photos::manifest( '33682' )['files'][ $o12 ] ), is_file( $dirp . '/' . $n12 . '.jpg' ) ), array( false, false, true ) );

DS_Roster_Data::delete_key( '33682' );
chk( 'removing the key deletes the folder and the list', array( is_dir( $UP . '/ds-la-roster/33682' ), get_option( 'ds_la_roster_ph_33682', 'gone' ) ), array( false, 'gone' ) );

@rmdir( $UP . '/ds-la-roster' ); @rmdir( $UP );
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
