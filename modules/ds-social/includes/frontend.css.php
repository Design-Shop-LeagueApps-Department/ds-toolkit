<?php
/**
 * LeagueApps Social — dynamic CSS from settings. $module, $settings, $id in scope.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$node = ".fl-node-$id";

// Colour helper: pass through rgb()/#/var(); otherwise treat as a bare hex.
$col = array( 'DS_Module_UI', 'color' );

// Unit helper: blank-safe int, falls back to $default.
$u = array( 'DS_Module_UI', 'u' );

$show_bg = ! isset( $settings->show_bg ) || 'yes' === $settings->show_bg;

$icon_color = $col( $settings->icon_color ?? 'var(--fl-global-white)' );
$icon_hover = $col( $settings->icon_hover_color ?? '' );
$bg_color   = $col( $settings->bg_color ?? 'var(--fl-global-primary)' );
$bg_hover   = $col( $settings->bg_hover_color ?? 'var(--fl-global-accent)' );
$bg_radius  = $settings->bg_radius ?? '';

// Responsive breakpoints (BB global settings).
$g      = FLBuilderModel::get_global_settings();
list( $bp_md, $bp_sm ) = DS_Module_UI::breakpoints();

// ---- Desktop (base) -------------------------------------------------------
$size    = $u( $settings->icon_size ?? '', 18 );
$gap     = $u( $settings->icon_gap ?? '', 10 );
$pad     = $u( $settings->bg_padding ?? '', 12 );
$justify = $settings->alignment ?? 'center';

echo "$node .ds-social { gap: {$gap}px; justify-content: {$justify}; }\n";
echo "$node .ds-social-link svg { width: {$size}px; height: {$size}px; }\n";

// Set fill on the svg directly (not just `color` + currentColor): in a dark
// footer the theme's link-color rules can override the <a> colour, which would
// drag the icon fill with it. Node-scoped svg fill wins regardless.
if ( '' !== $icon_color ) {
	echo "$node .ds-social-link { color: {$icon_color}; }\n";
	echo "$node .ds-social-link svg { fill: {$icon_color}; }\n";
}
if ( '' !== $icon_hover ) {
	echo "$node .ds-social-link:hover { color: {$icon_hover}; }\n";
	echo "$node .ds-social-link:hover svg { fill: {$icon_hover}; }\n";
}

if ( $show_bg ) {
	// Blank radius falls back to the global Corner Radius token; a per-instance
	// numeric value overrides it in px.
	$radius_css = ( '' !== $bg_radius ) ? ( (int) $bg_radius ) . 'px' : 'var(--ds-radius)';
	echo "$node .ds-social--bg .ds-social-link { padding: {$pad}px; border-radius: {$radius_css};";
	if ( '' !== $bg_color ) { echo " background-color: {$bg_color};"; }
	echo " }\n";
	if ( '' !== $bg_hover ) {
		echo "$node .ds-social--bg .ds-social-link:hover { background-color: {$bg_hover}; }\n";
	}
}

// ---- Tablet ---------------------------------------------------------------
$size_md    = $settings->icon_size_medium ?? '';
$gap_md     = $settings->icon_gap_medium ?? '';
$pad_md     = $settings->bg_padding_medium ?? '';
$justify_md = $settings->alignment_medium ?? '';

if ( '' !== $size_md || '' !== $gap_md || '' !== $pad_md || '' !== $justify_md ) {
	echo "@media (max-width: {$bp_md}px) {\n";
	if ( '' !== $gap_md || '' !== $justify_md ) {
		echo "  $node .ds-social {";
		if ( '' !== $gap_md ) { echo ' gap: ' . (int) $gap_md . 'px;'; }
		if ( '' !== $justify_md ) { echo ' justify-content: ' . $justify_md . ';'; }
		echo " }\n";
	}
	if ( '' !== $size_md ) {
		$sm = (int) $size_md;
		echo "  $node .ds-social-link svg { width: {$sm}px; height: {$sm}px; }\n";
	}
	if ( $show_bg && '' !== $pad_md ) {
		echo "  $node .ds-social--bg .ds-social-link { padding: " . (int) $pad_md . "px; }\n";
	}
	echo "}\n";
}

// ---- Mobile ---------------------------------------------------------------
$size_sm    = $settings->icon_size_responsive ?? '';
$gap_sm     = $settings->icon_gap_responsive ?? '';
$pad_sm     = $settings->bg_padding_responsive ?? '';
$justify_sm = $settings->alignment_responsive ?? '';

if ( '' !== $size_sm || '' !== $gap_sm || '' !== $pad_sm || '' !== $justify_sm ) {
	echo "@media (max-width: {$bp_sm}px) {\n";
	if ( '' !== $gap_sm || '' !== $justify_sm ) {
		echo "  $node .ds-social {";
		if ( '' !== $gap_sm ) { echo ' gap: ' . (int) $gap_sm . 'px;'; }
		if ( '' !== $justify_sm ) { echo ' justify-content: ' . $justify_sm . ';'; }
		echo " }\n";
	}
	if ( '' !== $size_sm ) {
		$ss = (int) $size_sm;
		echo "  $node .ds-social-link svg { width: {$ss}px; height: {$ss}px; }\n";
	}
	if ( $show_bg && '' !== $pad_sm ) {
		echo "  $node .ds-social--bg .ds-social-link { padding: " . (int) $pad_sm . "px; }\n";
	}
	echo "}\n";
}
