<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Post Loop: the news and custom card layouts: the shared loop wrapper and header, featured and standard cards, Style 1 and Style 2, and the Custom Item Markup template.
 *
 * One part of DS_Post_Loop_Module, which was a single 1,640-line class until the 2026-11 audit. The
 * methods were moved here as they were: a trait, so they are still methods of the module (same
 * visibility, same $this, same settings). The query, the defaults and the layout list stay in
 * ds-post-loop.php; the settings form is includes/settings-form.php.
 */
trait DS_Post_Loop_Cards {

	/** Items wrapper open: a CSS grid, or a carousel track when Display = Carousel. */
	private function loop_open( $grid_class ) {
		$s = $this->settings;
		$dmode = $s->display ?? 'grid';
		if ( 'paginated' === $dmode ) {
			$pp = max( 1, (int) ( $s->pag_per_page ?? 6 ) );
			$pt = in_array( $s->pag_type ?? 'numbers', array( 'numbers', 'loadmore' ), true ) ? ( $s->pag_type ?? 'numbers' ) : 'numbers';
			echo '<div class="ds-looppage" data-perpage="' . $pp . '" data-pagtype="' . esc_attr( $pt ) . '"><div class="' . esc_attr( $grid_class ) . '">';
			return;
		}
		if ( 'carousel' !== $dmode ) { echo '<div class="' . esc_attr( $grid_class ) . '">'; return; }
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

	/** Items wrapper close (matches loop_open). */
	private function loop_close() {
		$s = $this->settings;
		$dmode = $s->display ?? 'grid';
		if ( 'paginated' === $dmode ) { echo '</div><div class="ds-looppage-nav"></div></div>'; return; }
		if ( 'carousel' !== $dmode ) { echo '</div>'; return; }
		echo '</div></div>';
		if ( ( $s->car_dots ?? 'yes' ) === 'yes' ) { echo '<div class="ds-loopcar-dots"></div>'; }
		echo '</div>';
	}

	/** Optional header row: {a}accent{/a} heading + "see all" button. */
	private function render_head() {
		$s = $this->settings;
		if ( ( $s->show_header ?? 'yes' ) !== 'yes' ) { return; }
		$show_btn = ( $s->show_button ?? 'yes' ) === 'yes';
		$has_h    = ! empty( $s->heading );
		if ( ! $has_h && ! $show_btn ) { return; }

		echo '<div class="ds-news-head">';
		if ( $has_h ) {
			echo '<h2 class="ds-news-heading">' . $this->heading_html( $s->heading ) . '</h2>';
		}
		if ( $show_btn ) {
			$txt = trim( (string) ( $s->button_text ?? '' ) );
			if ( '' !== $txt ) {
				list( $url, $target ) = $this->link_parts( $s->button_link ?? '', $s->button_link_target ?? '' );
				$rel = '_blank' === $target ? ' rel="noopener noreferrer"' : '';
				echo '<a class="ds-news-seeall" href="' . $url . '" target="' . esc_attr( $target ) . '"' . $rel . '>' . esc_html( $txt ) . '</a>';
			}
		}
		echo '</div>';
	}

	/** Built-in featured card. */
	private function render_featured( $f ) {
		$s        = $this->settings;
		$readmore = trim( (string) ( $s->readmore_text ?? '' ) );
		$badge    = trim( (string) ( $s->featured_badge ?? '' ) );
		if ( '' === $badge ) { $badge = (string) ( $f['eyebrow'] ?? '' ); }

		$img  = $f['image'] ?? '';
		$url  = $f['url'] ?: '#';
		$tar  = $f['target'] ?: '_self';
		$rel  = '_blank' === $tar ? ' rel="noopener noreferrer"' : '';

		echo '<a class="ds-news-feature" href="' . esc_url( $url ) . '" target="' . esc_attr( $tar ) . '"' . $rel . '>';
		echo '<div class="ds-news-feature-bg"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . '></div>';
		echo '<div class="ds-news-feature-overlay" aria-hidden="true"></div>';
		echo '<div class="ds-news-feature-body">';
		if ( '' !== $badge )              { echo '<span class="ds-news-badge">' . esc_html( $badge ) . '</span>'; }
		if ( '' !== ( $f['title'] ?? '' ) )   { echo '<h3 class="ds-news-feature-title">' . DS_Module_UI::inline( $f['title'] ) . '</h3>'; }
		if ( '' !== ( $f['excerpt'] ?? '' ) ) { echo '<p class="ds-news-feature-excerpt">' . esc_html( $f['excerpt'] ) . '</p>'; }
		if ( '' !== $readmore ) {
			echo '<span class="ds-news-readmore">' . esc_html( $readmore ) . ' <span class="ds-news-readmore-arrow" aria-hidden="true">&rarr;</span></span>';
		}
		echo '</div></a>';
	}

	/** Built-in small loop card. */
	private function render_card( $c ) {
		$url = $c['url'] ?: '#';
		$tar = $c['target'] ?: '_self';
		$rel = '_blank' === $tar ? ' rel="noopener noreferrer"' : '';
		echo '<a class="ds-news-card" href="' . esc_url( $url ) . '" target="' . esc_attr( $tar ) . '"' . $rel . '>';
		echo '<div class="ds-news-card-top">';
		if ( '' !== ( $c['eyebrow'] ?? '' ) ) { echo '<span class="ds-news-card-cat">' . esc_html( $c['eyebrow'] ) . '</span>'; }
		if ( '' !== ( $c['title'] ?? '' ) )   { echo '<h3 class="ds-news-card-title">' . DS_Module_UI::inline( $c['title'] ) . '</h3>'; }
		echo '</div>';
		echo '<div class="ds-news-card-foot">';
		echo '<span class="ds-news-card-date">' . esc_html( $c['date'] ?? '' ) . '</span>';
		echo '<span class="ds-news-card-chevron" aria-hidden="true">&rsaquo;</span>';
		echo '</div>';
		echo '</a>';
	}

	/**
	 * Custom loop card: render the editor's HTML / shortcode template once per
	 * post, with that post's data in scope. Supports simple {tokens} and any
	 * WordPress shortcode (incl. [fl_builder_insert_layout id=…]) which resolves
	 * against the current post via setup_postdata().
	 */
	private function render_custom( $c, $tpl ) {
		$tpl = (string) $tpl;
		if ( '' === trim( $tpl ) ) { return; }

		$map = array(
			'{id}'         => (string) ( $c['id'] ?? '' ),
			'{title}'      => DS_Module_UI::inline( $c['title'] ?? '' ),
			'{permalink}'  => esc_url( $c['url'] ?? '#' ),
			'{url}'        => esc_url( $c['url'] ?? '#' ),
			'{date}'       => esc_html( $c['date'] ?? '' ),
			'{category}'   => esc_html( $c['eyebrow'] ?? '' ),
			'{excerpt}'    => esc_html( $c['excerpt'] ?? '' ),
			'{image}'      => esc_url( $c['image'] ?? '' ),
			'{thumb_url}'  => esc_url( $c['image'] ?? '' ),
		);
		$html = strtr( $tpl, $map );

		$pid = (int) ( $c['id'] ?? 0 );
		echo '<div class="ds-news-custom-item">';
		if ( $pid ) {
			global $post;
			$saved = $post;
			$post  = get_post( $pid );
			setup_postdata( $post );
			echo do_shortcode( $html );
			wp_reset_postdata();
			$post = $saved;
		} else {
			echo do_shortcode( $html );
		}
		echo '</div>';
	}

	/* ------------------------------------------------------------------
	 * [ds_team_coach_avatars] — coach faces for a Custom Loop Layout card.
	 *
	 * render_custom() above runs each card under setup_postdata(), so a
	 * shortcode placed in the Custom Item Markup sees the loop post as the
	 * current post. That is all this needs: read the current post's
	 * relationship field, follow it to the related posts, and draw their
	 * featured images as overlapping circles.
	 * --------------------------------------------------------------- */

	/** Style 1 — featured card + grid of loop cards. */
	public function render_style1() {
		$s = $this->settings;

		$mods = 'ds-news ds-news--style1';

		echo '<section class="' . esc_attr( $mods ) . '"><div class="ds-news-wrap">';

		$this->render_head();

		$items = $this->collect_items();
		if ( empty( $items ) ) {
			$this->empty_state( __( 'No posts found for this query. Publish posts of the selected type, or adjust the Query tab.', 'ds-toolkit' ) );
			echo '</div></section>';
			return;
		}

		$featured = array_shift( $items );

		echo '<div class="ds-news-grid">';
		$this->render_featured( $featured );
		foreach ( $items as $card ) {
			$this->render_card( $card );
		}
		echo '</div></div></section>';
	}

	/** A Style 2 card: image + pill badge, then date, title, Read More link. */
	private function render_card2( $c ) {
		$s        = $this->settings;
		$url      = $c['url'] ?: '#';
		$tar      = $c['target'] ?: '_self';
		$rel      = '_blank' === $tar ? ' rel="noopener noreferrer"' : '';
		$img      = $c['image'] ?? '';
		$pill     = trim( (string) ( $c['eyebrow'] ?? '' ) );
		$readmore = trim( (string) ( $s->readmore2_text ?? '' ) );

		echo '<a class="ds-news-card2" href="' . esc_url( $url ) . '" target="' . esc_attr( $tar ) . '"' . $rel . '>';
		echo '<div class="ds-news-card2-media">';
		if ( $img ) { echo '<div class="ds-news-card2-img" style="background-image:url(' . esc_url( $img ) . ')"></div>'; }
		if ( '' !== $pill ) { echo '<span class="ds-news-card2-pill">' . esc_html( $pill ) . '</span>'; }
		echo '</div>';
		echo '<div class="ds-news-card2-body">';
		if ( '' !== ( $c['date'] ?? '' ) )  { echo '<span class="ds-news-card2-date">' . esc_html( $c['date'] ) . '</span>'; }
		if ( '' !== ( $c['title'] ?? '' ) ) { echo '<h3 class="ds-news-card2-title">' . DS_Module_UI::inline( $c['title'] ) . '</h3>'; }
		if ( '' !== $readmore ) {
			echo '<span class="ds-news-card2-more">' . esc_html( $readmore ) . ' <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></span>';
		}
		echo '</div></a>';
	}

	/** Style 2 — uniform grid of pill cards. */
	public function render_style2() {
		$s = $this->settings;

		$mods = 'ds-news ds-news--style2';

		echo '<section class="' . esc_attr( $mods ) . '"><div class="ds-news-wrap">';
		$this->render_head();

		$items = $this->collect_items();
		if ( empty( $items ) ) {
			$this->empty_state( __( 'No posts found for this query. Publish posts of the selected type, or adjust the Query tab.', 'ds-toolkit' ) );
			echo '</div></section>';
			return;
		}

		$this->loop_open( 'ds-news-grid2' );
		foreach ( $items as $card ) {
			$this->render_card2( $card );
		}
		$this->loop_close(); echo '</div></section>';
	}
}
