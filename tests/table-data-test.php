<?php
/**
 * DS_Table_Data (modules/ds-table): storage, CSV parsing, column types, sort keys,
 * Google Sheets links and cell escaping. Needs WordPress (esc_html, wp_json_encode…):
 *   wp eval-file tests/table-data-test.php
 * Exit code 1 on any failure.
 */
if ( ! class_exists( 'DS_Table_Data' ) ) { require_once dirname( __DIR__ ) . '/modules/ds-table/includes/class-ds-table-data.php'; }

$GLOBALS['dst_fail'] = 0; $GLOBALS['dst_n'] = 0;
function dst_is( $label, $got, $want ) {
	$GLOBALS['dst_n']++;
	$ok = $got === $want;
	if ( ! $ok ) { $GLOBALS['dst_fail']++; }
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $ok ? '' : "\n     got:  " . var_export( $got, true ) . "\n     want: " . var_export( $want, true ) ) . "\n";
}

/* ---- storage ---- */
$t = array( 'cols' => array( array( 'label' => 'Día', 'align' => 'center' ), array( 'label' => 'Notes' ) ), 'rows' => array( array( 'Sat – Jan 17', "Line 1\nLine \"2\", ok 🏈" ) ) );
$enc = DS_Table_Data::encode( $t );
dst_is( 'encoded value is not JSON-looking (BB would decode it)', null === json_decode( $enc ) || ! is_array( json_decode( $enc, true ) ), true );
$dec = DS_Table_Data::decode( $enc );
dst_is( 'round trip keeps unicode, line breaks and quotes', $dec['rows'][0][1], "Line 1\nLine \"2\", ok 🏈" );
dst_is( 'round trip keeps column options', array( $dec['cols'][0]['label'], $dec['cols'][0]['align'], $dec['cols'][1]['align'] ), array( 'Día', 'center', '' ) );
dst_is( 'decode accepts an already-decoded object (BB json_decode_deep)', DS_Table_Data::decode( json_decode( wp_json_encode( $t ) ) )['rows'][0][0], 'Sat – Jan 17' );
dst_is( 'decode of garbage is an empty table', DS_Table_Data::decode( 'dst1:%%%' ), array( 'cols' => array(), 'rows' => array() ) );
$rag = DS_Table_Data::normalize( array( 'cols' => array( 'A' ), 'rows' => array( array( '1', '2', '3' ), array( '4' ) ) ) );
dst_is( 'ragged rows padded, extra columns created', array( count( $rag['cols'] ), $rag['rows'][1] ), array( 3, array( '4', '', '' ) ) );

/* ---- CSV ---- */
$p = DS_Table_Data::parse_csv( "Date,Time,Location\n\"Sat, Jan 17\",9:00 AM,\"Field 1\nNorth gate\"\nSun Jan 18,11:00 AM,Field 2\n" );
dst_is( 'comma CSV with quoted comma + line break', $p['rows'][1], array( 'Sat, Jan 17', '9:00 AM', "Field 1\nNorth gate" ) );
dst_is( 'comma CSV row count', count( $p['rows'] ), 3 );
$p = DS_Table_Data::parse_csv( "\xEF\xBB\xBFName;Price\n\"Camp; Spring\";€120,50\n" );
dst_is( 'semicolon CSV (European Excel) + BOM stripped', array( $p['delimiter'], $p['rows'][0][0], $p['rows'][1] ), array( ';', 'Name', array( 'Camp; Spring', '€120,50' ) ) );
$p = DS_Table_Data::parse_csv( "A\tB\n1\t2\n" );
dst_is( 'tab-separated', array( $p['delimiter'], $p['rows'][1] ), array( "\t", array( '1', '2' ) ) );
$p = DS_Table_Data::parse_csv( "Team,Coach\nCaf\xE9 FC,Jos\xE9\n" );
dst_is( 'Windows-1252 export converted to UTF-8', $p['rows'][1], array( 'Café FC', 'José' ) );
$p = DS_Table_Data::parse_csv( "A,B,,\n1,2,,\n,,,\n\n3,4,,\n" );
dst_is( 'trailing empty columns and blank rows dropped', $p['rows'], array( array( 'A', 'B' ), array( '1', '2' ), array( '3', '4' ) ) );
dst_is( 'empty file', DS_Table_Data::parse_csv( "  \n" )['rows'], array() );
$big = "H\n" . str_repeat( "x\n", DS_Table_Data::MAX_ROWS + 5 );
$p = DS_Table_Data::parse_csv( $big );
dst_is( 'row cap with a warning', array( count( $p['rows'] ), (bool) $p['warnings'] ), array( DS_Table_Data::MAX_ROWS, true ) );
$tab = DS_Table_Data::from_rows( array( array( 'Date', 'Time' ), array( 'Jan 1', '9 AM' ) ), true );
dst_is( 'first row becomes the headings', array( $tab['cols'][1]['label'], $tab['rows'] ), array( 'Time', array( array( 'Jan 1', '9 AM' ) ) ) );
$csv = DS_Table_Data::to_csv( $dec );
dst_is( 'export then import round trip', DS_Table_Data::from_rows( DS_Table_Data::parse_csv( $csv )['rows'], true )['rows'], $dec['rows'] );

/* ---- column types + sort keys ---- */
dst_is( 'numbers / prices / percents', DS_Table_Data::column_type( array( '$1,200.50', '45', '', '12%' ) ), 'num' );
dst_is( 'times and time ranges', DS_Table_Data::column_type( array( '9:00 AM – 10:30 AM', '6pm', '11:15 am', '12:00 PM' ) ), 'time' );
dst_is( 'dates in three formats', DS_Table_Data::column_type( array( 'Sat, Jan 17, 2026', '2026-01-24', '1/31/2026' ) ), 'date' );
dst_is( 'plain text', DS_Table_Data::column_type( array( 'U10 & U11', 'Main Field', '9' ) ), 'text' );
dst_is( 'date with a time is a date', DS_Table_Data::column_type( array( 'Jan 17, 2026 6:00 PM', 'Jan 18, 2026 9:00 AM' ) ), 'date' );
$times = array( '6pm', '9:00 AM – 10:30 AM', '12:00 PM', '11:15 am' );
usort( $times, function ( $a, $b ) { return (float) DS_Table_Data::sort_key( $a, 'time' ) <=> (float) DS_Table_Data::sort_key( $b, 'time' ); } );
dst_is( 'times sort by clock', $times, array( '9:00 AM – 10:30 AM', '11:15 am', '12:00 PM', '6pm' ) );
$dates = array( '1/31/2026', 'Sat, Jan 17, 2026', '2026-01-24' );
usort( $dates, function ( $a, $b ) { return (float) DS_Table_Data::sort_key( $a, 'date' ) <=> (float) DS_Table_Data::sort_key( $b, 'date' ); } );
dst_is( 'dates sort by calendar', $dates, array( 'Sat, Jan 17, 2026', '2026-01-24', '1/31/2026' ) );
dst_is( '12 AM is midnight', DS_Table_Data::time_minutes( '12:30 AM' ), 30 );

/* ---- Google Sheets links ---- */
dst_is( 'share link -> CSV export (with tab)', DS_Table_Data::csv_url( 'https://docs.google.com/spreadsheets/d/1AbC-xyz_09/edit#gid=123456' ), 'https://docs.google.com/spreadsheets/d/1AbC-xyz_09/export?format=csv&gid=123456' );
dst_is( 'share link without a tab -> first tab', DS_Table_Data::csv_url( 'https://docs.google.com/spreadsheets/d/1AbC/edit?usp=sharing' ), 'https://docs.google.com/spreadsheets/d/1AbC/export?format=csv&gid=0' );
dst_is( 'published link -> CSV output', DS_Table_Data::csv_url( 'https://docs.google.com/spreadsheets/d/e/2PACX-1v/pubhtml?gid=7' ), 'https://docs.google.com/spreadsheets/d/e/2PACX-1v/pub?gid=7&single=true&output=csv' );
dst_is( 'other links unchanged', DS_Table_Data::csv_url( 'https://example.org/schedule.csv' ), 'https://example.org/schedule.csv' );

/* ---- cells ---- */
dst_is( 'HTML is escaped', DS_Table_Data::cell_html( '<script>alert(1)</script>' ), '&lt;script&gt;alert(1)&lt;/script&gt;' );
$h = DS_Table_Data::cell_html( 'Register: https://example.org/reg?a=1&b=2.' );
dst_is( 'web address linked, trailing full stop left out', (bool) preg_match( '#<a class="ds-table-link" href="https://example.org/reg\?a=1&\#038;b=2" target="_blank" rel="noopener">https://example.org/reg\?a=1&amp;b=2</a>\.$#', $h ), true );
dst_is( 'email linked', DS_Table_Data::cell_html( 'coach@club.org' ), '<a class="ds-table-link" href="mailto:coach@club.org">coach@club.org</a>' );
dst_is( 'line breaks kept', DS_Table_Data::cell_html( "a\nb" ), "a<br>\nb" );
dst_is( 'linking can be turned off', DS_Table_Data::cell_html( 'https://example.org', false ), 'https://example.org' );

/* ---- column types: links, buttons, images ---- */
$n = DS_Table_Data::normalize( array( 'cols' => array( array( 'label' => 'Logo', 'type' => 'image' ), array( 'label' => 'X', 'type' => 'evil' ) ), 'rows' => array() ) );
dst_is( 'column type kept, unknown type dropped', array( $n['cols'][0]['type'], $n['cols'][1]['type'] ), array( 'image', '' ) );
dst_is( 'split "Label | address"', DS_Table_Data::split_link( 'Register | https://x.org/r' ), array( 'Register', 'https://x.org/r' ) );
dst_is( 'split [Label](address)', DS_Table_Data::split_link( '[Map](https://maps.example.com/a)' ), array( 'Map', 'https://maps.example.com/a' ) );
dst_is( 'split lone address', DS_Table_Data::split_link( 'https://x.org' ), array( '', 'https://x.org' ) );
dst_is( 'split plain text', DS_Table_Data::split_link( 'Just words' ), array( 'Just words', '' ) );
dst_is( 'javascript: address refused', DS_Table_Data::safe_url( 'javascript:alert(1)' ), '' );
dst_is( 'data: address refused', DS_Table_Data::safe_url( 'data:text/html,<b>x' ), '' );
dst_is( 'site path becomes a full address', DS_Table_Data::safe_url( '/register/' ), home_url( '/register/' ) );
dst_is( 'bare domain gets https', DS_Table_Data::safe_url( 'example.org/tryouts' ), 'https://example.org/tryouts' );
dst_is( 'link cell with label', DS_Table_Data::link_html( 'Register | https://x.org/r', 'link' ), '<a class="ds-table-link" href="https://x.org/r" target="_blank" rel="noopener">Register</a>' );
dst_is( 'lone address takes the column heading', (bool) preg_match( '#>Register</a>$#', DS_Table_Data::link_html( 'https://x.org/r', 'link', 'Register' ) ), true );
dst_is( 'button cell uses the site button class', (bool) preg_match( '#^<a class="fl-button ds-table-btn" href="https://x.org/r"[^>]*><span class="fl-button-text">Go</span></a>$#', DS_Table_Data::link_html( 'Go | https://x.org/r', 'button' ) ), true );
dst_is( 'label markup is escaped', false === strpos( DS_Table_Data::link_html( '<img src=x onerror=alert(1)> | https://x.org', 'link' ), '<img' ), true );
dst_is( 'link cell with a bad address falls back to text', DS_Table_Data::link_html( 'Hi | javascript:alert(1)', 'link' ), 'Hi | javascript:alert(1)' );
$h = DS_Table_Data::cell_html( 'Bring cleats. [Directions](https://maps.example.com/f) or https://x.org' );
dst_is( 'inline [label](address) plus a bare address in one cell', (bool) preg_match( '#^Bring cleats\. <a class="ds-table-link" href="https://maps\.example\.com/f"[^>]*>Directions</a> or <a class="ds-table-link" href="https://x\.org"[^>]*>https://x\.org</a>$#', $h ), true );
dst_is( 'inline javascript: link stays text', DS_Table_Data::cell_html( '[x](javascript:alert(1))' ), '[x](javascript:alert(1))' );
dst_is( 'inline link works with auto-linking off', (bool) preg_match( '#<a [^>]*>Map</a>#', DS_Table_Data::cell_html( '[Map](https://m.org)', false ) ), true );
dst_is( 'Google Drive share link -> thumbnail', DS_Table_Data::image_source( 'https://drive.google.com/file/d/1AbCdEfGhIjK_lm/view?usp=sharing' )['url'], 'https://drive.google.com/thumbnail?id=1AbCdEfGhIjK_lm&sz=w800' );
dst_is( 'number that is not an image attachment', DS_Table_Data::image_source( '999999999' ), array( 'id' => 0, 'url' => '' ) );
dst_is( 'image address with alt text', DS_Table_Data::image_html( 'https://x.org/logo.png', 'U10 Blue', 48 ), '<img class="ds-table-img" src="https://x.org/logo.png" alt="U10 Blue" width="48" height="48" loading="lazy" decoding="async">' );
dst_is( '"image | link" wraps the image in the link', (bool) preg_match( '#^<a class="ds-table-imglink" href="https://x\.org/team"[^>]*><img [^>]*src="https://x\.org/l\.png"#', DS_Table_Data::image_html( 'https://x.org/l.png | https://x.org/team', 'T' ) ), true );
dst_is( 'javascript: image refused', DS_Table_Data::image_html( 'javascript:alert(1)' ), '' );
dst_is( 'display text: link label, no image text, inline link label', array( DS_Table_Data::display_text( 'Go | https://x.org', 'button' ), DS_Table_Data::display_text( 'https://x.org/l.png', 'image' ), DS_Table_Data::display_text( 'See [Map](https://m.org)' ) ), array( 'Go', '', 'See Map' ) );

echo $GLOBALS['dst_fail'] ? "FAILURES: {$GLOBALS['dst_fail']} of {$GLOBALS['dst_n']}\n" : "ALL {$GLOBALS['dst_n']} PASS\n";
if ( $GLOBALS['dst_fail'] ) { exit( 1 ); }
