<?php
/**
 * @var object $settings
 * @var string $id
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$node = '.fl-node-' . $id . ' .dsst';
$col  = array( 'DS_Module_UI', 'color' );

// Main colour: the setting, else Global Styles Primary. Text on it and the highlight tint are worked out here
// as plain hex (never color-mix(), which the contrast tools read as black).
$brand_set = (string) call_user_func( $col, $settings->brand ?? '' );
$brand     = '' !== $brand_set ? $brand_set : 'var(--fl-global-primary, #1f2937)';
$card      = (string) call_user_func( $col, $settings->card_bg ?? '' );
$pal       = DS_LA_Colors::palette( $brand_set, $card );
$head_bg   = (string) call_user_func( $col, $settings->head_bg ?? '' );
$head_bg   = '' !== $head_bg ? $head_bg : $brand;
$head_ink  = (string) call_user_func( $col, $settings->head_text ?? '' );
if ( '' === $head_ink ) {
	$head_ink = DS_Standings_Module::text_on( $head_bg, $pal ? $pal['on_brand'] : '#ffffff' );
}
$hl = (string) call_user_func( $col, $settings->hl_bg ?? '' );
if ( '' === $hl && $pal ) {
	$hl = $pal['tint'];
}

$vars = array(
	'--dsst-brand'    => $brand,
	'--dsst-head-bg'  => $head_bg,
	'--dsst-head-ink' => $head_ink,
	'--dsst-card'     => $card,
	'--dsst-zebra'    => (string) call_user_func( $col, $settings->zebra_bg ?? '' ),
	'--dsst-hl'       => $hl,
	'--dsst-text'     => (string) call_user_func( $col, $settings->text ?? '' ),
	'--dsst-muted'    => (string) call_user_func( $col, $settings->muted ?? '' ),
	'--dsst-line'     => (string) call_user_func( $col, $settings->line ?? '' ),
	'--dsst-pos'      => (string) call_user_func( $col, $settings->pos ?? '' ),
	'--dsst-neg'      => (string) call_user_func( $col, $settings->neg ?? '' ),
);
$decl = '';
foreach ( $vars as $k => $v ) {
	if ( '' !== $v ) {
		$decl .= $k . ':' . $v . ';';
	}
}
if ( isset( $settings->radius ) && '' !== $settings->radius && is_numeric( $settings->radius ) ) {
	$decl .= '--dsst-radius:' . max( 0, (int) $settings->radius ) . 'px;';
}
echo $node . '{' . $decl . "}\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- every value passed DS_Module_UI::color() or (int)

// Text size: blank = 15px, 13px on phones (the responsive breakpoint of this site).
list( , $sm ) = DS_Module_UI::breakpoints();
if ( ! isset( $settings->font_size_responsive ) || '' === $settings->font_size_responsive ) {
	echo "@media(max-width:{$sm}px){{$node}{font-size:13px}}\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- ints
}
echo DS_Module_UI::responsive_css( $node, 'font-size', $settings, 'font_size' ); // phpcs:ignore WordPress.Security.EscapeOutput -- numbers only
echo DS_Module_UI::responsive_css( $node . ' .dsst-title', 'font-size', $settings, 'title_size' ); // phpcs:ignore WordPress.Security.EscapeOutput -- numbers only

// Columns left out on phones, and a narrower team column there.
echo "@media(max-width:{$sm}px){{$node} .dsst-np{display:none}{$node} .dsst-table .dsst-c-team{min-width:140px}{$node} .dsst-top{padding:16px 14px 12px}{$node} .dsst-foot{padding:12px 14px 16px}{$node} .dsst-cards{padding:0 14px 14px}}\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- ints and fixed selectors
