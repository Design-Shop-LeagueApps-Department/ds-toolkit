<?php
/**
 * DS_Home_Templates (Theme Setting > Home page): the development-site gate, Home templates hidden from
 * non-LeagueApps users, the hero carried into a new layout, Apply / Revert (backslashes kept, exact
 * restore), and the builder-lock refusal. Needs at least one published Home template. Works on a
 * temporary page (ds_home_templates_target) and temporary users, deleted at the end; the real front page
 * is never written:
 *   wp eval-file tests/home-templates-test.php
 * Exit code 1 on any failure.
 */
$GLOBALS['ht_fail'] = 0; $GLOBALS['ht_n'] = 0;
function ht_is( $label, $got, $want ) {
	$GLOBALS['ht_n']++;
	$ok = $got === $want;
	if ( ! $ok ) { $GLOBALS['ht_fail']++; }
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $ok ? '' : "\n     got:  " . var_export( $got, true ) . "\n     want: " . var_export( $want, true ) ) . "\n";
}
if ( ! class_exists( 'DS_Home_Templates' ) ) { echo "SKIP: DS_Home_Templates not loaded (blueprint 7+ and the feature on)\n"; return; }

/* ---- the gate ---- */
$hosts = array( 'ds-launchpad-7.local' => true, 'goldrush.wpenginepowered.com' => true, 'x.flywheelsites.com' => true, 'localhost' => true, 'goldrushhockey.com' => false, 'www.club.org' => false, 'evil.local.example.com' => false, 'notwpenginepowered.com' => false );
$got = array(); foreach ( $hosts as $h => $w ) { $got[ $h ] = DS_Home_Templates::is_dev_host( $h ); }
ht_is( 'development addresses only (a partner domain, or one merely containing ".local", is not)', $got, $hosts );
$was = get_option( DS_Home_Templates::LAUNCHED );
update_option( DS_Home_Templates::LAUNCHED, 1 );
ht_is( 'marked launched: the picker is off', DS_Home_Templates::available(), false );
if ( false === $was ) { delete_option( DS_Home_Templates::LAUNCHED ); } else { update_option( DS_Home_Templates::LAUNCHED, $was ); }

/* ---- templates, and who can see them ---- */
$tpls = DS_Home_Templates::templates();
if ( ! $tpls ) { echo "SKIP: no Home template on this site\n"; return; }
$tpl  = $tpls[0]['id'];
$other = get_posts( array( 'post_type' => 'fl-builder-template', 'numberposts' => 1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => 'fl-builder-template-category', 'field' => 'slug', 'terms' => 'home', 'operator' => 'NOT IN' ) ) ) );
ht_is( 'a Home template is recognised, another template is not', array( DS_Home_Templates::is_home_template( $tpl ), $other ? DS_Home_Templates::is_home_template( $other[0] ) : false ), array( true, false ) );

$tmp_users = array();
$mkuser = function ( $email, $role ) use ( &$tmp_users ) { $id = wp_insert_user( array( 'user_login' => 'ht_' . wp_generate_password( 6, false ), 'user_email' => $email, 'user_pass' => wp_generate_password(), 'role' => $role ) ); $tmp_users[] = $id; return $id; };
$la      = $mkuser( 'ht-test-' . wp_generate_password( 4, false ) . '@leagueapps.com', 'administrator' );
$partner = $mkuser( 'ht-test-' . wp_generate_password( 4, false ) . '@partner-club.org', 'administrator' );
$see = function ( $uid ) use ( $tpl ) {
	wp_set_current_user( $uid );
	$q = new WP_Query( array( 'post_type' => 'fl-builder-template', 'posts_per_page' => -1, 'fields' => 'ids', 'post_status' => 'any' ) );
	return in_array( $tpl, array_map( 'intval', $q->posts ), true );
};
add_filter( 'ds_home_templates_hide_in_cli', '__return_true' ); // query as a web request would
ht_is( 'a LeagueApps user sees Home templates', $see( $la ), true );
ht_is( 'a partner does not (template lists and the builder\'s Templates panel query this way)', $see( $partner ), false );
ht_is( '...but still sees the other templates', $other ? ( function () use ( $partner, $other ) { wp_set_current_user( $partner ); $q = new WP_Query( array( 'post_type' => 'fl-builder-template', 'posts_per_page' => -1, 'fields' => 'ids', 'post_status' => 'any' ) ); return in_array( (int) $other[0], array_map( 'intval', $q->posts ), true ); } )() : true, true );
remove_filter( 'ds_home_templates_hide_in_cli', '__return_true' );
ht_is( 'a partner cannot open, edit or delete a Home template by ID', array( user_can( $partner, 'edit_post', $tpl ), user_can( $partner, 'delete_post', $tpl ), user_can( $partner, 'read_post', $tpl ) ), array( false, false, false ) );
ht_is( 'a LeagueApps admin can edit it', user_can( $la, 'edit_post', $tpl ), true );
ht_is( 'other templates are untouched for a partner', $other ? user_can( $partner, 'edit_post', $other[0] ) : true, true );

/* ---- apply / revert on a temporary page ---- */
wp_set_current_user( $la );
$page = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Home templates test (temporary)' ) );
$to_page = function () use ( $page ) { return $page; };
add_filter( 'ds_home_templates_target', $to_page );
$hero = (object) array( 'node' => 'tstheronode1', 'type' => 'module', 'parent' => 'tstcolumn001', 'position' => 0, 'settings' => (object) array( 'type' => 'ds-hero', 'heading' => 'Gold Rush {a}Hockey{/a}', 'subtext' => 'Our own words', 'btn1_text' => 'Register', 'btn1_link' => 'https://example.com/register', 'heading_color' => 'ff0000', 'connections' => array( 'heading' => '' ) ) );
$html = (object) array( 'node' => 'tsthtmlnode1', 'type' => 'module', 'parent' => 'tstcolumn001', 'position' => 1, 'settings' => (object) array( 'type' => 'html', 'html' => '<style>.q:before{content:"\201C"}</style>' ) );
$row  = (object) array( 'node' => 'tstrow000001', 'type' => 'row', 'parent' => null, 'position' => 0, 'settings' => (object) array( 'type' => 'row' ) );
$grp  = (object) array( 'node' => 'tstgroup0001', 'type' => 'column-group', 'parent' => 'tstrow000001', 'position' => 0, 'settings' => null );
$col  = (object) array( 'node' => 'tstcolumn001', 'type' => 'column', 'parent' => 'tstgroup0001', 'position' => 0, 'settings' => (object) array( 'type' => 'column' ) );
$mine = array( 'tstrow000001' => $row, 'tstgroup0001' => $grp, 'tstcolumn001' => $col, 'tstheronode1' => $hero, 'tsthtmlnode1' => $html );
foreach ( array( 'published', 'draft' ) as $st ) { FLBuilderModel::update_layout_data( unserialize( serialize( $mine ) ), $st, $page ); }
update_post_meta( $page, '_fl_builder_enabled', true );
foreach ( array( 'published', 'draft' ) as $st ) { FLBuilderModel::update_layout_settings( (object) array( 'css' => '.x{content:"\\2014"}', 'js' => '', 'title' => 'Test page title', 'slug' => 'test-page-slug' ), $st, $page ); }
$before_settings = array( get_post_meta( $page, '_fl_builder_data_settings', true ), get_post_meta( $page, '_fl_builder_draft_settings', true ) );
$before = array( get_post_meta( $page, '_fl_builder_data', true ), get_post_meta( $page, '_fl_builder_draft', true ) );

$tdata = get_post_meta( $tpl, '_fl_builder_data', true );
$err   = DS_Home_Templates::apply( $tpl, $page );
$after = get_post_meta( $page, '_fl_builder_data', true );
$h1    = null; foreach ( $after as $n ) { if ( 'module' === ( $n->type ?? '' ) && 'ds-hero' === ( $n->settings->type ?? '' ) ) { $h1 = $n; break; } }
$t_hero = null; foreach ( $tdata as $n ) { if ( 'module' === ( $n->type ?? '' ) && 'ds-hero' === ( $n->settings->type ?? '' ) ) { $t_hero = $n; break; } }
ht_is( 'apply succeeds', $err, '' );
ht_is( 'the page now has the template layout, node IDs kept', array_keys( $after ), array_keys( $tdata ) );
ht_is( 'the draft matches the published layout', array_keys( get_post_meta( $page, '_fl_builder_draft', true ) ), array_keys( $tdata ) );
if ( $h1 && $t_hero ) {
	ht_is( 'the hero keeps the page\'s heading, text and button', array( $h1->settings->heading, $h1->settings->subtext, $h1->settings->btn1_text, $h1->settings->btn1_link ), array( 'Gold Rush {a}Hockey{/a}', 'Our own words', 'Register', 'https://example.com/register' ) );
	ht_is( 'the hero keeps the template\'s styling (colour not carried)', $h1->settings->heading_color ?? null, $t_hero->settings->heading_color ?? null );
}
ht_is( 'the template itself is unchanged', get_post_meta( $tpl, '_fl_builder_data', true ) == $tdata, true );
$ps = get_post_meta( $page, '_fl_builder_data_settings', true );
$ts = FLBuilderModel::get_layout_settings( 'published', $tpl );
ht_is( 'the page keeps its own title and slug settings; layout CSS comes from the template', array( $ps->title, $ps->slug, $ps->css ), array( 'Test page title', 'test-page-slug', (string) ( $ts->css ?? '' ) ) );
ht_is( 'marked as set from the template', (int) get_post_meta( $page, DS_Home_Templates::CURRENT_META, true ), $tpl );
$bk = get_post_meta( $page, DS_Home_Templates::BACKUP_META, true );
ht_is( 'the backup keeps the backslashes of the old layout', $bk['data']['tsthtmlnode1']->settings->html ?? null, '<style>.q:before{content:"\201C"}</style>' );

$err = DS_Home_Templates::revert( $page );
ht_is( 'revert succeeds', $err, '' );
ht_is( 'revert restores the live and draft layout exactly', array( get_post_meta( $page, '_fl_builder_data', true ), get_post_meta( $page, '_fl_builder_draft', true ) ) == $before, true );
ht_is( 'revert restores the layout settings exactly (CSS backslash kept)', array( get_post_meta( $page, '_fl_builder_data_settings', true ), get_post_meta( $page, '_fl_builder_draft_settings', true ) ) == $before_settings && false !== strpos( get_post_meta( $page, '_fl_builder_data_settings', true )->css, '\\2014' ), true );
ht_is( 'revert removes the backup and the "current" mark', array( get_post_meta( $page, DS_Home_Templates::BACKUP_META, true ), get_post_meta( $page, DS_Home_Templates::CURRENT_META, true ) ), array( '', '' ) );
ht_is( 'nothing to revert twice', DS_Home_Templates::revert( $page ) !== '', true );

// Someone else has the page open in the builder: refuse.
update_post_meta( $page, '_edit_lock', time() . ':' . $partner );
ht_is( 'apply refuses while another user has the page open', false !== strpos( DS_Home_Templates::apply( $tpl, $page ), 'is editing the home page' ), true );
ht_is( '...and writes nothing', get_post_meta( $page, '_fl_builder_data', true ) == $before[0], true );
delete_post_meta( $page, '_edit_lock' );

/* ---- tidy up ---- */
remove_filter( 'ds_home_templates_target', $to_page );
wp_delete_post( $page, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( $tmp_users as $u ) { wp_delete_user( $u ); }
ht_is( 'temporary page and users removed', array( get_post( $page ), array_filter( array_map( 'get_userdata', $tmp_users ) ) ), array( null, array() ) );

echo $GLOBALS['ht_fail'] ? "FAILURES: {$GLOBALS['ht_fail']} of {$GLOBALS['ht_n']}\n" : "ALL {$GLOBALS['ht_n']} PASS\n";
if ( $GLOBALS['ht_fail'] ) { exit( 1 ); }
