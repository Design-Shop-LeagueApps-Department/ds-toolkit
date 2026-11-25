<?php
/**
 * @var DS_Standings_Module $module
 * @var object              $settings
 * @var string              $id
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$view    = $module->view();
$builder = class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_active();
$shown   = function ( $k ) use ( $settings ) { return 'hide' !== ( $settings->$k ?? 'show' ); };
$txt     = function ( $k, $d ) use ( $settings ) { return ( isset( $settings->$k ) && '' !== trim( (string) $settings->$k ) ) ? (string) $settings->$k : $d; };

if ( '' !== $view['notice'] ) {
	if ( $builder ) {
		echo '<div class="dsst dsst-notice">' . esc_html( $view['notice'] ) . '</div>';
	}
	return;
}

$league  = $view['league'];
$divs    = $view['divisions'];
$layout  = in_array( ( $settings->layout ?? 'table' ), array( 'leaders', 'carousel', 'ticker' ), true ) ? (string) $settings->layout : 'table';
$cards   = in_array( $layout, array( 'leaders', 'carousel' ), true ); // the carousel is the leader cards in one sideways row
$arrows  = 'carousel' === $layout && $shown( 'car_arrows' );
$mode    = ( count( $divs ) > 1 && ! $cards ) ? (string) ( $settings->div_view ?? 'tabs' ) : 'single';
if ( 'auto' === $mode && count( $divs ) > 6 ) { // past 6 the chips never fit one row: a dropdown from the start
	$mode = 'select';
}
// 'auto' prints the chips and the dropdown; js/frontend.js shows the dropdown when the chips wrap (phones, long names).
$mode    = in_array( $mode, array( 'auto', 'tabs', 'select', 'stacked', 'single' ), true ) ? $mode : 'tabs';
$chips   = 'tabs' === $mode || 'auto' === $mode;
$menu    = 'select' === $mode || 'auto' === $mode;
$title   = trim( (string) ( $settings->title ?? __( 'Standings', 'ds-toolkit' ) ) );
$hl      = $module->highlight();
$links   = 'no' !== ( $settings->team_links ?? 'yes' );
list( $cols, $not_phone ) = $module->columns();
$limit   = 'table' === $layout ? max( 0, (int) ( $settings->rows_n ?? 0 ) ) : max( 0, (int) ( $settings->leaders_n ?? 3 ) ); // 0 = every team
$uid     = 'dsst-' . preg_replace( '/[^a-z0-9]/', '', (string) $id );

// League dropdown (Let Visitors Switch League). A plain GET form, so it works without the script; with it,
// js/frontend.js asks the REST route at data-dsst-src for the picked league and swaps the block in place.
$picker      = $view['picker'] ?? array();
$src         = ( $picker && ! $builder ) ? $module->src() : '';
$picker_html = '';
if ( $picker && $view['league'] ) {
	$plabel       = trim( (string) ( $settings->pick_label ?? __( 'League', 'ds-toolkit' ) ) );
	$picker_html .= '<form class="dsst-league" method="get" data-dsst-league><label class="dsst-league-pick">';
	$picker_html .= '<span class="dsst-league-label' . ( '' === $plabel ? ' dsst-sr' : '' ) . '">' . esc_html( '' !== $plabel ? $plabel : __( 'League', 'ds-toolkit' ) ) . '</span>';
	$picker_html .= '<select class="dsst-league-select" name="dsst_league"' . ( $builder ? ' disabled' : '' ) . '>';
	foreach ( $picker as $k => $l ) {
		$picker_html .= '<option value="' . esc_attr( $k ) . '" data-slug="' . esc_attr( DS_Standings_Module::slug( $l['name'] ) ) . '"' . selected( $k, $view['key'], false ) . '>' . esc_html( $l['name'] ) . '</option>';
	}
	$picker_html .= '</select></label>';
	$picker_html .= '<noscript><button type="submit" class="fl-content-ui-button dsst-league-go">' . esc_html__( 'Show', 'ds-toolkit' ) . '</button></noscript>';
	$meta         = DS_Standings_Module::league_meta( $view['league'] );
	$picker_html .= '<span class="dsst-league-meta" data-failed="' . esc_attr__( 'That league could not load. Please try again.', 'ds-toolkit' ) . '">' . esc_html( $meta ) . '</span>';
	$picker_html .= '</form>';
}
$root_attr = '' !== $src ? ' data-dsst-src="' . esc_url( $src ) . '"' : '';
// The page link names the league and division shown (#standings=girls-winter-league/5th-grade), so a menu item or a
// shared link can open one directly; js/frontend.js reads and writes it.
$root_attr .= ' data-dsst-league-slug="' . esc_attr( DS_Standings_Module::slug( $view['league']['name'] ?? '' ) ) . '"';

if ( ! $divs ) {
	// LeagueApps could not be reached and nothing is kept yet.
	// The dropdown stays, so a visitor can still pick another league while this one cannot be read.
	echo '<div class="dsst dsst-' . esc_attr( $layout ) . '" data-dsst' . $root_attr . '>' . ( '' !== $picker_html ? '<div class="dsst-top"><div class="dsst-titles">' . $picker_html . '</div></div>' : '' ) . '<p class="dsst-empty">'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above
	echo esc_html( $txt( 'down_text', __( 'Standings are temporarily unavailable. Please check back shortly.', 'ds-toolkit' ) ) ) . '</p>';
	if ( $builder && $view['errors'] ) {
		echo '<p class="dsst-empty">' . esc_html( implode( ' ', $view['errors'] ) ) . '</p>';
	}
	echo '</div>';
	return;
}

if ( 'ticker' === $layout ) {
	// Like the Marquee block: a pinned label, then two identical groups scrolled by -50% for a seamless loop
	// (js/frontend.js adds copies when one group is narrower than the bar and sets the speed).
	$label = trim( (string) ( $settings->ticker_label ?? __( 'Standings', 'ds-toolkit' ) ) );
	$link  = ! empty( $settings->leaders_link ) ? esc_url( (string) $settings->leaders_link ) : '';
	$group = '';
	foreach ( $divs as $d ) {
		if ( ! $d['played'] ) {
			continue;
		}
		$name = esc_html( $d['name'] );
		$href = $link ? $link : ( '' !== $d['url'] ? esc_url( $d['url'] ) : '' );
		$group .= '<span class="dsst-tk-div">';
		$group .= $href ? '<a class="dsst-tk-name" href="' . $href . '"' . ( $link ? '' : ' target="_blank" rel="noopener"' ) . '>' . $name . '</a>' : '<span class="dsst-tk-name">' . $name . '</span>';
		foreach ( ( $limit ? array_slice( $d['rows'], 0, $limit ) : $d['rows'] ) as $n => $r ) {
			$me     = '' !== $hl && DS_Standings_Module::norm( $r['team'] ) === $hl;
			$group .= '<span class="dsst-tk-team' . ( $me ? ' is-me' : '' ) . '"><span class="dsst-tk-rk">' . (int) ( $n + 1 ) . '</span> ' . esc_html( $r['team'] ) . ' <span class="dsst-tk-rec">' . esc_html( $r['w'] . '-' . $r['l'] . ( $r['t'] ? '-' . $r['t'] : '' ) ) . '</span></span>';
		}
		$group .= '</span><span class="dsst-tk-sep" aria-hidden="true">&#9670;</span>';
	}
	if ( '' === $group ) {
		echo '<div class="dsst dsst-ticker"><p class="dsst-empty">' . esc_html( $txt( 'empty_text', __( 'No results yet. Standings appear after the first game.', 'ds-toolkit' ) ) ) . '</p></div>';
		return;
	}
	?>
<div class="dsst dsst-ticker" data-dsst data-dsst-ticker data-speed="<?php echo (int) max( 20, min( 160, DS_Module_UI::u( $settings->ticker_speed ?? '', 50 ) ) ); ?>" role="region" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: league name */ __( '%s standings', 'ds-toolkit' ), $league['name'] ) ); ?>">
	<?php if ( '' !== $label ) : ?>
		<div class="dsst-tk-label"><?php echo DS_Module_UI::inline( $label ); // phpcs:ignore WordPress.Security.EscapeOutput -- inline() allows a short list of inline tags ?></div>
	<?php endif; ?>
	<div class="dsst-tk-viewport"><div class="dsst-tk-track">
		<div class="dsst-tk-group"><?php echo $group; // phpcs:ignore WordPress.Security.EscapeOutput -- every part escaped above ?></div>
		<div class="dsst-tk-group" aria-hidden="true" inert><?php echo $group; // phpcs:ignore WordPress.Security.EscapeOutput -- every part escaped above ?></div>
	</div></div>
	<button type="button" class="fl-content-ui-button dsst-tk-pause" aria-pressed="false" aria-label="<?php esc_attr_e( 'Pause the standings ticker', 'ds-toolkit' ); ?>"><span aria-hidden="true"></span></button>
</div>
	<?php
	return;
}

$classes = array( 'dsst', 'dsst-' . $layout, 'dsst-mode-' . $mode );
if ( ! $shown( 'frame' ) ) {
	$classes[] = 'dsst-noframe';
}
?>
<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" data-dsst<?php echo $root_attr; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url above ?>>
	<?php if ( ( $shown( 'eyebrow_show' ) && '' !== $league['name'] ) || '' !== $title || $chips || $menu || $arrows || '' !== $picker_html ) : ?>
	<div class="dsst-top">
		<div class="dsst-titles">
			<?php if ( '' === $picker_html && $shown( 'eyebrow_show' ) && '' !== $league['name'] ) : // the dropdown names the league instead ?>
				<span class="dsst-eyebrow"><?php echo esc_html( $league['name'] ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $title ) : ?>
				<h2 class="dsst-title"><?php echo DS_Module_UI::inline( $title ); // phpcs:ignore WordPress.Security.EscapeOutput -- inline() allows a short list of inline tags ?><?php echo 'single' === $mode && 'table' === $layout && count( $league['divisions'] ) > 1 ? ' <span class="dsst-title-div">· ' . esc_html( $divs[0]['name'] ) . '</span>' : ''; ?></h2>
			<?php endif; ?>
			<?php echo $picker_html; // phpcs:ignore WordPress.Security.EscapeOutput -- every part escaped above ?>
		</div>
		<?php if ( $chips ) : ?>
			<div class="dsst-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Divisions', 'ds-toolkit' ); ?>">
				<?php foreach ( $divs as $i => $d ) : ?>
					<button type="button" class="fl-content-ui-button dsst-tab" role="tab" id="<?php echo esc_attr( $uid . '-t' . $i ); ?>" aria-controls="<?php echo esc_attr( $uid . '-p' . $i ); ?>" aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>" tabindex="<?php echo 0 === $i ? '0' : '-1'; ?>"><?php echo esc_html( $d['name'] ); ?></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php if ( $menu ) : ?>
			<label class="dsst-pick">
				<span class="dsst-pick-label"><?php esc_html_e( 'Division', 'ds-toolkit' ); ?></span>
				<select class="dsst-select" aria-controls="<?php echo esc_attr( $uid . '-p0' ); ?>">
					<?php foreach ( $divs as $i => $d ) : ?>
						<option value="<?php echo esc_attr( $uid . '-p' . $i ); ?>"><?php echo esc_html( $d['name'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		<?php endif; ?>
		<?php if ( ! $chips && ! $menu && $arrows ) : ?>
			<?php // Arrows sit in the header: the framed block clips anything placed outside it. The script shows them when the cards overflow. ?>
			<div class="dsst-car-nav" hidden>
				<button type="button" class="fl-content-ui-button dsst-nav dsst-prev" aria-controls="<?php echo esc_attr( $uid . '-cards' ); ?>" aria-label="<?php esc_attr_e( 'Previous divisions', 'ds-toolkit' ); ?>"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M15 5l-7 7 7 7"/></svg></button>
				<button type="button" class="fl-content-ui-button dsst-nav dsst-next" aria-controls="<?php echo esc_attr( $uid . '-cards' ); ?>" aria-label="<?php esc_attr_e( 'Next divisions', 'ds-toolkit' ); ?>"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></button>
			</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php if ( $cards ) : ?>
	<?php if ( 'carousel' === $layout ) : ?>
	<div class="dsst-car">
	<div class="dsst-cards" id="<?php echo esc_attr( $uid . '-cards' ); ?>" data-dsst-car tabindex="0" role="region" aria-label="<?php esc_attr_e( 'Division leaders (scroll sideways for more)', 'ds-toolkit' ); ?>">
	<?php else : ?>
	<div class="dsst-cards">
	<?php endif; ?>
		<?php foreach ( $divs as $d ) :
			$more = '';
			if ( ! empty( $settings->leaders_link ) ) {
				$more = esc_url( (string) $settings->leaders_link );
			} elseif ( '' !== $d['url'] ) {
				$more = esc_url( $d['url'] );
			}
			?>
			<article class="dsst-card">
				<h3 class="dsst-card-title"><?php echo esc_html( $d['name'] ); ?></h3>
				<?php if ( $d['played'] ) : ?>
					<ol class="dsst-leaders">
						<?php foreach ( ( $limit ? array_slice( $d['rows'], 0, $limit ) : $d['rows'] ) as $n => $r ) : // 0 = All teams
							$me = '' !== $hl && DS_Standings_Module::norm( $r['team'] ) === $hl;
							?>
							<li class="<?php echo $me ? 'is-me' : ''; ?>"<?php echo $me ? ' aria-current="true"' : ''; ?>>
								<span class="dsst-rk"><?php echo (int) ( $n + 1 ); ?></span>
								<span class="dsst-nm"><?php echo esc_html( $r['team'] ); ?></span>
								<span class="dsst-rec"><?php echo esc_html( $r['w'] . '-' . $r['l'] . ( $r['t'] ? '-' . $r['t'] : '' ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php else : ?>
					<p class="dsst-empty"><?php echo esc_html( $txt( 'empty_text', __( 'No results yet. Standings appear after the first game.', 'ds-toolkit' ) ) ); ?></p>
				<?php endif; ?>
				<?php if ( $more ) : ?>
					<a class="dsst-more" href="<?php echo $more; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url above ?>"<?php echo empty( $settings->leaders_link ) ? ' target="_blank" rel="noopener"' : ''; ?>><?php esc_html_e( 'Full standings', 'ds-toolkit' ); ?></a>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>
	<?php if ( 'carousel' === $layout ) : ?>
	</div>
	<?php endif; ?>
	<?php else : ?>
		<?php foreach ( $divs as $i => $d ) :
			$active = ( 0 === $i || 'stacked' === $mode || 'single' === $mode );
			$rows   = $limit ? array_slice( $d['rows'], 0, $limit ) : $d['rows'];
			$dcols  = $module->division_columns( $cols, $d['rows'] );
			?>
			<section class="dsst-panel<?php echo $active ? ' is-active' : ''; ?>" id="<?php echo esc_attr( $uid . '-p' . $i ); ?>" data-slug="<?php echo esc_attr( DS_Standings_Module::slug( $d['name'] ) ); ?>"<?php echo $chips ? ' role="tabpanel" aria-labelledby="' . esc_attr( $uid . '-t' . $i ) . '"' : ''; ?>>
				<?php if ( 'stacked' === $mode ) : ?>
					<h3 class="dsst-div-title"><?php echo esc_html( $d['name'] ); ?></h3>
				<?php endif; ?>
				<?php if ( ! $d['played'] && ! $d['rows'] ) : ?>
					<p class="dsst-empty"><?php echo esc_html( $txt( 'empty_text', __( 'No results yet. Standings appear after the first game.', 'ds-toolkit' ) ) ); ?></p>
				<?php else : ?>
				<?php if ( count( $rows ) > 15 && $shown( 'find' ) ) : ?>
				<div class="dsst-find">
					<label class="dsst-find-pick">
						<span class="dsst-find-label"><?php esc_html_e( 'Find a team', 'ds-toolkit' ); ?></span>
						<input type="search" class="dsst-find-input" data-dsst-find autocomplete="off" spellcheck="false">
					</label>
					<p class="dsst-nomatch" role="status" hidden><?php esc_html_e( 'No team in this division matches that name.', 'ds-toolkit' ); ?></p>
				</div>
				<?php endif; ?>
				<div class="dsst-scroll" tabindex="0" role="region" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: division name */ __( '%s standings', 'ds-toolkit' ), $d['name'] ) ); ?>">
					<table class="dsst-table">
						<caption class="dsst-sr"><?php echo esc_html( sprintf( /* translators: 1: league, 2: division, 3: number of teams */ _n( '%1$s: %2$s standings, %3$d team', '%1$s: %2$s standings, %3$d teams', count( $d['rows'] ), 'ds-toolkit' ), $league['name'], $d['name'], count( $d['rows'] ) ) ); ?></caption>
						<thead>
							<?php
							if ( $shown( 'group_head' ) ) {
								// A group label spans its columns, and a colspan cannot shrink when phones hide some of them
								// (a Points label over PS PSA PSD widened the phone table by two columns). So phones get their
								// own row, counted from the columns they show, when that differs.
								$phone_cols = array_values( array_diff( $dcols, $not_phone ) );
								$group_row  = function ( array $list, $cls ) {
									$gs = array();
									foreach ( $list as $c ) {
										$g = DS_Standings_Module::COLUMNS[ $c ][2];
										$n = count( $gs );
										if ( $n && $gs[ $n - 1 ][0] === $g ) {
											$gs[ $n - 1 ][1]++;
										} else {
											$gs[] = array( $g, 1 );
										}
									}
									if ( count( $gs ) < 2 ) {
										return '';
									}
									$out = '<tr class="dsst-groups' . $cls . '"><td class="dsst-c-rk dsst-pin"></td><td class="dsst-c-team dsst-pin2"></td>';
									foreach ( $gs as $gr ) {
										$out .= '<th scope="colgroup" colspan="' . (int) $gr[1] . '" class="dsst-g"><span>' . esc_html( __( DS_Standings_Module::GROUPS[ $gr[0] ], 'ds-toolkit' ) ) . '</span></th>'; // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
									}
									return $out . '</tr>';
								};
								$differs = $phone_cols !== $dcols;
								echo $group_row( $dcols, $differs ? ' dsst-np' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
								if ( $differs ) {
									echo $group_row( $phone_cols, ' dsst-po' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside
								}
							}
							?>
							<tr>
								<th scope="col" class="dsst-c-rk dsst-pin"><span class="dsst-sr"><?php esc_html_e( 'Rank', 'ds-toolkit' ); ?></span><span aria-hidden="true">#</span></th>
								<th scope="col" class="dsst-c-team dsst-pin2"><?php esc_html_e( 'Team', 'ds-toolkit' ); ?></th>
								<?php foreach ( $dcols as $c ) : ?>
									<th scope="col" class="dsst-c-<?php echo esc_attr( $c ); ?><?php echo in_array( $c, $not_phone, true ) ? ' dsst-np' : ''; ?>"><abbr title="<?php echo esc_attr( __( DS_Standings_Module::COLUMNS[ $c ][1], 'ds-toolkit' ) ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText ?>"><?php echo esc_html( DS_Standings_Module::COLUMNS[ $c ][0] ); ?></abbr></th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $n => $r ) :
								$me  = '' !== $hl && DS_Standings_Module::norm( $r['team'] ) === $hl;
								$url = $links ? DS_Standings_Module::team_url( $d['url'], $r['id'] ) : '';
								?>
								<tr data-team="<?php echo esc_attr( DS_Standings_Module::norm( $r['team'] ) ); ?>"<?php echo $me ? ' class="is-me" aria-current="true"' : ''; ?>>
									<td class="dsst-c-rk dsst-pin"><?php echo (int) ( $n + 1 ); ?></td>
									<th scope="row" class="dsst-c-team dsst-pin2"><?php
									if ( $url ) {
										echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $r['team'] ) . '</a>';
									} else {
										echo esc_html( $r['team'] );
									}
									?></th>
									<?php foreach ( $dcols as $c ) :
										list( $val, $cls ) = $module->cell( $r, $c );
										?>
										<td class="<?php echo esc_attr( $cls . ( in_array( $c, $not_phone, true ) ? ' dsst-np' : '' ) ); ?>"><?php echo esc_html( $val ); ?></td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>
				<?php
				$foot = array();
				if ( $shown( 'legend' ) && $d['played'] ) {
					$keys = array();
					foreach ( $dcols as $c ) {
						$keys[] = '<span' . ( in_array( $c, $not_phone, true ) ? ' class="dsst-np"' : '' ) . '><b>' . esc_html( DS_Standings_Module::COLUMNS[ $c ][0] ) . '</b> ' . esc_html( __( DS_Standings_Module::COLUMNS[ $c ][1], 'ds-toolkit' ) ) . '</span>'; // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
					}
					$foot[] = '<p class="dsst-legend">' . implode( ' ', $keys ) . '</p>';
				}
				if ( $shown( 'rules_note' ) && $d['played'] ) {
					$foot[] = '<p class="dsst-note">' . esc_html( $module->rules_line() ) . '</p>';
				}
				$meta = $shown( 'updated' ) ? DS_Standings_Module::status_line( $d ) : '';
				$la   = ( $shown( 'la_link' ) && '' !== $d['url'] ) ? '<a class="dsst-la" href="' . esc_url( $d['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $txt( 'la_link_text', __( 'View on LeagueApps', 'ds-toolkit' ) ) ) . '</a>' : '';
				$sched = $shown( 'sched_link' ) ? DS_Standings_Module::schedule_url( $d['url'] ) : '';
				if ( '' !== $sched ) {
					$la = '<a class="dsst-la dsst-sched" href="' . esc_url( $sched ) . '" target="_blank" rel="noopener">' . esc_html( $txt( 'sched_link_text', __( 'Full schedule', 'ds-toolkit' ) ) ) . '</a>' . $la;
				}
				$la = '' !== $la ? '<span class="dsst-links">' . $la . '</span>' : '';
				if ( '' !== $meta || '' !== $la ) {
					$foot[] = '<div class="dsst-meta">' . ( '' !== $meta ? '<span>' . esc_html( $meta ) . '</span>' : '<span></span>' ) . $la . '</div>';
				}
				if ( $foot ) {
					echo '<div class="dsst-foot">' . implode( '', $foot ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- every part escaped above
				}
				?>
			</section>
		<?php endforeach; ?>
	<?php endif; ?>
	<?php if ( $builder && $view['errors'] ) : ?>
		<p class="dsst-empty"><?php echo esc_html( implode( ' ', $view['errors'] ) ); ?></p>
	<?php endif; ?>
</div>
