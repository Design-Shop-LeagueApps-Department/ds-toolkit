<?php
/**
 * Leagueapps Marquee — dynamic CSS. $module, $settings, $id in scope.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$node = ".fl-node-$id";

/** Normalise a colour: pass through rgb()/#hex/var(); otherwise prefix #. Blank -> ''. */
$col = array( 'DS_Module_UI', 'color' );
/** Colour with a fallback when the field is blank. */
$colf = array( 'DS_Module_UI', 'colf' );
/** Int with default. */
$u = array( 'DS_Module_UI', 'u' );
/** color-mix wrapper for opacity on any colour token (hex or var). */
$mix = function( $c, $op ) {
	if ( '' === $c ) { return ''; }
	$op = max( 0, min( 100, (int) $op ) );
	return "color-mix(in srgb, {$c} {$op}%, transparent)";
};

$g    = FLBuilderModel::get_global_settings();
list( $bpm, $bpr ) = DS_Module_UI::breakpoints();
$acc  = 'var(--fl-global-accent)'; // brand-aware fallback for accent-ish fields

/* ---- Bar background + height ---- */
$barbg = $colf( $settings->bar_bg ?? '', 'var(--fl-global-dark-background)' );
echo "$node .ds-marquee { background: {$barbg}; }\n";

$h = $u( $settings->height ?? '', 34 );
echo "$node .ds-marquee { height: {$h}px; }\n";
if ( '' !== ( $settings->height_medium ?? '' ) ) { echo "@media (max-width:{$bpm}px){ $node .ds-marquee { height: " . (int) $settings->height_medium . "px; } }\n"; }
if ( '' !== ( $settings->height_responsive ?? '' ) ) { echo "@media (max-width:{$bpr}px){ $node .ds-marquee { height: " . (int) $settings->height_responsive . "px; } }\n"; }

/* ---- Border (top / bottom / both) ---- */
$bstyle = $settings->border_style ?? 'solid';
$bsides = $settings->border_sides ?? 'bottom';
// Reset both sides first so switching sides never leaves a stale edge.
echo "$node .ds-marquee { border-top: 0; border-bottom: 0; }\n";
if ( 'none' !== $bstyle ) {
	$bw = $u( $settings->border_width ?? '', 1 );
	$bc = $mix( $colf( $settings->border_color ?? '', $acc ), $settings->border_opacity ?? 18 );
	$rule = "{$bw}px {$bstyle} {$bc}";
	if ( 'top' === $bsides || 'both' === $bsides )    { echo "$node .ds-marquee { border-top: {$rule}; }\n"; }
	if ( 'bottom' === $bsides || 'both' === $bsides ) { echo "$node .ds-marquee { border-bottom: {$rule}; }\n"; }
}

/* ---- Label ---- */
$lpad = $u( $settings->label_pad ?? '', 16 );
echo "$node .ds-marquee-label { padding: 0 {$lpad}px; background: " . $colf( $settings->label_bg ?? '', $acc ) . "; color: " . $colf( $settings->label_color ?? '', 'var(--fl-global-dark-background)' ) . "; }\n";
echo "$node .ds-marquee-dot { background: " . $colf( $settings->dot_color ?? '', $colf( $settings->label_color ?? '', 'var(--fl-global-dark-background)' ) ) . "; }\n";

/* ---- Items + separators ---- */
echo "$node .ds-marquee-item { color: " . $colf( $settings->item_color ?? '', 'var(--fl-global-body)' ) . "; }\n";
$ih = $col( $settings->item_hover_color ?? '' );
if ( '' !== $ih ) { echo "$node a.ds-marquee-item--link:hover { color: {$ih}; }\n"; }
$sep_size = $u( $settings->sep_size ?? '', 12 );
echo "$node .ds-marquee-sep { color: " . $colf( $settings->sep_color ?? '', $acc ) . "; font-size: {$sep_size}px; line-height: 1; }\n";

/* ---- Item spacing ---- */
$gap = $u( $settings->item_gap ?? '', 38 );
echo "$node .ds-marquee-group { gap: {$gap}px; padding-left: {$gap}px; }\n";

/* ---- Motion ---- */
$speed = max( 1, $u( $settings->speed ?? '', 32 ) );
echo "$node .ds-marquee-track { animation-duration: {$speed}s; }\n";

/* ---- Spacing: outer margin on the bar (deferred) ---- */
if ( class_exists( 'FLBuilderCSS' ) ) {
	FLBuilderCSS::dimension_field_rule( array(
		'settings'     => $settings,
		'setting_name' => 'margin',
		'selector'     => "$node .ds-marquee",
		'unit'         => 'px',
		'props'        => array( 'margin-top' => 'margin_top', 'margin-right' => 'margin_right', 'margin-bottom' => 'margin_bottom', 'margin-left' => 'margin_left' ),
	) );
}

/* ---- Typography (deferred; flushed by FLBuilderCSS::render()) ---- */
if ( class_exists( 'FLBuilderCSS' ) ) {
	$typo = array(
		'item_typography'  => '.ds-marquee-item',
		'label_typography' => '.ds-marquee-label-text',
	);
	foreach ( $typo as $key => $sel ) {
		if ( ! empty( $settings->{$key} ) ) {
			FLBuilderCSS::typography_field_rule( array( 'settings' => $settings, 'setting_name' => $key, 'selector' => "$node $sel" ) );
		}
	}
}
