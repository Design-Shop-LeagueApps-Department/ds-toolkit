<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LeagueApps Standings: a league's standings from LeagueApps, every division, in the site's own design.
 *
 * Data: DS_Standings_Data (the league list from the Programs feed cache, each division's played games from
 * its public schedule, cached an hour; credentials from Settings > DS Toolkit > LeagueApps). The table is
 * worked out on the server and printed as HTML: nothing about LeagueApps reaches the browser but the table.
 *
 * Origin: Zay's request (2026-11-16) to show exactly the LeagueApps standings columns, redesigned; preview
 * built on Big Cat Basketball's 2026 Girls Winter League.
 *
 * @class DS_Standings_Module
 */
class DS_Standings_Module extends FLBuilderModule {

	/** Column key => array( abbreviation, meaning, group ). The order is the LeagueApps standings page's. */
	const COLUMNS = array(
		'gp'  => array( 'GP', 'Games played', 'rec' ),
		'w'   => array( 'W', 'Wins', 'rec' ),
		'l'   => array( 'L', 'Losses', 'rec' ),
		't'   => array( 'T', 'Ties', 'rec' ),
		'pct' => array( 'PCT', 'Winning percentage', 'rec' ),
		'ps'  => array( 'PS', 'Points scored', 'pts' ),
		'psa' => array( 'PSA', 'Points scored against', 'pts' ),
		'psd' => array( 'PSD', 'Points scored differential', 'pts' ),
		'dnp' => array( 'DNP', 'Forfeits', 'std' ),
		'pf'  => array( 'PF', 'Standings points for', 'std' ),
		'pa'  => array( 'PA', 'Standings points against', 'std' ),
		'pm'  => array( '+/-', 'Standings point differential', 'std' ),
	);

	const GROUPS = array( 'rec' => 'Record', 'pts' => 'Points', 'std' => 'Standings points' );

	public function __construct() {
		parent::__construct( array(
			'name'            => __( 'Standings', 'ds-toolkit' ),
			'description'     => __( 'A league\'s standings from LeagueApps, every division, in the site\'s design.', 'ds-toolkit' ),
			'category'        => __( 'LeagueApps', 'ds-toolkit' ),
			'dir'             => DS_TOOLKIT_PATH . 'modules/ds-la-standings/',
			'url'             => DS_TOOLKIT_URL . 'modules/ds-la-standings/',
			'partial_refresh' => true,
			'editor_export'   => false,
		) );
	}

	/* ------------------------------------------------------------------
	 * Builder pickers (looked up only while the builder is open)
	 * ---------------------------------------------------------------- */

	public static function league_options() {
		$out = array( '' => __( '— Select a league —', 'ds-toolkit' ) );
		if ( ! DS_Module_UI::form_shown() ) {
			return $out;
		}
		$sites  = DS_Programs_Data::configured_sites();
		$labels = array();
		foreach ( $sites as $s ) {
			$labels[ $s['site_id'] ] = '' !== $s['label'] ? $s['label'] : $s['site_id'];
		}
		foreach ( DS_Standings_Data::leagues( $sites ) as $key => $l ) {
			$name = $l['name'] . ( '' !== $l['season'] ? ' (' . $l['season'] . ')' : '' );
			if ( count( $sites ) > 1 ) {
				$name .= ' · ' . ( $labels[ $l['site'] ] ?? $l['site'] );
			}
			$n          = count( $l['divisions'] );
			$out[ $key ] = $name . ( $n > 1 ? ' · ' . sprintf( _n( '%d division', '%d divisions', $n, 'ds-toolkit' ), $n ) : '' );
		}
		if ( 1 === count( $out ) ) {
			$out[''] = $sites
				? __( 'No league with standings is live on LeagueApps', 'ds-toolkit' )
				: __( 'Save a LeagueApps site under Settings > DS Toolkit > LeagueApps first', 'ds-toolkit' );
		}
		return $out;
	}

	public static function division_options() {
		$out = array( '' => __( '— Select a division —', 'ds-toolkit' ) );
		if ( ! DS_Module_UI::form_shown() ) {
			return $out;
		}
		foreach ( DS_Standings_Data::leagues() as $l ) {
			foreach ( $l['divisions'] as $pid => $d ) {
				$out[ (string) $pid ] = $l['name'] . ' › ' . $d['name'];
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------
	 * What the block shows
	 * ---------------------------------------------------------------- */

	/** BB multi-selects arrive as arrays, button groups as comma strings. */
	public static function list_value( $v ) {
		if ( is_string( $v ) ) {
			$v = explode( ',', $v );
		}
		return array_values( array_filter( array_map( 'trim', (array) $v ), 'strlen' ) );
	}

	/** The columns to print, in LeagueApps order, and which of them are left out on phones. */
	public function columns() {
		$s     = $this->settings;
		$all   = array_keys( self::COLUMNS );
		$cols  = array_values( array_intersect( $all, self::list_value( $s->cols ?? '' ) ) );
		$cols  = $cols ? $cols : $all;
		$phone = array_values( array_intersect( $cols, self::list_value( $s->cols_phone ?? '' ) ) );
		return array( $cols, $phone ? array_values( array_diff( $cols, $phone ) ) : array() );
	}

	/** Tiebreakers, standings points and forfeit points from the Rules tab. */
	public function rules() {
		$s   = $this->settings;
		$tbs = array();
		foreach ( array( 'tb1', 'tb2', 'tb3' ) as $i => $k ) {
			$v = (string) ( $s->$k ?? array( 'h2h', 'psd', 'psa' )[ $i ] );
			if ( isset( DS_Standings_Data::BREAKERS[ $v ] ) && ! in_array( $v, $tbs, true ) ) {
				$tbs[] = $v;
			}
		}
		$num = function ( $v, $d ) { return ( isset( $v ) && '' !== $v && is_numeric( $v ) ) ? (float) $v : $d; };
		return array(
			'breakers' => $tbs,
			'points'   => array( $num( $s->pts_win ?? '', 1.0 ), $num( $s->pts_tie ?? '', 0.5 ), $num( $s->pts_loss ?? '', 0.0 ) ),
			'forfeit'  => $num( $s->pts_forfeit ?? '', 0.0 ),
		);
	}

	/**
	 * Everything the template prints.
	 *
	 * @return array( 'league' => array|null, 'divisions' => list of array( id, name, url, rows, played, left, next, last, missing ), 'errors' => list, 'notice' => string for editors )
	 */
	public function view() {
		$s   = $this->settings;
		$key = (string) ( $s->league ?? '' );
		$out = array( 'league' => null, 'divisions' => array(), 'errors' => array(), 'notice' => '' );
		if ( ! DS_Programs_Data::configured_sites() ) {
			$out['notice'] = __( 'No LeagueApps site is saved yet. Add one under Settings > DS Toolkit > LeagueApps.', 'ds-toolkit' );
			return $out;
		}
		if ( ! preg_match( '/^\d+:\d+$/', $key ) ) {
			$out['notice'] = __( 'Choose a league on the Standings tab, under League.', 'ds-toolkit' );
			return $out;
		}
		$leagues = DS_Standings_Data::leagues();
		if ( ! isset( $leagues[ $key ] ) ) {
			$out['notice'] = __( 'This league is no longer live on LeagueApps (or its listing is still loading). Choose another on the Standings tab, under League.', 'ds-toolkit' );
			return $out;
		}
		$league        = $leagues[ $key ];
		$out['league'] = $league;
		$divs          = $league['divisions'];
		if ( 'one' === ( $s->div_show ?? 'all' ) ) {
			$one  = (string) ( $s->div_one ?? '' );
			$divs = isset( $divs[ $one ] ) ? array( $one => $divs[ $one ] ) : array_slice( $divs, 0, 1, true );
		}
		$data          = DS_Standings_Data::get( $league['site'], array_map( 'strval', array_keys( $divs ) ) );
		$out['errors'] = $data['errors'];
		$rules         = $this->rules();
		foreach ( $divs as $pid => $d ) {
			$kept = $data['divisions'][ (string) $pid ] ?? null;
			if ( ! $kept ) {
				continue;
			}
			$out['divisions'][] = array(
				'id'      => (string) $pid,
				'name'    => $d['name'],
				'url'     => $d['url'],
				'rows'    => DS_Standings_Data::table( $kept, $rules ),
				'played'  => count( (array) $kept['games'] ),
				'left'    => (int) ( $kept['left'] ?? 0 ),
				'next'    => (int) ( $kept['next'] ?? 0 ),
				'last'    => (int) ( $kept['last'] ?? 0 ),
				'missing' => ! empty( $kept['missing'] ),
			);
		}
		return $out;
	}

	/** The team to mark, normalised for comparing: a name typed in the block, or this page's title. */
	public function highlight() {
		$s    = $this->settings;
		$mode = (string) ( $s->highlight ?? 'none' );
		$name = '';
		if ( 'name' === $mode ) {
			$name = (string) ( $s->highlight_name ?? '' );
		} elseif ( 'page' === $mode ) {
			$id   = function_exists( 'get_queried_object_id' ) ? (int) get_queried_object_id() : 0;
			$name = $id ? (string) get_the_title( $id ) : '';
		}
		return self::norm( $name );
	}

	public static function norm( $name ) {
		$name = strtolower( wp_strip_all_tags( html_entity_decode( (string) $name, ENT_QUOTES, 'UTF-8' ) ) );
		return trim( preg_replace( '/[^a-z0-9]+/', ' ', $name ) );
	}

	/** One cell's text and class. */
	public function cell( array $r, $key ) {
		$v   = $r[ $key ];
		$cls = 'dsst-c-' . $key;
		switch ( $key ) {
			case 'pct':
				if ( 'la' === ( $this->settings->pct_style ?? 'dec' ) ) {
					$txt = rtrim( rtrim( number_format( (float) $v, 2, '.', '' ), '0' ), '.' );
					$txt = false === strpos( $txt, '.' ) ? $txt . '.0' : $txt;
				} else {
					$txt = $v >= 100 ? '1.000' : '.' . str_pad( (string) (int) round( $v * 10 ), 3, '0', STR_PAD_LEFT );
				}
				break;
			case 'pf':
			case 'pa':
			case 'pm':
				$txt = number_format( (float) $v, 1, '.', '' );
				break;
			default:
				$txt = (string) $v;
		}
		if ( in_array( $key, array( 'psd', 'pm' ), true ) ) {
			if ( $v > 0 ) {
				$txt  = '+' . $txt;
				$cls .= ' is-pos';
			} elseif ( $v < 0 ) {
				$txt  = "\u{2212}" . ltrim( $txt, '-' );
				$cls .= ' is-neg';
			} else {
				$cls .= ' is-zero';
			}
		}
		if ( 'w' === $key ) {
			$cls .= ' is-strong';
		}
		return array( $txt, $cls );
	}

	/** A team's LeagueApps page, built from the division's standings address. */
	public static function team_url( $division_url, $team_id ) {
		if ( '' === $division_url || ! preg_match( '#/standings/?$#', $division_url ) ) {
			return '';
		}
		return preg_replace( '#/standings/?$#', '/teams/' . (int) $team_id, $division_url );
	}

	/** "Results through Sunday, Nov 15 · 32 games played, 22 to go · next games Sunday, Nov 22". */
	public static function status_line( array $d ) {
		if ( ! $d['played'] ) {
			return $d['next'] ? sprintf( /* translators: %s: a date */ __( 'First games %s', 'ds-toolkit' ), DS_Standings_Data::day( $d['next'] ) ) : '';
		}
		$parts   = array();
		$parts[] = sprintf( /* translators: %s: a date */ __( 'Results through %s', 'ds-toolkit' ), DS_Standings_Data::day( $d['last'] ) );
		$played  = sprintf( _n( '%d game played', '%d games played', $d['played'], 'ds-toolkit' ), $d['played'] );
		$parts[] = $d['left'] ? $played . ', ' . sprintf( /* translators: %d: games still to play */ __( '%d to go', 'ds-toolkit' ), $d['left'] ) : $played;
		if ( $d['left'] && $d['next'] ) {
			$parts[] = sprintf( /* translators: %s: a date */ __( 'next games %s', 'ds-toolkit' ), DS_Standings_Data::day( $d['next'] ) );
		}
		return implode( ' · ', $parts );
	}

	/** The tiebreaker sentence, from the Rules tab. */
	public function rules_line() {
		$names = array();
		foreach ( $this->rules()['breakers'] as $b ) {
			$names[] = strtolower( DS_Standings_Data::BREAKERS[ $b ] );
		}
		if ( ! $names ) {
			return __( 'Teams are ranked by winning percentage.', 'ds-toolkit' );
		}
		return sprintf( /* translators: %s: a list such as "head to head, then higher point differential" */ __( 'Teams are ranked by winning percentage; ties are broken by %s.', 'ds-toolkit' ), implode( ', then ', $names ) );
	}

	/** Text on a background: white or near-black, whichever reads (the background may be a Global Styles colour). */
	public static function text_on( $bg, $fallback = '#ffffff' ) {
		$c = DS_LA_Colors::rgb( $bg );
		if ( ! $c ) {
			return $fallback;
		}
		$w = DS_LA_Colors::ratio( array( 255, 255, 255 ), $c );
		$d = DS_LA_Colors::ratio( array( 20, 23, 28 ), $c );
		return ( $w >= 4.5 || $w >= $d ) ? '#ffffff' : '#14171c';
	}
}

$ds_st_col = function ( $label, array $extra = array() ) { return DS_Module_UI::color_field( $label, $extra ); };
$ds_st_breakers = array( '' => __( 'None', 'ds-toolkit' ) ) + array_map( '__', DS_Standings_Data::BREAKERS );
$ds_st_cols = array();
foreach ( DS_Standings_Module::COLUMNS as $k => $c ) {
	$ds_st_cols[ $k ] = $c[0];
}

FLBuilder::register_module( 'DS_Standings_Module', array(

	'standings' => array(
		'title'    => __( 'Standings', 'ds-toolkit' ),
		'sections' => array(
			'league' => array(
				'title'  => __( 'League', 'ds-toolkit' ),
				'fields' => array(
					'league'   => array(
						'type'    => 'select',
						'label'   => __( 'League', 'ds-toolkit' ),
						'default' => '',
						'options' => DS_Standings_Module::league_options(),
						'help'    => __( 'Live leagues on the LeagueApps site saved under Settings > DS Toolkit > LeagueApps. A league with divisions shows all of them.', 'ds-toolkit' ),
					),
					'div_show' => array(
						'type'    => 'select',
						'label'   => __( 'Divisions', 'ds-toolkit' ),
						'default' => 'all',
						'options' => array( 'all' => __( 'All divisions', 'ds-toolkit' ), 'one' => __( 'One division', 'ds-toolkit' ) ),
						'toggle'  => array( 'one' => array( 'fields' => array( 'div_one' ) ), 'all' => array( 'fields' => array( 'div_view' ) ) ),
					),
					'div_one'  => array(
						'type'    => 'select',
						'label'   => __( 'Division', 'ds-toolkit' ),
						'default' => '',
						'options' => DS_Standings_Module::division_options(),
					),
					'div_view' => array(
						'type'    => 'select',
						'label'   => __( 'Show Divisions As', 'ds-toolkit' ),
						'default' => 'tabs',
						'options' => array(
							'tabs'    => __( 'Tabs', 'ds-toolkit' ),
							'select'  => __( 'A dropdown', 'ds-toolkit' ),
							'stacked' => __( 'One table under another', 'ds-toolkit' ),
						),
						'help'    => __( 'A dropdown suits a league with many divisions.', 'ds-toolkit' ),
					),
				),
			),
			'layout' => array(
				'title'  => __( 'Layout', 'ds-toolkit' ),
				'fields' => array(
					'layout'    => array(
						'type'    => 'select',
						'label'   => __( 'Layout', 'ds-toolkit' ),
						'default' => 'table',
						'options' => array( 'table' => __( 'Standings table', 'ds-toolkit' ), 'leaders' => __( 'Leader cards (top teams of each division)', 'ds-toolkit' ) ),
						'toggle'  => array(
							'leaders' => array( 'fields' => array( 'leaders_n', 'leaders_link' ) ),
							'table'   => array( 'fields' => array( 'rows_n' ), 'tabs' => array( 'columns' ) ),
						),
					),
					'rows_n'       => array(
						'type'    => 'select',
						'label'   => __( 'Teams', 'ds-toolkit' ),
						'default' => '0',
						'options' => array( '0' => __( 'All teams', 'ds-toolkit' ), '3' => __( 'Top 3', 'ds-toolkit' ), '5' => __( 'Top 5', 'ds-toolkit' ), '8' => __( 'Top 8', 'ds-toolkit' ) ),
					),
					'leaders_n'    => array(
						'type'    => 'select',
						'label'   => __( 'Teams Per Division', 'ds-toolkit' ),
						'default' => '3',
						'options' => array( '1' => '1', '3' => '3', '5' => '5' ),
					),
					'leaders_link' => array(
						'type'        => 'link',
						'label'       => __( 'Full Standings Link', 'ds-toolkit' ),
						'show_target' => false,
						'show_nofollow' => false,
						'help'        => __( 'The page on this site with the full standings. Blank links each card to LeagueApps.', 'ds-toolkit' ),
					),
				),
			),
			'team' => array(
				'title'     => __( 'Highlight a Team', 'ds-toolkit' ),
				'collapsed' => true,
				'fields'    => array(
					'highlight'      => array(
						'type'    => 'select',
						'label'   => __( 'Highlight', 'ds-toolkit' ),
						'default' => 'none',
						'options' => array(
							'none' => __( 'No team', 'ds-toolkit' ),
							'page' => __( 'The team named in this page\'s title', 'ds-toolkit' ),
							'name' => __( 'A team I type', 'ds-toolkit' ),
						),
						'toggle'  => array( 'name' => array( 'fields' => array( 'highlight_name' ) ) ),
						'help'    => __( 'On a team page, "this page\'s title" marks that team\'s row when the title matches its LeagueApps name.', 'ds-toolkit' ),
					),
					'highlight_name' => array(
						'type'  => 'text',
						'label' => __( 'Team Name', 'ds-toolkit' ),
						'help'  => __( 'As it is spelled in LeagueApps.', 'ds-toolkit' ),
					),
				),
			),
			'heading' => array(
				'title'     => __( 'Heading and Notes', 'ds-toolkit' ),
				'collapsed' => true,
				'fields'    => array(
					'title'        => array( 'type' => 'text', 'label' => __( 'Title', 'ds-toolkit' ), 'default' => __( 'Standings', 'ds-toolkit' ), 'help' => __( 'Blank hides the title.', 'ds-toolkit' ) ),
					'eyebrow_show' => DS_Module_UI::show_hide_field( __( 'League Name Above the Title', 'ds-toolkit' ) ),
					'legend'       => DS_Module_UI::show_hide_field( __( 'Column Key', 'ds-toolkit' ), 'show', array( 'help' => __( 'What each abbreviation means, under the table.', 'ds-toolkit' ) ) ),
					'rules_note'   => DS_Module_UI::show_hide_field( __( 'Tiebreaker Note', 'ds-toolkit' ) ),
					'updated'      => DS_Module_UI::show_hide_field( __( 'Results Date and Games Played', 'ds-toolkit' ) ),
					'la_link'      => DS_Module_UI::show_hide_field( __( 'LeagueApps Link', 'ds-toolkit' ) ),
					'la_link_text' => array( 'type' => 'text', 'label' => __( 'LeagueApps Link Text', 'ds-toolkit' ), 'default' => __( 'View on LeagueApps', 'ds-toolkit' ) ),
					'team_links'   => DS_Module_UI::yes_no_field( __( 'Link Team Names to LeagueApps', 'ds-toolkit' ), 'yes' ),
					'empty_text'   => array( 'type' => 'text', 'label' => __( 'Before the First Game', 'ds-toolkit' ), 'default' => __( 'No results yet. Standings appear after the first game.', 'ds-toolkit' ) ),
					'down_text'    => array( 'type' => 'text', 'label' => __( 'When LeagueApps Cannot Be Reached', 'ds-toolkit' ), 'default' => __( 'Standings are temporarily unavailable. Please check back shortly.', 'ds-toolkit' ) ),
				),
			),
		),
	),

	'columns' => array(
		'title'    => __( 'Columns', 'ds-toolkit' ),
		'sections' => array(
			'cols' => array(
				'title'  => __( 'Columns', 'ds-toolkit' ),
				'fields' => array(
					'cols'       => array(
						'type'         => 'button-group',
						'label'        => __( 'Columns', 'ds-toolkit' ),
						'default'      => implode( ',', array_keys( DS_Standings_Module::COLUMNS ) ),
						'multi-select' => true,
						'options'      => $ds_st_cols,
						'help'         => __( 'The same columns as the LeagueApps standings page. Rank and team are always shown.', 'ds-toolkit' ),
					),
					'cols_phone' => array(
						'type'         => 'button-group',
						'label'        => __( 'Columns on Phones', 'ds-toolkit' ),
						'default'      => '',
						'multi-select' => true,
						'options'      => $ds_st_cols,
						'help'         => __( 'None picked = the same columns as above. On phones the rank and team stay in place while the numbers scroll sideways.', 'ds-toolkit' ),
					),
					'group_head' => DS_Module_UI::show_hide_field( __( 'Column Group Labels', 'ds-toolkit' ), 'show', array( 'help' => __( 'Record, Points and Standings points above the column names.', 'ds-toolkit' ) ) ),
					'pct_style'  => array(
						'type'    => 'select',
						'label'   => __( 'Winning Percentage', 'ds-toolkit' ),
						'default' => 'dec',
						'options' => array( 'dec' => '.833', 'la' => __( '83.33 (as on LeagueApps)', 'ds-toolkit' ) ),
					),
				),
			),
		),
	),

	'rules' => array(
		'title'    => __( 'Rules', 'ds-toolkit' ),
		'sections' => array(
			'tiebreak' => array(
				'title'       => __( 'Tiebreakers', 'ds-toolkit' ),
				'description' => __( 'Set these to the league\'s own rules in LeagueApps (the "View Tiebreaker Rules" link on its standings page). LeagueApps does not send them with the results.', 'ds-toolkit' ),
				'fields'      => array(
					'tb1' => array( 'type' => 'select', 'label' => __( 'First', 'ds-toolkit' ), 'default' => 'h2h', 'options' => $ds_st_breakers ),
					'tb2' => array( 'type' => 'select', 'label' => __( 'Then', 'ds-toolkit' ), 'default' => 'psd', 'options' => $ds_st_breakers ),
					'tb3' => array( 'type' => 'select', 'label' => __( 'Then', 'ds-toolkit' ), 'default' => 'psa', 'options' => $ds_st_breakers ),
				),
			),
			'points' => array(
				'title'       => __( 'Standings Points', 'ds-toolkit' ),
				'description' => __( 'What PF, PA and +/- count. Most youth leagues give 1 for a win; soccer often 3, 1 and 0.', 'ds-toolkit' ),
				'fields'      => array(
					'pts_win'     => array( 'type' => 'unit', 'label' => __( 'Win', 'ds-toolkit' ), 'default' => '1', 'slider' => array( 'min' => 0, 'max' => 5, 'step' => 0.5 ) ),
					'pts_tie'     => array( 'type' => 'unit', 'label' => __( 'Tie', 'ds-toolkit' ), 'default' => '0.5', 'slider' => array( 'min' => 0, 'max' => 5, 'step' => 0.5 ) ),
					'pts_loss'    => array( 'type' => 'unit', 'label' => __( 'Loss', 'ds-toolkit' ), 'default' => '0', 'slider' => array( 'min' => 0, 'max' => 5, 'step' => 0.5 ) ),
					'pts_forfeit' => array( 'type' => 'unit', 'label' => __( 'Taken Off Per Forfeit', 'ds-toolkit' ), 'default' => '0', 'slider' => array( 'min' => 0, 'max' => 5, 'step' => 0.5 ), 'help' => __( 'Shown as PA.', 'ds-toolkit' ) ),
				),
			),
		),
	),

	'style' => array(
		'title'    => __( 'Style', 'ds-toolkit' ),
		'sections' => array(
			'colours' => array(
				'title'  => __( 'Colours', 'ds-toolkit' ),
				'fields' => array(
					'brand'     => $ds_st_col( __( 'Main Colour', 'ds-toolkit' ), array( 'help' => __( 'The header band, active tab, group lines and highlighted team. Blank = Global Styles Primary.', 'ds-toolkit' ) ) ),
					'head_bg'   => $ds_st_col( __( 'Header Background', 'ds-toolkit' ), array( 'help' => __( 'Blank = the main colour.', 'ds-toolkit' ) ) ),
					'head_text' => $ds_st_col( __( 'Header Text', 'ds-toolkit' ), array( 'help' => __( 'Blank = white or dark, whichever reads on the header.', 'ds-toolkit' ) ) ),
					'card_bg'   => $ds_st_col( __( 'Table Background', 'ds-toolkit' ), array( 'help' => __( 'Blank = white.', 'ds-toolkit' ) ) ),
					'zebra_bg'  => $ds_st_col( __( 'Alternate Row Background', 'ds-toolkit' ), array( 'show_alpha' => true, 'help' => __( 'Blank = a light grey.', 'ds-toolkit' ) ) ),
					'hl_bg'     => $ds_st_col( __( 'Highlighted Team Background', 'ds-toolkit' ), array( 'help' => __( 'Blank = a tint of the main colour.', 'ds-toolkit' ) ) ),
					'text'      => $ds_st_col( __( 'Text', 'ds-toolkit' ) ),
					'muted'     => $ds_st_col( __( 'Secondary Text', 'ds-toolkit' ), array( 'help' => __( 'Ranks, notes and zero values.', 'ds-toolkit' ) ) ),
					'line'      => $ds_st_col( __( 'Lines', 'ds-toolkit' ) ),
					'pos'       => $ds_st_col( __( 'Positive Differential', 'ds-toolkit' ), array( 'help' => __( 'Blank = green.', 'ds-toolkit' ) ) ),
					'neg'       => $ds_st_col( __( 'Negative Differential', 'ds-toolkit' ), array( 'help' => __( 'Blank = red.', 'ds-toolkit' ) ) ),
				),
			),
			'size' => array(
				'title'  => __( 'Size and Shape', 'ds-toolkit' ),
				'fields' => array(
					'font_size'  => DS_Module_UI::unit_field( __( 'Table Text Size', 'ds-toolkit' ), 11, 20, array( 'responsive' => true, 'help' => __( 'Blank = 15px, 13px on phones.', 'ds-toolkit' ) ) ),
					'title_size' => DS_Module_UI::unit_field( __( 'Title Size', 'ds-toolkit' ), 16, 60, array( 'responsive' => true, 'help' => __( 'Blank = 30px. The title uses the site\'s heading font.', 'ds-toolkit' ) ) ),
					'radius'     => DS_Module_UI::unit_field( __( 'Corner Radius', 'ds-toolkit' ), 0, 30, array( 'help' => __( 'Blank = 10px.', 'ds-toolkit' ) ) ),
					'frame'      => DS_Module_UI::show_hide_field( __( 'Border and Background Around the Block', 'ds-toolkit' ), 'show', array( 'help' => __( 'Hide it when the row already gives the block a card.', 'ds-toolkit' ) ) ),
				),
			),
		),
	),
) );
