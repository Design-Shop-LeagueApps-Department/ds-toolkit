<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Make front-end archives follow the Nested Pages drag order.
 *
 * WP Nested Pages gives editors a drag-to-reorder tree and writes the result to
 * each post's `menu_order`. What it does NOT do is touch any front-end query:
 * the plugin registers no `pre_get_posts` and no `posts_orderby` hook at all.
 * So a CPT or taxonomy archive keeps WordPress's default `date DESC`, and the
 * order an editor carefully dragged is simply ignored on the public site. When
 * every entry shares a publish date (bulk-imported rosters, sample content) the
 * archive can even look correct by accident, then reshuffle the moment one post
 * is edited.
 *
 * This sets `menu_order ASC` on front-end archive queries for the post types
 * Nested Pages is actually managing, and only when nothing else has asked for a
 * particular order.
 *
 * Deliberately NOT reordered:
 *   - `post`. A blog or news archive is reverse-chronological by definition, and
 *     Nested Pages lists posts too. Reordering it would put news in drag order,
 *     which is never what a news archive wants.
 *   - `page`. Page archives are not a thing; child-page listings are already
 *     handled by [getsubmenu], [child_pages] and the Page Cards module, all of
 *     which order by `menu_order title` ASC themselves.
 *   - Search results, feeds and admin screens.
 *   - Any query that already carries an explicit `orderby`, so a Beaver Builder
 *     loop, a shortcode or another plugin always wins.
 *
 * Ascending is the only correct direction: Nested Pages numbers from 0 at the
 * top of the tree, so descending renders the tree upside down.
 */
class DS_Nested_Order {

	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = $settings;
	}

	public function init() {
		add_action( 'pre_get_posts', array( $this, 'apply_order' ) );
	}

	/**
	 * Post types Nested Pages manages, minus the ones that must not be reordered.
	 *
	 * The option is a map of post_type => settings written by the plugin. If it is
	 * absent (Nested Pages not installed, or never configured) this returns an
	 * empty array and the feature does nothing at all.
	 *
	 * @return string[]
	 */
	private function ordered_post_types() {
		$opt = get_option( 'nestedpages_posttypes' );
		if ( ! is_array( $opt ) || empty( $opt ) ) {
			return array();
		}
		$types = array_diff( array_keys( $opt ), array( 'post', 'page' ) );

		/**
		 * Filter the post types whose archives follow the Nested Pages order.
		 *
		 * @param string[] $types
		 */
		return (array) apply_filters( 'ds_nested_order_post_types', array_values( $types ) );
	}

	/**
	 * Which post type is this query actually listing?
	 *
	 * A taxonomy archive is the awkward case. At `pre_get_posts` the generic
	 * `taxonomy` and `term` query vars are still EMPTY: a taxonomy registered with
	 * its own query_var (team-category) is matched by that var instead, and WP only
	 * fills in `taxonomy`/`term` later when it builds the tax query. Reading
	 * `$q->get( 'taxonomy' )` therefore finds nothing and the whole feature
	 * silently no-ops on exactly the archives it exists for.
	 *
	 * So the taxonomy is identified from the other direction: for each candidate
	 * post type, look at the taxonomies attached to it and see whether the query
	 * carries a value for one of their query vars.
	 *
	 * @param WP_Query $q
	 * @param string[] $types Candidate post types.
	 * @return string|false
	 */
	private function queried_post_type( $q, $types ) {
		$pt = $q->get( 'post_type' );
		if ( is_array( $pt ) ) {
			$pt = count( $pt ) === 1 ? reset( $pt ) : false;
		}
		if ( $pt && 'any' !== $pt ) {
			return $pt;
		}

		if ( ! $q->is_tax() ) {
			return false;
		}

		// The generic pair, on the chance it is populated.
		$tax = (string) $q->get( 'taxonomy' );
		if ( '' !== $tax ) {
			$obj = get_taxonomy( $tax );
			if ( $obj && ! empty( $obj->object_type ) && count( $obj->object_type ) === 1 ) {
				return reset( $obj->object_type );
			}
			return false;
		}

		foreach ( $types as $type ) {
			foreach ( get_object_taxonomies( $type ) as $tax_name ) {
				$obj = get_taxonomy( $tax_name );
				if ( ! $obj ) {
					continue;
				}
				$var = ! empty( $obj->query_var ) ? $obj->query_var : $tax_name;
				if ( '' !== (string) $q->get( $var ) ) {
					// Only claim it when the taxonomy belongs to this type alone,
					// otherwise a shared taxonomy would reorder someone else's archive.
					if ( ! empty( $obj->object_type ) && count( $obj->object_type ) === 1 ) {
						return $type;
					}
				}
			}
		}
		return false;
	}

	/**
	 * @param WP_Query $q
	 */
	public function apply_order( $q ) {
		if ( is_admin() || ! $q->is_main_query() ) {
			return;
		}
		if ( $q->is_search() || $q->is_feed() || $q->is_singular() ) {
			return;
		}
		if ( ! $q->is_post_type_archive() && ! $q->is_tax() ) {
			return;
		}
		// Never override a deliberate choice made by a template, loop or plugin.
		if ( '' !== (string) $q->get( 'orderby' ) ) {
			return;
		}

		$types = $this->ordered_post_types();
		if ( empty( $types ) ) {
			return;
		}

		$pt = $this->queried_post_type( $q, $types );
		if ( ! $pt || ! in_array( $pt, $types, true ) ) {
			return;
		}

		// "menu_order title" so entries left at the same position (never dragged,
		// all still 0) fall back to something stable and readable instead of an
		// arbitrary database order.
		$q->set( 'orderby', 'menu_order title' );
		$q->set( 'order', 'ASC' );
	}
}
