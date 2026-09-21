<?php
/**
 * LeagueApps Programs, dynamic node-scoped CSS. In scope: $module, $settings, $id.
 *
 * Everything colour-ish and every dimension a partner might want to change is
 * emitted from here so the Style tab actually drives it; css/frontend.css
 * holds structure only. The phone card layout lives here too, not in the
 * static file, because its breakpoint must follow the site's Beaver Builder
 * setting rather than a hardcoded 767px.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$node = ".fl-node-$id";
$col  = array( 'DS_Module_UI', 'color' );
$st   = function ( $key, $default = '' ) use ( $module ) { return $module->style( $key, $default ); };
$sc   = function ( $key ) use ( $st, $col ) { return call_user_func( $col, $st( $key ) ); };
$px   = function ( $key, $default = '' ) use ( $st ) { $v = $st( $key, $default ); return ( '' !== $v && is_numeric( $v ) ) ? (int) $v . 'px' : ''; };
list( $bp_md, $bp_sm ) = DS_Module_UI::breakpoints();

/* ---------- table frame ---------- */
$table_bg = $sc( 'table_bg' );
$tprops   = array();
if ( $table_bg ) { $tprops[] = "background-color:$table_bg"; }
$radius = $px( 'table_radius' );
if ( $radius ) { $tprops[] = "border-radius:$radius"; $tprops[] = 'overflow:hidden'; }
$shadow = (string) $st( 'table_shadow', 'none' );
if ( 'soft' === $shadow )   { $tprops[] = 'box-shadow:0 2px 10px rgba(0,0,0,.08)'; }
if ( 'medium' === $shadow ) { $tprops[] = 'box-shadow:0 6px 24px rgba(0,0,0,.14)'; }

$bmode = (string) $st( 'table_border', 'none' );
$bw    = $px( 'table_border_width', 1 ) ?: '1px';
$bc    = $sc( 'table_border_color' ) ?: 'rgba(0,0,0,.1)';
if ( in_array( $bmode, array( 'outer', 'outer_horizontal', 'grid' ), true ) ) { $tprops[] = "border:$bw solid $bc"; }
if ( $tprops ) { echo "$node .ds-programs-scroll{" . implode( ';', $tprops ) . ";}\n"; }

if ( in_array( $bmode, array( 'horizontal', 'outer_horizontal', 'grid' ), true ) ) {
	echo "$node .ds-programs-td{border-bottom:$bw solid $bc;}\n";
	echo "$node .ds-programs-table tbody tr:last-child .ds-programs-td{border-bottom:0;}\n";
}
if ( 'grid' === $bmode ) {
	echo "$node .ds-programs-th,$node .ds-programs-td{border-right:$bw solid $bc;}\n";
	echo "$node .ds-programs-th:last-child,$node .ds-programs-td:last-child{border-right:0;}\n";
}

/* ---------- header row ---------- */
$hp = array();
$v = $sc( 'head_bg' );    if ( $v ) { $hp[] = "background-color:$v"; }
$v = $sc( 'head_color' ); if ( $v ) { $hp[] = "color:$v"; }
$hbw = $px( 'head_border_width' );
if ( $hbw ) { $hp[] = "border-bottom:$hbw solid " . ( $sc( 'head_border_color' ) ?: $bc ); }
elseif ( in_array( $bmode, array( 'horizontal', 'outer_horizontal', 'grid' ), true ) ) { $hp[] = "border-bottom:$bw solid $bc"; }
if ( $hp ) { echo "$node .ds-programs-th{" . implode( ';', $hp ) . ";}\n"; }

/* ---------- rows ---------- */
$v = $sc( 'row_bg' );     if ( $v ) { echo "$node .ds-programs-table tbody tr{background-color:$v;}\n"; }
$v = $sc( 'row_stripe' ); if ( $v ) { echo "$node .ds-programs-table tbody tr:nth-child(even):not(.is-hidden){background-color:$v;}\n"; }
$v = $sc( 'row_hover' );  if ( $v ) { echo "$node .ds-programs-table tbody tr:hover{background-color:$v;}\n"; }
$v = $sc( 'row_color' );  if ( $v ) { echo "$node .ds-programs-td{color:$v;}\n"; }

// Program-name emphasis. Emitted with the column class so it out-specifies
// the row typography rule (two classes vs three) instead of being flattened by it.
$nw = (string) $st( 'name_weight', '600' );
if ( 'inherit' !== $nw && is_numeric( $nw ) ) { echo "$node .ds-programs-td.ds-programs-td--program{font-weight:$nw;}\n"; }

/* cell padding: responsive; the preset supplies a default the field may be blank on */
$pad_base = $px( 'cell_pad', 12 );
if ( $pad_base ) { echo "$node .ds-programs-th,$node .ds-programs-td{padding:$pad_base;}\n"; }
foreach ( array( 'medium' => $bp_md, 'responsive' => $bp_sm ) as $suffix => $bp ) {
	$pv = $settings->{"cell_pad_$suffix"} ?? '';
	if ( '' !== $pv && is_numeric( $pv ) ) { echo "@media(max-width:{$bp}px){{$node} .ds-programs-th,{$node} .ds-programs-td{padding:" . (int) $pv . "px;}}\n"; }
}

/* per-column alignment, width, wrapping (from the Columns repeater) */
foreach ( $module->chosen_columns() as $ckey => $c ) {
	$props = array();
	if ( ! empty( $c['align'] ) && 'left' !== $c['align'] ) { $props[] = 'text-align:' . $c['align']; }
	if ( ! empty( $c['width'] ) ) { $props[] = 'width:' . $c['width']; }
	if ( ! empty( $c['nowrap'] ) ) { $props[] = 'white-space:nowrap'; }
	if ( $props ) { echo "$node .ds-programs-th--$ckey,$node .ds-programs-td--$ckey{" . implode( ';', $props ) . ";}\n"; }
}

/* ---------- filter bar ---------- */
$v = $sc( 'filter_color' ); if ( $v ) { echo "$node .ds-programs-label,$node .ds-programs-count,$node .ds-programs-clear{color:$v;}\n"; }
$sp = array();
$v = $sc( 'select_bg' );     if ( $v ) { $sp[] = "background-color:$v"; }
$v = $sc( 'select_color' );  if ( $v ) { $sp[] = "color:$v"; }
$v = $sc( 'select_border' ); if ( $v ) { $sp[] = "border-color:$v"; }
$v = $px( 'select_radius' ); if ( $v ) { $sp[] = "border-radius:$v"; }
if ( $sp ) { echo "$node .ds-programs-select,$node .ds-programs-input{" . implode( ';', $sp ) . ";}\n"; }
$v = $px( 'select_width' );  if ( $v ) { echo "$node .ds-programs-field{flex:0 0 $v;max-width:$v;}\n"; }
$v = $px( 'search_width' );  if ( $v ) { echo "$node .ds-programs-field--search{flex:0 0 $v;max-width:$v;}\n"; }
/* pager + sort arrows */
$pp = array();
$v = $sc( 'pager_color' );  if ( $v ) { $pp[] = "color:$v"; }
$v = $sc( 'pager_border' ); if ( $v ) { $pp[] = "border-color:$v"; }
$v = $px( 'pager_radius' ); if ( $v ) { $pp[] = "border-radius:$v"; }
if ( $pp ) { echo "$node .ds-programs-page{" . implode( ';', $pp ) . ";}\n"; }
$v = $sc( 'pager_color' );  if ( $v ) { echo "$node .ds-programs-page-range,$node .ds-programs-page-gap{color:$v;}\n"; }
$ap = array();
$v = $sc( 'pager_active_bg' );    if ( $v ) { $ap[] = "background-color:$v"; $ap[] = "border-color:$v"; }
$v = $sc( 'pager_active_color' ); if ( $v ) { $ap[] = "color:$v"; }
if ( $ap ) { echo "$node .ds-programs-page.is-current{" . implode( ';', $ap ) . ";}\n"; }
$v = $sc( 'sort_icon_color' ); if ( $v ) { echo "$node .ds-programs-sortbtn.is-asc .ds-programs-sorticon,$node .ds-programs-sortbtn.is-desc .ds-programs-sorticon{color:$v;}\n"; }
$v = $px( 'bar_gap' );       if ( $v ) { echo "$node .ds-programs-bar{gap:$v;}\n"; }
$v = $px( 'bar_space' );     if ( $v ) { echo "$node .ds-programs-bar{margin-bottom:$v;}\n"; }
foreach ( array( 'medium' => $bp_md, 'responsive' => $bp_sm ) as $suffix => $bp ) {
	$pv = $settings->{"bar_space_$suffix"} ?? '';
	if ( '' !== $pv && is_numeric( $pv ) ) { echo "@media(max-width:{$bp}px){{$node} .ds-programs-bar{margin-bottom:" . (int) $pv . "px;}}\n"; }
}

/* ---------- typography ---------- */
foreach ( array(
	'head_typo'   => "$node .ds-programs-th",
	'row_typo'    => "$node .ds-programs-td",
	'filter_typo' => "$node .ds-programs-label",
	'empty_typo'  => "$node .ds-programs-empty, $node .ds-programs-none",
) as $field => $selector ) {
	if ( ! empty( $settings->{$field} ) ) {
		FLBuilderCSS::typography_field_rule( array( 'settings' => $settings, 'setting_name' => $field, 'selector' => $selector ) );
	}
}
$v = $sc( 'empty_color' ); if ( $v ) { echo "$node .ds-programs-empty,$node .ds-programs-none{color:$v;}\n"; }

/* ---------- register button ---------- */
$btn = "$node .ds-programs-btn";
if ( 'custom' !== ( $settings->btn_global ?? 'global' ) ) {
	// House rule: an in-house filled button matches the site's global Button by default.
	$emitted = DS_Module_UI::global_button_css( $btn, "$node a.ds-programs-btn:hover, $node a.ds-programs-btn:focus" );
	if ( ! $emitted ) {
		// Older Launchpad without Global Styles: the bb-theme accent is where the visible button colour comes from.
		$acc = call_user_func( $col, get_theme_mod( 'fl-button-background', '' ) ?: get_theme_mod( 'fl-accent', '' ) );
		$ach = call_user_func( $col, get_theme_mod( 'fl-button-background-hover', '' ) ?: get_theme_mod( 'fl-accent-hover', '' ) );
		if ( $acc ) { echo "$btn{background-color:$acc !important;color:#fff !important;}\n"; }
		if ( $ach ) { echo "$node a.ds-programs-btn:hover,$node a.ds-programs-btn:focus{background-color:$ach !important;}\n"; }
	}
} else {
	$bp = array();
	$v = $sc( 'btn_bg' );    if ( $v ) { $bp[] = "background-color:$v !important"; }
	$v = $sc( 'btn_color' ); if ( $v ) { $bp[] = "color:$v !important"; }
	$v = $px( 'btn_radius' ); if ( $v ) { $bp[] = "border-radius:$v"; }
	$py = $px( 'btn_pad_y' ); $pxx = $px( 'btn_pad_x' );
	if ( $py || $pxx ) { $bp[] = 'padding:' . ( $py ?: '8px' ) . ' ' . ( $pxx ?: '16px' ); }
	if ( $bp ) { echo "$btn{" . implode( ';', $bp ) . ";}\n"; }
	$hp = array();
	$v = $sc( 'btn_bg_hover' );    if ( $v ) { $hp[] = "background-color:$v !important"; }
	$v = $sc( 'btn_color_hover' ); if ( $v ) { $hp[] = "color:$v !important"; }
	if ( $hp ) { echo "$node a.ds-programs-btn:hover,$node a.ds-programs-btn:focus{" . implode( ';', $hp ) . ";}\n"; }
	if ( ! empty( $settings->btn_typo ) ) {
		FLBuilderCSS::typography_field_rule( array( 'settings' => $settings, 'setting_name' => 'btn_typo', 'selector' => $btn ) );
	}
}

/* sold-out */
$fm = (string) ( $settings->btn_full_style ?? 'fade' );
if ( 'colors' === $fm ) {
	$fp = array( 'opacity:1', 'cursor:default' );
	$v = $sc( 'btn_full_bg' );    if ( $v ) { $fp[] = "background-color:$v !important"; }
	$v = $sc( 'btn_full_color' ); if ( $v ) { $fp[] = "color:$v !important"; }
	echo "$node .ds-programs-btn--full{" . implode( ';', $fp ) . ";}\n";
} elseif ( 'text' === $fm ) {
	$v = $sc( 'btn_full_color' ); if ( $v ) { echo "$node .ds-programs-full{color:$v;}\n"; }
}

/* ---------- phone cards (breakpoint follows Beaver Builder) ---------- */
$m = array();
$m[] = ".ds-programs-field{max-width:none;flex-basis:100%;}";
$m[] = ".ds-programs-field--sort{display:flex;}";
$m[] = ".ds-programs-scroll{overflow-x:visible;border:0;box-shadow:none;background:none;border-radius:0;}";
$m[] = ".ds-programs-table,.ds-programs-table tbody{display:block;width:auto;}";
$m[] = ".ds-programs-table tr{display:flex;flex-direction:column;width:auto;}";
$m[] = ".ds-programs-table thead{display:none;}";
$card = array( 'margin:0 0 ' . ( $px( 'mob_card_gap' ) ?: '18px' ), 'padding:' . ( $px( 'mob_card_pad' ) ?: '2px 0 12px' ) );
$mcb = $st( 'mob_card_border', '2' );
$card[] = 'border-width:0 0 ' . ( is_numeric( $mcb ) ? (int) $mcb : 2 ) . 'px';
$card[] = 'border-style:solid';
$card[] = 'border-color:' . ( $sc( 'table_border_color' ) ?: 'rgba(0,0,0,.12)' );
$v = $sc( 'mob_card_bg' );    if ( $v ) { $card[] = "background-color:$v"; }
$v = $px( 'mob_card_radius' ); if ( $v ) { $card[] = "border-radius:$v"; }
$m[] = ".ds-programs-row{" . implode( ';', $card ) . ";}";
$m[] = ".ds-programs-table tbody tr:nth-child(even):not(.is-hidden){background-color:" . ( $sc( 'mob_card_bg' ) ?: 'transparent' ) . ";}";
$m[] = ".ds-programs-table .ds-programs-td{display:flex;justify-content:space-between;align-items:baseline;gap:16px;width:auto;padding:5px 0;white-space:normal;text-align:left;border:0;}";
$m[] = ".ds-programs-table .ds-programs-td:empty{display:none;}";
$lbl = array( 'content:attr(data-label)', 'flex:0 0 auto', 'font-weight:700', 'letter-spacing:.03em' );
$lbl[] = 'font-size:' . ( $px( 'mob_label_size' ) ?: '12px' );
$lbl[] = ( 'none' === $st( 'mob_label_case', 'upper' ) ) ? 'text-transform:none' : 'text-transform:uppercase';
$v = $sc( 'mob_label_color' ); $lbl[] = $v ? "color:$v;opacity:1" : 'opacity:.65';
$m[] = ".ds-programs-td::before{" . implode( ';', $lbl ) . ";}";
$m[] = ".ds-programs-table .ds-programs-td--program{display:block;order:-1;padding:6px 0;font-size:" . ( $px( 'mob_name_size' ) ?: '19px' ) . ";line-height:1.25;font-weight:" . ( is_numeric( $nw ) ? $nw : 700 ) . ";}";
$m[] = ".ds-programs-td--program::before{display:none;}";
$m[] = ".ds-programs-table .ds-programs-td--register{display:block;padding-top:12px;}";
$m[] = ".ds-programs-td--register::before{display:none;}";
$m[] = ".ds-programs-btn{display:block;width:100%;padding:13px 16px;}";
$m[] = ".ds-programs-table .ds-programs-td--spots:has(.ds-programs-dash){display:none;}";
// Node-scoped so it out-specifies the `tr{display:flex}` card rule above (the static
// .is-hidden rule loses to it); without this, phones ignore filters and paging.
$m[] = ".ds-programs-table tr.ds-programs-row.is-hidden,$node .ds-programs-table tr.ds-programs-row.is-paged{display:none;}";
echo "@media(max-width:{$bp_sm}px){\n";
foreach ( $m as $rule ) { echo "$node $rule\n"; }
echo "}\n";
