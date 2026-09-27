<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Home page templates: pick the home page layout of a new build from Theme Setting.
 *
 * A template is an ordinary Beaver Builder layout template (Templates, type Layout) in
 * the template category "Home", so any LeagueApps dev can open and edit it in the
 * builder. The blueprint carries them, so every build cloned from it has them.
 *
 * Theme Setting > Home page lists them. Preview shows one on the site's front page in
 * the Theme Setting preview (this site's colours, logo and fonts, nothing saved); Apply
 * replaces the front page layout with it, after backing the current one up, and keeps the
 * current hero's content (heading, text, buttons, stats, photos or video), so switching
 * layouts does not lose the partner's copy. Revert puts the previous home page back.
 *
 * Where it shows (blueprint 7+, see the registry):
 *   - the picker: only on a development address (.local, *.wpenginepowered.com,
 *     *.flywheelsites.com ...), only until the site is marked launched, and only for
 *     LeagueApps users. A launched partner site never loads it.
 *   - the "Home" templates themselves stay hidden from non-LeagueApps users everywhere
 *     (template lists, the builder's Templates panel, editing), including after launch.
 */
class DS_Home_Templates {

	const CATEGORY     = 'home';                 // fl-builder-template-category slug
	const NONCE        = 'ds_home_templates';
	const APPLY_AJAX   = 'ds_home_tpl_apply';
	const REVERT_AJAX  = 'ds_home_tpl_revert';
	const LAUNCH_AJAX  = 'ds_home_tpl_launched';
	const QUERY        = 'ds_home_tpl';          // preview: ?ds_ts_preview=1&_dsnonce=..&ds_home_tpl=<id>
	const LAUNCHED     = 'ds_site_launched';     // option: 1 once the site is live
	const BACKUP_META  = '_ds_home_tpl_backup';  // the home page layout before the last Apply
	const CURRENT_META = '_ds_home_tpl_current'; // the template the home page was last set from

	/**
	 * Hero content that Apply carries from the current home page into the new layout
	 * (first module of the type on each side). Styling stays the template's.
	 */
	public static function carry_fields() {
		return apply_filters( 'ds_home_templates_carry', array(
			'ds-hero' => '/^(eyebrow|eyebrow_image(_src)?|eyebrow_img_h|heading|subtext|btn[12]_(text|link(_target|_nofollow|_download)?)|stat\d+_(number|label)|bg_type|bg_photo(_src)?|bg_photos|mixed_slides|peek_slides|video_(media|url|poster)(_src)?)$/',
		) );
	}

	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = is_array( $settings ) ? $settings : array();
	}

	public function init() {
		// Always on blueprint 7+: the Home templates are for LeagueApps staff only.
		add_action( 'pre_get_posts', array( __CLASS__, 'hide_from_others' ) );
		add_filter( 'map_meta_cap', array( __CLASS__, 'deny_others' ), 10, 4 );

		if ( ! self::available() ) { return; }
		if ( is_admin() ) {
			add_filter( 'ds_theme_setting_sections', array( $this, 'add_section' ) );
			add_action( 'ds_theme_setting_extra_sections', array( $this, 'render_section' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
			add_action( 'wp_ajax_' . self::APPLY_AJAX, array( $this, 'ajax_apply' ) );
			add_action( 'wp_ajax_' . self::REVERT_AJAX, array( $this, 'ajax_revert' ) );
			add_action( 'wp_ajax_' . self::LAUNCH_AJAX, array( $this, 'ajax_launched' ) );
		} elseif ( isset( $_GET[ self::QUERY ] ) ) {
			// Early (the user is known at init): nothing may have read the front page's layout
			// into Beaver Builder's per-request cache before the preview filter is in place.
			add_action( 'init', array( $this, 'maybe_preview' ), 99 );
		}
	}

	/* ------------------------------------------------------------- Gate */

	/** A development copy of the site: a local or host-provided address, not the partner's domain. */
	public static function is_dev_host( $host = null ) {
		$host = strtolower( (string) ( $host ?? wp_parse_url( home_url(), PHP_URL_HOST ) ) );
		$dev  = array( 'localhost', '.local', '.test', '.wpenginepowered.com', '.wpengine.com', '.flywheelsites.com', '.flywheelstaging.com' );
		$is   = false;
		foreach ( $dev as $d ) {
			if ( $host === ltrim( $d, '.' ) || ( '.' === $d[0] && substr( $host, -strlen( $d ) ) === $d ) ) { $is = true; break; }
		}
		return (bool) apply_filters( 'ds_home_templates_dev_host', $is, $host );
	}

	/** The picker runs here: a development address, and the site is not marked launched. */
	public static function available() {
		return self::is_dev_host() && ! get_option( self::LAUNCHED );
	}

	/** Who may use the picker: LeagueApps users who can edit pages. */
	public static function can_use() {
		return class_exists( 'DS_Toolkit' ) && DS_Toolkit::is_leagueapps_user() && current_user_can( 'edit_pages' );
	}

	/* ------------------------------------------- Hidden from non-LeagueApps users */

	private static function home_term_id() {
		$t = get_term_by( 'slug', self::CATEGORY, 'fl-builder-template-category' );
		return $t ? (int) $t->term_id : 0;
	}

	public static function is_home_template( $id ) {
		$id = (int) $id;
		return $id && 'fl-builder-template' === get_post_type( $id ) && has_term( self::CATEGORY, 'fl-builder-template-category', $id );
	}

	/** Leave Home templates out of every template query a non-LeagueApps user makes. */
	public static function hide_from_others( $q ) {
		// Scripts (WP-CLI) see every template, unless a test asks for the web behaviour.
		$cli = defined( 'WP_CLI' ) && WP_CLI && ! apply_filters( 'ds_home_templates_hide_in_cli', false );
		if ( $cli || ( class_exists( 'DS_Toolkit' ) && DS_Toolkit::is_leagueapps_user() ) ) { return; }
		$pt = $q->get( 'post_type' );
		if ( ! in_array( 'fl-builder-template', (array) $pt, true ) ) { return; }
		$rule = array( 'taxonomy' => 'fl-builder-template-category', 'field' => 'slug', 'terms' => array( self::CATEGORY ), 'operator' => 'NOT IN' );
		$tq   = $q->get( 'tax_query' );
		$q->set( 'tax_query', $tq ? array( 'relation' => 'AND', $tq, $rule ) : array( $rule ) );
	}

	/** And no reading, editing or deleting one by ID either. */
	public static function deny_others( $caps, $cap, $user_id, $args ) {
		if ( empty( $args[0] ) || ! in_array( $cap, array( 'edit_post', 'delete_post', 'read_post', 'publish_post' ), true ) ) { return $caps; }
		if ( 'fl-builder-template' !== get_post_type( (int) $args[0] ) || ! has_term( self::CATEGORY, 'fl-builder-template-category', (int) $args[0] ) ) { return $caps; }
		$u = get_userdata( $user_id );
		if ( $u && class_exists( 'DS_Toolkit' ) && self::leagueapps_email( $u->user_email ) ) { return $caps; }
		return array( 'do_not_allow' );
	}

	/** Same rule as DS_Toolkit::is_leagueapps_user(), for any user. */
	private static function leagueapps_email( $email ) {
		$domain = defined( 'DS_TOOLKIT_ADMIN_DOMAIN' ) ? DS_TOOLKIT_ADMIN_DOMAIN : '@leagueapps.com';
		return '' !== (string) $email && (bool) preg_match( '/' . preg_quote( $domain, '/' ) . '$/i', (string) $email );
	}

	/* ----------------------------------------------------------- Data */

	/** Home templates in builder order: [ id, title, thumb, edit ]. */
	public static function templates() {
		$out = array();
		foreach ( get_posts( array(
			'post_type'        => 'fl-builder-template',
			'post_status'      => 'publish',
			'posts_per_page'   => 50,
			'orderby'          => 'menu_order title',
			'order'            => 'ASC',
			'suppress_filters' => false,
			'tax_query'        => array(
				'relation' => 'AND',
				array( 'taxonomy' => 'fl-builder-template-type', 'field' => 'slug', 'terms' => 'layout' ),
				array( 'taxonomy' => 'fl-builder-template-category', 'field' => 'slug', 'terms' => self::CATEGORY ),
			),
		) ) as $p ) {
			$out[] = array(
				'id'    => (int) $p->ID,
				'title' => get_the_title( $p ),
				'thumb' => (string) get_the_post_thumbnail_url( $p, 'medium_large' ),
				'edit'  => add_query_arg( 'fl_builder', '', get_permalink( $p ) ),
			);
		}
		return $out;
	}

	/** The page the templates are applied to: the static front page. */
	public static function target() {
		$id = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;
		return (int) apply_filters( 'ds_home_templates_target', $id );
	}

	/** A layout copy that shares no objects with BB's cache (its writers mutate objects in place). */
	private static function copy( $v ) {
		return unserialize( serialize( $v ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	/**
	 * The template's layout with the current home page's hero content carried over.
	 * Node IDs are kept as the template has them, so layout CSS that targets a node
	 * (.fl-node-<id>) keeps working, and applying the template a page was made from is
	 * a no-op. A page's node IDs only need to be unique within that page.
	 */
	public static function build( $tpl, $current ) {
		$data = self::copy( FLBuilderModel::get_layout_data( 'published', $tpl ) );
		if ( ! is_array( $data ) || ! $data ) { return array(); }
		$current = is_array( $current ) ? $current : array();
		foreach ( self::carry_fields() as $type => $pattern ) {
			$from = self::first_module( $current, $type );
			$to   = self::first_module( $data, $type );
			if ( ! $from || ! $to ) { continue; }
			$src = $current[ $from ]->settings;
			$dst = $data[ $to ]->settings;
			foreach ( get_object_vars( $src ) as $k => $v ) {
				if ( ! preg_match( $pattern, $k ) || ! property_exists( $dst, $k ) ) { continue; }
				$dst->$k = self::copy( $v );
				// A field connected to dynamic data on the current page keeps that connection.
				if ( isset( $src->connections ) ) {
					$sc = (array) $src->connections;
					if ( ! isset( $dst->connections ) ) { $dst->connections = array(); }
					if ( is_object( $dst->connections ) ) { $dst->connections->$k = $sc[ $k ] ?? ''; } else { $dst->connections[ $k ] = $sc[ $k ] ?? ''; }
				}
			}
		}
		return $data;
	}

	/** ID of the first module of a type, in page order (row, column group, column, module positions). */
	private static function first_module( array $data, $type ) {
		$best = null; $best_key = null;
		foreach ( $data as $id => $n ) {
			if ( ! is_object( $n ) || 'module' !== ( $n->type ?? '' ) || $type !== ( $n->settings->type ?? '' ) ) { continue; }
			$key = array(); $cur = $n; $guard = 0;
			while ( $cur && $guard++ < 12 ) { array_unshift( $key, (int) ( $cur->position ?? 0 ) ); $cur = ( ! empty( $cur->parent ) && isset( $data[ $cur->parent ] ) ) ? $data[ $cur->parent ] : null; }
			if ( null === $best_key || $key < $best_key ) { $best = $id; $best_key = $key; }
		}
		return $best;
	}

	/* ---------------------------------------------------------- Apply */

	public function ajax_apply() {
		$this->guard();
		$tpl    = isset( $_POST['template'] ) ? absint( $_POST['template'] ) : 0;
		$target = self::target();
		if ( ! self::is_home_template( $tpl ) ) { wp_send_json_error( array( 'message' => 'That template is not in the Home category.' ) ); }
		$err = self::apply( $tpl, $target );
		if ( $err ) { wp_send_json_error( array( 'message' => $err ) ); }
		wp_send_json_success( array( 'html' => $this->section_html() ) );
	}

	/** Set the page's layout from a template (backing the page up first). Returns an error message or ''. */
	public static function apply( $tpl, $target ) {
		if ( ! $target || 'page' !== get_post_type( $target ) ) { return 'Set a static front page first (Settings > Reading).'; }
		$busy = self::locked_by( $target );
		if ( $busy ) { return sprintf( '%s is editing the home page in Beaver Builder. Try again when they close it.', $busy ); }
		$current = FLBuilderModel::get_layout_data( 'published', $target );
		$data    = self::build( $tpl, $current );
		if ( ! $data ) { return 'That template has no layout yet. Open it in Beaver Builder and publish it first.'; }
		self::backup( $target );
		$settings = self::merged_settings( $tpl, $target );
		foreach ( array( 'published', 'draft' ) as $status ) {
			FLBuilderModel::update_layout_data( self::copy( $data ), $status, $target );
			FLBuilderModel::update_layout_settings( self::copy( $settings ), $status, $target );
		}
		update_post_meta( $target, '_fl_builder_enabled', true );
		update_post_meta( $target, self::CURRENT_META, (int) $tpl );
		self::flush( $target );
		return '';
	}

	/**
	 * The page's layout settings with the template's layout CSS and JS. Only those two: BB's
	 * layout settings also hold page settings (title, slug, status, template), and a template
	 * saved from another page carries that page's, which BB would write to the home page.
	 */
	private static function merged_settings( $tpl, $target ) {
		$page = (object) (array) FLBuilderModel::get_layout_settings( 'published', $target );
		$from = (object) (array) FLBuilderModel::get_layout_settings( 'published', $tpl );
		$page->css = (string) ( $from->css ?? '' );
		$page->js  = (string) ( $from->js ?? '' );
		return $page;
	}

	/** Who holds the builder / edit lock on the page (another user, in the last 150 seconds), or ''. */
	private static function locked_by( $id ) {
		if ( ! function_exists( 'wp_check_post_lock' ) ) { require_once ABSPATH . 'wp-admin/includes/post.php'; }
		$uid = wp_check_post_lock( $id );
		if ( ! $uid ) { return ''; }
		$u = get_userdata( $uid );
		return $u ? $u->display_name : 'Someone';
	}

	/**
	 * Keep the page's layout as it is before Apply (one level). Written slashed with
	 * add_post_meta(), which unslashes once. Not update_post_meta(): for a key the post does
	 * not have yet it unslashes the value, which changes the layout's objects in place, then
	 * passes those same objects to add_metadata(), which unslashes them again, so every
	 * module would lose a level of backslashes ("\201C" -> "201C").
	 */
	private static function backup( $id ) {
		$b = array(
			'time'     => time(),
			'user'     => get_current_user_id(),
			'from'     => (int) get_post_meta( $id, self::CURRENT_META, true ),
			'data'     => get_post_meta( $id, '_fl_builder_data', true ),
			'draft'    => get_post_meta( $id, '_fl_builder_draft', true ),
			'settings' => get_post_meta( $id, '_fl_builder_data_settings', true ),
			'draft_settings' => get_post_meta( $id, '_fl_builder_draft_settings', true ),
		);
		delete_post_meta( $id, self::BACKUP_META );
		add_post_meta( $id, self::BACKUP_META, FLBuilderModel::slash_settings( self::copy( $b ) ), true );
	}

	public function ajax_revert() {
		$this->guard();
		$err = self::revert( self::target() );
		if ( $err ) { wp_send_json_error( array( 'message' => $err ) ); }
		wp_send_json_success( array( 'html' => $this->section_html() ) );
	}

	/** Put the page back as it was before the last Apply. Returns an error message or ''. */
	public static function revert( $target ) {
		$b = get_post_meta( $target, self::BACKUP_META, true );
		if ( ! is_array( $b ) || ! isset( $b['data'] ) ) { return 'There is no earlier home page to go back to.'; }
		$busy = self::locked_by( $target );
		if ( $busy ) { return sprintf( '%s is editing the home page in Beaver Builder. Try again when they close it.', $busy ); }
		// Back through Beaver Builder's own writers (slash-safe, and they add or update as needed).
		foreach ( array( 'published' => array( 'data', 'settings', '_fl_builder_data', '_fl_builder_data_settings' ), 'draft' => array( 'draft', 'draft_settings', '_fl_builder_draft', '_fl_builder_draft_settings' ) ) as $status => $m ) {
			if ( is_array( $b[ $m[0] ] ) ) { FLBuilderModel::update_layout_data( self::copy( $b[ $m[0] ] ), $status, $target ); } else { delete_post_meta( $target, $m[2] ); }
			// Settings exactly as they were (update_layout_settings() would merge and reorder them).
			delete_post_meta( $target, $m[3] );
			if ( $b[ $m[1] ] ) { add_post_meta( $target, $m[3], FLBuilderModel::slash_settings( self::copy( $b[ $m[1] ] ) ), true ); }
		}
		if ( $b['from'] ) { update_post_meta( $target, self::CURRENT_META, (int) $b['from'] ); } else { delete_post_meta( $target, self::CURRENT_META ); }
		delete_post_meta( $target, self::BACKUP_META );
		self::flush( $target );
		return '';
	}

	/** New layout CSS/JS for the page, and no cached copy of the old one. */
	private static function flush( $id ) {
		FLBuilderModel::delete_all_asset_cache( $id );
		clean_post_cache( $id );
		wp_cache_delete( $id, 'post_meta' );
		if ( class_exists( 'WpeCommon' ) && method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) { WpeCommon::purge_varnish_cache( $id ); }
		do_action( 'ds_home_templates_applied', $id );
	}

	public function ajax_launched() {
		$this->guard();
		update_option( self::LAUNCHED, 1 );
		wp_send_json_success();
	}

	private function guard() {
		check_ajax_referer( self::NONCE, 'nonce' );
		if ( ! self::can_use() ) { wp_send_json_error( array( 'message' => 'Only LeagueApps users can change the home page layout here.' ), 403 ); }
	}

	/* -------------------------------------------------------- Preview */

	/**
	 * The Theme Setting preview of a template: the front page renders the template's layout
	 * for this request only (checked for user + the Theme Setting preview nonce; BB renders
	 * that request's layout CSS inline, so no cache file is written).
	 */
	public function maybe_preview() {
		if ( ! self::can_use() || ! class_exists( 'DS_Theme_Setting' ) || empty( $_GET[ DS_Theme_Setting::PREVIEW_QUERY ] ) ) { return; }
		$nonce = isset( $_GET['_dsnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_dsnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, DS_Theme_Setting::PREVIEW_NONCE ) ) { return; }
		$tpl    = absint( $_GET[ self::QUERY ] );
		$target = self::target();
		if ( ! $target || ! self::is_home_template( $tpl ) ) { return; }
		// Read straight from the meta: going through get_layout_data() here would fill BB's
		// per-request cache with the real layout, and the filter below would never run.
		$data     = self::build( $tpl, get_metadata( 'post', $target, '_fl_builder_data', true ) );
		$settings = self::merged_settings( $tpl, $target );
		if ( ! $data ) { return; }
		// Both filters: BB caches the filtered metadata, but the FIRST get_layout_data() call of a
		// request returns the unfiltered copy, and BB then caches each node's settings by node ID
		// from it (a template made from this page shares its node IDs). fl_builder_layout_data runs
		// on every return, the first included; each gets its own copy of the nodes.
		$swap = function ( $d, $status, $post_id ) use ( $data, $target ) {
			if ( (int) $post_id !== $target || 'draft' === $status ) { return $d; }
			return array_map( function ( $n ) { return is_object( $n ) ? clone $n : $n; }, $data );
		};
		add_filter( 'fl_builder_get_layout_metadata', $swap, 999, 3 );
		add_filter( 'fl_builder_layout_data', $swap, 999, 3 );
		add_filter( 'fl_builder_layout_settings', function ( $s, $status, $post_id ) use ( $settings, $target ) {
			return (int) $post_id === $target ? $settings : $s;
		}, 999, 3 );
		if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
	}

	/* ------------------------------------------------------ Admin UI */

	public function add_section( $sections ) {
		if ( ! self::can_use() ) { return $sections; }
		return array( 'home' => array( 'Home page', 'dashicons-admin-home' ) ) + (array) $sections;
	}

	public function enqueue( $hook ) {
		if ( ! class_exists( 'DS_Theme_Setting' ) || 'toplevel_page_' . DS_Theme_Setting::PAGE_SLUG !== $hook || ! self::can_use() ) { return; }
		$ver = function ( $rel ) { $t = @filemtime( DS_TOOLKIT_PATH . $rel ); return $t ? DS_TOOLKIT_VERSION . '.' . $t : DS_TOOLKIT_VERSION; }; // phpcs:ignore WordPress.PHP.NoSilencedErrors
		wp_enqueue_style( 'ds-home-templates', DS_TOOLKIT_URL . 'assets/css/home-templates.css', array( 'ds-theme-setting' ), $ver( 'assets/css/home-templates.css' ) );
		wp_enqueue_script( 'ds-home-templates', DS_TOOLKIT_URL . 'assets/js/home-templates.js', array( 'ds-theme-setting' ), $ver( 'assets/js/home-templates.js' ), true );
		wp_localize_script( 'ds-home-templates', 'dsHomeTpl', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::NONCE ),
			'apply'   => self::APPLY_AJAX,
			'revert'  => self::REVERT_AJAX,
			'launch'  => self::LAUNCH_AJAX,
			'query'   => self::QUERY,
		) );
	}

	public function render_section() {
		if ( ! self::can_use() ) { return; }
		echo '<section class="dsts-section" id="dsts-sec-home" data-section="home" hidden>';
		echo '<div class="dsts-sec-head"><h2>Home page</h2><p>Start the home page from a layout. Preview shows it with this site\'s colours, logo and hero content; Apply replaces the home page layout and keeps its hero content (the current layout is kept for Revert). Development sites only, and gone once the site launches.</p></div>';
		echo '<div id="dsht" data-dsht>' . $this->section_html() . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in section_html()
		echo '</section>';
	}

	/** The section's inner HTML (also sent back after Apply / Revert). */
	public function section_html() {
		$target  = self::target();
		$current = $target ? (int) get_post_meta( $target, self::CURRENT_META, true ) : 0;
		$tpls    = self::templates();
		ob_start();
		if ( ! $target ) {
			echo '<p class="dsht-note">This site has no static front page. Set one in Settings &gt; Reading.</p>';
		}
		$b = $target ? get_post_meta( $target, self::BACKUP_META, true ) : null;
		if ( is_array( $b ) && ! empty( $b['time'] ) ) {
			$from = $b['from'] ? get_the_title( $b['from'] ) : '';
			echo '<div class="dsht-revert"><p><strong>Previous home page kept.</strong> ' . esc_html( sprintf( 'Saved %s ago%s.', human_time_diff( (int) $b['time'] ), '' !== $from ? ', from "' . $from . '"' : '' ) ) . '</p>';
			echo '<button type="button" class="button" data-dsht-revert>Revert to it</button></div>';
		}
		if ( ! $tpls ) {
			echo '<p class="dsht-note">No home templates yet. In Beaver Builder, save a page as a Layout template in the category <strong>Home</strong>.</p>';
		} else {
			echo '<ul class="dsht-grid">';
			foreach ( $tpls as $t ) {
				$is = $current === $t['id'];
				echo '<li class="dsht-card' . ( $is ? ' is-current' : '' ) . '" data-id="' . esc_attr( $t['id'] ) . '">';
				echo '<div class="dsht-thumb">' . ( $t['thumb'] ? '<img src="' . esc_url( $t['thumb'] ) . '" alt="" loading="lazy">' : '<span class="dashicons dashicons-admin-home" aria-hidden="true"></span>' ) . ( $is ? '<span class="dsht-badge">Current</span>' : '' ) . '</div>';
				echo '<div class="dsht-meta"><strong class="dsht-title">' . esc_html( $t['title'] ) . '</strong>';
				echo '<a class="dsht-edit" href="' . esc_url( $t['edit'] ) . '" target="_blank" rel="noopener">Edit template<span class="screen-reader-text"> (opens Beaver Builder in a new tab)</span></a>';
				echo '<div class="dsht-actions"><button type="button" class="button" data-dsht-preview>Preview</button>';
				echo '<button type="button" class="button button-primary" data-dsht-apply' . ( $is ? ' disabled' : '' ) . '>' . ( $is ? 'In use' : 'Apply' ) . '</button></div></div></li>';
			}
			echo '</ul>';
		}
		echo '<p class="dsht-help">Add a template: build the page in Beaver Builder, then Save As &gt; Template, category <strong>Home</strong>. Give it a featured image for its card. Only LeagueApps users can see Home templates.</p>';
		echo '<p class="dsht-launch"><button type="button" class="button-link" data-dsht-launch>This site has launched: remove the picker</button></p>';
		return ob_get_clean();
	}
}
