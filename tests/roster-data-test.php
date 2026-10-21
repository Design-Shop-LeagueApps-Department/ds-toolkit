<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * LeagueApps Roster data (DS_Roster_Data): the private key is sealed and never stored in the
 * clear, only public fields of rostered players and coaches are ever kept, the RS256 login is
 * correct, paging + the cursor work, and an interrupted full sync never blanks a roster.
 * Stub-style, no WordPress needed:
 *
 *   php tests/roster-data-test.php
 */
define( 'ABSPATH', '/tmp/fake-wp/' );
define( 'MINUTE_IN_SECONDS', 60 ); define( 'HOUR_IN_SECONDS', 3600 ); define( 'DAY_IN_SECONDS', 86400 );
define( 'AUTH_KEY', 'test-auth-key-6Q#p8vZ!2mK@x9Lr$4tW&7yB^1cN*5hJ' ); define( 'SECURE_AUTH_KEY', 'test-secure-3Fg%8kP!0qR#6sV@2uX$9wY^4zA&1bD' ); define( 'LOGGED_IN_KEY', 'test-logged-in-7Hj*2nM!5pQ@8rT#1vW$4xZ%6yC&9e' );
define( 'DB_NAME', 'wp_testsite' ); $table_prefix = 'wp_';

// A tiny options table for the atomic lock (INSERT IGNORE / compare-and-swap UPDATE / DELETE).
class FakeWpdb {
  public $options = 'wp_options'; public $prefix = 'wp_'; public $rows = array();
  function prepare( $q, ...$a ) { return array( $q, $a ); }
  function query( $q ) { list( $sql, $a ) = $q;
    if ( 0 === strpos( $sql, 'INSERT IGNORE' ) ) { if ( isset( $this->rows[ $a[0] ] ) ) { return 0; } $this->rows[ $a[0] ] = $a[1]; return 1; }
    if ( 0 === strpos( $sql, 'UPDATE' ) ) { if ( ( $this->rows[ $a[1] ] ?? null ) === $a[2] ) { $this->rows[ $a[1] ] = $a[0]; return 1; } return 0; }
    if ( 0 === strpos( $sql, 'DELETE' ) ) { unset( $this->rows[ $a[0] ] ); return 1; } return 0; }
  function get_var( $q ) { return $this->rows[ $q[1][0] ] ?? null; }
}
$wpdb = new FakeWpdb();
$B = ( time() - 30 * 86400 ) * 1000; // registration timestamps in the tests are $B + n (recent, like real data)
$GLOBALS['opts'] = array(); $GLOBALS['tr'] = array(); $GLOBALS['http'] = array(); $GLOBALS['urls'] = array(); $GLOBALS['jwt'] = null;
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['opts'][ $k ] ); return true; }
function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['tr'] ) ? $GLOBALS['tr'][ $k ] : false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['tr'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['tr'][ $k ] ); return true; }
function __( $s, $d = '' ) { return $s; }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function get_current_user_id() { return 7; }
function is_wp_error( $e ) { return $e instanceof WP_Error; }
class WP_Error { private $c, $m; function __construct( $c = '', $m = '' ) { $this->c = $c; $this->m = $m; } function get_error_message() { return $this->m; } function get_error_code() { return $this->c; } }
$GLOBALS['cron'] = array();
function wp_next_scheduled( $h ) { foreach ( $GLOBALS['cron'] as $c ) { if ( $c[1] === $h ) { return $c[0]; } } return false; }
function wp_schedule_single_event( $t, $h ) { $GLOBALS['cron'][] = array( $t, $h ); return true; }
function wp_remote_post( $url, $args ) { $GLOBALS['post_args'] = $args; $GLOBALS['jwt'] = $args['body']['assertion'] ?? null; return $GLOBALS['auth'] ?? array( 'code' => 200, 'body' => json_encode( array( 'access_token' => 'tok-123', 'expires_in' => 900 ) ) ); }
function wp_remote_get( $url, $args ) { if ( isset( $GLOBALS['on_get'] ) ) { ( $GLOBALS['on_get'] )( $url ); } $GLOBALS['get_args'] = $args; $GLOBALS['urls'][] = $url; $GLOBALS['hdr'] = $args['headers'] ?? array(); return array_shift( $GLOBALS['http'] ) ?? array( 'code' => 200, 'body' => '[]' ); } // an entry may be a WP_Error (timeout)
function wp_remote_retrieve_response_code( $r ) { return $r['code'] ?? 0; }
function wp_remote_retrieve_body( $r ) { return $r['body'] ?? ''; }
require __DIR__ . '/../includes/class-ds-roster-data.php';

$fails = 0;
function chk( $l, $g, $w ) { global $fails; $ok = ( $g === $w ); if ( ! $ok ) { $fails++; } printf( "  %-74s %s\n", $l, $ok ? 'ok' : 'FAIL got=' . var_export( $g, true ) ); }

// A real RSA key, like the one inside LeagueApps' .p12.
$pk  = openssl_pkey_new( array( 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA ) );
openssl_pkey_export( $pk, $pem );
$pub = openssl_pkey_get_details( $pk )['key'];

echo "the key\n";
chk( 'a .pem upload reads', DS_Roster_Data::pem_from_upload( "junk\n" . $pem . "\n" ), trim( $pem ) );
chk( 'garbage is refused', is_wp_error( DS_Roster_Data::pem_from_upload( 'not a key' ) ), true );
$csr = openssl_csr_new( array( 'commonName' => 'la' ), $pk ); $crt = openssl_csr_sign( $csr, null, $pk, 1 );
if ( openssl_pkcs12_export( $crt, $p12, $pk, 'notasecret' ) ) {
	$got = DS_Roster_Data::pem_from_upload( $p12 );
	chk( 'a LeagueApps-style .p12 (password notasecret) reads, or explains how to convert', is_string( $got ) ? (bool) openssl_pkey_get_private( $got ) : ( false !== strpos( $got->get_error_message(), 'openssl pkcs12' ) ), true );
}
chk( 'save needs site, client and key', is_wp_error( DS_Roster_Data::save_key( '52078', '', $pem ) ), true );
chk( 'save works', DS_Roster_Data::save_key( '52078', 'abc123client', $pem ), true );
$raw = serialize( $GLOBALS['opts'] );
chk( 'the private key is never stored in the clear', false === strpos( $raw, 'PRIVATE KEY' ) && false === strpos( $raw, substr( preg_replace( '/-----[^-]+-----|\s/', '', $pem ), 40, 40 ) ), true );
$st = DS_Roster_Data::key_status( '52078' );
chk( 'status: client id, fingerprint, readable; never the key, sealed or not', array( $st['client_id'], strlen( $st['print'] ), $st['readable'], false === strpos( serialize( $st ), 'PRIVATE KEY' ) && false === strpos( serialize( $st ), 'v2.' ) ), array( 'abc123client', 12, true, true ) );
$k = $GLOBALS['opts']['ds_la_private_keys']; $k['52078']['key'] = base64_encode( str_repeat( 'x', 80 ) ); $GLOBALS['opts']['ds_la_private_keys'] = $k;
chk( 'a sealed key that no longer opens reads as unreadable (secrets changed)', DS_Roster_Data::key_status( '52078' )['readable'], false );
DS_Roster_Data::save_key( '52078', 'abc123client', $pem );

echo "only public fields\n";
$reg = function ( $over = array() ) use ( &$B ) {
	return array_merge( array( 'id' => 1, 'registrationId' => 101, 'lastUpdated' => $B + 1000, 'teamId' => 8039511, 'team' => '17 Legends', 'programId' => 5079447, 'programName' => '2026/2027 Surfside Volleyball: 17 Legends',
		'programStartDate' => ( time() - 30 * 86400 ) * 1000, 'programEndDate' => ( time() + 200 * 86400 ) * 1000, 'sport' => 'Volleyball', 'role' => 'PLAYER', 'registrationStatus' => 'SPOT_RESERVED',
		'firstName' => 'lauren', 'lastName' => 'BECKER', 'Position' => 'Outside', 'High School Graduation Year' => 'Class of 2027', 'Height' => "5'10", 'isCoCaptain' => true,
		'email' => 'SENTINEL-EMAIL@x.com', 'parentEmail' => 'SENTINEL-PARENT@x.com', 'birthDate' => 'SENTINEL-DOB', 'address1' => 'SENTINEL-ADDRESS', 'phone' => 'SENTINEL-PHONE', 'School' => 'SENTINEL-SCHOOL', 'city' => 'SENTINEL-CITY', 'amountPaid' => 999.99,
		'Jersey Number Preference 1 (Returning Players Receive Priority)' => '7' ), $over );
};
chk( 'player row: name cased, position, grad year, height, captain', DS_Roster_Data::public_row( $reg() ), array( 'f' => 'Lauren', 'l' => 'Becker', 'r' => 'player', 'pos' => 'Outside', 'gy' => '2027', 'ht' => "5'10\"", 'cap' => true, 'ph' => '', 'rl' => '' ) );
chk( 'the jersey PREFERENCE is not taken as a position', DS_Roster_Data::custom_value( array( 'Jersey Number Preference 1' => '7', 'Position' => 'Setter' ), 'position' ), 'Setter' );
chk( 'free agent (registered, not rostered) is left out', DS_Roster_Data::public_row( $reg( array( 'role' => 'FREEAGENT' ) ) ), null );
chk( 'waiting list is left out', DS_Roster_Data::public_row( $reg( array( 'registrationStatus' => 'WAITING_LIST' ) ) ), null );
chk( 'admins and directors are left out', array( DS_Roster_Data::public_row( $reg( array( 'role' => 'Admin' ) ) ), DS_Roster_Data::public_row( $reg( array( 'role' => 'Director' ) ) ) ), array( null, null ) );
chk( 'no team = not on a roster', DS_Roster_Data::public_row( $reg( array( 'teamId' => null ) ) ), null );
$c = DS_Roster_Data::public_row( $reg( array( 'role' => 'Coach', 'firstName' => 'ana', 'lastName' => 'ruiz' ) ) );
chk( 'coach row keeps no player details', array( $c['r'], $c['pos'], $c['gy'], $c['ht'] ), array( 'coach', '', '', '' ) );
foreach ( array( "5'10" => "5'10\"", '5 ft 9 in' => "5'9\"", '5-11' => "5'11\"", '70' => "5'10\"", "6'" => "6'0\"", '5’ 8”' => "5'8\"", 'tall' => 'tall' ) as $in => $out ) {
	chk( "height \"$in\" -> $out", DS_Roster_Data::height( $in ), $out );
}
chk( 'names: "molly" -> Molly, "OLeary" and "McDonald" kept', array( DS_Roster_Data::name_case( 'molly' ), DS_Roster_Data::name_case( 'OLeary' ), DS_Roster_Data::name_case( 'McDonald' ) ), array( 'Molly', 'OLeary', 'McDonald' ) );

echo "teams in the store\n";
$s = DS_Roster_Data::apply( array(), array( $reg(), $reg( array( 'id' => 2, 'registrationId' => 102, 'firstName' => 'Chloe', 'lastName' => 'Cruz', 'Position' => 'Setter', 'isCoCaptain' => false ) ), $reg( array( 'id' => 3, 'registrationId' => 103, 'role' => 'FREEAGENT' ) ) ) );
chk( 'two rostered players on one team (free agent skipped)', count( $s['teams']['8039511']['m'] ), 2 );
$s = DS_Roster_Data::apply( $s, array( $reg( array( 'registrationId' => 102, 'firstName' => 'Chloe', 'lastName' => 'Cruz', 'teamId' => 8039512, 'team' => '16 Legends' ) ) ) );
chk( 'a player who moves team moves (not on both)', array( count( $s['teams']['8039511']['m'] ), count( $s['teams']['8039512']['m'] ) ), array( 1, 1 ) );
$s = DS_Roster_Data::apply( $s, array( $reg( array( 'registrationId' => 102, 'registrationStatus' => 'WAITING_LIST', 'teamId' => 8039512 ) ) ) );
chk( 'a player taken off a roster goes, and the empty team is dropped', isset( $s['teams']['8039512'] ), false );
$s = DS_Roster_Data::apply( $s, array( $reg( array( 'registrationId' => 500, 'teamId' => 6471387, 'team' => '18 PV Legends', 'programEndDate' => ( time() - 500 * 86400 ) * 1000 ) ) ) );
chk( 'a team whose season ended over 400 days ago is not kept', isset( $s['teams']['6471387'] ), false );
$blob = serialize( $s );
chk( 'nothing private reaches the store (emails, birthday, address, phone, school, city, payments)', 0 === preg_match( '/SENTINEL|999\.99/', $blob ), true );

echo "sync\n";
$GLOBALS['opts'] = array_intersect_key( $GLOBALS['opts'], array( 'ds_la_private_keys' => 1 ) ); $GLOBALS['tr'] = array();
$p1 = array( $reg( array( 'id' => 1, 'registrationId' => 101, 'lastUpdated' => $B + 10 ) ), $reg( array( 'id' => 2, 'registrationId' => 102, 'lastUpdated' => $B + 11, 'firstName' => 'Chloe', 'lastName' => 'Cruz' ) ) );
$p2 = array( $reg( array( 'id' => 2, 'registrationId' => 102, 'lastUpdated' => $B + 11, 'firstName' => 'Chloe', 'lastName' => 'Cruz' ) ), $reg( array( 'id' => 3, 'registrationId' => 103, 'lastUpdated' => $B + 12, 'role' => 'Coach', 'firstName' => 'Ana', 'lastName' => 'Ruiz' ) ) );
$GLOBALS['http'] = array( array( 'code' => 200, 'body' => json_encode( $p1 ) ), array( 'code' => 200, 'body' => json_encode( $p2 ) ), array( 'code' => 200, 'body' => json_encode( array( end( $p2 ) ) ) ) );
$r = DS_Roster_Data::sync( '52078' );
list( $h, $cl, $sg ) = explode( '.', (string) $GLOBALS['jwt'] );
$claims = json_decode( base64_decode( strtr( $cl, '-_', '+/' ) ), true );
chk( 'login JWT: RS256 signature verifies with the public key', 1 === openssl_verify( $h . '.' . $cl, base64_decode( strtr( $sg, '-_', '+/' ) . str_repeat( '=', ( 4 - strlen( $sg ) % 4 ) % 4 ) ), $pub, OPENSSL_ALGO_SHA256 ), true );
chk( 'login JWT: iss = sub = client id, aud = token URL, 5-minute expiry', array( $claims['iss'], $claims['sub'], $claims['aud'], $claims['exp'] - $claims['iat'] ), array( 'abc123client', 'abc123client', 'https://auth.leagueapps.io/v2/auth/token', 300 ) );
chk( 'pages are read with the bearer token', $GLOBALS['hdr']['Authorization'] ?? '', 'Bearer tok-123' );
chk( 'paging follows the cursor (last-updated + last-id of the previous page)', array( substr( $GLOBALS['urls'][1], strpos( $GLOBALS['urls'][1], '?' ) ), substr( $GLOBALS['urls'][2], strpos( $GLOBALS['urls'][2], '?' ) ) ), array( '?last-updated=' . ( $B + 11 ) . '&last-id=2', '?last-updated=' . ( $B + 12 ) . '&last-id=3' ) );
chk( 'the repeated cursor row is not counted twice', count( $r['teams']['8039511']['m'] ), 3 );
$team = DS_Roster_Data::team( '52078:8039511' );
chk( 'team(): players by last name, coach apart', array( wp_list_pluck_t( $team['players'], 'l' ), wp_list_pluck_t( $team['coaches'], 'l' ) ), array( array( 'Becker', 'Cruz' ), array( 'Ruiz' ) ) );
chk( 'the token is cached sealed, not in the clear', false === strpos( serialize( $GLOBALS['tr'] ), 'tok-123' ), true );
chk( 'a full sync was recorded (daily rebuild clock)', ! empty( $GLOBALS['opts']['ds_la_roster_52078']['full'] ), true );

$GLOBALS['urls'] = array(); $GLOBALS['http'] = array( array( 'code' => 200, 'body' => '[]' ) );
DS_Roster_Data::sync( '52078' );
chk( 'next sync is incremental from the saved cursor', substr( $GLOBALS['urls'][0], strpos( $GLOBALS['urls'][0], '?' ) ), '?last-updated=' . ( $B + 12 ) . '&last-id=3' );

$GLOBALS['urls'] = array(); $GLOBALS['http'] = array( array( 'code' => 200, 'body' => json_encode( array( $reg( array( 'id' => 9, 'registrationId' => 109, 'lastUpdated' => $B + 50, 'firstName' => 'Zoe', 'lastName' => 'Zed' ) ) ) ) ), array( 'code' => 500, 'body' => '' ) );
DS_Roster_Data::sync( '52078', true );
chk( 'a full rebuild that fails half-way keeps showing the last complete roster', count( DS_Roster_Data::team( '52078:8039511' )['players'] ), 2 );
chk( '... and remembers where to carry on', ! empty( $GLOBALS['opts']['ds_la_roster_52078']['partial'] ), true );
chk( '... and says what went wrong', $GLOBALS['opts']['ds_la_roster_52078']['error'], 'HTTP 500' );
$GLOBALS['http'] = array( array( 'code' => 200, 'body' => '[]' ) );
DS_Roster_Data::sync( '52078', false );
chk( 'the next run finishes it and swaps the new roster in', array( count( DS_Roster_Data::team( '52078:8039511' )['players'] ), empty( $GLOBALS['opts']['ds_la_roster_52078']['partial'] ) ), array( 1, true ) );

$GLOBALS['auth'] = array( 'code' => 401, 'body' => '{}' ); $GLOBALS['tr'] = array();
$e = DS_Roster_Data::sync( '52078', true );
chk( 'a refused key keeps the roster and records the reason', array( count( DS_Roster_Data::team( '52078:8039511' )['players'] ), false !== strpos( $GLOBALS['opts']['ds_la_roster_52078']['error'], '401' ) ), array( 1, true ) );
unset( $GLOBALS['auth'] );
chk( 'a second sync while one runs is refused (database lock, shared by every container)', ( function () { global $wpdb; $wpdb->rows['ds_la_roster_lock_52078'] = (string) time(); $r = DS_Roster_Data::sync( '52078' ); unset( $wpdb->rows['ds_la_roster_lock_52078'] ); return is_wp_error( $r ) && 'ds_roster_busy' === $r->get_error_code(); } )(), true );
chk( '... and a lock left by a run that died (over 90 s) is taken over', ( function () { global $wpdb; $wpdb->rows['ds_la_roster_lock_52078'] = (string) ( time() - 120 ); $GLOBALS['http'] = array( array( 'code' => 200, 'body' => '[]' ) ); $r = DS_Roster_Data::sync( '52078' ); return ! is_wp_error( $r ) && ! isset( $wpdb->rows['ds_la_roster_lock_52078'] ); } )(), true );
echo "background runs\n";
$st2 = $GLOBALS['opts']['ds_la_roster_52078']; unset( $st2['partial'] ); $st2['synced'] = time() - 300; $st2['full'] = time() - 300; $GLOBALS['opts']['ds_la_roster_52078'] = $st2;
$GLOBALS['urls'] = array(); $GLOBALS['http'] = array( array( 'code' => 200, 'body' => '[]' ) );
DS_Roster_Data::cron();
chk( 'the hourly job skips a site synced minutes ago', count( $GLOBALS['urls'] ), 0 );
$st2 = $GLOBALS['opts']['ds_la_roster_52078']; $st2['full'] = 0; $GLOBALS['opts']['ds_la_roster_52078'] = $st2;
$GLOBALS['urls'] = array(); $GLOBALS['http'] = array( array( 'code' => 200, 'body' => '[]' ) );
DS_Roster_Data::cron();
chk( '"Sync now" (full = 0) is picked up by the next background run, as a full rebuild', array( count( $GLOBALS['urls'] ), substr( $GLOBALS['urls'][0] ?? '', strpos( $GLOBALS['urls'][0] ?? '?', '?' ) ) ), array( 1, '?last-updated=' . DS_Roster_Data::full_start()[0] . '&last-id=0' ) );
ini_set( 'max_execution_time', '30' ); $_SERVER['REQUEST_TIME_FLOAT'] = microtime( true ) - 1.5;
chk( 'budget fits a 30 s host limit (30 - 1.5 used - 15 page wait - 3 = 10)', DS_Roster_Data::budget(), 10 );
ini_set( 'max_execution_time', '60' );
chk( 'budget on a 60 s host = the 25 s cap', DS_Roster_Data::budget(), 25 );
ini_set( 'max_execution_time', '0' );

echo "key security\n";
$keys = function () { return $GLOBALS['opts']['ds_la_private_keys']; };
$put  = function ( $k ) { $GLOBALS['opts']['ds_la_private_keys'] = $k; };
chk( 'sealed with XChaCha20-Poly1305 (v2 format), secret source recorded', array( substr( $keys()['52078']['key'], 0, 3 ), $keys()['52078']['kek'] ), array( 'v2.', 'salts' ) );
$good = $keys();
$k = $good; $k['11111'] = $k['52078']; $put( $k );
chk( 'the sealed key copied to another LeagueApps site does not open', DS_Roster_Data::key_status( '11111' )['readable'], false );
$k = $good; $k['52078']['client_id'] = 'otherclient'; $put( $k );
chk( 'a changed client ID does not open it', DS_Roster_Data::key_status( '52078' )['readable'], false );
$put( $good ); $wpdb->prefix = 'copy_';
chk( 'another install (a copied or migrated database) cannot open it', DS_Roster_Data::key_status( '52078' )['readable'], false );
$wpdb->prefix = 'wp_';
chk( '... and back on its own install it opens', DS_Roster_Data::key_status( '52078' )['readable'], true );
$k = $good; $b = base64_decode( substr( $k['52078']['key'], 3 ) ); $b[30] = chr( ord( $b[30] ) ^ 1 ); $k['52078']['key'] = 'v2.' . base64_encode( $b ); $put( $k );
chk( 'one flipped bit (tampering) and it does not open', DS_Roster_Data::key_status( '52078' )['readable'], false );
$put( $good );
// The unbound format the first test builds used (secretbox under the salts) is not accepted any more:
// it could have been pasted into another club's slot.
$n = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
$v1 = base64_encode( $n . sodium_crypto_secretbox( $pem, $n, sodium_crypto_generichash( AUTH_KEY . '|' . SECURE_AUTH_KEY . '|' . LOGGED_IN_KEY . '|ds-la-roster', '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES ) ) );
$k = $good; $k['52078']['key'] = $v1; $put( $k );
chk( 'the unbound format of the first test builds is refused', DS_Roster_Data::key_status( '52078' )['readable'], false );
$put( $good );
$GLOBALS['tr'] = array(); $GLOBALS['http'] = array( array( 'code' => 200, 'body' => '[]' ) );
DS_Roster_Data::sync( '52078' );
chk( 'login: TLS verified, no redirects followed (the assertion cannot be forwarded)', array( $GLOBALS['post_args']['sslverify'], $GLOBALS['post_args']['redirection'], $GLOBALS['post_args']['reject_unsafe_urls'] ), array( true, 0, true ) );
chk( 'export: same for the bearer token', array( $GLOBALS['get_args']['sslverify'], $GLOBALS['get_args']['redirection'] ), array( true, 0 ) );
chk( 'the token is sealed v2 too', substr( (string) $GLOBALS['tr']['ds_la_roster_tok_52078'], 0, 3 ), 'v2.' );
$GLOBALS['tr']['ds_la_roster_tok_11111'] = $GLOBALS['tr']['ds_la_roster_tok_52078']; $k = $keys(); $k['11111'] = $k['52078']; $put( $k );
$GLOBALS['auth'] = array( 'code' => 401, 'body' => '{}' ); $GLOBALS['http'] = array();
chk( 'a token copied to another site is not used (a fresh login is asked, and refused here)', is_wp_error( DS_Roster_Data::sync( '11111' ) ), true );
$k = $keys(); unset( $k['11111'] ); $put( $k ); unset( $GLOBALS['tr']['ds_la_roster_tok_11111'] );
delete_transient( 'ds_la_roster_lock_52078' ); delete_transient( 'ds_la_roster_tok_52078' );
DS_Roster_Data::sync( '52078' ); delete_transient( 'ds_la_roster_lock_52078' ); DS_Roster_Data::sync( '52078' );
$refused = array_filter( DS_Roster_Data::audit_log( '52078' ), function ( $e ) { return 'refused 401' === $e['event']; } );
chk( 'a refused login is in the audit trail once a day, not every sync', count( $refused ), 1 );
unset( $GLOBALS['auth'] );
$ec = openssl_pkey_new( array( 'private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1' ) ); openssl_pkey_export( $ec, $ecpem );
$sm = openssl_pkey_new( array( 'private_key_bits' => 1024, 'private_key_type' => OPENSSL_KEYTYPE_RSA ) ); openssl_pkey_export( $sm, $smpem );
chk( 'a non-RSA key or an RSA key under 2048 bits is refused', array( is_wp_error( DS_Roster_Data::save_key( '52078', 'abc123client', $ecpem ) ), is_wp_error( DS_Roster_Data::save_key( '52078', 'abc123client', $smpem ) ) ), array( true, true ) );
$GLOBALS['tr']['ds_la_roster_tok_52078'] = 'v2.a-token-from-the-old-key';
DS_Roster_Data::save_key( '52078', 'abc123client', $pem );
chk( 'replacing a key drops the old token', isset( $GLOBALS['tr']['ds_la_roster_tok_52078'] ), false );
$audit = serialize( DS_Roster_Data::audit_log() );
chk( 'audit trail: saved/replaced/resealed with user and fingerprint, never key or token', array( DS_Roster_Data::audit_log( '52078' )[0]['event'], false === strpos( $audit, 'PRIVATE' ) && false === strpos( $audit, 'tok-123' ) ), array( 'replaced', true ) );

$GLOBALS['tr']['ds_la_roster_tok_52078'] = 'v2.a-live-token';
DS_Roster_Data::delete_key( '52078' );
chk( 'removing the key removes the stored rosters too', array( DS_Roster_Data::key_status( '52078' ), isset( $GLOBALS['opts']['ds_la_roster_52078'] ) ), array( null, false ) );
chk( '... and its cached token, with an audit entry', array( isset( $GLOBALS['tr']['ds_la_roster_tok_52078'] ), DS_Roster_Data::audit_log( '52078' )[0]['event'] ), array( false, 'removed' ) );

echo "audit fixes\n";
DS_Roster_Data::save_key( '52078', 'abc123client', $pem );
$seed = function () use ( $reg ) { delete_transient( 'ds_la_roster_lock_52078' ); $GLOBALS['http'] = array( array( 'code' => 200, 'body' => json_encode( array( $reg( array( 'id' => 1, 'registrationId' => 101, 'lastUpdated' => $GLOBALS['B'] + 5 ) ) ) ) ), array( 'code' => 200, 'body' => '[]' ) ); return DS_Roster_Data::sync( '52078', true ); };
$GLOBALS['B'] = $B; $seed();
$good_store = DS_Roster_Data::store( '52078' );
chk( 'setup: a complete roster is stored', count( $good_store['teams'] ?? array() ), 1 );
foreach ( array( 'a maintenance page (HTML)' => '<html>Down for maintenance</html>', 'an error object' => '{"error":"boom"}', 'a cut-off body' => '[{"id":1,"lastUp' ) as $what => $body ) {
	delete_transient( 'ds_la_roster_lock_52078' ); $GLOBALS['http'] = array( array( 'code' => 200, 'body' => $body ) );
	DS_Roster_Data::sync( '52078', true );
	$st = DS_Roster_Data::store( '52078' );
	chk( "full rebuild given $what: error, the published roster is untouched", array( count( $st['teams'] ?? array() ), '' !== ( $st['error'] ?? '' ) ), array( 1, true ) );
}
$GLOBALS['opts']['ds_la_roster_52078'] = $good_store;
delete_transient( 'ds_la_roster_lock_52078' );
$GLOBALS['http'] = array( array( 'code' => 200, 'body' => json_encode( array( $reg( array( 'id' => 1, 'registrationId' => 101, 'lastUpdated' => $B - 999999 ) ) ) ) ) );
DS_Roster_Data::sync( '52078' );
chk( 'a page whose cursor goes backwards stops the sync', false !== strpos( DS_Roster_Data::store( '52078' )['error'] ?? '', 'out of order' ), true );
$GLOBALS['opts']['ds_la_roster_52078'] = $good_store;
$GLOBALS['on_get'] = function () { DS_Roster_Data::delete_key( '52078' ); unset( $GLOBALS['on_get'] ); };
$GLOBALS['http'] = array( array( 'code' => 200, 'body' => json_encode( array( $reg( array( 'id' => 7, 'registrationId' => 107, 'lastUpdated' => $B + 50 ) ) ) ) ), array( 'code' => 200, 'body' => '[]' ) );
$r = DS_Roster_Data::sync( '52078', true );
chk( 'key removed while a sync runs: the sync saves nothing (the roster stays removed)', array( is_wp_error( $r ) ? $r->get_error_code() : 'saved', isset( $GLOBALS['opts']['ds_la_roster_52078'] ) ), array( 'ds_roster_key_changed', false ) );
DS_Roster_Data::save_key( '52078', 'abc123client', $pem ); $seed(); $good_store = DS_Roster_Data::store( '52078' );
DS_Roster_Data::save_key( '52078', 'abc123client', $pem );
chk( 'a new key for the SAME client (rotation): the roster keeps showing, a full rebuild is asked for', array( count( DS_Roster_Data::store( '52078' )['teams'] ?? array() ), false !== get_option( 'ds_la_roster_force_52078', false ) ), array( 1, true ) );
$GLOBALS['urls'] = array(); $GLOBALS['http'] = array( array( 'code' => 200, 'body' => '[]' ) ); delete_transient( 'ds_la_roster_lock_52078' );
DS_Roster_Data::cron();
chk( '... the next background run does that full rebuild from the start, and clears the request', array( substr( $GLOBALS['urls'][0] ?? '?', strpos( $GLOBALS['urls'][0] ?? '?', '?' ) ), get_option( 'ds_la_roster_force_52078', 'gone' ) ), array( '?last-updated=' . DS_Roster_Data::full_start()[0] . '&last-id=0', 'gone' ) );
$seed();
DS_Roster_Data::save_key( '52078', 'otherclient', $pem );
chk( 'a key for a DIFFERENT client starts a fresh roster', DS_Roster_Data::store( '52078' ), array() );
DS_Roster_Data::save_key( '52078', 'abc123client', $pem ); $seed();
chk( '"Sync rosters now" asks for a full rebuild without rewriting the roster', array( DS_Roster_Data::request_full( '52078' ), count( DS_Roster_Data::store( '52078' )['teams'] ), false !== get_option( 'ds_la_roster_force_52078', false ) ), array( true, 1, true ) );
chk( '... and refuses a site with no key', DS_Roster_Data::request_full( '11111' ), false );
delete_option( 'ds_la_roster_force_52078' );
$k = $GLOBALS['opts']['ds_la_private_keys']; unset( $GLOBALS['opts']['ds_la_private_keys']['52078'] );
chk( 'with no key, a stored roster is never shown', DS_Roster_Data::team( '52078:8039511' ), null );
$GLOBALS['opts']['ds_la_private_keys'] = $k;
chk( 'secret check: the wp-config sample phrase and short values are refused', array( DS_Roster_Data::secret_ok( 'put your unique phrase here' ), DS_Roster_Data::secret_ok( '|||' ), DS_Roster_Data::secret_ok( str_repeat( 'x', 40 ) ) ), array( false, false, true ) );
chk( 'roles: player, any coach, trainer on the roster; admin, director, coordinator, volunteer not', array_map( array( 'DS_Roster_Data', 'role_kind' ), array( 'PLAYER', 'Coach', 'HEAD COACH', 'ASSISTANT COACH', 'TRAINER', 'ADMIN', 'DIRECTOR', 'AGE GROUP COORDINATOR', 'VOLUNTEER', 'FREEAGENT' ) ), array( 'player', 'coach', 'coach', 'coach', 'coach', '', '', '', '', '' ) );
chk( 'statuses: an allowlist (reserved and pending spots only)', array( (bool) DS_Roster_Data::public_row( $reg( array( 'registrationStatus' => 'SPOT_PENDING' ) ) ), DS_Roster_Data::public_row( $reg( array( 'registrationStatus' => 'INVITED' ) ) ), DS_Roster_Data::public_row( $reg( array( 'registrationStatus' => '' ) ) ) ), array( true, null, null ) );
chk( 'position: "Position" wins over "Secondary Position" listed first', DS_Roster_Data::custom_value( array( 'Secondary Position' => 'Setter', 'Position' => 'Outside' ), 'position' ), 'Outside' );
chk( 'position: "Primary Position" when there is no plain one; never the secondary or a volunteer question', array( DS_Roster_Data::custom_value( array( 'Secondary Position' => 'Setter', 'Primary Position' => 'Libero' ), 'position' ), DS_Roster_Data::custom_value( array( 'Volunteer position you can help with' => 'Scorer' ), 'position' ) ), array( 'Libero', '' ) );
$GLOBALS['opts']['ds_la_roster_52078']['teams']['8039511']['m'] = array(
	201 => DS_Roster_Data::public_row( $reg( array( 'registrationId' => 201, 'role' => 'TRAINER', 'firstName' => 'Tess', 'lastName' => 'Abbot' ) ) ),
	202 => DS_Roster_Data::public_row( $reg( array( 'registrationId' => 202, 'role' => 'ASSISTANT COACH', 'firstName' => 'Al', 'lastName' => 'Baker' ) ) ),
	203 => DS_Roster_Data::public_row( $reg( array( 'registrationId' => 203, 'role' => 'HEAD COACH', 'firstName' => 'Hana', 'lastName' => 'Zane' ) ) ),
);
$c = DS_Roster_Data::team( '52078:8039511' )['coaches'];
chk( 'staff: head coach first, then assistants, then trainers, each with their own title', array_map( function ( $x ) { return $x['rl']; }, $c ), array( 'Head Coach', 'Assistant Coach', 'Trainer' ) );

echo "dedicated secret (DS_LA_KEY_SECRET)\n";
DS_Roster_Data::save_key( '52078', 'abc123client', $pem );
define( 'DS_LA_KEY_SECRET', str_repeat( 'k', 64 ) );
chk( 'new keys are sealed under the dedicated secret', DS_Roster_Data::kek_in_use(), 'constant' );
chk( 'a key sealed under the salts before it was added still opens', DS_Roster_Data::key_status( '52078' )['readable'], true );
chk( '... and is moved to the dedicated secret by itself', $keys()['52078']['kek'], 'constant' );

echo "teams without an end date\n";
$nt = DS_Roster_Data::apply( array(), array(
	$reg( array( 'id' => 71, 'registrationId' => 71, 'teamId' => 501, 'team' => 'AJV 16 Molten', 'programEndDate' => null, 'programStartDate' => ( time() - 3 * 365 * 86400 ) * 1000 ) ),
	$reg( array( 'id' => 72, 'registrationId' => 72, 'teamId' => 502, 'team' => '15 Navy', 'programEndDate' => null, 'programStartDate' => ( time() - 100 * 86400 ) * 1000 ) ),
	$reg( array( 'id' => 73, 'registrationId' => 73, 'teamId' => 503, 'team' => 'Undated', 'programEndDate' => null, 'programStartDate' => null ) ),
) );
chk( 'no end date: a one-year season from its start (3-year-old team dropped, this season kept)', array_keys( $nt['teams'] ), array( 502, 503 ) );

echo "large clubs (Austin Junior VB)\n";
$slow = function () { return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out after 15001 milliseconds with 0 bytes received' ); };
$pg   = function ( $id, $lu ) use ( $reg, $B ) { return array( 'code' => 200, 'body' => json_encode( array( $reg( array( 'id' => $id, 'registrationId' => 900 + $id, 'lastUpdated' => $B + $lu ) ) ) ) ); };
$run  = function ( $force = false ) { delete_transient( 'ds_la_roster_lock_52078' ); $GLOBALS['urls'] = array(); $GLOBALS['cron'] = array(); return DS_Roster_Data::sync( '52078', $force ); };
$q    = function ( $u ) { return substr( $u, strpos( $u, '?' ) ); };
delete_option( 'ds_la_roster_52078' );
$GLOBALS['http'] = array( $pg( 1, 2000 ), array( 'code' => 200, 'body' => '[]' ) );
$run( true );
chk( 'a full rebuild reads the last two years, not the whole history', $q( $GLOBALS['urls'][0] ), '?last-updated=' . DS_Roster_Data::full_start()[0] . '&last-id=0' );
chk( 'full_start is 730 days back', (int) round( ( time() * 1000 - DS_Roster_Data::full_start()[0] ) / 86400000 ), 730 );
$GLOBALS['http'] = array( $pg( 1, 2000 ), $slow(), $pg( 2, 3000 ), array( 'code' => 200, 'body' => '[]' ) );
$r = $run( true );
chk( 'one slow page is asked again in the same run and the sync finishes', array( count( $GLOBALS['urls'] ), $q( $GLOBALS['urls'][1] ) === $q( $GLOBALS['urls'][2] ), $r['error'] ?? 'x', isset( $r['partial'] ) ), array( 4, true, '', false ) );
delete_option( 'ds_la_roster_52078' );
$GLOBALS['http'] = array( $pg( 1, 2000 ), $slow(), $slow() );
$run( true );
$st = DS_Roster_Data::key_status( '52078' );
chk( 'two slow pages in a row: the run stops, keeps its progress', array( $st['partial']['teams'], $st['partial']['retries'], false !== strpos( $st['error'], '15 seconds' ) ), array( 1, 1, true ) );
chk( '... and carries on by itself in two minutes (not at the hourly run)', array( $GLOBALS['cron'][0][1] ?? '', ( $GLOBALS['cron'][0][0] ?? 0 ) - time() ), array( 'ds_la_roster_continue', 120 ) );
$GLOBALS['http'] = array( $pg( 2, 3000 ), array( 'code' => 200, 'body' => '[]' ) );
$r = $run();
chk( 'the next run carries on from the saved page and finishes', array( $q( $GLOBALS['urls'][0] ), $r['error'], isset( $r['partial'] ), isset( $r['retries'] ), count( $r['teams']['8039511']['m'] ?? array() ) ), array( '?last-updated=' . ( $B + 2000 ) . '&last-id=1', '', false, false, 2 ) );
delete_option( 'ds_la_roster_52078' );
for ( $i = 1; $i <= DS_Roster_Data::RETRY_MAX + 1; $i++ ) { $GLOBALS['http'] = array( $slow(), $slow() ); $run( 1 === $i ); }
chk( 'after ' . ( DS_Roster_Data::RETRY_MAX + 1 ) . ' failed runs in a row it waits for the hourly sync', array( DS_Roster_Data::key_status( '52078' )['partial']['retries'], count( $GLOBALS['cron'] ) ), array( DS_Roster_Data::RETRY_MAX + 1, 0 ) );
$GLOBALS['http'] = array( new WP_Error( 'http_request_failed', 'cURL error 6: Could not resolve host' ) );
$run();
chk( 'an unreachable server is not asked twice in one run, and says so', array( count( $GLOBALS['urls'] ), DS_Roster_Data::key_status( '52078' )['error'] ), array( 1, 'LeagueApps could not be reached.' ) );

function wp_list_pluck_t( $l, $f ) { return array_map( function ( $x ) use ( $f ) { return $x[ $f ]; }, $l ); }
echo $fails ? "\n$fails FAILED\n" : "\nall passed\n";
exit( $fails ? 1 : 0 );
