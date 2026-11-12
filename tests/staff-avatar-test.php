<?php
if ( 'cli' !== PHP_SAPI ) { exit; } // a WP-CLI / php script: does nothing over HTTP
/**
 * Photo Style "Small circle" (1.16.0) on the Post Loop (Staff: Cards) and the Team Detail coaches: a small
 * round photo for small or low-quality headshots, initials for anyone without a photo, and full-card
 * grids that are unchanged. Renders modules in memory with Beaver Builder (nothing is saved). Needs at
 * least one published Staff entry and one published team with coaches:
 *   wp eval-file tests/staff-avatar-test.php
 *   @requires wordpress (tests/run.sh runs it only with --wp)
 * Exit code 1 on any failure.
 */
if ( ! class_exists( 'FLBuilder' ) || ! class_exists( 'DS_Post_Loop_Module' ) ) { echo "Beaver Builder or the Post Loop module is not active.\n"; exit( 1 ); }

$GLOBALS['sav_fail'] = 0; $GLOBALS['sav_n'] = 0;
function sav_is( $label, $got, $want ) {
	$GLOBALS['sav_n']++;
	$ok = $got === $want;
	if ( ! $ok ) { $GLOBALS['sav_fail']++; }
	echo ( $ok ? 'PASS ' : 'FAIL ' ) . $label . ( $ok ? '' : ' got=' . var_export( $got, true ) . ' want=' . var_export( $want, true ) ) . "\n";
}

// Initials (private helper).
$ini = method_exists( 'DS_Card', 'initials' );
sav_is( 'initials helper exists (DS_Card::initials)', $ini, true );
foreach ( $ini ? array(
	'Stephen J. Fenton'      => 'SF',
	'Cher'                   => 'C',
	'ana-maría lópez'        => 'AL',
	'José Ñúñez'             => 'JÑ',
	'Tom O&#8217;Brien'      => 'TO',
	'(Coach) Mike Trzaska'   => 'MT',
	'  '                     => '',
	''                       => '',
) : array() as $in => $want ) {
	sav_is( 'initials of "' . $in . '"', DS_Card::initials( $in ), $want );
}

$staff = get_posts( array( 'post_type' => 'staff', 'post_status' => 'publish', 'numberposts' => 6, 'fields' => 'ids' ) );
if ( ! $staff ) { echo "No published Staff entries to render.\n"; exit( 1 ); }
$n = count( $staff );

$base = array( 'card_layout' => 'staff_card', 'source' => 'custom', 'post_type' => 'staff', 'posts_per_page' => $n, 'show_header' => 'no' );
$render = function ( array $over ) use ( $base ) {
	$s = array_merge( $base, $over );
	return FLBuilder::render_module_html( 'ds-post-loop', (object) $s, null, 'sav' . substr( md5( serialize( $s ) ), 0, 8 ), false );
};
$css = function ( array $over ) use ( $base ) {
	$s = array_merge( $base, $over );
	ob_start(); echo FLBuilder::render_module_css( 'ds-post-loop', 'savcss', (object) $s ); return ob_get_clean();
};
$count = function ( $html, $class ) { return preg_match_all( '/class="[^"]*\b' . preg_quote( $class, '/' ) . '\b[^"]*"/', $html ); };

// Full card (the default) is unchanged: photo block on top, no circle anywhere.
$h = $render( array() );
sav_is( 'default: one card per entry', $count( $h, 'ds-people-card' ), $n );
sav_is( 'default: one full photo per card', $count( $h, 'ds-people-photo' ), $n );
sav_is( 'default: no circle', $count( $h, 'ds-people-avatar' ), 0 );
sav_is( 'default equals an explicit Full card', $render( array( 'staff_photo_style' => 'card' ) ) === $render( array() ), true );
sav_is( 'default CSS has no circle size', false !== strpos( $css( array() ), '--ds-people-avatar:' ), false );

// Small circle: every card gets one circle and no full photo.
$h = $render( array( 'staff_photo_style' => 'avatar' ) );
sav_is( 'circle: one circle per card', $count( $h, 'ds-people-avatar' ), $n );
sav_is( 'circle: no full photo', $count( $h, 'ds-people-photo' ), 0 );
sav_is( 'circle: every card marked --avatar', $count( $h, 'ds-people-card--avatar' ), $n );
sav_is( 'circle: centred by default (no --side)', $count( $h, 'ds-people-card--side' ), 0 );
sav_is( 'circle side by side: every card marked --side', $count( $render( array( 'staff_photo_style' => 'avatar', 'staff_avatar_layout' => 'side' ) ), 'ds-people-card--side' ), $n );

// Show Photo = No still wins over the style.
$h = $render( array( 'staff_photo_style' => 'avatar', 'staff_show_photo' => 'no' ) );
sav_is( 'names only beats Small circle: no circle', $count( $h, 'ds-people-avatar' ), 0 );
sav_is( 'names only beats Small circle: --nophoto cards', $count( $h, 'ds-people-card--nophoto' ), $n );

// Nobody has a photo: initials, never the placeholder social card.
add_filter( 'post_thumbnail_id', '__return_zero', 99 );
$h = $render( array( 'staff_photo_style' => 'avatar' ) );
remove_filter( 'post_thumbnail_id', '__return_zero', 99 );
sav_is( 'no photo: initials in every circle', $count( $h, 'ds-people-avatar--initials' ), $n );
preg_match_all( '#ds-people-avatar--initials" aria-hidden="true">([^<]*)</div>#u', $h, $m );
sav_is( 'no photo: initials hidden from screen readers', count( $m[1] ), $n );
sav_is( 'no photo: each circle holds one or two capital letters', count( preg_grep( '/^\p{Lu}{1,2}$/u', $m[1] ) ), $n );
sav_is( 'no photo: no background image on the circle', preg_match( '/ds-people-avatar[^>]*background-image/', $h ), 0 );

// CSS: size, clamp and initials colours, only for Small circle.
$c = $css( array( 'staff_photo_style' => 'avatar', 'staff_avatar_size' => '120' ) );
sav_is( 'CSS: Circle Size 120 sets the variable', false !== strpos( $c, '--ds-people-avatar: 120px' ), true );
sav_is( 'CSS: Circle Size is clamped to 200', false !== strpos( $css( array( 'staff_photo_style' => 'avatar', 'staff_avatar_size' => '999' ) ), '--ds-people-avatar: 200px' ), true );
$c = $css( array( 'staff_photo_style' => 'avatar', 'staff_initials_bg' => '123456', 'staff_initials_color' => 'fafafa' ) );
sav_is( 'CSS: Initials Background prints', false !== strpos( $c, '.ds-people-avatar--initials { background-color: #123456; }' ), true );
sav_is( 'CSS: Initials Colour prints', false !== strpos( $c, '.ds-people-avatar--initials { color: #fafafa; }' ), true );
sav_is( 'CSS: names only prints no circle size', false !== strpos( $css( array( 'staff_photo_style' => 'avatar', 'staff_show_photo' => 'no' ) ), '--ds-people-avatar:' ), false );

// Circle Layout per device: phones only switch inside the phone breakpoint; same value = no media rule.
list( $bpm, $bpr ) = DS_Module_UI::breakpoints();
$c = $css( array( 'staff_photo_style' => 'avatar', 'staff_avatar_layout_responsive' => 'side' ) );
sav_is( 'CSS: side by side on phones only prints a phone rule', false !== strpos( $c, "@media (max-width:{$bpr}px){ .fl-node-savcss .ds-people-card--avatar { flex-direction: row;" ), true );
sav_is( 'CSS: ...and no tablet rule', false !== strpos( $c, "@media (max-width:{$bpm}px){ .fl-node-savcss .ds-people-card--avatar" ), false );
$c = $css( array( 'staff_photo_style' => 'avatar', 'staff_avatar_layout' => 'side', 'staff_avatar_layout_responsive' => 'side' ) );
sav_is( 'CSS: side everywhere needs no media rule', false !== strpos( $c, '.ds-people-card--avatar { flex-direction' ), false );
$c = $css( array( 'staff_photo_style' => 'avatar', 'staff_avatar_layout' => 'side', 'staff_avatar_layout_medium' => 'stack' ) );
sav_is( 'CSS: side on desktop, centred from tablet down', false !== strpos( $c, "@media (max-width:{$bpm}px){ .fl-node-savcss .ds-people-card--avatar { flex-direction: column;" ), true );

// Team Detail (team pages): the same Photo Style, rendered against a team that has published coaches.
$team = 0; $coaches = array();
foreach ( get_posts( array( 'post_type' => 'teams', 'post_status' => 'publish', 'numberposts' => 50, 'fields' => 'ids' ) ) as $tid ) {
	$rel = get_post_meta( $tid, 'team_coach', true );
	$rel = array_values( array_filter( array_map( 'intval', (array) maybe_unserialize( $rel ) ), function ( $i ) { return $i && 'publish' === get_post_status( $i ); } ) );
	if ( $rel ) { $team = $tid; $coaches = $rel; break; }
}
if ( ! $team || ! class_exists( 'DS_Team_Detail_Module' ) ) {
	sav_is( 'Team Detail: a published team with coaches to render', false, true );
} else {
	global $post;
	$post = get_post( $team ); setup_postdata( $post );
	$td = function ( array $over ) {
		return FLBuilder::render_module_html( 'ds-team-detail', (object) $over, null, 'savtd' . substr( md5( serialize( $over ) ), 0, 8 ), false );
	};
	$k  = count( $coaches );
	$h  = $td( array() );
	sav_is( 'Team Detail default: full photo per coach', $count( $h, 'ds-people-photo' ), $k );
	sav_is( 'Team Detail default: no circle', $count( $h, 'ds-people-avatar' ), 0 );
	sav_is( 'Team Detail default equals an explicit Full card', $td( array( 'coach_photo_style' => 'card' ) ) === $h, true );
	$h  = $td( array( 'coach_photo_style' => 'avatar' ) );
	sav_is( 'Team Detail circle: one circle per coach', $count( $h, 'ds-people-avatar' ), $k );
	sav_is( 'Team Detail circle: no full photo', $count( $h, 'ds-people-photo' ), 0 );
	sav_is( 'Team Detail circle: circle sits inside the card body', preg_match_all( '/class="ds-people-body"><div class="ds-people-avatar/', $h ), $k );
	add_filter( 'post_thumbnail_id', '__return_zero', 99 );
	sav_is( 'Team Detail no photo: initials', $count( $td( array( 'coach_photo_style' => 'avatar' ) ), 'ds-people-avatar--initials' ), $k );
	remove_filter( 'post_thumbnail_id', '__return_zero', 99 );
	ob_start(); echo FLBuilder::render_module_css( 'ds-team-detail', 'savtdcss', (object) array( 'coach_photo_style' => 'avatar', 'coach_avatar_size' => '80', 'coach_photo_bg' => '0a2240' ) ); $c = ob_get_clean();
	sav_is( 'Team Detail CSS: Circle Size', false !== strpos( $c, '--ds-people-avatar:80px' ), true );
	sav_is( 'Team Detail CSS: placeholder colour behind the initials', false !== strpos( $c, '.ds-people-avatar--initials{background-color:#0a2240;}' ), true );
	wp_reset_postdata();
}

// Settings form: the new field defaults to Full card and Show Photo reveals it.
$d = FLBuilderModel::get_module_defaults( 'ds-post-loop' );
sav_is( 'form: Photo Style defaults to Full card', $d->staff_photo_style ?? null, 'card' );
sav_is( 'form: Circle Size defaults to 96', $d->staff_avatar_size ?? null, '96' );
$d = FLBuilderModel::get_module_defaults( 'ds-team-detail' );
sav_is( 'form: Team Detail Photo Style defaults to Full card', $d->coach_photo_style ?? null, 'card' );

echo $GLOBALS['sav_n'] . ' checks, ' . $GLOBALS['sav_fail'] . " failed\n";
exit( $GLOBALS['sav_fail'] ? 1 : 0 );
