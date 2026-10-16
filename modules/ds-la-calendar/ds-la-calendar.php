<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LeagueApps Calendar: a club's LeagueApps team schedules (tournaments, games,
 * practices) as a month calendar and an upcoming list, with a team filter and
 * an event pop-up (teams going, event website, directions).
 *
 * Data: DS_Calendar_Data (server-side, cached an hour, credentials from
 * Settings > DS Toolkit > LeagueApps). Nothing about LeagueApps reaches the
 * browser except the events themselves.
 *
 * Origin: inspireclvb preview, 2026-10-16.
 *
 * @class DS_Calendar_Module
 */
class DS_Calendar_Module extends FLBuilderModule {

	public function __construct() {
		parent::__construct( array(
			'name'            => __( 'LeagueApps Calendar', 'ds-toolkit' ),
			'description'     => __( 'Your teams\' LeagueApps schedules as a month calendar and an upcoming list.', 'ds-toolkit' ),
			'category'        => __( 'LeagueApps', 'ds-toolkit' ),
			'dir'             => DS_TOOLKIT_PATH . 'modules/ds-la-calendar/',
			'url'             => DS_TOOLKIT_URL . 'modules/ds-la-calendar/',
			'partial_refresh' => false,
			'editor_export'   => false,
		) );
	}

	/** Events for this module's settings, filtered by type and team. */
	public function calendar_data() {
		$s     = $this->settings;
		$ids   = ( 'pick' === ( $s->sites_mode ?? 'all' ) ) ? array_filter( (array) ( $s->sites_pick ?? array() ) ) : array();
		$data  = DS_Calendar_Data::get( array_map( 'strval', $ids ) );
		$types = self::list_value( $s->types ?? '' );
		$teams = ( 'pick' === ( $s->teams_mode ?? 'all' ) ) ? self::list_value( $s->teams_pick ?? '' ) : array();

		if ( $types || $teams ) {
			$data['events'] = array_values( array_filter( $data['events'], function ( $e ) use ( $types, $teams ) {
				if ( $types && ! in_array( $e['type'], $types, true ) ) {
					return false;
				}
				if ( $teams ) {
					$e_teams = wp_list_pluck( $e['teams'], 'n' );
					if ( ! array_intersect( $e_teams, $teams ) ) {
						return false;
					}
				}
				return true;
			} ) );
			if ( $teams ) {
				$data['teams'] = array_values( array_intersect( $data['teams'], $teams ) );
			}
		}
		return $data;
	}

	/** BB multi-selects arrive as arrays, button groups as comma strings. */
	public static function list_value( $v ) {
		if ( is_string( $v ) ) {
			$v = explode( ',', $v );
		}
		return array_values( array_filter( array_map( 'trim', (array) $v ), 'strlen' ) );
	}

	/** Builder pickers learn teams and types from the last fetch. */
	public static function team_options() {
		$out = array();
		foreach ( DS_Calendar_Data::known( 'teams' ) as $t ) {
			$out[ $t ] = $t;
		}
		return $out ? $out : array( '' => __( 'Teams appear here once the calendar has loaded once', 'ds-toolkit' ) );
	}

	public static function type_options() {
		$out = array();
		foreach ( array_unique( array_merge( array( 'Tournament', 'Game', 'Practice' ), DS_Calendar_Data::known( 'types' ) ) ) as $t ) {
			$out[ $t ] = $t;
		}
		return $out;
	}

	/** Colour field value -> safe CSS colour, or '' when unset (hex, rgb/hsl, var(--x), keyword; nothing else). */
	public static function color( $v ) {
		return DS_Module_UI::color( $v );
	}

	/** Global Styles colours keyed by variable slug ("Primary" -> primary). */
	private static function globals() {
		static $map = null;
		if ( null === $map ) {
			$map    = array();
			$styles = get_option( '_fl_builder_styles' );
			$colors = is_object( $styles ) ? ( $styles->colors ?? array() ) : ( is_array( $styles ) ? ( $styles['colors'] ?? array() ) : array() );
			foreach ( (array) $colors as $c ) {
				$c = (array) $c;
				if ( ! empty( $c['label'] ) && ! empty( $c['color'] ) ) {
					$map[ sanitize_title( $c['label'] ) ] = $c['color'];
				}
			}
		}
		return $map;
	}

	/** Any colour we can resolve (hex, rgb, a Global Styles var) -> array( r, g, b ), else null. */
	public static function rgb( $v ) {
		$v = trim( (string) $v );
		if ( preg_match( '/^var\(\s*--fl-global-([a-z0-9-]+)/i', $v, $m ) ) {
			$g = self::globals();
			return isset( $g[ $m[1] ] ) && 0 !== strpos( (string) $g[ $m[1] ], 'var(' ) ? self::rgb( $g[ $m[1] ] ) : null;
		}
		$v = ltrim( $v, '#' );
		if ( preg_match( '/^[0-9a-f]{3}$/i', $v ) ) {
			$v = $v[0] . $v[0] . $v[1] . $v[1] . $v[2] . $v[2];
		}
		if ( preg_match( '/^([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})/i', $v, $m ) ) {
			return array( hexdec( $m[1] ), hexdec( $m[2] ), hexdec( $m[3] ) );
		}
		if ( preg_match( '/rgba?\(\s*(\d+)\D+(\d+)\D+(\d+)/i', $v, $m ) ) {
			return array( (int) $m[1], (int) $m[2], (int) $m[3] );
		}
		return null;
	}

	public static function hex( array $c ) {
		return sprintf( '#%02x%02x%02x', max( 0, min( 255, (int) round( $c[0] ) ) ), max( 0, min( 255, (int) round( $c[1] ) ) ), max( 0, min( 255, (int) round( $c[2] ) ) ) );
	}

	/** $t share of $a, the rest $b. */
	public static function mix( array $a, array $b, $t ) {
		return array( $a[0] * $t + $b[0] * ( 1 - $t ), $a[1] * $t + $b[1] * ( 1 - $t ), $a[2] * $t + $b[2] * ( 1 - $t ) );
	}

	/** WCAG contrast ratio of two rgb triples. */
	public static function ratio( array $a, array $b ) {
		$lum = function ( $c ) {
			$f = function ( $x ) { $x /= 255; return $x <= 0.03928 ? $x / 12.92 : pow( ( $x + 0.055 ) / 1.055, 2.4 ); };
			return 0.2126 * $f( $c[0] ) + 0.7152 * $f( $c[1] ) + 0.0722 * $f( $c[2] );
		};
		$l1 = $lum( $a ); $l2 = $lum( $b );
		return ( max( $l1, $l2 ) + 0.05 ) / ( min( $l1, $l2 ) + 0.05 );
	}

	/**
	 * A soft tint of an event colour and a label colour that is guaranteed 4.5:1 on it,
	 * as plain hex (CSS color-mix() would do the tint, but contrast tools misread it and
	 * it cannot promise the label stays readable for any colour a partner picks).
	 */
	public static function tint_pair( $color ) {
		$base = self::rgb( $color );
		if ( ! $base ) {
			return null;
		}
		$tint = self::mix( $base, array( 255, 255, 255 ), 0.13 );
		$ink  = self::mix( $base, array( 0, 0, 0 ), 0.55 );
		for ( $i = 0; $i < 12 && self::ratio( $ink, $tint ) < 4.6; $i++ ) {
			$ink = self::mix( $ink, array( 0, 0, 0 ), 0.8 );
		}
		return array( self::hex( $tint ), self::hex( $ink ) );
	}
}

/* ------------------------------------------------------------------ form */

$ds_la_cal_yes_no = array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) );

$ds_la_cal_color = function ( $label, $help = '' ) {
	$f = array( 'type' => 'color', 'label' => $label, 'default' => '', 'show_reset' => true, 'show_alpha' => false, 'connections' => array( 'color' ), 'preview' => array( 'type' => 'refresh' ) );
	if ( $help ) {
		$f['help'] = $help;
	}
	return $f;
};

FLBuilder::register_module( 'DS_Calendar_Module', array(

	'calendar' => array(
		'title'    => __( 'Calendar', 'ds-toolkit' ),
		'sections' => array(
			'account' => array(
				'title'  => __( 'LeagueApps account', 'ds-toolkit' ),
				'fields' => array(
					'sites_mode' => array(
						'type'    => 'select',
						'label'   => __( 'Show schedules from', 'ds-toolkit' ),
						'default' => 'all',
						'options' => array( 'all' => __( 'Every LeagueApps site saved in DS Toolkit', 'ds-toolkit' ), 'pick' => __( 'Only the sites I pick', 'ds-toolkit' ) ),
						'toggle'  => array( 'pick' => array( 'fields' => array( 'sites_pick' ) ) ),
						'help'    => __( 'The LeagueApps Site ID and key are saved once in Settings > DS Toolkit > LeagueApps, the same place the LeagueApps Programs module reads them.', 'ds-toolkit' ),
					),
					'sites_pick' => array(
						'type'         => 'select',
						'label'        => __( 'Sites', 'ds-toolkit' ),
						'options'      => DS_Calendar_Data::site_options(),
						'multi-select' => true,
					),
				),
			),
			'content' => array(
				'title'  => __( 'What to show', 'ds-toolkit' ),
				'fields' => array(
					'types' => array(
						'type'         => 'select',
						'label'        => __( 'Event types', 'ds-toolkit' ),
						'options'      => DS_Calendar_Module::type_options(),
						'multi-select' => true,
						'help'         => __( 'Leave empty to show every type. Pick one or more to show only those (for example only Tournaments).', 'ds-toolkit' ),
					),
					'teams_mode' => array(
						'type'    => 'select',
						'label'   => __( 'Teams', 'ds-toolkit' ),
						'default' => 'all',
						'options' => array( 'all' => __( 'All teams', 'ds-toolkit' ), 'pick' => __( 'Only the teams I pick', 'ds-toolkit' ) ),
						'toggle'  => array( 'pick' => array( 'fields' => array( 'teams_pick' ) ) ),
						'help'    => __( 'Pick one team to make a single team\'s schedule, for example on that team\'s page.', 'ds-toolkit' ),
					),
					'teams_pick' => array(
						'type'         => 'select',
						'label'        => __( 'Show these teams', 'ds-toolkit' ),
						'options'      => DS_Calendar_Module::team_options(),
						'multi-select' => true,
					),
				),
			),
			'views' => array(
				'title'  => __( 'Views', 'ds-toolkit' ),
				'fields' => array(
					'view_desktop' => array(
						'type'    => 'button-group',
						'label'   => __( 'Opens as (computer)', 'ds-toolkit' ),
						'default' => 'month',
						'options' => array( 'month' => __( 'Month', 'ds-toolkit' ), 'list' => __( 'List', 'ds-toolkit' ) ),
					),
					'view_phone' => array(
						'type'    => 'button-group',
						'label'   => __( 'Opens as (phone)', 'ds-toolkit' ),
						'default' => 'list',
						'options' => array( 'month' => __( 'Month', 'ds-toolkit' ), 'list' => __( 'List', 'ds-toolkit' ) ),
						'help'    => __( 'On phones the month view shows a dot on each busy day; tapping the day lists its events underneath.', 'ds-toolkit' ),
					),
					'show_switch' => array(
						'type'    => 'button-group',
						'label'   => __( 'Let visitors switch Month / List', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => $ds_la_cal_yes_no,
						'toggle'  => array( 'yes' => array( 'fields' => array( 'month_text', 'list_text' ) ) ),
					),
					'month_text' => array( 'type' => 'text', 'label' => __( 'Month button text', 'ds-toolkit' ), 'default' => __( 'Month', 'ds-toolkit' ), 'size' => 12 ),
					'list_text'  => array( 'type' => 'text', 'label' => __( 'List button text', 'ds-toolkit' ), 'default' => __( 'List', 'ds-toolkit' ), 'size' => 12 ),
					'max_chips'  => array(
						'type'    => 'unit',
						'label'   => __( 'Events per day before "+ more"', 'ds-toolkit' ),
						'default' => '3',
						'slider'  => array( 'min' => 1, 'max' => 6, 'step' => 1 ),
					),
				),
			),
			'filter' => array(
				'title'  => __( 'Team filter', 'ds-toolkit' ),
				'fields' => array(
					'show_team_filter' => array(
						'type'    => 'button-group',
						'label'   => __( 'Show a team dropdown', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => $ds_la_cal_yes_no,
						'toggle'  => array( 'yes' => array( 'fields' => array( 'team_label', 'team_all', 'group_by_age' ) ) ),
					),
					'team_label'   => array( 'type' => 'text', 'label' => __( 'Label', 'ds-toolkit' ), 'default' => __( 'Team', 'ds-toolkit' ), 'size' => 14 ),
					'team_all'     => array( 'type' => 'text', 'label' => __( '"Everyone" option', 'ds-toolkit' ), 'default' => __( 'All teams', 'ds-toolkit' ), 'size' => 14 ),
					'group_by_age' => array(
						'type'    => 'button-group',
						'label'   => __( 'Group teams by age', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => $ds_la_cal_yes_no,
						'help'    => __( 'Uses the number at the start of the team name ("14 Sunrise" goes under 14s).', 'ds-toolkit' ),
					),
				),
			),
			'details' => array(
				'title'  => __( 'Event details', 'ds-toolkit' ),
				'fields' => array(
					'show_teams'      => array( 'type' => 'button-group', 'label' => __( 'List the teams going', 'ds-toolkit' ), 'default' => 'yes', 'options' => $ds_la_cal_yes_no ),
					'collapse_after'  => array( 'type' => 'unit', 'label' => __( 'In the list, fold the teams when there are more than', 'ds-toolkit' ), 'default' => '6', 'slider' => array( 'min' => 2, 'max' => 30, 'step' => 1 ) ),
					'website_text'    => array( 'type' => 'text', 'label' => __( 'Event link button', 'ds-toolkit' ), 'default' => __( 'Event website', 'ds-toolkit' ), 'help' => __( 'Shown when the event in LeagueApps has a web address in its description. Leave empty to hide.', 'ds-toolkit' ) ),
					'directions_text' => array( 'type' => 'text', 'label' => __( 'Directions button', 'ds-toolkit' ), 'default' => __( 'Directions', 'ds-toolkit' ), 'help' => __( 'Opens Google Maps at the venue. Leave empty to hide.', 'ds-toolkit' ) ),
				),
			),
			'text' => array(
				'title'     => __( 'Messages', 'ds-toolkit' ),
				'collapsed' => true,
				'fields'    => array(
					'empty_text' => array( 'type' => 'text', 'label' => __( 'When nothing is scheduled', 'ds-toolkit' ), 'default' => __( 'No upcoming events yet. Please check back soon.', 'ds-toolkit' ) ),
					'note_text'  => array( 'type' => 'text', 'label' => __( 'Note under the calendar', 'ds-toolkit' ), 'default' => __( 'Times are Eastern.', 'ds-toolkit' ), 'help' => __( 'Leave empty to hide.', 'ds-toolkit' ) ),
				),
			),
		),
	),

	'style' => array(
		'title'    => __( 'Style', 'ds-toolkit' ),
		'sections' => array(
			'colors' => array(
				'title'  => __( 'Colours', 'ds-toolkit' ),
				'fields' => array(
					'c_brand'  => $ds_la_cal_color( __( 'Main colour', 'ds-toolkit' ), __( 'Selected button, today, date blocks and buttons. Empty = your Global Styles Primary colour.', 'ds-toolkit' ) ),
					'c_brand_text' => $ds_la_cal_color( __( 'Text on the main colour', 'ds-toolkit' ), __( 'Empty = picked for you: white, or dark when white would be hard to read on your main colour.', 'ds-toolkit' ) ),
					'c_line'   => $ds_la_cal_color( __( 'Accent line', 'ds-toolkit' ), __( 'The line under each month in the list. Empty = Global Styles Secondary.', 'ds-toolkit' ) ),
					'c_text'   => $ds_la_cal_color( __( 'Text', 'ds-toolkit' ) ),
					'c_muted'  => $ds_la_cal_color( __( 'Small text (venue, times)', 'ds-toolkit' ) ),
				),
			),
			'type_colors' => array(
				'title'  => __( 'Event type colours', 'ds-toolkit' ),
				'fields' => array(
					'c_tour'  => $ds_la_cal_color( __( 'Tournaments', 'ds-toolkit' ), __( 'Empty = Global Styles Secondary. Events show as a soft tint of this colour with dark text, so any colour stays readable.', 'ds-toolkit' ) ),
					'c_game'  => $ds_la_cal_color( __( 'Games', 'ds-toolkit' ) ),
					'c_other' => $ds_la_cal_color( __( 'Practices and everything else', 'ds-toolkit' ) ),
					'chip_style' => array(
						'type'    => 'button-group',
						'label'   => __( 'Event style', 'ds-toolkit' ),
						'default' => 'soft',
						'options' => array( 'soft' => __( 'Soft fill', 'ds-toolkit' ), 'outline' => __( 'Outline', 'ds-toolkit' ), 'bar' => __( 'Side bar only', 'ds-toolkit' ) ),
					),
				),
			),
			'grid' => array(
				'title'  => __( 'Month grid', 'ds-toolkit' ),
				'fields' => array(
					'c_grid'      => $ds_la_cal_color( __( 'Grid lines', 'ds-toolkit' ) ),
					'c_cell'      => $ds_la_cal_color( __( 'Day background', 'ds-toolkit' ) ),
					'c_head'      => $ds_la_cal_color( __( 'Weekday row background', 'ds-toolkit' ) ),
					'cell_height' => array(
						'type'       => 'unit',
						'label'      => __( 'Day height', 'ds-toolkit' ),
						'default'    => '120',
						'units'      => array( 'px' ),
						'slider'     => array( 'min' => 80, 'max' => 220, 'step' => 5 ),
						'help'       => __( 'Computer and tablet. Phones use compact days with dots.', 'ds-toolkit' ),
					),
					'radius' => array(
						'type'    => 'unit',
						'label'   => __( 'Corner rounding', 'ds-toolkit' ),
						'default' => '6',
						'units'   => array( 'px' ),
						'slider'  => array( 'min' => 0, 'max' => 20, 'step' => 1 ),
					),
				),
			),
			'text_style' => array(
				'title'  => __( 'Text', 'ds-toolkit' ),
				'fields' => array(
					'title_typo' => array(
						'type'       => 'typography',
						'label'      => __( 'Month title', 'ds-toolkit' ),
						'responsive' => true,
						'preview'    => array( 'type' => 'css', 'selector' => '{node} .dslc-month' ),
						'help'       => __( 'Empty = your site\'s heading font.', 'ds-toolkit' ),
					),
					'event_typo' => array(
						'type'       => 'typography',
						'label'      => __( 'Event names in the list', 'ds-toolkit' ),
						'responsive' => true,
						'preview'    => array( 'type' => 'css', 'selector' => '{node} .dslc-row h3' ),
					),
				),
			),
		),
	),
) );
