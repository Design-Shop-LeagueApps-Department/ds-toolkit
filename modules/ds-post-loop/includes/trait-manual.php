<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Post Loop: the layouts filled by hand instead of a query: the Sponsor grid, Program cards and the card loop they share.
 *
 * One part of DS_Post_Loop_Module, which was a single 1,640-line class until the 2026-11 audit. The
 * methods were moved here as they were: a trait, so they are still methods of the module (same
 * visibility, same $this, same settings). The query, the defaults and the layout list stay in
 * ds-post-loop.php; the settings form is includes/settings-form.php.
 */
trait DS_Post_Loop_Manual {

	/**
	 * Custom Loop Layout — the universal layout (available for ANY content type).
	 * Loops the queried posts and renders the editor's own HTML / shortcode once per
	 * post (e.g. a saved BB layout: [fl_builder_insert_layout id="123"]). Ideal for
	 * CPTs (Staff / Team / Athletes) once they exist. Columns/gap are configurable.
	 */
	/** Sponsor Grid — a manual list of logos (image + caption + url + description), no query. */
	public function render_sponsor() {
		$s     = $this->settings;
		$items = ( isset( $s->sponsors ) && is_array( $s->sponsors ) ) ? $s->sponsors : array();
		echo '<section class="ds-news ds-people ds-sponsors"><div class="ds-news-wrap">';
		$this->render_head();
		if ( empty( $items ) ) {
			$this->empty_state( __( 'No sponsors yet. Add them on the Content tab, under Sponsors (manual list).', 'ds-toolkit' ) );
			echo '</div></section>'; return;
		}
		$this->loop_open( 'ds-sponsor-grid' );
		foreach ( $items as $it ) {
			$it   = (object) $it;
			$img  = $this->acf_image_url( $it->sponsor_image ?? '' ) ?: self::placeholder_image();
			$cap  = trim( (string) ( $it->sponsor_caption ?? '' ) );
			$desc = trim( (string) ( $it->sponsor_desc ?? '' ) );
			list( $url, $target ) = $this->link_parts( $it->sponsor_url ?? '', $it->sponsor_url_target ?? '' );
			$has = ( '' !== $url && '#' !== $url );
			echo '<div class="ds-sponsor-card">';
			if ( $has ) { echo '<a class="ds-card-link" href="' . esc_url( $url ) . '"' . ( '_blank' === $target ? ' target="_blank" rel="noopener"' : '' ) . ' aria-label="' . esc_attr( $cap ) . '"></a>'; }
			if ( '' !== $img )  { echo '<div class="ds-sponsor-logo"><img src="' . esc_url( $img ) . '" alt="' . esc_attr( $cap ) . '" loading="lazy" /></div>'; }
			if ( '' !== $cap )  { echo '<h3 class="ds-sponsor-name">' . esc_html( $cap ) . '</h3>'; }
			if ( '' !== $desc ) { echo '<div class="ds-sponsor-desc">' . wpautop( wp_kses_post( $desc ) ) . '</div>'; }
			echo '</div>';
		}
		$this->loop_close();
		echo '</div></section>';
	}

	/**
	 * Legacy manual program-card list. The card itself now lives in the CTA
	 * module (Style 6) because it is a hand-built list, not a post loop. This
	 * delegates to the shared renderer so legacy instances and migrated ones are
	 * byte-identical. Kept indefinitely: revision restores and un-migrated sites
	 * still resolve to this layout.
	 */
	public function render_program() {
		DS_Program_Cards::render(
			$this->settings,
			__( 'No program cards yet. Add them on the Content tab, under Program Cards (manual list).', 'ds-toolkit' )
		);
	}

	public function render_cardloop() {
		$s   = $this->settings;
		$tpl = (string) ( $s->loop_custom ?? '' );

		echo '<section class="ds-news ds-news--cardloop"><div class="ds-news-wrap">';
		$this->render_head();

		$items = $this->collect_items();
		if ( empty( $items ) ) {
			$this->empty_state( __( 'No posts found for this query. Publish posts of the selected type, or adjust the Query tab.', 'ds-toolkit' ) );
			echo '</div></section>';
			return;
		}

		if ( '' === trim( $tpl ) ) {
			$this->empty_state( __( 'Add your HTML / shortcode in the "Custom Item Markup" field — it is rendered once per post.', 'ds-toolkit' ) );
			echo '</div></section>';
			return;
		}

		$this->loop_open( 'ds-news-loopgrid' );
		foreach ( $items as $card ) {
			$this->render_custom( $card, $tpl );
		}
		$this->loop_close(); echo '</div></section>';
	}
}
