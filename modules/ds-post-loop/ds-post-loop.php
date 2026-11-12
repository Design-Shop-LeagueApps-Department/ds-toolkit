<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once DS_TOOLKIT_PATH . 'includes/class-ds-module-ui.php';
require_once __DIR__ . '/includes/class-ds-loop-manager.php';

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
require_once __DIR__ . '/includes/trait-people.php';
require_once __DIR__ . '/includes/trait-cards.php';
require_once __DIR__ . '/includes/trait-avatars.php';
require_once __DIR__ . '/includes/trait-manual.php';
require_once __DIR__ . '/includes/trait-tournament.php';

class DS_Post_Loop_Module extends FLBuilderModule {

	// The layouts live in includes/, one trait per family. This file keeps what they share: the layout
	// list, the defaults, the one query and the dispatcher.
	use DS_Post_Loop_People,
		DS_Post_Loop_Cards,
		DS_Post_Loop_Avatars,
		DS_Post_Loop_Manual,
		DS_Post_Loop_Tournament;

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

	/** Beaver Builder runs this whenever the module's settings are saved (all versions): sign any pending "Manage entries" changes for the saving user. */
	public function update( $settings ) {
		// The Custom layout's HTML is printed as written, like Beaver Builder's own HTML module. Someone saving
		// without unfiltered_html (an Author or Editor on a site that grants them the builder) gets the same
		// filtering WordPress applies to their post content; administrators and scripts are unchanged.
		if ( is_object( $settings ) && isset( $settings->loop_custom ) && is_string( $settings->loop_custom )
			&& get_current_user_id() && ! current_user_can( 'unfiltered_html' ) ) {
			$settings->loop_custom = wp_kses_post( $settings->loop_custom );
		}
		return class_exists( 'DS_Loop_Manager' ) ? DS_Loop_Manager::stamp_settings( $settings ) : $settings;
	}

	/**
	 * Values a module gets when its saved settings predate a field, pinned to what that
	 * field's default USED to be. Beaver Builder fills missing keys from the current form
	 * defaults, so without this a module scripted or saved before 1.10.4 would pick up the
	 * new defaults (fill the column, solid header divider) and change on the live site.
	 * Every module saved from the builder already stores these keys, so this only reaches
	 * the ones that never did.
	 */
	private static function legacy_defaults() {
		return array(
			'content_width'        => 'boxed',
			'header_divider'       => 'none',
			'header_divider_w'     => '1',
			'header_divider_color' => '',
			'connections'          => array(),
		);
	}

	/**
	 * A new module's Divider Colour arrives connected to the site's "Line Color" global
	 * colour, so the builder shows the "Global - Line Color" pill and the line follows that
	 * swatch. The connection names the colour by its per-site id, so it is looked up here;
	 * a site without a "Line Color" global keeps the plain var(--fl-global-line-color) default.
	 * Runs on fl_builder_module_defaults, i.e. only for settings Beaver builds from defaults.
	 */
	public static function default_divider_connection( $defaults, $module = null ) {
		if ( ! is_object( $defaults ) || 'ds-post-loop' !== ( $defaults->type ?? '' ) || ! class_exists( 'FLBuilderGlobalStyles' ) ) {
			return $defaults;
		}
		static $uid = null;
		if ( null === $uid ) {
			$uid = '';
			$gs  = FLBuilderGlobalStyles::get_settings( false );
			foreach ( (array) ( $gs->colors ?? array() ) as $c ) {
				$c = (object) $c;
				if ( ! empty( $c->uid ) && 'line-color' === sanitize_title( $c->label ?? '' ) ) { $uid = (string) $c->uid; break; }
			}
		}
		if ( '' === $uid ) { return $defaults; }
		$conn = is_array( $defaults->connections ?? null ) ? $defaults->connections : array();
		if ( empty( $conn['header_divider_color'] ) ) {
			$conn['header_divider_color'] = (object) array( 'property' => 'global_color_' . $uid, 'object' => 'site', 'field' => 'color', 'settings' => null );
		}
		$defaults->connections = $conn;
		return $defaults;
	}

	private static function pin_legacy_defaults( $settings ) {
		if ( is_object( $settings ) ) {
			foreach ( self::legacy_defaults() as $k => $v ) {
				if ( ! property_exists( $settings, $k ) ) { $settings->$k = $v; }
			}
		}
		return $settings;
	}

	/** Beaver Builder 2.9+: raw saved settings, before the form defaults are merged in. */
	public function filter_raw_settings_defaults( $settings, $defaults ) {
		return self::pin_legacy_defaults( $settings );
	}

	/** Beaver Builder before 2.9 calls this one instead. */
	public function filter_raw_settings( $settings ) {
		return self::pin_legacy_defaults( $settings );
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

		// A related loop is empty because nothing is linked on this page, not because nothing is published.
		if ( 'related' === ( $s->source ?? '' ) ) {
			$rf    = (string) ( $s->related_field ?? '' );
			$field = ( '' !== $rf && function_exists( 'acf_get_field' ) ) ? acf_get_field( $rf ) : null;
			/* translators: %s: ACF relationship field label */
			$hint = sprintf( __( 'Nothing is linked to this page yet. Pick entries in its "%s" field in the dashboard.', 'ds-toolkit' ), ! empty( $field['label'] ) ? $field['label'] : $rf );
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
	private function link_parts( $link, $target = '' ) {
		return DS_Card::link_parts( $link, $target );
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

		// Related source: the posts an ACF relationship / post-object field on the page being viewed
		// points to, in the order they were picked (e.g. a team's coaches via team_coach).
		if ( ( $s->source ?? 'custom' ) === 'related' ) {
			$field = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $s->related_field ?? '' ) ) ?: 'team_coach';
			$pid   = is_singular() ? (int) get_queried_object_id() : (int) get_the_ID();
			$ids   = $pid ? self::related_ids( $field, $pid ) : array();
			return new WP_Query( array(
				'post_type'           => 'any',
				'post__in'            => $ids ?: array( 0 ),
				'orderby'             => 'post__in',
				'posts_per_page'      => max( 1, count( $ids ) ),
				'post_status'         => 'publish',
				'ignore_sticky_posts' => true,
			) );
		}

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
		// Ties (every entry at menu_order 0, two posts at the same minute) need a fixed tiebreak, or the database
		// picks one and the Manage entries list can show another. ID ascending is what MySQL returned for ties
		// before, so no live page changes order; DS_Loop_Manager::list_posts() uses the same rule.
		if ( in_array( $ob, array( 'date', 'title', 'menu_order', 'modified' ), true ) ) {
			$args['orderby'] = array( $ob => $order, 'ID' => 'ASC' );
			unset( $args['order'] );
		}

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

		// Builder preview of pending "Manage entries" changes (DS_Loop_Manager::pv_posts).
		if ( DS_Loop_Manager::previewing() ) { $args['ds_loop_preview'] = 1; }

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
		// In the builder, show pending "Manage entries" changes (saved only on Publish).
		$pv = DS_Loop_Manager::begin_preview( $this->settings );
		try { $this->$method(); } finally { if ( $pv ) { DS_Loop_Manager::end_preview(); } }
	}

}

// The settings form: sub-forms for the manual lists, then $ds_pl_form.
require __DIR__ . '/includes/settings-form.php';

FLBuilder::register_module( 'DS_Post_Loop_Module', $ds_pl_form );
add_filter( 'fl_builder_module_defaults', array( 'DS_Post_Loop_Module', 'default_divider_connection' ), 10, 2 );

// Builder assets for "Manage entries".
add_action( 'fl_builder_ui_enqueue_scripts', function () {
	$v = function ( $rel ) { $t = @filemtime( DS_TOOLKIT_PATH . $rel ); return $t ? DS_TOOLKIT_VERSION . '.' . $t : DS_TOOLKIT_VERSION; };
	wp_enqueue_media();
	wp_enqueue_style( 'ds-loop-manager', DS_TOOLKIT_URL . 'modules/ds-post-loop/css/manager.css', array(), $v( 'modules/ds-post-loop/css/manager.css' ) );
	wp_enqueue_script( 'ds-loop-manager', DS_TOOLKIT_URL . 'modules/ds-post-loop/js/manager.js', array( 'jquery' ), $v( 'modules/ds-post-loop/js/manager.js' ), true );
	wp_localize_script( 'ds-loop-manager', 'DSLoopManager', array(
		'ajaxurl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'ds_loop_manager' ),
	) );
} );

// [ds_team_coach_avatars] — usable in the Custom Item Markup above, and anywhere
// else a shortcode runs (a Text module, a Themer layout, post content).
if ( ! shortcode_exists( 'ds_team_coach_avatars' ) ) {
	add_shortcode( 'ds_team_coach_avatars', array( 'DS_Post_Loop_Module', 'coach_avatars_shortcode' ) );
}
