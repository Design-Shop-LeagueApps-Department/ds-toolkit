<?php
/**
 * @var DS_Schedule_Module $module
 * @var object             $settings
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$d       = $module->schedule();
$yes     = function ( $k, $def = 'yes' ) use ( $settings ) { return 'yes' === ( isset( $settings->$k ) && '' !== $settings->$k ? $settings->$k : $def ); };
$txt     = function ( $k, $def ) use ( $settings ) { return isset( $settings->$k ) && '' !== trim( (string) $settings->$k ) ? sanitize_text_field( (string) $settings->$k ) : $def; };
$compact = 'compact' === (string) ( $settings->layout ?? 'table' );
$limit   = max( 1, min( 200, (int) ( '' !== (string) ( $settings->rows ?? '' ) ? $settings->rows : 8 ) ) );
$more    = ! $compact && $yes( 'show_more' );
$months  = ! $compact && $yes( 'show_months' );

// The link under the list: the module's own link, else the team's schedule page on LeagueApps.
$link_url = '';
$link_new = false;
if ( $yes( 'link_show' ) ) {
	$own = trim( (string) ( $settings->link_url ?? '' ) );
	if ( '' !== $own ) {
		$link_url = 0 === strpos( $own, '#' ) ? '#' . sanitize_html_class( substr( $own, 1 ) ) : esc_url( $own );
		$link_new = '_blank' === (string) ( $settings->link_url_target ?? '' );
	} elseif ( '' !== $d['url'] ) {
		$link_url = esc_url( $d['url'] );
		$link_new = true;
	}
}
$link_text = $txt( 'link_text', __( 'Full schedule on LeagueApps', 'ds-toolkit' ) );

$initial = $d['initial'];
$shown   = 0;
$visible = function ( array $r ) use ( $initial, $limit, &$shown ) {
	if ( '' !== $initial && $r['grp'] !== $initial ) {
		return false;
	}
	return ++$shown <= $limit;
};
$badge = function ( array $r, $with_month ) {
	$p = DS_Schedule_Data::date_parts( $r );
	$h = '<span class="dss-badge" aria-hidden="true">';
	if ( $with_month ) {
		$h .= '<span class="dss-mon">' . esc_html( $p['mon'] ) . '</span>';
	}
	$h .= '<span class="dss-num">' . esc_html( $p['num'] ) . '</span><span class="dss-dow">' . esc_html( $p['dow'] ) . '</span></span>';
	return $h . '<span class="screen-reader-text">' . esc_html( $p['full'] ) . '</span>';
};
$when = function ( array $r ) {
	return '' !== $r['time'] ? $r['time'] : __( 'All day', 'ds-toolkit' );
};
$where = function ( array $r ) {
	if ( '' === $r['loc'] ) {
		return '<span class="dss-tbd">' . esc_html__( 'Location TBD', 'ds-toolkit' ) . '</span>';
	}
	if ( '' === $r['map'] ) {
		return esc_html( $r['loc'] );
	}
	return '<a class="dss-map" href="' . esc_url( $r['map'] ) . '" target="_blank" rel="noopener"><svg aria-hidden="true" viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg>' . esc_html( $r['loc'] ) . '<span class="screen-reader-text"> ' . esc_html__( '(map)', 'ds-toolkit' ) . '</span></a>'; // Beaver Builder adds "(opens in new tab)" itself
};
$tag = function ( array $r ) {
	return '<span class="dss-type dss-type--' . esc_attr( 'practice' === $r['grp'] ? 'practice' : 'main' ) . '">' . esc_html( '' !== $r['label'] ? $r['label'] : $r['type'] ) . '</span>';
};
?>
<div class="dss dss--<?php echo $compact ? 'compact' : 'table'; ?>" data-rows="<?php echo (int) $limit; ?>"<?php echo '' !== $initial ? ' data-f="' . esc_attr( $initial ) . '"' : ''; ?>>
<?php if ( 'pick' === $d['state'] ) : ?>
	<?php if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) : ?>
		<p class="dss-empty"><?php esc_html_e( 'Editors only: pick the LeagueApps team in this block\'s settings.', 'ds-toolkit' ); ?></p>
	<?php else : ?>
		<p class="dss-empty"><?php echo esc_html( $txt( 'empty_text', __( 'The schedule for this team will be posted soon.', 'ds-toolkit' ) ) ); ?></p>
	<?php endif; ?>
<?php elseif ( 'none' === $d['state'] ) : ?>
	<p class="dss-empty"><?php echo esc_html( $txt( 'empty_text', __( 'The schedule for this team will be posted soon.', 'ds-toolkit' ) ) ); ?></p>
<?php elseif ( $compact ) : ?>
	<ol class="dss-next">
		<?php foreach ( array_slice( $d['rows'], 0, $limit ) as $r ) : ?>
			<li class="dss-item<?php echo $r['past'] ? ' dss-past' : ''; ?>">
				<?php echo $badge( $r, true ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>
				<span class="dss-body">
					<?php echo $tag( $r ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<span class="dss-title"><?php echo esc_html( $r['title'] ); ?></span>
					<span class="dss-meta"><span><?php echo esc_html( $when( $r ) ); ?></span><span><?php echo $where( $r ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span></span>
				</span>
			</li>
		<?php endforeach; ?>
	</ol>
<?php else : ?>
	<?php if ( $d['groups'] ) : ?>
		<div class="dss-filters" role="group" aria-label="<?php esc_attr_e( 'Filter the schedule', 'ds-toolkit' ); ?>">
			<button type="button" class="fl-content-ui-button dss-chip" data-f="main" aria-pressed="true"><?php echo esc_html( $d['main_label'] ); ?> <span><?php echo (int) $d['groups']['main']; ?></span></button>
			<button type="button" class="fl-content-ui-button dss-chip" data-f="practice" aria-pressed="false"><?php esc_html_e( 'Practices', 'ds-toolkit' ); ?> <span><?php echo (int) $d['groups']['practice']; ?></span></button>
			<button type="button" class="fl-content-ui-button dss-chip" data-f="" aria-pressed="false"><?php esc_html_e( 'All', 'ds-toolkit' ); ?> <span><?php echo (int) ( $d['groups']['main'] + $d['groups']['practice'] ); ?></span></button>
		</div>
	<?php endif; ?>
	<table class="dss-table">
		<caption class="screen-reader-text"><?php esc_html_e( 'Schedule', 'ds-toolkit' ); ?></caption>
		<thead>
			<tr><th scope="col"><?php esc_html_e( 'Date', 'ds-toolkit' ); ?></th><th scope="col"><?php esc_html_e( 'Event', 'ds-toolkit' ); ?></th><th scope="col"><?php esc_html_e( 'Time', 'ds-toolkit' ); ?></th><th scope="col"><?php esc_html_e( 'Location', 'ds-toolkit' ); ?></th></tr>
		</thead>
		<?php
		$by_month = array();
		foreach ( $d['rows'] as $r ) {
			$by_month[ $months ? $r['month'] : 'all' ][] = $r;
		}
		foreach ( $by_month as $m => $list ) :
			$rows_html = '';
			$any       = false;
			foreach ( $list as $r ) {
				$on  = $visible( $r );
				$any = $any || $on;
				$rows_html .= '<tr class="dss-row' . ( $r['past'] ? ' dss-past' : '' ) . '" data-g="' . esc_attr( $r['grp'] ) . '"' . ( $on ? '' : ' hidden' ) . '>'
					. '<td class="dss-date">' . $badge( $r, ! $months ) . '</td>'
					. '<td class="dss-ev">' . $tag( $r ) . '<span class="dss-title">' . esc_html( $r['title'] ) . '</span></td>'
					. '<td class="dss-time">' . esc_html( $when( $r ) ) . '</td>'
					. '<td class="dss-loc">' . $where( $r ) . '</td>'
					. '</tr>';
			}
			?>
			<tbody<?php echo $any ? '' : ' hidden'; ?>>
				<?php if ( $months ) : ?>
					<tr class="dss-month"><th colspan="4" scope="colgroup"><?php echo esc_html( wp_date( 'F Y', ( new DateTime( $m . '-15 12:00', new DateTimeZone( DS_Schedule_Data::TZ ) ) )->getTimestamp(), new DateTimeZone( DS_Schedule_Data::TZ ) ) ); ?></th></tr>
				<?php endif; ?>
				<?php echo $rows_html; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts above ?>
			</tbody>
		<?php endforeach; ?>
	</table>
	<?php if ( $more ) : ?>
		<button type="button" class="fl-content-ui-button dss-more"<?php echo $shown > $limit ? '' : ' hidden'; ?>><?php esc_html_e( 'Show more', 'ds-toolkit' ); ?></button>
	<?php endif; ?>
<?php endif; ?>
<?php if ( 'ok' === $d['state'] && '' !== $link_url ) : ?>
	<p class="dss-link"><a href="<?php echo $link_url; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url / sanitize_html_class above ?>"<?php echo $link_new ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html( $link_text ); ?><span aria-hidden="true"> →</span></a></p><?php // Beaver Builder's page script adds the "(opens in new tab)" notice to target=_blank links ?>
<?php endif; ?>
</div>
