<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LeagueApps Team Schedule: one team's (or program's) LeagueApps schedule as a table with
 * month groups, a type filter and "Show more", or as a compact "Next up" list for a sidebar.
 * Data: DS_Schedule_Data (public API, one program per module, cached an hour).
 *
 * Origin: the team page on the Design Shop demo (Alipio, 2026-10-21: "Can we add example game
 * schedule leagueapps table"), next to the LeagueApps Roster.
 *
 * @class DS_Schedule_Module
 */
class DS_Schedule_Module extends FLBuilderModule {

	public function __construct() {
		parent::__construct( array(
			'name'            => __( 'Schedule', 'ds-toolkit' ),
			'description'     => __( 'One team\'s games, tournaments and practices from LeagueApps.', 'ds-toolkit' ),
			'category'        => __( 'LeagueApps', 'ds-toolkit' ),
			'dir'             => DS_TOOLKIT_PATH . 'modules/ds-la-schedule/',
			'url'             => DS_TOOLKIT_URL . 'modules/ds-la-schedule/',
			'partial_refresh' => true,
			'editor_export'   => false,
		) );
	}

	/**
	 * Everything the template needs.
	 *
	 * @return array{state:string,rows:array,groups:array,main_label:string,url:string,initial:string}
	 *         state: 'pick' (no program chosen), 'none' (nothing to show), 'ok'
	 */
	public function schedule() {
		$s   = $this->settings;
		$key = (string) ( $s->program ?? '' );
		$out = array( 'state' => 'pick', 'rows' => array(), 'groups' => array(), 'main_label' => '', 'url' => '', 'initial' => '' );
		$p   = DS_Schedule_Data::parse_key( $key );
		if ( ! $p ) {
			return $out;
		}
		// The LeagueApps link is only looked up when it will be shown (a module with its own link never asks).
		$s_link = isset( $s->link_show ) && 'no' === $s->link_show ? false : '' === trim( (string) ( $s->link_url ?? '' ) );
		if ( $s_link ) {
			$out['url'] = DS_Schedule_Data::schedule_url( DS_Schedule_Data::program_row( (string) $p[0]['site_id'], $p[1], $p[0] ) );
		}

		$data = DS_Schedule_Data::items( $key );
		$rows = DS_Schedule_Data::rows( $data['items'], (string) ( $s->team_name ?? '' ) );
		if ( 'season' !== (string) ( $s->when ?? 'upcoming' ) ) {
			$rows = array_values( array_filter( $rows, function ( $r ) { return ! $r['past']; } ) );
		}
		$practices = (string) ( $s->practices ?? 'filter' );
		if ( 'compact' === (string) ( $s->layout ?? 'table' ) && 'mixed' !== $practices ) {
			$practices = 'hide'; // a short "next up" list is for the games; practices would fill it
		}
		foreach ( $rows as $i => $r ) {
			$rows[ $i ]['grp'] = self::is_practice( $r['type'] ) ? 'practice' : 'main';
		}
		if ( 'hide' === $practices ) {
			$rows = array_values( array_filter( $rows, function ( $r ) { return 'main' === $r['grp']; } ) );
		}
		$out['rows']  = $rows;
		$out['state'] = $rows ? 'ok' : 'none';

		$main  = array();
		$count = array( 'main' => 0, 'practice' => 0 );
		foreach ( $rows as $r ) {
			$count[ $r['grp'] ]++;
			if ( 'main' === $r['grp'] ) {
				$main[ $r['type'] ] = true;
			}
		}
		if ( 'filter' === $practices && $count['main'] && $count['practice'] ) {
			$out['groups']     = $count;
			$out['main_label'] = self::main_label( array_keys( $main ) );
			$out['initial']    = 'main'; // open on the games, practices one tap away
		}
		return $out;
	}

	public static function is_practice( $type ) {
		return (bool) preg_match( '/\bpractice|training\b/i', (string) $type );
	}

	/** "Tournaments", "Games & tournaments", "Games & events": the chip for everything but practices. */
	public static function main_label( array $types ) {
		$plural = function ( $t ) {
			$t = strtolower( trim( $t ) );
			return preg_match( '/s$/', $t ) ? $t : $t . 's';
		};
		if ( 1 === count( $types ) ) {
			return ucfirst( $plural( $types[0] ) );
		}
		if ( 2 === count( $types ) ) {
			sort( $types );
			return ucfirst( $plural( $types[0] ) ) . ' & ' . $plural( $types[1] );
		}
		return __( 'Games & events', 'ds-toolkit' );
	}

	/** CSS class suffix for a type chip. */
	public static function type_slug( $type ) {
		$t = sanitize_title( $type );
		return '' === $t ? 'event' : $t;
	}
}

/* ------------------------------------------------------------------ form */

$ds_sch_yes_no = array( 'yes' => __( 'Yes', 'ds-toolkit' ), 'no' => __( 'No', 'ds-toolkit' ) );
$ds_sch_color  = function ( $label, $help = '' ) {
	$f = array( 'type' => 'color', 'label' => $label, 'default' => '', 'show_reset' => true, 'show_alpha' => false, 'connections' => array( 'color' ), 'preview' => array( 'type' => 'refresh' ) );
	if ( $help ) {
		$f['help'] = $help;
	}
	return $f;
};
// Built only where the form is shown (the builder), never on a visitor's page view, where this file is loaded too.
$ds_sch_programs = ( class_exists( 'FLBuilderModel' ) && ( FLBuilderModel::is_builder_active() || ( class_exists( 'FLBuilderAJAX' ) && FLBuilderAJAX::doing_ajax() ) ) ) ? DS_Schedule_Data::program_options() : array();

FLBuilder::register_module( 'DS_Schedule_Module', array(

	'schedule' => array(
		'title'    => __( 'Schedule', 'ds-toolkit' ),
		'sections' => array(
			'source' => array(
				'title'  => __( 'Team', 'ds-toolkit' ),
				'fields' => array(
					'program' => array(
						'type'    => 'select',
						'label'   => __( 'LeagueApps team or program', 'ds-toolkit' ),
						'default' => '',
						'options' => $ds_sch_programs ? array( '' => __( 'Pick a team', 'ds-toolkit' ) ) + $ds_sch_programs : array( '' => __( 'No programs yet: save the club\'s site ID and API key on Settings > DS Toolkit > LeagueApps', 'ds-toolkit' ) ),
						'help'    => __( 'Club teams first. The schedule comes from LeagueApps and refreshes every hour.', 'ds-toolkit' ),
					),
					'team_name' => array(
						'type'        => 'text',
						'label'       => __( 'Only this team (leagues)', 'ds-toolkit' ),
						'default'     => '',
						'placeholder' => __( 'e.g. 14 Sunrise', 'ds-toolkit' ),
						'help'        => __( 'A league\'s schedule holds every team\'s games. Type one team\'s name exactly as LeagueApps shows it to keep only its games. Leave empty for a club team.', 'ds-toolkit' ),
					),
				),
			),
			'show' => array(
				'title'  => __( 'What to show', 'ds-toolkit' ),
				'fields' => array(
					'layout' => array(
						'type'    => 'button-group',
						'label'   => __( 'Layout', 'ds-toolkit' ),
						'default' => 'table',
						'options' => array( 'table' => __( 'Table', 'ds-toolkit' ), 'compact' => __( 'Next up', 'ds-toolkit' ) ),
						'toggle'  => array(
							'table'   => array( 'fields' => array( 'show_more', 'show_months' ) ),
							'compact' => array(),
						),
						'help'    => __( 'Next up = a short list of the coming games for a sidebar or a home page; practices stay out of it unless Practices is "Mixed in".', 'ds-toolkit' ),
					),
					'practices' => array(
						'type'    => 'button-group',
						'label'   => __( 'Practices', 'ds-toolkit' ),
						'default' => 'filter',
						'options' => array( 'filter' => __( 'Own filter', 'ds-toolkit' ), 'mixed' => __( 'Mixed in', 'ds-toolkit' ), 'hide' => __( 'Hide', 'ds-toolkit' ) ),
						'help'    => __( 'Own filter: the table opens on the games and tournaments, with a Practices button beside them.', 'ds-toolkit' ),
					),
					'when' => array(
						'type'    => 'button-group',
						'label'   => __( 'Dates', 'ds-toolkit' ),
						'default' => 'upcoming',
						'options' => array( 'upcoming' => __( 'Upcoming', 'ds-toolkit' ), 'season' => __( 'Whole season', 'ds-toolkit' ) ),
						'help'    => __( 'Whole season keeps past dates, greyed out.', 'ds-toolkit' ),
					),
					'rows' => array(
						'type'    => 'unit',
						'label'   => __( 'Rows shown', 'ds-toolkit' ),
						'default' => '8',
						'slider'  => array( 'min' => 1, 'max' => 40, 'step' => 1 ),
						'help'    => __( 'Table: rows before "Show more". Next up: the whole list.', 'ds-toolkit' ),
					),
					'show_more'   => array( 'type' => 'button-group', 'label' => __( '"Show more" button', 'ds-toolkit' ), 'default' => 'yes', 'options' => $ds_sch_yes_no ),
					'show_months' => array( 'type' => 'button-group', 'label' => __( 'Month headings', 'ds-toolkit' ), 'default' => 'yes', 'options' => $ds_sch_yes_no ),
				),
			),
			'links' => array(
				'title'  => __( 'Link below', 'ds-toolkit' ),
				'fields' => array(
					'link_show' => array(
						'type'    => 'button-group',
						'label'   => __( 'Show a link', 'ds-toolkit' ),
						'default' => 'yes',
						'options' => $ds_sch_yes_no,
						'toggle'  => array( 'yes' => array( 'fields' => array( 'link_text', 'link_url' ) ) ),
					),
					'link_text' => array( 'type' => 'text', 'label' => __( 'Link text', 'ds-toolkit' ), 'default' => __( 'Full schedule on LeagueApps', 'ds-toolkit' ) ),
					'link_url'  => array(
						'type'          => 'link',
						'label'         => __( 'Link to', 'ds-toolkit' ),
						'show_target'   => true,
						'show_nofollow' => false,
						'connections'   => array( 'url' ),
						'help'          => __( 'Empty = the team\'s schedule page on LeagueApps. For a Next up list on a team page, link to the table (e.g. #schedule) and change the text to "Full schedule".', 'ds-toolkit' ),
					),
				),
			),
			'text' => array(
				'title'     => __( 'Messages', 'ds-toolkit' ),
				'collapsed' => true,
				'fields'    => array(
					'empty_text' => array( 'type' => 'text', 'label' => __( 'When there is nothing to show', 'ds-toolkit' ), 'default' => __( 'The schedule for this team will be posted soon.', 'ds-toolkit' ) ),
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
					'c_brand' => $ds_sch_color( __( 'Main colour', 'ds-toolkit' ), __( 'Date badges, type tags, the active filter and links. Empty = your Global Styles Primary colour. Tints and readable text versions are worked out from it.', 'ds-toolkit' ) ),
					'c_text'  => $ds_sch_color( __( 'Text', 'ds-toolkit' ) ),
					'c_muted' => $ds_sch_color( __( 'Small text', 'ds-toolkit' ) ),
					'c_card'  => $ds_sch_color( __( 'Background', 'ds-toolkit' ), __( 'Empty = white. Keep it light: the text colours are dark.', 'ds-toolkit' ) ),
					'c_line'  => $ds_sch_color( __( 'Lines', 'ds-toolkit' ) ),
				),
			),
			'shape' => array(
				'title'  => __( 'Shape', 'ds-toolkit' ),
				'fields' => array(
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
					'title_typo' => array( 'type' => 'typography', 'label' => __( 'Event names', 'ds-toolkit' ), 'responsive' => true, 'preview' => array( 'type' => 'css', 'selector' => '{node} .dss-title' ) ),
				),
			),
		),
	),
) );
