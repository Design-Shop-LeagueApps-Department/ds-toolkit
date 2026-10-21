<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LeagueApps Roster: one LeagueApps team's players and coaches as a designed roster
 * (initials medallion, name, position, graduation year, height, captain badge), with
 * position filter chips. Data: DS_Roster_Data (private key on the LeagueApps tab, public
 * fields only, synced hourly in the background).
 *
 * Origin: Surfside Volleyball's hosted LeagueApps roster page (Alipio, 2026-10-21: "the
 * roster of team of leagueapps present so shitty... design and ux sucks").
 *
 * @class DS_Roster_Module
 */
class DS_Roster_Module extends FLBuilderModule {

	public function __construct() {
		parent::__construct( array(
			'name'            => __( 'LeagueApps Roster', 'ds-toolkit' ),
			'description'     => __( 'A team\'s players and coaches from LeagueApps.', 'ds-toolkit' ),
			'category'        => __( 'LeagueApps', 'ds-toolkit' ),
			'dir'             => DS_TOOLKIT_PATH . 'modules/ds-la-roster/',
			'url'             => DS_TOOLKIT_URL . 'modules/ds-la-roster/',
			'partial_refresh' => true,
			'editor_export'   => false,
		) );
	}

	/** The team to render, shaped for the template, or null. */
	public function roster() {
		$s = $this->settings;
		$t = DS_Roster_Data::team( (string) ( $s->team ?? '' ) );
		if ( ! $t ) {
			return null;
		}
		$initial = 'initial' === (string) ( $s->name_format ?? 'full' );
		// "Last initial" is for players (mostly minors); coaches are adults and keep their full names.
		$people  = function ( array $list, $initial ) {
			return array_map( function ( $p ) use ( $initial ) {
				$first = $p['f'];
				$last  = $p['l'];
				$p['name'] = trim( $first . ' ' . ( $initial && '' !== $last ? mb_substr( $last, 0, 1 ) . '.' : $last ) );
				$ini       = mb_substr( $first, 0, 1 ) . mb_substr( $last, 0, 1 );
				$p['ini']  = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $ini ) : strtoupper( $ini );
				return $p;
			}, $list );
		};
		$t['players'] = $people( $t['players'], $initial );
		$t['coaches'] = $people( $t['coaches'], false );
		if ( 'position' === (string) ( $s->sort_by ?? 'name' ) ) {
			$order = self::position_order( $t['players'] );
			usort( $t['players'], function ( $a, $b ) use ( $order ) {
				return ( $order[ $a['pos'] ] ?? 99 ) <=> ( $order[ $b['pos'] ] ?? 99 ) ?: strnatcasecmp( $a['name'], $b['name'] );
			} );
		}
		$t['season'] = self::season( $t );
		return $t;
	}

	/** Positions in a sensible order (volleyball first, then the club's own words), empty last. */
	public static function position_order( array $players ) {
		$known = array( 'setter', 'outside', 'outside hitter', 'opposite', 'right side', 'middle', 'middle blocker', 'ds/libero', 'libero', 'ds', 'defensive specialist' );
		$out   = array();
		foreach ( $players as $p ) {
			$k = strtolower( $p['pos'] );
			$i = array_search( $k, $known, true );
			$out[ $p['pos'] ] = '' === $p['pos'] ? 98 : ( false === $i ? 50 : $i );
		}
		return $out;
	}

	/** "2026/2027" from the program name, else the program's years. */
	public static function season( array $t ) {
		if ( preg_match( '/\b(20\d\d)\s*[\/-]\s*(20)?(\d\d)\b/', (string) $t['program'], $m ) ) {
			return $m[1] . '/' . ( $m[2] ?? '' ) . $m[3];
		}
		$a = $t['start'] ? gmdate( 'Y', $t['start'] ) : '';
		$b = $t['end'] ? gmdate( 'Y', $t['end'] ) : '';
		return $a && $b && $a !== $b ? $a . '/' . $b : $a;
	}
}

/* ------------------------------------------------------------------ form */

$ds_ros_yes_no = array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) );
$ds_ros_color  = function ( $label, $help = '' ) {
	$f = array( 'type' => 'color', 'label' => $label, 'default' => '', 'show_reset' => true, 'show_alpha' => false, 'connections' => array( 'color' ), 'preview' => array( 'type' => 'refresh' ) );
	if ( $help ) {
		$f['help'] = $help;
	}
	return $f;
};
$ds_ros_teams = DS_Roster_Data::team_options();

FLBuilder::register_module( 'DS_Roster_Module', array(

	'roster' => array(
		'title'    => __( 'Roster', 'ds-toolkit' ),
		'sections' => array(
			'team' => array(
				'title'  => __( 'Team', 'ds-toolkit' ),
				'fields' => array(
					'team' => array(
						'type'    => 'select',
						'label'   => __( 'LeagueApps team', 'ds-toolkit' ),
						'default' => '',
						'options' => $ds_ros_teams ? array( '' => __( 'Pick a team', 'ds-toolkit' ) ) + $ds_ros_teams : array( '' => __( 'No teams yet: save the club\'s private key on Settings > DS Toolkit > LeagueApps', 'ds-toolkit' ) ),
						'help'    => __( 'Newest season first; the number is the players on the roster. The list comes from LeagueApps and refreshes every hour.', 'ds-toolkit' ),
					),
					'show_header' => array(
						'type'    => 'button-group',
						'label'   => __( 'Show the team name and counts', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => $ds_ros_yes_no,
						'toggle'  => array( 'yes' => array( 'fields' => array( 'title_text', 'title_tag' ) ) ),
						'help'    => __( 'Turn off when the page already has a "Team Roster" heading.', 'ds-toolkit' ),
					),
					'title_text' => array( 'type' => 'text', 'label' => __( 'Title', 'ds-toolkit' ), 'default' => '', 'placeholder' => __( 'Blank = the team name from LeagueApps', 'ds-toolkit' ) ),
					'title_tag'  => array(
						'type'    => 'select',
						'label'   => __( 'Title level', 'ds-toolkit' ),
						'default' => 'h3',
						'options' => array( 'h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4' ),
						'help'    => __( 'H3 sits under a section heading such as "Team Roster" (the usual team page). Use H2 when the roster is the section.', 'ds-toolkit' ),
					),
				),
			),
			'players' => array(
				'title'  => __( 'Players', 'ds-toolkit' ),
				'fields' => array(
					'name_format' => array(
						'type'    => 'button-group',
						'label'   => __( 'Player names', 'ds-toolkit' ),
						'default' => 'full',
						'options' => array( 'full' => __( 'Full name', 'ds-toolkit' ), 'initial' => __( 'Last initial', 'ds-toolkit' ) ),
						'help'    => __( '"Lauren B." keeps young players\' surnames off the public site. Coaches always show their full name.', 'ds-toolkit' ),
					),
					'show_position' => array( 'type' => 'button-group', 'label' => __( 'Position', 'ds-toolkit' ), 'default' => 'yes', 'options' => $ds_ros_yes_no ),
					'show_grad'     => array( 'type' => 'button-group', 'label' => __( 'Graduation year', 'ds-toolkit' ), 'default' => 'yes', 'options' => $ds_ros_yes_no ),
					'show_height'   => array( 'type' => 'button-group', 'label' => __( 'Height', 'ds-toolkit' ), 'default' => 'yes', 'options' => $ds_ros_yes_no, 'help' => __( 'Position, graduation year and height come from the club\'s registration questions; players who did not answer show without them.', 'ds-toolkit' ) ),
					'show_filter'   => array( 'type' => 'button-group', 'label' => __( 'Position filter', 'ds-toolkit' ), 'default' => 'yes', 'options' => $ds_ros_yes_no, 'help' => __( 'Buttons above the roster (All, Outside, Setter...). Hidden when there is only one position.', 'ds-toolkit' ) ),
					'sort_by'       => array(
						'type'    => 'button-group',
						'label'   => __( 'Order', 'ds-toolkit' ),
						'default' => 'name',
						'options' => array( 'name' => __( 'Last name', 'ds-toolkit' ), 'position' => __( 'Position', 'ds-toolkit' ) ),
					),
					'layout' => array(
						'type'    => 'button-group',
						'label'   => __( 'Layout', 'ds-toolkit' ),
						'default' => 'cards',
						'options' => array( 'cards' => __( 'Cards', 'ds-toolkit' ), 'list' => __( 'List', 'ds-toolkit' ) ),
					),
				),
			),
			'coaches' => array(
				'title'  => __( 'Coaches', 'ds-toolkit' ),
				'fields' => array(
					'show_coaches'  => array( 'type' => 'button-group', 'label' => __( 'Show the coaches', 'ds-toolkit' ), 'default' => 'yes', 'options' => $ds_ros_yes_no, 'toggle' => array( 'yes' => array( 'fields' => array( 'coaches_title' ) ) ) ),
					'coaches_title' => array( 'type' => 'text', 'label' => __( 'Heading', 'ds-toolkit' ), 'default' => __( 'Coaches', 'ds-toolkit' ) ),
				),
			),
			'text' => array(
				'title'     => __( 'Messages', 'ds-toolkit' ),
				'collapsed' => true,
				'fields'    => array(
					'empty_text' => array( 'type' => 'text', 'label' => __( 'When the roster is empty', 'ds-toolkit' ), 'default' => __( 'The roster for this team will be posted soon.', 'ds-toolkit' ) ),
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
					'c_brand' => $ds_ros_color( __( 'Main colour', 'ds-toolkit' ), __( 'Medallions, position tags, the active filter and the captain badge. Empty = your Global Styles Primary colour. Tints and readable text versions are worked out from it.', 'ds-toolkit' ) ),
					'c_text'  => $ds_ros_color( __( 'Text', 'ds-toolkit' ) ),
					'c_muted' => $ds_ros_color( __( 'Small text', 'ds-toolkit' ) ),
					'c_card'  => $ds_ros_color( __( 'Card background', 'ds-toolkit' ), __( 'Empty = white. Keep it light: the text colours are dark.', 'ds-toolkit' ) ),
					'c_line'  => $ds_ros_color( __( 'Card border', 'ds-toolkit' ) ),
					'medallion' => array(
						'type'    => 'button-group',
						'label'   => __( 'Initials medallion', 'ds-toolkit' ),
						'default' => 'tint',
						'options' => array( 'tint' => __( 'Soft', 'ds-toolkit' ), 'solid' => __( 'Solid', 'ds-toolkit' ), 'ring' => __( 'Outline', 'ds-toolkit' ) ),
					),
				),
			),
			'grid' => array(
				'title'  => __( 'Cards', 'ds-toolkit' ),
				'fields' => array(
					'columns' => array(
						'type'       => 'unit',
						'label'      => __( 'Cards per row', 'ds-toolkit' ),
						'default'    => '4',
						'responsive' => array( 'default' => array( 'default' => '4', 'medium' => '3', 'responsive' => '2' ) ),
						'slider'     => array( 'min' => 1, 'max' => 6, 'step' => 1 ),
					),
					'radius' => array(
						'type'    => 'unit',
						'label'   => __( 'Corner rounding', 'ds-toolkit' ),
						'default' => '12',
						'units'   => array( 'px' ),
						'slider'  => array( 'min' => 0, 'max' => 24, 'step' => 1 ),
					),
				),
			),
			'type' => array(
				'title'  => __( 'Text', 'ds-toolkit' ),
				'fields' => array(
					'title_typo' => array( 'type' => 'typography', 'label' => __( 'Team name', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '{node} .dsr-name' ), 'help' => __( 'Empty = your site\'s heading font.', 'ds-toolkit' ) ),
					'name_typo'  => array( 'type' => 'typography', 'label' => __( 'Player names', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '{node} .dsr-pname' ) ),
				),
			),
		),
	),
) );
