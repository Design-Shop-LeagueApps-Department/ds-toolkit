<?php
/** Leagueapps Info List — dynamic CSS. $module, $settings, $id in scope. */
if ( ! defined( 'ABSPATH' ) ) exit;
$node = ".fl-node-$id";
$col = array( 'DS_Module_UI', 'color' );
$u = array( 'DS_Module_UI', 'u' );

$dir   = ( ( $settings->layout ?? 'stack' ) === 'row' ) ? 'row' : 'column';
$align = $settings->align ?? 'left';
$gap   = $u( $settings->gap ?? '', 12 );
$just  = ( 'center' === $align ) ? 'center' : ( ( 'right' === $align ) ? 'flex-end' : 'flex-start' );
if ( 'row' === $dir ) {
	echo "$node .ds-info-list{flex-direction:row;flex-wrap:wrap;align-items:center;justify-content:{$just};gap:{$gap}px;}\n";
	// Row layout has no natural mobile collapse, so stack it on small screens.
	echo "@media(max-width:768px){ $node .ds-info-list{flex-direction:column;align-items:{$just};}}\n";
} else {
	echo "$node .ds-info-list{flex-direction:column;align-items:{$just};gap:{$gap}px;}\n";
}

$isz = $u( $settings->icon_size ?? '', 16 );
echo "$node .ds-info-ico i{font-size:{$isz}px;}\n";
$ic  = $col( $settings->icon_color ?? '' );
$ibg = $col( $settings->icon_bg ?? '' );

if ( ( $settings->icon_style ?? 'circle' ) === 'circle' ) {
	$box = $isz + 22;
	echo "$node .ds-info-ico{width:{$box}px;height:{$box}px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:" . ( $ibg ?: 'var(--fl-global-accent)' ) . ";color:" . ( $ic ?: 'var(--fl-global-white)' ) . ";}\n";
} else {
	echo "$node .ds-info-ico{display:inline-flex;align-items:center;justify-content:center;width:auto;height:auto;background:none;" . ( $ic ? "color:{$ic};" : 'color:var(--fl-global-accent);' ) . "}\n";
	if ( $ibg ) { echo "$node .ds-info-ico{background:{$ibg};}\n"; }
}

$tc = $col( $settings->text_color ?? '' );
if ( $tc ) { echo "$node .ds-info-item .ds-info-text{color:{$tc};}\n"; }
