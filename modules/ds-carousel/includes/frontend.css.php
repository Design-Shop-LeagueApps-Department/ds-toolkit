<?php
/**
 * Leagueapps Image Carousel — dynamic CSS. $module, $settings, $id in scope.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$node  = ".fl-node-$id";
$style = isset( $settings->carousel_style ) ? preg_replace( '/[^a-z0-9_]/', '', $settings->carousel_style ) : 'style1';

$col = array( 'DS_Module_UI', 'color' );
$u = array( 'DS_Module_UI', 'u' );

$g   = FLBuilderModel::get_global_settings();
list( $bpm, $bpr ) = DS_Module_UI::breakpoints();

/* ---- Section background (shared) ---- */
$sbg = $col( $settings->section_bg ?? '' );
if ( '' !== $sbg ) { echo "$node .ds-carousel { background: {$sbg}; }\n"; }

if ( 'style3' === $style ) {
	/* ============================ Style 3: reels strip ============================ */
	$cols  = $u( $settings->reel_cols ?? '', 5 );
	$colsM = $u( $settings->reel_cols_medium ?? '', min( $cols, 3 ) );
	$colsR = $u( $settings->reel_cols_responsive ?? '', 2 );
	$gap   = $u( $settings->reel_gap ?? '', 24 );
	echo "$node .ds-reels-track { --ds-reel-cols: {$cols}; --ds-reel-gap: {$gap}px; }\n";
	echo "@media (max-width:{$bpm}px){ $node .ds-reels-track { --ds-reel-cols: {$colsM}; } }\n";
	echo "@media (max-width:{$bpr}px){ $node .ds-reels-track { --ds-reel-cols: {$colsR}; } }\n";

	$ra = preg_replace( '/[^0-9 \/]/', '', (string) ( $settings->reel_aspect ?? '9 / 16' ) ) ?: '9 / 16';
	$rr = ( isset( $settings->reel_radius ) && '' !== $settings->reel_radius ) ? ( (int) $settings->reel_radius ) . 'px' : 'var(--ds-radius)';
	echo "$node .ds-reel-card { aspect-ratio: {$ra}; border-radius: {$rr}; }\n";

	$pbg = $col( $settings->reel_play_bg ?? '' );
	$pc  = $col( $settings->reel_play_color ?? '' );
	if ( '' !== $pbg ) { echo "$node .ds-reel-play { background: {$pbg}; }\n"; }
	if ( '' !== $pc )  { echo "$node .ds-reel-play { color: {$pc}; }\n"; }

} elseif ( 'style2' === $style ) {
	/* ============================ Style 2: Sponsors / logos ============================ */
	$cols = $u( $settings->columns ?? '', 6 );
	$gap  = $u( $settings->gap ?? '', 20 );
	$lh   = $u( $settings->logo_height ?? '', 120 );
	$tp   = $u( $settings->tile_padding ?? '', 22 );
	echo "$node .ds-carousel--style2 { --ds-cols: {$cols}; --ds-gap: {$gap}px; --ds-logo-h: {$lh}px; --ds-tile-pad: {$tp}px; }\n";

	// Responsive column/gap/height/padding (defaults step down: tablet cols=min(cols,3), mobile=min(cols,2)).
	$colsM = $u( $settings->columns_medium ?? '', min( $cols, 3 ) );
	$gapM  = $u( $settings->gap_medium ?? '', $gap );
	$lhM   = $u( $settings->logo_height_medium ?? '', $lh );
	$tpM   = $u( $settings->tile_padding_medium ?? '', $tp );
	echo "@media (max-width:{$bpm}px){ $node .ds-carousel--style2 { --ds-cols: {$colsM}; --ds-gap: {$gapM}px; --ds-logo-h: {$lhM}px; --ds-tile-pad: {$tpM}px; } }\n";
	$colsR = $u( $settings->columns_responsive ?? '', min( $cols, 2 ) );
	$gapR  = $u( $settings->gap_responsive ?? '', $gap );
	$lhR   = $u( $settings->logo_height_responsive ?? '', $lh );
	$tpR   = $u( $settings->tile_padding_responsive ?? '', $tp );
	echo "@media (max-width:{$bpr}px){ $node .ds-carousel--style2 { --ds-cols: {$colsR}; --ds-gap: {$gapR}px; --ds-logo-h: {$lhR}px; --ds-tile-pad: {$tpR}px; } }\n";

	// Tile background / border / radius.
	$tbg = $col( $settings->tile_bg ?? '' );
	if ( '' !== $tbg ) { echo "$node .ds-logos-item { background: {$tbg}; }\n"; }
	$tbgh = $col( $settings->tile_hover_bg ?? '' );
	if ( '' !== $tbgh ) { echo "$node .ds-logos-item:hover { background: {$tbgh}; }\n"; }
	$tbw = $u( $settings->tile_border_width ?? '', 1 );
	$tbc = $col( $settings->tile_border_color ?? '' );
	echo "$node .ds-logos-item { border-width: {$tbw}px; border-style: solid;" . ( '' !== $tbc ? " border-color: {$tbc};" : '' ) . " }\n";
	$tbch = $col( $settings->tile_border_hover_color ?? '' );
	if ( '' !== $tbch ) { echo "$node .ds-logos-item:hover { border-color: {$tbch}; }\n"; }
	$trad = ( isset( $settings->tile_radius ) && '' !== $settings->tile_radius ) ? ( (int) $settings->tile_radius ) . 'px' : 'var(--ds-radius)';
	echo "$node .ds-logos-item { border-radius: {$trad}; }\n";
	if ( ( $settings->tile_hover_lift ?? 'yes' ) !== 'yes' ) {
		echo "$node a.ds-logos-item:hover { transform: none; box-shadow: none; }\n";
	}

	// Logo max-height (keeps mixed sizes balanced; aspect ratio always preserved).
	$lmh = $u( $settings->logo_max_height ?? '', 64 );
	echo "$node .ds-logos-logo { max-height: {$lmh}px; }\n";
	if ( '' !== ( $settings->logo_max_height_medium ?? '' ) ) { echo "@media (max-width:{$bpm}px){ $node .ds-logos-logo { max-height: " . (int) $settings->logo_max_height_medium . "px; } }\n"; }
	if ( '' !== ( $settings->logo_max_height_responsive ?? '' ) ) { echo "@media (max-width:{$bpr}px){ $node .ds-logos-logo { max-height: " . (int) $settings->logo_max_height_responsive . "px; } }\n"; }

	// Header label + accent line colours.
	$hc = $col( $settings->sl_head_color ?? '' );
	if ( '' !== $hc ) { echo "$node .ds-logos-head-label { color: {$hc}; }\n"; }
	$hlc = $col( $settings->sl_head_line_color ?? '' );
	if ( '' !== $hlc ) { echo "$node .ds-logos-head-line { background: {$hlc}; }\n"; }

	// Header typography (deferred).
	if ( class_exists( 'FLBuilderCSS' ) && ! empty( $settings->sl_head_typography ) ) {
		FLBuilderCSS::typography_field_rule( array( 'settings' => $settings, 'setting_name' => 'sl_head_typography', 'selector' => "$node .ds-logos-head-label" ) );
	}
} elseif ( 'style4' === $style ) {
	/* ============================ Style 4: overlapping images ============================ */
	$ovr = $u( $settings->ov_radius ?? '', 8 );
	echo "$node .ds-overlap-primary, $node .ds-overlap-secondary { border-radius: {$ovr}px; }\n";

	$obw = $u( $settings->ov_border_width ?? '', 0 );
	if ( $obw > 0 ) {
		$obc = DS_Module_UI::colf( $settings->ov_border_color ?? '', 'var(--fl-global-white)' );
		echo "$node .ds-overlap-secondary { border: {$obw}px solid {$obc}; }\n";
	}

	$shadows = array(
		'soft'   => '0 6px 18px rgba(0,0,0,.14)',
		'medium' => '0 10px 28px rgba(0,0,0,.22)',
		'strong' => '0 16px 44px rgba(0,0,0,.32)',
	);
	$osh = (string) ( $settings->ov_shadow ?? 'soft' );
	if ( isset( $shadows[ $osh ] ) ) {
		echo "$node .ds-overlap-secondary { box-shadow: {$shadows[ $osh ]}; }\n";
	}

	$omw = $u( $settings->ov_max_width ?? '', 0 );
	if ( $omw > 0 ) {
		echo "$node .ds-overlap { max-width: {$omw}px; margin-left: auto; margin-right: auto; }\n";
	}
} else {

/* ---- Card sizing + aspect + radius ---- */
$mw  = $u( $settings->max_width ?? '', 540 );
echo "$node .ds-carousel-stage { max-width: {$mw}px; }\n";

/* Height: a preset / custom FIXED height beats the aspect ratio; blank = aspect (original). */
$dh_preset = (string) ( $settings->deck_height ?? '' );
$dh_map    = array( 'small' => 300, 'medium' => 420, 'tall' => 560 );
$dh        = $dh_map[ $dh_preset ] ?? 0;
if ( 'custom' === $dh_preset ) { $dh = $u( $settings->deck_height_custom ?? '', 420 ); }
if ( $dh > 0 ) {
	echo "$node .ds-carousel-framewrap { height: {$dh}px; aspect-ratio: auto; }\n";
	if ( 'custom' === $dh_preset ) {
		if ( '' !== ( $settings->deck_height_custom_medium ?? '' ) )     { echo "@media (max-width:{$bpm}px){ $node .ds-carousel-framewrap { height: " . (int) $settings->deck_height_custom_medium . "px; } }\n"; }
		if ( '' !== ( $settings->deck_height_custom_responsive ?? '' ) ) { echo "@media (max-width:{$bpr}px){ $node .ds-carousel-framewrap { height: " . (int) $settings->deck_height_custom_responsive . "px; } }\n"; }
	}
} else {
	$ar = preg_replace( '/[^0-9 \/]/', '', (string) ( $settings->aspect ?? '4 / 5' ) ) ?: '4 / 5';
	echo "$node .ds-carousel-framewrap { aspect-ratio: {$ar}; }\n";
}

$crad = ( isset( $settings->card_radius ) && '' !== $settings->card_radius ) ? ( (int) $settings->card_radius ) . 'px' : 'var(--ds-radius)';
echo "$node .ds-carousel-slide { border-radius: {$crad}; }\n";

/* ---- Transition speed ---- */
$speed = max( 100, $u( $settings->speed ?? '', 600 ) );
echo "$node .ds-carousel-slide { transition: transform {$speed}ms cubic-bezier(.22,.61,.36,1), opacity {$speed}ms ease; }\n";

/* ---- Frame bracket (offset / thickness / colour) ---- */
if ( ( $settings->frame_show ?? 'yes' ) === 'yes' ) {
	$foff = $u( $settings->frame_offset ?? '', 18 );
	$fw   = $u( $settings->frame_width ?? '', 2 );
	$fc   = $col( $settings->frame_color ?? '' ) ?: 'var(--fl-global-line-color)';
	// Peek-left stacks read better with the frame offset the other way.
	$dir  = $settings->direction ?? 'right';
	$fx   = ( 'left' === $dir ) ? -$foff : $foff;
	echo "$node .ds-carousel-bracket { border: {$fw}px solid {$fc}; transform: translate({$fx}px, -{$foff}px); }\n";
}

/* ---- Caption pill ---- */
$cbg = $col( $settings->caption_bg ?? '' );
if ( '' !== $cbg ) { echo "$node .ds-carousel-caption { background: {$cbg}; }\n"; }
$cco = $col( $settings->caption_color ?? '' );
if ( '' !== $cco ) { echo "$node .ds-carousel-caption { color: {$cco}; }\n"; }
$crd = $u( $settings->caption_radius ?? '', 0 );
echo "$node .ds-carousel-caption { border-radius: {$crd}px; }\n";

// Caption image height (GH #109) — a custom property so the static rule keeps its
// own fallback and the pill still works if this block is ever skipped.
$cih = $u( $settings->caption_image_height ?? '', 22 );
echo "$node .ds-carousel-caption { --ds-cap-img-h: {$cih}px; }\n";
$cimw = $u( $settings->caption_image_max_width ?? '', 90 );
echo "$node .ds-carousel-caption { --ds-cap-img-maxw: {$cimw}px; }\n";
foreach ( array( 'caption_image_height' => array( '--ds-cap-img-h', 22 ), 'caption_image_max_width' => array( '--ds-cap-img-maxw', 90 ) ) as $key => $meta ) {
	list( $prop, $def ) = $meta;
	foreach ( array( 'medium' => $bpm, 'responsive' => $bpr ) as $sfx => $bp ) {
		$v = $settings->{$key . '_' . $sfx} ?? '';
		if ( '' !== $v && null !== $v ) {
			echo "@media (max-width: {$bp}px) { $node .ds-carousel-caption { {$prop}: " . $u( $v, $def ) . "px; } }\n";
		}
	}
}

} // end style1 / non-style2

/* ---- Navigation (arrows + dots) — shared by both styles ---- */
$nav = $col( $settings->nav_color ?? '' ); // base accent fallback

// Arrows: size, icon size, radius, colours, border, position/offset.
$asz  = $u( $settings->arrow_size ?? '', 44 );
$ais  = $u( $settings->arrow_icon_size ?? '', 20 );
$arad = $u( $settings->arrow_radius ?? '', 50 );
$abg  = $col( $settings->arrow_bg ?? '' );
$aclr = $col( $settings->arrow_color ?? '' );
$abw  = $u( $settings->arrow_border_width ?? '', 0 );
$abc  = $col( $settings->arrow_border_color ?? '' );
$aoff = (int) ( ( isset( $settings->arrow_offset ) && '' !== $settings->arrow_offset ) ? $settings->arrow_offset : 6 );
$apos = in_array( $settings->arrow_position ?? 'overlay', array( 'overlay', 'outside' ), true ) ? ( $settings->arrow_position ?? 'overlay' ) : 'overlay';

$abtn = "width:{$asz}px;height:{$asz}px;border-radius:{$arad}px;";
if ( '' !== $abg )  { $abtn .= "background:{$abg};"; }
if ( '' !== $aclr ) { $abtn .= "color:{$aclr};"; }
if ( $abw > 0 )     { $abtn .= "border:{$abw}px solid " . ( '' !== $abc ? $abc : 'transparent' ) . ";"; }
echo "$node .ds-carousel-nav { {$abtn} }\n";
echo "$node .ds-carousel-nav svg { width:{$ais}px; height:{$ais}px; }\n";

$ahbg = $col( $settings->arrow_hover_bg ?? '' );
if ( '' === $ahbg ) { $ahbg = $nav; }
if ( '' !== $ahbg ) { echo "$node .ds-carousel-nav:hover { background:{$ahbg}; }\n"; }
$ahcl = $col( $settings->arrow_hover_color ?? '' );
if ( '' !== $ahcl ) { echo "$node .ds-carousel-nav:hover { color:{$ahcl}; }\n"; }

if ( 'outside' === $apos ) {
	$gapout = max( 0, $aoff );
	echo "$node .ds-carousel-nav--prev { left:-" . ( $asz + $gapout ) . "px; right:auto; }\n";
	echo "$node .ds-carousel-nav--next { right:-" . ( $asz + $gapout ) . "px; left:auto; }\n";
} else {
	echo "$node .ds-carousel-nav--prev { left:{$aoff}px; right:auto; }\n";
	echo "$node .ds-carousel-nav--next { right:{$aoff}px; left:auto; }\n";
}

// Dots: size, gap, colours.
$dsz = $u( $settings->dot_size ?? '', 9 );
$dgp = $u( $settings->dot_gap ?? '', 9 );
echo "$node .ds-carousel-dots { gap:{$dgp}px; }\n";
$dcl = $col( $settings->dot_color ?? '' );
echo "$node .ds-carousel-dot { width:{$dsz}px; height:{$dsz}px;" . ( '' !== $dcl ? " background:{$dcl};" : '' ) . " }\n";
$dac = $col( $settings->dot_active_color ?? '' );
if ( '' === $dac ) { $dac = $nav; }
if ( '' !== $dac ) { echo "$node .ds-carousel-dot.is-active { background:{$dac}; }\n"; }

/* ---- Spacing (deferred) ---- */
if ( class_exists( 'FLBuilderCSS' ) ) {
	FLBuilderCSS::dimension_field_rule( array(
		'settings'     => $settings,
		'setting_name' => 'padding',
		'selector'     => "$node .ds-carousel-wrap",
		'unit'         => 'px',
		'props'        => array( 'padding-top' => 'padding_top', 'padding-right' => 'padding_right', 'padding-bottom' => 'padding_bottom', 'padding-left' => 'padding_left' ),
	) );
	FLBuilderCSS::dimension_field_rule( array(
		'settings'     => $settings,
		'setting_name' => 'margin',
		'selector'     => "$node .ds-carousel",
		'unit'         => 'px',
		'props'        => array( 'margin-top' => 'margin_top', 'margin-right' => 'margin_right', 'margin-bottom' => 'margin_bottom', 'margin-left' => 'margin_left' ),
	) );
}

/* ---- Caption typography (deferred; flushed by FLBuilderCSS::render()) ---- */
if ( class_exists( 'FLBuilderCSS' ) && ! empty( $settings->caption_typography ) ) {
	FLBuilderCSS::typography_field_rule( array( 'settings' => $settings, 'setting_name' => 'caption_typography', 'selector' => "$node .ds-carousel-caption" ) );
}
