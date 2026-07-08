<?php
/** Leagueapps Page Cards — per-instance dynamic CSS. $module, $settings, $id in scope. */
if ( ! defined( 'ABSPATH' ) ) exit;

// Saved-template mode uses the template's own styling; nothing to emit here.
if ( ( $settings->card_source ?? 'default' ) !== 'default' ) { return; }

$node = ".fl-node-$id";
$col = array( 'DS_Module_UI', 'color' );
$u = array( 'DS_Module_UI', 'u' );

// ── Card box ──
$decl = '';
$bg   = $col( $settings->card_bg ?? '' );
if ( '' !== $bg ) { $decl .= "background:$bg;"; }
$rad  = ( isset( $settings->card_radius ) && '' !== $settings->card_radius ) ? ( (int) $settings->card_radius ) . 'px' : 'var(--ds-radius)';
$decl .= "border-radius:{$rad};";
$bw   = $u( $settings->card_border_width ?? '', 1 );
$bc   = $col( $settings->card_border_color ?? '' ) ?: 'rgba(0,0,0,.12)';
$decl .= "border:{$bw}px solid {$bc};";
echo "$node .dst-card{{$decl}}\n";

// ── Body: padding + alignment ──
$pad = $u( $settings->card_padding ?? '', 24 );
$al  = ( ( $settings->card_align ?? 'center' ) === 'left' ) ? 'left' : 'center';
$ai  = ( 'left' === $al ) ? 'flex-start' : 'center';
echo "$node .dst-card-body{padding:{$pad}px;text-align:{$al};align-items:{$ai};}\n";

// ── Image: ratio for Top, width for Left ──
if ( ( $settings->image_position ?? 'top' ) === 'left' ) {
	$iw = $u( $settings->image_width ?? '', 42 );
	$iw = max( 15, min( 70, $iw ) );
	echo "$node .dst-card--imgleft > .dst-card-img{flex-basis:{$iw}%;width:{$iw}%;}\n";
	// Re-emit the mobile stack node-scoped: this node-scoped desktop width rule outranks
	// the static @media(max-width:768px) override, so the image never goes full-width on
	// mobile without an equally-specific node-scoped rule inside the media query.
	echo "@media(max-width:768px){ $node .dst-card--imgleft > .dst-card-img{width:100%;flex-basis:auto;} }\n";
} else {
	$ar = preg_replace( '#[^0-9/ ]#', '', (string) ( $settings->image_ratio ?? '3 / 2' ) );
	if ( '' === trim( $ar ) ) { $ar = '3 / 2'; }
	echo "$node .dst-card-img{aspect-ratio:$ar;}\n";
}

// ── Text colours (blank inherits the theme) ──
if ( $tc = $col( $settings->title_color ?? '' ) )   { echo "$node .dst-card-title{color:$tc;}\n"; }
if ( $ec = $col( $settings->excerpt_color ?? '' ) ) { echo "$node .dst-card-excerpt{color:$ec;}\n"; }

// ---- "Button" link style: follow the Theme Setting global Button (the static CSS only
//      borrowed its colour; radius/typography/hover now sync too). ----
if ( ( $settings->button_style ?? 'link' ) === 'button' && class_exists( 'FLBuilderGlobalStyles' ) ) {
	$gs = FLBuilderGlobalStyles::get_settings( false );
	if ( $gs ) {
		$gbg    = $col( $gs->button_background ?? '' );
		$gtext  = $col( $gs->button_color ?? '' );
		$gbgh   = $col( $gs->button_hover_background ?? '' ) ?: $gbg;
		$gtexth = $col( $gs->button_hover_color ?? '' ) ?: $gtext;
		$gradius = '';
		$bb = isset( $gs->button_border ) ? (array) $gs->button_border : array();
		$r  = isset( $bb['radius'] ) ? (array) $bb['radius'] : array();
		$tl = $r['top_left'] ?? ''; $tr = $r['top_right'] ?? ''; $bl = $r['bottom_left'] ?? ''; $brr = $r['bottom_right'] ?? '';
		if ( '' !== ( $tl . $tr . $bl . $brr ) ) { $ru = function( $v ) { return ( '' === $v ? '0' : (int) $v ) . 'px'; }; $gradius = $ru( $tl ) . ' ' . $ru( $tr ) . ' ' . $ru( $brr ) . ' ' . $ru( $bl ); }
		$p = '';
		if ( $gbg )   { $p .= "background:{$gbg} !important;"; }
		if ( $gtext ) { $p .= "color:{$gtext} !important;"; }
		if ( '' !== $gradius ) { $p .= "border-radius:{$gradius};"; }
		if ( '' !== $p ) { echo "$node .dst-card-btn--button { {$p} }\n"; }
		if ( $gbgh || $gtexth ) { echo "$node .dst-card:hover .dst-card-btn--button { " . ( $gbgh ? "background:{$gbgh} !important;" : '' ) . ( $gtexth ? "color:{$gtexth} !important;" : '' ) . " }\n"; }
		if ( class_exists( 'FLBuilderCSS' ) && ! empty( $gs->button_typography ) ) {
			$gt = (object) array( 'gbtypo' => $gs->button_typography, 'gbtypo_large' => $gs->button_typography_large ?? '', 'gbtypo_medium' => $gs->button_typography_medium ?? '', 'gbtypo_responsive' => $gs->button_typography_responsive ?? '' );
			FLBuilderCSS::typography_field_rule( array( 'settings' => $gt, 'setting_name' => 'gbtypo', 'selector' => "$node .dst-card-btn--button" ) );
		}
	}
}
