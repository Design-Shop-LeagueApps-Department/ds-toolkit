<?php
/**
 * @var DS_Calendar_Module $module
 * @var object             $settings
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$data  = $module->calendar_data();
$today = ( new DateTime( 'now', new DateTimeZone( DS_Calendar_Data::TZ ) ) )->format( 'Y-m-d' );
$yes   = function ( $k, $d = 'yes' ) use ( $settings ) { return 'yes' === ( isset( $settings->$k ) && '' !== $settings->$k ? $settings->$k : $d ); };
$txt   = function ( $k, $d ) use ( $settings ) { return isset( $settings->$k ) ? sanitize_text_field( (string) $settings->$k ) : $d; };

// Subscribe feed: only for a module saved on a published post the feed can read back.
$feed = '';
if ( $yes( 'sub_show' ) && class_exists( 'FLBuilderModel' ) ) {
	$pid  = (int) FLBuilderModel::get_post_id();
	$feed = $pid && DS_LA_Calendar::node_settings( $pid, $module->node ) ? rest_url( 'ds-toolkit/v1/calendar/' . $pid . '/' . $module->node ) : '';
}

$config = array(
	'viewDesktop'    => 'list' === ( $settings->view_desktop ?? 'month' ) ? 'list' : 'month',
	'viewPhone'      => 'month' === ( $settings->view_phone ?? 'list' ) ? 'month' : 'list',
	'maxChips'       => max( 1, min( 12, DS_Module_UI::u( $settings->max_chips ?? '', 3 ) ) ),
	'collapseAfter'  => max( 2, DS_Module_UI::u( $settings->collapse_after ?? '', 6 ) ),
	'groupByAge'     => $yes( 'group_by_age' ),
	'showTeams'      => $yes( 'show_teams' ),
	'canSwitch'      => $yes( 'show_switch' ),
	'rail'           => $yes( 'show_rail' ),
	'railTitle'      => $txt( 'rail_title', __( 'Coming up', 'ds-toolkit' ) ),
	'railCount'      => max( 1, min( 10, DS_Module_UI::u( $settings->rail_count ?? '', 5 ) ) ),
	'websiteText'    => $txt( 'website_text', __( 'Event website', 'ds-toolkit' ) ),
	'directionsText' => $txt( 'directions_text', __( 'Directions', 'ds-toolkit' ) ),
	'emptyText'      => $txt( 'empty_text', __( 'No upcoming events yet. Please check back soon.', 'ds-toolkit' ) ),
	'teamAll'        => $txt( 'team_all', __( 'All teams', 'ds-toolkit' ) ),
	'registerText'   => $txt( 'register_text', __( 'Register', 'ds-toolkit' ) ),
	'detailsText'    => $txt( 'details_text', __( 'Program details', 'ds-toolkit' ) ),
	'addText'        => $yes( 'add_show' ) ? $txt( 'add_text', __( 'Add to calendar', 'ds-toolkit' ) ) : '',
	'subText'        => $txt( 'sub_text', __( 'Subscribe', 'ds-toolkit' ) ),
	'feed'           => $feed ? esc_url_raw( $feed ) : '',
	'calName'        => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
	'tz'             => DS_Calendar_Data::TZ,
	'teamsLabel'     => __( 'Teams', 'ds-toolkit' ),
	'programsLabel'  => __( 'Programs', 'ds-toolkit' ),
);
$chip     = (string) ( $settings->chip_style ?? 'soft' );
$chip     = in_array( $chip, array( 'soft', 'outline', 'bar' ), true ) ? $chip : 'soft';
$by_type  = 'type' === DS_Calendar_Module::color_mode( $settings );
$note     = $txt( 'note_text', __( 'Times are Eastern.', 'ds-toolkit' ) );
$title_id = 'dslc-dt-' . $module->node;
$filter   = $yes( 'show_team_filter' ) && count( $data['teams'] ) + count( (array) $data['programs'] ) > 1;
$switch   = $yes( 'show_switch' );
$svg      = function ( $d ) { return '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false"><path d="' . $d . '" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'; };
?>
<div class="dslc dslc--chip-<?php echo esc_attr( $chip ); ?><?php echo $by_type ? ' dslc--by-type' : ''; ?>" data-today="<?php echo esc_attr( $today ); ?>">
	<?php if ( ! empty( $data['no_sites'] ) ) : ?>
		<?php if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) : ?>
			<p class="dslc-empty">
				<?php esc_html_e( 'Editors only: add the LeagueApps site ID and API key under Settings > DS Toolkit > LeagueApps, then this calendar fills in by itself.', 'ds-toolkit' ); ?>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<a href="<?php echo esc_url( admin_url( 'options-general.php?page=ds-toolkit&tab=leagueapps' ) ); ?>"><?php esc_html_e( 'Open the LeagueApps settings', 'ds-toolkit' ); ?></a>
				<?php endif; ?>
			</p>
		<?php endif; ?>
	<?php else : ?>
		<div class="dslc-cal">
			<div class="dslc-bar">
				<div class="dslc-title">
					<h2 class="dslc-month" aria-live="polite"></h2>
					<span class="dslc-count"></span>
				</div>
				<div class="dslc-nav">
					<?php if ( $yes( 'show_today' ) ) : ?>
						<button type="button" class="fl-content-ui-button dslc-btn dslc-today"><?php esc_html_e( 'Today', 'ds-toolkit' ); ?></button>
					<?php endif; ?>
					<button type="button" class="fl-content-ui-button dslc-btn dslc-icon dslc-prev" aria-label="<?php esc_attr_e( 'Previous month', 'ds-toolkit' ); ?>"><?php echo $svg( 'M10 3 5 8l5 5' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?></button>
					<button type="button" class="fl-content-ui-button dslc-btn dslc-icon dslc-next" aria-label="<?php esc_attr_e( 'Next month', 'ds-toolkit' ); ?>"><?php echo $svg( 'm6 3 5 5-5 5' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed markup ?></button>
				</div>
				<?php if ( $filter || $switch ) : ?>
					<div class="dslc-ctrls">
						<?php if ( $filter ) : ?>
							<label class="dslc-filter">
								<span><?php echo esc_html( $txt( 'team_label', __( 'Team', 'ds-toolkit' ) ) ); ?></span>
								<select class="dslc-team"><option value=""><?php echo esc_html( $config['teamAll'] ); ?></option></select>
							</label>
						<?php endif; ?>
						<?php if ( $switch ) : ?>
							<div class="dslc-views" role="group" aria-label="<?php esc_attr_e( 'View', 'ds-toolkit' ); ?>">
								<button type="button" class="fl-content-ui-button dslc-view" data-view="month" aria-pressed="false"><?php echo esc_html( $txt( 'month_text', __( 'Month', 'ds-toolkit' ) ) ); ?></button>
								<button type="button" class="fl-content-ui-button dslc-view" data-view="list" aria-pressed="false"><?php echo esc_html( $txt( 'list_text', __( 'List', 'ds-toolkit' ) ) ); ?></button>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
			<div class="dslc-body"></div>
			<?php if ( '' !== trim( $note ) || $feed || $by_type ) : ?>
				<div class="dslc-foot">
					<span class="dslc-fnote"><?php echo esc_html( $note ); ?></span>
					<div class="dslc-foot-r">
						<span class="dslc-legend"></span>
						<?php if ( $feed && '' !== $config['subText'] ) : ?>
							<div class="dslc-menu" data-menu="sub">
								<button type="button" class="fl-content-ui-button dslc-btn dslc-menu-btn" aria-expanded="false" aria-haspopup="true"><svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false"><path d="M4.5 9.5a5.5 5.5 0 0 1 9.4-3.9l1.6 1.6M15.5 3.5v3.7h-3.7M15.5 10.5a5.5 5.5 0 0 1-9.4 3.9l-1.6-1.6M4.5 16.5v-3.7h3.7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg><span><?php echo esc_html( $config['subText'] ); ?></span></button>
								<div class="dslc-pop" hidden></div>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( ! empty( $data['stale'] ) && is_user_logged_in() && current_user_can( 'edit_posts' ) ) : ?>
				<p class="dslc-enote"><?php esc_html_e( 'Editors only: LeagueApps did not respond just now, so this is the last good copy of the schedule.', 'ds-toolkit' ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $data['checks'] ) && is_user_logged_in() && current_user_can( 'edit_posts' ) ) : ?>
				<p class="dslc-enote"><?php echo esc_html( sprintf( /* translators: %s: list of programs */ __( 'Editors only, LeagueApps data to check (the calendar shows LeagueApps\' date): %s', 'ds-toolkit' ), implode( '; ', (array) $data['checks'] ) ) ); ?></p>
			<?php endif; ?>
			<?php if ( ! empty( $data['errors'] ) && is_user_logged_in() && current_user_can( 'manage_options' ) ) : ?>
				<p class="dslc-enote"><?php echo esc_html( sprintf( /* translators: %s: error detail */ __( 'Admins only, LeagueApps schedules: %s', 'ds-toolkit' ), implode( '; ', (array) $data['errors'] ) ) ); ?></p>
			<?php endif; ?>
		</div>
		<dialog class="dslc-dialog" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
			<div class="dslc-dialog-body" data-title-id="<?php echo esc_attr( $title_id ); ?>"></div>
		</dialog>
		<script type="application/json" class="dslc-data"><?php
			// HEX flags: no "</script>", quote or ampersand from LeagueApps text can break out of this tag.
			echo wp_json_encode( array( 'events' => $data['events'], 'teams' => $data['teams'], 'programs' => array_values( (array) $data['programs'] ), 'config' => $config ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		?></script>
		<noscript><p><?php esc_html_e( 'Turn on JavaScript to see the calendar.', 'ds-toolkit' ); ?></p></noscript>
	<?php endif; ?>
</div>
