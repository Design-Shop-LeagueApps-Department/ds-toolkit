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
// A global ROW saved into the Home category is not a Home template: visitors' global-node lookups must still find it.
$row = wp_insert_post( array( 'post_type' => 'fl-builder-template', 'post_status' => 'publish', 'post_title' => 'HT test global row (temporary)' ) );
wp_set_post_terms( $row, 'row', 'fl-builder-template-type' );
$hc = get_term_by( 'slug', 'home', 'fl-builder-template-category' ); if ( $hc ) { wp_set_object_terms( $row, (int) $hc->term_id, 'fl-builder-template-category' ); }
update_post_meta( $row, '_fl_builder_template_global', true );
add_filter( 'ds_home_templates_hide_in_cli', '__return_true' );
wp_set_current_user( $partner );
$rq = new WP_Query( array( 'post_type' => 'fl-builder-template', 'posts_per_page' => -1, 'fields' => 'ids', 'post_status' => 'any' ) );
$rp = array_map( 'intval', $rq->posts );
remove_filter( 'ds_home_templates_hide_in_cli', '__return_true' );
ht_is( 'a global row in the Home category stays visible to everyone, the Home layout does not', array( in_array( (int) $row, $rp, true ), in_array( (int) $tpl, $rp, true ) ), array( true, false ) );
ht_is( 'a partner can still edit that row, and a WP_Post argument is handled', array( user_can( $partner, 'edit_post', $row ), user_can( $partner, 'edit_post', get_post( $tpl ) ) ), array( true, false ) );
wp_delete_post( $row, true );

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
ht_is( 'hero media detection', array( DS_Home_Templates::hero_has_media( (object) array( 'bg_type' => 'image', 'bg_photo' => '12' ) ), DS_Home_Templates::hero_has_media( (object) array( 'bg_type' => 'image', 'bg_photo' => '' ) ), DS_Home_Templates::hero_has_media( (object) array( 'bg_type' => 'video', 'video_url' => 'https://x.org/a.mp4' ) ), DS_Home_Templates::hero_has_media( (object) array( 'bg_type' => 'slideshow', 'bg_photos' => array() ) ) ), array( true, false, true, false ) );
ht_is( 'the template itself is unchanged', get_post_meta( $tpl, '_fl_builder_data', true ) == $tdata, true );
$ps = get_post_meta( $page, '_fl_builder_data_settings', true );
$ts = FLBuilderModel::get_layout_settings( 'published', $tpl );
ht_is( 'the page keeps its own title and slug settings; layout CSS comes from the template', array( $ps->title, $ps->slug, $ps->css ), array( 'Test page title', 'test-page-slug', (string) ( $ts->css ?? '' ) ) );
ht_is( 'marked as set from the template', (int) get_post_meta( $page, DS_Home_Templates::CURRENT_META, true ), $tpl );
$bk = get_post_meta( $page, DS_Home_Templates::BACKUP_META, true );
ht_is( 'the backup keeps the backslashes of the old layout', $bk['home']['_fl_builder_data']['tsthtmlnode1']->settings->html ?? null, '<style>.q:before{content:"\201C"}</style>' );

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

/* ---- whole-site templates: header, footer and design go with the template ---- */
$mkl = function ( $type, $text, $ts ) {
	$id = wp_insert_post( array( 'post_type' => 'fl-theme-layout', 'post_status' => 'publish', 'post_title' => "HT test $type (temporary)" ) );
	update_post_meta( $id, '_fl_theme_layout_type', $type );
	$l = array( 'r1' => (object) array( 'node' => 'r1', 'type' => 'row', 'parent' => null, 'position' => 0, 'settings' => (object) array( 'type' => 'row' ) ),
		'm1' => (object) array( 'node' => 'm1', 'type' => 'module', 'parent' => 'r1', 'position' => 0, 'settings' => (object) array( 'type' => 'html', 'html' => $text ) ) );
	foreach ( array( 'published', 'draft' ) as $st ) { FLBuilderModel::update_layout_data( unserialize( serialize( $l ) ), $st, $id ); }
	delete_post_meta( $id, '_fl_theme_layout_settings' ); add_post_meta( $id, '_fl_theme_layout_settings', $ts, true );
	return $id;
};
$hdr = $mkl( 'header', 'Header A <style>.a:before{content:"\2014"}</style>', array( 'sticky' => '1', 'shrink' => '1', 'overlay' => '0', 'overlay_bg' => 'transparent' ) );
$ftr = $mkl( 'footer', 'Footer A', array( 'sticky' => '0', 'shrink' => '0', 'overlay' => '0', 'overlay_bg' => 'transparent' ) );
$parts = function () use ( $hdr, $ftr ) { return array( 'header' => $hdr, 'footer' => $ftr ); };
add_filter( 'ds_home_templates_part_ids', $parts );
$styles_before = DS_Home_Templates::capture_styles();
$hdr_a = get_post_meta( $hdr, '_fl_builder_data', true );

$t2 = DS_Home_Templates::save_site( $page, 0, 'HT test template (temporary)' );
ht_is( 'save the site as a new template: a Home template holding home, header, footer and design', array( is_int( $t2 ), DS_Home_Templates::is_home_template( $t2 ), DS_Home_Templates::parts_of( $t2 ) ), array( true, true, array( 'home', 'header', 'footer', 'styles' ) ) );
$bun = get_post_meta( $t2, DS_Home_Templates::BUNDLE_META, true );
ht_is( 'the saved header keeps its backslashes and its sticky / overlay settings', array( $bun['header']['_fl_builder_data']['m1']->settings->html ?? null, $bun['header']['_fl_theme_layout_settings']['sticky'] ?? null ), array( 'Header A <style>.a:before{content:"\2014"}</style>', '1' ) );
ht_is( 'the template\'s own layout is the home page\'s', array_keys( get_post_meta( $t2, '_fl_builder_data', true ) ), array_keys( get_post_meta( $page, '_fl_builder_data', true ) ) );

$page_at_save = array( get_post_meta( $page, '_fl_builder_data', true ), get_post_meta( $page, '_fl_builder_draft', true ), get_post_meta( $page, '_fl_builder_data_settings', true ) );
// The site moves on: the page's HTML module, another header (overlaid), another footer, other design options.
$l = get_post_meta( $page, '_fl_builder_data', true ); $l['tsthtmlnode1']->settings->html = 'Changed later';
foreach ( array( 'published', 'draft' ) as $st ) { FLBuilderModel::update_layout_data( $l, $st, $page ); }
$l = get_post_meta( $hdr, '_fl_builder_data', true ); $l['m1']->settings->html = 'Header B';
foreach ( array( 'published', 'draft' ) as $st ) { FLBuilderModel::update_layout_data( $l, $st, $hdr ); }
update_post_meta( $hdr, '_fl_theme_layout_settings', array( 'sticky' => '0', 'shrink' => '0', 'overlay' => '1', 'overlay_bg' => 'transparent' ) );
$l = get_post_meta( $ftr, '_fl_builder_data', true ); $l['m1']->settings->html = 'Footer B';
foreach ( array( 'published', 'draft' ) as $st ) { FLBuilderModel::update_layout_data( $l, $st, $ftr ); }
$radius_before = get_theme_mod( 'ds-corner-radius', null );
set_theme_mod( 'ds-corner-radius', 33 );
update_option( 'ds_button_style', 'pill-test' );
$site_b = DS_Home_Templates::capture_site( $page );

$err = DS_Home_Templates::apply( $t2, $page );
ht_is( 'apply succeeds', $err, '' );
ht_is( 'apply brings the template\'s header back (text, backslash, sticky, not overlaid)', array( get_post_meta( $hdr, '_fl_builder_data', true )['m1']->settings->html, get_post_meta( $hdr, '_fl_theme_layout_settings', true )['overlay'] ), array( 'Header A <style>.a:before{content:"\2014"}</style>', '0' ) );
ht_is( '...its footer', get_post_meta( $ftr, '_fl_builder_data', true )['m1']->settings->html, 'Footer A' );
ht_is( '...and its design options (corner radius, button style)', array( get_theme_mod( 'ds-corner-radius', null ), get_option( 'ds_button_style', null ) ), array( $styles_before['mods']['ds-corner-radius'], $styles_before['button'] ) );
ht_is( 'the header is byte-identical to when the template was saved', serialize( get_post_meta( $hdr, '_fl_builder_data', true ) ) === serialize( $hdr_a ), true );
ht_is( 'the page (live copy, draft copy, layout settings) is byte-identical to when the template was saved from it', serialize( array( get_post_meta( $page, '_fl_builder_data', true ), get_post_meta( $page, '_fl_builder_draft', true ), get_post_meta( $page, '_fl_builder_data_settings', true ) ) ) === serialize( $page_at_save ), true );

$err = DS_Home_Templates::revert( $page );
ht_is( 'one-click revert succeeds', $err, '' );
ht_is( 'revert brings EVERYTHING back exactly: home, header, footer, design', DS_Home_Templates::capture_site( $page ) == $site_b, true );

// Leave the real design options exactly as they were.
DS_Home_Templates::write_styles( $styles_before );
ht_is( 'the site\'s real design options are as they were before the test', DS_Home_Templates::capture_styles() == $styles_before, true );
remove_filter( 'ds_home_templates_part_ids', $parts );
foreach ( array( $hdr, $ftr, $t2 ) as $id ) { wp_delete_post( $id, true ); }

/* ---- tidy up ---- */
remove_filter( 'ds_home_templates_target', $to_page );
wp_delete_post( $page, true );
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( $tmp_users as $u ) { wp_delete_user( $u ); }
ht_is( 'temporary page and users removed', array( get_post( $page ), array_filter( array_map( 'get_userdata', $tmp_users ) ) ), array( null, array() ) );

echo $GLOBALS['ht_fail'] ? "FAILURES: {$GLOBALS['ht_fail']} of {$GLOBALS['ht_n']}\n" : "ALL {$GLOBALS['ht_n']} PASS\n";
if ( $GLOBALS['ht_fail'] ) { exit( 1 ); }
