<?php
/**
 * LeagueApps Programs, markup for all three layouts: the table, the card grid and the list.
 *
 * Rendered fully server-side so the listing is in the HTML (indexable, and
 * it survives a visitor on flaky mobile data). Filtering, keyword search,
 * column sorting and pagination are progressive enhancement: every row is
 * already there, the JS only hides, shows and reorders them. All layouts
 * share the same row element class, data attributes and list container, so
 * the JS does not know or care which one is rendered.
 *
 * In scope: $this (the module).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$s       = $this->settings;
$rows    = $this->rows();
$cols    = $this->chosen_columns();
$filters = $this->chosen_filters();
$node    = $this->node;
$layout  = in_array( (string) ( $s->layout ?? 'table' ), array( 'cards', 'list' ), true ) ? (string) $s->layout : 'table';

$btn_text  = trim( (string) ( $s->btn_text ?? '' ) ) ?: __( 'Register', 'ds-toolkit' );
$btn_full  = trim( (string) ( $s->btn_full_text ?? '' ) ) ?: __( 'Sold Out', 'ds-toolkit' );
$btn_canc  = trim( (string) ( $s->btn_cancel_text ?? '' ) ) ?: __( 'Cancelled', 'ds-toolkit' );
$full_mode = (string) ( $s->btn_full_style ?? 'fade' );
$season    = 'no' !== ( $s->season_links ?? 'yes' );
$sched_txt = trim( (string) ( $s->schedule_text ?? '' ) ) ?: __( 'View schedule', 'ds-toolkit' );
$stand_txt = trim( (string) ( $s->standings_text ?? '' ) ) ?: __( 'View standings', 'ds-toolkit' );
$show_cnt  = 'no' !== ( $s->show_count ?? 'yes' );
$show_clr  = 'no' !== ( $s->show_clear ?? 'yes' );
$show_srch = 'no' !== ( $s->show_search ?? 'yes' );
$sortable  = 'no' !== ( $s->sortable ?? 'yes' );
$page_size = max( 0, (int) ( $s->page_size ?? 0 ) );
$cnt_one   = trim( (string) ( $s->count_singular ?? '' ) ) ?: __( 'program', 'ds-toolkit' );
$cnt_many  = trim( (string) ( $s->count_plural ?? '' ) ) ?: __( 'programs', 'ds-toolkit' );
$clear_txt = trim( (string) ( $s->clear_text ?? '' ) ) ?: __( 'Clear filters', 'ds-toolkit' );
$srch_lbl  = trim( (string) ( $s->search_label ?? '' ) ) ?: __( 'Search', 'ds-toolkit' );
$srch_ph   = trim( (string) ( $s->search_placeholder ?? '' ) ) ?: __( 'Search programs', 'ds-toolkit' );
$prev_txt  = trim( (string) ( $s->pager_prev ?? '' ) ) ?: __( 'Previous', 'ds-toolkit' );
$next_txt  = trim( (string) ( $s->pager_next ?? '' ) ) ?: __( 'Next', 'ds-toolkit' );
$empty_txt = trim( (string) ( $s->empty_text ?? '' ) ) ?: __( 'No programs are open right now. Please check back soon.', 'ds-toolkit' );
$none_txt  = trim( (string) ( $s->none_text ?? '' ) ) ?: __( 'No programs match those filters.', 'ds-toolkit' );

/** Cell content for one column. Everything is escaped here. */
$cell = function ( $key, $r ) use ( $btn_text, $btn_full, $btn_canc, $full_mode, $cols, $season, $sched_txt, $stand_txt ) {
	if ( 'register' === $key ) {
		// The program's LeagueApps page, not the checkout form. See DS_Programs_Data::button_url().
		$url = DS_Programs_Data::button_url( $r );
		// A cancelled session outranks every other state: LeagueApps keeps serving its
		// register URL after it is called off, so never render it as a live button.
		if ( ! empty( $r['canceled'] ) ) {
			$cls = ( 'text' === $full_mode ) ? 'ds-programs-full ds-programs-full--canceled' : 'ds-programs-btn ds-programs-btn--full ds-programs-btn--canceled';
			return '<span class="' . $cls . '">' . esc_html( $btn_canc ) . '</span>';
		}
		if ( ! empty( $r['soldOut'] ) ) {
			$cls  = ( 'text' === $full_mode ) ? 'ds-programs-full' : 'ds-programs-btn ds-programs-btn--full';
			$main = '<span class="' . $cls . '">' . esc_html( $btn_full ) . '</span>';
		} else {
			$main = '' === $url ? '' : '<a class="ds-programs-btn" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $btn_text ) . '</a>';
		}
		// In season: the hosted widget's rule. "View schedule" / "View standings" text links once
		// the program is LIVE, and the register button only while registration is still open, so
		// families mid-season stop landing on a sign-up page to find their games.
		$sched = (string) ( $r['scheduleUrl'] ?? '' );
		$stand = (string) ( $r['standingsUrl'] ?? '' );
		if ( ! $season || 'LIVE' !== ( $r['stateRaw'] ?? '' ) || ( '' === $sched && '' === $stand ) ) { return $main; }
		$links = '';
		if ( '' !== $sched ) { $links .= '<a class="ds-programs-seasonlink" href="' . esc_url( $sched ) . '" target="_blank" rel="noopener">' . esc_html( $sched_txt ) . '</a>'; }
		if ( '' !== $stand ) { $links .= '<a class="ds-programs-seasonlink" href="' . esc_url( $stand ) . '" target="_blank" rel="noopener">' . esc_html( $stand_txt ) . '</a>'; }
		$keep = ( 'CLOSED' !== ( $r['statusRaw'] ?? '' ) ) ? $main : '';
		return '<span class="ds-programs-actions">' . $keep . '<span class="ds-programs-seasonlinks">' . $links . '</span></span>';
	}
	$v = trim( (string) ( $r[ $key ] ?? '' ) );
	// A cancelled session has no places to take, so never print "15 spots left"
	// beside a Cancelled label - that is the same false advertisement as the button.
	if ( 'spots' === $key && ! empty( $r['canceled'] ) ) {
		return '<span class="ds-programs-dash" aria-label="' . esc_attr__( 'Not applicable', 'ds-toolkit' ) . '">&mdash;</span>';
	}
	// LeagueApps leaves spots blank when a program has no cap; say so rather
	// than leaving a hole next to rows that show a number.
	if ( '' === $v && 'spots' === $key ) {
		return '<span class="ds-programs-dash" aria-label="' . esc_attr__( 'Not limited', 'ds-toolkit' ) . '">&mdash;</span>';
	}
	$link = ( ! empty( $r['programUrl'] ) && 'yes' === ( $this->settings->link_program ?? 'no' ) );
	// A sub-program nested under its main program's row: the Program cell is
	// the sub-program's own name, indented, with the main program's name as an
	// eyebrow that only shows where there is no column to indent under (phone
	// cards, the Cards layout). Its Age Group cell is then left blank rather
	// than printing the same name twice; it still holds the value when the
	// table has no Program column.
	if ( ! empty( $r['parentId'] ) && isset( $cols['program'] ) ) {
		if ( 'ageGroup' === $key ) { return ''; }
		if ( 'program' === $key ) {
			$own = esc_html( (string) $r['ageGroup'] );
			if ( $link ) { $own = '<a class="ds-programs-link" href="' . esc_url( $r['programUrl'] ) . '" target="_blank" rel="noopener">' . $own . '</a>'; }
			return '<span class="ds-programs-parent">' . esc_html( $v ) . '</span><span class="ds-programs-sub">' . $own . '</span>';
		}
	}
	if ( 'program' === $key && $link ) {
		return '<a class="ds-programs-link" href="' . esc_url( $r['programUrl'] ) . '" target="_blank" rel="noopener">' . esc_html( $v ) . '</a>';
	}
	return esc_html( $v );
};

/**
 * Sort key for a column: numbers for dates, prices, counts, ages, months and
 * days (compared numerically in JS), lowercase text for everything else.
 * Returns array( value, 'num'|'text' ).
 */
$sort_val = function ( $key, $r ) {
	switch ( $key ) {
		case 'dateRange': case 'startDate': return array( (int) $r['startTs'], 'num' );
		case 'endDate':   return array( (int) $r['endTs'], 'num' );
		case 'month':     return array( DS_Programs_Data::month_rank( $r['month'] ), 'num' );
		case 'ageGroup':  return array( DS_Programs_Data::age_rank( $r['ageGroup'] ), 'num' );
		case 'days':      return array( '' === $r['days'] ? 99 : DS_Programs_Data::day_rank( explode( ',', $r['days'] )[0] ), 'num' );
		case 'price':     return array( '' === $r['price'] ? 0 : (float) preg_replace( '/[^\d.]/', '', $r['price'] ), 'num' );
		case 'spots':     return array( '' === $r['spots'] ? 999999 : (int) $r['spots'], 'num' );
		case 'register':  return array( ! empty( $r['canceled'] ) ? 2 : ( ! empty( $r['soldOut'] ) ? 1 : 0 ), 'num' );
		default:          return array( strtolower( trim( (string) ( $r[ $key ] ?? '' ) ) ), 'text' );
	}
};
$col_types = array();
foreach ( $cols as $ckey => $c ) { $col_types[ $ckey ] = $sort_val( $ckey, array( 'startTs' => 0, 'endTs' => 0, 'month' => '', 'ageGroup' => '', 'days' => '', 'price' => '', 'spots' => '', 'soldOut' => false, 'canceled' => false ) )[1]; }

/** Row index of each main program listed with its sub-programs, by program id. */
$parent_i = array();
foreach ( $rows as $i => $r ) { if ( ! empty( $r['isMaster'] ) ) { $parent_i[ (int) $r['programId'] ] = $i; } }

/** The attributes every row element carries, in either layout. */
$row_attrs = function ( $i, $r ) use ( $cols, $filters, $sortable, $sort_val, $rows, $parent_i ) {
	$search = array();
	foreach ( $cols as $ckey => $c ) { if ( 'register' !== $ckey ) { $search[] = (string) ( $r[ $ckey ] ?? '' ); } }
	$a  = ' data-i="' . (int) $i . '" data-search="' . esc_attr( strtolower( implode( ' ', array_filter( $search ) ) ) ) . '"';
	// The LeagueApps site the program came from: Per-site styles key on it.
	if ( '' !== (string) ( $r['siteId'] ?? '' ) ) { $a .= ' data-site="' . esc_attr( $r['siteId'] ) . '"'; }
	foreach ( $filters as $fkey => $f ) { $a .= ' data-f-' . esc_attr( strtolower( $fkey ) ) . '="' . esc_attr( trim( (string) ( $r[ $fkey ] ?? '' ) ) ) . '"'; }
	// A nested sub-program sorts by its main program's values, so a group moves
	// as one block whatever column is sorted; row order settles the rest.
	$sort_row = $r;
	if ( ! empty( $r['parentId'] ) && isset( $parent_i[ (int) $r['parentId'] ] ) ) {
		$a .= ' data-parent="' . (int) $parent_i[ (int) $r['parentId'] ] . '"';
		$sort_row = $rows[ $parent_i[ (int) $r['parentId'] ] ];
	}
	if ( $sortable ) {
		foreach ( $cols as $ckey => $c ) { if ( 'register' !== $ckey ) { $a .= ' data-s-' . esc_attr( strtolower( $ckey ) ) . '="' . esc_attr( $sort_val( $ckey, $sort_row )[0] ) . '"'; } }
	}
	return $a;
};
?>
<div class="ds-programs ds-programs--<?php echo esc_attr( $layout ); ?>" id="ds-programs-<?php echo esc_attr( $node ); ?>" data-ds-programs
	data-one="<?php echo esc_attr( $cnt_one ); ?>" data-many="<?php echo esc_attr( $cnt_many ); ?>"
	data-page-size="<?php echo (int) $page_size; ?>" data-prev="<?php echo esc_attr( $prev_txt ); ?>" data-next="<?php echo esc_attr( $next_txt ); ?>">

	<?php if ( $rows && ( $filters || $show_cnt || $show_srch || ( $sortable && $cols ) ) ) : ?>
	<div class="ds-programs-bar">
		<?php if ( $show_srch ) : $sid = 'ds-programs-' . esc_attr( $node ) . '-q'; ?>
			<div class="ds-programs-field ds-programs-field--search">
				<label class="ds-programs-label" for="<?php echo esc_attr( $sid ); ?>"><?php echo esc_html( $srch_lbl ); ?></label>
				<input type="search" class="ds-programs-input" id="<?php echo esc_attr( $sid ); ?>" data-ds-programs-search placeholder="<?php echo esc_attr( $srch_ph ); ?>" autocomplete="off">
			</div>
		<?php endif; ?>

		<?php foreach ( $filters as $fkey => $f ) :
			$opts = $this->filter_values( $fkey, $rows );
			if ( count( $opts ) < 2 ) { continue; }
			$fid = 'ds-programs-' . esc_attr( $node ) . '-' . esc_attr( $fkey );
			?>
			<div class="ds-programs-field">
				<label class="ds-programs-label" for="<?php echo esc_attr( $fid ); ?>"><?php echo esc_html( $f['label'] ); ?></label>
				<select class="ds-programs-select" id="<?php echo esc_attr( $fid ); ?>" data-ds-programs-filter="<?php echo esc_attr( $fkey ); ?>"<?php echo $f['multi'] ? ' data-multi="1"' : ''; ?>>
					<option value=""><?php echo esc_html( $f['all'] ); ?></option>
					<?php foreach ( $opts as $o ) : ?>
						<option value="<?php echo esc_attr( $o ); ?>"><?php echo esc_html( $o ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endforeach; ?>

		<?php if ( $sortable && $cols ) : $oid = 'ds-programs-' . esc_attr( $node ) . '-sort'; ?>
			<div class="ds-programs-field ds-programs-field--sort">
				<label class="ds-programs-label" for="<?php echo esc_attr( $oid ); ?>"><?php esc_html_e( 'Sort by', 'ds-toolkit' ); ?></label>
				<select class="ds-programs-select" id="<?php echo esc_attr( $oid ); ?>" data-ds-programs-sortsel>
					<option value=""><?php esc_html_e( 'Default order', 'ds-toolkit' ); ?></option>
					<?php foreach ( $cols as $ckey => $c ) : if ( 'register' === $ckey ) { continue; } ?>
						<option value="<?php echo esc_attr( $ckey ); ?>:asc"><?php echo esc_html( $c['label'] ); ?> &#9650;</option>
						<option value="<?php echo esc_attr( $ckey ); ?>:desc"><?php echo esc_html( $c['label'] ); ?> &#9660;</option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endif; ?>

		<?php if ( $show_cnt ) : ?>
			<p class="ds-programs-count" data-ds-programs-count aria-live="polite"></p>
		<?php endif; ?>

		<?php if ( $show_clr && ( $filters || $show_srch ) ) : ?>
			<button type="button" class="ds-programs-clear" data-ds-programs-clear hidden><?php echo esc_html( $clear_txt ); ?></button>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php if ( empty( $rows ) ) : ?>
		<p class="ds-programs-empty"><?php echo esc_html( $empty_txt ); ?></p>
	<?php elseif ( 'list' === $layout ) :
		// List roles: logo tile, then the program name as the title with its sub-program
		// (the age group or session) under it, a line of where and when with icons, the rest
		// of the chosen columns as chips, and price + register at the end.
		$meta_keys = array_values( array_intersect( array_keys( $cols ), array( 'location', 'city', 'dateRange', 'startDate', 'endDate', 'month', 'days' ) ) );
		$chip_keys = array_values( array_diff( array_keys( $cols ), $meta_keys, array( 'program', 'ageGroup', 'price', 'register' ) ) );
		$show_logo = 'no' !== ( $s->list_logo ?? 'yes' );
		$has_price = isset( $cols['price'] );
		$has_btn   = isset( $cols['register'] );
		$link_name = 'yes' === ( $s->link_program ?? 'no' );
		$item_tag  = $s->list_title_tag ?? 'h3';
		$item_tag  = in_array( $item_tag, array( 'h2', 'h3', 'h4', 'p' ), true ) ? $item_tag : 'h3';
		$icon_pin  = '<svg class="ds-programs-item-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M8 1.5a4.75 4.75 0 00-4.75 4.75c0 3.4 4.1 7.86 4.27 8.05a.65.65 0 00.96 0c.17-.19 4.27-4.65 4.27-8.05A4.75 4.75 0 008 1.5zm0 6.6a1.85 1.85 0 110-3.7 1.85 1.85 0 010 3.7z"/></svg>';
		$icon_cal  = '<svg class="ds-programs-item-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M5 1.25a.75.75 0 01.75.75v.75h4.5V2a.75.75 0 011.5 0v.75h.75A2 2 0 0114.5 4.75v7.5a2 2 0 01-2 2h-9a2 2 0 01-2-2v-7.5a2 2 0 012-2h.75V2A.75.75 0 015 1.25zM3 7v5.25c0 .28.22.5.5.5h9a.5.5 0 00.5-.5V7H3z"/></svg>';
		?>
	<div class="ds-programs-list" data-ds-programs-list>
		<?php foreach ( $rows as $i => $r ) :
			$title = esc_html( (string) $r['program'] );
			if ( $link_name && ! empty( $r['programUrl'] ) ) { $title = '<a class="ds-programs-link" href="' . esc_url( $r['programUrl'] ) . '" target="_blank" rel="noopener">' . $title . '</a>'; }
			$sub = trim( (string) ( $r['ageGroup'] ?? '' ) );
			list( $logo_url, $logo_src ) = $show_logo ? $this->logo_for( $r ) : array( '', '' );
			?>
			<article class="ds-programs-row ds-programs-item<?php echo $show_logo ? ' has-logo' : ''; echo ! empty( $r['soldOut'] ) ? ' is-soldout' : ''; echo ! empty( $r['canceled'] ) ? ' is-canceled' : ''; echo ! empty( $r['isMaster'] ) ? ' is-master' : ''; echo ! empty( $r['parentId'] ) ? ' is-child' : ''; ?>"<?php echo $row_attrs( $i, $r ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $row_attrs ?>>
				<?php if ( $show_logo ) : ?>
					<span class="ds-programs-item-logo<?php echo 'feed' === $logo_src ? ' is-feed' : ' is-placeholder'; ?>">
						<?php if ( '' !== $logo_url ) : ?>
							<img src="<?php echo esc_url( $logo_url ); ?>" alt="" loading="lazy" decoding="async" width="150" height="150">
						<?php else : // No logo anywhere: the program's initial keeps every row aligned. ?>
							<span class="ds-programs-item-initial" aria-hidden="true"><?php echo esc_html( function_exists( 'mb_substr' ) ? mb_substr( (string) $r['program'], 0, 1 ) : substr( (string) $r['program'], 0, 1 ) ); ?></span>
						<?php endif; ?>
					</span>
				<?php endif; ?>
				<div class="ds-programs-item-main">
					<<?php echo $item_tag; ?> class="ds-programs-item-title"><?php echo $title; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?></<?php echo $item_tag; ?>>
					<?php if ( '' !== $sub ) : ?><p class="ds-programs-item-sub"><?php echo esc_html( $sub ); ?></p><?php endif; ?>
					<?php
					$meta = '';
					foreach ( $meta_keys as $mkey ) {
						$v = trim( (string) ( $r[ $mkey ] ?? '' ) );
						if ( '' === $v ) { continue; }
						$icon  = in_array( $mkey, array( 'location', 'city' ), true ) ? $icon_pin : $icon_cal;
						$meta .= '<li class="ds-programs-item-meta-' . esc_attr( $mkey ) . '">' . $icon . '<span>' . esc_html( $v ) . '</span></li>';
					}
					if ( '' !== $meta ) { echo '<ul class="ds-programs-item-meta">' . $meta . '</ul>'; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
					$chips = '';
					foreach ( $chip_keys as $ckey ) {
						$v = trim( (string) ( $r[ $ckey ] ?? '' ) );
						if ( 'spots' === $ckey ) {
							// Only a real count: blank means no cap, and a cancelled session has none to take.
							if ( '' === $v || ! empty( $r['canceled'] ) || ! is_numeric( $v ) ) { continue; }
							/* translators: %d: places left */
							$v = sprintf( _n( '%d spot left', '%d spots left', (int) $v, 'ds-toolkit' ), (int) $v );
						}
						if ( '' === $v ) { continue; }
						$chips .= '<li class="ds-programs-chip ds-programs-chip--' . esc_attr( $ckey ) . '">' . esc_html( $v ) . '</li>';
					}
					if ( '' !== $chips ) { echo '<ul class="ds-programs-item-chips">' . $chips . '</ul>'; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
					?>
				</div>
				<?php if ( $has_price || $has_btn ) : ?>
				<div class="ds-programs-item-action">
					<?php if ( $has_price && '' !== trim( (string) $r['price'] ) ) : ?><span class="ds-programs-item-price"><?php echo esc_html( $r['price'] ); ?></span><?php endif; ?>
					<?php if ( $has_btn ) { echo $cell( 'register', $r ); } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $cell ?>
				</div>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>

	<p class="ds-programs-none" data-ds-programs-none hidden><?php echo esc_html( $none_txt ); ?></p>
	<?php if ( $page_size > 0 ) : ?>
		<nav class="ds-programs-pager" data-ds-programs-pager aria-label="<?php esc_attr_e( 'Pagination', 'ds-toolkit' ); ?>" hidden></nav>
	<?php endif; ?>

	<?php elseif ( 'cards' === $layout ) :
		// Card roles: the program name is the title, the age group a badge, price and
		// the register button sit in the footer, everything else the editor chose is a
		// labelled line in the body, in the editor's order.
		$body_keys = array();
		foreach ( $cols as $ckey => $c ) { if ( ! in_array( $ckey, array( 'program', 'ageGroup', 'price', 'register' ), true ) ) { $body_keys[] = $ckey; } }
		$has_badge = isset( $cols['ageGroup'] );
		$has_price = isset( $cols['price'] );
		$has_btn   = isset( $cols['register'] );
		$head_tag  = $s->card_title_tag ?? 'h3';
		$head_tag  = in_array( $head_tag, array( 'h2', 'h3', 'h4', 'p' ), true ) ? $head_tag : 'h3';
		?>
	<div class="ds-programs-grid" data-ds-programs-list>
		<?php foreach ( $rows as $i => $r ) : ?>
			<article class="ds-programs-row ds-programs-card<?php echo ! empty( $r['soldOut'] ) ? ' is-soldout' : ''; echo ! empty( $r['canceled'] ) ? ' is-canceled' : ''; echo ! empty( $r['isMaster'] ) ? ' is-master' : ''; echo ! empty( $r['parentId'] ) ? ' is-child' : ''; ?>"<?php echo $row_attrs( $i, $r ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $row_attrs ?>>
				<div class="ds-programs-card-head">
					<<?php echo $head_tag; ?> class="ds-programs-card-title"><?php echo $cell( 'program', $r ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $cell ?></<?php echo $head_tag; ?>>
					<?php if ( $has_badge && '' !== trim( (string) $r['ageGroup'] ) && empty( $r['parentId'] ) ) : ?>
						<span class="ds-programs-card-badge"><?php echo esc_html( $r['ageGroup'] ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( $body_keys ) : ?>
				<dl class="ds-programs-card-body">
					<?php foreach ( $body_keys as $ckey ) :
						$v = $cell( $ckey, $r );
						if ( '' === $v ) { continue; } ?>
						<div class="ds-programs-card-field ds-programs-card-field--<?php echo esc_attr( $ckey ); ?>">
							<dt><?php echo esc_html( $cols[ $ckey ]['label'] ); ?></dt>
							<dd><?php echo $v; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $cell ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
				<?php endif; ?>
				<?php if ( $has_price || $has_btn ) : ?>
				<div class="ds-programs-card-foot">
					<?php if ( $has_price ) : ?><span class="ds-programs-card-price"><?php echo esc_html( $r['price'] ); ?></span><?php endif; ?>
					<?php if ( $has_btn ) : ?><span class="ds-programs-card-action"><?php echo $cell( 'register', $r ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $cell ?></span><?php endif; ?>
				</div>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</div>

	<p class="ds-programs-none" data-ds-programs-none hidden><?php echo esc_html( $none_txt ); ?></p>
	<?php if ( $page_size > 0 ) : ?>
		<nav class="ds-programs-pager" data-ds-programs-pager aria-label="<?php esc_attr_e( 'Pagination', 'ds-toolkit' ); ?>" hidden></nav>
	<?php endif; ?>

	<?php else : ?>

	<div class="ds-programs-scroll">
		<table class="ds-programs-table">
			<thead>
				<tr class="ds-programs-hrow">
					<?php foreach ( $cols as $ckey => $c ) :
						$is_reg  = ( 'register' === $ckey );
						$default = $is_reg && $c['label'] === DS_Programs_Data::catalog()['register']['label'];
						$label   = $default ? '<span class="ds-programs-sr">' . esc_html__( 'Register', 'ds-toolkit' ) . '</span>' : esc_html( $c['label'] );
						?>
						<th scope="col" class="ds-programs-th ds-programs-th--<?php echo esc_attr( $ckey ); ?>"<?php echo ( $sortable && ! $is_reg ) ? ' aria-sort="none"' : ''; ?>><?php
							if ( $sortable && ! $is_reg ) {
								echo '<button type="button" class="ds-programs-sortbtn" data-sort="' . esc_attr( $ckey ) . '" data-type="' . esc_attr( $col_types[ $ckey ] ) . '">' . $label . '<span class="ds-programs-sorticon" aria-hidden="true"></span></button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $label escaped above
							} else {
								echo $label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above
							}
						?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody data-ds-programs-list>
				<?php foreach ( $rows as $i => $r ) : ?>
					<tr class="ds-programs-row<?php echo ! empty( $r['soldOut'] ) ? ' is-soldout' : ''; echo ! empty( $r['canceled'] ) ? ' is-canceled' : ''; echo ! empty( $r['isMaster'] ) ? ' is-master' : ''; echo ! empty( $r['parentId'] ) ? ' is-child' : ''; ?>"<?php echo $row_attrs( $i, $r ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $row_attrs ?>>
						<?php foreach ( $cols as $ckey => $c ) : ?>
							<td class="ds-programs-td ds-programs-td--<?php echo esc_attr( $ckey ); ?>" data-label="<?php echo esc_attr( 'register' === $ckey ? '' : $c['label'] ); ?>"><?php
								echo $cell( $ckey, $r ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in $cell
							?></td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<p class="ds-programs-none" data-ds-programs-none hidden><?php echo esc_html( $none_txt ); ?></p>
	<?php if ( $page_size > 0 ) : ?>
		<nav class="ds-programs-pager" data-ds-programs-pager aria-label="<?php esc_attr_e( 'Pagination', 'ds-toolkit' ); ?>" hidden></nav>
	<?php endif; ?>

	<?php endif; ?>

	<?php
	// Only ever shown to a logged-in editor. A visitor sees the last good data
	// silently rather than an error, which is the whole point of the server fetch.
	if ( ( $this->feed_stale ?? false ) && is_user_logged_in() && current_user_can( 'edit_posts' ) ) : ?>
		<p class="ds-programs-note"><?php esc_html_e( 'Editors only: LeagueApps did not respond just now, so this is the last good copy of the listings.', 'ds-toolkit' ); ?></p>
	<?php endif; ?>

	<?php if ( ! empty( $this->feed_errors ) && is_user_logged_in() && current_user_can( 'manage_options' ) ) : ?>
		<p class="ds-programs-note"><?php echo esc_html( sprintf(
			/* translators: %s: error detail */
			__( 'Admins only, LeagueApps feed: %s', 'ds-toolkit' ),
			implode( '; ', $this->feed_errors )
		) ); ?></p>
	<?php endif; ?>

</div>
