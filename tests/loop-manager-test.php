<?php
/**
 * DS_Loop_Manager (Post Loop > Manage entries): storage, per-type sanitizing, the loop's
 * taxonomy filter and sort direction, and the guards that stop writes (no edit rights, a
 * non-publish save, an unsupported post type), plus the audit fixes (payload cleaning,
 * the author's rights, applied once, backslashes kept, preview limits). Real posts are never
 * touched: the write tests create temporary staff posts and a page, and delete them:
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
lm_is( 'rich text: post markup kept, onclick and javascript: gone, script blocks gone with their content', $w, '<p>Hi <strong>there</strong> <a href="alert(1)">x</a> <a href="https://x.org" target="_blank">ok</a></p><div>d</div>' );
lm_is( 'rich text: no script tag, no onclick, no javascript: left', (bool) preg_match( '/<script|onclick|javascript:/i', $w ), false );

/* ---- the loop's filter and direction ---- */
lm_is( 'taxonomy filter from the loop settings', DS_Loop_Manager::tax_filter( array( 'filter_tax' => 'staff-category', 'flt_staff_category' => '3,4' ) ), array( 'taxonomy' => 'staff-category', 'field' => 'term_id', 'terms' => array( '3', '4' ) ) );
lm_is( 'no taxonomy, no filter', DS_Loop_Manager::tax_filter( array( 'filter_tax' => '' ) ), array() );
lm_is( 'direction: ASC / DESC', array( DS_Loop_Manager::ascending( array( 'order_by' => 'menu_order', 'order' => 'ASC' ) ), DS_Loop_Manager::ascending( array( 'order_by' => 'menu_order', 'order' => 'DESC' ) ) ), array( true, false ) );
lm_is( 'pages, posts and media cannot be managed', array( DS_Loop_Manager::supported( 'page' ), DS_Loop_Manager::supported( 'post' ), DS_Loop_Manager::supported( 'attachment' ), DS_Loop_Manager::supported( 'staff' ) ), array( false, false, false, true ) );

/* ---- audit fixes: payload shape (2026-09-27) ---- */
$bad = DS_Loop_Manager::sanitize_payload( array(
	'pt'    => 'staff',
	'trash' => array( '"><img src=x onerror=alert(1)>', '12', 'x' ),
	'order' => array( '12', array( 'x' ), '<b>', 'n2' ),
	'items' => array( '12' => array( 'title' => '<img src=x onerror=alert(1)>Coach<br>U14', 'thumb' => array( 'id' => 0, 'url' => 'https://evil.example/t.gif' ), 'fields' => array( 'bad name' => 1, 'staff_title' => array( 'x' => 1 ) ) ), '"><x' => array( 'title' => 'a' ), 'n2' => array( 'title' => 'New' ) ),
) );
lm_is( 'payload: trash keeps only positive integers', $bad['trash'], array( 12 ) );
lm_is( 'payload: order keeps only entry keys', $bad['order'], array( '12', 'n2' ) );
lm_is( 'payload: a crafted item key is dropped, new keys marked new', array_map( 'strval', array_keys( $bad['items'] ) ), array( '12', 'n2' ) );
lm_is( 'payload: title keeps inline markup, loses the script attribute', $bad['items']['12']['title'], 'Coach<br>U14' );
lm_is( 'payload: an image URL is never taken from the payload', $bad['items']['12']['thumb'], array( 'id' => 0, 'url' => '' ) );
lm_is( 'payload: bad field names dropped', array_keys( $bad['items']['12']['fields'] ), array( 'staff_title' ) );
lm_is( 'payload: new entry flagged', ! empty( $bad['items']['n2']['_new'] ), true );
$node = (object) array( 'settings' => (object) array( 'type' => 'ds-post-loop' ) );
$in   = (object) array( 'pl_manage' => DS_Loop_Manager::encode( array( 'pt' => 'staff', 'trash' => array( '1"><x' ), 'by' => 999 ) ) );
$out  = DS_Loop_Manager::decode( DS_Loop_Manager::on_save_settings( $in, $node )->pl_manage );
lm_is( 'saving the module re-cleans the change set and stamps the real author', array( $out['trash'], $out['by'] ), array( array( 1 ), get_current_user_id() ) );
lm_is( 'a change set with no post type is emptied on save', DS_Loop_Manager::on_save_settings( (object) array( 'pl_manage' => 'dsm1:e30=' ), $node )->pl_manage, '' );
lm_is( 'number: infinity refused', DS_Loop_Manager::clean_field( '1e999', $f( 'number' ) ), null );
lm_is( 'date: only Ymd', array( DS_Loop_Manager::clean_field( '20260927', $f( 'date_picker' ) ), DS_Loop_Manager::clean_field( 'next week', $f( 'date_picker' ) ) ), array( '20260927', null ) );
lm_is( 'an array where text belongs is refused, not a PHP warning', DS_Loop_Manager::clean_field( array( 'a' ), $f( 'text' ) ), null );
lm_is( 'rich text: a schedule table and heading survive', DS_Loop_Manager::clean_field( '<h3>Coach</h3><table><tr><td>Jo</td><td>#7</td></tr></table><script>x()</script>', $f( 'wysiwyg' ) ), '<h3>Coach</h3><table><tr><td>Jo</td><td>#7</td></tr></table>' );

/* ---- apply / publish, on temporary posts only (created here, deleted at the end) ---- */
$admin = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
wp_set_current_user( (int) $admin[0] );
$tmp   = array();
$mk    = function ( $title, $order = 0 ) use ( &$tmp ) { $id = wp_insert_post( array( 'post_type' => 'staff', 'post_status' => 'publish', 'post_title' => $title, 'menu_order' => $order ) ); $tmp[] = $id; return $id; };
$a1 = $mk( 'LM test A', 1 ); $a2 = $mk( 'LM test B', 2 );
$loop = function ( $extra = array() ) { return (object) array_merge( array( 'type' => 'ds-post-loop', 'post_type' => 'staff', 'order_by' => 'menu_order', 'order' => 'ASC' ), $extra ); };

$deny = function ( $caps ) { foreach ( array( 'edit_posts', 'edit_others_posts', 'edit_published_posts', 'delete_posts', 'delete_others_posts', 'delete_published_posts', 'publish_posts', 'edit_post', 'delete_post' ) as $k ) { $caps[ $k ] = false; } return $caps; };
add_filter( 'user_has_cap', $deny );
$r = DS_Loop_Manager::apply( array( 'pt' => 'staff', 'items' => array( (string) $a1 => array( 'title' => 'HACKED', 'status' => 'draft' ), 'n1' => array( 'title' => 'Injected' ) ), 'trash' => array( $a2 ), 'order' => array( (string) $a2, (string) $a1 ) ), $loop() );
remove_filter( 'user_has_cap', $deny );
lm_is( 'without edit rights: every change skipped', array( $r['updated'], $r['created'], $r['trashed'], $r['reordered'] ), array( 0, 0, 0, 0 ) );
lm_is( 'without edit rights: the post is untouched', array( get_the_title( $a1 ), get_post_status( $a1 ), get_post_status( $a2 ) ), array( 'LM test A', 'publish', 'publish' ) );

$sub = wp_insert_user( array( 'user_login' => 'lm_test_' . wp_generate_password( 6, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$r   = DS_Loop_Manager::apply( array( 'pt' => 'staff', 'by' => $sub, 'items' => array( (string) $a1 => array( 'title' => 'By a subscriber' ) ), 'trash' => array( $a2 ) ), $loop() );
lm_is( 'changes written by a user without rights are refused even when an admin publishes', array( $r['updated'], $r['trashed'], get_the_title( $a1 ) ), array( 0, 0, 'LM test A' ) );
wp_delete_user( $sub );

$r = DS_Loop_Manager::apply( array( 'pt' => 'athlete', 'items' => array( (string) $a1 => array( 'title' => 'Wrong type' ) ) ), $loop() );
lm_is( 'a change set for another post type than the loop shows is ignored', array( $r['updated'], get_the_title( $a1 ) ), array( 0, 'LM test A' ) );
$r = DS_Loop_Manager::apply( array( 'pt' => 'staff', 'items' => array( (string) get_option( 'page_on_front' ) => array( 'title' => 'HACKED' ) ) ), $loop() );
lm_is( 'an ID of another post type is skipped', array( $r['updated'], $r['skipped'], get_the_title( get_option( 'page_on_front' ) ) !== 'HACKED' ), array( 0, 1, true ) );
$r = DS_Loop_Manager::apply( array( 'pt' => 'staff', 'order' => array( (string) $a2, (string) $a1 ) ), $loop( array( 'order_by' => 'date' ) ) );
lm_is( 'a date-sorted loop does not renumber menu_order', array( $r['reordered'], (int) get_post_field( 'menu_order', $a1 ), (int) get_post_field( 'menu_order', $a2 ) ), array( 0, 1, 2 ) );
$r = DS_Loop_Manager::apply( array( 'pt' => 'staff', 'order' => array( (string) $a2, (string) $a1 ) ), $loop() );
lm_is( 'a menu_order loop takes the list order', array( (int) get_post_field( 'menu_order', $a2 ), (int) get_post_field( 'menu_order', $a1 ) ), array( 1, 2 ) );
$tax = get_object_taxonomies( 'staff' );
if ( $tax ) {
	$t0 = wp_insert_term( 'LM test term ' . wp_generate_password( 4, false ), $tax[0] );
	if ( ! is_wp_error( $t0 ) ) {
		$tid = (int) $t0['term_id'];
		wp_set_object_terms( $a1, array( $tid ), $tax[0] );
		$hide = function ( $terms, $taxes, $args ) use ( $tid ) { return is_array( $terms ) ? array_values( array_filter( $terms, function ( $t ) use ( $tid ) { return ! is_object( $t ) || (int) $t->term_id !== $tid; } ) ) : $terms; };
		add_filter( 'get_terms', $hide, 10, 3 ); // as if it were term 201+, not listed in the panel
		DS_Loop_Manager::apply( array( 'pt' => 'staff', 'items' => array( (string) $a1 => array( 'terms' => array( $tax[0] => array() ) ) ) ), $loop() );
		remove_filter( 'get_terms', $hide, 10 );
		lm_is( 'a term the panel did not list is kept', wp_get_object_terms( $a1, $tax[0], array( 'fields' => 'ids' ) ), array( $tid ) );
		wp_delete_term( $tid, $tax[0] );
	}
}

// Publish: a real page with a loop, an HTML module full of backslashes, and a revision.
$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'LM test page (temporary)' ) ); $tmp[] = $page;
$html = (object) array( 'node' => 'h1', 'type' => 'module', 'parent' => null, 'position' => 1, 'settings' => (object) array( 'type' => 'html', 'html' => '<style>.q:before{content:"\201C"}</style><script>/\d+/.test("1")</script>' ) );
$pl   = DS_Loop_Manager::encode( array( 'pt' => 'staff', 't' => 1, 'items' => array( (string) $a1 => array( 'title' => 'Published name' ) ) ) );
$lm   = (object) array( 'node' => 'l1', 'type' => 'module', 'parent' => null, 'position' => 0, 'settings' => $loop( array( 'pl_manage' => $pl ) ) );
$data = array( 'l1' => $lm, 'h1' => $html );
foreach ( array( 'published', 'draft' ) as $st ) { FLBuilderModel::update_layout_data( $data, $st, $page ); }
DS_Loop_Manager::on_publish( $page, true, $data, array() );
$pub = get_post_meta( $page, '_fl_builder_data', true ); $drf = get_post_meta( $page, '_fl_builder_draft', true );
lm_is( 'publish writes the change', get_the_title( $a1 ), 'Published name' );
lm_is( 'publish clears the change set from the live and draft layout', array( $pub['l1']->settings->pl_manage, $drf['l1']->settings->pl_manage ), array( '', '' ) );
lm_is( 'every other module keeps its backslashes', array( $pub['h1']->settings->html, $drf['h1']->settings->html ), array( $html->settings->html, $html->settings->html ) );
wp_update_post( array( 'ID' => $a1, 'post_title' => 'Renamed in the dashboard' ) );
DS_Loop_Manager::on_publish( $page, true, $data, array() ); // the same change set again (restored revision, undo, duplicate)
lm_is( 'the same change set is never applied twice', get_the_title( $a1 ), 'Renamed in the dashboard' );
$dup = array( 'l1' => $lm, 'l2' => (object) array_merge( (array) $lm, array( 'node' => 'l2' ) ) );
$pl2 = DS_Loop_Manager::encode( array( 'pt' => 'staff', 't' => 2, 'items' => array( 'n1' => array( '_new' => 1, 'title' => 'LM test dup' ) ) ) );
$dup['l1'] = clone $lm; $dup['l1']->settings = clone $lm->settings; $dup['l1']->settings->pl_manage = $pl2;
$dup['l2'] = clone $dup['l1']; $dup['l2']->node = 'l2';
DS_Loop_Manager::on_publish( $page, true, $dup, array() );
$made = get_posts( array( 'post_type' => 'staff', 'title' => 'LM test dup', 'post_status' => 'any', 'fields' => 'ids' ) );
$tmp  = array_merge( $tmp, $made );
lm_is( 'a duplicated module creates its new entry once', count( $made ), 1 );
DS_Loop_Manager::on_publish( $page, false, array( 'l1' => (object) array( 'type' => 'module', 'settings' => $loop( array( 'pl_manage' => DS_Loop_Manager::encode( array( 'pt' => 'staff', 't' => 3, 'items' => array( (string) $a1 => array( 'title' => 'Draft save' ) ) ) ) ) ) ) ), array() );
lm_is( 'a draft save applies nothing', get_the_title( $a1 ), 'Renamed in the dashboard' );

/* ---- preview: only entries of the loop's type that the editor may edit ---- */
$front = (int) get_option( 'page_on_front' );
add_filter( 'fl_builder_model_is_builder_active', '__return_true' );
$ok = DS_Loop_Manager::begin_preview( $loop( array( 'pl_manage' => DS_Loop_Manager::encode( array( 'pt' => 'staff', 'items' => array( (string) $front => array( 'status' => 'publish' ), (string) $a2 => array( 'status' => 'publish', 'title' => '<img src=x onerror=alert(1)>Shown' ) ) ) ) ) ) );
$q  = new WP_Query(); $q->set( 'ds_loop_preview', 1 );
$ids = $ok ? wp_list_pluck( DS_Loop_Manager::pv_posts( array(), $q ), 'ID' ) : array();
$ttl = $ok ? DS_Loop_Manager::pv_title( 'x', $a2 ) : '';
DS_Loop_Manager::end_preview();
remove_filter( 'fl_builder_model_is_builder_active', '__return_true' );
lm_is( 'preview pulls in no post of another type', array( $ok, in_array( $front, $ids, true ), in_array( $a2, $ids, true ) ), array( true, false, true ) );
lm_is( 'preview titles are cleaned like saved ones', $ttl, 'Shown' );
lm_is( 'preview state is gone afterwards', DS_Loop_Manager::previewing(), false );

/* ---- tidy up ---- */
foreach ( array_unique( $tmp ) as $id ) { wp_delete_post( $id, true ); }
$applied = get_option( DS_Loop_Manager::APPLIED, array() );
foreach ( array( $pl, $pl2 ) as $raw ) { unset( $applied[ md5( $raw ) ] ); }
update_option( DS_Loop_Manager::APPLIED, $applied, false );
lm_is( 'temporary posts removed', count( array_filter( array_map( 'get_post', array_unique( $tmp ) ) ) ), 0 );

echo $GLOBALS['lm_fail'] ? "FAILURES: {$GLOBALS['lm_fail']} of {$GLOBALS['lm_n']}\n" : "ALL {$GLOBALS['lm_n']} PASS\n";
if ( $GLOBALS['lm_fail'] ) { exit( 1 ); }
