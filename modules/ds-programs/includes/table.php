<?php
/**
 * LeagueApps Programs, table markup.
 *
 * Rendered fully server-side so the listing is in the HTML (indexable, and
 * it survives a visitor on flaky mobile data). Filtering is progressive
 * enhancement: the rows are already there, the JS only hides and shows them.
 *
 * In scope: $this (the module).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$s       = $this->settings;
$rows    = $this->rows();
$cols    = $this->chosen_columns();
$filters = $this->chosen_filters();
$node    = $this->node;

$btn_text  = trim( (string) ( $s->btn_text ?? '' ) ) ?: __( 'Register', 'ds-toolkit' );
$btn_full  = trim( (string) ( $s->btn_full_text ?? '' ) ) ?: __( 'Sold Out', 'ds-toolkit' );
$full_mode = (string) ( $s->btn_full_style ?? 'fade' );
$show_cnt  = 'no' !== ( $s->show_count ?? 'yes' );
$show_clr  = 'no' !== ( $s->show_clear ?? 'yes' );
$cnt_one   = trim( (string) ( $s->count_singular ?? '' ) ) ?: __( 'program', 'ds-toolkit' );
$cnt_many  = trim( (string) ( $s->count_plural ?? '' ) ) ?: __( 'programs', 'ds-toolkit' );
$clear_txt = trim( (string) ( $s->clear_text ?? '' ) ) ?: __( 'Clear filters', 'ds-toolkit' );
$empty_txt = trim( (string) ( $s->empty_text ?? '' ) ) ?: __( 'No programs are open right now. Please check back soon.', 'ds-toolkit' );
$none_txt  = trim( (string) ( $s->none_text ?? '' ) ) ?: __( 'No programs match those filters.', 'ds-toolkit' );

/** Cell content for one column. Everything is escaped here. */
$cell = function ( $key, $r ) use ( $btn_text, $btn_full, $full_mode ) {
	if ( 'register' === $key ) {
		$url = (string) $r['registerUrl'];
		if ( ! empty( $r['soldOut'] ) ) {
			$cls = ( 'text' === $full_mode ) ? 'ds-programs-full' : 'ds-programs-btn ds-programs-btn--full';
			return '<span class="' . $cls . '">' . esc_html( $btn_full ) . '</span>';
		}
		if ( '' === $url ) { return ''; }
		return '<a class="ds-programs-btn" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $btn_text ) . '</a>';
	}
	$v = trim( (string) ( $r[ $key ] ?? '' ) );
	// LeagueApps leaves spots blank when a program has no cap; say so rather
	// than leaving a hole next to rows that show a number.
	if ( '' === $v && 'spots' === $key ) {
		return '<span class="ds-programs-dash" aria-label="' . esc_attr__( 'Not limited', 'ds-toolkit' ) . '">&mdash;</span>';
	}
	if ( 'program' === $key && ! empty( $r['programUrl'] ) && 'yes' === ( $this->settings->link_program ?? 'no' ) ) {
		return '<a class="ds-programs-link" href="' . esc_url( $r['programUrl'] ) . '" target="_blank" rel="noopener">' . esc_html( $v ) . '</a>';
	}
	return esc_html( $v );
};

/** Filter attribute value for a row: multi-value keys keep their comma list. */
$fattr = function ( $key, $r ) {
	return trim( (string) ( $r[ $key ] ?? '' ) );
};
?>
<div class="ds-programs" id="ds-programs-<?php echo esc_attr( $node ); ?>" data-ds-programs
	data-one="<?php echo esc_attr( $cnt_one ); ?>" data-many="<?php echo esc_attr( $cnt_many ); ?>">

	<?php if ( $rows && ( $filters || $show_cnt ) ) : ?>
	<div class="ds-programs-bar">
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

		<?php if ( $show_cnt ) : ?>
			<p class="ds-programs-count" data-ds-programs-count aria-live="polite"></p>
		<?php endif; ?>

		<?php if ( $show_clr && $filters ) : ?>
			<button type="button" class="ds-programs-clear" data-ds-programs-clear hidden><?php echo esc_html( $clear_txt ); ?></button>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php if ( empty( $rows ) ) : ?>
		<p class="ds-programs-empty"><?php echo esc_html( $empty_txt ); ?></p>
	<?php else : ?>

	<div class="ds-programs-scroll">
		<table class="ds-programs-table">
			<thead>
				<tr class="ds-programs-hrow">
					<?php foreach ( $cols as $ckey => $c ) : ?>
						<th scope="col" class="ds-programs-th ds-programs-th--<?php echo esc_attr( $ckey ); ?>"><?php
							// The register column is a button, not a labelled value; a visible
							// "Register Button" heading is the builder's field name leaking out.
							if ( 'register' === $ckey && $c['label'] === DS_Programs_Data::catalog()['register']['label'] ) {
								echo '<span class="ds-programs-sr">' . esc_html__( 'Register', 'ds-toolkit' ) . '</span>';
							} else {
								echo esc_html( $c['label'] );
							}
						?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $r ) : ?>
					<tr class="ds-programs-row<?php echo ! empty( $r['soldOut'] ) ? ' is-soldout' : ''; ?>"<?php
						foreach ( $filters as $fkey => $f ) {
							echo ' data-f-' . esc_attr( strtolower( $fkey ) ) . '="' . esc_attr( $fattr( $fkey, $r ) ) . '"';
						}
					?>>
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
