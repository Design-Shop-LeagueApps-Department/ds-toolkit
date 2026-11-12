<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Post Loop: the people layouts: Staff cards, the four Athlete commitment layouts with their filter bar, Team list and Team cards.
 *
 * One part of DS_Post_Loop_Module, which was a single 1,640-line class until the 2026-11 audit. The
 * methods were moved here as they were: a trait, so they are still methods of the module (same
 * visibility, same $this, same settings). The query, the defaults and the layout list stay in
 * ds-post-loop.php; the settings form is includes/settings-form.php.
 */
trait DS_Post_Loop_People {

	/** A round contact/social icon link. */
	private function contact_icon( $href, $svg, $label ) {
		if ( '' === trim( (string) $href ) ) { return ''; }
		// Staff links come from post meta: drop javascript:/data: and other unsafe schemes, keep everything else as typed.
		$href = wp_kses_bad_protocol( (string) $href, wp_allowed_protocols() );
		return '<a class="ds-people-ico" href="' . esc_attr( $href ) . '" aria-label="' . esc_attr( $label ) . '" target="_blank" rel="noopener noreferrer">' . $svg . '</a>';
	}

	/**
	 * Stretched link covering a whole CPT card, pointing at the post's permalink.
	 * Renders as an absolutely-positioned overlay (see CSS) so the entire card is
	 * clickable while nested links (contact icons) stay clickable above it.
	 */
	private function card_link( $id ) {
		if ( ( $this->settings->card_link ?? 'yes' ) === 'no' ) { return ''; } // "Link Card to Post" off.
		return DS_Card::stretched_link( get_permalink( $id ), get_the_title( $id ) );
	}

	/** Staff Cards — photo + name + title + contact/social icons (screenshot 1). */
	public function render_staff_card() {
		$s  = $this->settings;
		$ph = self::placeholder_image();
		$q  = $this->run_query();

		echo '<section class="ds-news ds-people ds-people--staff"><div class="ds-news-wrap">';
		$this->render_head();

		if ( ! $q->have_posts() ) {
			$this->empty_state( __( 'No Staff entries published yet.', 'ds-toolkit' ) );
			echo '</div></section>';
			return;
		}

		// SVG icons (shared registry).
		$ic_mail  = DS_Card::icon( 'mail' );
		$ic_phone = DS_Card::icon( 'phone' );
		$ic_ig    = DS_Card::icon( 'instagram' );
		$ic_in    = DS_Card::icon( 'linkedin' );
		$ic_fb    = DS_Card::icon( 'facebook' );
		$ic_x     = DS_Card::icon( 'x' );
		$photo    = ( $s->staff_show_photo ?? 'yes' ) !== 'no';
		// Photo Style "Small circle" (1.16.0): the photo shows as a small round avatar, so a small or
		// low-resolution headshot is never stretched across a whole card. No photo = their initials.
		$avatar   = $photo && ( $s->staff_photo_style ?? 'card' ) === 'avatar';
		$side     = $avatar && ( $s->staff_avatar_layout ?? 'stack' ) === 'side';
		$av_img   = $avatar ? self::avatar_img_size( (int) ( $s->staff_avatar_size ?? 96 ) > 110 ? 'medium_large' : 'medium' ) : '';
		$cls      = $photo ? ( $avatar ? ' ds-people-card--avatar' . ( $side ? ' ds-people-card--side' : '' ) : '' ) : ' ds-people-card--nophoto';

		$this->loop_open( 'ds-people-grid' );
		while ( $q->have_posts() ) {
			$q->the_post();
			$id    = get_the_ID();
			$img   = $photo && ! $avatar ? ( get_the_post_thumbnail_url( $id, 'large' ) ?: $ph ) : '';
			$name  = get_the_title( $id );
			$title = (string) get_post_meta( $id, 'staff_title', true );

			$icons  = '';
			if ( ( $s->staff_show_email ?? 'yes' ) === 'yes' ) { $em = (string) get_post_meta( $id, 'staff_email', true ); $icons .= $em ? '<a class="ds-people-ico" href="mailto:' . esc_attr( $em ) . '" aria-label="' . esc_attr__( 'Email', 'ds-toolkit' ) . '">' . $ic_mail . '</a>' : ''; }
			if ( ( $s->staff_show_phone ?? 'yes' ) === 'yes' ) { $tel = (string) get_post_meta( $id, 'staff_phone', true ); $icons .= $tel ? '<a class="ds-people-ico" href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $tel ) ) . '" aria-label="' . esc_attr__( 'Phone', 'ds-toolkit' ) . '">' . $ic_phone . '</a>' : ''; }
			if ( ( $s->staff_show_social ?? 'yes' ) === 'yes' ) {
				$icons .= $this->contact_icon( get_post_meta( $id, 'staff_instagram', true ), $ic_ig, 'Instagram' );
				$icons .= $this->contact_icon( get_post_meta( $id, 'staff_linkedin', true ), $ic_in, 'LinkedIn' );
				$icons .= $this->contact_icon( get_post_meta( $id, 'staff_facebook', true ), $ic_fb, 'Facebook' );
				$icons .= $this->contact_icon( get_post_meta( $id, 'staff_x', true ), $ic_x, 'X' );
			}

			echo '<div class="ds-people-card' . $cls . '">'; echo $this->card_link( $id );
			if ( $avatar ) {
				$src = get_the_post_thumbnail_url( $id, $av_img );
				if ( $src ) {
					echo '<div class="ds-people-avatar" style="background-image:url(' . esc_url( $src ) . ')"></div>';
				} else {
					// The name is the heading right below, so the letters are decoration for screen readers.
					echo '<div class="ds-people-avatar ds-people-avatar--initials" aria-hidden="true">' . esc_html( DS_Card::initials( $name ) ) . '</div>';
				}
			} elseif ( $photo ) {
				echo '<div class="ds-people-photo"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . '></div>';
			}
			echo '<div class="ds-people-body">';
			if ( '' !== $name )  { echo '<h3 class="ds-people-name">' . DS_Module_UI::inline( $name ) . '</h3>'; }
			if ( '' !== $title ) { echo '<span class="ds-people-role">' . DS_Module_UI::inline( $title ) . '</span>'; }
			if ( '' !== $icons ) { echo '<div class="ds-people-contacts">' . $icons . '</div>'; }
			echo '</div></div>';
		}
		$this->loop_close(); echo '</div></section>';
		wp_reset_postdata();
	}

	/* ------------------------------------------------------- Commitments / Athletes */

	/**
	 * The college/school NAME for a commitment. The blueprint key is school_name,
	 * but partner sites name the field differently (495 Lacrosse uses `university`),
	 * which silently rendered a blank line. The developer can pin the key in the
	 * Commitment Cards section; blank auto-detects across the known key names.
	 */
	private function commit_school( $id ) {
		$key = trim( (string) ( $this->settings->commit_school_key ?? '' ) );
		if ( '' !== $key ) { return trim( (string) $this->acf( $key, $id ) ); }
		foreach ( array( 'school_name', 'university', 'college', 'college_name', 'school' ) as $k ) {
			$v = trim( (string) $this->acf( $k, $id ) );
			if ( '' !== $v ) { return $v; }
		}
		return '';
	}

	/** The college/school LOGO for a commitment. Same key-resolution story as commit_school(). */
	private function commit_logo( $id ) {
		$key = trim( (string) ( $this->settings->commit_logo_key ?? '' ) );
		if ( '' !== $key ) { return $this->acf_image_url( $this->acf( $key, $id ) ); }
		foreach ( array( 'school_logo', 'college_logo', 'logo' ) as $k ) {
			$url = $this->acf_image_url( $this->acf( $k, $id ) );
			if ( $url ) { return $url; }
		}
		return '';
	}

	/** True when the Commitment filter bar is switched on. */
	private function commit_filter_on() {
		return ( $this->settings->cf_filter ?? 'disable' ) === 'enable';
	}

	/**
	 * The facet value(s) a commitment is tagged with, per the developer-chosen
	 * source: a meta/ACF field ("meta:<key>", e.g. the default meta:division) or a
	 * taxonomy ("tax:<slug>"). A comma/pipe-separated meta value counts as several.
	 */
	private function commit_facets( $id ) {
		$src = trim( (string) ( $this->settings->cf_source ?? 'meta:division' ) );
		if ( '' === $src ) { return array(); }
		if ( 0 === strpos( $src, 'tax:' ) ) {
			$terms = get_the_terms( $id, substr( $src, 4 ) );
			return ( $terms && ! is_wp_error( $terms ) ) ? array_values( wp_list_pluck( $terms, 'name' ) ) : array();
		}
		$raw = $this->acf( 0 === strpos( $src, 'meta:' ) ? substr( $src, 5 ) : $src, $id );
		if ( is_array( $raw ) ) { $raw = implode( ',', array_map( 'strval', $raw ) ); }
		return array_values( array_filter( array_map( 'trim', preg_split( '/[,|]/', (string) $raw ) ) ) );
	}

	/** data-* attributes that let the front-end JS filter a card without a round trip. */
	private function commit_data_attr( $facets ) {
		if ( ! $this->commit_filter_on() ) { return ''; }
		return ' data-facet="' . esc_attr( strtolower( implode( ' ', $facets ) ) ) . '"';
	}

	/**
	 * The Commitment filter bar: an "All" pill plus one pill per facet value found
	 * in the result set. Order follows the Tab Order field when set, else the order
	 * the values first appear in the loop (so the developer controls it either way).
	 */
	private function commit_filter_bar( $all_facets, $total ) {
		$s = $this->settings;
		$tabs = array();
		foreach ( $all_facets as $v ) { $tabs[ strtolower( $v ) ] = $v; }
		if ( empty( $tabs ) ) { return false; }

		$explicit = array_filter( array_map( 'trim', explode( ',', (string) ( $s->cf_order ?? '' ) ) ) );
		if ( ! empty( $explicit ) ) {
			$ordered = array();
			foreach ( $explicit as $v ) { $k = strtolower( $v ); if ( isset( $tabs[ $k ] ) ) { $ordered[ $k ] = $tabs[ $k ]; unset( $tabs[ $k ] ); } }
			$tabs = $ordered + $tabs; // anything not named in Tab Order keeps its natural place at the end
		}

		$label = trim( (string) ( $s->cf_all_label ?? '' ) ) ?: __( 'All', 'ds-toolkit' );
		echo '<div class="ds-commit-filter">';
		echo '<div class="ds-commit-tabs" role="group" aria-label="' . esc_attr__( 'Filter commitments', 'ds-toolkit' ) . '">';
		echo '<button type="button" class="ds-commit-tab is-active" data-tab="" aria-pressed="true">' . esc_html( $label ) . '</button>';
		foreach ( $tabs as $key => $text ) {
			echo '<button type="button" class="ds-commit-tab" data-tab="' . esc_attr( $key ) . '" aria-pressed="false">' . esc_html( $text ) . '</button>';
		}
		echo '</div>';
		if ( ( $s->cf_count ?? 'show' ) === 'show' ) {
			$noun = trim( (string) ( $s->cf_count_noun ?? '' ) ) ?: __( 'commitments', 'ds-toolkit' );
			echo '<span class="ds-commit-count" data-noun="' . esc_attr( $noun ) . '">' . (int) $total . ' ' . esc_html( $noun ) . '</span>';
		}
		echo '</div>';
		return true;
	}

	/** Shared open/close for the three athlete layouts so the filter bar is wired once. */
	private function athlete_section_open( $modifier, $q, &$rows ) {
		$filter = $this->commit_filter_on();
		$rows   = array();
		if ( $filter ) {
			foreach ( $q->posts as $p ) { foreach ( $this->commit_facets( $p->ID ) as $f ) { $rows[ strtolower( $f ) ] = $f; } }
		}
		$root = 'ds-news ds-people ds-people--commit ' . $modifier . ( $filter ? ' ds-commit--filter' : '' );
		echo '<section class="' . esc_attr( $root ) . '"><div class="ds-news-wrap">';
		$this->render_head();
		if ( ! $q->have_posts() ) {
			$this->empty_state( __( 'No Commitments published yet.', 'ds-toolkit' ) );
			echo '</div></section>';
			return false;
		}
		if ( $filter ) { $this->commit_filter_bar( $rows, count( $q->posts ) ); }
		return true;
	}

	/** Close the athlete section: grid close, the "no matches" line, wrappers, reset. */
	private function athlete_section_close() {
		$this->loop_close();
		if ( $this->commit_filter_on() ) {
			echo '<p class="ds-commit-none" hidden>' . esc_html__( 'No commitments match this filter.', 'ds-toolkit' ) . '</p>';
		}
		echo '</div></section>';
		wp_reset_postdata();
	}

	/** Photo Cards — portrait photo + name + school (screenshot 2). */
	public function render_athlete_photo() {
		$ph   = self::placeholder_image();
		$q    = $this->run_query();
		$rows = array();
		if ( ! $this->athlete_section_open( 'ds-commit--photo', $q, $rows ) ) { return; }
		$this->loop_open( 'ds-people-grid' );
		while ( $q->have_posts() ) { $q->the_post(); $id = get_the_ID();
			$img    = get_the_post_thumbnail_url( $id, 'large' ) ?: $ph;
			$name   = get_the_title( $id );
			$school = $this->commit_school( $id );
			echo '<div class="ds-commit-card"' . $this->commit_data_attr( $this->commit_facets( $id ) ) . '>'; echo $this->card_link( $id );
			echo '<div class="ds-people-photo"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . '></div>';
			echo '<div class="ds-people-body">';
			if ( '' !== $name )   { echo '<h3 class="ds-people-name">' . DS_Module_UI::inline( $name ) . '</h3>'; }
			if ( '' !== $school ) { echo '<span class="ds-people-role">' . esc_html( $school ) . '</span>'; }
			echo '</div></div>';
		}
		$this->athlete_section_close();
	}

	/** Logo Row — dark horizontal card: school logo + name + school (screenshot 3). */
	public function render_athlete_logo() {
		$q    = $this->run_query();
		$rows = array();
		if ( ! $this->athlete_section_open( 'ds-commit--logo', $q, $rows ) ) { return; }
		$this->loop_open( 'ds-people-grid' );
		while ( $q->have_posts() ) { $q->the_post(); $id = get_the_ID();
			$logo   = $this->commit_logo( $id );
			$name   = get_the_title( $id );
			$school = $this->commit_school( $id );
			echo '<div class="ds-commit-row"' . $this->commit_data_attr( $this->commit_facets( $id ) ) . '>'; echo $this->card_link( $id );
			echo '<div class="ds-commit-row-logo">' . ( $logo ? '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $school ) . '" loading="lazy" />' : '' ) . '</div>';
			echo '<div class="ds-commit-row-body">';
			if ( '' !== $name )   { echo '<h3 class="ds-people-name">' . DS_Module_UI::inline( $name ) . '</h3>'; }
			if ( '' !== $school ) { echo '<span class="ds-people-role">' . esc_html( $school ) . '</span>'; }
			echo '</div></div>';
		}
		$this->athlete_section_close();
	}

	/** Action Cards — action photo + logo + name + "year | school" (screenshot 4). */
	public function render_athlete_action() {
		$ph       = self::placeholder_image();
		$q        = $this->run_query();
		$rows     = array();
		$show_yr  = ( $this->settings->commit_show_year ?? 'yes' ) === 'yes';
		if ( ! $this->athlete_section_open( 'ds-commit--action', $q, $rows ) ) { return; }
		$this->loop_open( 'ds-people-grid' );
		while ( $q->have_posts() ) { $q->the_post(); $id = get_the_ID();
			$img    = get_the_post_thumbnail_url( $id, 'large' ) ?: $ph;
			$logo   = $this->commit_logo( $id );
			$name   = get_the_title( $id );
			$school = $this->commit_school( $id );
			$year   = $show_yr ? trim( (string) $this->acf( 'year', $id ) ) : '';
			$meta   = trim( $year . ( ( '' !== $year && '' !== $school ) ? ' | ' : '' ) . $school );
			echo '<div class="ds-commit-action"' . $this->commit_data_attr( $this->commit_facets( $id ) ) . '>'; echo $this->card_link( $id );
			echo '<div class="ds-people-photo"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . '></div>';
			echo '<div class="ds-commit-action-body">';
			echo '<div class="ds-commit-action-logo">' . ( $logo ? '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $school ) . '" loading="lazy" />' : '' ) . '</div>';
			echo '<div class="ds-commit-action-text">';
			if ( '' !== $name ) { echo '<h3 class="ds-people-name">' . DS_Module_UI::inline( $name ) . '</h3>'; }
			if ( '' !== $meta ) { echo '<span class="ds-people-role">' . esc_html( $meta ) . '</span>'; }
			echo '</div></div></div>';
		}
		$this->athlete_section_close();
	}

	/**
	 * The club/partner mark shown on the right of a Compact Strip. Falls back to the
	 * site's ACF `partner_logo` option, so the layout is correct on any fleet site with
	 * zero configuration; the module's own Club Logo field overrides it per instance.
	 */
	private function commit_brand_logo() {
		$own = $this->acf_image_url( $this->settings->cr_brand ?? '' );
		if ( $own ) { return $own; }
		if ( function_exists( 'get_field' ) ) {
			$opt = $this->acf_image_url( get_field( 'partner_logo', 'option' ) );
			if ( $opt ) { return $opt; }
		}
		return '';
	}

	/**
	 * Compact Strip — a short, dense row instead of a tall photo card:
	 * [college logo] [player name + college] [club logo]. Modelled on the 4Leaf
	 * commitments strip a partner asked us to match. No athlete photo at all, which
	 * is the point: it stays readable when every athlete shares one placeholder image,
	 * and it fits three or four across without dominating the page.
	 */
	public function render_athlete_strip() {
		$s     = $this->settings;
		$q     = $this->run_query();
		$rows  = array();
		if ( ! $this->athlete_section_open( 'ds-commit--strip', $q, $rows ) ) { return; }

		$brand      = ( $s->cr_brand_show ?? 'yes' ) === 'yes' ? $this->commit_brand_logo() : '';
		$show_coll  = ( $s->cr_show_college ?? 'yes' ) === 'yes';
		$brand_alt  = get_bloginfo( 'name' );

		$this->loop_open( 'ds-people-grid' );
		while ( $q->have_posts() ) { $q->the_post(); $id = get_the_ID();
			$logo   = $this->commit_logo( $id );
			$name   = get_the_title( $id );
			$school = $show_coll ? $this->commit_school( $id ) : '';
			echo '<div class="ds-commit-strip"' . $this->commit_data_attr( $this->commit_facets( $id ) ) . '>';
			echo $this->card_link( $id );
			echo '<span class="ds-commit-strip-logo">' . ( $logo ? '<img src="' . esc_url( $logo ) . '" alt="' . esc_attr( $school ?: $name ) . '" loading="lazy" />' : '' ) . '</span>';
			echo '<span class="ds-commit-strip-body">';
			if ( '' !== $name )   { echo '<h3 class="ds-people-name">' . DS_Module_UI::inline( $name ) . '</h3>'; }
			if ( '' !== $school ) { echo '<span class="ds-people-role">' . esc_html( $school ) . '</span>'; }
			echo '</span>';
			if ( '' !== $brand ) { echo '<span class="ds-commit-strip-brand"><img src="' . esc_url( $brand ) . '" alt="' . esc_attr( $brand_alt ) . '" loading="lazy" /></span>'; }
			echo '</div>';
		}
		$this->athlete_section_close();
	}

	/* ------------------------------------------------------------------------ Team */

	/** Team List — full-width rows, name + arrow / external-link button (screenshot 5). */
	public function render_team_list() {
		$q = $this->run_query();
		echo '<section class="ds-news ds-teams"><div class="ds-news-wrap">';
		$this->render_head();
		if ( ! $q->have_posts() ) { $this->empty_state( __( 'No Teams published yet.', 'ds-toolkit' ) ); echo '</div></section>'; return; }
		$arrow = DS_Card::icon( 'arrow' );
		$ext   = DS_Card::icon( 'external' );
		echo '<div class="ds-teams-list">';
		while ( $q->have_posts() ) { $q->the_post(); $id = get_the_ID();
			$name    = get_the_title( $id );
			$extlink = trim( (string) $this->acf( 'team_external_link', $id ) );
			$is_ext  = '' !== $extlink;
			$url     = $is_ext ? $extlink : get_permalink( $id );
			$tgt     = $is_ext ? ' target="_blank" rel="noopener noreferrer"' : '';
			echo '<a class="ds-team-row" href="' . esc_url( $url ) . '"' . $tgt . '>';
			echo '<span class="ds-team-name">' . DS_Module_UI::inline( $name ) . '</span>';
			echo '<span class="ds-team-btn">' . ( $is_ext ? $ext : $arrow ) . '</span>';
			echo '</a>';
		}
		$this->loop_close(); echo '</div></section>';
		wp_reset_postdata();
	}

	/** Teams: Cards — photo grid; external LeagueApps link preferred, permalink fallback. */
	public function render_team_card() {
		$s  = $this->settings;
		$ph = self::placeholder_image();
		$q  = $this->run_query();

		echo '<section class="ds-news ds-teamcards"><div class="ds-news-wrap">';
		$this->render_head();
		if ( ! $q->have_posts() ) {
			$this->empty_state( __( 'No Teams published yet.', 'ds-toolkit' ) );
			echo '</div></section>';
			return;
		}

		$arrow = DS_Card::icon( 'arrow' );
		$ext   = DS_Card::icon( 'external' );
		$this->loop_open( 'ds-teamcard-grid' );
		while ( $q->have_posts() ) {
			$q->the_post();
			$id      = get_the_ID();
			$name    = get_the_title( $id );
			$img     = get_the_post_thumbnail_url( $id, 'large' ) ?: $ph;
			$extlink = trim( (string) $this->acf( 'team_external_link', $id ) );
			$is_ext  = '' !== $extlink;
			$url     = $is_ext ? $extlink : get_permalink( $id );
			$tgt     = $is_ext ? ' target="_blank" rel="noopener noreferrer"' : '';

			echo '<a class="ds-teamcard" href="' . esc_url( $url ) . '"' . $tgt . '>';
			echo '<span class="ds-teamcard-photo"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . '></span>';
			echo '<span class="ds-teamcard-body">';
			echo '<span class="ds-teamcard-name">' . DS_Module_UI::inline( $name ) . '</span>';
			echo '<span class="ds-teamcard-ico" aria-hidden="true">' . ( $is_ext ? $ext : $arrow ) . '</span>';
			echo '</span></a>';
		}
		$this->loop_close();
		echo '</div></section>';
		wp_reset_postdata();
	}
}
