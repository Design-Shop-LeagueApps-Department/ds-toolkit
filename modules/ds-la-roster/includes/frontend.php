<?php
/**
 * @var DS_Roster_Module $module
 * @var object           $settings
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$t     = $module->roster();
$yes   = function ( $k, $d = 'yes' ) use ( $settings ) { return 'yes' === ( isset( $settings->$k ) && '' !== $settings->$k ? $settings->$k : $d ); };
$txt   = function ( $k, $d ) use ( $settings ) { return isset( $settings->$k ) && '' !== trim( (string) $settings->$k ) ? sanitize_text_field( (string) $settings->$k ) : $d; };
$med   = in_array( (string) ( $settings->medallion ?? 'tint' ), array( 'tint', 'solid', 'ring' ), true ) ? (string) $settings->medallion : 'tint';
$list  = 'list' === (string) ( $settings->layout ?? 'cards' );
$slug  = function ( $s ) { return '' === $s ? 'none' : sanitize_title( $s ); };
$htag  = in_array( (string) ( $settings->title_tag ?? '' ), array( 'h2', 'h3', 'h4' ), true ) ? (string) $settings->title_tag : 'h3'; // phpcs: allow-listed tag
?>
<div class="dsr dsr--<?php echo $list ? 'list' : 'cards'; ?> dsr--med-<?php echo esc_attr( $med ); ?>">
<?php if ( ! $t ) : ?>
	<?php if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) : ?>
		<p class="dsr-empty"><?php echo esc_html( DS_Roster_Data::sites_with_keys() ? __( 'Editors only: pick the LeagueApps team in this block\'s settings.', 'ds-toolkit' ) : __( 'Editors only: save the club\'s private LeagueApps key under Settings > DS Toolkit > LeagueApps, then pick the team here.', 'ds-toolkit' ) ); ?></p>
	<?php endif; ?>
<?php else :
	$players = $t['players'];
	$coaches = $yes( 'show_coaches' ) ? $t['coaches'] : array();
	$pos     = array();
	foreach ( $players as $p ) {
		if ( '' !== $p['pos'] ) {
			$pos[ $p['pos'] ] = ( $pos[ $p['pos'] ] ?? 0 ) + 1;
		}
	}
	$order = DS_Roster_Module::position_order( $players );
	uksort( $pos, function ( $a, $b ) use ( $order ) { return ( $order[ $a ] ?? 99 ) <=> ( $order[ $b ] ?? 99 ); } );
	$show_pos = $yes( 'show_position' );
	?>
	<?php if ( $yes( 'show_header' ) ) : ?>
		<div class="dsr-head">
			<div class="dsr-title">
				<?php if ( '' !== $t['season'] ) : ?><p class="dsr-eyebrow"><?php echo esc_html( sprintf( /* translators: %s season */ __( '%s Season', 'ds-toolkit' ), $t['season'] ) ); ?></p><?php endif; ?>
				<<?php echo $htag; ?> class="dsr-name"><?php echo esc_html( $txt( 'title_text', $t['name'] ) ); ?></<?php echo $htag; ?>>
			</div>
			<p class="dsr-stats">
				<span><b><?php echo count( $players ); ?></b> <?php echo esc_html( 1 === count( $players ) ? __( 'Player', 'ds-toolkit' ) : __( 'Players', 'ds-toolkit' ) ); ?></span>
				<?php if ( $coaches ) : ?><span><b><?php echo count( $coaches ); ?></b> <?php echo esc_html( 1 === count( $coaches ) ? __( 'Coach', 'ds-toolkit' ) : __( 'Coaches', 'ds-toolkit' ) ); ?></span><?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if ( ! $players ) : ?>
		<p class="dsr-empty"><?php echo esc_html( $txt( 'empty_text', __( 'The roster for this team will be posted soon.', 'ds-toolkit' ) ) ); ?></p>
	<?php else : ?>
		<?php if ( $show_pos && $yes( 'show_filter' ) && count( $pos ) > 1 ) : ?>
			<div class="dsr-filters" role="group" aria-label="<?php esc_attr_e( 'Filter players by position', 'ds-toolkit' ); ?>">
				<button type="button" class="fl-content-ui-button dsr-chip" data-pos="" aria-pressed="true"><?php esc_html_e( 'All', 'ds-toolkit' ); ?> <span><?php echo count( $players ); ?></span></button>
				<?php foreach ( $pos as $name => $n ) : ?>
					<button type="button" class="fl-content-ui-button dsr-chip" data-pos="<?php echo esc_attr( $slug( $name ) ); ?>" aria-pressed="false"><?php echo esc_html( $name ); ?> <span><?php echo (int) $n; ?></span></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<ul class="dsr-grid" aria-live="polite">
			<?php foreach ( $players as $p ) :
				$meta = array();
				if ( $yes( 'show_grad' ) && '' !== $p['gy'] ) { $meta[] = sprintf( /* translators: %s year */ __( 'Class of %s', 'ds-toolkit' ), $p['gy'] ); }
				if ( $yes( 'show_height' ) && '' !== $p['ht'] ) { $meta[] = $p['ht']; }
				?>
				<li class="dsr-card" data-pos="<?php echo esc_attr( $slug( $p['pos'] ) ); ?>">
					<span class="dsr-med" aria-hidden="true"><?php echo esc_html( $p['ini'] ); ?><?php if ( $p['cap'] ) : ?><span class="dsr-cap" title="<?php esc_attr_e( 'Captain', 'ds-toolkit' ); ?>">C</span><?php endif; ?></span>
					<span class="dsr-body">
						<span class="dsr-pname"><?php echo esc_html( $p['name'] ); ?><?php if ( $p['cap'] ) : ?><span class="screen-reader-text"> (<?php esc_html_e( 'Captain', 'ds-toolkit' ); ?>)</span><?php endif; ?></span>
						<?php if ( $show_pos && '' !== $p['pos'] ) : ?><span class="dsr-pos"><?php echo esc_html( $p['pos'] ); ?></span><?php endif; ?>
						<?php if ( $meta ) : ?><span class="dsr-meta"><?php echo esc_html( implode( ' · ', $meta ) ); ?></span><?php endif; ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $coaches ) : ?>
		<div class="dsr-coaches">
			<h3 class="dsr-sub"><?php echo esc_html( $txt( 'coaches_title', __( 'Coaches', 'ds-toolkit' ) ) ); ?></h3>
			<ul class="dsr-clist">
				<?php foreach ( $coaches as $c ) : ?>
					<li class="dsr-coach"><span class="dsr-med dsr-med--sm" aria-hidden="true"><?php echo esc_html( $c['ini'] ); ?></span><span class="dsr-cname"><?php echo esc_html( $c['name'] ); ?><small><?php esc_html_e( 'Coach', 'ds-toolkit' ); ?></small></span></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
<?php endif; ?>
</div>
