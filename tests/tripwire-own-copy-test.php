<?php
// Run: php tests/tripwire-own-copy-test.php        (plain PHP 7.4+, no WordPress, no network)
//
// Guards DS_Tripwire::own_marker_md5() and its use in run_content_scan(): the REAL content scan runs
// over a throwaway web root and must
//   - IGNORE byte-identical copies of the engine and of class-ds-tripwire.php in a staging folder
//     (the 71 false CRITICALs of the 1.10.16 WP Engine push, 2026-09-28, plugins/.dstk-new.33/), and
//   - still FLAG a real web shell, a TAMPERED copy of the engine, and a non-marker toolkit file an
//     attacker modified. If either of the last two ever comes back clean, the exemption has become
//     "trust anything that looks like ours" and must not ship.
// Minimal WordPress stubs below; everything the scan decides is the shipped code.

error_reporting( E_ALL & ~E_DEPRECATED );
define( 'ABSPATH', sys_get_temp_dir() . '/' );
$ROOT = sys_get_temp_dir() . '/tw-own-copy-' . getmypid();
define( 'WP_CONTENT_DIR', $ROOT . '/wp-content' );
define( 'DS_TOOLKIT_PATH', dirname( __DIR__ ) . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['tw_opts'] = array();
$GLOBALS['tw_mail'] = array();
class WP_Error { public function get_error_message() { return 'offline test'; } }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function get_option( $k, $d = false ) { return isset( $GLOBALS['tw_opts'][ $k ] ) ? $GLOBALS['tw_opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['tw_opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['tw_opts'][ $k ] ); return true; }
function get_transient( $k ) { return false; }
function set_transient( $k, $v, $t = 0 ) { return true; }
function delete_transient( $k ) { return true; }
function apply_filters( $h, $v ) { return $v; }
function do_action() {}
function add_action() {}
function add_filter() {}
function untrailingslashit( $s ) { return rtrim( $s, '/\\' ); }
function trailingslashit( $s ) { return untrailingslashit( $s ) . '/'; }
function wp_get_upload_dir() { return array( 'basedir' => WP_CONTENT_DIR . '/uploads' ); }
function wp_upload_dir() { return wp_get_upload_dir(); }
function wp_remote_get() { return new WP_Error(); }
function wp_safe_remote_get() { return new WP_Error(); }
function wp_remote_retrieve_response_code() { return 0; }
function wp_remote_retrieve_body() { return ''; }
function wp_remote_retrieve_header() { return ''; }
function wp_mail( $to, $s, $b ) { $GLOBALS['tw_mail'][] = $s; return true; }
function get_bloginfo() { return 'test'; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function site_url( $p = '' ) { return home_url( $p ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function esc_html( $s ) { return $s; }
function __( $s ) { return $s; }
function current_time( $t ) { return time(); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function is_email( $e ) { return filter_var( $e, FILTER_VALIDATE_EMAIL ) ? $e : false; }
function sanitize_email( $e ) { return trim( (string) $e ); }
function sanitize_text_field( $s ) { return trim( (string) $s ); }
function wp_strip_all_tags( $s ) { return strip_tags( (string) $s ); }

require DS_TOOLKIT_PATH . 'features/class-ds-tripwire.php';

$ok = 0; $bad = 0;
$is = function ( $n, $a, $b ) use ( &$ok, &$bad ) {
	if ( $a === $b ) { $ok++; echo "  ok   $n\n"; }
	else { $bad++; echo "  FAIL $n: got " . var_export( $a, true ) . ' want ' . var_export( $b, true ) . "\n"; }
};

// ---- a throwaway web root --------------------------------------------------------------------------
$engine = file_get_contents( DS_TOOLKIT_PATH . 'includes/ds-scan-engine.php' );
$klass  = file_get_contents( DS_TOOLKIT_PATH . 'features/class-ds-tripwire.php' );
$stage  = WP_CONTENT_DIR . '/plugins/.dstk-new.33/ds-toolkit';
@mkdir( $stage . '/includes', 0777, true );
@mkdir( $stage . '/features', 0777, true );
@mkdir( WP_CONTENT_DIR . '/uploads', 0777, true );
@mkdir( WP_CONTENT_DIR . '/plugins/ds-toolkit-copy/features', 0777, true );
file_put_contents( $stage . '/includes/ds-scan-engine.php', $engine );                    // must be ignored
file_put_contents( $stage . '/features/class-ds-tripwire.php', $klass );                  // must be ignored
file_put_contents( WP_CONTENT_DIR . '/uploads/wp-cache.php', '<?php if(isset($_POST["c"])){ eval(base64_decode($_POST["c"])); }' ); // must be flagged
file_put_contents( WP_CONTENT_DIR . '/plugins/ds-toolkit-copy/tampered-engine.php', $engine . "\n@eval(\$_POST['z']);\n" );          // must be flagged
file_put_contents( WP_CONTENT_DIR . '/plugins/ds-toolkit-copy/features/class-ds-carousel.php', "<?php\n@eval(base64_decode(\$_REQUEST['q']));\n" ); // a modified NON-marker toolkit file: must be flagged
file_put_contents( WP_CONTENT_DIR . '/plugins/.dstk-new.33/ds-toolkit/empty.php', '' );  // 0 bytes never inherits the exemption

// ---- own_marker_md5() ------------------------------------------------------------------------------
$m = new ReflectionMethod( 'DS_Tripwire', 'own_marker_md5' );
$m->setAccessible( true );
$own = $m->invoke( null );
$is( 'own set holds the engine md5', isset( $own[ md5( $engine ) ] ), true );
$is( 'own set holds the class md5', isset( $own[ md5( $klass ) ] ), true );
$is( 'own set holds exactly those two', count( $own ), 2 );
$is( 'own set never holds the empty-file md5', isset( $own['d41d8cd98f00b204e9800998ecf8427e'] ), false );
$is( 'own set does NOT hold ds-toolkit.php (not a marker file)', isset( $own[ md5_file( DS_TOOLKIT_PATH . 'ds-toolkit.php' ) ] ), false );

// ---- the real scan -------------------------------------------------------------------------------
// web_root() is dirname( WP_CONTENT_DIR ), i.e. the throwaway tree
$tw  = new DS_Tripwire( array() );
$found = $tw->run_content_scan( 60 );
$paths = array_map( function ( $f ) { return str_replace( $GLOBALS['ROOT'], '', $f['path'] ); }, $found );
$has   = function ( $needle ) use ( $paths ) { foreach ( $paths as $p ) { if ( false !== strpos( $p, $needle ) ) { return true; } } return false; };
$state = get_option( 'ds_tripwire_state', array() );
$c     = isset( $state['content'] ) ? $state['content'] : array();
$is( 'scan ran without an engine error', isset( $c['error'] ) ? $c['error'] : '', '' );
$is( 'staged engine copy is NOT flagged', $has( '.dstk-new.33/ds-toolkit/includes/ds-scan-engine.php' ), false );
$is( 'staged class copy is NOT flagged', $has( '.dstk-new.33/ds-toolkit/features/class-ds-tripwire.php' ), false );
$is( 'own_copies counter = 2', isset( $c['own_copies'] ) ? (int) $c['own_copies'] : -1, 2 );
$is( 'real uploads shell IS flagged', $has( '/uploads/wp-cache.php' ), true );
$is( 'tampered engine copy IS flagged', $has( '/tampered-engine.php' ), true );
$is( 'modified non-marker toolkit file IS flagged', $has( '/ds-toolkit-copy/features/class-ds-carousel.php' ), true );
$is( 'a CRITICAL mail was still sent for the real shells', count( $GLOBALS['tw_mail'] ) > 0, true );

// cleanup
$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $ROOT, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
foreach ( $it as $f ) { $f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() ); }
rmdir( $ROOT );

echo "\n$ok passed, $bad failed\n";
exit( $bad ? 1 : 0 );
