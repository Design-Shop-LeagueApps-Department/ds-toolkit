<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once DS_TOOLKIT_PATH . 'includes/class-ds-module-ui.php';

/**
 * LeagueApps Post Loop — an in-house Beaver Builder query-loop module.
 *
 * Powerful, query-driven loop over any post type. The Query tab is the SINGLE
 * source of truth for WHAT is fetched (Post Type + number + order + taxonomy);
 * a single "Card Layout" picker decides HOW each result is drawn (News, Staff,
 * Athletes, Team, or a Custom HTML loop). Internal markup keeps the .ds-news-*
 * class names + style1/style2 layout keys for backward-compatibility.
 *
 * A multi-STYLE module (same pattern as ds-hero / ds-cta). Style 1 is the
 * "Featured + Grid" layout (modeled on the threehl home design): a header row
 * ({a}accent{/a} heading + a "See all" angled button), then a grid with one big
 * FEATURED card (background image, gradient overlay, badge, title, excerpt,
 * "Read More") spanning two rows on the left, and a grid of small news cards
 * (category eyebrow, title, date, chevron) on the right.
 *
 * Content is QUERY-DRIVEN (a "Query" tab): it pulls posts from any public post
 * type with order / taxonomy filters; the newest/first post becomes the
 * featured card and the rest fill the loop. The loop card can render with the
 * built-in design OR a "Custom" layout where the editor supplies HTML and/or a
 * shortcode (e.g. [fl_builder_insert_layout id=123]) rendered once per post with
 * that post's data in scope.
 *
 * HOW TO ADD A NEW LAYOUT:
 *   1. Add a key + label to self::card_layouts().
 *   2. Add a render_<key>() method (reuse run_query() / collect_items() helpers).
 *   3. Map the key to that method in render_loop(), and list its option sections
 *      under the `card_layout` select's `toggle`.
 *   4. Scope style CSS under the auto-printed `.ds-news--<key>` modifier class.
 *
 * No UABB dependency; fully SSH-editable.
 *
 * @class DS_Post_Loop_Module
 */
class DS_Post_Loop_Module extends FLBuilderModule {

	public function __construct() {
		parent::__construct( array(
			'name'            => __( 'Post Loop', 'ds-toolkit' ),
			'description'     => __( 'Query-driven post loop (News, with Staff / Team / Athletes presets to come). Multiple layouts.', 'ds-toolkit' ),
			'category'        => __( 'LeagueApps', 'ds-toolkit' ),
			'dir'             => DS_TOOLKIT_PATH . 'modules/ds-post-loop/',
			'url'             => DS_TOOLKIT_URL . 'modules/ds-post-loop/',
			'partial_refresh' => false,
			'editor_export'   => false,
		) );
		$this->add_css( 'font-awesome-5' );
	}

	/**
	 * Layout options for the Layout dropdown. The News-specific designs first, then
	 * the universal "Loop Card" — always offered for every content type (it loops any
	 * post type into a grid of built-in or custom cards). Layout keys kept for data compat.
	 */
	/**
	 * Manual-list layouts that no longer belong in this module.
	 *
	 * Neither queries a post type, so they contradict this module's contract
	 * ("loop over posts of type X"). They now live in the CTA module. They are
	 * still RENDERED here forever — legacy instances, revision restores and
	 * un-migrated sites all resolve to them — but they are hidden from the Card
	 * Layout picker so no NEW instance can be built on them.
	 *
	 * @see DS_Program_Cards
	 */
	public static function retired_layouts() {
		return array(
			'program' => __( 'Custom Program Card (manual list) — moved to CTA', 'ds-toolkit' ),
			// NOTE: `sponsor` has the same defect (manual list, no query) and belongs
			// in CTA too, but it has no CTA style yet. Do NOT retire it here until
			// one exists — hiding it from the picker with nowhere to build it would
			// leave editors unable to create a sponsor grid at all.
		);
	}

	/**
	 * Card Layout options.
	 *
	 * Retired manual-list layouts are omitted, EXCEPT when the module instance
	 * being edited already uses one. Without that exception the select would have
	 * no matching option and Beaver Builder would silently fall back to the first
	 * one, silently converting a partner's program list into a news grid the
	 * moment they opened the panel.
	 */
	public static function card_layouts() {
		$layouts = array(
			'news_featured'  => __( 'News: Featured + Grid', 'ds-toolkit' ),
			'news_grid'      => __( 'News: Card Grid', 'ds-toolkit' ),
			'staff_card'     => __( 'Staff: Cards', 'ds-toolkit' ),
			'athlete_photo'  => __( 'Athletes: Photo Cards', 'ds-toolkit' ),
			'athlete_logo'   => __( 'Athletes: Logo Row (dark)', 'ds-toolkit' ),
			'athlete_action' => __( 'Athletes: Action Cards', 'ds-toolkit' ),
			'athlete_strip'  => __( 'Athletes: Compact Strip', 'ds-toolkit' ),
			'team_list'      => __( 'Teams: List', 'ds-toolkit' ),
			'team_card'      => __( 'Teams: Cards (photo grid)', 'ds-toolkit' ),
			// Still selectable: it has the same manual-list defect as `program` but no
			// CTA style to move to yet. See retired_layouts().
			'sponsor'        => __( 'Sponsors: Grid (manual list)', 'ds-toolkit' ),
			'tournament'     => __( 'Tournament Cards (events, upcoming)', 'ds-toolkit' ),
			'custom'         => __( 'Custom Loop Layout', 'ds-toolkit' ),
		);

		$current = self::editing_card_layout();
		$retired = self::retired_layouts();
		if ( '' !== $current && isset( $retired[ $current ] ) ) {
			$layouts[ $current ] = $retired[ $current ];
		}

		return $layouts;
	}

	/**
	 * The card_layout of the module instance currently open in the builder.
	 *
	 * Returns '' when a new module is being inserted (no node id yet), which is
	 * exactly when the retired layouts must stay hidden.
	 *
	 * @return string
	 */
	private static function editing_card_layout() {
		if ( ! class_exists( 'FLBuilderModel' ) ) {
			return '';
		}
		$node_id = isset( $_GET['node'] ) ? sanitize_text_field( wp_unslash( $_GET['node'] ) ) : '';
		if ( '' === $node_id && isset( $_POST['node_id'] ) ) {
			$node_id = sanitize_text_field( wp_unslash( $_POST['node_id'] ) );
		}
		if ( '' === $node_id ) {
			return '';
		}
		$node = FLBuilderModel::get_node( $node_id );
		if ( ! is_object( $node ) || ! isset( $node->settings->card_layout ) ) {
			return '';
		}
		return (string) $node->settings->card_layout;
	}
	/** Public post types for the Query tab's Post Type select. */
	/**
	 * The Include / Exclude id lists for the post type currently selected (GH #132).
	 *
	 * The suggest fields are generated per post type (see the form builder at the
	 * bottom of this file), because a BB settings form is registered once at init
	 * and cannot know which type a given module instance has chosen. So the values
	 * live in `inc_<type>` / `exc_<type>` and only the pair matching the active
	 * type is read — switching Post Type therefore reveals that type's own picks
	 * instead of silently applying another type's ids.
	 *
	 * BB's suggest field stores a comma-separated id string; older saves and
	 * programmatic writes can hand back an array, so both shapes are accepted.
	 *
	 * @return array{0:array<int>,1:array<int>} include ids, exclude ids
	 */
	private function include_exclude_ids( $ptype ) {
		$s   = $this->settings;
		$key = str_replace( '-', '_', (string) $ptype );
		$get = function ( $field ) use ( $s ) {
			$v = $s->{$field} ?? '';
			if ( is_array( $v ) ) { $ids = $v; } else { $ids = explode( ',', (string) $v ); }
			$ids = array_filter( array_map( 'absint', $ids ) );
			return array_values( array_unique( $ids ) );
		};
		return array( $get( 'inc_' . $key ), $get( 'exc_' . $key ) );
	}

	/**
	 * What a visitor sees when the query returns nothing.
	 *
	 * Every layout used to hide its empty message behind is_builder_active(), so an
	 * editor saw an explanation and the public saw a bare heading over blank space
	 * (reported on /programs/tournaments/). The notice now renders on the front end
	 * too, worded by the partner rather than by us.
	 *
	 * $hint stays builder-only on purpose: "Publish events with a future Event Date"
	 * is a diagnostic for whoever is building the page, not copy for a visitor. In the
	 * builder both appear, so an editor sees what the public sees AND why it is empty.
	 *
	 * @param string $hint Builder-only diagnostic for this layout.
	 */
	private function empty_state( $hint = '' ) {
		$s    = $this->settings;
		$show = ( $s->empty_show ?? 'yes' ) === 'yes';

		if ( $show ) {
			$badge   = trim( (string) ( $s->empty_text ?? '' ) );
			$heading = trim( (string) ( $s->empty_heading ?? '' ) );
			$desc    = trim( (string) ( $s->empty_desc ?? '' ) );
			// Defaults so the notice is never blank on a site that has not touched
			// these fields — which is every existing build.
			if ( '' === $badge && '' === $heading && '' === $desc ) {
				$heading = __( 'Coming Soon', 'ds-toolkit' );
			}
			$align = ( ( $s->empty_align ?? 'center' ) === 'left' ) ? 'left' : 'center';

			echo '<div class="ds-loop-empty ds-loop-empty--' . esc_attr( $align ) . '">';
			if ( '' !== $badge )   { echo '<span class="ds-loop-empty-badge">' . esc_html( $badge ) . '</span>'; }
			if ( '' !== $heading ) { echo '<h3 class="ds-loop-empty-title">' . DS_Module_UI::inline( $heading ) . '</h3>'; }
			if ( '' !== $desc )    { echo '<div class="ds-loop-empty-desc">' . wpautop( wp_kses_post( $desc ) ) . '</div>'; }
			echo '</div>';
		}

		if ( '' !== $hint && class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_active() ) {
			echo '<p class="ds-loop-empty-hint" style="padding:14px;opacity:.7">' . esc_html( $hint ) . '</p>';
		}
	}

	public static function post_type_options() {
		$out = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			if ( 'attachment' === $pt->name ) { continue; }
			$out[ $pt->name ] = $pt->label ? $pt->label : $pt->name;
		}
		if ( empty( $out ) ) { $out = array( 'post' => __( 'Posts', 'ds-toolkit' ) ); }
		return $out;
	}

	/**
	 * Fallback image for a post with no featured image: the Theme Setting
	 * Social Card (so empty cards still show a branded image, not a blank box).
	 */
	/**
	 * Fallback image for entries without a featured image.
	 * Launchpad 6: the Theme Setting Social Card. Launchpad 5 (or any site
	 * without the Theme Setting card): the stock social card that every DSLP5
	 * build ships in its media library.
	 */
	private static function placeholder_image() {
		if ( class_exists( 'DS_Toolkit' ) && DS_Toolkit::blueprint_version() >= 6 ) {
			$url = DS_Card::placeholder_image(); // Theme Setting → Social Card
			if ( '' !== $url ) { return $url; }
		}
		return home_url( '/wp-content/uploads/2024/11/lasocialcard1.png' );
	}

	/** Heading markup: escape, {a}..{/a} -> accent span, newlines -> <br>. */
	private function heading_html( $raw ) {
		$h = DS_Module_UI::inline( (string) $raw ); // safe inline HTML (span/strong/em...) allowed
		$h = str_replace( array( '{a}', '{/a}' ), array( '<span class="ds-news-accent">', '</span>' ), $h );
		$h = str_replace( array( '{outline}', '{/outline}' ), array( '<span class="ds-outline-text">', '</span>' ), $h );
		return nl2br( $h );
	}

	/** Normalise a BB link field (string URL or {url,target} object/array). */
	private function link_parts( $link ) {
		return DS_Card::link_parts( $link );
	}

	/**
	 * Run the configured post query and return a normalised item list. Each item
	 * is an array: id, image(url), eyebrow, title, date, excerpt, url, target.
	 * Item 0 is the featured card; the rest are loop cards.
	 */
	/**
	 * The Taxonomy Filter tab as a `tax_query`, or an empty array when no filter is
	 * configured. Terms come from the per-taxonomy suggest field `flt_<tax>`
	 * (comma-separated term IDs), falling back to the legacy `filter_terms` text
	 * field. Shared so EVERY layout that exposes the Taxonomy Filter section honours
	 * it — the Tournament layout builds its own query (it sorts by the event_date
	 * ACF field, not by WP_Query) and silently ignored the filter before this.
	 */
	private function tax_query_args() {
		$s   = $this->settings;
		$tax = trim( (string) ( $s->filter_tax ?? '' ) );
		if ( '' === $tax || ! taxonomy_exists( $tax ) ) { return array(); }

		$field_key = 'flt_' . str_replace( '-', '_', $tax );
		$raw       = $s->$field_key ?? '';
		if ( is_array( $raw ) ) { $raw = implode( ',', $raw ); }
		if ( '' === trim( (string) $raw ) && isset( $s->filter_terms ) ) { $raw = (string) $s->filter_terms; }

		$list = array_filter( array_map( 'trim', explode( ',', (string) $raw ) ) );
		if ( empty( $list ) ) { return array(); }

		$numeric = count( $list ) === count( array_filter( $list, 'is_numeric' ) );
		return array(
			array(
				'taxonomy' => $tax,
				'field'    => $numeric ? 'term_id' : 'slug',
				'terms'    => $list,
			),
		);
	}

	/**
	 * The ONE query: built entirely from the Query tab (Post Type + number + order +
	 * offset + taxonomy filter). Single source of truth for WHAT the loop fetches.
	 */
	/**
	 * Is "Nested Pages Order on the Front End" switched on for this site?
	 *
	 * Read straight from the option rather than through DS_Nested_Order, because
	 * that class is only loaded when the feature is enabled, so referencing it
	 * here would fatal on every site that has it off.
	 *
	 * @return bool
	 */
	private static function nested_order_enabled() {
		$s = get_option( 'ds_toolkit_settings' );
		return is_array( $s ) && ! empty( $s['nested_order_enabled'] );
	}

	private function run_query() {
		$s     = $this->settings;

		// Archive source: render whatever the CURRENT archive query returns (taxonomy
		// term, category, tag, CPT archive) so ONE loop powers any archive — the engine
		// behind the Themer archive template (mirrors DS5's blog-posts main_query).
		if ( ( $s->source ?? 'custom' ) === 'archive' && ! empty( $GLOBALS['wp_query'] ) && is_a( $GLOBALS['wp_query'], 'WP_Query' ) ) {
			$qv = $GLOBALS['wp_query']->query_vars;
			$qv['post_status']         = 'publish';
			$qv['ignore_sticky_posts'] = true;
			return new WP_Query( $qv );
		}

		$ptype = preg_replace( '/[^a-z0-9_-]/', '', (string) ( $s->post_type ?? 'post' ) ) ?: 'post';
		$num   = (int) ( $s->posts_per_page ?? 5 );
		// Resolve the fallback ONCE. Reading $s->order_by again in the true branch
		// warned "Undefined property" on any layout saved before the field existed —
		// and worse, returned null rather than 'date', because the ?? default is
		// itself a valid option so an unset property takes exactly that branch.
		$ob    = (string) ( $s->order_by ?? 'date' );
		if ( ! in_array( $ob, array( 'date', 'title', 'menu_order', 'rand', 'modified', 'meta_value', 'meta_value_num' ), true ) ) { $ob = 'date'; }
		$order = ( strtoupper( (string) ( $s->order ?? 'DESC' ) ) === 'ASC' ) ? 'ASC' : 'DESC';
		// "Menu Order / Nested Pages position" is a sequence somebody deliberately
		// dragged, and Nested Pages numbers it ascending from the top of the list.
		// Descending renders that order backwards, and the Order field's
		// date-flavoured labels ("Descending (newest first)") give an editor no
		// reason to expect it, so the module shipped DESC by default and quietly
		// reversed every dragged order.
		//
		// Gated on the same setting as the archive ordering so ONE switch governs
		// "follow the Nested Pages order". With it off, a live site keeps rendering
		// exactly as it does today rather than having its visible order changed by
		// a plugin update.
		if ( 'menu_order' === $ob && self::nested_order_enabled() ) { $order = 'ASC'; }

		$args = array(
			'post_type'           => $ptype,
			'post_status'         => 'publish',
			'posts_per_page'      => $num > 0 ? $num : -1,
			'orderby'             => $ob,
			'order'               => $order,
			'offset'              => max( 0, (int) ( $s->offset ?? 0 ) ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);

		// On a single view, optionally drop the post being viewed so a "More News /
		// Related" strip never relists the current article.
		$not_in = array();
		if ( ( $s->exclude_current ?? 'no' ) === 'yes' && is_singular() ) {
			$not_in[] = get_queried_object_id();
		}

		// Hand-picked posts (GH #132). Include narrows the loop to exactly those
		// entries; exclude drops them from an otherwise normal query. Order, count
		// and offset are left alone on purpose so every existing query setting keeps
		// working — in particular `orderby` is NOT forced to post__in, so an included
		// set still sorts by whatever the module is set to.
		list( $inc, $exc ) = $this->include_exclude_ids( $ptype );
		if ( ! empty( $inc ) ) { $args['post__in'] = $inc; }
		if ( ! empty( $exc ) ) { $not_in = array_merge( $not_in, $exc ); }
		// Merged rather than assigned: Exclude Current Post writes here too, and one
		// overwriting the other would silently drop a filter the editor had set.
		if ( ! empty( $not_in ) ) { $args['post__not_in'] = array_values( array_unique( $not_in ) ); }

		// Order by a custom meta field (e.g. an ACF key). Falls back to date if no key.
		if ( 'meta_value' === $ob || 'meta_value_num' === $ob ) {
			$mk = trim( (string) ( $s->order_meta_key ?? '' ) );
			if ( '' !== $mk ) { $args['meta_key'] = $mk; } else { $args['orderby'] = 'date'; }
		}

		$tq = $this->tax_query_args();
		if ( ! empty( $tq ) ) { $args['tax_query'] = $tq; }

		// Date-range filter (GH #46). Bounds parse via strtotime, so absolute
		// dates (2026-01-01) and relative windows (-30 days) both work; only
		// the bounds that actually parse are applied.
		$after  = trim( (string) ( $s->date_after ?? '' ) );
		$before = trim( (string) ( $s->date_before ?? '' ) );
		$dq     = array();
		if ( '' !== $after && false !== ( $ts = strtotime( $after ) ) ) { $dq['after'] = gmdate( 'Y-m-d', $ts ); }
		if ( '' !== $before && false !== ( $ts = strtotime( $before ) ) ) { $dq['before'] = gmdate( 'Y-m-d', $ts ); }
		if ( ! empty( $dq ) ) {
			$dq['inclusive']    = true;
			$args['date_query'] = array( $dq );
		}

		// Keyword search (GH #46) — matches title/content like the site search.
		$kw = trim( (string) ( $s->keyword ?? '' ) );
		if ( '' !== $kw ) { $args['s'] = sanitize_text_field( $kw ); }

		// The Post Types Order plugin rewrites every query's ORDER BY to
		// menu_order unless the query opts out; honour the explicit sort chosen
		// here (except when the user actually picked Menu Order).
		if ( 'menu_order' !== $ob ) { $args['ignore_custom_sort'] = true; }

		return new WP_Query( $args );
	}

	/**
	 * Run the query and normalise to item arrays for the News / Custom layouts.
	 */
	private function collect_items() {
		$s    = $this->settings;
		$dfmt = trim( (string) ( $s->date_format ?? '' ) ) ?: 'M Y';
		$tax  = trim( (string) ( $s->filter_tax ?? '' ) );

		$items = array();
		$q     = $this->run_query();
		foreach ( $q->posts as $p ) {
			$terms_obj = get_the_category( $p->ID );
			$eyebrow   = ! empty( $terms_obj ) ? $terms_obj[0]->name : '';
			if ( '' === $eyebrow && '' !== $tax ) {
				$pt = get_the_terms( $p->ID, $tax );
				if ( $pt && ! is_wp_error( $pt ) ) { $eyebrow = $pt[0]->name; }
			}
			$items[] = array(
				'id'      => $p->ID,
				'image'   => get_the_post_thumbnail_url( $p->ID, 'large' ) ?: self::placeholder_image(),
				'eyebrow' => $eyebrow,
				'title'   => get_the_title( $p->ID ),
				'date'    => get_the_date( $dfmt, $p->ID ),
				'excerpt' => wp_strip_all_tags( get_the_excerpt( $p->ID ) ),
				'url'     => get_permalink( $p->ID ),
				'target'  => '_self',
			);
		}
		wp_reset_postdata();

		return $items;
	}
	/** ACF get_field with a graceful fallback when ACF is inactive. */
	private function acf( $key, $id ) {
		return function_exists( 'get_field' ) ? get_field( $key, $id ) : get_post_meta( $id, $key, true );
	}

	/** Resolve an ACF/photo value to a URL. */
	private function acf_image_url( $val, $size = 'large' ) {
		return DS_Card::photo_url( $val, $size );
	}

	/** Top-level entry — dispatch to the selected Content Type. */
	public function render_loop() {
		$layout = isset( $this->settings->card_layout ) ? preg_replace( '/[^a-z0-9_]/', '', $this->settings->card_layout ) : 'news_featured';
		$map = array(
			'news_featured'  => 'render_style1',
			'news_grid'      => 'render_style2',
			'staff_card'     => 'render_staff_card',
			'athlete_photo'  => 'render_athlete_photo',
			'athlete_logo'   => 'render_athlete_logo',
			'athlete_action' => 'render_athlete_action',
			'athlete_strip'  => 'render_athlete_strip',
			'team_list'      => 'render_team_list',
			'team_card'      => 'render_team_card',
			'sponsor'        => 'render_sponsor',
			'program'        => 'render_program',
			'tournament'     => 'render_tournament',
			'custom'         => 'render_cardloop',
		);
		$method = isset( $map[ $layout ] ) ? $map[ $layout ] : 'render_style1';
		if ( ! method_exists( $this, $method ) ) { $method = 'render_style1'; }
		$this->$method();
	}

	/** A round contact/social icon link. */
	private function contact_icon( $href, $svg, $label ) {
		if ( '' === trim( (string) $href ) ) { return ''; }
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

		$this->loop_open( 'ds-people-grid' );
		while ( $q->have_posts() ) {
			$q->the_post();
			$id    = get_the_ID();
			$img   = get_the_post_thumbnail_url( $id, 'large' ) ?: $ph;
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

			echo '<div class="ds-people-card">'; echo $this->card_link( $id );
			echo '<div class="ds-people-photo"' . ( $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : '' ) . '></div>';
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
				list( $url, $target ) = $this->link_parts( $s->button_link ?? '' );
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
			$this->empty_state( __( 'No sponsors yet. Add them in the Query tab (Sponsors list).', 'ds-toolkit' ) );
			echo '</div></section>'; return;
		}
		$this->loop_open( 'ds-sponsor-grid' );
		foreach ( $items as $it ) {
			$it   = (object) $it;
			$img  = $this->acf_image_url( $it->sponsor_image ?? '' ) ?: self::placeholder_image();
			$cap  = trim( (string) ( $it->sponsor_caption ?? '' ) );
			$desc = trim( (string) ( $it->sponsor_desc ?? '' ) );
			list( $url, $target ) = $this->link_parts( $it->sponsor_url ?? '' );
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

	/** Parse an event_date free-text value (e.g. "July 18-20, 2026") to a timestamp; 0 if unknown. */
	private function parse_event_date( $str ) {
		$str = trim( (string) $str );
		if ( '' === $str ) { return 0; }
		// "18-20" / "18th-20th" -> "18". Ordinal suffixes must be part of the match:
		// strtotime mis-parses an unnormalised "March 20th-21st, 2027" into MARCH 2026,
		// which silently dropped future events as "past" (seen on the fleet blueprint).
		$norm = preg_replace( '/(\d{1,2})(?:st|nd|rd|th)?\s*[\x{2013}\x{2014}-]\s*\d{1,2}(?:st|nd|rd|th)?/iu', '$1', $str );
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
		$q_args = array( 'post_type' => $ptype, 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'date', 'order' => 'DESC' );
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
		$tabsrc = (string) ( $s->tn_filter_tabs ?? '' );
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
				echo '</span></span>';
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
			echo '<span class="ds-tourn-btn' . ( 'event' === $style ? ' ds-tourn-btn--full' : '' ) . '">' . esc_html( $btn ) . '</span>';
			echo '</div></a>';
		}
		$this->loop_close();
		if ( $filter_on ) { echo '<p class="ds-tourn-none" hidden>' . esc_html__( 'No events match your filters.', 'ds-toolkit' ) . '</p>'; }
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
			__( 'No program cards yet. Add them in the Query tab (Program Cards list).', 'ds-toolkit' )
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

FLBuilder::register_settings_form( 'ds_sponsor_form', array(
	'title' => __( 'Sponsor', 'ds-toolkit' ),
	'tabs'  => array(
		'general' => array(
			'title'    => '',
			'sections' => array(
				'general' => array(
					'title'  => '',
					'fields' => array(
						'sponsor_image'   => array( 'type' => 'photo', 'label' => __( 'Logo / Image', 'ds-toolkit' ), 'show_remove' => true, 'connections' => array( 'photo' ) ),
						'sponsor_caption' => array( 'type' => 'text', 'label' => __( 'Caption / Name', 'ds-toolkit' ), 'connections' => array( 'string' ) ),
						'sponsor_url'     => array( 'type' => 'link', 'label' => __( 'Link URL', 'ds-toolkit' ), 'connections' => array( 'url' ) ),
						'sponsor_desc'    => array( 'type' => 'editor', 'media_buttons' => false, 'rows' => 6, 'wpautop' => false, 'label' => __( 'Description', 'ds-toolkit' ) ),
					),
				),
			),
		),
	),
) );

FLBuilder::register_settings_form( 'ds_program_form', array(
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
) );

$ds_pl_form = array(
	'content' => array(
		'title'    => __( 'Content', 'ds-toolkit' ),
		'sections' => array(
			'card_layout_sec' => array(
				'title'  => __( 'Layout', 'ds-toolkit' ),
				'fields' => array(
					'card_layout' => array(
						'type'    => 'select',
						'label'   => __( 'Card Layout', 'ds-toolkit' ),
						'default' => 'news_featured',
						'options' => DS_Post_Loop_Module::card_layouts(),
						'help'    => __( 'How each result is presented. The Query tab decides WHICH posts are pulled (Post Type + filters). Set Post Type to match the card (Staff card uses the Staff type, etc.).', 'ds-toolkit' ),
						'toggle'  => array(
							'news_featured'  => array( 'sections' => array( 'header', 'query', 'query_filter', 'layout', 'featured', 'cards', 'header_style', 'typography', 'spacing', 'hover', 'card_border', 'ds_borders' ), 'tabs' => array( 'query' ) ),
							'news_grid'      => array( 'sections' => array( 'header', 'query', 'query_filter', 'cards2', 'header_style', 'typography', 'spacing', 'hover', 'card_border', 'ds_borders', 'ds_display' ), 'tabs' => array( 'query' ) ),
							'staff_card'     => array( 'sections' => array( 'header', 'query', 'query_filter', 'staff_card', 'header_style', 'spacing', 'hover', 'card_border', 'ds_borders', 'ds_display' ), 'tabs' => array( 'query' ) ),
							'athlete_photo'  => array( 'sections' => array( 'header', 'query', 'query_filter', 'commit_card', 'commit_filter_opts', 'header_style', 'spacing', 'hover', 'card_border', 'ds_borders', 'ds_display' ), 'tabs' => array( 'query' ) ),
							'athlete_logo'   => array( 'sections' => array( 'header', 'query', 'query_filter', 'commit_card', 'commit_filter_opts', 'header_style', 'spacing', 'hover', 'card_border', 'ds_borders', 'ds_display' ), 'tabs' => array( 'query' ) ),
							'athlete_action' => array( 'sections' => array( 'header', 'query', 'query_filter', 'commit_card', 'commit_filter_opts', 'header_style', 'spacing', 'hover', 'card_border', 'ds_borders', 'ds_display' ), 'tabs' => array( 'query' ) ),
							'athlete_strip'  => array( 'sections' => array( 'header', 'query', 'query_filter', 'commit_strip_opts', 'commit_filter_opts', 'header_style', 'spacing', 'hover', 'ds_borders', 'ds_display' ), 'tabs' => array( 'query' ) ),
							'team_list'      => array( 'sections' => array( 'header', 'query', 'query_filter', 'team_list_opts', 'header_style', 'spacing', 'hover', 'card_border', 'ds_borders' ), 'tabs' => array( 'query' ) ),
							'team_card'      => array( 'sections' => array( 'header', 'query', 'query_filter', 'team_card_opts', 'header_style', 'spacing', 'hover', 'card_border', 'ds_borders', 'ds_display' ), 'tabs' => array( 'query' ) ),
							'custom'         => array( 'sections' => array( 'header', 'query', 'query_filter', 'loopcard', 'header_style', 'typography', 'spacing', 'hover', 'card_border', 'ds_borders', 'ds_display' ), 'tabs' => array( 'query' ) ),
							'sponsor'        => array( 'sections' => array( 'header', 'sponsors_sec', 'sponsor_opts', 'header_style', 'spacing', 'hover', 'card_border', 'ds_borders', 'ds_display' ) ),
							'program'        => array( 'sections' => array( 'header', 'programs_sec', 'program_opts', 'header_style', 'spacing', 'hover', 'card_border', 'ds_borders', 'ds_display' ) ),
							// No news 'typography' section here: its fields target .ds-news-card-* classes
							// that never render in tournament markup (GH: title/meta typography live in
							// the Tournament Cards section as tn_title_typo / tn_meta_typo).
							'tournament'     => array( 'sections' => array( 'header', 'query', 'query_filter', 'tn_filter_opts', 'tournament_opts', 'header_style', 'spacing', 'hover', 'card_border', 'ds_borders', 'ds_display' ), 'tabs' => array( 'query' ) ),
						),
					),
				),
			),
			// Sits directly below the Layout selector. Only revealed when the
			// "Custom Loop Layout" is selected (it IS content: your per-post markup).
			'loopcard' => array(
				'title'  => __( 'Custom Loop Layout', 'ds-toolkit' ),
				'fields' => array(
					'loop_custom' => array(
						'type'        => 'code',
						'editor'      => 'html',
						'mode'        => 'html',
						'label'       => __( 'Custom Item Markup', 'ds-toolkit' ),
						'rows'        => 14,
						'default'     => "<article class=\"my-card\">\n\t<a href=\"{permalink}\">\n\t\t<img src=\"{image}\" alt=\"{title}\" />\n\t\t<span class=\"cat\">{category}</span>\n\t\t<h3>{title}</h3>\n\t\t<time>{date}</time>\n\t</a>\n</article>",
						'connections' => array( 'string' ),
						'help'        => __( 'Rendered once per post. Use the connect (+) icon to insert a dynamic field, the {tokens} below, or any shortcode (e.g. a saved layout: [fl_builder_insert_layout id="123"]). Tokens: {title} {permalink} {date} {category} {excerpt} {image} {id}. Shortcodes + dynamic fields resolve against each post.', 'ds-toolkit' ),
						'preview'     => array( 'type' => 'none' ),
					),
					'loop_cols' => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '3', 'responsive' => true, 'slider' => array( 'min' => 1, 'max' => 6, 'step' => 1 ), 'help' => __( 'Grid columns wrapping your items. Defaults: 2 on tablet, 1 on mobile.', 'ds-toolkit' ) ),
					'loop_gap'  => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
				),
			),
			// Program Cards manual list: content (not a query), so it lives on the Content
			// tab below Layout. Revealed only when Card Layout = Program (card_layout toggle).
			'programs_sec' => array(
				'title'       => __( 'Program Cards (manual list)', 'ds-toolkit' ),
				'description' => __( 'Build each card by hand: date, sub-heading, title, description, a link/button, and an icon or image.', 'ds-toolkit' ),
				'fields' => array(
					'programs' => array(
						'type'         => 'form',
						'label'        => __( 'Program', 'ds-toolkit' ),
						'form'         => 'ds_program_form',
						'preview_text' => 'prog_title',
						'multiple'     => true,
					),
				),
			),
			// Sponsors manual list: content, not a query — Content tab, revealed only when
			// Card Layout = Sponsor (card_layout toggle).
			'sponsors_sec' => array(
				'title'  => __( 'Sponsors (manual list)', 'ds-toolkit' ),
				'fields' => array(
					'sponsors' => array(
						'type'         => 'form',
						'label'        => __( 'Sponsor', 'ds-toolkit' ),
						'form'         => 'ds_sponsor_form',
						'preview_text' => 'sponsor_caption',
						'multiple'     => true,
					),
				),
			),
			'header' => array(
				'title'  => __( 'Header', 'ds-toolkit' ),
				'fields' => array(
					'show_header'  => array(
						'type'    => 'select',
						'label'   => __( 'Show Header', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'heading', 'show_button', 'header_divider' ) ) ),
					),
					'heading'      => array(
						'type'    => 'textarea',
						'label'   => __( 'Heading', 'ds-toolkit' ),
						'rows'    => 2,
						'default' => '{a}Lorem{/a} Ipsum',
						'help'    => __( 'Wrap a word in {a}…{/a} to colour it with the header accent. {outline}…{/outline} renders outlined (transparent, stroked) text — default style in Theme Setting.', 'ds-toolkit' ),
					),
					'show_button'  => array(
						'type'    => 'select',
						'label'   => __( 'Show Button', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'button_text', 'button_link' ) ) ),
					),
					'button_text'  => array( 'type' => 'text', 'label' => __( 'Button Text', 'ds-toolkit' ), 'default' => 'Lorem Ipsum' ),
					'button_link'  => array( 'type' => 'link', 'label' => __( 'Button Link', 'ds-toolkit' ), 'show_target' => true, 'default' => '/news/', 'connections' => array( 'url' ) ),
					'header_divider'    => array(
						'type'    => 'select',
						'label'   => __( 'Header Divider', 'ds-toolkit' ),
						'default' => 'none',
						'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'solid' => __( 'Solid', 'ds-toolkit' ), 'dashed' => __( 'Dashed', 'ds-toolkit' ), 'dotted' => __( 'Dotted', 'ds-toolkit' ) ),
						'help'    => __( 'Draws a line between the header and the content below.', 'ds-toolkit' ),
						'toggle'  => array(
							'solid'  => array( 'fields' => array( 'header_divider_w', 'header_divider_color', 'header_divider_gap' ) ),
							'dashed' => array( 'fields' => array( 'header_divider_w', 'header_divider_color', 'header_divider_gap' ) ),
							'dotted' => array( 'fields' => array( 'header_divider_w', 'header_divider_color', 'header_divider_gap' ) ),
						),
					),
					'header_divider_w'     => array( 'type' => 'unit', 'label' => __( 'Divider Width', 'ds-toolkit' ), 'default' => '1', 'description' => 'px', 'slider' => array( 'min' => 1, 'max' => 10, 'step' => 1 ) ),
					'header_divider_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Divider Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'header_divider_gap'   => array( 'type' => 'unit', 'label' => __( 'Space Below Divider', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 80, 'step' => 1 ) ),
				),
			),
			'ds_display' => array(
				'title'  => __( 'Display', 'ds-toolkit' ),
				'fields' => array(
					'display' => array(
						'type'    => 'select',
						'label'   => __( 'Display As', 'ds-toolkit' ),
						'default' => 'grid',
						'options' => array( 'grid' => __( 'Grid', 'ds-toolkit' ), 'carousel' => __( 'Carousel', 'ds-toolkit' ), 'paginated' => __( 'Grid + Pagination', 'ds-toolkit' ) ),
						'toggle'  => array(
							'carousel'  => array( 'fields' => array( 'car_per_view', 'car_gap', 'car_autoplay', 'car_loop', 'car_pause_hover', 'car_drag', 'car_arrows', 'car_dots', 'car_speed', 'car_nav_color' ) ),
							'paginated' => array( 'fields' => array( 'pag_per_page', 'pag_type', 'pag_color', 'pag_text_color' ) ),
						),
					),
					'car_per_view'   => array( 'type' => 'unit', 'label' => __( 'Slides Per View', 'ds-toolkit' ), 'default' => '4', 'responsive' => true, 'slider' => array( 'min' => 1, 'max' => 8, 'step' => 1 ), 'help' => __( 'How many cards show at once. Defaults: tablet 2, mobile 1.', 'ds-toolkit' ) ),
					'car_gap'        => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'car_autoplay'   => array( 'type' => 'select', 'label' => __( 'Autoplay', 'ds-toolkit' ), 'default' => 'no', 'options' => array( 'no' => __( 'No', 'ds-toolkit' ), 'yes' => __( 'Yes', 'ds-toolkit' ) ), 'toggle' => array( 'yes' => array( 'fields' => array( 'car_interval' ) ) ) ),
					'car_interval'   => array( 'type' => 'unit', 'label' => __( 'Interval', 'ds-toolkit' ), 'default' => '5', 'description' => 's', 'slider' => array( 'min' => 2, 'max' => 15, 'step' => 1 ) ),
					'car_loop'       => array( 'type' => 'select', 'label' => __( 'Loop', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'car_pause_hover'=> array( 'type' => 'select', 'label' => __( 'Pause on Hover', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'car_drag'       => array( 'type' => 'select', 'label' => __( 'Drag / Swipe', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'car_arrows'     => array( 'type' => 'select', 'label' => __( 'Arrows', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'car_dots'       => array( 'type' => 'select', 'label' => __( 'Dots', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'car_speed'      => array( 'type' => 'unit', 'label' => __( 'Transition Speed', 'ds-toolkit' ), 'default' => '500', 'description' => 'ms', 'slider' => array( 'min' => 150, 'max' => 1200, 'step' => 50 ) ),
					'car_nav_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Arrows / Dots Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pag_per_page'   => array( 'type' => 'unit', 'label' => __( 'Items Per Page', 'ds-toolkit' ), 'default' => '6', 'slider' => array( 'min' => 1, 'max' => 24, 'step' => 1 ) ),
					'pag_type'       => array( 'type' => 'select', 'label' => __( 'Pagination Style', 'ds-toolkit' ), 'default' => 'numbers', 'options' => array( 'numbers' => __( 'Page Numbers', 'ds-toolkit' ), 'loadmore' => __( 'Load More button', 'ds-toolkit' ) ) ),
					'pag_color'      => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Active / Button Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pag_text_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Text Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
				),
			),
		),
	),
	'query'   => array(
		'title'    => __( 'Query', 'ds-toolkit' ),
		'sections' => array(
			'query' => array(
				'title'  => __( 'Posts', 'ds-toolkit' ),
				'fields' => array(
					'source'         => array( 'type' => 'select', 'label' => __( 'Source', 'ds-toolkit' ), 'default' => 'custom', 'options' => array( 'custom' => __( 'This query (below)', 'ds-toolkit' ), 'archive' => __( 'Current archive (main query)', 'ds-toolkit' ) ), 'help' => __( 'On an archive template choose “Current archive” to loop whatever the archive shows (team-category term, category, tag, CPT archive). Otherwise build a custom query below.', 'ds-toolkit' ), 'toggle' => array( 'custom' => array( 'fields' => array( 'post_type', 'posts_per_page', 'order_by', 'order', 'offset', 'exclude_current', 'date_after', 'date_before', 'keyword' ) ) ) ),
					'post_type'      => array( 'type' => 'select', 'label' => __( 'Post Type', 'ds-toolkit' ), 'default' => 'post', 'options' => DS_Post_Loop_Module::post_type_options() ),
					'posts_per_page' => array( 'type' => 'unit', 'label' => __( 'Number of Posts', 'ds-toolkit' ), 'default' => '5', 'slider' => array( 'min' => 1, 'max' => 12, 'step' => 1 ), 'help' => __( 'Total posts pulled. The first one becomes the large featured card; the rest fill the loop.', 'ds-toolkit' ) ),
					'order_by'       => array(
						'type'    => 'select',
						'label'   => __( 'Order By', 'ds-toolkit' ),
						'default' => 'date',
						'options' => array(
							'date'           => __( 'Date', 'ds-toolkit' ),
							'modified'       => __( 'Last Modified', 'ds-toolkit' ),
							'title'          => __( 'Title', 'ds-toolkit' ),
							'menu_order'     => __( 'Menu Order / Nested Pages position', 'ds-toolkit' ),
							'meta_value'     => __( 'Custom Field (text)', 'ds-toolkit' ),
							'meta_value_num' => __( 'Custom Field (number)', 'ds-toolkit' ),
							'rand'           => __( 'Random', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'meta_value'     => array( 'fields' => array( 'order_meta_key' ) ),
							'meta_value_num' => array( 'fields' => array( 'order_meta_key' ) ),
						),
						'help'    => __( 'Menu Order follows the drag order set by the Nested Pages plugin (and any post type’s Page Attributes order).', 'ds-toolkit' ),
					),
					'order_meta_key' => array( 'type' => 'text', 'label' => __( 'Custom Field Key', 'ds-toolkit' ), 'default' => '', 'help' => __( 'The meta_key to order by, e.g. an ACF field name. Pick the (number) option above for numeric fields.', 'ds-toolkit' ) ),
					'order'          => array(
						'type'    => 'select',
						'label'   => __( 'Order', 'ds-toolkit' ),
						'default' => 'DESC',
						'options' => array( 'DESC' => __( 'Descending (newest first)', 'ds-toolkit' ), 'ASC' => __( 'Ascending (oldest first)', 'ds-toolkit' ) ),
					),
					'offset'         => array( 'type' => 'unit', 'label' => __( 'Offset', 'ds-toolkit' ), 'default' => '0', 'slider' => array( 'min' => 0, 'max' => 20, 'step' => 1 ), 'help' => __( 'Skip this many posts from the start of the result set.', 'ds-toolkit' ) ),
					'date_after'     => array( 'type' => 'text', 'label' => __( 'From Date', 'ds-toolkit' ), 'default' => '', 'help' => __( 'Only posts published on/after this date. YYYY-MM-DD, or a relative window like "-30 days". Blank = no lower bound.', 'ds-toolkit' ) ),
					'date_before'    => array( 'type' => 'text', 'label' => __( 'To Date', 'ds-toolkit' ), 'default' => '', 'help' => __( 'Only posts published on/before this date. Same formats as From Date. Blank = no upper bound.', 'ds-toolkit' ) ),
					'keyword'        => array( 'type' => 'text', 'label' => __( 'Keyword Search', 'ds-toolkit' ), 'default' => '', 'help' => __( 'Only posts matching this keyword (searches title and content).', 'ds-toolkit' ) ),
					'exclude_current' => array( 'type' => 'select', 'label' => __( 'Exclude Current Post', 'ds-toolkit' ), 'default' => 'no', 'options' => array( 'no' => __( 'No', 'ds-toolkit' ), 'yes' => __( 'Yes', 'ds-toolkit' ) ), 'help' => __( 'On a single post / CPT view, leave out the post being viewed — ideal for a “More News / Related” strip.', 'ds-toolkit' ) ),
					'date_format'    => array( 'type' => 'text', 'label' => __( 'Date Format', 'ds-toolkit' ), 'default' => 'M Y', 'help' => __( 'PHP date format for the card date (e.g. M Y → Jun 2026).', 'ds-toolkit' ) ),
				),
			),
			'empty_cfg' => array(
				'title'  => __( 'When There Are No Results', 'ds-toolkit' ),
				'fields' => array(
					'empty_show' => array(
						'type'    => 'select',
						'label'   => __( 'Show a Notice', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No (render nothing)', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'empty_text', 'empty_heading', 'empty_desc', 'empty_align' ) ) ),
						'help'    => __( 'What visitors see when the query returns nothing. Without it the section renders as a heading over blank space. Choose No only where an empty loop should collapse silently.', 'ds-toolkit' ),
					),
					'empty_text' => array(
						'type'        => 'text',
						'label'       => __( 'Badge', 'ds-toolkit' ),
						'default'     => '',
						'connections' => array( 'string' ),
						'help'        => __( 'Optional small pill above the heading, e.g. COMING SOON.', 'ds-toolkit' ),
					),
					'empty_heading' => array(
						'type'        => 'text',
						'label'       => __( 'Heading', 'ds-toolkit' ),
						'default'     => '',
						'connections' => array( 'string' ),
						'help'        => __( 'Blank shows "Coming Soon", unless a Badge or Description is set.', 'ds-toolkit' ),
					),
					'empty_desc' => array(
						'type'          => 'editor',
						'media_buttons' => false,
						'rows'          => 3,
						'wpautop'       => false,
						'label'         => __( 'Description', 'ds-toolkit' ),
						'help'          => __( 'Optional line under the heading, e.g. "Our next tournament dates are being finalised."', 'ds-toolkit' ),
					),
					'empty_align' => array(
						'type'    => 'select',
						'label'   => __( 'Alignment', 'ds-toolkit' ),
						'default' => 'center',
						'options' => array( 'center' => __( 'Center', 'ds-toolkit' ), 'left' => __( 'Left', 'ds-toolkit' ) ),
					),
				),
			),
			'query_filter' => array(
				'title'  => __( 'Taxonomy Filter', 'ds-toolkit' ),
				// Fields injected after the form is defined (one term-suggest field per
				// public taxonomy, revealed by the Taxonomy selector's toggle).
				'fields' => array(),
			),
			'tn_filter_opts' => array(
				'title'       => __( 'Filter Bar (Event Card)', 'ds-toolkit' ),
				'description' => __( 'The front-end filter bar above the Tournament "Event Card" grid/list. Every element is optional and developer-configured here — nothing is hard-coded.', 'ds-toolkit' ),
				'fields'      => array(
					'tn_filter'        => array(
						'type'    => 'select',
						'label'   => __( 'Filter Bar', 'ds-toolkit' ),
						'default' => 'disable',
						'options' => array( 'disable' => __( 'Disable', 'ds-toolkit' ), 'enable' => __( 'Enable', 'ds-toolkit' ) ),
						'toggle'  => array( 'enable' => array( 'fields' => array( 'tn_filter_tabs', 'tn_filter_state', 'tn_filter_search', 'tn_filter_count' ) ) ),
					),
					'tn_filter_tabs'   => array(
						'type'    => 'select',
						'label'   => __( 'Tabs Source', 'ds-toolkit' ),
						'default' => '',
						'options' => DS_Post_Loop_Module::tourn_tab_options(),
						'help'    => __( 'What drives the tab pills (All / …): pick any taxonomy or ACF choice field that exists on THIS site for the queried post type. The plugin registers nothing itself — if the site has no suitable taxonomy, create one (e.g. via ACF → Taxonomies) or leave tabs off. The chosen values also feed the row eyebrow and the card chips.', 'ds-toolkit' ),
					),
					'tn_filter_state'  => array(
						'type'    => 'select',
						'label'   => __( 'State Dropdown', 'ds-toolkit' ),
						'default' => 'show',
						'options' => array( 'show' => __( 'Show', 'ds-toolkit' ), 'hide' => __( 'Hide', 'ds-toolkit' ) ),
						'help'    => __( 'Built from each event\'s State field (event_state), falling back to the ", XX" tail of the Location address. Hidden automatically when no event has a state.', 'ds-toolkit' ),
					),
					'tn_filter_search' => array(
						'type'    => 'select',
						'label'   => __( 'Search Box', 'ds-toolkit' ),
						'default' => 'show',
						'options' => array( 'show' => __( 'Show', 'ds-toolkit' ), 'hide' => __( 'Hide', 'ds-toolkit' ) ),
					),
					'tn_filter_count'  => array(
						'type'    => 'select',
						'label'   => __( 'Event Count', 'ds-toolkit' ),
						'default' => 'show',
						'options' => array( 'show' => __( 'Show', 'ds-toolkit' ), 'hide' => __( 'Hide', 'ds-toolkit' ) ),
					),
				),
			),
		),
	),
	'style'   => array(
		'title'    => __( 'Style', 'ds-toolkit' ),
		'sections' => array(
			'layout' => array(
				'title'  => __( 'Layout', 'ds-toolkit' ),
				'fields' => array(
					'gap'             => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '20', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'feature_width'   => array( 'type' => 'text', 'label' => __( 'Featured Column Width', 'ds-toolkit' ), 'default' => '1.4fr', 'help' => __( 'CSS track size for the featured column (e.g. 1.4fr, 1.6fr, 480px).', 'ds-toolkit' ) ),
					'feature_min_h'   => array( 'type' => 'unit', 'label' => __( 'Featured Min Height', 'ds-toolkit' ), 'default' => '420', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 240, 'max' => 700, 'step' => 10 ) ),
					'card_min_h'      => array( 'type' => 'unit', 'label' => __( 'Card Min Height', 'ds-toolkit' ), 'default' => '195', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 120, 'max' => 400, 'step' => 5 ) ),
				),
			),
			'featured' => array(
				'title'  => __( 'Featured Card', 'ds-toolkit' ),
				'fields' => array(
					'featured_badge'  => array( 'type' => 'text', 'label' => __( 'Badge Override', 'ds-toolkit' ), 'default' => '', 'help' => __( 'Optional. Overrides the featured badge text (defaults to the post’s category).', 'ds-toolkit' ) ),
					'feature_bg'      => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Fallback Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Shown behind / when a post has no featured image.', 'ds-toolkit' ) ),
					'overlay_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Overlay Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'overlay_top'     => array( 'type' => 'unit', 'label' => __( 'Overlay Top Opacity', 'ds-toolkit' ), 'default' => '15', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'overlay_bottom'  => array( 'type' => 'unit', 'label' => __( 'Overlay Bottom Opacity', 'ds-toolkit' ), 'default' => '92', 'description' => '%', 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 1 ) ),
					'badge_bg'        => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Badge Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'badge_color'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Badge Text', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'feature_title_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Featured Title', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'excerpt_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Excerpt Text', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'readmore_text'   => array( 'type' => 'text', 'label' => __( 'Read More Text', 'ds-toolkit' ), 'default' => 'Lorem Ipsum' ),
					'readmore_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Read More Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
				),
			),
			'cards' => array(
				'title'  => __( 'Loop Cards', 'ds-toolkit' ),
				'fields' => array(
					'card_bg'            => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'card_border_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Border', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'card_hover_border'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Hover Border', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'card_border_width'  => array( 'type' => 'unit', 'label' => __( 'Card Border Width', 'ds-toolkit' ), 'default' => '1', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 6, 'step' => 1 ) ),
					'card_radius'        => array( 'type' => 'unit', 'label' => __( 'Card Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 30, 'step' => 1 ) ),
					'category_color'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Category Eyebrow', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'card_title_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Title', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'date_color'         => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Date', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'chevron_color'      => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Chevron', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
				),
			),
			// --- Style 2 only: card grid ---
			'cards2' => array(
				'title'  => __( 'Cards', 'ds-toolkit' ),
				'fields' => array(
					'cols2'           => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '4', 'responsive' => true, 'slider' => array( 'min' => 1, 'max' => 6, 'step' => 1 ) ),
					'gap2'            => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '20', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'card2_min'       => array( 'type' => 'unit', 'label' => __( 'Image Height', 'ds-toolkit' ), 'default' => '210', 'description' => 'px', 'responsive' => true, 'slider' => array( 'min' => 120, 'max' => 400, 'step' => 5 ) ),
					'card2_radius'    => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 30, 'step' => 1 ) ),
					'card2_bg'        => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'card2_border'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Border', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'card2_border_w'  => array( 'type' => 'unit', 'label' => __( 'Border Width', 'ds-toolkit' ), 'default' => '1', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 6, 'step' => 1 ) ),
					'pill2_bg'        => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Pill Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pill2_color'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Pill Text', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'title2_color'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Title', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'date2_color'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Date', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'readmore2_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Read More', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'readmore2_text'  => array( 'type' => 'text', 'label' => __( 'Read More Text', 'ds-toolkit' ), 'default' => 'Lorem Ipsum' ),
				),
			),
			// --- Staff card layout options ---
			'staff_card' => array(
				'title'  => __( 'Staff Cards', 'ds-toolkit' ),
				'fields' => array(
					'staff_cols'        => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '4', 'responsive' => true, 'slider' => array( 'min' => 1, 'max' => 6, 'step' => 1 ), 'help' => __( 'Defaults: 2 on tablet, 1 on mobile.', 'ds-toolkit' ) ),
					'staff_gap'         => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'card_link'         => array( 'type' => 'select', 'label' => __( 'Link Card to Post', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'help' => __( 'Makes the whole card a clickable link to the post. Turn off to remove the card link (contact / social icons stay clickable).', 'ds-toolkit' ) ),
					'staff_photo_ratio' => array(
						'type'    => 'select',
						'label'   => __( 'Photo Ratio', 'ds-toolkit' ),
						'default' => '3 / 4',
						'options' => array( '3 / 4' => __( 'Portrait 3:4', 'ds-toolkit' ), '4 / 5' => __( 'Portrait 4:5', 'ds-toolkit' ), '1 / 1' => __( 'Square 1:1', 'ds-toolkit' ), '4 / 3' => __( 'Landscape 4:3', 'ds-toolkit' ) ),
					),
					'staff_card_bg'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'staff_card_radius' => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 30, 'step' => 1 ) ),
					'staff_name_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Name', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'staff_role_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Title', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'staff_ico_size'    => array( 'type' => 'unit', 'label' => __( 'Icon Size', 'ds-toolkit' ), 'default' => '38', 'description' => 'px', 'slider' => array( 'min' => 24, 'max' => 72, 'step' => 1 ), 'help' => __( 'Diameter of the round contact / social icons; the glyph scales with it.', 'ds-toolkit' ) ),
					'staff_ico_bg'      => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Icon Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'staff_ico_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Icon Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'staff_ico_hover'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Icon Hover Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'staff_show_email'  => array( 'type' => 'select', 'label' => __( 'Show Email', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'staff_show_phone'  => array( 'type' => 'select', 'label' => __( 'Show Phone', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'staff_show_social' => array( 'type' => 'select', 'label' => __( 'Show Social', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'staff_name_typo'   => array( 'type' => 'typography', 'label' => __( 'Name Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-people-name' ) ),
					'staff_role_typo'   => array( 'type' => 'typography', 'label' => __( 'Title Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-people-role' ) ),
				),
			),
			// --- Commitments / Athletes card options (shared by Photo / Logo / Action) ---
			'commit_card' => array(
				'title'  => __( 'Commitment Cards', 'ds-toolkit' ),
				'fields' => array(
					'commit_cols'        => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '4', 'responsive' => true, 'slider' => array( 'min' => 1, 'max' => 6, 'step' => 1 ), 'help' => __( 'Logo Row reads better at 1-2 columns. Defaults: 2 tablet, 1 mobile.', 'ds-toolkit' ) ),
					'commit_gap'         => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'card_link'          => array( 'type' => 'select', 'label' => __( 'Link Card to Post', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'help' => __( 'Makes the whole card a clickable link to the post. Turn off to remove the card link.', 'ds-toolkit' ) ),
					'commit_photo_ratio' => array( 'type' => 'select', 'label' => __( 'Photo Ratio (Photo / Action)', 'ds-toolkit' ), 'default' => '3 / 4', 'options' => array( '3 / 4' => __( 'Portrait 3:4', 'ds-toolkit' ), '4 / 5' => __( 'Portrait 4:5', 'ds-toolkit' ), '1 / 1' => __( 'Square 1:1', 'ds-toolkit' ), '4 / 3' => __( 'Landscape 4:3', 'ds-toolkit' ), '16 / 10' => __( 'Landscape 16:10', 'ds-toolkit' ) ) ),
					'commit_photo_fit'   => array( 'type' => 'select', 'label' => __( 'Photo Fit', 'ds-toolkit' ), 'default' => 'cover', 'options' => array( 'cover' => __( 'Fill (crop to the ratio)', 'ds-toolkit' ), 'contain' => __( 'Fit (whole photo, no crop)', 'ds-toolkit' ) ), 'help' => __( 'Fill crops the photo to the card ratio; use Photo Focus to keep the subject in frame. Fit always shows the whole photo, letterboxed on the Photo Backdrop colour.', 'ds-toolkit' ) ),
					'commit_photo_focus' => array( 'type' => 'select', 'label' => __( 'Photo Focus', 'ds-toolkit' ), 'default' => 'center', 'options' => array( 'center' => __( 'Center', 'ds-toolkit' ), 'top' => __( 'Top (faces)', 'ds-toolkit' ), 'bottom' => __( 'Bottom', 'ds-toolkit' ), 'left' => __( 'Left', 'ds-toolkit' ), 'right' => __( 'Right', 'ds-toolkit' ) ), 'help' => __( 'Which part of the photo stays visible when Fill crops it — Top keeps heads in frame on portrait ratios.', 'ds-toolkit' ) ),
					'commit_photo_bg'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Photo Backdrop', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Behind the photo (visible with Fit, or while loading). Blank = the global Headings colour.', 'ds-toolkit' ) ),
					'commit_card_bg'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'commit_dark_bg'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Logo Row Background (dark)', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'commit_radius'      => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 30, 'step' => 1 ) ),
					'commit_border_color'=> array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Border Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'commit_border_width'=> array( 'type' => 'unit', 'label' => __( 'Border Width', 'ds-toolkit' ), 'default' => '2', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 8, 'step' => 1 ) ),
					'commit_name_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Name', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'commit_school_color'=> array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'School / Meta', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'commit_name_typo'   => array( 'type' => 'typography', 'label' => __( 'Name Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-people-name' ) ),
					'commit_meta_typo'   => array( 'type' => 'typography', 'label' => __( 'School / Meta Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-people-role' ) ),
					'commit_show_year'   => array( 'type' => 'select', 'label' => __( 'Show Class Year (Action Card)', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'help' => __( 'The Action Card meta line reads "year | college". Set to No for a college-only line.', 'ds-toolkit' ) ),
					'commit_school_key'  => array( 'type' => 'text', 'label' => __( 'College Name Field', 'ds-toolkit' ), 'default' => '', 'help' => __( 'Meta/ACF key holding the college name. Blank auto-detects school_name, university, college, college_name, school.', 'ds-toolkit' ) ),
					'commit_logo_key'    => array( 'type' => 'text', 'label' => __( 'College Logo Field', 'ds-toolkit' ), 'default' => '', 'help' => __( 'Meta/ACF key holding the college logo image. Blank auto-detects school_logo, college_logo, logo.', 'ds-toolkit' ) ),
				),
			),
			// --- Compact Strip (logo | name | club mark) ---
			'commit_strip_opts' => array(
				'title'       => __( 'Compact Strip', 'ds-toolkit' ),
				'description' => __( 'A short dense row per athlete: college logo on the left, name and college in the middle, the club mark on the right. No athlete photo — ideal when athletes share a placeholder image.', 'ds-toolkit' ),
				'fields'      => array(
					'cr_cols'         => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '3', 'responsive' => true, 'slider' => array( 'min' => 1, 'max' => 5, 'step' => 1 ), 'help' => __( 'Defaults: 2 tablet, 1 mobile.', 'ds-toolkit' ) ),
					'cr_gap'          => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '16', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
					'card_link'       => array( 'type' => 'select', 'label' => __( 'Link Card to Post', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ) ),
					'cr_show_college' => array( 'type' => 'select', 'label' => __( 'Show College Name', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'help' => __( 'Off leaves just the athlete name beside the logo.', 'ds-toolkit' ) ),
					'cr_bg'           => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Strip Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank = the global Accent colour.', 'ds-toolkit' ) ),
					'cr_radius'       => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '4', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 24, 'step' => 1 ) ),
					'cr_pad'          => array( 'type' => 'unit', 'label' => __( 'Padding', 'ds-toolkit' ), 'default' => '12', 'description' => 'px', 'slider' => array( 'min' => 4, 'max' => 32, 'step' => 1 ) ),
					'cr_name_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Name Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank = white.', 'ds-toolkit' ) ),
					'cr_meta_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'College Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true ),
					'cr_logo_size'    => array( 'type' => 'unit', 'label' => __( 'College Logo Size', 'ds-toolkit' ), 'default' => '44', 'description' => 'px', 'slider' => array( 'min' => 24, 'max' => 90, 'step' => 1 ) ),
					'cr_logo_bg'      => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'College Logo Backdrop', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'A light chip behind the logo keeps dark college marks legible on a strong background. Blank = white.', 'ds-toolkit' ) ),
					'cr_brand_show'   => array( 'type' => 'select', 'label' => __( 'Show Club Logo', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'toggle' => array( 'yes' => array( 'fields' => array( 'cr_brand', 'cr_brand_size' ) ) ) ),
					'cr_brand'        => array( 'type' => 'photo', 'label' => __( 'Club Logo', 'ds-toolkit' ), 'show_remove' => true, 'connections' => array( 'photo' ), 'help' => __( 'Blank = the site\'s Partner Logo setting. Use a version that reads on the strip background (a white mark on a strong colour).', 'ds-toolkit' ) ),
					'cr_brand_size'   => array( 'type' => 'unit', 'label' => __( 'Club Logo Size', 'ds-toolkit' ), 'default' => '38', 'description' => 'px', 'slider' => array( 'min' => 20, 'max' => 80, 'step' => 1 ) ),
					'cr_name_typo'    => array( 'type' => 'typography', 'label' => __( 'Name Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-commit-strip .ds-people-name' ) ),
					'cr_meta_typo'    => array( 'type' => 'typography', 'label' => __( 'College Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-commit-strip .ds-people-role' ) ),
				),
			),
			// --- Front-end filter bar for the Commitment / Athlete card grids ---
			'commit_filter_opts' => array(
				'title'       => __( 'Filter Bar (Commitments)', 'ds-toolkit' ),
				'description' => __( 'A pill bar above the card grid that filters the flat list in place — no page reload, no grouping. The facet is developer-chosen (a meta field like Division, or a taxonomy); every value found in the results becomes a pill, and "All" shows everything.', 'ds-toolkit' ),
				'fields'      => array(
					'cf_filter'     => array( 'type' => 'select', 'label' => __( 'Filter Bar', 'ds-toolkit' ), 'default' => 'disable', 'options' => array( 'disable' => __( 'Disable', 'ds-toolkit' ), 'enable' => __( 'Enable', 'ds-toolkit' ) ), 'toggle' => array( 'enable' => array( 'fields' => array( 'cf_source', 'cf_order', 'cf_all_label', 'cf_count', 'cf_count_noun', 'cf_align', 'cf_bar_bg', 'cf_tab_color', 'cf_tab_active_bg', 'cf_tab_active_color', 'cf_tab_typo' ) ) ) ),
					'cf_source'     => array( 'type' => 'text', 'label' => __( 'Filter By', 'ds-toolkit' ), 'default' => 'meta:division', 'help' => __( 'meta:&lt;field_key&gt; for an ACF/meta field (default meta:division), or tax:&lt;taxonomy_slug&gt; for a taxonomy. A comma or pipe separated meta value tags the card with several values.', 'ds-toolkit' ) ),
					'cf_order'      => array( 'type' => 'text', 'label' => __( 'Pill Order', 'ds-toolkit' ), 'default' => '', 'help' => __( 'Optional comma-separated order for the pills, e.g. "D1, D2, D3, MCLA". Values not listed keep their natural order at the end.', 'ds-toolkit' ) ),
					'cf_all_label'  => array( 'type' => 'text', 'label' => __( '"All" Pill Label', 'ds-toolkit' ), 'default' => 'All' ),
					'cf_count'      => array( 'type' => 'select', 'label' => __( 'Result Count', 'ds-toolkit' ), 'default' => 'show', 'options' => array( 'show' => __( 'Show', 'ds-toolkit' ), 'hide' => __( 'Hide', 'ds-toolkit' ) ) ),
					'cf_count_noun' => array( 'type' => 'text', 'label' => __( 'Count Noun', 'ds-toolkit' ), 'default' => 'commitments', 'help' => __( 'Word after the number, e.g. "20 commitments".', 'ds-toolkit' ) ),
					'cf_align'      => array( 'type' => 'select', 'label' => __( 'Alignment', 'ds-toolkit' ), 'default' => 'left', 'options' => array( 'left' => __( 'Left', 'ds-toolkit' ), 'center' => __( 'Center', 'ds-toolkit' ), 'right' => __( 'Right', 'ds-toolkit' ) ) ),
					'cf_bar_bg'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Bar Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Blank = transparent (pills sit straight on the page).', 'ds-toolkit' ) ),
					'cf_tab_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Pill Text', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'cf_tab_active_bg'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Active Pill Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank = the global Accent colour.', 'ds-toolkit' ) ),
					'cf_tab_active_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Active Pill Text', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'cf_tab_typo'   => array( 'type' => 'typography', 'label' => __( 'Pill Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-commit-tab' ) ),
				),
			),
			// --- Team list options ---
			'team_card_opts' => array(
				'title'       => __( 'Team Cards', 'ds-toolkit' ),
				'description' => __( 'Photo grid of teams. Cards link to the team\'s External Link (new tab) when set, else the team page. Teams without a featured image use the Social Card (Theme Setting on Launchpad 6; the stock LA social card on Launchpad 5).', 'ds-toolkit' ),
				'fields'      => array(
					'tc_cols'       => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '3', 'slider' => array( 'min' => 1, 'max' => 5, 'step' => 1 ) ),
					'tc_gap'        => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'tc_ratio'      => array( 'type' => 'select', 'label' => __( 'Photo Ratio', 'ds-toolkit' ), 'default' => '16 / 10', 'options' => array( '16 / 9' => '16:9', '16 / 10' => '16:10', '4 / 3' => '4:3', '3 / 2' => '3:2', '1 / 1' => '1:1' ) ),
					'tc_card_bg'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'tc_name_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Team Name Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'tc_ico_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Arrow Icon Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank = the global Accent colour.', 'ds-toolkit' ) ),
					'tc_name_typo'  => array( 'type' => 'typography', 'label' => __( 'Team Name Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-teamcard-name' ) ),
				),
			),
			'team_list_opts' => array(
				'title'  => __( 'Team List', 'ds-toolkit' ),
				'fields' => array(
					'team_gap'          => array( 'type' => 'unit', 'label' => __( 'Row Gap', 'ds-toolkit' ), 'default' => '14', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ) ),
					'team_row_bg'       => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Row Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'team_row_border'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Row Border', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'team_row_border_w' => array( 'type' => 'unit', 'label' => __( 'Row Border Width', 'ds-toolkit' ), 'default' => '2', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 6, 'step' => 1 ) ),
					'team_row_radius'   => array( 'type' => 'unit', 'label' => __( 'Row Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 24, 'step' => 1 ) ),
					'team_name_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Team Name', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'team_btn_bg'       => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'team_btn_color'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Icon', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'team_name_typo'    => array( 'type' => 'typography', 'label' => __( 'Team Name Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-team-name' ) ),
				),
			),
			// --- Display: Grid / Carousel / Paginated (card-grid layouts) ---
			'hover' => array(
				'title'  => __( 'Hover & Animation', 'ds-toolkit' ),
				'fields' => array(
					'hover_effect' => array(
						'type'    => 'select',
						'label'   => __( 'Hover Effect', 'ds-toolkit' ),
						'default' => 'lift',
						'options' => array(
							'none'   => __( 'None', 'ds-toolkit' ),
							'lift'   => __( 'Lift', 'ds-toolkit' ),
							'grow'   => __( 'Grow', 'ds-toolkit' ),
							'zoom'   => __( 'Zoom Image', 'ds-toolkit' ),
							'shadow' => __( 'Shadow', 'ds-toolkit' ),
							'border' => __( 'Border Highlight', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'lift'   => array( 'fields' => array( 'hover_distance', 'hover_speed', 'hover_shadow_color' ) ),
							'grow'   => array( 'fields' => array( 'hover_scale', 'hover_speed', 'hover_shadow_color' ) ),
							'zoom'   => array( 'fields' => array( 'hover_scale', 'hover_speed' ) ),
							'shadow' => array( 'fields' => array( 'hover_speed', 'hover_shadow_color' ) ),
							'border' => array( 'fields' => array( 'hover_speed', 'hover_border_color' ) ),
						),
						'help'    => __( 'Animation when a card is hovered. Applies to every card layout.', 'ds-toolkit' ),
					),
					'hover_distance'     => array( 'type' => 'unit', 'label' => __( 'Lift Distance', 'ds-toolkit' ), 'default' => '6', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 30, 'step' => 1 ) ),
					'hover_scale'        => array( 'type' => 'unit', 'label' => __( 'Scale', 'ds-toolkit' ), 'default' => '105', 'description' => '%', 'slider' => array( 'min' => 100, 'max' => 120, 'step' => 1 ) ),
					'hover_speed'        => array( 'type' => 'unit', 'label' => __( 'Transition Speed', 'ds-toolkit' ), 'default' => '300', 'description' => 'ms', 'slider' => array( 'min' => 100, 'max' => 800, 'step' => 25 ) ),
					'hover_shadow_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Shadow Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true ),
					'hover_border_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Hover Border Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'hover_bg'           => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Hover Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true, 'help' => __( 'Optional. Colour the card fades to on hover (any layout).', 'ds-toolkit' ) ),
				),
			),
			'card_border' => array(
				'title'  => __( 'Card Border', 'ds-toolkit' ),
				'fields' => array(
					'card_bd_style' => array(
						'type'    => 'select',
						'label'   => __( 'Border Style', 'ds-toolkit' ),
						'default' => 'default',
						'options' => array(
							'default' => __( 'Theme Default', 'ds-toolkit' ),
							'none'    => __( 'None', 'ds-toolkit' ),
							'solid'   => __( 'Solid', 'ds-toolkit' ),
							'dashed'  => __( 'Dashed', 'ds-toolkit' ),
							'dotted'  => __( 'Dotted', 'ds-toolkit' ),
							'double'  => __( 'Double', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'solid'  => array( 'fields' => array( 'card_bd_width', 'card_bd_color' ) ),
							'dashed' => array( 'fields' => array( 'card_bd_width', 'card_bd_color' ) ),
							'dotted' => array( 'fields' => array( 'card_bd_width', 'card_bd_color' ) ),
							'double' => array( 'fields' => array( 'card_bd_width', 'card_bd_color' ) ),
						),
						'help'    => __( 'Border around each card. “Theme Default” keeps the layout’s built-in border.', 'ds-toolkit' ),
					),
					'card_bd_width'  => array( 'type' => 'unit', 'label' => __( 'Border Width', 'ds-toolkit' ), 'default' => '1', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 10, 'step' => 1 ) ),
					'card_bd_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Border Color', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'card_bd_radius' => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ), 'help' => __( 'Blank keeps the layout default.', 'ds-toolkit' ) ),
				),
			),
			'tournament_opts' => array(
				'title'       => __( 'Tournament Cards', 'ds-toolkit' ),
				'description' => __( 'Ordering is automatic: upcoming events first, sorted by the Event Date field, with past events hidden. (The Query tab\'s Order By / Order do not apply to this layout.)', 'ds-toolkit' ),
				'fields' => array(
					'tn_style'       => array(
						'type'    => 'select',
						'label'   => __( 'Card Style', 'ds-toolkit' ),
						'default' => 'overlap',
						'options' => array(
							'overlap' => __( 'Classic — overlapping details card', 'ds-toolkit' ),
							'event'   => __( 'Event Card — badge, details list, filter-ready', 'ds-toolkit' ),
						),
						'toggle'  => array(
							'overlap' => array( 'fields' => array( 'tn_overlap', 'tn_inset', 'tn_align', 'tn_logo_shape' ) ),
							'event'   => array( 'fields' => array( 'tn_display', 'tn_badge', 'tn_filter' ) ),
						),
						'help'    => __( 'Event Card: flat card with a badge pill on the image, left-aligned date/location plus gender & division chips, and a full-width button. Supports the filter bar.', 'ds-toolkit' ),
					),
					'tn_display'     => array(
						'type'    => 'select',
						'label'   => __( 'Display', 'ds-toolkit' ),
						'default' => 'grid',
						'options' => array( 'grid' => __( 'Grid (cards)', 'ds-toolkit' ), 'list' => __( 'List (rows)', 'ds-toolkit' ) ),
						'toggle'  => array( 'grid' => array( 'fields' => array( 'tn_badge', 'tn_cols', 'tn_img_ratio' ) ) ),
						'help'    => __( 'List renders each event as a full-width row: date tile, logo, gender + title + location, division chips, and a Register button.', 'ds-toolkit' ),
					),
					'tn_badge'       => array( 'type' => 'text', 'label' => __( 'Badge Text', 'ds-toolkit' ), 'default' => 'Tournament', 'help' => __( 'The pill shown at the top of the image. Blank = no badge.', 'ds-toolkit' ) ),
					'tn_cols'        => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '3', 'slider' => array( 'min' => 1, 'max' => 5, 'step' => 1 ) ),
					'tn_gap'         => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '28', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'tn_img_ratio'   => array( 'type' => 'select', 'label' => __( 'Image Ratio', 'ds-toolkit' ), 'default' => '16 / 10', 'options' => array( '16 / 9' => '16:9', '16 / 10' => '16:10', '4 / 3' => '4:3', '3 / 2' => '3:2', '1 / 1' => '1:1' ) ),
					'tn_logo_size'   => array( 'type' => 'unit', 'label' => __( 'Event Image Size', 'ds-toolkit' ), 'default' => '96', 'description' => 'px', 'slider' => array( 'min' => 48, 'max' => 200, 'step' => 1 ), 'help' => __( 'Size of the small Event Image centered over the featured image. Shown only when an event has an Event Image.', 'ds-toolkit' ) ),
					'tn_logo_shape'  => array( 'type' => 'select', 'label' => __( 'Event Image Shape', 'ds-toolkit' ), 'default' => 'circle', 'options' => array( 'circle' => __( 'Circle', 'ds-toolkit' ), 'square' => __( 'Rounded square', 'ds-toolkit' ) ) ),
					'tn_overlap'     => array( 'type' => 'unit', 'label' => __( 'Card Overlap', 'ds-toolkit' ), 'default' => '48', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 120, 'step' => 1 ), 'help' => __( 'How far the details card pulls up over the image.', 'ds-toolkit' ) ),
					'tn_inset'       => array( 'type' => 'unit', 'label' => __( 'Card Side Inset', 'ds-toolkit' ), 'default' => '18', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ), 'help' => __( 'Horizontal inset of the details card from the image edges.', 'ds-toolkit' ) ),
					'tn_align'       => array( 'type' => 'select', 'label' => __( 'Alignment', 'ds-toolkit' ), 'default' => 'center', 'options' => array( 'center' => __( 'Center', 'ds-toolkit' ), 'left' => __( 'Left', 'ds-toolkit' ) ) ),
					'tn_surface_bg'  => array(
						'type'        => 'color',
						'connections' => array( 'color' ),
						'label'       => __( 'Card Background', 'ds-toolkit' ),
						'default'     => '',
						'show_reset'  => true,
						'show_alpha'  => true,
						'help'        => __( 'The card itself. Blank keeps the built-in white on Event cards, and keeps the card transparent on Overlap cards (where the Details panel below is the visible surface).', 'ds-toolkit' ),
						'preview'     => array( 'type' => 'css', 'selector' => '.ds-tourn-card', 'property' => 'background-color' ),
					),
					'tn_card_bg'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Details Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'The inner details panel that overlaps the image on the Overlap layout — not the card. For the card surface use Card Background above.', 'ds-toolkit' ), 'preview' => array( 'type' => 'css', 'selector' => '.ds-tourn-body', 'property' => 'background-color' ) ),
					'tn_pad'         => array( 'type' => 'unit', 'label' => __( 'Details Padding', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'tn_radius'      => array( 'type' => 'unit', 'label' => __( 'Corner Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ), 'help' => __( 'Blank = global Theme Setting corner radius.', 'ds-toolkit' ) ),
					'tn_title_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Title Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'tn_meta_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Date / Location Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'tn_icon_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Meta Icon Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
					'tourn_btn'      => array( 'type' => 'text', 'label' => __( 'Button Text', 'ds-toolkit' ), 'default' => 'View More' ),
					'tn_title_typo'  => array( 'type' => 'typography', 'label' => __( 'Title Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-tourn-title' ) ),
					'tn_meta_typo'   => array( 'type' => 'typography', 'label' => __( 'Date / Location Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-tourn-meta' ) ),
				),
			),
			'program_opts' => array(
				'title'  => __( 'Program Cards', 'ds-toolkit' ),
				'fields' => array(
					'pg_cols'        => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '3', 'slider' => array( 'min' => 1, 'max' => 6, 'step' => 1 ) ),
					'pg_gap'         => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'pg_align'       => array( 'type' => 'select', 'label' => __( 'Alignment', 'ds-toolkit' ), 'default' => 'left', 'options' => array( 'left' => __( 'Left', 'ds-toolkit' ), 'center' => __( 'Center', 'ds-toolkit' ) ) ),
					'pg_same_height' => array( 'type' => 'select', 'label' => __( 'Card Height', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Equal (match tallest in row)', 'ds-toolkit' ), 'no' => __( 'Natural (fit content)', 'ds-toolkit' ) ), 'help' => __( 'Equal makes every card in a row the same height (buttons line up at the bottom).', 'ds-toolkit' ) ),
					'pg_card_bg'     => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pg_pad'         => array( 'type' => 'unit', 'label' => __( 'Card Padding', 'ds-toolkit' ), 'default' => '28', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'pg_img_h'       => array( 'type' => 'unit', 'label' => __( 'Image Height', 'ds-toolkit' ), 'default' => '180', 'description' => 'px', 'slider' => array( 'min' => 80, 'max' => 360, 'step' => 1 ), 'help' => __( 'Image height for items that use an image (not an icon).', 'ds-toolkit' ), 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-media', 'property' => 'height', 'unit' => 'px' ) ),
					'pg_icon_size'   => array( 'type' => 'unit', 'label' => __( 'Icon Size', 'ds-toolkit' ), 'default' => '40', 'description' => 'px', 'slider' => array( 'min' => 16, 'max' => 96, 'step' => 1 ), 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-ico', 'property' => 'font-size', 'unit' => 'px' ) ),
					'pg_icon_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Icon Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-ico', 'property' => 'color' ) ),
					'pg_date_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Date Colour', 'ds-toolkit' ), 'default' => 'var(--fl-global-accent)', 'show_reset' => true ),
					'pg_sub_color'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Sub-heading Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pg_title_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Title Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pg_desc_color'  => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Description Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pg_btn_style'      => array( 'type' => 'select', 'label' => __( 'Button Style', 'ds-toolkit' ), 'default' => 'theme', 'options' => array( 'theme' => __( 'Theme button (default)', 'ds-toolkit' ), 'custom' => __( 'Custom', 'ds-toolkit' ) ), 'toggle' => array( 'custom' => array( 'fields' => array( 'pg_btn_bg', 'pg_btn_color', 'pg_btn_hover_bg', 'pg_btn_hover_color', 'pg_btn_radius' ) ) ), 'help' => __( 'Theme button follows Theme Setting (colour + shape). Choose Custom to style this card\'s button below.', 'ds-toolkit' ) ),
					'pg_btn_bg'         => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Blank = the global Button colour.', 'ds-toolkit' ) ),
					'pg_btn_color'      => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Text', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pg_btn_hover_bg'   => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Hover Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pg_btn_hover_color'=> array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Hover Text', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'pg_btn_radius'     => array( 'type' => 'unit', 'label' => __( 'Button Radius', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 40, 'step' => 1 ), 'help' => __( 'Blank = follow the theme Button Shape. Set a value for a custom rounded button (overrides the theme shape for this card).', 'ds-toolkit' ) ),
					'pg_date_typo'      => array( 'type' => 'typography', 'label' => __( 'Date Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-date' ) ),
					'pg_sub_typo'       => array( 'type' => 'typography', 'label' => __( 'Sub-heading Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-sub' ) ),
					'pg_title_typo'     => array( 'type' => 'typography', 'label' => __( 'Title Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-title' ) ),
					'pg_desc_typo'      => array( 'type' => 'typography', 'label' => __( 'Description Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-desc' ) ),
					'pg_btn_typo'       => array( 'type' => 'typography', 'label' => __( 'Button Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-program-btn' ) ),
				),
			),
			'sponsor_opts' => array(
				'title'  => __( 'Sponsor Grid', 'ds-toolkit' ),
				'fields' => array(
					'sp_cols'       => array( 'type' => 'unit', 'label' => __( 'Columns', 'ds-toolkit' ), 'default' => '4', 'responsive' => true, 'slider' => array( 'min' => 1, 'max' => 6, 'step' => 1 ) ),
					'sp_gap'        => array( 'type' => 'unit', 'label' => __( 'Gap', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'sp_logo_h'     => array( 'type' => 'unit', 'label' => __( 'Logo Max Height', 'ds-toolkit' ), 'default' => '70', 'description' => 'px', 'slider' => array( 'min' => 30, 'max' => 180, 'step' => 1 ) ),
					'sp_pad'        => array( 'type' => 'unit', 'label' => __( 'Card Padding', 'ds-toolkit' ), 'default' => '24', 'description' => 'px', 'slider' => array( 'min' => 0, 'max' => 60, 'step' => 1 ) ),
					'sp_card_bg'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Card Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true ),
					'sp_name_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Caption Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'sp_desc_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Description Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'sp_grayscale'  => array( 'type' => 'select', 'label' => __( 'Greyscale Logos', 'ds-toolkit' ), 'default' => 'no', 'options' => array( 'no' => __( 'No', 'ds-toolkit' ), 'yes' => __( 'Yes (colour on hover)', 'ds-toolkit' ) ) ),
				),
			),
			'header_style' => array(
				'title'  => __( 'Header & Section', 'ds-toolkit' ),
				'fields' => array(
					'section_bg'          => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Section Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'heading_color'       => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Heading Text', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'heading_accent_color'=> array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Heading Accent', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'outline_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Outline Text Colour', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'help' => __( 'Stroke colour for {outline}…{/outline} text in this module. Blank = the Theme Setting default.', 'ds-toolkit' ) ),
					'outline_width' => array( 'type' => 'unit', 'label' => __( 'Outline Text Width', 'ds-toolkit' ), 'default' => '', 'description' => 'px', 'help' => __( 'Blank = the Theme Setting default.', 'ds-toolkit' ), 'slider' => array( 'min' => 1, 'max' => 8, 'step' => 1 ) ),
					'heading_typography'  => array( 'type' => 'typography', 'label' => __( 'Heading Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-news-heading' ) ),
					'btn_global' => array(
						'type'    => 'select',
						'label'   => __( 'Button Style', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array(
							'yes'    => __( 'Match site Button (Theme Setting)', 'ds-toolkit' ),
							'dark'   => __( 'Dark', 'ds-toolkit' ),
							'accent' => __( 'Heading Accent colour', 'ds-toolkit' ),
						),
						'help'    => __( 'The “See all” button inherits the global Button (background, hover, radius, typography) from Theme Setting by default.', 'ds-toolkit' ),
						'toggle'  => array( 'dark' => array( 'fields' => array( 'btn_dark_bg', 'btn_dark_color' ) ) ),
					),
					'btn_dark_bg'    => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
					'btn_dark_color' => array( 'type' => 'color', 'connections' => array( 'color' ), 'label' => __( 'Button Text', 'ds-toolkit' ), 'default' => '', 'show_reset' => true ),
				),
			),
			'typography' => array(
				'title'  => __( 'Typography', 'ds-toolkit' ),
				'fields' => array(
					'feature_title_typography' => array( 'type' => 'typography', 'label' => __( 'Featured Title', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-news-feature-title' ) ),
					'excerpt_typography'       => array( 'type' => 'typography', 'label' => __( 'Featured Excerpt', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-news-feature-excerpt' ) ),
					'badge_typography'         => array( 'type' => 'typography', 'label' => __( 'Badge', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-news-badge' ) ),
					'card_title_typography'    => array( 'type' => 'typography', 'label' => __( 'Card Title', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-news-card-title' ) ),
					'card_cat_typography'      => array( 'type' => 'typography', 'label' => __( 'Card Category', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-news-card-cat' ) ),
					'card_date_typography'     => array( 'type' => 'typography', 'label' => __( 'Card Date', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-news-card-date' ) ),
				),
			),
			'spacing' => array(
				'title'  => __( 'Spacing', 'ds-toolkit' ),
				'fields' => array(
					'content_width'     => array(
						'type'    => 'select',
						'label'   => __( 'Content Width', 'ds-toolkit' ),
						'default' => 'boxed',
						'options' => array(
							'boxed'  => __( 'Boxed (max 1280px)', 'ds-toolkit' ),
							'full'   => __( 'Full width (fill container)', 'ds-toolkit' ),
							'custom' => __( 'Custom max-width', 'ds-toolkit' ),
						),
						'toggle'  => array( 'custom' => array( 'fields' => array( 'content_max_width' ) ) ),
						'help'    => __( 'Full width lets this fill a full-width row/column; use the row/column padding for side spacing.', 'ds-toolkit' ),
					),
					'content_max_width' => array( 'type' => 'unit', 'label' => __( 'Max Width', 'ds-toolkit' ), 'default' => '1280', 'description' => 'px', 'slider' => array( 'min' => 480, 'max' => 1920, 'step' => 10 ) ),
					'padding' => array(
						'type'       => 'dimension',
						'label'      => __( 'Padding', 'ds-toolkit' ),
						'default'    => '0',
						'units'      => array( 'px' ),
						'slider'     => true,
						'responsive' => true,
						'help'       => __( 'Inner spacing around the content. 0 = compact / flush.', 'ds-toolkit' ),
					),
					'margin'  => array(
						'type'       => 'dimension',
						'label'      => __( 'Margin', 'ds-toolkit' ),
						'default'    => '0',
						'units'      => array( 'px' ),
						'slider'     => true,
						'responsive' => true,
						'help'       => __( 'Outer spacing around the whole section. 0 = compact / flush.', 'ds-toolkit' ),
					),
				),
			),
		),
	),
);
// Shared "Border & Divider" section appended to the Style tab for every layout.
$ds_pl_form['style']['sections'] = array_merge( $ds_pl_form['style']['sections'], DS_Module_UI::border_section( false ) );

/* Include / Exclude specific posts (GH #132) — one post-suggest (autocomplete + pills)
   pair per public post type, revealed by the Post Type selector's toggle. Built the
   same way as the taxonomy filter below and for the same reason: a BB settings form is
   registered once at init, so a single field cannot know which post type a given module
   instance will be set to. Generating a pair per type means each one can point
   `fl_as_posts` at its own type, which is what makes the picker search only Staff when
   Staff is selected. */
$ds_pl_q =& $ds_pl_form['query']['sections']['query']['fields'];
if ( isset( $ds_pl_q['post_type'] ) ) {
	if ( ! isset( $ds_pl_q['post_type']['toggle'] ) ) { $ds_pl_q['post_type']['toggle'] = array(); }
	foreach ( DS_Post_Loop_Module::post_type_options() as $ds_pt_name => $ds_pt_label ) {
		$ds_pt_key = str_replace( '-', '_', $ds_pt_name );
		$ds_inc    = 'inc_' . $ds_pt_key;
		$ds_exc    = 'exc_' . $ds_pt_key;
		$ds_pl_q['post_type']['toggle'][ $ds_pt_name ] = array( 'fields' => array( $ds_inc, $ds_exc ) );
		if ( isset( $ds_pl_q['source']['toggle']['custom']['fields'] ) ) {
			// A field named in no toggle branch is always visible, so without this the
			// pickers would linger when Source is "Current archive" (which ignores them).
			$ds_pl_q['source']['toggle']['custom']['fields'][] = $ds_inc;
			$ds_pl_q['source']['toggle']['custom']['fields'][] = $ds_exc;
		}
		$ds_pl_q[ $ds_inc ] = array(
			'type'   => 'suggest',
			'label'  => sprintf( __( 'Include Specific %s', 'ds-toolkit' ), $ds_pt_label ),
			'action' => 'fl_as_posts',
			'data'   => $ds_pt_name,
			'help'   => __( 'Type to search; picks show as pills. Leave empty for the normal query. When set, ONLY these entries are shown — Number of Posts, Order and Offset still apply on top.', 'ds-toolkit' ),
		);
		$ds_pl_q[ $ds_exc ] = array(
			'type'   => 'suggest',
			'label'  => sprintf( __( 'Exclude Specific %s', 'ds-toolkit' ), $ds_pt_label ),
			'action' => 'fl_as_posts',
			'data'   => $ds_pt_name,
			'help'   => __( 'Everything the query would normally return, minus these. Combines with Exclude Current Post rather than replacing it.', 'ds-toolkit' ),
		);
	}
	unset( $ds_pt_name, $ds_pt_label, $ds_pt_key, $ds_inc, $ds_exc );
}
unset( $ds_pl_q );

/* Taxonomy filter — a Taxonomy selector plus one term-suggest (autocomplete + pills)
   field per public taxonomy, revealed by the selector's toggle. Built dynamically so
   it always matches the site's registered taxonomies. */
$ds_pl_taxes = array();
foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $ds_tx ) {
	if ( 'post_format' === $ds_tx->name ) { continue; }
	$ds_pl_taxes[ $ds_tx->name ] = $ds_tx->label;
}
$ds_tax_fields = array(
	'filter_tax' => array(
		'type'    => 'select',
		'label'   => __( 'Filter by Taxonomy', 'ds-toolkit' ),
		'default' => '',
		'options' => array_merge( array( '' => __( '— No filter —', 'ds-toolkit' ) ), $ds_pl_taxes ),
		'toggle'  => array(),
		'help'    => __( 'Limit the loop to specific terms. Choose a taxonomy, then add terms below (they appear as pills).', 'ds-toolkit' ),
	),
);
foreach ( $ds_pl_taxes as $ds_tx_name => $ds_tx_label ) {
	$ds_field = 'flt_' . str_replace( '-', '_', $ds_tx_name );
	$ds_tax_fields['filter_tax']['toggle'][ $ds_tx_name ] = array( 'fields' => array( $ds_field ) );
	$ds_tax_fields[ $ds_field ] = array(
		'type'   => 'suggest',
		'label'  => sprintf( __( '%s Terms', 'ds-toolkit' ), $ds_tx_label ),
		'action' => 'fl_as_terms',
		'data'   => $ds_tx_name,
		'help'   => __( 'Type to search; selected terms show as pills. Leave empty for all terms in this taxonomy.', 'ds-toolkit' ),
	);
}
$ds_pl_form['query']['sections']['query_filter']['fields'] = $ds_tax_fields;

FLBuilder::register_module( 'DS_Post_Loop_Module', $ds_pl_form );
