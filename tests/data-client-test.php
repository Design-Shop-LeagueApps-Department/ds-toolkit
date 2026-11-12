<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a php script: does nothing over HTTP
/**
 * DS_Data_Client (one GET for JSON, one last-good store) and DS_Lock as the only lock. Stub-style:
 *
 *   php tests/data-client-test.php
 *
 * Why (2026-11 audit, item 27): the data classes carried six GET wrappers that disagreed on what to retry,
 * and four hand-written copies of the same options-row lock (HQ Link, HQ Update, Roster, Calendar).
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'DS_TOOLKIT_VERSION', '9.9.9' );
$GLOBALS['http'] = array(); $GLOBALS['calls'] = array(); $GLOBALS['opts'] = array(); $GLOBALS['autoload'] = array();
class WP_Error {
	private $m;
	function __construct( $c = '', $m = '' ) { $this->m = $m; }
	function get_error_message() { return $this->m; }
}
function is_wp_error( $e ) { return $e instanceof WP_Error; }
function wp_remote_get( $url, $args = array() ) { $GLOBALS['calls'][] = $args; $q = &$GLOBALS['http']; return count( $q ) > 1 ? array_shift( $q ) : $q[0]; }
function wp_remote_retrieve_response_code( $r ) { return $r['code'] ?? 0; }
function wp_remote_retrieve_body( $r ) { return $r['body'] ?? ''; }
function wp_remote_retrieve_header( $r, $h ) { return $r['headers'][ strtolower( $h ) ] ?? ''; }
function home_url( $p = '' ) { return 'https://example.org' . $p; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['autoload'][ $k ] = $autoload; return true; }

require dirname( __DIR__ ) . '/includes/class-ds-data-client.php';

$fails = 0;
function chk( $l, $g, $w ) { global $fails; $ok = ( $g === $w ); if ( ! $ok ) { $fails++; } printf( "  %-80s %s\n", $l, $ok ? 'ok' : 'FAIL got=' . var_export( $g, true ) ); }
$ok   = array( 'code' => 200, 'body' => '{"a":1}', 'headers' => array() );
$down = new WP_Error( 'http_request_failed', 'cURL error 28: timed out' );
$get  = function ( array $script, array $args = array() ) { $GLOBALS['http'] = $script; $GLOBALS['calls'] = array(); return DS_Data_Client::get_json( 'https://api.example/x', array_merge( array( 'pause' => 0 ), $args ) ); };
$pick = function ( $r ) { return array( $r['ok'], $r['code'], $r['tries'], $r['kind'] ); };

echo "what is retried\n";
$r = $get( array( $ok ) );
chk( 'a good answer: one request', array( $pick( $r ), $r['json'] ), array( array( true, 200, 1, '' ), array( 'a' => 1 ) ) );
chk( 'it says who is calling and asks for JSON', array( $GLOBALS['calls'][0]['user-agent'], $GLOBALS['calls'][0]['headers']['Accept'], $GLOBALS['calls'][0]['timeout'] ), array( 'ds-toolkit-data/9.9.9 (+https://example.org/)', 'application/json', 8 ) );
chk( 'a dropped connection is tried again, then succeeds', $pick( $get( array( $down, $ok ) ) ), array( true, 200, 2, '' ) );
$r = $get( array( $down ) );
chk( 'three dropped connections: gives up, keeps the transport error', array( $pick( $r ), $r['error'], $r['wp_error'] instanceof WP_Error ), array( array( false, 0, 3, 'connection' ), 'cURL error 28: timed out', true ) );
chk( 'a body that is not JSON is tried again', $pick( $get( array( array( 'code' => 200, 'body' => '<html>', 'headers' => array() ), $ok ) ) ), array( true, 200, 2, '' ) );
chk( 'a 500 is final: the service answered', $pick( $get( array( array( 'code' => 500 ), $ok ) ) ), array( false, 500, 1, 'http' ) );
chk( 'a 403 is a key problem, not retried', $pick( $get( array( array( 'code' => 403 ), $ok ) ) ), array( false, 403, 1, 'key' ) );
chk( 'a 404 is "not found", not retried', $pick( $get( array( array( 'code' => 404 ), $ok ) ) ), array( false, 404, 1, 'missing' ) );
$r = $get( array( array( 'code' => 429, 'headers' => array( 'retry-after' => '120' ) ), $ok ) );
chk( 'a 429 stops at once and carries Retry-After', array( $pick( $r ), $r['retry_after'] ), array( array( false, 429, 1, 'throttled' ), 120 ) );
chk( 'attempts can be set', $pick( $get( array( $down ), array( 'attempts' => 1 ) ) ), array( false, 0, 1, 'connection' ) );

echo "what a caller can set, and what it gets back\n";
$r = $get( array( array( 'code' => 200, 'body' => '{"a":1}', 'headers' => array() ) ), array( 'user_agent' => 'ds-toolkit-roster/1', 'http' => array( 'sslverify' => true, 'redirection' => 0, 'timeout' => 99 ), 'headers' => array( 'Authorization' => 'Bearer x' ) ) );
chk( 'its own User-Agent, extra transport settings, extra headers', array( $GLOBALS['calls'][0]['user-agent'], $GLOBALS['calls'][0]['redirection'], $GLOBALS['calls'][0]['sslverify'], $GLOBALS['calls'][0]['headers'] ), array( 'ds-toolkit-roster/1', 0, true, array( 'Accept' => 'application/json', 'Authorization' => 'Bearer x' ) ) );
chk( 'a transport setting cannot override the timeout the client worked out', $GLOBALS['calls'][0]['timeout'], 8 );
chk( 'the body comes back as sent', $r['body'], '{"a":1}' );
$r = $get( array( array( 'code' => 503, 'body' => 'busy', 'headers' => array( 'retry-after' => '30' ) ) ) );
chk( 'a 503 that asks for a wait carries it, with the body', array( $r['code'], $r['retry_after'], $r['body'], $r['kind'] ), array( 503, 30, 'busy', 'http' ) );
$r = $get( array( $down ), array( 'attempts' => 1 ) );
chk( 'no answer: code 0 and an empty body', array( $r['code'], $r['body'] ), array( 0, '' ) );
chk( 'the Calendar\'s one-at-a-time requests go through the client', (bool) preg_match( "/DS_Data_Client::get_json\( \\\$u, array\( 'timeout' => min\( self::TIMEOUT, \\\$left \), 'attempts' => 1, 'user_agent' => \\\$ua \) \)/", file_get_contents( dirname( __DIR__ ) . '/includes/class-ds-calendar-data.php' ) ), true );

echo "deadline\n";
$r = $get( array( $ok ), array( 'deadline' => microtime( true ) - 1 ) );
chk( 'past the deadline: no request is made', array( $pick( $r ), count( $GLOBALS['calls'] ) ), array( array( false, 0, 0, 'deadline' ), 0 ) );
$get( array( $ok ), array( 'deadline' => microtime( true ) + 3.5, 'timeout' => 8 ) );
chk( 'the wait is cut to the time left', $GLOBALS['calls'][0]['timeout'], 3 );
$r = $get( array( $down ), array( 'deadline' => microtime( true ) + 1.2, 'pause' => 400000 ) );
chk( 'a retry that would start after the deadline is not made', array( $r['tries'], $r['kind'] ), array( 1, 'connection' ) );

echo "last good copy\n";
DS_Data_Client::keep( 'ds_x_good', array( 'rows' => array( 1, 2 ) ) );
chk( 'kept in its own option, not autoloaded, stamped with the time', array( $GLOBALS['autoload']['ds_x_good'], $GLOBALS['opts']['ds_x_good']['rows'], abs( time() - $GLOBALS['opts']['ds_x_good']['fetched'] ) < 3 ), array( false, array( 1, 2 ), true ) );
chk( 'read back', DS_Data_Client::kept( 'ds_x_good' )['rows'], array( 1, 2 ) );
$GLOBALS['opts']['ds_x_good']['fetched'] = time() - 1000;
chk( 'ignored once older than the caller allows', array( DS_Data_Client::kept( 'ds_x_good', 500 ), is_array( DS_Data_Client::kept( 'ds_x_good', 2000 ) ) ), array( null, true ) );
chk( 'nothing kept reads as null', DS_Data_Client::kept( 'ds_none' ), null );

echo "one lock\n";
$root = dirname( __DIR__ ) . '/';
$own  = array();
foreach ( array( 'features', 'includes', 'admin', 'modules' ) as $dir ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		$p = str_replace( '\\', '/', substr( (string) $f, strlen( $root ) ) );
		if ( 'php' !== pathinfo( $p, PATHINFO_EXTENSION ) || 'includes/class-ds-lock.php' === $p ) { continue; }
		$code = '';
		foreach ( token_get_all( file_get_contents( (string) $f ) ) as $t ) { if ( is_array( $t ) ) { if ( T_COMMENT !== $t[0] && T_DOC_COMMENT !== $t[0] ) { $code .= $t[1]; } } else { $code .= $t; } }
		if ( false !== stripos( $code, 'INSERT IGNORE INTO' ) ) { $own[] = $p; }
	}
}
chk( 'no class writes its own options-row lock', $own, array() );
foreach ( array( 'features/class-ds-hq-link.php', 'features/class-ds-hq-update.php', 'includes/class-ds-roster-data.php', 'includes/class-ds-calendar-data.php' ) as $f ) {
	$src = file_get_contents( $root . $f );
	chk( $f . ' takes and releases through DS_Lock', array( (bool) preg_match( '/DS_Lock::acquire\(/', $src ), (bool) preg_match( '/DS_Lock::release\(/', $src ), false !== strpos( $src, 'class-ds-lock.php' ) ), array( true, true, true ) );
}
chk( 'the Programs feed reads LeagueApps through the client', (bool) preg_match( '/function fetch_url\( \$url \) \{.*?DS_Data_Client::get_json\(/s', file_get_contents( $root . 'includes/class-ds-programs-data.php' ) ), true );

echo "\n" . ( $fails ? "$fails FAILED" : "all passed" ) . "\n";
exit( $fails ? 1 : 0 );
