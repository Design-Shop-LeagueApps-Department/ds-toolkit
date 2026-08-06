<?php
/**
 * LeagueApps Pathway — dynamic CSS. $module, $settings, $id in scope.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$node = ".fl-node-$id";
$col  = array( 'DS_Module_UI', 'color' );
$u    = array( 'DS_Module_UI', 'u' );
list( $bpm, $bpr ) = DS_Module_UI::breakpoints();

// ---- Content width (boxed / full / custom) ----
$cw = $settings->content_width ?? 'boxed';
if ( 'full' === $cw ) {
	echo "$node .ds-pathway-wrap { max-width: none; margin: 0; }\n";
} elseif ( 'custom' === $cw ) {
	$cmw = ( isset( $settings->content_max_width ) && '' !== $settings->content_max_width ) ? (int) $settings->content_max_width : 1280;
	echo "$node .ds-pathway-wrap { max-width: {$cmw}px; margin: 0 auto; }\n";
}

// ---- Track / marker custom properties on the section ----
$vars   = array();
$vars[] = '--ds-path-msize:' . $u( $settings->marker_size ?? '', 14 ) . 'px';
$vars[] = '--ds-path-mborder:' . max( 1, $u( $settings->marker_border ?? '', 2 ) ) . 'px';
$vars[] = '--ds-path-mspeed:' . max( 0, $u( $settings->marker_speed ?? '', 250 ) ) . 'ms';
$vars[] = '--ds-path-line:' . $u( $settings->line_thickness ?? '', 2 ) . 'px';
$vars[] = '--ds-path-trackgap:' . $u( $settings->track_gap ?? '', 26 ) . 'px';
$vars[] = '--ds-path-gap:' . $u( $settings->gap ?? '', 32 ) . 'px';
$lc = $col( $settings->line_color ?? '' );
if ( '' !== $lc ) { $vars[] = '--ds-path-line-c:' . $lc; }
$mc = $col( $settings->marker_color ?? '' );
if ( '' !== $mc ) { $vars[] = '--ds-path-marker-c:' . $mc; }
$hc = $col( $settings->marker_hover_color ?? '' );
if ( '' !== $hc ) { $vars[] = '--ds-path-hover-c:' . $hc; }
$dc = $col( $settings->divider_color ?? '' );
if ( '' !== $dc ) { $vars[] = '--ds-path-divider-c:' . $dc; }
echo "$node .ds-pathway { " . implode( '; ', $vars ) . "; }\n";

// Responsive gap overrides.
if ( '' !== ( $settings->gap_medium ?? '' ) ) { echo "@media (max-width:{$bpm}px){ $node .ds-pathway { --ds-path-gap: " . (int) $settings->gap_medium . "px; } }\n"; }
if ( '' !== ( $settings->gap_responsive ?? '' ) ) { echo "@media (max-width:{$bpr}px){ $node .ds-pathway { --ds-path-gap: " . (int) $settings->gap_responsive . "px; } }\n"; }

// ---- Colours ----
$c = $col( $settings->eyebrow_color ?? '' ); if ( '' !== $c ) { echo "$node .ds-path-eyebrow { color: {$c}; }\n"; }
$c = $col( $settings->title_color ?? '' );   if ( '' !== $c ) { echo "$node .ds-path-title { color: {$c}; }\n"; }
$c = $col( $settings->text_color ?? '' );    if ( '' !== $c ) { echo "$node .ds-path-text { color: {$c}; }\n"; }

// ---- Vertical timeline below the stack breakpoint (GH #96 responsive) ----
// Markers run down a left rail; each stage's segment drops to the next marker;
// the last stage fades downward. Dividers are a horizontal-mode treatment.
$stack_map = array( 'large' => 0, 'medium' => $bpm, 'small' => $bpr );
$stack_at  = $settings->stack_at ?? 'medium';
if ( isset( $stack_map[ $stack_at ] ) ) {
	$bp_lg    = (int) ( FLBuilderModel::get_global_settings()->large_breakpoint ?? 1200 );
	$stack_bp = 'large' === $stack_at ? $bp_lg : $stack_map[ $stack_at ];
	echo "@media (max-width:{$stack_bp}px){\n";
	echo "\t$node .ds-pathway-grid { grid-template-columns: 1fr; row-gap: 30px; }\n";
	echo "\t$node .ds-path-stage { padding-left: calc(var(--ds-path-msize,14px) + 20px); }\n";
	echo "\t$node .ds-path-track { position: absolute; left: 0; top: 4px; bottom: -30px; height: auto; width: var(--ds-path-msize,14px); margin: 0; }\n";
	echo "\t$node .ds-path-track::before { left: 50%; right: auto; top: calc(var(--ds-path-msize,14px) + 4px); bottom: 4px; height: auto; width: var(--ds-path-line,2px); transform: translateX(-50%); }\n";
	echo "\t$node .ds-path-marker { top: 0; transform: rotate(45deg); }\n";
	echo "\t$node .ds-pathway--m-circle .ds-path-marker { transform: none; }\n";
	echo "\t$node .ds-path-stage--last .ds-path-track { bottom: auto; height: calc(var(--ds-path-msize,14px) + 90px); }\n";
	echo "\t$node .ds-path-stage--last .ds-path-track::before { width: var(--ds-path-line,2px); background: linear-gradient(to bottom, var(--ds-path-line-c,var(--fl-global-accent,#f26a21)), transparent); }\n";
	echo "\t$node .ds-pathway--dividers .ds-path-stage::after { display: none; }\n";
	echo "}\n";
}

// ---- Spacing: padding on the wrap, margin on the section (deferred) ----
if ( class_exists( 'FLBuilderCSS' ) ) {
	FLBuilderCSS::dimension_field_rule( array(
		'settings'     => $settings,
		'setting_name' => 'padding',
		'selector'     => "$node .ds-pathway-wrap",
		'unit'         => 'px',
		'props'        => array( 'padding-top' => 'padding_top', 'padding-right' => 'padding_right', 'padding-bottom' => 'padding_bottom', 'padding-left' => 'padding_left' ),
	) );
	FLBuilderCSS::dimension_field_rule( array(
		'settings'     => $settings,
		'setting_name' => 'margin',
		'selector'     => "$node .ds-pathway",
		'unit'         => 'px',
		'props'        => array( 'margin-top' => 'margin_top', 'margin-right' => 'margin_right', 'margin-bottom' => 'margin_bottom', 'margin-left' => 'margin_left' ),
	) );
}

// ---- Typography (deferred; flushed by FLBuilderCSS::render()) ----
// Every typography field is in this map ON PURPOSE (GH #87 lesson).
if ( class_exists( 'FLBuilderCSS' ) ) {
	$typo = array(
		'eyebrow_typography' => '.ds-path-eyebrow',
		'title_typography'   => '.ds-path-title',
		'text_typography'    => '.ds-path-text',
	);
	foreach ( $typo as $key => $sel ) {
		if ( ! empty( $settings->{$key} ) ) {
			FLBuilderCSS::typography_field_rule( array( 'settings' => $settings, 'setting_name' => $key, 'selector' => "$node $sel" ) );
		}
	}
}
