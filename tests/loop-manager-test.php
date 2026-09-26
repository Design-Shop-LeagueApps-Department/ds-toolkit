<?php
/**
 * DS_Loop_Manager (Post Loop > Manage entries): storage, per-type sanitizing, the loop's
 * taxonomy filter and sort direction, and the guards that stop writes (no edit rights, a
 * non-publish save, an unsupported post type). Writes nothing:
 *   wp eval-file tests/loop-manager-test.php
 * Exit code 1 on any failure.
 */
if ( ! class_exists( 'DS_Loop_Manager' ) ) { require_once dirname( __DIR__ ) . '/modules/ds-post-loop/includes/class-ds-loop-manager.php'; }

$GLOBALS['lm_fail'] = 0; $GLOBALS['lm_n'] = 0;
function lm_is( $label, $got, $want ) {
	$GLOBALS['lm_n']++;
	$ok = $got === $want;
	if ( ! $ok ) { $GLOBALS['lm_fail']++; }
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $ok ? '' : "\n     got:  " . var_export( $got, true ) . "\n     want: " . var_export( $want, true ) ) . "\n";
}

/* ---- storage ---- */
$c = array( 'pt' => 'staff', 'order' => array( '12', 'n1' ), 'items' => array( '12' => array( 'title' => 'Jo – “Coach” & co' ) ), 'trash' => array( 5 ) );
lm_is( 'round trip keeps unicode and structure', DS_Loop_Manager::decode( DS_Loop_Manager::encode( $c ) ), $c );
lm_is( 'stored value is not JSON-looking', null === json_decode( DS_Loop_Manager::encode( $c ) ), true );
lm_is( 'garbage decodes to nothing', DS_Loop_Manager::decode( 'dsm1:%%%' ), array() );
lm_is( 'raw JSON is not accepted', DS_Loop_Manager::decode( '{"pt":"staff"}' ), array() );

/* ---- sanitizing by field type ---- */
$f = function ( $type, $extra = array() ) { return array_merge( array( 'type' => $type, 'choices' => array() ), $extra ); };
lm_is( 'url: javascript: dropped', DS_Loop_Manager::clean_field( 'javascript:alert(1)', $f( 'url' ) ), '' );
lm_is( 'url: https kept', DS_Loop_Manager::clean_field( 'https://x.org/a?b=1', $f( 'url' ) ), 'https://x.org/a?b=1' );
lm_is( 'email: markup stripped (harmless text left)', DS_Loop_Manager::clean_field( 'coach@club.org<script>', $f( 'email' ) ), 'coach@club.orgscript' );
lm_is( 'email: valid kept', DS_Loop_Manager::clean_field( 'coach@club.org', $f( 'email' ) ), 'coach@club.org' );
lm_is( 'text: tags stripped', DS_Loop_Manager::clean_field( '<b>Head</b> Coach<script>x</script>', $f( 'text' ) ), 'Head Coach' );
lm_is( 'number: not a number refused', DS_Loop_Manager::clean_field( '12abc', $f( 'number' ) ), null );
lm_is( 'select: value outside the choices refused', DS_Loop_Manager::clean_field( 'Senior; DROP', $f( 'select', array( 'choices' => array( 'Senior' => 'Senior', 'Junior' => 'Junior' ) ) ) ), null );
lm_is( 'select: listed choice kept', DS_Loop_Manager::clean_field( 'Junior', $f( 'select', array( 'choices' => array( 'Senior' => 'Senior', 'Junior' => 'Junior' ) ) ) ), 'Junior' );
lm_is( 'image: a non-image ID refused', DS_Loop_Manager::clean_field( array( 'id' => 999999999 ), $f( 'image' ) ), null );
lm_is( 'image: empty clears it', DS_Loop_Manager::clean_field( array( 'id' => 0 ), $f( 'image' ) ), 0 );
lm_is( 'true/false', array( DS_Loop_Manager::clean_field( '1', $f( 'true_false' ) ), DS_Loop_Manager::clean_field( '', $f( 'true_false' ) ) ), array( 1, 0 ) );
$w = DS_Loop_Manager::clean_field( '<p onclick="x()">Hi <strong>there</strong> <a href="javascript:alert(1)">x</a> <a href="https://x.org" target="_blank">ok</a></p><div>d</div><script>bad()</script>', $f( 'wysiwyg' ) );
lm_is( 'rich text: only p / strong / a(href) survive; script blocks gone with their content', $w, '<p>Hi <strong>there</strong> <a href="alert(1)">x</a> <a href="https://x.org" target="_blank">ok</a></p>d' );
lm_is( 'rich text: no script tag, no onclick, no javascript: left', (bool) preg_match( '/<script|onclick|javascript:/i', $w ), false );

/* ---- the loop's filter and direction ---- */
lm_is( 'taxonomy filter from the loop settings', DS_Loop_Manager::tax_filter( array( 'filter_tax' => 'staff-category', 'flt_staff_category' => '3,4' ) ), array( 'taxonomy' => 'staff-category', 'field' => 'term_id', 'terms' => array( '3', '4' ) ) );
lm_is( 'no taxonomy, no filter', DS_Loop_Manager::tax_filter( array( 'filter_tax' => '' ) ), array() );
lm_is( 'direction: ASC / DESC', array( DS_Loop_Manager::ascending( array( 'order_by' => 'menu_order', 'order' => 'ASC' ) ), DS_Loop_Manager::ascending( array( 'order_by' => 'menu_order', 'order' => 'DESC' ) ) ), array( true, false ) );
lm_is( 'pages, posts and media cannot be managed', array( DS_Loop_Manager::supported( 'page' ), DS_Loop_Manager::supported( 'post' ), DS_Loop_Manager::supported( 'attachment' ), DS_Loop_Manager::supported( 'staff' ) ), array( false, false, false, true ) );

/* ---- guards: nothing is written ---- */
$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( (int) $admin[0] );
$target = get_posts( array( 'post_type' => 'staff', 'numberposts' => 1 ) );
if ( $target ) {
	$t      = $target[0];
	$before = array( $t->post_title, get_post_meta( $t->ID, 'staff_title', true ), $t->post_status );
	$deny   = function ( $caps ) { foreach ( array( 'edit_posts', 'edit_others_posts', 'edit_published_posts', 'delete_posts', 'delete_others_posts', 'delete_published_posts', 'publish_posts', 'edit_post', 'delete_post' ) as $k ) { $caps[ $k ] = false; } return $caps; };
	add_filter( 'user_has_cap', $deny );
	$r = DS_Loop_Manager::apply( array( 'pt' => 'staff', 'items' => array( (string) $t->ID => array( 'title' => 'HACKED', 'fields' => array( 'staff_title' => 'x' ), 'status' => 'draft' ), 'n1' => array( '_new' => 1, 'title' => 'Injected' ) ), 'trash' => array( $t->ID ), 'order' => array( (string) $t->ID ) ), (object) array( 'order' => 'DESC' ) );
	remove_filter( 'user_has_cap', $deny );
	$t2 = get_post( $t->ID );
	lm_is( 'without edit rights: every change skipped', array( $r['updated'], $r['created'], $r['trashed'], $r['reordered'] ), array( 0, 0, 0, 0 ) );
	lm_is( 'without edit rights: the post is untouched', array( $t2->post_title, get_post_meta( $t->ID, 'staff_title', true ), $t2->post_status ), $before );
	lm_is( 'without edit rights: nothing was created', count( get_posts( array( 'post_type' => 'staff', 'title' => 'Injected', 'post_status' => 'any' ) ) ), 0 );
	$r = DS_Loop_Manager::apply( array( 'pt' => 'page', 'items' => array( (string) $t->ID => array( 'title' => 'HACKED' ) ) ), (object) array() );
	lm_is( 'a post type the manager does not support is ignored', array( $r['updated'], get_post( $t->ID )->post_title ), array( 0, $before[0] ) );
	$r = DS_Loop_Manager::apply( array( 'pt' => 'staff', 'items' => array( (string) get_option( 'page_on_front' ) => array( 'title' => 'HACKED' ) ) ), (object) array() );
	lm_is( 'an ID of another post type is skipped', array( $r['updated'], $r['skipped'], get_the_title( get_option( 'page_on_front' ) ) !== 'HACKED' ), array( 0, 1, true ) );
	// A "Save Draft" (publish = false) must not apply anything.
	$node = (object) array( 'type' => 'module', 'settings' => (object) array( 'type' => 'ds-post-loop', 'pl_manage' => DS_Loop_Manager::encode( array( 'pt' => 'staff', 'items' => array( (string) $t->ID => array( 'title' => 'HACKED' ) ) ) ) ) );
	DS_Loop_Manager::on_publish( 0, false, array( 'x' => $node ), array() );
	lm_is( 'a draft save applies nothing', get_post( $t->ID )->post_title, $before[0] );
} else {
	echo "SKIP guard tests: no staff post on this site\n";
}

echo $GLOBALS['lm_fail'] ? "FAILURES: {$GLOBALS['lm_fail']} of {$GLOBALS['lm_n']}\n" : "ALL {$GLOBALS['lm_n']} PASS\n";
if ( $GLOBALS['lm_fail'] ) { exit( 1 ); }
