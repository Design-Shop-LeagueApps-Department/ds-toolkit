<?php
/**
 * Leagueapps Org Stats — dynamic CSS. $module, $settings, $id in scope.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$node  = ".fl-node-$id";
$style = isset( $settings->orgstats_style ) ? preg_replace( '/[^a-z0-9_]/', '', $settings->orgstats_style ) : 'style1';

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

$g   = FLBuilderModel::get_global_settings();
list( $bpm, $bpr ) = DS_Module_UI::breakpoints();
$acc = 'var(--fl-global-accent)'; // brand-aware fallback for accent-ish fields

/* ---- Section background ---- */
$sbg = $col( $settings->section_bg ?? '' );
if ( '' !== $sbg ) { echo "$node .ds-orgstats { background: {$sbg}; }\n"; }

/* ---- Background image (opacity + size/position/repeat/attachment/blur) ---- */
$bgop = $u( $settings->bg_opacity ?? '', 16 );
$bg_props = array( 'opacity: ' . ( $bgop / 100 ) );
$bg_size  = in_array( $settings->bg_size ?? 'cover', array( 'cover', 'contain', 'auto' ), true ) ? ( $settings->bg_size ?? 'cover' ) : 'cover';
$bg_pos   = preg_replace( '/[^a-z ]/', '', (string) ( $settings->bg_position ?? 'center center' ) ) ?: 'center center';
$bg_rep   = in_array( $settings->bg_repeat ?? 'no-repeat', array( 'no-repeat', 'repeat', 'repeat-x', 'repeat-y' ), true ) ? ( $settings->bg_repeat ?? 'no-repeat' ) : 'no-repeat';
$bg_att   = ( ( $settings->bg_attachment ?? 'scroll' ) === 'fixed' ) ? 'fixed' : 'scroll';
$bg_blur  = $u( $settings->bg_blur ?? '', 0 );
$bg_props[] = "background-size: {$bg_size}";
$bg_props[] = "background-position: {$bg_pos}";
$bg_props[] = "background-repeat: {$bg_rep}";
$bg_props[] = "background-attachment: {$bg_att}";
if ( $bg_blur > 0 ) { $bg_props[] = "filter: blur({$bg_blur}px)"; }
echo "$node .ds-orgstats-bg { " . implode( '; ', $bg_props ) . "; }\n";

/* ---- Overlay (gradient, color-mix so opacity works on hex AND global var) ---- */
$ocolor = $col( $settings->overlay_color ?? '' ) ?: 'var(--fl-global-dark-background)';
$otop   = $mix( $ocolor, $settings->overlay_top ?? 86 );
$obot   = $mix( $ocolor, $settings->overlay_bottom ?? 94 );
if ( $otop && $obot ) { echo "$node .ds-orgstats-overlay { background: linear-gradient(180deg, {$otop}, {$obot}); }\n"; }

/* ---- Content width (boxed / full / custom) so it can fill a full-width container ---- */
$cw = $settings->content_width ?? 'boxed';
if ( 'full' === $cw ) {
	echo "$node .ds-orgstats-wrap { max-width: none; margin: 0; }\n";
} else {
	$mw = $u( $settings->max_width ?? '', 1280 );
	echo "$node .ds-orgstats-wrap { max-width: {$mw}px; margin: 0 auto; }\n";
}

/* ---- Grid columns (node-scoped so they beat the static media queries) ---- */
$cols  = max( 1, $u( $settings->columns ?? '', 4 ) );
$colsM = max( 1, $u( $settings->columns_medium ?? '', min( $cols, 2 ) ) );
$colsR = max( 1, $u( $settings->columns_responsive ?? '', min( $cols, 2 ) ) );
echo "$node .ds-orgstats-grid { grid-template-columns: repeat({$cols},1fr); }\n";
echo "@media (max-width:{$bpm}px){ $node .ds-orgstats-grid { grid-template-columns: repeat({$colsM},1fr); } }\n";
echo "@media (max-width:{$bpr}px){ $node .ds-orgstats-grid { grid-template-columns: repeat({$colsR},1fr); } }\n";

/* ---- Item padding (responsive) ---- */
$pv = $u( $settings->item_pad_v ?? '', 40 );
$ph = $u( $settings->item_pad_h ?? '', 14 );
echo "$node .ds-orgstats-item { padding: {$pv}px {$ph}px; }\n";
if ( '' !== ( $settings->item_pad_v_medium ?? '' ) || '' !== ( $settings->item_pad_h_medium ?? '' ) ) {
	$pvM = $u( $settings->item_pad_v_medium ?? '', $pv );
	$phM = $u( $settings->item_pad_h_medium ?? '', $ph );
	echo "@media (max-width:{$bpm}px){ $node .ds-orgstats-item { padding: {$pvM}px {$phM}px; } }\n";
}
if ( '' !== ( $settings->item_pad_v_responsive ?? '' ) || '' !== ( $settings->item_pad_h_responsive ?? '' ) ) {
	$pvR = $u( $settings->item_pad_v_responsive ?? '', $pv );
	$phR = $u( $settings->item_pad_h_responsive ?? '', $ph );
	echo "@media (max-width:{$bpr}px){ $node .ds-orgstats-item { padding: {$pvR}px {$phR}px; } }\n";
}

/* ---- Dividers / border (clean grid lines: container top+left, items right+bottom) ---- */
$dstyle = $settings->divider_style ?? 'solid';
if ( 'none' === $dstyle ) {
	echo "$node .ds-orgstats-grid { border-top: 0; border-left: 0; }\n";
	echo "$node .ds-orgstats-item { border-right: 0; border-bottom: 0; }\n";
} else {
	$dw = $u( $settings->divider_width ?? '', 2 );
	$dc = $mix( $colf( $settings->divider_color ?? '', 'var(--fl-global-white)' ), $settings->divider_opacity ?? 22 );
	echo "$node .ds-orgstats-grid { border-top: {$dw}px {$dstyle} {$dc}; border-left: {$dw}px {$dstyle} {$dc}; }\n";
	echo "$node .ds-orgstats-item { border-right: {$dw}px {$dstyle} {$dc}; border-bottom: {$dw}px {$dstyle} {$dc}; }\n";
}

/* ---- Image-card layout controls ---- */
if ( ( $settings->stat_layout ?? 'plain' ) === 'cards' ) {
	$cmh   = $u( $settings->card_min_height ?? '', 360 );
	$crad  = ( isset( $settings->card_radius ) && '' !== $settings->card_radius ) ? ( (int) $settings->card_radius ) . 'px' : 'var(--ds-radius)';
	$cgap  = $u( $settings->card_gap ?? '', 24 );
	$cpad  = $u( $settings->card_pad ?? '', 32 );
	echo "$node .ds-orgstats--cards .ds-orgstats-grid { gap: {$cgap}px; }\n";
	echo "$node .ds-orgstats--cards .ds-orgstats-item { min-height: {$cmh}px; border-radius: {$crad}; }\n";
	echo "$node .ds-orgstats--cards .ds-orgstats-item-inner { padding: {$cpad}px; }\n";
	if ( '' !== ( $settings->card_min_height_medium ?? '' ) ) { echo "@media (max-width:{$bpm}px){ $node .ds-orgstats--cards .ds-orgstats-item { min-height: " . (int) $settings->card_min_height_medium . "px; } }\n"; }
	if ( '' !== ( $settings->card_min_height_responsive ?? '' ) ) { echo "@media (max-width:{$bpr}px){ $node .ds-orgstats--cards .ds-orgstats-item { min-height: " . (int) $settings->card_min_height_responsive . "px; } }\n"; }
	// Per-card overlay gradient (color-mix opacity on hex AND global var).
	$cov  = $col( $settings->card_ov_color ?? '' ) ?: 'var(--fl-global-dark-background)';
	$covt = $mix( $cov, $settings->card_ov_top ?? 20 );
	$covb = $mix( $cov, $settings->card_ov_bottom ?? 78 );
	if ( $covt && $covb ) { echo "$node .ds-orgstats-card-overlay { background: linear-gradient(180deg, {$covt}, {$covb}); }\n"; }
}

/* ---- Eyebrow rule (length + thickness) ---- */
$elen   = $u( $settings->eyebrow_rule_length ?? '', 34 );
$ethick = $u( $settings->eyebrow_rule_thickness ?? '', 2 );
echo "$node .ds-orgstats-eyebrow::before, $node .ds-orgstats-eyebrow::after { width: {$elen}px; height: {$ethick}px; }\n";

/* ---- Colours ---- */
echo "$node .ds-orgstats-eyebrow { color: " . $colf( $settings->eyebrow_color ?? '', $acc ) . "; }\n";
$head_c = $col( $settings->heading_color ?? '' );
if ( '' !== $head_c ) { echo "$node .ds-orgstats-heading { color: {$head_c}; }\n"; }
$accent = $colf( $settings->accent_color ?? '', $acc );
echo "$node .ds-orgstats-accent, $node .ds-orgstats-affix { color: {$accent}; }\n";
$num_c = $col( $settings->number_color ?? '' );
if ( '' !== $num_c ) { echo "$node .ds-orgstats-value { color: {$num_c}; }\n"; }
$lab_c = $col( $settings->label_color ?? '' );
if ( '' !== $lab_c ) { echo "$node .ds-orgstats-label { color: {$lab_c}; }\n"; }

/* ---- Count-up duration (CSS custom prop read by js/frontend.js) ---- */
$dur = max( 100, $u( $settings->count_duration ?? '', 1600 ) );
echo "$node .ds-orgstats { --ds-orgstats-dur: {$dur}; }\n";

/* ---- Spacing: padding on the wrap, margin on the section (deferred) ---- */
if ( class_exists( 'FLBuilderCSS' ) ) {
	FLBuilderCSS::dimension_field_rule( array(
		'settings'     => $settings,
		'setting_name' => 'padding',
		'selector'     => "$node .ds-orgstats-wrap",
		'unit'         => 'px',
		'props'        => array( 'padding-top' => 'padding_top', 'padding-right' => 'padding_right', 'padding-bottom' => 'padding_bottom', 'padding-left' => 'padding_left' ),
	) );
	FLBuilderCSS::dimension_field_rule( array(
		'settings'     => $settings,
		'setting_name' => 'margin',
		'selector'     => "$node .ds-orgstats",
		'unit'         => 'px',
		'props'        => array( 'margin-top' => 'margin_top', 'margin-right' => 'margin_right', 'margin-bottom' => 'margin_bottom', 'margin-left' => 'margin_left' ),
	) );
}

/* ---- Typography (deferred; flushed by FLBuilderCSS::render()) ---- */
if ( class_exists( 'FLBuilderCSS' ) ) {
	$typo = array(
		'eyebrow_typography' => '.ds-orgstats-eyebrow',
		'heading_typography' => '.ds-orgstats-heading',
		'number_typography'  => '.ds-orgstats-num',
		'label_typography'   => '.ds-orgstats-label',
	);
	foreach ( $typo as $key => $sel ) {
		if ( ! empty( $settings->{$key} ) ) {
			FLBuilderCSS::typography_field_rule( array( 'settings' => $settings, 'setting_name' => $key, 'selector' => "$node $sel" ) );
		}
	}
}

/* ---- Outline text ({outline}...{/outline}) per-module override: blank = Theme Setting. ---- */
$oc_ov = DS_Module_UI::color( $settings->outline_color ?? '' );
if ( '' !== $oc_ov ) { echo "$node { --ds-outline-c: {$oc_ov}; }\n"; }
if ( isset( $settings->outline_width ) && '' !== $settings->outline_width ) { echo "$node { --ds-outline-w: " . max( 1, (int) $settings->outline_width ) . "px; }\n"; }
