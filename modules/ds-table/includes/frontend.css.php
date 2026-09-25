<?php
/**
 * LeagueApps Table: dynamic node-scoped CSS. In scope: $module, $settings, $id.
 *
 * Selectors carry the wrapper class ($w = "$node .ds-table") so they out-rank the
 * three-class resets in css/frontend.css. The phone card layout lives here because
 * its breakpoint follows the site's Beaver Builder setting.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$node = ".fl-node-$id";
$w    = "$node .ds-table";
$col  = array( 'DS_Module_UI', 'color' );
$st   = function ( $key, $default = '' ) use ( $module ) { return $module->style( $key, $default ); };
$sc   = function ( $key ) use ( $st, $col ) { return call_user_func( $col, $st( $key ) ); };
$px   = function ( $key, $default = '' ) use ( $st ) { $v = $st( $key, $default ); return ( '' !== $v && is_numeric( $v ) ) ? (int) $v . 'px' : ''; };
list( $bp_md, $bp_sm ) = DS_Module_UI::breakpoints();
$cards = 'scroll' !== ( $settings->mobile_mode ?? 'cards' );

/* ---------- table frame ---------- */
$tprops = array();
$v = $sc( 'table_bg' ); if ( $v ) { $tprops[] = "background-color:$v"; }
$radius = $px( 'table_radius' );
if ( $radius ) { $tprops[] = "border-radius:$radius"; }
$shadow = (string) $st( 'table_shadow', 'none' );
if ( 'soft' === $shadow )   { $tprops[] = 'box-shadow:0 2px 12px rgba(0,0,0,.08)'; }
if ( 'medium' === $shadow ) { $tprops[] = 'box-shadow:0 8px 28px rgba(0,0,0,.14)'; }
$bmode = (string) $st( 'table_border', 'none' );
$bw    = $px( 'table_border_width', 1 ) ?: '1px';
$bc    = $sc( 'table_border_color' ) ?: 'rgba(0,0,0,.1)';
if ( in_array( $bmode, array( 'outer', 'outer_horizontal', 'grid' ), true ) ) { $tprops[] = "border:$bw solid $bc"; }
if ( $tprops ) { echo "$w .ds-table-scroll{" . implode( ';', $tprops ) . ";}\n"; }

if ( in_array( $bmode, array( 'horizontal', 'outer_horizontal', 'grid' ), true ) ) {
	echo "$w .ds-table-t .ds-table-td{border-bottom:$bw solid $bc;}\n";
	echo "$w .ds-table-t tbody tr:last-child > .ds-table-td{border-bottom:0;}\n";
}
if ( 'grid' === $bmode ) {
	echo "$w .ds-table-t .ds-table-th,$w .ds-table-t .ds-table-td{border-right:$bw solid $bc;}\n";
	echo "$w .ds-table-t tr > :last-child{border-right:0;}\n";
}

/* ---------- header row ---------- */
$hp    = array();
$hmode = (string) ( $settings->head_bg_style ?? 'preset' );
if ( 'none' === $hmode )       { $v = ''; }
elseif ( 'custom' === $hmode ) { $v = call_user_func( $col, $settings->head_bg ?? '' ); }
else                           { $v = $sc( 'head_bg' ); }
if ( $v ) { $hp[] = "background-color:$v"; }
$v = $sc( 'head_color' ); if ( $v ) { $hp[] = "color:$v"; }
$hbw = $px( 'head_border_width' );
if ( $hbw ) { $hp[] = "border-bottom:$hbw solid " . ( $sc( 'head_border_color' ) ?: $bc ); }
elseif ( in_array( $bmode, array( 'horizontal', 'outer_horizontal', 'grid' ), true ) ) { $hp[] = "border-bottom:$bw solid $bc"; }
if ( $hp ) { echo "$w .ds-table-t .ds-table-th{" . implode( ';', $hp ) . ";}\n"; }

/* ---------- rows ---------- */
$v = $sc( 'row_bg' );     if ( $v ) { echo "$w .ds-table-t tbody tr{background-color:$v;}\n"; }
$v = $sc( 'row_stripe' ); if ( $v ) { echo "$w .ds-table-t tbody tr.is-alt{background-color:$v;}\n"; }
$v = $sc( 'row_hover' );  if ( $v ) { echo "$w .ds-table-t tbody tr:hover{background-color:$v;}\n"; }
$v = $sc( 'row_color' );  if ( $v ) { echo "$w .ds-table-t .ds-table-td{color:$v;}\n"; }
$first = (string) ( $settings->first_col ?? 'normal' );
if ( 'normal' !== $first ) { echo "$w .ds-table-t .ds-table-first{font-weight:700;}\n"; }
$v = $sc( 'first_color' ); if ( $v ) { echo "$w .ds-table-t .ds-table-first{color:$v;}\n"; }
$v = $sc( 'link_color' );  if ( $v ) { echo "$w .ds-table-t a.ds-table-link{color:$v;}\n"; }
$v = $sc( 'link_hover' );  if ( $v ) { echo "$w .ds-table-t a.ds-table-link:hover,$w .ds-table-t a.ds-table-link:focus{color:$v;}\n"; }

$pad = $px( 'cell_pad' );
if ( $pad ) { echo "$w .ds-table-t .ds-table-th,$w .ds-table-t .ds-table-td{padding:$pad " . ( (int) $pad + 2 ) . "px;}\n"; }
foreach ( array( 'medium' => $bp_md, 'responsive' => $bp_sm ) as $suffix => $bp ) {
	$pv = $settings->{"cell_pad_$suffix"} ?? '';
	if ( '' !== $pv && is_numeric( $pv ) ) { echo "@media(max-width:{$bp}px){{$w} .ds-table-t .ds-table-th,{$w} .ds-table-t .ds-table-td{padding:" . (int) $pv . 'px ' . ( (int) $pv + 2 ) . "px;}}\n"; }
}

/* ---------- per column (from the editor) ---------- */
$table = $module->table();
$hide  = false;
foreach ( $table['cols'] as $i => $c ) {
	$props = array();
	if ( ! empty( $c['align'] ) && 'left' !== $c['align'] ) { $props[] = 'text-align:' . $c['align']; }
	if ( ! empty( $c['nowrap'] ) ) { $props[] = 'white-space:nowrap'; }
	if ( $props ) { echo "$w .ds-table-t .ds-table-c$i{" . implode( ';', $props ) . ";}\n"; }
	$hide = $hide || ! empty( $c['hide'] );
}
if ( ! $cards ) {
	$mc = $px( 'min_col', 120 ) ?: '120px';
	echo "$w .ds-table-t .ds-table-th,$w .ds-table-t .ds-table-td{min-width:$mc;}\n";
}

/* ---------- search, sort and pages ---------- */
$cp = array();
$v = $sc( 'control_bg' );     if ( $v ) { $cp[] = "background-color:$v"; }
$v = $sc( 'control_color' );  if ( $v ) { $cp[] = "color:$v"; }
$v = $sc( 'control_border' ); if ( $v ) { $cp[] = "border-color:$v"; }
$v = $px( 'control_radius' ); if ( $v ) { $cp[] = "border-radius:$v"; }
if ( $cp ) { echo "$w .ds-table-field .ds-table-input,$w .ds-table-field .ds-table-select{" . implode( ';', $cp ) . ";}\n"; }
$pp = array();
$v = $sc( 'control_color' );  if ( $v ) { $pp[] = "color:$v !important"; }
$v = $sc( 'control_border' ); if ( $v ) { $pp[] = "border-color:$v !important"; }
$v = $px( 'control_radius' ); if ( $v ) { $pp[] = "border-radius:$v"; }
if ( $pp ) { echo "$w .ds-table-pager .ds-table-page{" . implode( ';', $pp ) . ";}\n"; }
$v = $sc( 'control_color' );  if ( $v ) { echo "$w .ds-table-count,$w .ds-table-page-range,$w .ds-table-none{color:$v;}\n"; }
$ap = array();
$v = $sc( 'active_bg' );    if ( $v ) { $ap[] = "background-color:$v !important"; $ap[] = "border-color:$v !important"; }
$v = $sc( 'active_color' ); if ( $v ) { $ap[] = "color:$v !important"; }
if ( $ap ) { echo "$w .ds-table-pager .ds-table-page.is-current{" . implode( ';', $ap ) . ";}\n"; }
$v = $sc( 'sort_icon' );  if ( $v ) { echo "$w .ds-table-sortbtn.is-asc .ds-table-sorticon,$w .ds-table-sortbtn.is-desc .ds-table-sorticon{color:$v;}\n"; }
$v = $px( 'search_width' ); if ( $v ) { echo "$w .ds-table-field--search{flex:0 1 $v;max-width:$v;}\n"; }
$v = $px( 'bar_space' );    if ( '' !== $v ) { echo "$w .ds-table-bar{margin-bottom:$v;}\n"; }

/* ---------- typography ---------- */
foreach ( array( 'head_typo' => "$w .ds-table-t .ds-table-th", 'row_typo' => "$w .ds-table-t .ds-table-td" ) as $field => $selector ) {
	if ( ! empty( $settings->{$field} ) ) {
		FLBuilderCSS::typography_field_rule( array( 'settings' => $settings, 'setting_name' => $field, 'selector' => $selector ) );
	}
}

/* ---------- phones ---------- */
$m = array();
if ( $hide ) { $m[] = "$w .ds-table-hide-sm{display:none !important;}"; }
if ( $cards ) {
	$c   = "$w.ds-table--cards";
	$cbg = $sc( 'card_bg' );
	$cb  = $sc( 'card_border' ) ?: 'rgba(0,0,0,.12)';
	$cr  = $px( 'card_radius' ) ?: ( $radius ? $radius : '10px' );
	$cg  = $px( 'card_gap' ) ?: '12px';
	$lc  = $sc( 'label_color' );
	$ls  = $px( 'label_size' ) ?: '11.5px';
	$m[] = "$c .ds-table-scroll{overflow:visible;border:0;border-radius:0;box-shadow:none;background:none;}";
	$m[] = "$c .ds-table-t thead{display:none;}";
	$m[] = "$c .ds-table-t,$c .ds-table-t tbody{display:block;width:100%;}";
	$m[] = "$c .ds-table-t tbody tr{display:block;margin:0 0 $cg;padding:2px 16px;border:1px solid $cb;border-radius:$cr;background-color:" . ( $cbg ?: 'transparent' ) . ';}';
	$m[] = "$c .ds-table-t tbody tr.is-alt,$c .ds-table-t tbody tr:hover{background-color:" . ( $cbg ?: 'transparent' ) . ';}';
	$m[] = "$c .ds-table-t .ds-table-td{display:flex;justify-content:space-between;align-items:baseline;gap:14px;min-width:0;padding:9px 0;border:0;border-bottom:1px solid rgba(0,0,0,.07);text-align:right;white-space:normal;}";
	$m[] = "$c .ds-table-t tbody tr > .ds-table-td:last-child{border-bottom:0;}";
	$m[] = "$c .ds-table-t .ds-table-td::before{content:attr(data-label);flex:0 0 auto;max-width:48%;text-align:left;font-weight:700;font-size:$ls;line-height:1.3;letter-spacing:.05em;text-transform:uppercase;" . ( $lc ? "color:$lc;" : 'opacity:.7;' ) . '}';
	$m[] = "$c .ds-table-t .ds-table-td[data-label=\"\"]::before{display:none;}";
	$m[] = "$c .ds-table-t .ds-table-td.is-empty{display:none;}";
	if ( 'no' !== ( $settings->card_title ?? 'yes' ) ) {
		$m[] = "$c .ds-table-t .ds-table-td.ds-table-first{display:block;padding:12px 0 10px;border-bottom:1px solid rgba(0,0,0,.1);text-align:left;font-size:17px;font-weight:700;line-height:1.3;}";
		$m[] = "$c .ds-table-t .ds-table-td.ds-table-first::before{display:none;}";
	}
	$m[] = "$c .ds-table-field--sort{display:flex;flex:1 1 180px;max-width:280px;}";
}
if ( $m ) { echo "@media(max-width:{$bp_sm}px){" . implode( '', $m ) . "}\n"; }
