<?php
/**
 * Shared renderer for the manual "Program Cards" list.
 *
 * WHY THIS EXISTS
 * ---------------
 * The program card list is a MANUAL list of hand-built cards. It never queries a
 * post type, so it does not belong in the Post Loop module (whose whole contract
 * is "loop over posts of type X"). It now lives in the CTA module instead.
 *
 * Both modules render through this ONE class, so:
 *   - ds-post-loop `card_layout = program`  (legacy instances, never removed)
 *   - ds-cta       `cta_style   = style6`   (where new work is built)
 * produce byte-identical markup by construction rather than by testing. That is
 * what makes migrating existing instances safe, and what lets un-migrated sites
 * keep working forever.
 *
 * Settings contract (identical keys in both modules, so migration is a verbatim
 * carry): show_header, heading, show_button, button_text, button_link, display
 * (+ pagination/carousel keys), programs[], pg_*.
 *
 * @package ds-toolkit
 * @since   1.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DS_Program_Cards {

	/**
	 * Register the per-card repeater form.
	 *
	 * The Post Loop module registers `ds_program_form` itself for its legacy
	 * layout. A host module must not depend on that, because Post Loop can be
	 * switched off in DS Toolkit settings while CTA stays on — the repeater would
	 * then point at an unregistered form. Re-registering the same id with the same
	 * config is a harmless overwrite, so callers can always call this.
	 */
	public static function register_form() {
		if ( ! class_exists( 'FLBuilder' ) ) {
			return;
		}
		FLBuilder::register_settings_form(
			'ds_program_form',
			array(
				'title' => __( 'Program', 'ds-toolkit' ),
				'tabs'  => array(
					'general' => array(
						'title'    => '',
						'sections' => array(
							'general' => array(
								'title'  => '',
								'fields' => array(
									'prog_icon'       => array( 'type' => 'icon', 'label' => __( 'Icon', 'ds-toolkit' ), 'show_remove' => true, 'help' => __( 'Optional. Shown instead of the image when set.', 'ds-toolkit' ) ),
									'prog_image'      => array( 'type' => 'photo', 'label' => __( 'Image', 'ds-toolkit' ), 'show_remove' => true, 'connections' => array( 'photo' ), 'help' => __( 'Used when no icon is set.', 'ds-toolkit' ) ),
									'prog_date'       => array( 'type' => 'text', 'label' => __( 'Date', 'ds-toolkit' ), 'connections' => array( 'string' ) ),
									'prog_subheading' => array( 'type' => 'text', 'label' => __( 'Sub-heading', 'ds-toolkit' ), 'connections' => array( 'string' ) ),
									'prog_title'      => array( 'type' => 'text', 'label' => __( 'Title', 'ds-toolkit' ), 'connections' => array( 'string' ) ),
									'prog_desc'       => array( 'type' => 'editor', 'label' => __( 'Description', 'ds-toolkit' ), 'media_buttons' => false, 'rows' => 6, 'wpautop' => false, 'connections' => array( 'string' ) ),
									'prog_url'        => array( 'type' => 'link', 'label' => __( 'Link', 'ds-toolkit' ), 'show_target' => true, 'connections' => array( 'url' ) ),
									'prog_btn'        => array( 'type' => 'text', 'label' => __( 'Button Text', 'ds-toolkit' ), 'help' => __( 'Optional. With text a button shows; blank makes the whole card the link.', 'ds-toolkit' ) ),
								),
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Heading markup: safe inline HTML, {a}..{/a} -> accent span,
	 * {outline}..{/outline} -> outline span, newlines -> <br>.
	 *
	 * @param string $raw Raw heading text.
	 * @return string
	 */
	public static function heading_html( $raw ) {
		$h = DS_Module_UI::inline( (string) $raw );
		$h = str_replace( array( '{a}', '{/a}' ), array( '<span class="ds-news-accent">', '</span>' ), $h );
		$h = str_replace( array( '{outline}', '{/outline}' ), array( '<span class="ds-outline-text">', '</span>' ), $h );
		return nl2br( $h );
	}

	/**
	 * Optional header row: {a}accent{/a} heading + "see all" button.
	 *
	 * @param object $s Module settings.
	 */
	public static function head( $s ) {
		if ( ( $s->show_header ?? 'yes' ) !== 'yes' ) {
			return;
		}
		$show_btn = ( $s->show_button ?? 'yes' ) === 'yes';
		$has_h    = ! empty( $s->heading );
		if ( ! $has_h && ! $show_btn ) {
			return;
		}

		echo '<div class="ds-news-head">';
		if ( $has_h ) {
			echo '<h2 class="ds-news-heading">' . self::heading_html( $s->heading ) . '</h2>';
		}
		if ( $show_btn ) {
			$txt = trim( (string) ( $s->button_text ?? '' ) );
			if ( '' !== $txt ) {
				list( $url, $target ) = DS_Card::link_parts( $s->button_link ?? '' );
				$rel = '_blank' === $target ? ' rel="noopener noreferrer"' : '';
				echo '<a class="ds-news-seeall" href="' . $url . '" target="' . esc_attr( $target ) . '"' . $rel . '>' . esc_html( $txt ) . '</a>';
			}
		}
		echo '</div>';
	}

	/**
	 * Items wrapper open (grid / paginated / carousel).
	 *
	 * @param object $s           Module settings.
	 * @param string $grid_class  Grid CSS class.
	 */
	public static function open( $s, $grid_class ) {
		$dmode = $s->display ?? 'grid';
		if ( 'paginated' === $dmode ) {
			$pp = max( 1, (int) ( $s->pag_per_page ?? 6 ) );
			$pt = in_array( $s->pag_type ?? 'numbers', array( 'numbers', 'loadmore' ), true ) ? ( $s->pag_type ?? 'numbers' ) : 'numbers';
			echo '<div class="ds-looppage" data-perpage="' . $pp . '" data-pagtype="' . esc_attr( $pt ) . '"><div class="' . esc_attr( $grid_class ) . '">';
			return;
		}
		if ( 'carousel' !== $dmode ) {
			echo '<div class="' . esc_attr( $grid_class ) . '">';
			return;
		}
		$data = ' data-autoplay="' . ( ( $s->car_autoplay ?? 'no' ) === 'yes' ? 'yes' : 'no' ) . '"'
			. ' data-interval="' . max( 1, (int) ( $s->car_interval ?? 5 ) ) . '"'
			. ' data-loop="' . ( ( $s->car_loop ?? 'yes' ) === 'yes' ? 'yes' : 'no' ) . '"'
			. ' data-pause="' . ( ( $s->car_pause_hover ?? 'yes' ) === 'yes' ? 'yes' : 'no' ) . '"'
			. ' data-drag="' . ( ( $s->car_drag ?? 'yes' ) === 'yes' ? 'yes' : 'no' ) . '"'
			. ' data-speed="' . max( 100, (int) ( $s->car_speed ?? 500 ) ) . '"';
		echo '<div class="ds-loopcar">';
		if ( ( $s->car_arrows ?? 'yes' ) === 'yes' ) {
			echo '<button type="button" class="ds-loopcar-nav ds-loopcar-nav--prev" aria-label="' . esc_attr__( 'Previous', 'ds-toolkit' ) . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg></button>';
			echo '<button type="button" class="ds-loopcar-nav ds-loopcar-nav--next" aria-label="' . esc_attr__( 'Next', 'ds-toolkit' ) . '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg></button>';
		}
		echo '<div class="ds-loopcar-viewport"><div class="ds-loopcar-track ' . esc_attr( $grid_class ) . '"' . $data . '>';
	}

	/**
	 * Items wrapper close (matches open()).
	 *
	 * @param object $s Module settings.
	 */
	public static function close( $s ) {
		$dmode = $s->display ?? 'grid';
		if ( 'paginated' === $dmode ) {
			echo '</div><div class="ds-looppage-nav"></div></div>';
			return;
		}
		if ( 'carousel' !== $dmode ) {
			echo '</div>';
			return;
		}
		echo '</div></div>';
		if ( ( $s->car_dots ?? 'yes' ) === 'yes' ) {
			echo '<div class="ds-loopcar-dots"></div>';
		}
		echo '</div>';
	}

	/**
	 * Render the whole Program Cards section.
	 *
	 * @param object $s          Module settings.
	 * @param string $empty_hint Builder-only hint shown when there are no cards.
	 */
	public static function render( $s, $empty_hint = '' ) {
		$items = ( isset( $s->programs ) && is_array( $s->programs ) ) ? $s->programs : array();

		// A custom Button Radius opts out of the theme Button Shape (clip-path) so the
		// radius shows; blank keeps the theme shape (button follows the theme by default).
		$btn_cls = 'ds-program-btn' . ( ( $s->pg_btn_style ?? 'theme' ) === 'custom' ? ' ds-no-clip' : '' );

		echo '<section class="ds-news ds-programs"><div class="ds-news-wrap">';
		self::head( $s );

		if ( empty( $items ) ) {
			if ( FLBuilderModel::is_builder_active() ) {
				if ( '' === $empty_hint ) {
					$empty_hint = __( 'No program cards yet. Add them in the Program Cards list.', 'ds-toolkit' );
				}
				echo '<p style="padding:14px;opacity:.7">' . esc_html( $empty_hint ) . '</p>';
			}
			echo '</div></section>';
			return;
		}

		self::open( $s, 'ds-program-grid' );

		foreach ( $items as $it ) {
			$it    = (object) $it;
			$icon  = trim( (string) ( $it->prog_icon ?? '' ) );
			$img   = DS_Card::photo_url( $it->prog_image ?? '' );
			$date  = trim( (string) ( $it->prog_date ?? '' ) );
			$sub   = trim( (string) ( $it->prog_subheading ?? '' ) );
			$title = trim( (string) ( $it->prog_title ?? '' ) );
			$desc  = trim( (string) ( $it->prog_desc ?? '' ) );
			list( $url, $target ) = DS_Card::link_parts( $it->prog_url ?? '' );
			$hasurl = ( '' !== $url && '#' !== $url );
			$tgt    = ( '_blank' === $target ) ? ' target="_blank" rel="noopener"' : '';
			$btn    = trim( (string) ( $it->prog_btn ?? '' ) );

			echo '<div class="ds-program-card">';
			if ( $hasurl && '' === $btn ) {
				echo '<a class="ds-card-link" href="' . esc_url( $url ) . '"' . $tgt . ' aria-label="' . esc_attr( $title ) . '"></a>';
			}
			if ( '' !== $icon ) {
				echo '<span class="ds-program-ico"><i class="' . esc_attr( $icon ) . '" aria-hidden="true"></i></span>';
			} elseif ( '' !== $img ) {
				echo '<div class="ds-program-media"><img src="' . esc_url( $img ) . '" alt="' . esc_attr( $title ) . '" loading="lazy" /></div>';
			}
			echo '<div class="ds-program-body">';
			if ( '' !== $date ) {
				echo '<span class="ds-program-date">' . esc_html( $date ) . '</span>';
			}
			if ( '' !== $sub ) {
				echo '<span class="ds-program-sub">' . esc_html( $sub ) . '</span>';
			}
			if ( '' !== $title ) {
				echo '<h3 class="ds-program-title">' . DS_Module_UI::inline( $title ) . '</h3>';
			}
			if ( '' !== $desc ) {
				echo '<div class="ds-program-desc">' . wpautop( wp_kses_post( $desc ) ) . '</div>';
			}
			if ( '' !== $btn && $hasurl ) {
				echo '<a class="' . esc_attr( $btn_cls ) . '" href="' . esc_url( $url ) . '"' . $tgt . '>' . esc_html( $btn ) . '</a>';
			}
			echo '</div></div>';
		}

		self::close( $s );
		echo '</div></section>';
	}

	/**
	 * Header chrome: heading colours, the "see all" button, the header divider and
	 * the shared Border & Divider block.
	 *
	 * The header markup (.ds-news-head / .ds-news-heading / .ds-news-seeall) comes
	 * from head() above, so a host module must emit these or a migrated node loses
	 * its heading colour and the rule under the header — which measurably changes
	 * page height, not just colour.
	 *
	 * @param string $node     Node selector.
	 * @param object $settings Module settings.
	 */
	public static function header_css( $node, $settings ) {
		$col = array( 'DS_Module_UI', 'color' );
		$u   = array( 'DS_Module_UI', 'u' );

		// ---- Header text ----
		$hc = $col( $settings->heading_color ?? '' );
		if ( $hc ) {
			echo "$node .ds-news-heading { color: {$hc}; }\n";
		}
		$hac = $col( $settings->heading_accent_color ?? '' );
		if ( $hac ) {
			echo "$node .ds-news-accent { color: {$hac}; }\n";
		}

		// ---- "See all" button: default to the Theme Setting global Button ----
		$btn = $settings->btn_global ?? 'yes';
		$gs  = class_exists( 'FLBuilderGlobalStyles' ) ? FLBuilderGlobalStyles::get_settings( false ) : null;
		if ( 'yes' === $btn && $gs ) {
			DS_Module_UI::global_button_css( "$node .ds-news-seeall" );
		} elseif ( 'accent' === $btn ) {
			$ac = $col( $settings->heading_accent_color ?? 'var(--fl-global-accent)' );
			echo "$node .ds-news-seeall { background: {$ac} !important; color: var(--fl-global-white) !important; }\n";
		} else {
			$dbg = $col( $settings->btn_dark_bg ?? 'var(--fl-global-dark-background)' );
			$dtx = $col( $settings->btn_dark_color ?? 'var(--fl-global-white)' );
			if ( $dbg ) {
				echo "$node .ds-news-seeall { background: {$dbg} !important; }\n";
			}
			if ( $dtx ) {
				echo "$node .ds-news-seeall { color: {$dtx} !important; }\n";
			}
		}

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
	}

	/**
	 * Shared card chrome: section background, hover effect and the unified Card
	 * Border, scoped to .ds-program-card only.
	 *
	 * ds-post-loop emits these from its own CSS file for a selector list covering
	 * every card layout. A host module that only renders program cards (CTA
	 * Style 6) needs the same rules or a migrated node loses its border and hover,
	 * so they live here and stay in lockstep.
	 *
	 * @param string $node     Node selector, e.g. ".fl-node-abc123".
	 * @param object $settings Module settings.
	 */
	public static function chrome_css( $node, $settings ) {
		$col = array( 'DS_Module_UI', 'color' );
		$u   = array( 'DS_Module_UI', 'u' );

		$card  = "$node .ds-program-card";
		$cardH = "$node .ds-program-card:hover";

		// ---- Section background ----
		$sbg = $col( $settings->section_bg ?? '' );
		if ( '' !== $sbg ) {
			echo "$node .ds-news { background: {$sbg}; }\n";
		}

		// ---- Hover & animation ----
		$he     = preg_replace( '/[^a-z]/', '', (string) ( $settings->hover_effect ?? 'lift' ) );
		$hspeed = $u( $settings->hover_speed ?? '', 300 );

		$hbg = $col( $settings->hover_bg ?? '' );
		if ( '' !== $hbg ) {
			echo "$card{transition:transform {$hspeed}ms ease,box-shadow {$hspeed}ms ease,border-color {$hspeed}ms ease,background {$hspeed}ms ease;}\n";
			echo "$cardH{background:{$hbg};}\n";
		}

		if ( $he && 'none' !== $he ) {
			echo "$card{transition:transform {$hspeed}ms ease,box-shadow {$hspeed}ms ease,border-color {$hspeed}ms ease;will-change:transform;}\n";
			if ( 'lift' === $he ) {
				$d  = $u( $settings->hover_distance ?? '', 6 );
				$sc = $col( $settings->hover_shadow_color ?? '' ) ?: 'rgba(10,30,60,.28)';
				echo "$cardH{transform:translateY(-{$d}px);box-shadow:0 18px 38px -18px {$sc};}\n";
			} elseif ( 'grow' === $he ) {
				$sc    = $col( $settings->hover_shadow_color ?? '' ) ?: 'rgba(10,30,60,.22)';
				$scale = max( 100, (int) ( $settings->hover_scale ?? 105 ) ) / 100;
				echo "$cardH{transform:scale({$scale});box-shadow:0 18px 38px -18px {$sc};}\n";
			} elseif ( 'shadow' === $he ) {
				$sc = $col( $settings->hover_shadow_color ?? '' ) ?: 'rgba(10,30,60,.25)';
				echo "$cardH{box-shadow:0 18px 40px -18px {$sc};}\n";
			} elseif ( 'border' === $he ) {
				$bc = $col( $settings->hover_border_color ?? '' ) ?: 'var(--fl-global-accent)';
				echo "$cardH{border-color:{$bc};box-shadow:inset 0 0 0 1px {$bc};}\n";
			} elseif ( 'zoom' === $he ) {
				$scale = max( 100, (int) ( $settings->hover_scale ?? 105 ) ) / 100;
				echo "$node .ds-program-media img{transition:transform {$hspeed}ms ease;}\n";
				echo "$cardH .ds-program-media img{transform:scale({$scale});}\n";
			}
		}

		// ---- Unified Card Border (opt-in; blank/default leaves the layout look) ----
		$bs    = preg_replace( '/[^a-z]/', '', (string) ( $settings->card_bd_style ?? 'default' ) );
		$bdecl = '';
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
		if ( '' !== $bdecl ) {
			echo "$card{{$bdecl}}\n";
		}
	}

	/**
	 * Dynamic per-node CSS for the program cards (pg_* settings).
	 *
	 * Mirrors the block in ds-post-loop/includes/frontend.css.php so a migrated
	 * node renders identically. Emits only the program-specific rules; the shared
	 * card/hover/border rules are emitted by the host module's own CSS file.
	 *
	 * @param string $node     Node selector, e.g. ".fl-node-abc123".
	 * @param object $settings Module settings.
	 */
	public static function css( $node, $settings ) {
		$col = array( 'DS_Module_UI', 'color' );
		$u   = array( 'DS_Module_UI', 'u' );

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

		$pc = $col( $settings->pg_icon_color ?? '' );
		if ( $pc ) {
			echo "$node .ds-program-ico{color:{$pc};}\n";
		}
		$pc = $col( $settings->pg_date_color ?? '' );
		if ( $pc ) {
			echo "$node .ds-program-date{color:{$pc};}\n";
		}
		$pc = $col( $settings->pg_sub_color ?? '' );
		if ( $pc ) {
			echo "$node .ds-program-sub{color:{$pc};}\n";
		}
		$pc = $col( $settings->pg_title_color ?? '' );
		if ( $pc ) {
			echo "$node .ds-program-title{color:{$pc};}\n";
		}
		$pc = $col( $settings->pg_desc_color ?? '' );
		if ( $pc ) {
			echo "$node .ds-program-desc{color:{$pc};}\n";
		}

		if ( ( $settings->pg_btn_style ?? 'theme' ) !== 'custom' ) {
			// FULL theme Button sync (bg, text, hover, border + radius + shadow, typography).
			DS_Module_UI::global_button_css(
				"$node .ds-programs .ds-program-card .ds-program-btn",
				"$node .ds-programs .ds-program-card .ds-program-btn:hover, $node .ds-programs .ds-program-card:hover .ds-program-btn"
			);
		} else {
			$bbg   = $col( $settings->pg_btn_bg ?? '' );
			$btc   = $col( $settings->pg_btn_color ?? '' );
			$bdecl = '';
			if ( $bbg ) {
				$bdecl .= "background:{$bbg};";
			}
			if ( $btc ) {
				$bdecl .= "color:{$btc};";
			}
			$brad = $settings->pg_btn_radius ?? '';
			if ( '' !== $brad ) {
				$bdecl .= 'border-radius:' . (int) $brad . 'px;';
			}
			if ( '' !== $bdecl ) {
				echo "$node .ds-programs .ds-program-card .ds-program-btn{{$bdecl}}\n";
			}
			$bhb = $col( $settings->pg_btn_hover_bg ?? '' );
			$bhc = $col( $settings->pg_btn_hover_color ?? '' );
			$bhd = '';
			if ( $bhb ) {
				$bhd .= "background:{$bhb};";
			}
			if ( $bhc ) {
				$bhd .= "color:{$bhc};";
			}
			if ( '' !== $bhd ) {
				echo "$node .ds-programs .ds-program-card .ds-program-btn:hover,$node .ds-programs .ds-program-card:hover .ds-program-btn{{$bhd}}\n";
			}
		}
	}
}
