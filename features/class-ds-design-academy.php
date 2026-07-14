<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Design Academy dashboard panel (fleet-wide — every install).
 *
 * Puts the LeagueApps Design Academy (designacademy.leagueapps.com) in front
 * of partners the moment they log in: a widget pinned to the TOP of the
 * WordPress dashboard with
 *
 *  - a pinned "start here" course (defaults to the beginner's guide to
 *    WordPress & Beaver Builder; both URL and label editable in DS Toolkit →
 *    Features), and
 *  - the five newest tutorials, pulled from the academy's public REST API
 *    (`/wp-json/wp/v2/tutorial`) and cached for six hours. If the academy is
 *    unreachable the last good list is served from a backup option, so the
 *    dashboard never waits on or breaks over a remote fetch.
 *
 * Everyone who can see the dashboard sees the panel — it's aimed at partner
 * editors, but the links are just as useful to LeagueApps staff.
 */
class DS_Design_Academy {

	const SITE      = 'https://designacademy.leagueapps.com';
	const CACHE_KEY = 'ds_academy_tutorials';

	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = $settings;
	}

	public function init() {
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
	}

	public function register_widget() {
		wp_add_dashboard_widget( 'ds_design_academy', __( 'Design Academy', 'ds-toolkit' ), array( $this, 'render_widget' ) );

		// Move the widget to the FIRST slot of the main column so it's the
		// first thing a partner sees on login (WP appends new widgets last).
		global $wp_meta_boxes;
		$core = $wp_meta_boxes['dashboard']['normal']['core'] ?? array();
		if ( isset( $core['ds_design_academy'] ) ) {
			$ours = array( 'ds_design_academy' => $core['ds_design_academy'] );
			unset( $core['ds_design_academy'] );
			$wp_meta_boxes['dashboard']['normal']['core'] = $ours + $core;
		}
	}

	/**
	 * Latest tutorials from the academy REST API. Cached 6h in a transient;
	 * the last successful fetch is also kept in a no-autoload option so a
	 * temporary outage degrades to slightly stale links, never an empty box.
	 */
	private function tutorials() {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$res = wp_remote_get(
			self::SITE . '/wp-json/wp/v2/tutorial?per_page=5&orderby=date&order=desc&_fields=title,link,date',
			array( 'timeout' => 8 )
		);
		if ( is_wp_error( $res ) || 200 !== wp_remote_retrieve_response_code( $res ) ) {
			// Short negative cache so a down academy isn't re-tried on every load.
			set_transient( self::CACHE_KEY, get_option( self::CACHE_KEY . '_backup', array() ), 15 * MINUTE_IN_SECONDS );
			return get_option( self::CACHE_KEY . '_backup', array() );
		}
		$rows = json_decode( wp_remote_retrieve_body( $res ), true );
		$list = array();
		foreach ( ( is_array( $rows ) ? $rows : array() ) as $row ) {
			$title = isset( $row['title']['rendered'] ) ? wp_strip_all_tags( (string) $row['title']['rendered'] ) : '';
			$link  = isset( $row['link'] ) ? esc_url_raw( (string) $row['link'] ) : '';
			if ( '' === $title || '' === $link ) {
				continue;
			}
			$list[] = array(
				'title' => html_entity_decode( $title, ENT_QUOTES ),
				'link'  => $link,
				'date'  => substr( (string) ( $row['date'] ?? '' ), 0, 10 ),
			);
		}
		set_transient( self::CACHE_KEY, $list, 6 * HOUR_IN_SECONDS );
		update_option( self::CACHE_KEY . '_backup', $list, false );
		return $list;
	}

	public function render_widget() {
		$pin_url   = ! empty( $this->settings['academy_pinned_url'] )
			? esc_url( $this->settings['academy_pinned_url'] )
			: self::SITE . '/course/how-to-edit-your-website-a-beginners-guide-to-wordpress-beaverbuilder/';
		$pin_label = ! empty( $this->settings['academy_pinned_label'] )
			? $this->settings['academy_pinned_label']
			: __( "How to Edit Your Website: A Beginner's Guide to WordPress & Beaver Builder", 'ds-toolkit' );

		// Pinned "start here" course.
		echo '<div style="border:1px solid #c3c4c7;border-left:4px solid #2271b1;background:#f6f7f7;border-radius:2px;padding:10px 12px;margin:2px 0 12px;">';
		echo '<span class="dashicons dashicons-sticky" aria-hidden="true" style="color:#2271b1;margin-right:4px;"></span>';
		echo '<strong>' . esc_html__( 'Start here:', 'ds-toolkit' ) . '</strong> ';
		echo '<a href="' . esc_url( $pin_url ) . '" target="_blank" rel="noopener">' . esc_html( $pin_label ) . '</a>';
		echo '</div>';

		// Latest tutorials.
		$tutorials = $this->tutorials();
		if ( ! empty( $tutorials ) ) {
			echo '<p style="margin:0 0 4px;"><strong>' . esc_html__( 'Latest tutorials', 'ds-toolkit' ) . '</strong></p>';
			echo '<ul style="margin:0 0 12px;">';
			foreach ( $tutorials as $t ) {
				echo '<li style="margin-bottom:6px;">';
				echo '<a href="' . esc_url( $t['link'] ) . '" target="_blank" rel="noopener">' . esc_html( $t['title'] ) . '</a>';
				if ( ! empty( $t['date'] ) ) {
					echo ' <span style="color:#787c82;font-size:11px;">' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $t['date'] ) ) ) . '</span>';
				}
				echo '</li>';
			}
			echo '</ul>';
		}

		// Footer links.
		echo '<p style="margin:0;border-top:1px solid #f0f0f1;padding-top:8px;">';
		echo '<a href="' . esc_url( self::SITE . '/all-tutorials/' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Browse all tutorials', 'ds-toolkit' ) . ' &rarr;</a>';
		echo ' &nbsp;|&nbsp; ';
		echo '<a href="' . esc_url( self::SITE . '/courses/' ) . '" target="_blank" rel="noopener">' . esc_html__( 'Courses', 'ds-toolkit' ) . '</a>';
		echo ' &nbsp;|&nbsp; ';
		echo '<a href="' . esc_url( self::SITE ) . '" target="_blank" rel="noopener">' . esc_html__( 'Visit the Design Academy', 'ds-toolkit' ) . '</a>';
		echo '</p>';
	}
}
