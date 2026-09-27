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
			add_action( 'wp_ajax_' . self::SAVE_AJAX, array( $this, 'ajax_save' ) );
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

	/** Who may use the picker: LeagueApps users who can edit pages and the site's design (it rewrites the header, footer and site styles). */
	public static function can_use() {
		return class_exists( 'DS_Toolkit' ) && DS_Toolkit::is_leagueapps_user() && current_user_can( 'edit_pages' ) && current_user_can( 'edit_theme_options' );
	}

	/* ------------------------------------------- Hidden from non-LeagueApps users */

	private static function home_term_id() {
		$t = get_term_by( 'slug', self::CATEGORY, 'fl-builder-template-category' );
		return $t ? (int) $t->term_id : 0;
	}

	/**
	 * A Home template: a saved LAYOUT template in the "home" category. The type matters: a global row or module a
	 * developer saves into the same category must stay visible, or Beaver Builder's global-node lookups (get_posts)
	 * would miss it for visitors and they would get a stale copy (pre-release audit 2026-09-27).
	 */
	public static function is_home_template( $id ) {
		$id = is_object( $id ) ? (int) ( $id->ID ?? 0 ) : (int) $id;
		return $id && 'fl-builder-template' === get_post_type( $id )
			&& has_term( self::CATEGORY, 'fl-builder-template-category', $id )
			&& has_term( 'layout', 'fl-builder-template-type', $id );
	}

	/** The Home templates' IDs, once per request. */
	private static function home_ids() {
		static $ids = null, $busy = false;
		if ( null !== $ids ) { return $ids; }
		if ( $busy ) { return array(); } // this lookup runs through pre_get_posts too
		$busy = true;
		$ids  = get_posts( array(
			'post_type'        => 'fl-builder-template',
			'post_status'      => 'any',
			'posts_per_page'   => 200,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'tax_query'        => array(
				'relation' => 'AND',
				array( 'taxonomy' => 'fl-builder-template-category', 'field' => 'slug', 'terms' => array( self::CATEGORY ) ),
				array( 'taxonomy' => 'fl-builder-template-type', 'field' => 'slug', 'terms' => array( 'layout' ) ),
			),
		) );
		$busy = false;
		return $ids;
	}

	/** Leave Home templates out of every template query a non-LeagueApps user makes. */
	public static function hide_from_others( $q ) {
		$pt = $q->get( 'post_type' );
		if ( ! in_array( 'fl-builder-template', (array) $pt, true ) ) { return; }
		// Scripts (WP-CLI) see every template, unless a test asks for the web behaviour.
		$cli = defined( 'WP_CLI' ) && WP_CLI && ! apply_filters( 'ds_home_templates_hide_in_cli', false );
		if ( $cli || ( class_exists( 'DS_Toolkit' ) && DS_Toolkit::is_leagueapps_user() ) ) { return; }
		$ids = self::home_ids();
		if ( ! $ids ) { return; }
		$q->set( 'post__not_in', array_values( array_unique( array_merge( array_map( 'intval', (array) $q->get( 'post__not_in' ) ), $ids ) ) ) );
	}

	/** And no reading, editing or deleting one by ID either. */
	public static function deny_others( $caps, $cap, $user_id, $args ) {
		if ( empty( $args[0] ) || ! in_array( $cap, array( 'edit_post', 'delete_post', 'read_post', 'publish_post' ), true ) ) { return $caps; }
		if ( ! self::is_home_template( $args[0] ) ) { return $caps; }
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
			'update_post_meta_cache' => false, // bundles are large; each is read only when needed
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
	public static function build( $tpl, $current, $key = '_fl_builder_data' ) {
		// The layout exactly as stored: get_layout_data() fills in module defaults on the way out (a module
		// that gained settings since would gain keys), and a template applied back must be byte-identical.
		$data = self::copy( get_post_meta( $tpl, $key, true ) );
		if ( '_fl_builder_data' !== $key && ( ! is_array( $data ) || ! $data ) ) { $data = self::copy( get_post_meta( $tpl, '_fl_builder_data', true ) ); }
		if ( ! is_array( $data ) || ! $data ) { return array(); }
		$current = is_array( $current ) ? $current : array();
		foreach ( self::carry_fields() as $type => $pattern ) {
			$from = self::first_module( $current, $type );
			$to   = self::first_module( $data, $type );
			if ( ! $from || ! $to ) { continue; }
			$src = $current[ $from ]->settings;
			$dst = $data[ $to ]->settings;
			// Whether the hero shows a photo or video is the template's design: a template whose hero has none
			// (text on a textured band) keeps it that way, and a page whose hero has none has no media to give
			// (applying a photo template must not empty its photo). Media carries only between two media heroes;
			// the words and buttons always carry.
			$media = 'ds-hero' === $type && ( ! self::hero_has_media( $dst ) || ! self::hero_has_media( $src ) );
			foreach ( get_object_vars( $src ) as $k => $v ) {
				if ( ! preg_match( $pattern, $k ) || ! property_exists( $dst, $k ) ) { continue; }
				if ( $media && preg_match( self::HERO_MEDIA, $k ) ) { continue; }
				$dst->$k = self::copy( $v );
				// Its connection (a field bound to dynamic data) follows it exactly: set where the current page has
				// one, removed where it has none. Never an added empty entry.
				$sc = isset( $src->connections ) ? (array) $src->connections : array();
				if ( ! isset( $dst->connections ) ) { $dst->connections = array(); }
				$dc = (array) $dst->connections;
				if ( array_key_exists( $k, $sc ) ) { $dc[ $k ] = self::copy( $sc[ $k ] ); } else { unset( $dc[ $k ] ); }
				$dst->connections = is_object( $dst->connections ) ? (object) $dc : $dc;
			}
		}
		return $data;
	}

	/** The hero fields that hold its photo / video. */
	const HERO_MEDIA = '/^(bg_type|bg_photo(_src)?|bg_photos|mixed_slides|peek_slides|video_(media|url|poster)(_src)?)$/';

	/** Does this ds-hero show a background photo or video? */
	public static function hero_has_media( $s ) {
		$t = (string) ( $s->bg_type ?? 'image' );
		if ( 'slideshow' === $t ) { return ! empty( array_filter( (array) ( $s->bg_photos ?? array() ) ) ); }
		if ( 'video' === $t ) { return '' !== (string) ( $s->video_media ?? '' ) || '' !== (string) ( $s->video_url ?? '' ); }
		if ( 'mixed' === $t ) { return ! empty( array_filter( (array) ( $s->mixed_slides ?? array() ), function ( $x ) { $x = (array) $x; return ! empty( $x['photo'] ) || ! empty( $x['video_media'] ) || ! empty( $x['video_url'] ); } ) ); }
		return '' !== (string) ( $s->bg_photo ?? '' ) && '0' !== (string) ( $s->bg_photo ?? '' );
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

	/* ---------------------------------------------------- Site snapshots */

	/**
	 * A template is the whole look of a site: its own layout is the home page, and its
	 * BUNDLE_META holds the site header and footer layouts and the Theme Setting design
	 * options as they were when it was saved. Apply writes all of them; Revert puts all of
	 * them back. Partner identity (favicon, social card) and custom JavaScript are never part
	 * of a template.
	 */
	const BUNDLE_META = '_ds_home_bundle';
	const SAVE_AJAX   = 'ds_home_tpl_save';
	const POST_KEYS   = array( '_fl_builder_data', '_fl_builder_draft', '_fl_builder_data_settings', '_fl_builder_draft_settings', '_fl_theme_layout_settings' );
	/**
	 * The Themer layouts a template carries. 'page' and 'archive' are the base layouts that hold the page banner
	 * (Alipio 2026-09-27: a template's inner-page banner must be built with the Beaver module's own settings, not CSS),
	 * so each template keeps its own banner module settings and applying one never changes another's.
	 */
	const PARTS       = array( 'header', 'footer', 'page', 'archive' );

	/** Theme Setting's design options (theme mods) a template carries. */
	public static function style_mods() {
		return apply_filters( 'ds_home_templates_style_mods', array(
			'fl-body-bg-color', 'fl-body-bg-image', 'fl-body-bg-repeat', 'fl-body-bg-position', 'fl-body-bg-attachment', 'fl-body-bg-size', 'fl-body-bg-overlay', 'fl-body-bg-blend',
			'fl-content-bg-color', 'fl-content-bg-image', 'fl-content-bg-repeat', 'fl-content-bg-position', 'fl-content-bg-attachment', 'fl-content-bg-size', 'fl-content-bg-overlay', 'fl-content-bg-blend',
			'ds-banner-photo-title', 'ds-banner-photo-title-color', 'ds-banner-nobg-title-color', 'ds-banner-nobg-color', 'ds-banner-nobg-image', 'ds-banner-nobg-repeat', 'ds-banner-nobg-position', 'ds-banner-nobg-attachment', 'ds-banner-nobg-size', 'ds-banner-nobg-overlay', 'ds-banner-nobg-blend', 'ds-banner-featured-types',
			'ds-corner-radius', 'ds-outline-color', 'ds-outline-width', 'fl-css-code',
		) );
	}

	/**
	 * The Themer layouts a template sets, or 0: the site-wide header and footer, and the base page and archive layouts
	 * (the singular layout shown on every single, the archive layout on every archive) that hold the page banner.
	 */
	public static function part_ids() {
		$ids   = array( 'header' => 0, 'footer' => 0, 'page' => 0, 'archive' => 0 );
		$where = array( 'header' => array( 'header', 'general:site' ), 'footer' => array( 'footer', 'general:site' ), 'page' => array( 'singular', 'general:single' ), 'archive' => array( 'archive', 'general:archive' ) );
		foreach ( $where as $part => $w ) {
			foreach ( get_posts( array( 'post_type' => 'fl-theme-layout', 'post_status' => 'publish', 'posts_per_page' => 20, 'fields' => 'ids', 'orderby' => 'menu_order date', 'order' => 'ASC', 'meta_key' => '_fl_theme_layout_type', 'meta_value' => $w[0] ) ) as $id ) { // phpcs:ignore WordPress.DB.SlowDBQuery
				if ( in_array( $w[1], (array) get_post_meta( $id, '_fl_theme_builder_locations', true ), true ) ) { $ids[ $part ] = (int) $id; break; }
			}
		}
		return apply_filters( 'ds_home_templates_part_ids', $ids );
	}

	/** A post's builder layout, layout settings and Themer settings, exactly as stored. */
	private static function capture_post( $id ) {
		$out = array();
		foreach ( self::POST_KEYS as $k ) { $out[ $k ] = metadata_exists( 'post', $id, $k ) ? get_post_meta( $id, $k, true ) : null; }
		return $out;
	}

	/**
	 * Write a captured post back. Every key is deleted and added (never update_post_meta():
	 * see backup_to), so the stored values come back byte for byte, backslashes included.
	 */
	private static function write_post( $id, array $snap ) {
		foreach ( self::POST_KEYS as $k ) {
			if ( ! array_key_exists( $k, $snap ) ) { continue; }
			delete_post_meta( $id, $k );
			if ( null !== $snap[ $k ] ) { add_post_meta( $id, $k, FLBuilderModel::slash_settings( self::copy( $snap[ $k ] ) ), true ); }
		}
		update_post_meta( $id, '_fl_builder_enabled', true );
		FLBuilderModel::delete_all_asset_cache( $id );
		clean_post_cache( $id );
	}

	/** Theme Setting's design: BB Global Styles, the design theme mods and the button shape. */
	public static function capture_styles() {
		$mods = get_theme_mods();
		$mods = is_array( $mods ) ? $mods : array();
		$out  = array( 'styles' => get_option( '_fl_builder_styles', null ), 'mods' => array(), 'button' => get_option( 'ds_button_style', null ) );
		foreach ( self::style_mods() as $k ) { $out['mods'][ $k ] = array_key_exists( $k, $mods ) ? $mods[ $k ] : null; }
		return $out;
	}

	public static function write_styles( array $s ) {
		if ( array_key_exists( 'styles', $s ) ) {
			if ( null === $s['styles'] ) { delete_option( '_fl_builder_styles' ); } else { update_option( '_fl_builder_styles', $s['styles'], true ); }
		}
		foreach ( (array) ( $s['mods'] ?? array() ) as $k => $v ) {
			if ( null === $v ) { remove_theme_mod( $k ); } else { set_theme_mod( $k, $v ); }
		}
		if ( array_key_exists( 'button', $s ) ) {
			if ( null === $s['button'] ) { delete_option( 'ds_button_style' ); } else { update_option( 'ds_button_style', $s['button'] ); }
		}
	}

	/** Everything a template sets, as the site has it now. */
	public static function capture_site( $target ) {
		$ids = self::part_ids();
		return array(
			'home'   => $target ? self::capture_post( $target ) : null,
			'header'  => $ids['header'] ? self::capture_post( $ids['header'] ) : null,
			'footer'  => $ids['footer'] ? self::capture_post( $ids['footer'] ) : null,
			'page'    => $ids['page'] ? self::capture_post( $ids['page'] ) : null,
			'archive' => $ids['archive'] ? self::capture_post( $ids['archive'] ) : null,
			'styles'  => self::capture_styles(),
		);
	}

	/** Store a value holding BB layouts under a meta key, backslashes intact (delete + add: see backup_to). */
	private static function store( $id, $key, $value ) {
		delete_post_meta( $id, $key );
		add_post_meta( $id, $key, FLBuilderModel::slash_settings( self::copy( $value ) ), true );
	}

	/** Which parts a template carries, for its card. */
	public static function parts_of( $tpl ) {
		$b = get_post_meta( $tpl, self::BUNDLE_META, true );
		$p = array( 'home' );
		if ( is_array( $b ) ) { foreach ( array_merge( self::PARTS, array( 'styles' ) ) as $k ) { if ( ! empty( $b[ $k ] ) ) { $p[] = $k; } } }
		return $p;
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

	/** Refuse while anyone else has the home page, header or footer open in the builder. */
	private static function busy( $target ) {
		$names = array( 'home page' => $target ) + array_filter( self::part_ids() );
		foreach ( $names as $what => $id ) {
			$who = $id ? self::locked_by( $id ) : '';
			if ( $who ) { return sprintf( '%s is editing the %s in Beaver Builder. Try again when they close it.', $who, is_string( $what ) && 'home page' !== $what ? $what : 'home page' ); }
		}
		return '';
	}

	/**
	 * Set the site from a template: the home page layout (keeping its hero content), and the
	 * header, footer and design options the template was saved with. Everything is backed up
	 * first, as one set. Returns an error message or ''.
	 */
	public static function apply( $tpl, $target ) {
		if ( ! $target || 'page' !== get_post_type( $target ) ) { return 'Set a static front page first (Settings > Reading).'; }
		$busy = self::busy( $target );
		if ( $busy ) { return $busy; }
		$current = FLBuilderModel::get_layout_data( 'published', $target );
		$data    = self::build( $tpl, $current );
		if ( ! $data ) { return 'That template has no layout yet. Open it in Beaver Builder and publish it first.'; }
		self::backup_to( $target );
		// Each copy from its own: the template's draft is the page's draft as it was saved (they can differ, e.g.
		// in how BB ordered an empty repeater row), so applying a template saved from a page restores it exactly.
		$draft = self::build( $tpl, get_post_meta( $target, '_fl_builder_draft', true ) ?: $current, '_fl_builder_draft' );
		foreach ( array( 'published' => array( $data, '_fl_builder_data_settings' ), 'draft' => array( $draft, '_fl_builder_draft_settings' ) ) as $status => $x ) {
			FLBuilderModel::update_layout_data( self::copy( $x[0] ), $status, $target );
			self::store( $target, $x[1], self::merged_settings( $tpl, $target, $x[1] ) );
		}
		update_post_meta( $target, '_fl_builder_enabled', true );
		$bundle = get_post_meta( $tpl, self::BUNDLE_META, true );
		if ( is_array( $bundle ) ) {
			$ids = self::part_ids();
			foreach ( self::PARTS as $part ) {
				if ( ! empty( $bundle[ $part ] ) && $ids[ $part ] ) { self::write_post( $ids[ $part ], $bundle[ $part ] ); }
			}
			if ( ! empty( $bundle['styles'] ) ) { self::write_styles( $bundle['styles'] ); }
		}
		update_post_meta( $target, self::CURRENT_META, (int) $tpl );
		self::flush( $target );
		return '';
	}

	/**
	 * The page's layout settings with the template's layout CSS and JS. Only those two: BB's
	 * layout settings also hold page settings (title, slug, status, template), and a template
	 * saved from another page carries that page's, which BB would write to the home page.
	 */
	private static function merged_settings( $tpl, $target, $key = '_fl_builder_data_settings' ) {
		// Raw, not get_layout_settings(): that merges in defaults and reorders the keys.
		$raw  = get_post_meta( $target, $key, true );
		$page = $raw ? self::copy( (object) (array) $raw ) : (object) (array) FLBuilderModel::get_layout_settings( 'published', $target );
		$from = (object) (array) get_post_meta( $tpl, '_fl_builder_data_settings', true );
		$page->css = (string) ( $from->css ?? '' );
		$page->js  = (string) ( $from->js ?? '' );
		return $page;
	}

	/** Who holds the builder / edit lock on a post (another user, in the last 150 seconds), or ''. */
	private static function locked_by( $id ) {
		if ( ! function_exists( 'wp_check_post_lock' ) ) { require_once ABSPATH . 'wp-admin/includes/post.php'; }
		$uid = wp_check_post_lock( $id );
		if ( ! $uid ) { return ''; }
		$u = get_userdata( $uid );
		return $u ? $u->display_name : 'Someone';
	}

	/**
	 * Keep the whole site look as it is before Apply (one level): home page, header, footer,
	 * design options. Written slashed with add_post_meta(), which unslashes once. Not
	 * update_post_meta(): for a key the post does not have yet it unslashes the value, which
	 * changes the layout's objects in place, then passes those same objects to add_metadata(),
	 * which unslashes them again, so every module would lose a level of backslashes.
	 */
	private static function backup_to( $target ) {
		$b = array_merge( array( 'time' => time(), 'user' => get_current_user_id(), 'from' => (int) get_post_meta( $target, self::CURRENT_META, true ), 'ids' => self::part_ids() ), self::capture_site( $target ) );
		self::store( $target, self::BACKUP_META, $b );
	}

	public function ajax_revert() {
		$this->guard();
		$err = self::revert( self::target() );
		if ( $err ) { wp_send_json_error( array( 'message' => $err ) ); }
		wp_send_json_success( array( 'html' => $this->section_html() ) );
	}

	/** Put the home page, header, footer and design options back as they were before the last Apply. */
	public static function revert( $target ) {
		$b = get_post_meta( $target, self::BACKUP_META, true );
		if ( ! is_array( $b ) || empty( $b['home'] ) ) { return 'There is no earlier version to go back to.'; }
		$busy = self::busy( $target );
		if ( $busy ) { return $busy; }
		$from = (int) ( $b['from'] ?? 0 );
		self::write_post( $target, $b['home'] );
		foreach ( self::PARTS as $part ) {
			$id = (int) ( $b['ids'][ $part ] ?? 0 );
			if ( $id && ! empty( $b[ $part ] ) && get_post( $id ) ) { self::write_post( $id, $b[ $part ] ); }
		}
		if ( ! empty( $b['styles'] ) ) { self::write_styles( $b['styles'] ); }
		if ( $from ) { update_post_meta( $target, self::CURRENT_META, $from ); } else { delete_post_meta( $target, self::CURRENT_META ); }
		delete_post_meta( $target, self::BACKUP_META );
		self::flush( $target );
		return '';
	}

	/* ------------------------------------------------ Save the site into a template */

	public function ajax_save() {
		$this->guard();
		$target = self::target();
		if ( ! $target ) { wp_send_json_error( array( 'message' => 'Set a static front page first (Settings > Reading).' ) ); }
		$tpl  = isset( $_POST['template'] ) ? absint( $_POST['template'] ) : 0;
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		if ( $tpl && ! self::is_home_template( $tpl ) ) { wp_send_json_error( array( 'message' => 'That template is not in the Home category.' ) ); }
		if ( ! $tpl && '' === $name ) { wp_send_json_error( array( 'message' => 'Give the new template a name.' ) ); }
		$id = self::save_site( $target, $tpl, $name );
		if ( is_wp_error( $id ) ) { wp_send_json_error( array( 'message' => $id->get_error_message() ) ); }
		wp_send_json_success( array( 'html' => $this->section_html(), 'id' => $id ) );
	}

	/**
	 * Save the site as it is now into a Home template (a new one when $tpl is 0): the home
	 * page layout becomes the template's layout (node IDs kept), and the header, footer and
	 * design options go into its bundle. Returns the template ID or a WP_Error.
	 */
	public static function save_site( $target, $tpl = 0, $name = '' ) {
		$home  = get_post_meta( $target, '_fl_builder_data', true );
		if ( ! is_array( $home ) || ! $home ) { return new WP_Error( 'empty', 'The home page has no Beaver Builder layout to save.' ); }
		$draft = get_post_meta( $target, '_fl_builder_draft', true );
		$draft = is_array( $draft ) && $draft ? $draft : $home;
		if ( ! $tpl ) {
			$tpl = wp_insert_post( array( 'post_title' => $name, 'post_type' => 'fl-builder-template', 'post_status' => 'publish', 'menu_order' => count( self::templates() ) + 1, 'ping_status' => 'closed', 'comment_status' => 'closed' ), true );
			if ( is_wp_error( $tpl ) ) { return $tpl; }
			wp_set_post_terms( $tpl, 'layout', 'fl-builder-template-type' );
			$term = get_term_by( 'slug', self::CATEGORY, 'fl-builder-template-category' );
			if ( ! $term ) { $r = wp_insert_term( 'Home', 'fl-builder-template-category', array( 'slug' => self::CATEGORY ) ); $term = is_wp_error( $r ) ? null : get_term( $r['term_id'] ); }
			if ( $term ) { wp_set_object_terms( $tpl, (int) $term->term_id, 'fl-builder-template-category' ); }
		}
		$raw      = get_post_meta( $tpl, '_fl_builder_data_settings', true );
		$settings = $raw ? self::copy( (object) (array) $raw ) : (object) (array) FLBuilderModel::get_layout_settings( 'published', $tpl );
		$page     = (object) (array) get_post_meta( $target, '_fl_builder_data_settings', true );
		$settings->css = (string) ( $page->css ?? '' );
		$settings->js  = (string) ( $page->js ?? '' );
		foreach ( array( 'published' => array( $home, '_fl_builder_data_settings' ), 'draft' => array( $draft, '_fl_builder_draft_settings' ) ) as $status => $x ) {
			FLBuilderModel::update_layout_data( self::copy( $x[0] ), $status, $tpl );
			self::store( $tpl, $x[1], $settings );
		}
		update_post_meta( $tpl, '_fl_builder_enabled', true );
		$site = self::capture_site( $target );
		self::store( $tpl, self::BUNDLE_META, array( 'version' => 1, 'saved' => time(), 'by' => get_current_user_id(), 'header' => $site['header'], 'footer' => $site['footer'], 'page' => $site['page'], 'archive' => $site['archive'], 'styles' => $site['styles'] ) );
		FLBuilderModel::delete_all_asset_cache( $tpl );
		update_post_meta( $target, self::CURRENT_META, (int) $tpl );
		return (int) $tpl;
	}

	/** New layout CSS/JS everywhere (the header, footer and global styles are on every page), and no cached copies. */
	private static function flush( $id ) {
		FLBuilderModel::delete_all_asset_cache( $id );
		FLBuilderModel::delete_asset_cache_for_all_posts();
		if ( class_exists( 'FLCustomizer' ) && method_exists( 'FLCustomizer', 'refresh_css' ) ) { FLCustomizer::refresh_css(); }
		clean_post_cache( $id );
		wp_cache_flush();
		if ( class_exists( 'WpeCommon' ) ) {
			if ( method_exists( 'WpeCommon', 'purge_memcached' ) ) { WpeCommon::purge_memcached(); }
			if ( method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) { WpeCommon::purge_varnish_cache(); }
		}
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
		if ( ! class_exists( 'FLBuilderModel' ) ) { wp_send_json_error( array( 'message' => 'Beaver Builder is not active.' ), 409 ); }
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
		self::preview_bundle( get_post_meta( $tpl, self::BUNDLE_META, true ) );
		// The previewed layout's CSS/JS inline, never into the front page's shared cache file; and never cached.
		add_filter( 'fl_builder_render_assets_inline', '__return_true', 999 );
		if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
		nocache_headers();
	}

	/** The template's header, footer (layout, settings, sticky / overlay) and design options, for this request. */
	private static function preview_bundle( $bundle ) {
		if ( ! is_array( $bundle ) ) { return; }
		$ids = self::part_ids();
		foreach ( self::PARTS as $part ) {
			$pid  = (int) $ids[ $part ];
			$snap = $bundle[ $part ] ?? null;
			if ( ! $pid || ! is_array( $snap ) ) { continue; }
			if ( is_array( $snap['_fl_builder_data'] ?? null ) ) {
				$data = $snap['_fl_builder_data'];
				$swap = function ( $d, $status, $post_id ) use ( $data, $pid ) {
					if ( (int) $post_id !== $pid || 'draft' === $status ) { return $d; }
					return array_map( function ( $n ) { return is_object( $n ) ? clone $n : $n; }, $data );
				};
				add_filter( 'fl_builder_get_layout_metadata', $swap, 999, 3 );
				add_filter( 'fl_builder_layout_data', $swap, 999, 3 );
			}
			if ( isset( $snap['_fl_builder_data_settings'] ) && $snap['_fl_builder_data_settings'] ) {
				$ls = $snap['_fl_builder_data_settings'];
				add_filter( 'fl_builder_layout_settings', function ( $s, $status, $post_id ) use ( $ls, $pid ) { return (int) $post_id === $pid ? $ls : $s; }, 999, 3 );
			}
			if ( array_key_exists( '_fl_theme_layout_settings', $snap ) ) {
				$ts = $snap['_fl_theme_layout_settings'];
				add_filter( 'get_post_metadata', function ( $v, $oid, $key, $single ) use ( $ts, $pid ) {
					if ( (int) $oid !== $pid || '_fl_theme_layout_settings' !== $key ) { return $v; }
					return $single ? array( null === $ts ? '' : $ts ) : ( null === $ts ? array() : array( $ts ) );
				}, 999, 4 );
			}
		}
		$st = $bundle['styles'] ?? null;
		if ( ! is_array( $st ) ) { return; }
		if ( null !== ( $st['styles'] ?? null ) ) {
			$g = $st['styles'];
			add_filter( 'pre_option__fl_builder_styles', function () use ( $g ) { return $g; }, 999 );
			// BB keeps Global Styles in a static cache that may already hold the site's (read earlier in the request,
			// e.g. for the heading font): empty it so the preview reads the template's (fonts, colours, buttons).
			if ( class_exists( 'FLBuilderGlobalStyles' ) && property_exists( 'FLBuilderGlobalStyles', 'settings' ) ) {
				$prop = new ReflectionProperty( 'FLBuilderGlobalStyles', 'settings' );
				$prop->setAccessible( true );
				$prop->setValue( null, null );
			}
		}
		if ( null !== ( $st['button'] ?? null ) ) { $bt = $st['button']; add_filter( 'pre_option_ds_button_style', function () use ( $bt ) { return $bt; }, 999 ); }
		foreach ( (array) ( $st['mods'] ?? array() ) as $k => $v ) {
			if ( null !== $v ) { add_filter( 'theme_mod_' . $k, function () use ( $v ) { return $v; }, 999 ); }
		}
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
			'save'    => self::SAVE_AJAX,
			'query'   => self::QUERY,
		) );
	}

	public function render_section() {
		if ( ! self::can_use() ) { return; }
		echo '<section class="dsts-section" id="dsts-sec-home" data-section="home" hidden>';
		echo '<div class="dsts-sec-head"><h2>Home page</h2><p>Each template is a whole site look: the home page, header, footer and the design options on this page (colours, fonts, buttons, backgrounds). Preview shows it on this site; Apply sets all of it and keeps the hero\'s text and photos; Revert puts everything back in one click. Development sites only, and gone once the site launches.</p></div>';
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
			echo '<div class="dsht-revert"><p><strong>The previous site look is kept</strong> (home page, header, footer, design). ' . esc_html( sprintf( 'Saved %s ago%s.', human_time_diff( (int) $b['time'] ), '' !== $from ? ', from "' . $from . '"' : '' ) ) . '</p>';
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
				$labels = array( 'home' => 'Home', 'header' => 'Header', 'footer' => 'Footer', 'page' => 'Page banner', 'archive' => 'Archive banner', 'styles' => 'Design' );
				$parts  = array_map( function ( $k ) use ( $labels ) { return $labels[ $k ]; }, self::parts_of( $t['id'] ) );
				echo '<div class="dsht-meta"><strong class="dsht-title">' . esc_html( $t['title'] ) . '</strong>';
				echo '<span class="dsht-parts">' . esc_html( implode( ' · ', $parts ) ) . '</span>';
				echo '<span class="dsht-links"><a class="dsht-edit" href="' . esc_url( $t['edit'] ) . '" target="_blank" rel="noopener">Edit layout<span class="screen-reader-text"> (opens Beaver Builder in a new tab)</span></a>';
				echo '<button type="button" class="button-link dsht-save" data-dsht-save>Save site here</button></span>';
				echo '<div class="dsht-actions"><button type="button" class="button" data-dsht-preview>Preview</button>';
				// The template in use can be applied again: after it was edited in the builder, that is how the site gets the edit.
				echo '<button type="button" class="button ' . ( $is ? '' : 'button-primary' ) . '" data-dsht-apply' . ( $is ? ' data-reapply="1"' : '' ) . '>' . ( $is ? 'Re-apply' : 'Apply' ) . '</button></div></div></li>';
			}
			echo '</ul>';
		}
		echo '<p class="dsht-new"><button type="button" class="button" data-dsht-save-new><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> Save the current site as a new template</button></p>';
		echo '<p class="dsht-help">To make a template: build the look on this site (home page, header in Themer, design options here), then save it with the button above, or into an existing card with <em>Save site here</em>. Set a featured image on the template (Templates list) for its card. Only LeagueApps users can see Home templates.</p>';
		echo '<p class="dsht-launch"><button type="button" class="button-link" data-dsht-launch>This site has launched: remove the picker</button></p>';
		return ob_get_clean();
	}
}
