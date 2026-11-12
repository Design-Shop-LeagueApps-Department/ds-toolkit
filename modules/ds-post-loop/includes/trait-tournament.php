<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Post Loop: the Tournament layout: event dates, tabs, state and location filters.
 *
 * One part of DS_Post_Loop_Module, which was a single 1,640-line class until the 2026-11 audit. The
 * methods were moved here as they were: a trait, so they are still methods of the module (same
 * visibility, same $this, same settings). The query, the defaults and the layout list stay in
 * ds-post-loop.php; the settings form is includes/settings-form.php.
 */
trait DS_Post_Loop_Tournament {

	/** Parse an event_date free-text value (e.g. "July 18-20, 2026") to a timestamp; 0 if unknown. */
	private function parse_event_date( $str ) {
		$str = trim( (string) $str );
		if ( '' === $str ) { return 0; }
		// ISO "2026-09-19" (or an ISO range): those dashes are separators, not a day range.
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})/', $str, $iso ) ) {
			$ts = strtotime( $iso[1] );
			return $ts ? (int) $ts : 0;
		}
		$dash = '[\x{2013}\x{2014}-]';
		// Range whose END carries its own month or a slash date: "09/19 - 09/20/2026",
		// "09/19/2026 - 09/20/2026", "Oct 31 - Nov 1, 2026". Keep the START and borrow
		// the year from the end when the start has none. Before this, "09/19 - 09/20/2026"
		// normalised to "09/19/20/2026", strtotime returned false, every event fell into
		// the same "unknown date" bucket and the Tournament Cards kept the query's
		// date-DESC order (sportsatthebeach, Zendesk #456359).
		if ( preg_match( '/^(.*?)\s*' . $dash . '\s*((?:[A-Za-z]{3,9}\.?\s+\d{1,2}|\d{1,2}\/\d{1,2})(?:\/\d{2,4})?.*)$/u', $str, $m ) && '' !== trim( $m[1] ) ) {
			$start = trim( $m[1] );
			$end   = trim( $m[2] );
			if ( ! preg_match( '/\b\d{4}\b/', $start ) && preg_match( '/\b(\d{4})\b/', $end, $y ) ) {
				$start .= ( false !== strpos( $start, '/' ) ) ? '/' . $y[1] : ' ' . $y[1];
			}
			$str = $start;
		}
		// "18-20" / "18th-20th" -> "18". Ordinal suffixes must be part of the match:
		// strtotime mis-parses an unnormalised "March 20th-21st, 2027" into MARCH 2026,
		// which silently dropped future events as "past" (seen on the fleet blueprint).
		$norm = preg_replace( '/(\d{1,2})(?:st|nd|rd|th)?\s*' . $dash . '\s*\d{1,2}(?:st|nd|rd|th)?/iu', '$1', $str );
		$ts = strtotime( $norm );
		return $ts ? (int) $ts : 0;
	}

	/** Tournament Cards — upcoming events (by the event_date ACF field), image with an overlapping details card. */
	/**
	 * Options for the Event Card filter Tabs Source. Nothing is invented here:
	 * the list is discovered live from (a) every taxonomy with an admin UI —
	 * whose TERMS partners edit in the normal WP screens — and (b) every ACF
	 * choice field (select / checkbox / radio / button group) registered on the
	 * site. Labelled with the post types they belong to so devs pick the right
	 * facet for the queried post type.
	 */
	public static function tourn_tab_options() {
		$opts = array( '' => __( 'None (hide tabs)', 'ds-toolkit' ) );
		$skip = array( 'post_format', 'nav_menu', 'link_category', 'wp_theme', 'wp_template_part_area', 'fl-builder-template-category', 'fl-builder-template-type' );
		foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $tax ) {
			if ( in_array( $tax->name, $skip, true ) ) { continue; }
			$types = implode( ', ', (array) $tax->object_type );
			$opts[ 'tax:' . $tax->name ] = sprintf( __( 'Taxonomy: %1$s — on: %2$s', 'ds-toolkit' ), $tax->label, $types );
		}
		if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
			foreach ( acf_get_field_groups() as $group ) {
				foreach ( (array) acf_get_fields( $group ) as $field ) {
					if ( in_array( $field['type'], array( 'select', 'checkbox', 'radio', 'button_group' ), true ) ) {
						$opts[ 'meta:' . $field['name'] ] = sprintf( __( 'ACF field: %1$s (%2$s)', 'ds-toolkit' ), $field['label'], $group['title'] );
					}
				}
			}
		}
		return $opts;
	}

	/** Read an event meta value as a trimmed list (ACF checkbox array or comma text). */
	private function tourn_list( $pid, $key ) {
		$v = function_exists( 'get_field' ) ? get_field( $key, $pid ) : get_post_meta( $pid, $key, true );
		if ( is_array( $v ) ) { $v = implode( ',', $v ); }
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $v ) ) ) );
	}

	/** Event state: explicit event_state field, else the ", XX" tail of the location. */
	private function tourn_state( $pid, $loc ) {
		$st = function_exists( 'get_field' ) ? trim( (string) get_field( 'event_state', $pid ) ) : trim( (string) get_post_meta( $pid, 'event_state', true ) );
		if ( '' === $st && preg_match( '/,\s*([A-Za-z]{2})\.?\s*$/', $loc, $m ) ) { $st = $m[1]; }
		return strtoupper( $st );
	}

	/**
	 * The values that drive the filter TABS (and the row eyebrow / grid chips)
	 * for one event, per the developer-chosen source: a taxonomy ("tax:<slug>"),
	 * a meta/ACF field ("meta:<key>", default event_gender), or none.
	 */
	private function tourn_tab_values( $pid, $src ) {
		if ( '' === $src ) { return array(); }
		if ( 0 === strpos( $src, 'tax:' ) ) {
			$terms = get_the_terms( $pid, substr( $src, 4 ) );
			return ( $terms && ! is_wp_error( $terms ) ) ? array_values( wp_list_pluck( $terms, 'name' ) ) : array();
		}
		if ( 0 === strpos( $src, 'meta:' ) ) {
			return $this->tourn_list( $pid, substr( $src, 5 ) );
		}
		return array();
	}

	/** The Event Card filter bar. Every element is developer-configurable: tabs source, state dropdown, search, count. */
	private function tourn_filter_bar( $rows ) {
		$s          = $this->settings;
		$show_state  = ( $s->tn_filter_state ?? 'show' ) === 'show';
		$show_search = ( $s->tn_filter_search ?? 'show' ) === 'show';
		$show_count  = ( $s->tn_filter_count ?? 'show' ) === 'show';

		$tabs = array(); $states = array();
		foreach ( $rows as $r ) {
			foreach ( $r['tabs'] as $t ) { $tabs[ strtolower( $t ) ] = $t; }
			if ( '' !== $r['state'] ) { $states[ $r['state'] ] = $r['state'] ; }
		}
		ksort( $tabs ); ksort( $states );
		$pin  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>';
		$mag  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>';
		echo '<div class="ds-tourn-filter">';
		if ( ! empty( $tabs ) ) {
			echo '<div class="ds-tourn-tabs" role="group" aria-label="' . esc_attr__( 'Filter events', 'ds-toolkit' ) . '">';
			echo '<button type="button" class="ds-tourn-tab is-active" data-tab="">' . esc_html__( 'All', 'ds-toolkit' ) . '</button>';
			foreach ( $tabs as $key => $label ) {
				echo '<button type="button" class="ds-tourn-tab" data-tab="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</button>';
			}
			echo '</div>';
		}
		if ( $show_state && ! empty( $states ) ) {
			echo '<label class="ds-tourn-state">' . $pin . '<select aria-label="' . esc_attr__( 'Filter by state', 'ds-toolkit' ) . '"><option value="">' . esc_html__( 'All states', 'ds-toolkit' ) . '</option>';
			foreach ( $states as $st ) { echo '<option value="' . esc_attr( $st ) . '">' . esc_html( $st ) . '</option>'; }
			echo '</select></label>';
		}
		if ( $show_search ) {
			echo '<div class="ds-tourn-search">' . $mag . '<input type="search" placeholder="' . esc_attr__( 'Search events…', 'ds-toolkit' ) . '" aria-label="' . esc_attr__( 'Search events', 'ds-toolkit' ) . '"></div>';
		}
		if ( $show_count ) {
			echo '<span class="ds-tourn-count">' . count( $rows ) . ' ' . esc_html( 1 === count( $rows ) ? __( 'event', 'ds-toolkit' ) : __( 'events', 'ds-toolkit' ) ) . '</span>';
		}
		echo '</div>';
	}

	public function render_tournament() {
		$s     = $this->settings;
		$ptype = preg_replace( '/[^a-z0-9_-]/', '', (string) ( $s->post_type ?? 'event' ) );
		if ( '' === $ptype || 'post' === $ptype ) { $ptype = 'event'; }
		$limit = (int) ( $s->posts_per_page ?? 6 ); if ( $limit <= 0 ) { $limit = 6; }
		// Pull wide (ordering + the past-event drop happen below, on event_date), but
		// still scope to the Taxonomy Filter so two pages can list two different sets
		// of events (e.g. a Boys and a Girls tournaments page off one CPT).
		// Entries without an Event Date all tie in the sort below, which keeps the query's order (usort is stable on
		// PHP 8), so the query follows the Query tab's Order By / Order. Before 1.18.2 it was always newest first,
		// so a programs page set to Menu Order came out in publish order (lovellhockey Rockland).
		$ob = (string) ( $s->order_by ?? 'date' );
		if ( ! in_array( $ob, array( 'date', 'title', 'menu_order', 'modified', 'rand' ), true ) ) { $ob = 'date'; }
		$od = ( strtoupper( (string) ( $s->order ?? 'DESC' ) ) === 'ASC' ) ? 'ASC' : 'DESC';
		if ( 'menu_order' === $ob && self::nested_order_enabled() ) { $od = 'ASC'; }
		$q_args = array( 'post_type' => $ptype, 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'rand' === $ob ? 'rand' : array( $ob => $od, 'ID' => 'ASC' ) );
		$tq     = $this->tax_query_args();
		if ( ! empty( $tq ) ) { $q_args['tax_query'] = $tq; }
		// This layout builds its own query (it sorts on the event_date ACF field, so it
		// never goes through run_query), which is exactly how the Taxonomy Filter came to
		// be ignored here in 1.9.71. Include / Exclude has to be applied in both places.
		list( $tn_inc, $tn_exc ) = $this->include_exclude_ids( $ptype );
		if ( ! empty( $tn_inc ) ) { $q_args['post__in'] = $tn_inc; }
		if ( ! empty( $tn_exc ) ) { $q_args['post__not_in'] = $tn_exc; }
		$posts = get_posts( $q_args );
		$today = strtotime( 'today' );
		$items = array();
		foreach ( $posts as $p ) {
			$raw = function_exists( 'get_field' ) ? (string) get_field( 'event_date', $p->ID ) : (string) get_post_meta( $p->ID, 'event_date', true );
			$ts  = $this->parse_event_date( $raw );
			if ( $ts && $ts < $today ) { continue; } // past events are dropped
			$items[] = array( 'p' => $p, 'ts' => $ts ? $ts : PHP_INT_MAX, 'raw' => $raw );
		}
		usort( $items, function ( $a, $b ) { return $a['ts'] <=> $b['ts']; } ); // upcoming first (event_date ascending)
		$items = array_slice( $items, 0, $limit );

		$style     = ( ( $s->tn_style ?? 'overlap' ) === 'event' ) ? 'event' : 'overlap';
		$list      = 'event' === $style && ( $s->tn_display ?? 'grid' ) === 'list';
		$filter_on = 'event' === $style && ( $s->tn_filter ?? 'disable' ) === 'enable' && ! empty( $items );

		$root = 'ds-news ds-tourn' . ( 'event' === $style ? ' ds-tourn--event' : '' ) . ( $list ? ' ds-tourn--list' : '' ) . ( $filter_on ? ' ds-tourn--filter' : '' );
		echo '<section class="' . esc_attr( $root ) . '"><div class="ds-news-wrap">';
		$this->render_head();
		if ( empty( $items ) ) {
			$this->empty_state( __( 'No upcoming events. Publish events with a future Event Date.', 'ds-toolkit' ) );
			echo '</div></section>'; return;
		}

		// Enriched rows (the Event Card style + filter bar read tabs/division/state).
		// The tab facet is developer-chosen: a taxonomy, a meta field, or none.
		$tabsrc     = (string) ( $s->tn_filter_tabs ?? '' );
		$excerpt_on = 'show' === ( $s->tn_excerpt ?? 'hide' );
		$excerpt_n  = (int) ( $s->tn_excerpt_words ?? 18 );
		$excerpt_n  = $excerpt_n > 0 ? min( 60, max( 5, $excerpt_n ) ) : 18;
		$rows = array();
		foreach ( $items as $it ) {
			$p = $it['p']; $pid = $p->ID;
			$feat = has_post_thumbnail( $pid ) ? (string) get_the_post_thumbnail_url( $pid, 'large' ) : '';
			if ( '' === $feat ) { $feat = self::placeholder_image(); }
			$loc  = function_exists( 'get_field' ) ? trim( (string) get_field( 'event_location', $pid ) ) : '';
			$rows[] = array(
				'pid'      => $pid,
				'url'      => get_permalink( $pid ),
				'title'    => get_the_title( $pid ),
				'date'     => $it['raw'],
				'ts'       => $it['ts'],
				'feat'     => $feat,
				'logo'     => $this->acf_image_url( ( function_exists( 'get_field' ) ? get_field( 'event_image', $pid ) : '' ) ?: '' ),
				'loc'      => $loc,
				'tabs'     => $this->tourn_tab_values( $pid, $tabsrc ),
				'division' => $this->tourn_list( $pid, 'event_division' ),
				'state'    => $this->tourn_state( $pid, $loc ),
				'excerpt'  => $excerpt_on ? wp_trim_words( wp_strip_all_tags( get_the_excerpt( $p ) ), $excerpt_n, '…' ) : '',
			);
		}

		if ( $filter_on ) { $this->tourn_filter_bar( $rows ); }

		$cal    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/></svg>';
		$pin    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>';
		$person = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6.5 8-6.5s8 2.5 8 6.5"/></svg>';
		$tag    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20.6 13.4 12 22l-8.6-8.6a2 2 0 0 1-.6-1.4V4a2 2 0 0 1 2-2h8a2 2 0 0 1 1.4.6l8.4 8.4a2 2 0 0 1 0 2.8z" transform="scale(.9) translate(1 1)"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>';
		$arrow  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
		$btn    = trim( (string) ( $s->tourn_btn ?? '' ) );
		if ( '' === $btn ) { $btn = $list ? __( 'Register', 'ds-toolkit' ) : ( 'event' === $style ? __( 'View Event Details', 'ds-toolkit' ) : __( 'View More', 'ds-toolkit' ) ); }
		$badge  = 'event' === $style ? trim( (string) ( $s->tn_badge ?? __( 'Tournament', 'ds-toolkit' ) ) ) : '';

		$this->loop_open( 'ds-tourn-grid' );
		foreach ( $rows as $r ) {
			$data = '';
			if ( $filter_on ) {
				$q     = strtolower( $r['title'] . ' ' . $r['loc'] . ' ' . implode( ' ', $r['division'] ) . ' ' . implode( ' ', $r['tabs'] ) );
				$data  = ' data-tabs="' . esc_attr( strtolower( implode( ' ', $r['tabs'] ) ) ) . '"';
				$data .= ' data-state="' . esc_attr( $r['state'] ) . '"';
				$data .= ' data-q="' . esc_attr( $q ) . '"';
			}

			if ( $list ) {
				// List row: date tile · logo thumb · gender eyebrow + title + meta · chips + button.
				echo '<a class="ds-tourn-card ds-tourn-card--row" href="' . esc_url( $r['url'] ) . '"' . $data . '>';
				if ( $r['ts'] && PHP_INT_MAX !== $r['ts'] ) {
					echo '<span class="ds-tourn-when" aria-hidden="true"><span class="ds-tourn-when-m">' . esc_html( date_i18n( 'F', $r['ts'] ) ) . '</span><span class="ds-tourn-when-d">' . esc_html( date_i18n( 'd', $r['ts'] ) ) . '</span></span>';
				}
				$thumb = $r['logo'] ?: $r['feat'];
				if ( '' !== $thumb ) { echo '<span class="ds-tourn-thumb"><img src="' . esc_url( $thumb ) . '" alt="" loading="lazy" /></span>'; }
				echo '<span class="ds-tourn-rowmain">';
				if ( ! empty( $r['tabs'] ) ) { echo '<span class="ds-tourn-eyebrow">' . esc_html( implode( ' & ', $r['tabs'] ) ) . '</span>'; }
				echo '<h3 class="ds-tourn-title">' . DS_Module_UI::inline( $r['title'] ) . '</h3>';
				echo '<span class="ds-tourn-rowmeta">';
				if ( '' !== $r['loc'] )  { echo '<span class="ds-tourn-loc">' . $pin . '<span>' . esc_html( $r['loc'] ) . '</span></span>'; }
				if ( '' !== $r['date'] ) { echo '<span class="ds-tourn-date">' . $cal . '<span>' . esc_html( $r['date'] ) . '</span></span>'; }
				echo '</span>';
				if ( '' !== $r['excerpt'] ) { echo '<span class="ds-tourn-excerpt">' . esc_html( $r['excerpt'] ) . '</span>'; }
				echo '</span>';
				echo '<span class="ds-tourn-rowside">';
				if ( ! empty( $r['division'] ) ) {
					echo '<span class="ds-tourn-chips">';
					foreach ( $r['division'] as $d ) { echo '<span class="ds-tourn-chip ds-tourn-chip--outline">' . esc_html( $d ) . '</span>'; }
					echo '</span>';
				}
				echo '<span class="ds-tourn-btn ds-tourn-btn--row">' . esc_html( $btn ) . $arrow . '</span>';
				echo '</span></a>';
				continue;
			}

			echo '<a class="ds-tourn-card' . ( 'event' === $style ? ' ds-tourn-card--event' : '' ) . '" href="' . esc_url( $r['url'] ) . '"' . $data . '>';
			echo '<div class="ds-tourn-img"' . ( $r['feat'] ? ' style="background-image:url(' . esc_url( $r['feat'] ) . ')"' : '' ) . '>';
			if ( 'event' === $style && '' !== $badge ) { echo '<span class="ds-tourn-badge">' . esc_html( $badge ) . '</span>'; }
			if ( '' !== $r['logo'] ) { echo '<span class="ds-tourn-logo"><img src="' . esc_url( $r['logo'] ) . '" alt="" loading="lazy" /></span>'; }
			echo '</div>';
			echo '<div class="ds-tourn-body">';
			echo '<h3 class="ds-tourn-title">' . DS_Module_UI::inline( $r['title'] ) . '</h3>';
			echo '<div class="ds-tourn-meta">';
			if ( '' !== $r['date'] ) { echo '<span class="ds-tourn-date">' . $cal . '<span>' . esc_html( $r['date'] ) . '</span></span>'; }
			if ( '' !== $r['loc'] )  { echo '<span class="ds-tourn-loc">' . $pin . '<span>' . esc_html( $r['loc'] ) . '</span></span>'; }
			if ( 'event' === $style && ! empty( $r['tabs'] ) ) {
				echo '<span class="ds-tourn-row">' . $person . '<span class="ds-tourn-chips"><span class="ds-tourn-chip">' . esc_html( implode( ', ', $r['tabs'] ) ) . '</span></span></span>';
			}
			if ( 'event' === $style && ! empty( $r['division'] ) ) {
				echo '<span class="ds-tourn-row">' . $tag . '<span class="ds-tourn-chips">';
				foreach ( $r['division'] as $d ) { echo '<span class="ds-tourn-chip">' . esc_html( $d ) . '</span>'; }
				echo '</span></span>';
			}
			echo '</div>';
			if ( '' !== $r['excerpt'] ) { echo '<p class="ds-tourn-excerpt">' . esc_html( $r['excerpt'] ) . '</p>'; }
			echo '<span class="ds-tourn-btn' . ( 'event' === $style ? ' ds-tourn-btn--full' : '' ) . '">' . esc_html( $btn ) . '</span>';
			echo '</div></a>';
		}
		$this->loop_close();
		if ( $filter_on ) { echo '<p class="ds-tourn-none" hidden>' . esc_html__( 'No events match your filters.', 'ds-toolkit' ) . '</p>'; }
		echo '</div></section>';
	}
}
