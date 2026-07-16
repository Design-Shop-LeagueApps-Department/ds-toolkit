<?php
/**
 * Leagueapps News — dynamic CSS. $module, $settings, $id in scope.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$node = ".fl-node-$id";

// ---- Content width (boxed / full / custom) so it can fill a full-width container ----
$cw = $settings->content_width ?? 'boxed';
if ( 'full' === $cw ) {
	echo "$node .ds-news-wrap { max-width: none; margin: 0; }\n";
} elseif ( 'custom' === $cw ) {
	$cmw = ( isset( $settings->content_max_width ) && '' !== $settings->content_max_width ) ? (int) $settings->content_max_width : 1280;
	echo "$node .ds-news-wrap { max-width: {$cmw}px; margin: 0 auto; }\n";
}

$col = array( 'DS_Module_UI', 'color' );
$u   = array( 'DS_Module_UI', 'u' );
$mix = function( $color, $op ) use ( $col ) {
	$c = $col( $color );
	if ( '' === $c ) { return ''; }
	$op = max( 0, min( 100, (int) $op ) );
	return "color-mix(in srgb, {$c} {$op}%, transparent)";
};

list( $bpm, $bpr ) = DS_Module_UI::breakpoints();

// ---- Hover & animation (unified across every card layout) ----
$he      = preg_replace( '/[^a-z]/', '', (string) ( $settings->hover_effect ?? 'lift' ) );
$hspeed  = $u( $settings->hover_speed ?? '', 300 );
$cardsel = array( '.ds-news-card', '.ds-news-card2', '.ds-news-feature', '.ds-people-card', '.ds-commit-card', '.ds-commit-row', '.ds-commit-action', '.ds-team-row', '.ds-news-custom-item', '.ds-sponsor-card', '.ds-program-card', '.ds-tourn-card' );
$cards   = implode( ',', array_map( function ( $c ) use ( $node ) { return "$node $c"; }, $cardsel ) );
$cardsH  = implode( ',', array_map( function ( $c ) use ( $node ) { return "$node $c:hover"; }, $cardsel ) );

// Card hover background — any layout, independent of the animation effect.
$hbg = $col( $settings->hover_bg ?? '' );
if ( '' !== $hbg ) {
	echo "$cards{transition:transform {$hspeed}ms ease,box-shadow {$hspeed}ms ease,border-color {$hspeed}ms ease,background {$hspeed}ms ease;}\n";
	echo "$cardsH{background:{$hbg};}\n";
}

if ( $he && 'none' !== $he ) {
	echo "$cards{transition:transform {$hspeed}ms ease,box-shadow {$hspeed}ms ease,border-color {$hspeed}ms ease;will-change:transform;}\n";
	if ( 'lift' === $he ) {
		$d  = $u( $settings->hover_distance ?? '', 6 );
		$sc = $col( $settings->hover_shadow_color ?? '' ) ?: 'rgba(10,30,60,.28)';
		echo "$cardsH{transform:translateY(-{$d}px);box-shadow:0 18px 38px -18px {$sc};}\n";
	} elseif ( 'grow' === $he ) {
		$sc    = $col( $settings->hover_shadow_color ?? '' ) ?: 'rgba(10,30,60,.22)';
		$scale = max( 100, (int) ( $settings->hover_scale ?? 105 ) ) / 100;
		echo "$cardsH{transform:scale({$scale});box-shadow:0 18px 38px -18px {$sc};}\n";
	} elseif ( 'shadow' === $he ) {
		$sc = $col( $settings->hover_shadow_color ?? '' ) ?: 'rgba(10,30,60,.25)';
		echo "$cardsH{box-shadow:0 18px 40px -18px {$sc};}\n";
	} elseif ( 'border' === $he ) {
		$bc = $col( $settings->hover_border_color ?? '' ) ?: 'var(--fl-global-accent)';
		echo "$cardsH{border-color:{$bc};box-shadow:inset 0 0 0 1px {$bc};}\n";
	} elseif ( 'zoom' === $he ) {
		$scale = max( 100, (int) ( $settings->hover_scale ?? 105 ) ) / 100;
		$media = array( '.ds-people-photo', '.ds-news-feature-bg', '.ds-news-card2-media img', '.ds-commit-row-logo img', '.ds-commit-action-logo img', '.ds-program-media img' );
		$mt    = implode( ',', array_map( function ( $c ) use ( $node ) { return "$node $c"; }, $media ) );
		echo "$mt{transition:transform {$hspeed}ms ease;}\n";
		$mh = array();
		foreach ( $cardsel as $c ) { foreach ( $media as $m ) { $mh[] = "$node $c:hover $m"; } }
		echo implode( ',', $mh ) . "{transform:scale({$scale});}\n";
		echo "$node .ds-people-photo,$node .ds-news-feature,$node .ds-commit-action .ds-people-photo,$node .ds-commit-row-logo{overflow:hidden;}\n";
	}
}

// ---- Section background ----
$sbg = $col( $settings->section_bg ?? '' );
if ( '' !== $sbg ) { echo "$node .ds-news { background: {$sbg}; }\n"; }

// ---- Grid layout (responsive, node-scoped so it beats the static base rule) ----
$feat = trim( (string) ( $settings->feature_width ?? '1.4fr' ) ) ?: '1.4fr';
if ( ! preg_match( '#^[0-9.]+(fr|px|%|rem|em|vw)$#', $feat ) ) { $feat = '1.4fr'; }
$gap  = $u( $settings->gap ?? '', 20 );
echo "$node .ds-news-grid { grid-template-columns: {$feat} 1fr 1fr; gap: {$gap}px; }\n";
$gapM = $u( $settings->gap_medium ?? '', $gap );
echo "@media (max-width:{$bpm}px){ $node .ds-news-grid { grid-template-columns: 1fr 1fr; gap: {$gapM}px; } $node .ds-news-feature { grid-column: 1 / -1; grid-row: auto; } }\n";
$gapR = $u( $settings->gap_responsive ?? '', max( 12, $gap ) );
echo "@media (max-width:{$bpr}px){ $node .ds-news-grid { grid-template-columns: 1fr; gap: {$gapR}px; } }\n";

// ---- Heights (featured + small cards) ----
$fh = $u( $settings->feature_min_h ?? '', 420 );
echo "$node .ds-news-feature { min-height: {$fh}px; }\n";
$fhM = $u( $settings->feature_min_h_medium ?? '', min( $fh, 340 ) );
echo "@media (max-width:{$bpm}px){ $node .ds-news-feature { min-height: {$fhM}px; } }\n";
$fhR = $u( $settings->feature_min_h_responsive ?? '', min( $fh, 280 ) );
echo "@media (max-width:{$bpr}px){ $node .ds-news-feature { min-height: {$fhR}px; } }\n";

$ch = $u( $settings->card_min_h ?? '', 195 );
echo "$node .ds-news-card { min-height: {$ch}px; }\n";
if ( '' !== ( $settings->card_min_h_medium ?? '' ) ) { echo "@media (max-width:{$bpm}px){ $node .ds-news-card { min-height: " . (int) $settings->card_min_h_medium . "px; } }\n"; }
if ( '' !== ( $settings->card_min_h_responsive ?? '' ) ) { echo "@media (max-width:{$bpr}px){ $node .ds-news-card { min-height: " . (int) $settings->card_min_h_responsive . "px; } }\n"; }

// ---- Featured card ----
$fbg = $col( $settings->feature_bg ?? '' );
if ( '' !== $fbg ) { echo "$node .ds-news-feature { background-color: {$fbg}; }\n"; }
$ocolor = $settings->overlay_color ?? 'var(--fl-global-dark-background)';
$otop   = $mix( $ocolor, $settings->overlay_top ?? 15 );
$obot   = $mix( $ocolor, $settings->overlay_bottom ?? 92 );
if ( $otop && $obot ) { echo "$node .ds-news-feature-overlay { background: linear-gradient(180deg, {$otop} 40%, {$obot} 100%); }\n"; }

$badge_bg = $col( $settings->badge_bg ?? '' );
$badge_c  = $col( $settings->badge_color ?? '' );
if ( $badge_bg ) { echo "$node .ds-news-badge { background: {$badge_bg}; }\n"; }
if ( $badge_c )  { echo "$node .ds-news-badge { color: {$badge_c}; }\n"; }
$ftc = $col( $settings->feature_title_color ?? '' );
if ( $ftc ) { echo "$node .ds-news-feature-title { color: {$ftc}; }\n"; }
$exc = $col( $settings->excerpt_color ?? '' );
if ( $exc ) { echo "$node .ds-news-feature-excerpt { color: {$exc}; }\n"; }
$rmc = $col( $settings->readmore_color ?? '' );
if ( $rmc ) { echo "$node .ds-news-readmore { color: {$rmc}; }\n"; }

// ---- Small cards ----
$cbg  = $col( $settings->card_bg ?? '' );
$cbc  = $col( $settings->card_border_color ?? '' );
$cbh  = $col( $settings->card_hover_border ?? '' );
$cbw  = $u( $settings->card_border_width ?? '', 1 );
$crad = ( isset( $settings->card_radius ) && '' !== $settings->card_radius ) ? ( (int) $settings->card_radius ) . 'px' : 'var(--ds-radius)';
$cardrule = '';
if ( $cbg ) { $cardrule .= "background: {$cbg};"; }
$cardrule .= "border-style: solid; border-width: {$cbw}px;";
if ( $cbc ) { $cardrule .= " border-color: {$cbc};"; }
$cardrule .= " border-radius: {$crad};";
echo "$node .ds-news-card { {$cardrule} }\n";
if ( $cbh ) { echo "$node .ds-news--lift .ds-news-card:hover, $node .ds-news-card:hover { border-color: {$cbh}; }\n"; }

$catc = $col( $settings->category_color ?? '' );
if ( $catc ) { echo "$node .ds-news-card-cat { color: {$catc}; }\n"; }
$ctc = $col( $settings->card_title_color ?? '' );
if ( $ctc ) { echo "$node .ds-news-card-title { color: {$ctc}; }\n"; }
$dtc = $col( $settings->date_color ?? '' );
if ( $dtc ) { echo "$node .ds-news-card-date { color: {$dtc}; }\n"; }
$chv = $col( $settings->chevron_color ?? '' );
if ( $chv ) { echo "$node .ds-news-card-chevron { color: {$chv}; }\n"; }

// ---- Card Border (unified across every card layout; opt-in, default keeps layout look) ----
$bs       = preg_replace( '/[^a-z]/', '', (string) ( $settings->card_bd_style ?? 'default' ) );
$cardselB = array( '.ds-news-card', '.ds-news-card2', '.ds-news-feature', '.ds-people-card', '.ds-commit-card', '.ds-commit-row', '.ds-commit-action', '.ds-team-row', '.ds-news-custom-item', '.ds-sponsor-card', '.ds-program-card', '.ds-tourn-card' );
$cardsB   = implode( ',', array_map( function ( $c ) use ( $node ) { return "$node $c"; }, $cardselB ) );
$bdecl    = '';
// !important: per-layout border/radius rules above are more specific (e.g. the
// commit cards' .ds-commit--photo .ds-commit-card). This is opt-in (only emitted
// when the field is set), so layout defaults stay untouched until you choose a value.
if ( $bs && 'default' !== $bs ) {
	if ( 'none' === $bs ) {
		$bdecl .= 'border:0 !important;';
	} else {
		$bw = $u( $settings->card_bd_width ?? '', 1 );
		$bc = $col( $settings->card_bd_color ?? '' ) ?: 'var(--fl-global-line-color)';
		$bdecl .= "border-style:{$bs} !important;border-width:{$bw}px !important;border-color:{$bc} !important;";
	}
}
if ( isset( $settings->card_bd_radius ) && '' !== trim( (string) $settings->card_bd_radius ) ) {
	$bdecl .= 'border-radius:' . (int) $settings->card_bd_radius . 'px !important;';
}
if ( '' !== $bdecl ) { echo "$cardsB{{$bdecl}}\n"; }

// ---- Header text ----
$hc = $col( $settings->heading_color ?? '' );
if ( $hc ) { echo "$node .ds-news-heading { color: {$hc}; }\n"; }
$hac = $col( $settings->heading_accent_color ?? '' );
if ( $hac ) { echo "$node .ds-news-accent { color: {$hac}; }\n"; }

// ---- "See all" button: default to the Theme Setting global Button ----
$btn = $settings->btn_global ?? 'yes';
$gs  = class_exists( 'FLBuilderGlobalStyles' ) ? FLBuilderGlobalStyles::get_settings( false ) : null;
if ( 'yes' === $btn && $gs ) {
	// FULL theme Button sync (bg, text, hover, border + radius + shadow, typography).
	DS_Module_UI::global_button_css( "$node .ds-news-seeall" );
} elseif ( 'accent' === $btn ) {
	$ac = $col( $settings->heading_accent_color ?? 'var(--fl-global-accent)' );
	echo "$node .ds-news-seeall { background: {$ac} !important; color: var(--fl-global-white) !important; }\n";
} else { // 'dark' (or fallback)
	$dbg = $col( $settings->btn_dark_bg ?? 'var(--fl-global-dark-background)' );
	$dtx = $col( $settings->btn_dark_color ?? 'var(--fl-global-white)' );
	if ( $dbg ) { echo "$node .ds-news-seeall { background: {$dbg} !important; }\n"; }
	if ( $dtx ) { echo "$node .ds-news-seeall { color: {$dtx} !important; }\n"; }
}

// ---- Tournament card button: FULL theme Button sync (incl. border + shadow). ----
if ( $gs && ( $settings->card_layout ?? '' ) === 'tournament' ) {
	DS_Module_UI::global_button_css( "$node .ds-tourn-btn", "$node .ds-tourn-btn:hover, $node .ds-tourn-card:hover .ds-tourn-btn" );
}

// ---- Spacing: padding on the wrap, margin on the section (deferred) ----
if ( class_exists( 'FLBuilderCSS' ) ) {
	FLBuilderCSS::dimension_field_rule( array(
		'settings'     => $settings,
		'setting_name' => 'padding',
		'selector'     => "$node .ds-news-wrap",
		'unit'         => 'px',
		'props'        => array( 'padding-top' => 'padding_top', 'padding-right' => 'padding_right', 'padding-bottom' => 'padding_bottom', 'padding-left' => 'padding_left' ),
	) );
	FLBuilderCSS::dimension_field_rule( array(
		'settings'     => $settings,
		'setting_name' => 'margin',
		'selector'     => "$node .ds-news",
		'unit'         => 'px',
		'props'        => array( 'margin-top' => 'margin_top', 'margin-right' => 'margin_right', 'margin-bottom' => 'margin_bottom', 'margin-left' => 'margin_left' ),
	) );
}

// ---- Tournament cards (events) ----
if ( ( $settings->card_layout ?? '' ) === 'tournament' ) {
	$tc = max( 1, (int) ( $settings->tn_cols ?? 3 ) );
	$tg = $u( $settings->tn_gap ?? '', 28 );
	echo "$node .ds-tourn-grid{grid-template-columns:repeat({$tc},1fr);gap:{$tg}px;}\n";
	echo "@media(max-width:1024px){ $node .ds-tourn-grid{grid-template-columns:repeat(2,1fr);} }\n";
	echo "@media(max-width:600px){ $node .ds-tourn-grid{grid-template-columns:1fr;} }\n";
	$ar = preg_replace( '#[^0-9/ ]#', '', (string) ( $settings->tn_img_ratio ?? '16 / 10' ) ); if ( '' === trim( $ar ) ) { $ar = '16 / 10'; }
	echo "$node .ds-tourn-img{aspect-ratio:$ar;}\n";
	$ls = $u( $settings->tn_logo_size ?? '', 96 );
	$trad = ( isset( $settings->tn_radius ) && '' !== $settings->tn_radius ) ? ( (int) $settings->tn_radius ) . 'px' : 'var(--ds-radius)';
	$tpad = $u( $settings->tn_pad ?? '', 24 );
	if ( ( $settings->tn_style ?? 'overlap' ) === 'event' ) {
		// Event Card: flat white card (radius on the card, image square inside),
		// left-aligned details, plain centred logo (no white circle chrome).
		echo "$node .ds-tourn-card{border-radius:{$trad};}\n";
		echo "$node .ds-tourn-logo{width:{$ls}px;height:{$ls}px;border-radius:0;}\n";
		echo "$node .ds-tourn-body{margin:0;padding:{$tpad}px;text-align:left;align-items:stretch;}\n";
	} else {
		$lsh = ( ( $settings->tn_logo_shape ?? 'circle' ) === 'square' ) ? '14px' : '50%';
		echo "$node .ds-tourn-logo{width:{$ls}px;height:{$ls}px;border-radius:{$lsh};}\n";
		echo "$node .ds-tourn-img,$node .ds-tourn-body{border-radius:{$trad};}\n";
		$ov = $u( $settings->tn_overlap ?? '', 48 ); $ins = $u( $settings->tn_inset ?? '', 18 );
		$tal = ( ( $settings->tn_align ?? 'center' ) === 'left' ) ? 'left' : 'center'; $tai = ( 'left' === $tal ) ? 'flex-start' : 'center';
		echo "$node .ds-tourn-body{margin:-{$ov}px {$ins}px 0;padding:{$tpad}px;text-align:{$tal};align-items:{$tai};}\n";
	}
	$c = $col( $settings->tn_card_bg ?? '' );    if ( $c ) { echo "$node .ds-tourn-body{background:{$c};}\n"; }
	$c = $col( $settings->tn_title_color ?? '' ); if ( $c ) { echo "$node .ds-tourn-title{color:{$c};}\n"; }
	$c = $col( $settings->tn_meta_color ?? '' );  if ( $c ) { echo "$node .ds-tourn-date,$node .ds-tourn-loc{color:{$c};}\n"; }
	$c = $col( $settings->tn_icon_color ?? '' );  if ( $c ) { echo "$node .ds-tourn-date svg,$node .ds-tourn-loc svg{color:{$c};}\n"; }
}

// ---- Typography (deferred; flushed by FLBuilderCSS::render()) ----
if ( class_exists( 'FLBuilderCSS' ) ) {
	$typo = array(
		'heading_typography'       => '.ds-news-heading',
		'feature_title_typography' => '.ds-news-feature-title',
		'excerpt_typography'       => '.ds-news-feature-excerpt',
		'badge_typography'         => '.ds-news-badge',
		'card_title_typography'    => '.ds-news-card-title',
		'card_cat_typography'      => '.ds-news-card-cat',
		'card_date_typography'     => '.ds-news-card-date',
		// People / Team (Staff, Commitments, Team layouts)
		'staff_name_typo'          => '.ds-people-name',
		'staff_role_typo'          => '.ds-people-role',
		'commit_name_typo'         => '.ds-people-name',
		'commit_meta_typo'         => '.ds-people-role',
		'team_name_typo'           => '.ds-team-name',
		// Custom Program card
		'pg_date_typo'             => '.ds-program-date',
		'pg_sub_typo'              => '.ds-program-sub',
		'pg_title_typo'            => '.ds-program-title',
		'pg_desc_typo'             => '.ds-program-desc',
		'pg_btn_typo'              => '.ds-program-btn',
	);
	foreach ( $typo as $key => $sel ) {
		if ( ! empty( $settings->{$key} ) ) {
			FLBuilderCSS::typography_field_rule( array( 'settings' => $settings, 'setting_name' => $key, 'selector' => "$node $sel" ) );
		}
	}
}

// ---- Style 2: uniform pill-card grid (rules inert for Style 1; classes differ) ----
$cols2 = $u( $settings->cols2 ?? '', 4 );
$gap2  = $u( $settings->gap2 ?? '', 20 );
echo "$node .ds-news-grid2 { grid-template-columns: repeat({$cols2},1fr); gap: {$gap2}px; }\n";
$cols2M = $u( $settings->cols2_medium ?? '', min( $cols2, 2 ) );
$gap2M  = $u( $settings->gap2_medium ?? '', $gap2 );
echo "@media (max-width:{$bpm}px){ $node .ds-news-grid2 { grid-template-columns: repeat({$cols2M},1fr); gap: {$gap2M}px; } }\n";
$cols2R = $u( $settings->cols2_responsive ?? '', 1 );
$gap2R  = $u( $settings->gap2_responsive ?? '', max( 14, $gap2 ) );
echo "@media (max-width:{$bpr}px){ $node .ds-news-grid2 { grid-template-columns: repeat({$cols2R},1fr); gap: {$gap2R}px; } }\n";

$cmin = $u( $settings->card2_min ?? '', 210 );
echo "$node .ds-news-card2-img { height: {$cmin}px; }\n";

// ---- Custom Loop Layout: columns + gap (responsive) ----
$lcols = max( 1, $u( $settings->loop_cols ?? '', 3 ) );
$lgap  = $u( $settings->loop_gap ?? '', 24 );
echo "$node .ds-news-loopgrid { grid-template-columns: repeat({$lcols},1fr); gap: {$lgap}px; }\n";
$lcolsM = $u( $settings->loop_cols_medium ?? '', min( $lcols, 2 ) );
echo "@media (max-width:{$bpm}px){ $node .ds-news-loopgrid { grid-template-columns: repeat({$lcolsM},1fr); } }\n";
$lcolsR = $u( $settings->loop_cols_responsive ?? '', 1 );
echo "@media (max-width:{$bpr}px){ $node .ds-news-loopgrid { grid-template-columns: repeat({$lcolsR},1fr); } }\n";
if ( '' !== ( $settings->card2_min_medium ?? '' ) )     { echo "@media (max-width:{$bpm}px){ $node .ds-news-card2-img { height: " . (int) $settings->card2_min_medium . "px; } }\n"; }
if ( '' !== ( $settings->card2_min_responsive ?? '' ) ) { echo "@media (max-width:{$bpr}px){ $node .ds-news-card2-img { height: " . (int) $settings->card2_min_responsive . "px; } }\n"; }

$crad = ( isset( $settings->card2_radius ) && '' !== $settings->card2_radius ) ? ( (int) $settings->card2_radius ) . 'px' : 'var(--ds-radius)';
$cbw  = $u( $settings->card2_border_w ?? '', 1 );
$cbg2 = $col( $settings->card2_bg ?? '' );
$cb2  = $col( $settings->card2_border ?? '' );
echo "$node .ds-news-card2 { border-radius: {$crad}; border-style: solid; border-width: {$cbw}px;" . ( $cbg2 ? " background: {$cbg2};" : '' ) . ( $cb2 ? " border-color: {$cb2};" : '' ) . " }\n";
$pbg = $col( $settings->pill2_bg ?? '' ); $pc = $col( $settings->pill2_color ?? '' );
if ( $pbg || $pc ) { echo "$node .ds-news-card2-pill {" . ( $pbg ? " background: {$pbg};" : '' ) . ( $pc ? " color: {$pc};" : '' ) . " }\n"; }
$t2 = $col( $settings->title2_color ?? '' ); if ( '' !== $t2 ) { echo "$node .ds-news-card2-title { color: {$t2}; }\n"; }
$d2 = $col( $settings->date2_color ?? '' ); if ( '' !== $d2 ) { echo "$node .ds-news-card2-date { color: {$d2}; }\n"; }
$rm2 = $col( $settings->readmore2_color ?? '' ); if ( '' !== $rm2 ) { echo "$node .ds-news-card2-more { color: {$rm2}; }\n"; }


// ---- Header divider (line between header and content) ----
$hd = $settings->header_divider ?? 'none';
if ( in_array( $hd, array( 'solid', 'dashed', 'dotted' ), true ) && ( $settings->show_header ?? 'yes' ) === 'yes' ) {
	$hdw = $u( $settings->header_divider_w ?? '', 1 );
	$hdc = $col( $settings->header_divider_color ?? '' ) ?: 'var(--fl-global-line-color)';
	$hdg = $u( $settings->header_divider_gap ?? '', 24 );
	echo "$node .ds-news-head { border-bottom: {$hdw}px {$hd} {$hdc}; padding-bottom: {$hdg}px; }\n";
}

// ---- Shared Border & Divider (DS_Module_UI) ----
if ( class_exists( 'DS_Module_UI' ) ) {
	DS_Module_UI::emit_css( $node, $settings, '.ds-news', '.ds-news-head' );
}

// ---- Staff cards (content_type = staff) ----
if ( ( $settings->card_layout ?? '' ) === 'staff_card' ) {
	$scols = max( 1, $u( $settings->staff_cols ?? '', 4 ) );
	$sgap  = $u( $settings->staff_gap ?? '', 24 );
	echo "$node .ds-people-grid { grid-template-columns: repeat({$scols},1fr); gap: {$sgap}px; }\n";
	$scolsM = max( 1, $u( $settings->staff_cols_medium ?? '', min( $scols, 2 ) ) );
	echo "@media (max-width:{$bpm}px){ $node .ds-people-grid { grid-template-columns: repeat({$scolsM},1fr); } }\n";
	$scolsR = max( 1, $u( $settings->staff_cols_responsive ?? '', 1 ) );
	echo "@media (max-width:{$bpr}px){ $node .ds-people-grid { grid-template-columns: repeat({$scolsR},1fr); } }\n";

	$sratio = preg_replace( '/[^0-9 \/]/', '', (string) ( $settings->staff_photo_ratio ?? '3 / 4' ) ) ?: '3 / 4';
	echo "$node .ds-people-photo { aspect-ratio: {$sratio}; }\n";

	$scbg = $col( $settings->staff_card_bg ?? '' );
	if ( '' !== $scbg ) { echo "$node .ds-people-card { background: {$scbg}; }\n"; }
	$scrad = ( isset( $settings->staff_card_radius ) && '' !== $settings->staff_card_radius ) ? ( (int) $settings->staff_card_radius ) . 'px' : 'var(--ds-radius)';
	echo "$node .ds-people-card { border-radius: {$scrad}; }\n";

	$snc = $col( $settings->staff_name_color ?? '' ); if ( '' !== $snc ) { echo "$node .ds-people-name { color: {$snc}; }\n"; }
	$src = $col( $settings->staff_role_color ?? '' ); if ( '' !== $src ) { echo "$node .ds-people-role { color: {$src}; }\n"; }

	$sib = $col( $settings->staff_ico_bg ?? '' );    if ( '' !== $sib ) { echo "$node .ds-people-ico { background: {$sib}; }\n"; }
	// !important: .ds-people-ico is an <a>; the theme's content-link rule (.fl-builder-content a) outranks a node+class selector otherwise.
	$sic = $col( $settings->staff_ico_color ?? '' ) ?: 'var(--fl-global-white)';
	echo "$node .ds-people-ico { color: {$sic} !important; }\n";
	$sih = $col( $settings->staff_ico_hover ?? '' ); if ( '' !== $sih ) { echo "$node .ds-people-ico:hover { background: {$sih}; }\n"; }
	$sis = ( isset( $settings->staff_ico_size ) && '' !== $settings->staff_ico_size ) ? (int) $settings->staff_ico_size : 0;
	if ( $sis > 0 ) {
		$glyph = max( 10, (int) round( $sis * 0.47 ) ); // keep the glyph proportional to the 38/18 base
		echo "$node .ds-people-ico { width: {$sis}px; height: {$sis}px; }\n";
		echo "$node .ds-people-ico svg { width: {$glyph}px; height: {$glyph}px; }\n";
	}
}

// ---- Commitments / Athletes (content_type = athlete) ----
if ( in_array( $settings->card_layout ?? '', array( 'athlete_photo', 'athlete_logo', 'athlete_action' ), true ) ) {
	$ccols = max( 1, $u( $settings->commit_cols ?? '', 4 ) );
	$cgap  = $u( $settings->commit_gap ?? '', 24 );
	echo "$node .ds-people-grid { grid-template-columns: repeat({$ccols},1fr); gap: {$cgap}px; }\n";
	$ccolsM = max( 1, $u( $settings->commit_cols_medium ?? '', min( $ccols, 2 ) ) );
	echo "@media (max-width:{$bpm}px){ $node .ds-people-grid { grid-template-columns: repeat({$ccolsM},1fr); } }\n";
	$ccolsR = max( 1, $u( $settings->commit_cols_responsive ?? '', 1 ) );
	echo "@media (max-width:{$bpr}px){ $node .ds-people-grid { grid-template-columns: repeat({$ccolsR},1fr); } }\n";

	$cratio = preg_replace( '/[^0-9 \/]/', '', (string) ( $settings->commit_photo_ratio ?? '3 / 4' ) ) ?: '3 / 4';
	echo "$node .ds-commit--photo .ds-people-photo, $node .ds-commit--action .ds-people-photo { aspect-ratio: {$cratio}; }\n";

	$crad = ( isset( $settings->commit_radius ) && '' !== $settings->commit_radius ) ? ( (int) $settings->commit_radius ) . 'px' : 'var(--ds-radius)';
	echo "$node .ds-commit--photo .ds-commit-card, $node .ds-commit--action .ds-commit-action, $node .ds-commit--logo .ds-commit-row { border-radius: {$crad}; }\n";

	$cbg = $col( $settings->commit_card_bg ?? '' );
	if ( '' !== $cbg ) { echo "$node .ds-commit--photo .ds-commit-card, $node .ds-commit--action .ds-commit-action { background: {$cbg}; }\n"; }
	$cdbg = $col( $settings->commit_dark_bg ?? '' );
	if ( '' !== $cdbg ) { echo "$node .ds-commit--logo .ds-commit-row { background: {$cdbg}; }\n"; }

	$cbc = $col( $settings->commit_border_color ?? '' );
	$cbw = $u( $settings->commit_border_width ?? '', 2 );
	if ( '' !== $cbc ) { echo "$node .ds-commit--photo .ds-commit-card { border: {$cbw}px solid {$cbc}; }\n"; }

	$cnc = $col( $settings->commit_name_color ?? '' );
	if ( '' !== $cnc ) { echo "$node .ds-commit--photo .ds-people-name, $node .ds-commit--action .ds-people-name { color: {$cnc}; }\n"; }
	$csc = $col( $settings->commit_school_color ?? '' );
	if ( '' !== $csc ) { echo "$node .ds-commit--photo .ds-people-role, $node .ds-commit--action .ds-people-role { color: {$csc}; }\n"; }
}

// ---- Team list (content_type = team) ----
if ( ( $settings->card_layout ?? '' ) === 'team_list' ) {
	$tgap = $u( $settings->team_gap ?? '', 14 );
	echo "$node .ds-teams-list { gap: {$tgap}px; }\n";
	$trbg = $col( $settings->team_row_bg ?? '' );
	$trbc = $col( $settings->team_row_border ?? '' );
	$trbw = $u( $settings->team_row_border_w ?? '', 2 );
	$trad = ( isset( $settings->team_row_radius ) && '' !== $settings->team_row_radius ) ? ( (int) $settings->team_row_radius ) . 'px' : 'var(--ds-radius)';
	$rule = '';
	if ( '' !== $trbg ) { $rule .= "background: {$trbg};"; }
	$rule .= "border-width: {$trbw}px; border-style: solid;";
	if ( '' !== $trbc ) { $rule .= " border-color: {$trbc};"; }
	$rule .= " border-radius: {$trad};";
	echo "$node .ds-team-row { {$rule} }\n";
	$tnc = $col( $settings->team_name_color ?? '' );
	if ( '' !== $tnc ) { echo "$node .ds-team-name { color: {$tnc}; }\n"; }
	$tbb = $col( $settings->team_btn_bg ?? '' );
	if ( '' !== $tbb ) { echo "$node .ds-team-btn { background: {$tbb}; }\n"; }
	$tbc2 = $col( $settings->team_btn_color ?? '' );
	if ( '' !== $tbc2 ) { echo "$node .ds-team-btn { color: {$tbc2} !important; }\n"; }
}

// ---- Display: Carousel / Pagination ----
$disp = $settings->display ?? 'grid';
if ( 'carousel' === $disp ) {
	$pv = max( 1, $u( $settings->car_per_view ?? '', 4 ) );
	$cg = $u( $settings->car_gap ?? '', 24 );
	echo "$node .ds-loopcar-track { --lc-pv: {$pv}; --lc-gap: {$cg}px; }\n";
	$pvM = max( 1, $u( $settings->car_per_view_medium ?? '', min( $pv, 2 ) ) );
	echo "@media (max-width:{$bpm}px){ $node .ds-loopcar-track { --lc-pv: {$pvM}; } }\n";
	$pvR = max( 1, $u( $settings->car_per_view_responsive ?? '', 1 ) );
	echo "@media (max-width:{$bpr}px){ $node .ds-loopcar-track { --lc-pv: {$pvR}; } }\n";
	$cnav = $col( $settings->car_nav_color ?? '' );
	if ( '' !== $cnav ) {
		echo "$node .ds-loopcar-nav:hover { background: {$cnav}; }\n";
		echo "$node .ds-loopcar-dot.is-active { background: {$cnav}; }\n";
	}
}
if ( 'paginated' === $disp ) {
	$pgc = $col( $settings->pag_color ?? '' );
	$pgt = $col( $settings->pag_text_color ?? '' );
	if ( '' !== $pgc ) {
		echo "$node .ds-looppage-page.is-active { background: {$pgc}; border-color: {$pgc}; }\n";
		echo "$node .ds-looppage-page:hover { border-color: {$pgc}; }\n";
		echo "$node .ds-looppage-loadmore { background: {$pgc}; }\n";
	}
	if ( '' !== $pgt ) {
		echo "$node .ds-looppage-page.is-active { color: {$pgt}; }\n";
		echo "$node .ds-looppage-loadmore { color: {$pgt} !important; }\n";
	}
}

// ---- Sponsor Grid options ----
if ( ( $settings->card_layout ?? '' ) === 'sponsor' ) {
	$spc = max( 1, (int) ( $settings->sp_cols ?? 4 ) );
	$spg = $u( $settings->sp_gap ?? '', 24 );
	echo "$node .ds-sponsor-grid{grid-template-columns:repeat({$spc},1fr);gap:{$spg}px;}\n";
	$splh = $u( $settings->sp_logo_h ?? '', 70 );
	echo "$node .ds-sponsor-logo{height:{$splh}px;} $node .ds-sponsor-logo img{max-height:{$splh}px;}\n";
	$sppad = $u( $settings->sp_pad ?? '', 24 );
	$spbg  = $col( $settings->sp_card_bg ?? '' );
	echo "$node .ds-sponsor-card{padding:{$sppad}px;" . ( $spbg ? "background:{$spbg};" : '' ) . "}\n";
	$spn = $col( $settings->sp_name_color ?? '' ); if ( $spn ) { echo "$node .ds-sponsor-name{color:{$spn};}\n"; }
	$spd = $col( $settings->sp_desc_color ?? '' ); if ( $spd ) { echo "$node .ds-sponsor-desc,$node .ds-sponsor-desc p{color:{$spd};}\n"; }
	if ( ( $settings->sp_grayscale ?? 'no' ) === 'yes' ) {
		echo "$node .ds-sponsor-logo img{filter:grayscale(1);opacity:.7;transition:filter .3s ease,opacity .3s ease;} $node .ds-sponsor-card:hover .ds-sponsor-logo img{filter:none;opacity:1;}\n";
	}
}

// ---- Custom Program cards ----
if ( ( $settings->card_layout ?? '' ) === 'program' ) {
	$pgc = max( 1, (int) ( $settings->pg_cols ?? 3 ) );
	$pgg = $u( $settings->pg_gap ?? '', 24 );
	echo "$node .ds-program-grid{grid-template-columns:repeat({$pgc},1fr);gap:{$pgg}px;}\n";
	echo "@media(max-width:1024px){ $node .ds-program-grid{grid-template-columns:repeat(2,1fr);} }\n";
	echo "@media(max-width:600px){ $node .ds-program-grid{grid-template-columns:1fr;} }\n";
	if ( ( $settings->pg_same_height ?? 'yes' ) === 'yes' ) {
		echo "$node .ds-program-grid{align-items:stretch;} $node .ds-program-card{height:100%;}\n";
	} else {
		echo "$node .ds-program-grid{align-items:start;} $node .ds-program-card{height:auto;}\n";
	}
	$pgpad = $u( $settings->pg_pad ?? '', 28 );
	$pgbg  = $col( $settings->pg_card_bg ?? '' );
	echo "$node .ds-program-card{padding:{$pgpad}px;" . ( $pgbg ? "background:{$pgbg};" : '' ) . "}\n";
	$pal = ( ( $settings->pg_align ?? 'left' ) === 'center' ) ? 'center' : 'left';
	$pai = ( 'center' === $pal ) ? 'center' : 'flex-start';
	echo "$node .ds-program-card{text-align:{$pal};align-items:{$pai};}\n";
	$pih = $u( $settings->pg_img_h ?? '', 180 );
	echo "$node .ds-program-media{height:{$pih}px;}\n";
	$pis = $u( $settings->pg_icon_size ?? '', 40 );
	echo "$node .ds-program-ico{font-size:{$pis}px;line-height:1;}\n";
	$pc = $col( $settings->pg_icon_color ?? '' );  if ( $pc ) { echo "$node .ds-program-ico{color:{$pc};}\n"; }
	$pc = $col( $settings->pg_date_color ?? '' );  if ( $pc ) { echo "$node .ds-program-date{color:{$pc};}\n"; }
	$pc = $col( $settings->pg_sub_color ?? '' );   if ( $pc ) { echo "$node .ds-program-sub{color:{$pc};}\n"; }
	$pc = $col( $settings->pg_title_color ?? '' ); if ( $pc ) { echo "$node .ds-program-title{color:{$pc};}\n"; }
	$pc = $col( $settings->pg_desc_color ?? '' );  if ( $pc ) { echo "$node .ds-program-desc{color:{$pc};}\n"; }
	if ( ( $settings->pg_btn_style ?? 'theme' ) !== 'custom' ) {
		// FULL theme Button sync (bg, text, hover, border + radius + shadow, typography).
		DS_Module_UI::global_button_css(
			"$node .ds-programs .ds-program-card .ds-program-btn",
			"$node .ds-programs .ds-program-card .ds-program-btn:hover, $node .ds-programs .ds-program-card:hover .ds-program-btn"
		);
	}
	if ( ( $settings->pg_btn_style ?? 'theme' ) === 'custom' ) {
		$bbg = $col( $settings->pg_btn_bg ?? '' ); $btc = $col( $settings->pg_btn_color ?? '' );
		$bdecl = ''; if ( $bbg ) { $bdecl .= "background:{$bbg};"; } if ( $btc ) { $bdecl .= "color:{$btc};"; }
		$brad = $settings->pg_btn_radius ?? ''; if ( '' !== $brad ) { $bdecl .= 'border-radius:' . (int) $brad . 'px;'; }
		if ( '' !== $bdecl ) { echo "$node .ds-programs .ds-program-card .ds-program-btn{{$bdecl}}\n"; }
		$bhb = $col( $settings->pg_btn_hover_bg ?? '' ); $bhc = $col( $settings->pg_btn_hover_color ?? '' );
		$bhd = ''; if ( $bhb ) { $bhd .= "background:{$bhb};"; } if ( $bhc ) { $bhd .= "color:{$bhc};"; }
		if ( '' !== $bhd ) { echo "$node .ds-programs .ds-program-card .ds-program-btn:hover,$node .ds-programs .ds-program-card:hover .ds-program-btn{{$bhd}}\n"; }
	}
}

/* ---- Outline text ({outline}...{/outline}) per-module override: blank = Theme Setting. ---- */
$oc_ov = DS_Module_UI::color( $settings->outline_color ?? '' );
if ( '' !== $oc_ov ) { echo "$node { --ds-outline-c: {$oc_ov}; }\n"; }
if ( isset( $settings->outline_width ) && '' !== $settings->outline_width ) { echo "$node { --ds-outline-w: " . max( 1, (int) $settings->outline_width ) . "px; }\n"; }
