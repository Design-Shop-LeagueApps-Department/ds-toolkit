<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LeagueApps Programs, an in-house Beaver Builder module that renders a
 * partner's live LeagueApps tournament, league, camp and clinic listings as a
 * filterable table.
 *
 * Replaces the hosted `la-listing-widget-<uuid>` iframe embed. What it does
 * that the hosted widget cannot:
 *
 *   1. COLUMN and FILTER order are dragged in the builder instead of rebuilt
 *      by LeagueApps staff, and every value comes from one field catalogue
 *      (DS_Programs_Data::catalog()), so a partner can show days, city,
 *      youth/adult, registration status and more, not a fixed set.
 *   2. MONTH sorts chronologically, AGE GROUP numerically, DAYS in week order.
 *      The hosted widget sorts every filter option alphabetically.
 *   3. Dates and months are DERIVED from the program's real start and end,
 *      not from text an admin typed into an unrelated field.
 *   4. The feed is fetched SERVER-side with retries and a stale fallback, so
 *      one dropped mobile request never blanks the table with "Invalid Site
 *      Id or Api Key". Credentials live once in DS Toolkit settings and never
 *      reach the browser.
 *   5. A Style tab that covers the whole table, the filter bar, the button,
 *      the empty state and the phone card view, with palette-neutral presets
 *      so a new site is two colours away from done.
 *
 * Origin: saltcitysports mu-plugin (2026-09-20), Zendesk #456367, ClickUp
 * 868m3dg4z and 868m3q8p3. Generalised for the fleet in 1.9.143.
 *
 * @class DS_Programs_Module
 */
class DS_Programs_Module extends FLBuilderModule {

	/** True when the feed was unreachable and the last good copy was served. */
	public $feed_stale = false;

	/** Fetch warnings, surfaced to admins only. */
	public $feed_errors = array();

	public function __construct() {
		parent::__construct( array(
			'name'            => __( 'Programs', 'ds-toolkit' ),
			'description'     => __( 'Live tournament, league, camp and clinic listings from LeagueApps as a filterable table you control.', 'ds-toolkit' ),
			'category'        => __( 'LeagueApps', 'ds-toolkit' ),
			'dir'             => DS_TOOLKIT_PATH . 'modules/ds-programs/',
			'url'             => DS_TOOLKIT_URL . 'modules/ds-programs/',
			'partial_refresh' => false,
			'editor_export'   => false,
		) );
	}

	/* ------------------------------------------------------------------
	 * Builder option helpers
	 * ---------------------------------------------------------------- */

	/** Sport picker: learned from the last fetched feed, else just "All". */
	public static function sport_options() {
		$out = array( '' => __( 'All sports', 'ds-toolkit' ) );
		foreach ( DS_Programs_Data::known_sports() as $s ) { $out[ $s ] = $s; }
		return $out;
	}

	/** Season / Level picker: the values LeagueApps has sent on this site. */
	public static function value_options( $field ) {
		$out = array();
		foreach ( DS_Programs_Data::known_values( $field ) as $v ) { $out[ $v ] = $v; }
		return $out ? $out : array( '' => __( '(the list fills in once the programs have loaded on this site)', 'ds-toolkit' ) );
	}

	/** The picked seasons and levels, when "Only some seasons or levels" is on. */
	public static function season_level_scope( $s ) {
		if ( 'yes' !== (string) ( $s->season_level ?? 'no' ) ) {
			return array( array(), array() );
		}
		return array( self::type_list_plain( $s->seasons_pick ?? array() ), self::type_list_plain( $s->levels_pick ?? array() ) );
	}

	/**
	 * Does a row fall in the picked seasons and levels? Compared with LeagueApps' own Season /
	 * Experience Level exactly as the club typed them (a mapping that rewrites the shown "season"
	 * does not change it). A program with the field left empty is in no picked season or level.
	 */
	public static function in_season_level( array $r, array $seasons, array $levels ) {
		if ( $seasons && ! in_array( (string) ( $r['seasonRaw'] ?? $r['season'] ?? '' ), $seasons, true ) ) { return false; }
		if ( $levels && ! in_array( (string) ( $r['levelRaw'] ?? $r['level'] ?? '' ), $levels, true ) ) { return false; }
		return true;
	}

	/** A multi-select value as a clean list of exact strings (case kept, unlike type_list). */
	public static function type_list_plain( $v ) {
		if ( is_string( $v ) ) { $v = explode( ',', $v ); }
		return array_values( array_filter( array_map( function ( $x ) { return trim( (string) $x ); }, (array) $v ), 'strlen' ) );
	}

	/** Configured LeagueApps sites as a multi-select option list. */
	public static function site_options() {
		$out = array();
		foreach ( DS_Programs_Data::configured_sites() as $s ) {
			$out[ $s['site_id'] ] = ( '' !== $s['label'] ? $s['label'] . ' (' . $s['site_id'] . ')' : $s['site_id'] );
		}
		return $out;
	}

	/** Catalogue keys a raw field may be mapped onto (text-like values only). */
	public static function map_target_options() {
		$out = array();
		foreach ( DS_Programs_Data::catalog() as $k => $f ) {
			if ( in_array( $k, array( 'register', 'price', 'spots' ), true ) ) { continue; }
			$out[ $k ] = $f['label'];
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * Data
	 * ---------------------------------------------------------------- */

	/** The rows this instance renders, after its own scope filters, sorted, capped. */
	public function rows() {
		$s = $this->settings;

		$sites = DS_Programs_Data::configured_sites();
		if ( 'pick' === ( $s->sites_mode ?? 'all' ) ) {
			$pick  = array_map( 'strval', (array) ( $s->sites_pick ?? array() ) );
			$sites = array_values( array_filter( $sites, function ( $x ) use ( $pick ) { return in_array( $x['site_id'], $pick, true ); } ) );
		}

		$overrides = array();
		foreach ( (array) ( $s->field_map ?? array() ) as $row ) {
			$row = (array) $row;
			$t = (string) ( $row['map_target'] ?? '' ); $src = (string) ( $row['map_source'] ?? '' );
			if ( '' !== $t && '' !== $src ) { $overrides[ $t ] = $src; }
		}

		$feed = DS_Programs_Data::get( $sites, $overrides, array( 'date' => (string) ( $s->date_format ?? 'numeric' ) ), (string) ( $s->grouping ?? 'children' ) );
		$rows = $feed['programs'];
		$this->feed_stale  = ! empty( $feed['stale'] );
		$this->feed_errors = $feed['errors'];

		$types = self::type_list( $s->program_type ?? array() );
		$mode  = strtoupper( (string) ( $s->program_mode ?? '' ) );
		$state = strtoupper( (string) ( $s->state_filter ?? '' ) );
		$sport = trim( (string) ( $s->sport_filter ?? '' ) );
		if ( '' === $sport ) { $sport = trim( (string) ( $s->sport_text ?? '' ) ); }
		$locs = self::location_list( $s->location_scope ?? '' );
		$hide_sold   = 'yes' === ( $s->hide_sold_out ?? 'no' );
		$hide_closed = 'yes' === ( $s->hide_closed ?? 'no' );
		$hide_canc   = 'yes' === ( $s->hide_canceled ?? 'no' );
		list( $seasons, $levels ) = self::season_level_scope( $s );

		$rows = array_values( array_filter( $rows, function ( $r ) use ( $types, $mode, $state, $sport, $locs, $hide_sold, $hide_closed, $hide_canc, $seasons, $levels ) {
			if ( $types && ! in_array( $r['typeRaw'], $types, true ) ) { return false; }
			if ( $mode && $r['modeRaw'] !== $mode ) { return false; }
			if ( $state && $r['stateRaw'] !== $state ) { return false; }
			if ( '' !== $sport && 0 !== strcasecmp( $r['sport'], $sport ) ) { return false; }
			if ( $locs && ! self::location_matches( (string) ( $r['location'] ?? '' ), $locs ) ) { return false; }
			if ( ! self::in_season_level( $r, $seasons, $levels ) ) { return false; }
			if ( $hide_sold && $r['soldOut'] ) { return false; }
			if ( $hide_canc && ! empty( $r['canceled'] ) ) { return false; }
			if ( $hide_closed && 'CLOSED' === $r['statusRaw'] ) { return false; }
			return true;
		} ) );

		// Programs stay grouped (a tournament's age groups together), groups
		// ordered by date or name, age groups ascending within each group.
		$sort = (string) ( $s->sort_by ?? 'date_asc' );

		// A group's place in the list is the GROUP's own start, taken as the earliest
		// start in it, and every row in that group compares on it. Comparing each
		// row's own startTs across groups is NOT a group-consistent order: a series'
		// later sessions sort past a different program that starts in between, the
		// group is split, and the stranded sub-programs read as if they belonged to
		// the program now above them. Absolute VB, 2026-10-06: a six-session boys
		// series starting Oct 16 kept its first three rows and had the other four
		// pushed below the winter program that starts Oct 24.
		$group_ts = array();
		foreach ( $rows as $r ) {
			$ts = (int) $r['startTs'];
			if ( ! $ts ) { continue; }
			$gk = (string) $r['groupKey'];
			if ( ! isset( $group_ts[ $gk ] ) || $ts < $group_ts[ $gk ] ) { $group_ts[ $gk ] = $ts; }
		}

		usort( $rows, function ( $a, $b ) use ( $sort, $group_ts ) {
			// A main program listed with its sub-programs leads its group.
			if ( ( $a['groupKey'] === $b['groupKey'] ) && ( ! empty( $a['isMaster'] ) !== ! empty( $b['isMaster'] ) ) ) {
				return ! empty( $a['isMaster'] ) ? -1 : 1;
			}
			if ( $a['groupKey'] !== $b['groupKey'] ) {
				$ta = $group_ts[ (string) $a['groupKey'] ] ?? 0;
				$tb = $group_ts[ (string) $b['groupKey'] ] ?? 0;
				if ( 'name' === $sort ) { return strcasecmp( $a['program'], $b['program'] ) ?: ( $ta <=> $tb ); }
				$c = $ta <=> $tb;
				if ( 'date_desc' === $sort ) { $c = -$c; }
				return $c ?: strcasecmp( $a['program'], $b['program'] );
			}
			$ra = DS_Programs_Data::age_rank( $a['ageGroup'] ); $rb = DS_Programs_Data::age_rank( $b['ageGroup'] );
			if ( $ra !== $rb ) { return $ra <=> $rb; }
			// Same age: by date, so a series' sessions for one grade read in
			// order (Sept 25, Sept 26, Oct 4), then by name.
			return ( $a['startTs'] <=> $b['startTs'] ) ?: strcasecmp( $a['ageGroup'], $b['ageGroup'] );
		} );

		$max = (int) ( $s->max_rows ?? 0 );
		if ( $max > 0 ) { $rows = array_slice( $rows, 0, $max ); }

		return $rows;
	}

	/**
	 * Chosen columns in the editor's order.
	 * @return array key => array( label, align, width, nowrap )
	 */
	public function chosen_columns() {
		$cat = DS_Programs_Data::catalog();
		$out = array();
		foreach ( (array) ( $this->settings->columns ?? array() ) as $row ) {
			$row = (array) $row;
			$k   = (string) ( $row['col_key'] ?? '' );
			if ( ! isset( $cat[ $k ] ) || empty( $cat[ $k ]['column'] ) || isset( $out[ $k ] ) ) { continue; }
			$label  = trim( (string) ( $row['col_label'] ?? '' ) );
			$align  = (string) ( $row['col_align'] ?? 'auto' );
			$nowrap = (string) ( $row['col_nowrap'] ?? 'auto' );
			$out[ $k ] = array(
				'label'  => ( '' !== $label ) ? $label : $cat[ $k ]['label'],
				'align'  => in_array( $align, array( 'left', 'center', 'right' ), true ) ? $align : ( $cat[ $k ]['align'] ?? 'left' ), // printed into CSS: options only
				'width'  => self::css_length( (string) ( $row['col_width'] ?? '' ) ),
				'nowrap' => ( 'auto' === $nowrap || '' === $nowrap ) ? ! empty( $cat[ $k ]['nowrap'] ) : ( 'yes' === $nowrap ),
			);
		}
		if ( $out ) { return $out; }
		// Nothing chosen yet: a sensible default table.
		foreach ( array( 'dateRange', 'program', 'ageGroup', 'gender', 'location', 'price', 'spots', 'register' ) as $k ) {
			$out[ $k ] = array( 'label' => $cat[ $k ]['label'], 'align' => $cat[ $k ]['align'] ?? 'left', 'width' => '', 'nowrap' => ! empty( $cat[ $k ]['nowrap'] ) );
		}
		return $out;
	}

	/**
	 * Program types to keep, upper-cased, from every shape the field has had:
	 * the pre-1.9.150 single string ('TOURNAMENT', or '' = all), an array, or
	 * what Beaver Builder's multi-select button group actually saves, which is
	 * one COMMA-JOINED string ('TOURNAMENT,LEAGUE'; its JS does val.join(',')).
	 * Empty = every type.
	 */
	public static function type_list( $v ) {
		if ( is_string( $v ) ) { $v = preg_split( '/\s*,\s*/', trim( $v ) ); }
		$out = array();
		foreach ( (array) $v as $t ) {
			$t = strtoupper( trim( (string) $t ) );
			if ( '' !== $t ) { $out[ $t ] = $t; }
		}
		return array_values( $out );
	}

	/**
	 * Location scope: one entry per line, trimmed, blanks dropped. Not split on
	 * commas: a location name can hold one ("Fort Bragg, NC").
	 * One region's LeagueApps site often holds many bases, so a page for one base
	 * scopes by location, as the hosted widget does with its hidden Location filter.
	 */
	public static function location_list( $v ) {
		$out = array();
		foreach ( preg_split( '/[\r\n]+/', (string) $v ) as $l ) {
			$l = trim( $l );
			if ( '' !== $l ) { $out[] = $l; }
		}
		return array_values( array_unique( $out ) );
	}

	/** True when the location contains any scope entry, case-insensitively. */
	public static function location_matches( $location, array $locs ) {
		if ( '' === trim( $location ) ) { return false; }
		foreach ( $locs as $l ) {
			if ( false !== ( function_exists( 'mb_stripos' ) ? mb_stripos( $location, $l ) : stripos( $location, $l ) ) ) { return true; }
		}
		return false;
	}

	/** "120", "120px", "20%" -> a safe CSS length, else ''. */
	public static function css_length( $v ) {
		$v = trim( $v );
		if ( '' === $v ) { return ''; }
		if ( preg_match( '/^(\d+(?:\.\d+)?)(px|%|em|rem|ch)?$/', $v, $m ) ) { return $m[1] . ( $m[2] ?? 'px' ); }
		return '';
	}

	/**
	 * Chosen filters in the editor's order.
	 * @return array key => array( label, all, multi )
	 */
	public function chosen_filters() {
		if ( 'no' === ( $this->settings->show_filters ?? 'yes' ) ) { return array(); }
		$cat = DS_Programs_Data::catalog();
		$out = array();
		foreach ( (array) ( $this->settings->filters ?? array() ) as $row ) {
			$row = (array) $row;
			$k   = (string) ( $row['filter_key'] ?? '' );
			if ( ! isset( $cat[ $k ] ) || empty( $cat[ $k ]['filter'] ) || isset( $out[ $k ] ) ) { continue; }
			$label = trim( (string) ( $row['filter_label'] ?? '' ) );
			$all   = trim( (string) ( $row['filter_all'] ?? '' ) );
			$out[ $k ] = array(
				'label' => ( '' !== $label ) ? $label : $cat[ $k ]['label'],
				'all'   => ( '' !== $all ) ? $all : ( $cat[ $k ]['all'] ?: sprintf( __( 'All %s', 'ds-toolkit' ), $cat[ $k ]['label'] ) ),
				'multi' => ! empty( $cat[ $k ]['multi'] ),
			);
		}
		return $out;
	}

	/** Options for one filter, ordered the way a person expects. */
	public function filter_values( $key, array $rows ) {
		$cat  = DS_Programs_Data::catalog();
		$sort = $cat[ $key ]['sort'] ?? 'natural';
		$vals = array();
		foreach ( $rows as $r ) {
			$v = trim( (string) ( $r[ $key ] ?? '' ) );
			if ( '' === $v ) { continue; }
			if ( ! empty( $cat[ $key ]['multi'] ) ) {
				foreach ( explode( ',', $v ) as $p ) { $p = trim( $p ); if ( '' !== $p ) { $vals[ $p ] = true; } }
			} else {
				$vals[ $v ] = true;
			}
		}
		$vals = array_keys( $vals );
		switch ( $sort ) {
			case 'month': usort( $vals, function ( $a, $b ) { return DS_Programs_Data::month_rank( $a ) <=> DS_Programs_Data::month_rank( $b ); } ); break;
			case 'day':   usort( $vals, function ( $a, $b ) { return DS_Programs_Data::day_rank( $a ) <=> DS_Programs_Data::day_rank( $b ); } ); break;
			case 'age':   usort( $vals, function ( $a, $b ) { $ra = DS_Programs_Data::age_rank( $a ); $rb = DS_Programs_Data::age_rank( $b ); return ( $ra === $rb ) ? strcasecmp( $a, $b ) : $ra <=> $rb; } ); break;
			default:      natcasesort( $vals ); $vals = array_values( $vals );
		}
		return $vals;
	}

	/* ------------------------------------------------------------------
	 * Style presets
	 * ---------------------------------------------------------------- */

	/**
	 * Palette-neutral presets. Each is a base layer: a Style field the editor
	 * left blank takes the preset's value, a field they set wins. Translucent
	 * greys so every preset reads on a light or a dark section without a
	 * colour being chosen; brand colour is the editor's two-field job.
	 */
	public static function presets() {
		return array(
			'modern'   => array( 'table_bg' => '#ffffff', 'table_border' => 'outer_horizontal', 'table_border_color' => '#e4e7ec', 'table_border_width' => '1', 'table_radius' => '12', 'table_shadow' => 'subtle', 'head_bg' => '#f5f7fa', 'head_color' => '#4a5568', 'row_color' => '#1f2937', 'row_hover' => '#f8fafc', 'cell_pad' => '14', 'select_bg' => '#ffffff', 'select_color' => '#1f2937', 'select_border' => '#d0d5dd', 'select_radius' => '10', 'pager_border' => '#d0d5dd', 'pager_radius' => '10', 'mob_card_bg' => '#ffffff', 'mob_card_radius' => '12', 'mob_card_gap' => '12' ),
			'striped'  => array( 'table_border' => 'horizontal', 'table_border_color' => 'rgba(0,0,0,.10)', 'table_border_width' => '1', 'row_stripe' => 'rgba(0,0,0,.035)', 'head_bg' => 'rgba(0,0,0,.06)' ),
			'bordered' => array( 'table_border' => 'grid', 'table_border_color' => 'rgba(0,0,0,.14)', 'table_border_width' => '1', 'head_bg' => 'rgba(0,0,0,.06)' ),
			'minimal'  => array( 'table_border' => 'horizontal', 'table_border_color' => 'rgba(0,0,0,.08)', 'table_border_width' => '1', 'head_border_width' => '2', 'head_border_color' => 'rgba(0,0,0,.55)', 'cell_pad' => '14' ),
			'cards'    => array( 'table_border' => 'outer_horizontal', 'table_border_color' => 'rgba(0,0,0,.10)', 'table_border_width' => '1', 'table_radius' => '12', 'table_shadow' => 'soft', 'head_bg' => 'rgba(0,0,0,.04)', 'cell_pad' => '14' ),
		);
	}

	/** A style value: the editor's, else the active preset's, else $default. */
	public function style( $key, $default = '' ) {
		$v = $this->settings->{$key} ?? '';
		if ( '' !== $v && null !== $v ) { return $v; }
		$p = (string) ( $this->settings->style_preset ?? 'custom' );
		$presets = self::presets();
		if ( isset( $presets[ $p ][ $key ] ) ) { return $presets[ $p ][ $key ]; }
		return $default;
	}

	/* ------------------------------------------------------------------
	 * Per-site styles
	 * ---------------------------------------------------------------- */

	/**
	 * The "Per-site styles" rows, keyed by LeagueApps site id. One page often pulls several
	 * sites (PLL Play: a tournaments site and a site per local league), and each is its own
	 * brand, so a row can give one site its own accent, row background, button colours and a
	 * placeholder logo. The first row for a site wins; a row without a site is ignored.
	 *
	 * @return array site_id => array( accent, row_bg, btn_bg, btn_color, logo )
	 */
	public function site_styles() {
		if ( null !== $this->site_styles_cache ) { return $this->site_styles_cache; }
		$col = array( 'DS_Module_UI', 'color' );
		$out = array();
		foreach ( (array) ( $this->settings->site_styles ?? array() ) as $row ) {
			$row = (array) $row;
			$id  = preg_replace( '/\D+/', '', (string) ( $row['ss_site'] ?? '' ) );
			if ( '' === $id || isset( $out[ $id ] ) ) { continue; }
			$out[ $id ] = array(
				'accent'    => call_user_func( $col, $row['ss_accent'] ?? '' ),
				'row_bg'    => call_user_func( $col, $row['ss_row_bg'] ?? '' ),
				'btn_bg'    => call_user_func( $col, $row['ss_btn_bg'] ?? '' ),
				'btn_color' => call_user_func( $col, $row['ss_btn_color'] ?? '' ),
				'logo'      => self::photo_url( $row['ss_logo'] ?? '', $row['ss_logo_src'] ?? '' ),
			);
		}
		return $this->site_styles_cache = $out;
	}

	/** @var array|null */
	private $site_styles_cache = null;

	/** A Beaver photo field as a URL: the attachment's medium size, else the stored src. */
	public static function photo_url( $id, $src ) {
		$id = (int) $id;
		if ( $id && function_exists( 'wp_get_attachment_image_url' ) ) {
			$u = wp_get_attachment_image_url( $id, 'medium' );
			if ( $u ) { return $u; }
		}
		return esc_url_raw( (string) $src );
	}

	/**
	 * The logo a program shows in the List layout, and where it came from:
	 * the program's own LeagueApps logo (or its main program's), else the placeholder set for
	 * its site under Per-site styles, else the module's default placeholder.
	 *
	 * @return array( url, 'feed'|'placeholder' ) or array( '', '' ) for no logo
	 */
	public function logo_for( array $r ) {
		if ( '' !== (string) ( $r['logo'] ?? '' ) ) { return array( $r['logo'], 'feed' ); }
		$ss = $this->site_styles();
		$id = (string) ( $r['siteId'] ?? '' );
		if ( isset( $ss[ $id ] ) && '' !== $ss[ $id ]['logo'] ) { return array( $ss[ $id ]['logo'], 'placeholder' ); }
		$u = self::photo_url( $this->settings->list_logo_default ?? '', $this->settings->list_logo_default_src ?? '' );
		return '' !== $u ? array( $u, 'placeholder' ) : array( '', '' );
	}

	/**
	 * Readable text for a solid colour: near-white or near-black, whichever has the higher
	 * contrast. '' when the colour cannot be read (a var() with no stored value).
	 */
	public static function text_on( $bg ) {
		require_once DS_TOOLKIT_PATH . 'includes/class-ds-la-colors.php';
		$rgb = DS_LA_Colors::rgb( $bg );
		if ( ! $rgb ) { return ''; }
		$w = DS_LA_Colors::ratio( array( 255, 255, 255 ), $rgb );
		$d = DS_LA_Colors::ratio( array( 17, 17, 17 ), $rgb );
		return ( $w >= $d ) ? '#ffffff' : '#111111';
	}

	/** A solid colour 12% darker, for a hover state; '' when it cannot be read. */
	public static function darker( $c ) {
		require_once DS_TOOLKIT_PATH . 'includes/class-ds-la-colors.php';
		$rgb = DS_LA_Colors::rgb( $c );
		return $rgb ? DS_LA_Colors::hex( DS_LA_Colors::mix( $rgb, array( 0, 0, 0 ), 0.88 ) ) : '';
	}

	/* ------------------------------------------------------------------
	 * Render
	 * ---------------------------------------------------------------- */

	public function render_programs() {
		include DS_TOOLKIT_PATH . 'modules/ds-programs/includes/table.php';
	}
}

/* ---------------------------------------------------------------------
 * Repeater sub-forms
 * ------------------------------------------------------------------ */

FLBuilder::register_settings_form( 'ds_programs_column_form', array(
	'title' => __( 'Column', 'ds-toolkit' ),
	'tabs'  => array(
		'general' => array(
			'title'    => __( 'Column', 'ds-toolkit' ),
			'sections' => array(
				'general' => array(
					'title'  => '',
					'fields' => array(
						'col_key'    => array( 'type' => 'select', 'label' => __( 'Show', 'ds-toolkit' ), 'default' => 'program', 'options' => DS_Programs_Data::column_options() ),
						'col_label'  => array( 'type' => 'text', 'label' => __( 'Header text', 'ds-toolkit' ), 'help' => __( 'Blank = the default name. Use it to relabel a field the way your partner talks about it ("Season" as "Games").', 'ds-toolkit' ) ),
						'col_align'  => array( 'type' => 'select', 'label' => __( 'Alignment', 'ds-toolkit' ), 'default' => 'auto', 'options' => array( 'auto' => __( 'Auto (prices right, counts centred)', 'ds-toolkit' ), 'left' => __( 'Left', 'ds-toolkit' ), 'center' => __( 'Centre', 'ds-toolkit' ), 'right' => __( 'Right', 'ds-toolkit' ) ) ),
						'col_width'  => array( 'type' => 'text', 'label' => __( 'Width', 'ds-toolkit' ), 'size' => 8, 'placeholder' => 'auto', 'help' => __( 'e.g. 140px or 18%. Blank lets the browser size it.', 'ds-toolkit' ) ),
						'col_nowrap' => array( 'type' => 'select', 'label' => __( 'Wrap text', 'ds-toolkit' ), 'default' => 'auto', 'options' => array( 'auto' => __( 'Auto', 'ds-toolkit' ), 'no' => __( 'Wrap', 'ds-toolkit' ), 'yes' => __( 'Keep on one line', 'ds-toolkit' ) ) ),
					),
				),
			),
		),
	),
) );

FLBuilder::register_settings_form( 'ds_programs_filter_form', array(
	'title' => __( 'Filter', 'ds-toolkit' ),
	'tabs'  => array(
		'general' => array(
			'title'    => __( 'Filter', 'ds-toolkit' ),
			'sections' => array(
				'general' => array(
					'title'  => '',
					'fields' => array(
						'filter_key'   => array( 'type' => 'select', 'label' => __( 'Filter by', 'ds-toolkit' ), 'default' => 'month', 'options' => DS_Programs_Data::filter_options() ),
						'filter_label' => array( 'type' => 'text', 'label' => __( 'Label', 'ds-toolkit' ), 'help' => __( 'Blank = the default name.', 'ds-toolkit' ) ),
						'filter_all'   => array( 'type' => 'text', 'label' => __( '"Show all" option text', 'ds-toolkit' ), 'help' => __( 'Blank = e.g. "All Months".', 'ds-toolkit' ) ),
					),
				),
			),
		),
	),
) );

FLBuilder::register_settings_form( 'ds_programs_map_form', array(
	'title' => __( 'Field mapping', 'ds-toolkit' ),
	'tabs'  => array(
		'general' => array(
			'title'    => __( 'Mapping', 'ds-toolkit' ),
			'sections' => array(
				'general' => array(
					'title'  => '',
					'fields' => array(
						'map_target' => array( 'type' => 'select', 'label' => __( 'Fill this value', 'ds-toolkit' ), 'default' => 'dateRange', 'options' => DS_Programs_Module::map_target_options() ),
						'map_source' => array( 'type' => 'select', 'label' => __( 'from this LeagueApps field', 'ds-toolkit' ), 'default' => 'sponsor', 'options' => DS_Programs_Data::raw_field_options() ),
					),
				),
			),
		),
	),
) );

FLBuilder::register_settings_form( 'ds_programs_site_form', array(
	'title' => __( 'Site style', 'ds-toolkit' ),
	'tabs'  => array(
		'general' => array(
			'title'    => __( 'Site style', 'ds-toolkit' ),
			'sections' => array(
				'general' => array(
					'title'  => '',
					'fields' => array(
						'ss_site'      => array( 'type' => 'select', 'label' => __( 'LeagueApps site', 'ds-toolkit' ), 'default' => '', 'options' => array( '' => __( 'Pick a site', 'ds-toolkit' ) ) + DS_Programs_Module::site_options(), 'help' => __( 'Sites come from Settings > DS Toolkit > LeagueApps.', 'ds-toolkit' ) ),
						'ss_logo'      => array( 'type' => 'photo', 'label' => __( 'Placeholder logo', 'ds-toolkit' ), 'show_remove' => true, 'help' => __( 'Shown in the List layout for this site\'s programs that have no logo in LeagueApps. A program\'s own LeagueApps logo always wins.', 'ds-toolkit' ) ),
						'ss_accent'    => array( 'type' => 'color', 'label' => __( 'Accent', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'connections' => array( 'color' ), 'help' => __( 'List layout: the left edge and the placeholder logo\'s tile. Blank = this site\'s button colour.', 'ds-toolkit' ) ),
						'ss_row_bg'    => array( 'type' => 'color', 'label' => __( 'Row background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'show_alpha' => true, 'connections' => array( 'color' ), 'help' => __( 'The row, card or list item background for this site\'s programs. Blank = the module\'s own.', 'ds-toolkit' ) ),
						'ss_btn_bg'    => array( 'type' => 'color', 'label' => __( 'Register button background', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'connections' => array( 'color' ) ),
						'ss_btn_color' => array( 'type' => 'color', 'label' => __( 'Register button text', 'ds-toolkit' ), 'default' => '', 'show_reset' => true, 'connections' => array( 'color' ), 'help' => __( 'Blank = white or near-black, whichever reads on the background.', 'ds-toolkit' ) ),
					),
				),
			),
		),
	),
) );

/* ---------------------------------------------------------------------
 * The module form
 * ------------------------------------------------------------------ */

$ds_prg_sites   = DS_Programs_Module::site_options();
$ds_prg_bp      = DS_Module_UI::breakpoints();
$ds_prg_colour  = function ( $label, $extra = array() ) {
	return array_merge( array( 'type' => 'color', 'label' => $label, 'default' => '', 'show_reset' => true, 'connections' => array( 'color' ) ), $extra );
};
$ds_prg_unit = function ( $label, $min, $max, $extra = array() ) {
	return array_merge( array( 'type' => 'unit', 'label' => $label, 'default' => '', 'description' => 'px', 'slider' => array( 'min' => $min, 'max' => $max, 'step' => 1 ) ), $extra );
};

FLBuilder::register_module( 'DS_Programs_Module', array(

	'content' => array(
		'title'    => __( 'Programs', 'ds-toolkit' ),
		'sections' => array(
			'account' => array(
				'title'  => __( 'LeagueApps account', 'ds-toolkit' ),
				'fields' => array(
					'sites_mode' => array(
						'type'    => 'select',
						'label'   => __( 'Pull programs from', 'ds-toolkit' ),
						'default' => 'all',
						'options' => array( 'all' => __( 'Every configured site', 'ds-toolkit' ), 'pick' => __( 'Only the sites I pick', 'ds-toolkit' ) ),
						'toggle'  => array( 'pick' => array( 'fields' => array( 'sites_pick' ) ) ),
						'help'    => $ds_prg_sites
							? sprintf( __( 'Configured: %s. Add or change sites under Settings > DS Toolkit > LeagueApps.', 'ds-toolkit' ), implode( ', ', $ds_prg_sites ) )
							: __( 'No LeagueApps site is configured yet. Add the site ID and public API key under Settings > DS Toolkit > LeagueApps, then reload the builder.', 'ds-toolkit' ),
					),
					'sites_pick' => array(
						'type'         => 'select',
						'label'        => __( 'Sites', 'ds-toolkit' ),
						'multi-select' => true,
						'options'      => $ds_prg_sites ?: array( '' => __( '(none configured)', 'ds-toolkit' ) ),
					),
				),
			),
			'scope' => array(
				'title'       => __( 'What to show', 'ds-toolkit' ),
				'description' => __( 'Listed: every program LeagueApps marks Public and not deleted, whose season is upcoming or in progress (past seasons are never listed). Sold-out and closed-registration programs are listed unless hidden below.', 'ds-toolkit' ),
				'fields'      => array(
					'grouping' => array(
						'type'    => 'select',
						'label'   => __( 'Programs with sub-programs', 'ds-toolkit' ),
						'default' => 'children',
						'options' => array(
							'children' => __( 'List the sub-programs only (one row per age group or session)', 'ds-toolkit' ),
							'master'   => __( 'List the main program only', 'ds-toolkit' ),
							'both'     => __( 'List the main program, then its sub-programs', 'ds-toolkit' ),
						),
						'help'    => __( 'LeagueApps nests a tournament\'s age groups, or a clinic\'s sessions, under one main program. When the registration is on the main program (a clinic series where the session is picked at checkout, a camp whose sub-programs are optional add-ons), list the main program. Its row takes its own dates and link, and its price is the range of its sub-programs when LeagueApps left the main fee blank.', 'ds-toolkit' ),
					),
					'program_type' => array(
						'type'         => 'button-group',
						'label'        => __( 'Program types', 'ds-toolkit' ),
						'multi-select' => true,
						'default'      => array(),
						'options'      => array( 'TOURNAMENT' => __( 'Tournaments', 'ds-toolkit' ), 'LEAGUE' => __( 'Leagues', 'ds-toolkit' ), 'CAMP' => __( 'Camps', 'ds-toolkit' ), 'CLINIC' => __( 'Clinics', 'ds-toolkit' ), 'CLASS' => __( 'Classes', 'ds-toolkit' ), 'EVENT' => __( 'Events', 'ds-toolkit' ), 'CLUBTEAM' => __( 'Club teams', 'ds-toolkit' ) ),
						'help'         => __( 'Tick one or more. Nothing ticked shows every type.', 'ds-toolkit' ),
					),
					'program_mode' => array(
						'type'    => 'select',
						'label'   => __( 'Audience', 'ds-toolkit' ),
						'default' => '',
						'options' => array( '' => __( 'Youth and adult', 'ds-toolkit' ), 'YOUTH' => __( 'Youth only', 'ds-toolkit' ), 'ADULT' => __( 'Adult only', 'ds-toolkit' ) ),
					),
					'sport_filter' => array(
						'type'    => 'select',
						'label'   => __( 'Sport', 'ds-toolkit' ),
						'default' => '',
						'options' => DS_Programs_Module::sport_options(),
						'help'    => __( 'The list fills in after the first render on this site. Blank = all sports.', 'ds-toolkit' ),
					),
					'sport_text' => array(
						'type'        => 'text',
						'label'       => __( 'Sport (typed)', 'ds-toolkit' ),
						'placeholder' => 'Soccer (Outdoor)',
						'help'        => __( 'Used only when the Sport list above is blank or empty. Type it exactly as LeagueApps names it.', 'ds-toolkit' ),
					),
					'location_scope' => array(
						'type'        => 'textarea',
						'label'       => __( 'Locations', 'ds-toolkit' ),
						'rows'        => 3,
						'placeholder' => "Fort Bragg\nHoneycutt Park",
						'help'        => __( 'One per line. Lists only programs whose LeagueApps location contains one of these words, e.g. "Fort Bragg" keeps every Fort Bragg field and drops the rest of the region. A field renamed in LeagueApps (Fort Liberty) needs its own line. Not case sensitive. Blank = every location.', 'ds-toolkit' ),
					),
					'season_level' => array(
						'type'    => 'button-group',
						'label'   => __( 'Only some seasons or levels', 'ds-toolkit' ),
						'default' => 'no',
						'options' => array( 'no' => __( 'No', 'ds-toolkit' ), 'yes' => __( 'Yes', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'seasons_pick', 'levels_pick' ) ) ),
						'help'    => __( 'Uses the Season and Experience Level the club set on each program in LeagueApps, for example one location\'s page or a girls-only page.', 'ds-toolkit' ),
					),
					'seasons_pick' => array(
						'type'         => 'select',
						'label'        => __( 'Seasons', 'ds-toolkit' ),
						'multi-select' => true,
						'options'      => DS_Programs_Module::value_options( 'season' ),
						'help'         => __( 'Click a season to tick or untick it. Nothing ticked = every season (Clear under the list).', 'ds-toolkit' ),
					),
					'levels_pick' => array(
						'type'         => 'select',
						'label'        => __( 'Levels', 'ds-toolkit' ),
						'multi-select' => true,
						'options'      => DS_Programs_Module::value_options( 'level' ),
						'help'         => __( 'Click a level to tick or untick it. Nothing ticked = every level (Clear under the list).', 'ds-toolkit' ),
					),
					'state_filter' => array(
						'type'    => 'select',
						'label'   => __( 'Season status', 'ds-toolkit' ),
						'default' => '',
						'options' => array( '' => __( 'Upcoming and in season', 'ds-toolkit' ), 'UPCOMING' => __( 'Upcoming only', 'ds-toolkit' ), 'LIVE' => __( 'In season only', 'ds-toolkit' ) ),
					),
					'hide_sold_out' => array( 'type' => 'select', 'label' => __( 'Hide sold-out programs', 'ds-toolkit' ), 'default' => 'no', 'options' => array( 'no' => __( 'No, show them', 'ds-toolkit' ), 'yes' => __( 'Yes', 'ds-toolkit' ) ) ),
					'hide_canceled' => array( 'type' => 'select', 'label' => __( 'Hide cancelled programs', 'ds-toolkit' ), 'default' => 'no', 'options' => array( 'no' => __( 'No, show them marked cancelled', 'ds-toolkit' ), 'yes' => __( 'Yes', 'ds-toolkit' ) ), 'help' => __( 'Cancelled programs never show a register button either way.', 'ds-toolkit' ) ),
					'hide_closed'   => array( 'type' => 'select', 'label' => __( 'Hide programs whose registration has closed', 'ds-toolkit' ), 'default' => 'no', 'options' => array( 'no' => __( 'No, show them', 'ds-toolkit' ), 'yes' => __( 'Yes', 'ds-toolkit' ) ) ),
				),
			),
			'list' => array(
				'title'  => __( 'List', 'ds-toolkit' ),
				'fields' => array(
					'sort_by'       => array( 'type' => 'select', 'label' => __( 'Order', 'ds-toolkit' ), 'default' => 'date_asc', 'options' => array( 'date_asc' => __( 'Soonest first', 'ds-toolkit' ), 'date_desc' => __( 'Latest first', 'ds-toolkit' ), 'name' => __( 'Program name A to Z', 'ds-toolkit' ) ), 'help' => __( 'Age groups always sort youngest to oldest within a program.', 'ds-toolkit' ) ),
					'max_rows'      => array( 'type' => 'unit', 'label' => __( 'Maximum rows', 'ds-toolkit' ), 'default' => '', 'placeholder' => __( 'all', 'ds-toolkit' ), 'slider' => array( 'min' => 0, 'max' => 200, 'step' => 5 ) ),
					'date_format'   => array( 'type' => 'select', 'label' => __( 'Date format', 'ds-toolkit' ), 'default' => 'numeric', 'options' => array( 'numeric' => '09/19/2026-09/20/2026', 'short' => 'Sep 19-20, 2026', 'long' => 'September 19-20, 2026' ) ),
					'link_program'  => array( 'type' => 'select', 'label' => __( 'Link the program name to its LeagueApps page', 'ds-toolkit' ), 'default' => 'no', 'options' => array( 'no' => __( 'No', 'ds-toolkit' ), 'yes' => __( 'Yes', 'ds-toolkit' ) ) ),
					'empty_text'    => array( 'type' => 'text', 'label' => __( 'Message when nothing is listed', 'ds-toolkit' ), 'default' => __( 'No programs are open right now. Please check back soon.', 'ds-toolkit' ) ),
					'none_text'     => array( 'type' => 'text', 'label' => __( 'Message when no row matches the filters', 'ds-toolkit' ), 'default' => __( 'No programs match those filters.', 'ds-toolkit' ) ),
				),
			),
			'mapping' => array(
				'title'       => __( 'Field mapping (advanced)', 'ds-toolkit' ),
				'collapsed'   => true,
				'description' => __( 'Only for a partner whose admins type meaning into an unrelated LeagueApps field, e.g. event dates typed into Sponsor. Dates, months and days are otherwise read from the program itself.', 'ds-toolkit' ),
				'fields'      => array(
					'field_map' => array(
						'type'         => 'form',
						'form'         => 'ds_programs_map_form',
						'preview_text' => 'map_target',
						'multiple'     => true,
						'label'        => __( 'Mapping', 'ds-toolkit' ),
						'default'      => array(),
					),
				),
			),
		),
	),

	'columns_tab' => array(
		'title'    => __( 'Columns', 'ds-toolkit' ),
		'sections' => array(
			'columns_sec' => array(
				'title'       => __( 'Columns', 'ds-toolkit' ),
				'description' => __( 'Drag to reorder. Each row is one column; the header text, alignment and width are per column.', 'ds-toolkit' ),
				'fields'      => array(
					'columns' => array(
						'type'         => 'form',
						'form'         => 'ds_programs_column_form',
						'preview_text' => 'col_key',
						'multiple'     => true,
						'label'        => __( 'Column', 'ds-toolkit' ),
						'default'      => array(
							array( 'col_key' => 'dateRange' ),
							array( 'col_key' => 'program' ),
							array( 'col_key' => 'ageGroup' ),
							array( 'col_key' => 'gender' ),
							array( 'col_key' => 'location' ),
							array( 'col_key' => 'price' ),
							array( 'col_key' => 'spots' ),
							array( 'col_key' => 'register' ),
						),
					),
				),
			),
		),
	),

	'filters_tab' => array(
		'title'    => __( 'Filters', 'ds-toolkit' ),
		'sections' => array(
			'filters_sec' => array(
				'title'       => __( 'Filter bar', 'ds-toolkit' ),
				'description' => __( 'Drag to reorder. A filter with fewer than two values in the current listing hides itself.', 'ds-toolkit' ),
				'fields'      => array(
					'show_filters' => array(
						'type'    => 'select',
						'label'   => __( 'Show filters', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'filters' ) ) ),
						'help'    => __( 'Switch the filters off here rather than deleting them: they are kept for when you switch back, and the Add button needs at least one filter to copy. With filters, search, count and sorting all off, the bar above the list is not shown at all.', 'ds-toolkit' ),
					),
					'filters' => array(
						'type'         => 'form',
						'form'         => 'ds_programs_filter_form',
						'preview_text' => 'filter_key',
						'multiple'     => true,
						'label'        => __( 'Filter', 'ds-toolkit' ),
						'default'      => array(
							array( 'filter_key' => 'month' ),
							array( 'filter_key' => 'sport' ),
							array( 'filter_key' => 'ageGroup' ),
							array( 'filter_key' => 'gender' ),
							array( 'filter_key' => 'location' ),
						),
					),
					'show_count'     => array( 'type' => 'select', 'label' => __( 'Show result count', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'toggle' => array( 'yes' => array( 'fields' => array( 'count_singular', 'count_plural' ) ) ) ),
					'count_singular' => array( 'type' => 'text', 'label' => __( 'Count word (one)', 'ds-toolkit' ), 'default' => __( 'program', 'ds-toolkit' ), 'size' => 12 ),
					'count_plural'   => array( 'type' => 'text', 'label' => __( 'Count word (many)', 'ds-toolkit' ), 'default' => __( 'programs', 'ds-toolkit' ), 'size' => 12 ),
					'show_clear'     => array( 'type' => 'select', 'label' => __( 'Show "Clear filters"', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'toggle' => array( 'yes' => array( 'fields' => array( 'clear_text' ) ) ) ),
					'clear_text'     => array( 'type' => 'text', 'label' => __( 'Clear text', 'ds-toolkit' ), 'default' => __( 'Clear filters', 'ds-toolkit' ) ),
				),
			),
			'search_sec' => array(
				'title'  => __( 'Keyword search', 'ds-toolkit' ),
				'fields' => array(
					'show_search'        => array( 'type' => 'select', 'label' => __( 'Show a search box', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'toggle' => array( 'yes' => array( 'fields' => array( 'search_label', 'search_placeholder' ) ) ), 'help' => __( 'Matches any word against every visible column (program, age group, location, sponsor…). Combines with the filters.', 'ds-toolkit' ) ),
					'search_label'       => array( 'type' => 'text', 'label' => __( 'Label', 'ds-toolkit' ), 'default' => __( 'Search', 'ds-toolkit' ) ),
					'search_placeholder' => array( 'type' => 'text', 'label' => __( 'Placeholder', 'ds-toolkit' ), 'default' => __( 'Search programs', 'ds-toolkit' ) ),
				),
			),
			'sort_sec' => array(
				'title'  => __( 'Sorting', 'ds-toolkit' ),
				'fields' => array(
					'sortable' => array( 'type' => 'select', 'label' => __( 'Let visitors sort by column', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) ), 'help' => __( 'Click a column heading: ascending, descending, then back to the default order. On phones, where the headings are hidden, a "Sort by" dropdown appears in the bar instead. Dates, prices, spots, ages and months sort as numbers.', 'ds-toolkit' ) ),
				),
			),
			'pager_sec' => array(
				'title'  => __( 'Pagination', 'ds-toolkit' ),
				'fields' => array(
					'page_size'  => array( 'type' => 'unit', 'label' => __( 'Rows per page', 'ds-toolkit' ), 'default' => '', 'placeholder' => __( 'all', 'ds-toolkit' ), 'slider' => array( 'min' => 0, 'max' => 100, 'step' => 5 ), 'help' => __( 'Blank or 0 shows every row on one page. Filters and search apply across all pages.', 'ds-toolkit' ) ),
					'pager_prev' => array( 'type' => 'text', 'label' => __( 'Previous label', 'ds-toolkit' ), 'default' => __( 'Previous', 'ds-toolkit' ), 'size' => 12 ),
					'pager_next' => array( 'type' => 'text', 'label' => __( 'Next label', 'ds-toolkit' ), 'default' => __( 'Next', 'ds-toolkit' ), 'size' => 12 ),
				),
			),
		),
	),

	'style' => array(
		'title'    => __( 'Style', 'ds-toolkit' ),
		'sections' => array(
			'layout_sec' => array(
				'title'  => __( 'Layout', 'ds-toolkit' ),
				'fields' => array(
					'layout' => array(
						'type'    => 'select',
						'label'   => __( 'Show programs as', 'ds-toolkit' ),
						'default' => 'table',
						'options' => array( 'table' => __( 'Table (one row per program)', 'ds-toolkit' ), 'cards' => __( 'Cards (a grid, one card per program)', 'ds-toolkit' ), 'list' => __( 'List (one wide card per program, with logo)', 'ds-toolkit' ) ),
						'toggle'  => array(
							'table' => array( 'sections' => array( 'preset_sec', 'table_sec', 'head_sec', 'row_sec' ) ),
							'cards' => array( 'sections' => array( 'card_sec', 'card_text_sec' ) ),
							'list'  => array( 'sections' => array( 'list_sec', 'list_text_sec' ) ),
						),
						'help'    => __( 'Same programs, filters, search, sort and paging in every layout. Cards and List use the columns you chose: the program name is the title, location and dates sit under it with icons, price and the button sit at the end. Cards show the rest as labelled lines, List as chips.', 'ds-toolkit' ),
					),
				),
			),
			'list_sec' => array(
				'title'  => __( 'List', 'ds-toolkit' ),
				'fields' => array(
					'list_logo'         => array( 'type' => 'select', 'label' => __( 'Logo', 'ds-toolkit' ), 'default' => 'yes', 'options' => array( 'yes' => __( 'Show', 'ds-toolkit' ), 'no' => __( 'Hide', 'ds-toolkit' ) ), 'toggle' => array( 'yes' => array( 'fields' => array( 'list_logo_default', 'list_logo_size' ) ) ), 'help' => __( 'The program\'s own logo from LeagueApps, else its site\'s placeholder under Per-site styles, else the default below. With none of those the tile is left out.', 'ds-toolkit' ) ),
					'list_logo_default' => array( 'type' => 'photo', 'label' => __( 'Default placeholder logo', 'ds-toolkit' ), 'show_remove' => true, 'help' => __( 'For sites without their own placeholder. A transparent PNG or SVG reads best on the accent tile.', 'ds-toolkit' ) ),
					'list_logo_size'    => $ds_prg_unit( __( 'Logo tile size', 'ds-toolkit' ), 40, 120, array( 'responsive' => true, 'placeholder' => '64' ) ),
					'list_accent'       => $ds_prg_colour( __( 'Accent', 'ds-toolkit' ), array( 'help' => __( 'The left edge and the placeholder tile. Blank = the Register button colour. A site\'s own accent under Per-site styles wins.', 'ds-toolkit' ) ) ),
					'list_edge'         => $ds_prg_unit( __( 'Accent edge width', 'ds-toolkit' ), 0, 12, array( 'placeholder' => '4' ) ),
					'list_bg'           => $ds_prg_colour( __( 'Item background', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'list_border'       => $ds_prg_colour( __( 'Item border', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'list_radius'       => $ds_prg_unit( __( 'Corner radius', 'ds-toolkit' ), 0, 40, array( 'placeholder' => '16' ) ),
					'list_shadow'       => array( 'type' => 'select', 'label' => __( 'Shadow', 'ds-toolkit' ), 'default' => 'none', 'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'soft' => __( 'Soft', 'ds-toolkit' ), 'lift' => __( 'Soft, lifts on hover', 'ds-toolkit' ) ) ),
					'list_pad'          => $ds_prg_unit( __( 'Item padding', 'ds-toolkit' ), 8, 48, array( 'responsive' => true, 'placeholder' => '20' ) ),
					'list_gap'          => $ds_prg_unit( __( 'Gap between items', 'ds-toolkit' ), 0, 48, array( 'placeholder' => '12' ) ),
				),
			),
			'list_text_sec' => array(
				'title'  => __( 'List text', 'ds-toolkit' ),
				'fields' => array(
					'list_title_tag'   => array( 'type' => 'select', 'label' => __( 'Title tag', 'ds-toolkit' ), 'default' => 'h3', 'options' => array( 'h2' => 'h2', 'h3' => 'h3', 'h4' => 'h4', 'p' => 'p' ) ),
					'list_title_typo'  => array( 'type' => 'typography', 'label' => __( 'Title typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-programs-item-title' ) ),
					'list_title_case'  => array( 'type' => 'select', 'label' => __( 'Title case', 'ds-toolkit' ), 'default' => 'upper', 'options' => array( 'upper' => __( 'UPPERCASE', 'ds-toolkit' ), 'none' => __( 'As written', 'ds-toolkit' ) ) ),
					'list_title_color' => $ds_prg_colour( __( 'Title colour', 'ds-toolkit' ) ),
					'list_meta_color'  => $ds_prg_colour( __( 'Location, date and sub-program colour', 'ds-toolkit' ) ),
					'list_chip_bg'     => $ds_prg_colour( __( 'Chip background', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'list_chip_color'  => $ds_prg_colour( __( 'Chip text', 'ds-toolkit' ) ),
					'list_chip_first'  => array( 'type' => 'select', 'label' => __( 'First chip', 'ds-toolkit' ), 'default' => 'dark', 'options' => array( 'dark' => __( 'Dark, accent text', 'ds-toolkit' ), 'same' => __( 'Same as the others', 'ds-toolkit' ) ) ),
					'list_price_color' => $ds_prg_colour( __( 'Price colour', 'ds-toolkit' ) ),
				),
			),
			'site_sec' => array(
				'title'       => __( 'Per-site styles', 'ds-toolkit' ),
				'description' => __( 'For a page that lists programs from more than one LeagueApps site: give each site its own Register button colours, row background, accent and placeholder logo. Works in every layout; accent and logo are List-only.', 'ds-toolkit' ),
				'fields'      => array(
					'site_styles' => array(
						'type'         => 'form',
						'form'         => 'ds_programs_site_form',
						'preview_text' => 'ss_site',
						'multiple'     => true,
						'label'        => __( 'Site', 'ds-toolkit' ),
						'default'      => array(),
					),
				),
			),
			'card_sec' => array(
				'title'  => __( 'Cards', 'ds-toolkit' ),
				'fields' => array(
					'card_cols'     => $ds_prg_unit( __( 'Cards per row', 'ds-toolkit' ), 1, 4, array( 'default' => '3', 'description' => '', 'responsive' => true, 'help' => __( 'Phones fall to one per row unless you set the small-screen value.', 'ds-toolkit' ) ) ),
					'card_gap'      => $ds_prg_unit( __( 'Gap between cards', 'ds-toolkit' ), 0, 60, array( 'responsive' => true ) ),
					'card_bg'       => $ds_prg_colour( __( 'Card background', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'card_border'   => $ds_prg_colour( __( 'Card border', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'card_radius'   => $ds_prg_unit( __( 'Corner radius', 'ds-toolkit' ), 0, 40 ),
					'card_shadow'   => array( 'type' => 'select', 'label' => __( 'Shadow', 'ds-toolkit' ), 'default' => 'soft', 'options' => array( 'none' => __( 'None', 'ds-toolkit' ), 'soft' => __( 'Soft', 'ds-toolkit' ), 'medium' => __( 'Medium', 'ds-toolkit' ), 'lift' => __( 'Soft, lifts on hover', 'ds-toolkit' ) ) ),
					'card_pad'      => $ds_prg_unit( __( 'Card padding', 'ds-toolkit' ), 8, 48, array( 'responsive' => true, 'help' => __( 'Inside the card. The coloured band stretches edge to edge whatever the padding.', 'ds-toolkit' ) ) ),
					'card_head'     => array( 'type' => 'select', 'label' => __( 'Title area', 'ds-toolkit' ), 'default' => 'band', 'options' => array( 'plain' => __( 'Plain', 'ds-toolkit' ), 'band' => __( 'Coloured band', 'ds-toolkit' ), 'rule' => __( 'Underline', 'ds-toolkit' ) ), 'help' => __( 'The band and underline use the Header row colours from the table style, so a site keeps one look across both layouts.', 'ds-toolkit' ) ),
					'card_head_bg'  => $ds_prg_colour( __( 'Band background', 'ds-toolkit' ), array( 'show_alpha' => true, 'help' => __( 'Blank = the table Header row background, then the site accent.', 'ds-toolkit' ) ) ),
					'card_head_color' => $ds_prg_colour( __( 'Band text', 'ds-toolkit' ) ),
					'card_badge_bg' => $ds_prg_colour( __( 'Age badge background', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'card_badge_color' => $ds_prg_colour( __( 'Age badge text', 'ds-toolkit' ) ),
				),
			),
			'card_text_sec' => array(
				'title'  => __( 'Card text', 'ds-toolkit' ),
				'fields' => array(
					'card_title_tag'   => array( 'type' => 'select', 'label' => __( 'Title tag', 'ds-toolkit' ), 'default' => 'h3', 'options' => array( 'h2' => 'h2', 'h3' => 'h3', 'h4' => 'h4', 'p' => 'p' ) ),
					'card_title_typo'  => array( 'type' => 'typography', 'label' => __( 'Title typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-programs-card-title' ) ),
					'card_title_color' => $ds_prg_colour( __( 'Title colour', 'ds-toolkit' ), array( 'help' => __( 'Only used with a Plain or Underline title area; the band uses Band text.', 'ds-toolkit' ) ) ),
					'card_label_color' => $ds_prg_colour( __( 'Field label colour', 'ds-toolkit' ) ),
					'card_label_size'  => $ds_prg_unit( __( 'Field label size', 'ds-toolkit' ), 9, 16 ),
					'card_value_color' => $ds_prg_colour( __( 'Field value colour', 'ds-toolkit' ) ),
					'card_value_typo'  => array( 'type' => 'typography', 'label' => __( 'Field value typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-programs-card-body dd' ) ),
					'card_price_size'  => $ds_prg_unit( __( 'Price size', 'ds-toolkit' ), 12, 40 ),
					'card_price_color' => $ds_prg_colour( __( 'Price colour', 'ds-toolkit' ) ),
				),
			),
			'preset_sec' => array(
				'title'  => __( 'Preset', 'ds-toolkit' ),
				'fields' => array(
					'style_preset' => array(
						'type'    => 'select',
						'label'   => __( 'Table preset', 'ds-toolkit' ),
						'default' => 'modern',
						'options' => array(
							'modern'   => __( 'Modern (white card, soft header)', 'ds-toolkit' ),
							'striped'  => __( 'Striped rows', 'ds-toolkit' ),
							'bordered' => __( 'Bordered grid', 'ds-toolkit' ),
							'minimal'  => __( 'Minimal lines', 'ds-toolkit' ),
							'cards'    => __( 'Framed (rounded, shadow)', 'ds-toolkit' ),
							'custom'   => __( 'None (only what I set below)', 'ds-toolkit' ),
						),
						'help'    => __( 'A palette-neutral base for the TABLE (separate from Layout above). Any field you set below overrides it; a blank field takes the preset\'s value, so use Background > None to remove the header tint entirely.', 'ds-toolkit' ),
					),
				),
			),
			'table_sec' => array(
				'title'  => __( 'Table', 'ds-toolkit' ),
				'fields' => array(
					'table_bg'           => $ds_prg_colour( __( 'Table background', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'table_border'       => array( 'type' => 'select', 'label' => __( 'Borders', 'ds-toolkit' ), 'default' => '', 'options' => array( '' => __( 'Preset default', 'ds-toolkit' ), 'none' => __( 'None', 'ds-toolkit' ), 'horizontal' => __( 'Row lines', 'ds-toolkit' ), 'grid' => __( 'Full grid', 'ds-toolkit' ), 'outer' => __( 'Outer frame only', 'ds-toolkit' ), 'outer_horizontal' => __( 'Outer frame + row lines', 'ds-toolkit' ) ) ),
					'table_border_width' => $ds_prg_unit( __( 'Border width', 'ds-toolkit' ), 1, 6 ),
					'table_border_color' => $ds_prg_colour( __( 'Border colour', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'table_radius'       => $ds_prg_unit( __( 'Corner radius', 'ds-toolkit' ), 0, 32 ),
					'table_shadow'       => array( 'type' => 'select', 'label' => __( 'Shadow', 'ds-toolkit' ), 'default' => '', 'options' => array( '' => __( 'Preset default', 'ds-toolkit' ), 'none' => __( 'None', 'ds-toolkit' ), 'subtle' => __( 'Subtle', 'ds-toolkit' ), 'soft' => __( 'Soft', 'ds-toolkit' ), 'medium' => __( 'Medium', 'ds-toolkit' ) ) ),
				),
			),
			'head_sec' => array(
				'title'  => __( 'Header row', 'ds-toolkit' ),
				'fields' => array(
					'head_bg_style'     => array(
						'type'    => 'select',
						'label'   => __( 'Background', 'ds-toolkit' ),
						'default' => 'preset',
						'options' => array(
							'preset' => __( 'From the preset', 'ds-toolkit' ),
							'none'   => __( 'None (transparent)', 'ds-toolkit' ),
							'custom' => __( 'Pick a colour', 'ds-toolkit' ),
						),
						'toggle'  => array( 'custom' => array( 'fields' => array( 'head_bg' ) ) ),
						'help'    => __( 'The presets tint the header a faint grey. Choose None for no background at all, which clearing the colour alone cannot do.', 'ds-toolkit' ),
					),
					'head_bg'           => $ds_prg_colour( __( 'Header colour', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'head_color'        => $ds_prg_colour( __( 'Text colour', 'ds-toolkit' ) ),
					'head_typo'         => array( 'type' => 'typography', 'label' => __( 'Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-programs-th' ) ),
					'head_border_width' => $ds_prg_unit( __( 'Line under header', 'ds-toolkit' ), 0, 6 ),
					'head_border_color' => $ds_prg_colour( __( 'Line colour', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
				),
			),
			'row_sec' => array(
				'title'  => __( 'Rows', 'ds-toolkit' ),
				'fields' => array(
					'row_bg'      => $ds_prg_colour( __( 'Row background', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'row_stripe'  => $ds_prg_colour( __( 'Alternate row background', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'row_hover'   => $ds_prg_colour( __( 'Hover background', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'row_color'   => $ds_prg_colour( __( 'Text colour', 'ds-toolkit' ) ),
					'row_typo'    => array( 'type' => 'typography', 'label' => __( 'Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-programs-td' ) ),
					'name_weight' => array( 'type' => 'select', 'label' => __( 'Program name emphasis', 'ds-toolkit' ), 'default' => '600', 'options' => array( 'inherit' => __( 'Same as other cells', 'ds-toolkit' ), '500' => '500', '600' => __( '600 (semi-bold)', 'ds-toolkit' ), '700' => __( '700 (bold)', 'ds-toolkit' ), '800' => '800' ) ),
					'cell_pad'    => $ds_prg_unit( __( 'Cell padding', 'ds-toolkit' ), 4, 40, array( 'responsive' => true ) ),
				),
			),
			'filter_sec' => array(
				'title'  => __( 'Filter bar', 'ds-toolkit' ),
				'fields' => array(
					'filter_color'  => $ds_prg_colour( __( 'Label colour', 'ds-toolkit' ) ),
					'filter_typo'   => array( 'type' => 'typography', 'label' => __( 'Label typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-programs-label' ) ),
					'select_bg'     => $ds_prg_colour( __( 'Dropdown background', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'select_color'  => $ds_prg_colour( __( 'Dropdown text', 'ds-toolkit' ) ),
					'select_border' => $ds_prg_colour( __( 'Dropdown border', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'select_radius' => $ds_prg_unit( __( 'Dropdown corner radius', 'ds-toolkit' ), 0, 30 ),
					'select_width'  => $ds_prg_unit( __( 'Dropdown width', 'ds-toolkit' ), 100, 400, array( 'help' => __( 'Blank = fluid, up to 230px each.', 'ds-toolkit' ) ) ),
					'bar_gap'       => $ds_prg_unit( __( 'Gap between dropdowns', 'ds-toolkit' ), 0, 40 ),
					'bar_space'     => $ds_prg_unit( __( 'Space below the bar', 'ds-toolkit' ), 0, 80, array( 'responsive' => true ) ),
					'search_width'  => $ds_prg_unit( __( 'Search box width', 'ds-toolkit' ), 120, 600, array( 'help' => __( 'Blank = same as a dropdown.', 'ds-toolkit' ) ) ),
				),
			),
			'pager_style' => array(
				'title'  => __( 'Pagination & sort arrows', 'ds-toolkit' ),
				'fields' => array(
					'pager_color'        => $ds_prg_colour( __( 'Page button text', 'ds-toolkit' ) ),
					'pager_border'       => $ds_prg_colour( __( 'Page button border', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'pager_active_bg'    => $ds_prg_colour( __( 'Current page background', 'ds-toolkit' ) ),
					'pager_active_color' => $ds_prg_colour( __( 'Current page text', 'ds-toolkit' ) ),
					'pager_radius'       => $ds_prg_unit( __( 'Page button corner radius', 'ds-toolkit' ), 0, 30 ),
					'sort_icon_color'    => $ds_prg_colour( __( 'Active sort arrow', 'ds-toolkit' ), array( 'help' => __( 'Blank = the header text colour.', 'ds-toolkit' ) ) ),
				),
			),
			'btn_sec' => array(
				'title'  => __( 'Register button', 'ds-toolkit' ),
				'fields' => array(
					'btn_global' => array(
						'type'    => 'select',
						'label'   => __( 'Button style', 'ds-toolkit' ),
						'default' => 'global',
						'options' => array( 'global' => __( 'Match site Button (Theme Setting)', 'ds-toolkit' ), 'custom' => __( 'Custom', 'ds-toolkit' ) ),
						'toggle'  => array( 'custom' => array( 'fields' => array( 'btn_bg', 'btn_color', 'btn_bg_hover', 'btn_color_hover', 'btn_radius', 'btn_pad_y', 'btn_pad_x', 'btn_typo' ) ) ),
					),
					'btn_text'        => array( 'type' => 'text', 'label' => __( 'Button text', 'ds-toolkit' ), 'default' => __( 'Register', 'ds-toolkit' ) ),
					'btn_full_text'   => array( 'type' => 'text', 'label' => __( 'Text when sold out', 'ds-toolkit' ), 'default' => __( 'Sold Out', 'ds-toolkit' ) ),
					'btn_cancel_text' => array( 'type' => 'text', 'label' => __( 'Text when cancelled', 'ds-toolkit' ), 'default' => __( 'Cancelled', 'ds-toolkit' ), 'help' => __( 'Cancelled programs show a neutral, non-clickable chip in place of the register button. Pick "Own colours" below to restyle it.', 'ds-toolkit' ) ),
					'season_links'    => array(
						'type'    => 'select',
						'label'   => __( 'Schedule & standings links', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => array( 'yes' => __( 'Show for in-season programs', 'ds-toolkit' ), 'no' => __( 'Hide', 'ds-toolkit' ) ),
						'toggle'  => array( 'yes' => array( 'fields' => array( 'schedule_text', 'standings_text', 'season_link_color' ) ) ),
						'help'    => __( 'Once a program is in season, LeagueApps publishes its schedule and standings pages; these text links point to them, as the LeagueApps listing widget did. The register button stays only while registration is still open.', 'ds-toolkit' ),
					),
					'schedule_text'     => array( 'type' => 'text', 'label' => __( 'Schedule link text', 'ds-toolkit' ), 'default' => __( 'View schedule', 'ds-toolkit' ) ),
					'standings_text'    => array( 'type' => 'text', 'label' => __( 'Standings link text', 'ds-toolkit' ), 'default' => __( 'View standings', 'ds-toolkit' ) ),
					'season_link_color' => $ds_prg_colour( __( 'Schedule & standings link colour', 'ds-toolkit' ), array( 'help' => __( 'Blank = the row text colour, underlined. A brand accent can fail contrast on light rows; check it before using one.', 'ds-toolkit' ) ) ),
					'btn_bg'          => $ds_prg_colour( __( 'Background', 'ds-toolkit' ) ),
					'btn_color'       => $ds_prg_colour( __( 'Text', 'ds-toolkit' ) ),
					'btn_bg_hover'    => $ds_prg_colour( __( 'Background hover', 'ds-toolkit' ) ),
					'btn_color_hover' => $ds_prg_colour( __( 'Text hover', 'ds-toolkit' ) ),
					'btn_radius'      => $ds_prg_unit( __( 'Corner radius', 'ds-toolkit' ), 0, 40 ),
					'btn_pad_y'       => $ds_prg_unit( __( 'Padding top / bottom', 'ds-toolkit' ), 2, 30 ),
					'btn_pad_x'       => $ds_prg_unit( __( 'Padding left / right', 'ds-toolkit' ), 4, 60 ),
					'btn_typo'        => array( 'type' => 'typography', 'label' => __( 'Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-programs-btn' ) ),
					'btn_full_style'  => array( 'type' => 'select', 'label' => __( 'Sold-out look', 'ds-toolkit' ), 'default' => 'fade', 'options' => array( 'fade' => __( 'Faded button', 'ds-toolkit' ), 'colors' => __( 'Own colours', 'ds-toolkit' ), 'text' => __( 'Plain text, no button', 'ds-toolkit' ) ), 'toggle' => array( 'colors' => array( 'fields' => array( 'btn_full_bg', 'btn_full_color' ) ), 'text' => array( 'fields' => array( 'btn_full_color' ) ) ) ),
					'btn_full_bg'     => $ds_prg_colour( __( 'Sold-out background', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'btn_full_color'  => $ds_prg_colour( __( 'Sold-out text', 'ds-toolkit' ) ),
				),
			),
			'state_sec' => array(
				'title'  => __( 'Empty states', 'ds-toolkit' ),
				'fields' => array(
					'empty_color' => $ds_prg_colour( __( 'Text colour', 'ds-toolkit' ) ),
					'empty_typo'  => array( 'type' => 'typography', 'label' => __( 'Typography', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '.ds-programs-empty, .ds-programs-none' ) ),
				),
			),
			'mobile_sec' => array(
				'title'       => __( 'Phone cards', 'ds-toolkit' ),
				'description' => sprintf( __( 'Below %dpx (the site\'s Beaver Builder small-screen breakpoint) the table becomes stacked cards, one per row, each value labelled.', 'ds-toolkit' ), (int) $ds_prg_bp[1] ),
				'fields'      => array(
					'mob_card_bg'      => $ds_prg_colour( __( 'Card background', 'ds-toolkit' ), array( 'show_alpha' => true ) ),
					'mob_card_pad'     => $ds_prg_unit( __( 'Card padding', 'ds-toolkit' ), 0, 40 ),
					'mob_card_radius'  => $ds_prg_unit( __( 'Card corner radius', 'ds-toolkit' ), 0, 32 ),
					'mob_card_gap'     => $ds_prg_unit( __( 'Gap between cards', 'ds-toolkit' ), 0, 60 ),
					'mob_card_border'  => $ds_prg_unit( __( 'Card bottom line', 'ds-toolkit' ), 0, 6 ),
					'mob_label_color'  => $ds_prg_colour( __( 'Field label colour', 'ds-toolkit' ) ),
					'mob_label_size'   => $ds_prg_unit( __( 'Field label size', 'ds-toolkit' ), 9, 18 ),
					'mob_label_case'   => array( 'type' => 'select', 'label' => __( 'Field label case', 'ds-toolkit' ), 'default' => 'upper', 'options' => array( 'upper' => __( 'UPPERCASE', 'ds-toolkit' ), 'none' => __( 'As written', 'ds-toolkit' ) ) ),
					'mob_name_size'    => $ds_prg_unit( __( 'Program name size', 'ds-toolkit' ), 14, 32 ),
				),
			),
		),
	),
) );
