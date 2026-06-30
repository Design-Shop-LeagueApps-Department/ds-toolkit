<?php
/** Leagueapps Team Detail — dynamic per-instance CSS. $module, $settings, $id in scope. */
if ( ! defined( 'ABSPATH' ) ) exit;

$node = ".fl-node-$id";

$col = array( 'DS_Module_UI', 'color' );
$u = array( 'DS_Module_UI', 'u' );

$g   = FLBuilderModel::get_global_settings();
list( $bpm, $bpr ) = DS_Module_UI::breakpoints();

/* ---- Layout: image column width + gap ---- */
$mw = $u( $settings->media_width ?? '', 38 );
$mw = max( 15, min( 70, $mw ) );
$mg = $u( $settings->media_gap ?? '', 40 );
echo "$node .ds-teamdetail{gap:{$mg}px;}\n";
echo "$node .ds-teamdetail--hasimg{grid-template-columns:{$mw}% 1fr;}\n";
// The per-node rule above outranks the stylesheet's media query, so stack the
// image above the content on tablet/phone here too.
echo "@media (max-width:768px){ $node .ds-teamdetail--hasimg{grid-template-columns:1fr;} }\n";

/* ---- Image radius (blank -> global Corner Radius) ---- */
$mr = ( isset( $settings->media_radius ) && '' !== $settings->media_radius ) ? ( (int) $settings->media_radius ) . 'px' : 'var(--ds-radius)';
echo "$node .ds-teamdetail-media img{border-radius:{$mr};}\n";

/* ---- Section gap (responsive) ---- */
$sg = $u( $settings->section_gap ?? '', 36 );
echo "$node .ds-teamdetail-main{gap:{$sg}px;}\n";
if ( '' !== ( $settings->section_gap_responsive ?? '' ) ) {
	echo "@media (max-width:{$bpr}px){ $node .ds-teamdetail-main{gap:" . (int) $settings->section_gap_responsive . "px;} }\n";
}

/* ---- Section colours (blank inherits theme) ---- */
if ( $c = $col( $settings->title_color ?? '' ) ) { echo "$node .ds-teamdetail-title{color:$c;}\n"; }
if ( $c = $col( $settings->body_color ?? '' ) )  { echo "$node .ds-teamdetail-body,$node .ds-teamdetail-body p,$node .ds-teamdetail-body li{color:$c;}\n"; }

/* ---- Divider ---- */
if ( ( $settings->divider_show ?? 'yes' ) === 'yes' ) {
	$dw  = $u( $settings->divider_width ?? '', 48 );
	$dth = $u( $settings->divider_thickness ?? '', 3 );
	$dc  = $col( $settings->divider_color ?? '' ) ?: 'var(--fl-global-accent)';
	echo "$node .ds-teamdetail-divider{width:{$dw}px;height:{$dth}px;background:{$dc};}\n";
}

/* ===================================================================== Coaches */
$shadow_map = array(
	'sm' => '0 1px 3px rgba(0,0,0,.08)',
	'md' => '0 4px 14px rgba(0,0,0,.10)',
	'lg' => '0 10px 30px rgba(0,0,0,.14)',
);

/* ---- Grid: columns + gap + width cap (cards stay ~260px, left-aligned) ---- */
$cc   = $u( $settings->coach_cols ?? '', 2 );
$cc   = max( 1, min( 4, $cc ) );
$cgap = $u( $settings->coach_gap ?? '', 20 );
$cap  = ( $cc * 260 ) + ( ( $cc - 1 ) * $cgap );
echo "$node .ds-people-grid--coaches{grid-template-columns:repeat({$cc},1fr);max-width:{$cap}px;gap:{$cgap}px;}\n";
echo "@media (max-width:480px){ $node .ds-people-grid--coaches{grid-template-columns:1fr;max-width:none;} }\n";
if ( '' !== ( $settings->coach_gap_medium ?? '' ) )     { echo "@media (max-width:{$bpm}px){ $node .ds-people-grid--coaches{gap:" . (int) $settings->coach_gap_medium . "px;} }\n"; }
if ( '' !== ( $settings->coach_gap_responsive ?? '' ) ) { echo "@media (max-width:{$bpr}px){ $node .ds-people-grid--coaches{gap:" . (int) $settings->coach_gap_responsive . "px;} }\n"; }

/* ---- Card box: radius, background, border, shadow ---- */
$ccr  = ( isset( $settings->coach_card_radius ) && '' !== $settings->coach_card_radius ) ? ( (int) $settings->coach_card_radius ) . 'px' : 'var(--ds-radius)';
$card = "border-radius:{$ccr};";
if ( $c = $col( $settings->coach_card_bg ?? '' ) ) { $card .= "background:$c;"; }
$cbw = $u( $settings->coach_card_border_width ?? '', 0 );
if ( $cbw > 0 ) { $cbc = $col( $settings->coach_card_border_color ?? '' ) ?: 'currentColor'; $card .= "border:{$cbw}px solid {$cbc};"; }
$csh = (string) ( $settings->coach_card_shadow ?? 'none' );
if ( isset( $shadow_map[ $csh ] ) ) { $card .= 'box-shadow:' . $shadow_map[ $csh ] . ';'; }
echo "$node .ds-people-card{{$card}}\n";

/* ---- Card hover lift ---- */
if ( ( $settings->coach_card_hover_lift ?? 'no' ) === 'yes' ) {
	echo "$node .ds-people-card{transition:transform .25s ease,box-shadow .25s ease;}\n";
	echo "$node .ds-people-card:hover{transform:translateY(-6px);box-shadow:{$shadow_map['lg']};}\n";
}

/* ---- Card body: text alignment + name/role gap ---- */
$body = 'gap:' . $u( $settings->coach_text_gap ?? '', 5 ) . 'px;';
if ( ( $settings->coach_text_align ?? 'center' ) === 'left' ) { $body .= 'text-align:left;align-items:flex-start;'; }
echo "$node .ds-people-body{{$body}}\n";

/* ---- Photo: aspect ratio, placeholder background, grayscale ---- */
$ratio = (string) ( $settings->coach_photo_ratio ?? '3/4' );
if ( ! in_array( $ratio, array( '3/4', '1/1', '4/5', '4/3', '16/9' ), true ) ) { $ratio = '3/4'; }
$photo = 'aspect-ratio:' . str_replace( '/', ' / ', $ratio ) . ';';
if ( $c = $col( $settings->coach_photo_bg ?? '' ) ) { $photo .= "background-color:$c;"; }
echo "$node .ds-people-photo{{$photo}}\n";
$gray = (string) ( $settings->coach_photo_grayscale ?? 'no' );
if ( 'always' === $gray ) {
	echo "$node .ds-people-photo{filter:grayscale(1);}\n";
} elseif ( 'hover' === $gray ) {
	echo "$node .ds-people-photo{filter:grayscale(1);transition:filter .3s ease;}\n";
	echo "$node .ds-people-card:hover .ds-people-photo{filter:grayscale(0);}\n";
}

/* ---- Name / Role colours ---- */
if ( $c = $col( $settings->coach_name_color ?? '' ) ) { echo "$node .ds-people-name{color:$c;}\n"; }
if ( $c = $col( $settings->coach_role_color ?? '' ) ) { echo "$node .ds-people-role{color:$c;}\n"; }

/* ---- Contact icons: button (size, radius, colour, background, border) ---- */
$ico  = 'width:' . ( $isz = $u( $settings->coach_icon_size ?? '', 38 ) ) . 'px;height:' . $isz . 'px;';
$ico .= 'border-radius:' . $u( $settings->coach_icon_radius ?? '', 50 ) . '%;';
$ico .= 'color:' . ( $col( $settings->coach_icon_color ?? '' ) ?: 'var(--fl-global-white)' ) . ';';
$ico .= 'background-color:' . ( $col( $settings->coach_icon_bg ?? '' ) ?: 'var(--fl-global-accent)' ) . ';';
$ibw  = $u( $settings->coach_icon_border_width ?? '', 0 );
if ( $ibw > 0 ) { $ibc = $col( $settings->coach_icon_border_color ?? '' ) ?: 'currentColor'; $ico .= "border:{$ibw}px solid {$ibc};"; }
// Transition includes colour + border-colour so the hover pair animates.
$ico .= 'transition:background-color .2s ease,color .2s ease,border-color .2s ease,transform .2s ease;';
// Use the "a" element token (a.ds-people-ico) so the glyph COLOUR ties the theme
// link rule .fl-builder-content a:not(...) at (0,2,1) and wins on source order;
// background/border already win at (0,2,0). No !important needed.
echo "$node a.ds-people-ico{{$ico}}\n";

// Glyph size (svg uses currentColor; never set fill/stroke — icons mix both).
echo "$node .ds-people-ico svg{width:" . ( $gsz = $u( $settings->coach_icon_glyph_size ?? '', 18 ) ) . "px;height:{$gsz}px;}\n";

// Icon hover: lift (default 2px = current look) + optional colour / background.
$hov = 'transform:translateY(-' . $u( $settings->coach_icon_hover_lift ?? '', 2 ) . 'px);';
if ( $c = $col( $settings->coach_icon_color_hover ?? '' ) ) { $hov .= "color:$c;"; }
if ( $c = $col( $settings->coach_icon_bg_hover ?? '' ) )    { $hov .= "background-color:$c;"; }
echo "$node a.ds-people-ico:hover,$node a.ds-people-ico:focus-visible{{$hov}}\n";

// Responsive icon size (node rule outranks stylesheet @media, so re-emit here).
if ( '' !== ( $settings->coach_icon_size_medium ?? '' ) )     { $r = (int) $settings->coach_icon_size_medium;     echo "@media (max-width:{$bpm}px){ $node .ds-people-ico{width:{$r}px;height:{$r}px;} }\n"; }
if ( '' !== ( $settings->coach_icon_size_responsive ?? '' ) ) { $r = (int) $settings->coach_icon_size_responsive; echo "@media (max-width:{$bpr}px){ $node .ds-people-ico{width:{$r}px;height:{$r}px;} }\n"; }

/* ---- Contact icon row: gap, top spacing, alignment ---- */
$ial = (string) ( $settings->coach_icon_align ?? 'center' );
if ( ! in_array( $ial, array( 'flex-start', 'center', 'flex-end' ), true ) ) { $ial = 'center'; }
echo "$node .ds-people-contacts{gap:" . $u( $settings->coach_icon_gap ?? '', 10 ) . 'px;margin-top:' . $u( $settings->coach_icon_top_gap ?? '', 12 ) . "px;justify-content:{$ial};}\n";

/* ---- Deferred: typography + card padding (flushed by FLBuilderCSS::render()) ---- */
if ( class_exists( 'FLBuilderCSS' ) ) {
	$typo = array(
		'title_typography'      => '.ds-teamdetail-title',
		'body_typography'       => '.ds-teamdetail-body',
		'coach_name_typography' => '.ds-people-name',
		'coach_role_typography' => '.ds-people-role',
	);
	foreach ( $typo as $key => $sel ) {
		if ( ! empty( $settings->{ $key } ) ) {
			FLBuilderCSS::typography_field_rule( array( 'settings' => $settings, 'setting_name' => $key, 'selector' => "$node $sel" ) );
		}
	}

	// Coach card body padding — only when the partner has set at least one side.
	$pad_set = false;
	foreach ( array( 'coach_card_padding_top', 'coach_card_padding_right', 'coach_card_padding_bottom', 'coach_card_padding_left' ) as $pk ) {
		if ( '' !== ( $settings->{ $pk } ?? '' ) ) { $pad_set = true; break; }
	}
	if ( $pad_set ) {
		FLBuilderCSS::dimension_field_rule( array(
			'settings'     => $settings,
			'setting_name' => 'coach_card_padding',
			'selector'     => "$node .ds-people-body",
			'unit'         => 'px',
			'props'        => array( 'padding-top' => 'coach_card_padding_top', 'padding-right' => 'coach_card_padding_right', 'padding-bottom' => 'coach_card_padding_bottom', 'padding-left' => 'coach_card_padding_left' ),
		) );
	}
}
