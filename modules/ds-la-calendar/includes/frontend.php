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

$config = array(
	'viewDesktop'    => 'list' === ( $settings->view_desktop ?? 'month' ) ? 'list' : 'month',
	'viewPhone'      => 'month' === ( $settings->view_phone ?? 'list' ) ? 'month' : 'list',
	'maxChips'       => max( 1, min( 12, DS_Module_UI::u( $settings->max_chips ?? '', 3 ) ) ),
	'collapseAfter'  => max( 2, DS_Module_UI::u( $settings->collapse_after ?? '', 6 ) ),
	'groupByAge'     => $yes( 'group_by_age' ),
	'showTeams'      => $yes( 'show_teams' ),
	'websiteText'    => $txt( 'website_text', __( 'Event website', 'ds-toolkit' ) ),
	'directionsText' => $txt( 'directions_text', __( 'Directions', 'ds-toolkit' ) ),
	'emptyText'      => $txt( 'empty_text', __( 'No upcoming events yet. Please check back soon.', 'ds-toolkit' ) ),
	'teamAll'        => $txt( 'team_all', __( 'All teams', 'ds-toolkit' ) ),
	'registerText'   => $txt( 'register_text', __( 'Register', 'ds-toolkit' ) ),
	'detailsText'    => $txt( 'details_text', __( 'Program details', 'ds-toolkit' ) ),
	'teamsLabel'     => __( 'Teams', 'ds-toolkit' ),
	'programsLabel'  => __( 'Programs', 'ds-toolkit' ),
);
$chip    = (string) ( $settings->chip_style ?? 'soft' );
$chip    = in_array( $chip, array( 'soft', 'outline', 'bar' ), true ) ? $chip : 'soft';
$note    = $txt( 'note_text', __( 'Times are Eastern.', 'ds-toolkit' ) );
$title_id = 'dslc-dt-' . $module->node;
?>
<div class="dslc dslc--chip-<?php echo esc_attr( $chip ); ?>" data-today="<?php echo esc_attr( $today ); ?>">
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
		<div class="dslc-bar">
			<?php if ( $yes( 'show_switch' ) ) : ?>
				<div class="dslc-views" role="group" aria-label="<?php esc_attr_e( 'View', 'ds-toolkit' ); ?>">
					<button type="button" class="fl-content-ui-button dslc-view" data-view="month" aria-pressed="false"><?php echo esc_html( $txt( 'month_text', __( 'Month', 'ds-toolkit' ) ) ); ?></button>
					<button type="button" class="fl-content-ui-button dslc-view" data-view="list" aria-pressed="false"><?php echo esc_html( $txt( 'list_text', __( 'List', 'ds-toolkit' ) ) ); ?></button>
				</div>
			<?php endif; ?>
			<div class="dslc-nav">
				<button type="button" class="fl-content-ui-button dslc-prev" aria-label="<?php esc_attr_e( 'Previous month', 'ds-toolkit' ); ?>">&#8249;</button>
				<h2 class="dslc-month" aria-live="polite"></h2>
				<button type="button" class="fl-content-ui-button dslc-next" aria-label="<?php esc_attr_e( 'Next month', 'ds-toolkit' ); ?>">&#8250;</button>
			</div>
			<?php if ( $yes( 'show_team_filter' ) && count( $data['teams'] ) + count( (array) $data['programs'] ) > 1 ) : ?>
				<label class="dslc-filter">
					<span><?php echo esc_html( $txt( 'team_label', __( 'Team', 'ds-toolkit' ) ) ); ?></span>
					<select class="dslc-team"><option value=""><?php echo esc_html( $config['teamAll'] ); ?></option></select>
				</label>
			<?php endif; ?>
		</div>
		<div class="dslc-body"></div>
		<?php if ( '' !== trim( $note ) ) : ?>
			<p class="dslc-foot"><?php echo esc_html( $note ); ?></p>
		<?php endif; ?>
		<dialog class="dslc-dialog" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
			<div class="dslc-dialog-in">
				<button type="button" class="fl-content-ui-button dslc-close" aria-label="<?php esc_attr_e( 'Close', 'ds-toolkit' ); ?>">&times;</button>
				<div class="dslc-dialog-body" data-title-id="<?php echo esc_attr( $title_id ); ?>"></div>
			</div>
		</dialog>
		<script type="application/json" class="dslc-data"><?php
			// HEX flags: no "</script>", quote or ampersand from LeagueApps text can break out of this tag.
			echo wp_json_encode( array( 'events' => $data['events'], 'teams' => $data['teams'], 'programs' => array_values( (array) $data['programs'] ), 'config' => $config ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
		?></script>
		<noscript><p><?php esc_html_e( 'Turn on JavaScript to see the calendar.', 'ds-toolkit' ); ?></p></noscript>
		<?php if ( ! empty( $data['stale'] ) && is_user_logged_in() && current_user_can( 'edit_posts' ) ) : ?>
			<p class="dslc-foot"><?php esc_html_e( 'Editors only: LeagueApps did not respond just now, so this is the last good copy of the schedule.', 'ds-toolkit' ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $data['checks'] ) && is_user_logged_in() && current_user_can( 'edit_posts' ) ) : ?>
			<p class="dslc-foot"><?php echo esc_html( sprintf( /* translators: %s: list of programs */ __( 'Editors only, LeagueApps data to check (the calendar shows LeagueApps\' date): %s', 'ds-toolkit' ), implode( '; ', (array) $data['checks'] ) ) ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $data['errors'] ) && is_user_logged_in() && current_user_can( 'manage_options' ) ) : ?>
			<p class="dslc-foot"><?php echo esc_html( sprintf( /* translators: %s: error detail */ __( 'Admins only, LeagueApps schedules: %s', 'ds-toolkit' ), implode( '; ', (array) $data['errors'] ) ) ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
</div>
