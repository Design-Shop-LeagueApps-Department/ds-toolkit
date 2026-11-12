<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Draws the Features tab of Settings > DS Toolkit from the schema (DS_Features): the groups, one card per
 * feature (name, scope chip, one-sentence summary, the detail behind "More", its toggle), the feature's own
 * settings (rarely changed ones behind "Advanced"), the Beaver Builder blocks list and the save bar.
 *
 * A card that needs something the schema cannot describe (the login logo picker, the ACF mapping table, a live
 * status line) gets it from $extras: key => array( 'status' => html, 'after' => callable ). Everything else is
 * data: a new feature shows up here by adding its entry to DS_Features, with no markup to write.
 */
class DS_Settings_Cards {

	private $opts;
	private $extras;
	private $bp;
	private $all;

	public function __construct( array $opts, array $extras = array() ) {
		$this->opts   = $opts;
		$this->extras = $extras;
		$this->bp     = DS_Toolkit::blueprint_version();
		$this->all    = DS_Features::all();
	}

	/** Is this setting on? A toggle nobody has saved yet reads as its default (so a fleet-wide feature shows on). */
	private function on( $key ) {
		if ( array_key_exists( $key, $this->opts ) ) {
			return ! empty( $this->opts[ $key ] );
		}
		return ! empty( $this->all[ $key ]['default'] ) && $this->bp >= (int) ( $this->all[ $key ]['default_bp'] ?? 0 );
	}

	/** A card shows when its feature can run on this site's blueprint generation. */
	private function shown( $key ) {
		return isset( $this->all[ $key ] ) && $this->bp >= (int) ( $this->all[ $key ]['min_blueprint'] ?? 0 );
	}

	private function value( $name, array $field ) {
		$v = $this->opts[ $name ] ?? '';
		if ( '' === $v || null === $v ) {
			$v = $field['default'] ?? '';
		}
		return $v;
	}

	public function render() {
		$layout  = DS_Features::layout();
		$modules = DS_Features::modules();
		foreach ( DS_Features::GROUPS as $group => $meta ) {
			list( $label, $icon, $open ) = $meta;
			$keys = 'modules' === $group ? array_keys( $modules ) : array_values( array_filter( (array) ( $layout[ $group ] ?? array() ), array( $this, 'shown' ) ) );
			if ( ! $keys ) {
				continue;
			}
			$on = count( array_filter( $keys, array( $this, 'on' ) ) );
			?>
			<details class="dst-group" id="dst-g-<?php echo esc_attr( $group ); ?>" data-dst-group="<?php echo esc_attr( $group ); ?>" data-dst-label="<?php echo esc_attr( $label ); ?>"<?php echo $open ? ' open' : ''; ?>>
				<summary class="dst-group-head">
					<span class="dst-group-title"><span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span> <?php echo esc_html( $label ); ?></span>
					<span class="dst-group-count" data-dst-count><?php echo esc_html( $on . ' of ' . count( $keys ) . ' on' ); ?></span>
				</summary>
				<div class="dst-group-body">
					<?php
					if ( 'modules' === $group ) {
						$this->modules_card( $modules );
					} else {
						foreach ( $keys as $key ) {
							$this->card( $key );
						}
					}
					?>
				</div>
			</details>
			<?php
		}
	}

	private function toggle( $key, $label_id, $desc_id, $class = '' ) {
		?>
		<div class="dst-toggle">
			<input type="hidden" name="ds_toolkit_settings[<?php echo esc_attr( $key ); ?>]" value="0">
			<input type="checkbox" role="switch" id="<?php echo esc_attr( $key ); ?>"<?php echo $class ? ' class="' . esc_attr( $class ) . '"' : ''; ?> name="ds_toolkit_settings[<?php echo esc_attr( $key ); ?>]" value="1" aria-labelledby="<?php echo esc_attr( $label_id ); ?>" aria-describedby="<?php echo esc_attr( $desc_id ); ?>" <?php checked( $this->on( $key ) ); ?>>
			<label for="<?php echo esc_attr( $key ); ?>" aria-hidden="true"></label>
		</div>
		<?php
	}

	private function card( $key ) {
		$def    = $this->all[ $key ];
		$extra  = (array) ( $this->extras[ $key ] ?? array() );
		$fields = array_filter( (array) ( $def['fields'] ?? array() ), function ( $f ) { return false !== ( $f['ui'] ?? true ); } );
		$basic  = array_filter( $fields, function ( $f ) { return empty( $f['advanced'] ); } );
		$adv    = array_filter( $fields, function ( $f ) { return ! empty( $f['advanced'] ); } );
		$scope  = (string) ( $def['scope'] ?? '' );
		?>
		<div class="dst-card<?php echo $this->on( $key ) ? '' : ' is-off'; ?>" data-dst-card="<?php echo esc_attr( $key ); ?>">
			<div class="dst-card-row">
				<div class="dst-card-icon"><span class="dashicons dashicons-<?php echo esc_attr( $def['icon'] ); ?>" aria-hidden="true"></span></div>
				<div class="dst-card-info">
					<span class="dst-card-title">
						<strong id="dst-t-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $def['label'] ); ?></strong>
						<?php if ( isset( DS_Features::SCOPES[ $scope ] ) ) : ?><span class="dst-chip dst-chip--<?php echo esc_attr( $scope ); ?>"><?php echo esc_html( DS_Features::SCOPES[ $scope ] ); ?></span><?php endif; ?>
						<?php $this->changed_tag(); ?>
					</span>
					<span class="dst-summary" id="dst-s-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $def['summary'] ); ?></span>
					<?php if ( ! empty( $extra['status'] ) ) : ?><span class="dst-status"><?php echo wp_kses_post( $extra['status'] ); ?></span><?php endif; ?>
					<?php if ( ! empty( $def['more'] ) ) : ?>
					<details class="dst-more">
						<summary>More</summary>
						<div class="dst-more-body"><?php echo wp_kses_post( $def['more'] ); ?></div>
					</details>
					<?php endif; ?>
				</div>
				<?php $this->toggle( $key, 'dst-t-' . $key, 'dst-s-' . $key ); ?>
			</div>
			<?php if ( $basic || $adv ) : ?>
			<div class="dst-card-fields">
				<?php
				foreach ( $basic as $name => $field ) {
					$this->field( $name, $field );
				}
				if ( $adv ) :
					?>
					<details class="dst-advanced">
						<summary>Advanced <span class="dst-advanced-count">(<?php echo (int) count( $adv ); ?>)</span></summary>
						<div class="dst-advanced-body">
							<?php
							foreach ( $adv as $name => $field ) {
								$this->field( $name, $field );
							}
							?>
						</div>
					</details>
				<?php endif; ?>
			</div>
			<?php endif; ?>
			<?php
			if ( ! empty( $extra['after'] ) && is_callable( $extra['after'] ) ) {
				call_user_func( $extra['after'], $this->opts );
			}
			?>
		</div>
		<?php
	}

	/** One of a feature's own settings, drawn by its type. */
	private function field( $name, array $f ) {
		$id    = 'dst-f-' . $name;
		$input = 'ds_toolkit_settings[' . $name . ']';
		$value = $this->value( $name, $f );
		$help  = (string) ( $f['help'] ?? '' );
		if ( 'bool' === $f['type'] ) {
			$on = array_key_exists( $name, $this->opts ) ? ! empty( $this->opts[ $name ] ) : ! empty( $f['default'] );
			?>
			<div class="dst-field dst-field--check">
				<input type="hidden" name="<?php echo esc_attr( $input ); ?>" value="0">
				<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $input ); ?>" value="1"<?php echo $help ? ' aria-describedby="' . esc_attr( $id ) . '-help"' : ''; ?> <?php checked( $on ); ?>>
				<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $f['label'] ); ?></label>
				<?php if ( $help ) : ?><span class="dst-field-help" id="<?php echo esc_attr( $id ); ?>-help"><?php echo esc_html( $help ); ?></span><?php endif; ?>
			</div>
			<?php
			return;
		}
		if ( 'choice' === $f['type'] ) {
			?>
			<div class="dst-field dst-field--choice" role="radiogroup" aria-labelledby="<?php echo esc_attr( $id ); ?>-label">
				<span class="dst-field-label" id="<?php echo esc_attr( $id ); ?>-label"><?php echo esc_html( $f['label'] ); ?></span>
				<span class="dst-field-control">
					<?php foreach ( (array) $f['options'] as $opt => $text ) : ?>
					<label><input type="radio" name="<?php echo esc_attr( $input ); ?>" value="<?php echo esc_attr( $opt ); ?>" <?php checked( (string) $opt, (string) $value ); ?>> <?php echo esc_html( $text ); ?></label>
					<?php endforeach; ?>
				</span>
			</div>
			<?php
			return;
		}
		$describe = $help ? ' aria-describedby="' . esc_attr( $id ) . '-help"' : '';
		?>
		<div class="dst-field dst-field--<?php echo esc_attr( $f['type'] ); ?>">
			<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $f['label'] ); ?></label>
			<span class="dst-field-control">
				<?php if ( 'lines' === $f['type'] ) : ?>
					<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $input ); ?>" rows="<?php echo (int) ( $f['rows'] ?? 4 ); ?>"<?php echo $describe; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts ?>><?php echo esc_textarea( (string) ( $this->opts[ $name ] ?? ( $f['default'] ?? '' ) ) ); ?></textarea>
				<?php elseif ( 'int' === $f['type'] ) : ?>
					<input type="number" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $input ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo isset( $f['min'] ) ? ' min="' . (int) $f['min'] . '"' : ''; ?><?php echo isset( $f['max'] ) ? ' max="' . (int) $f['max'] . '"' : ''; ?> step="1"<?php echo $describe; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<?php else : ?>
					<input type="<?php echo esc_attr( in_array( $f['type'], array( 'url', 'email' ), true ) ? $f['type'] : 'text' ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $input ); ?>" value="<?php echo esc_attr( $value ); ?>"<?php echo 'digits' === $f['type'] ? ' inputmode="numeric" pattern="[0-9]*"' : ''; ?><?php echo 'email' === $f['type'] && ! empty( $f['multiple'] ) ? ' multiple' : ''; ?><?php echo $describe; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
				<?php endif; ?>
				<?php if ( $help ) : ?><span class="dst-field-help" id="<?php echo esc_attr( $id ); ?>-help"><?php echo esc_html( $help ); ?></span><?php endif; ?>
			</span>
		</div>
		<?php
	}

	/** Shown by assets/js/admin.js when anything in the card differs from what is saved. */
	private function changed_tag() {
		echo '<span class="dst-chip dst-chip--changed" data-dst-changed hidden>Changed, not saved</span>';
	}

	/**
	 * The Beaver Builder blocks: a switch for all of them, then a grid of tiles, one per block (name, one line,
	 * switch). Each tile is a card of its own, so the filter, the counts and the "changed" tag treat it like any
	 * other card. Until the settings redesign the blocks were twenty full-width rows inside one card.
	 */
	private function modules_card( array $modules ) {
		?>
		<div class="dst-modules-all">
			<span class="dst-modules-all-text">
				<strong id="dst-t-modules-all">All modules</strong>
				<span id="dst-s-modules-all">Switches every LeagueApps block on or off at once. Remember to save.</span>
			</span>
			<div class="dst-toggle">
				<input type="checkbox" role="switch" id="dst-modules-all" aria-labelledby="dst-t-modules-all" aria-describedby="dst-s-modules-all">
				<label for="dst-modules-all" aria-hidden="true"></label>
			</div>
		</div>
		<div class="dst-module-grid">
			<?php foreach ( $modules as $key => $mod ) : ?>
			<div class="dst-card dst-card--tile<?php echo $this->on( $key ) ? '' : ' is-off'; ?>" data-dst-card="<?php echo esc_attr( $key ); ?>">
				<div class="dst-card-row">
					<div class="dst-card-info">
						<span class="dst-card-title">
							<strong id="dst-t-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $mod['label'] ); ?></strong>
							<?php $this->changed_tag(); ?>
						</span>
						<span class="dst-summary" id="dst-s-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $mod['desc'] ); ?></span>
					</div>
					<?php $this->toggle( $key, 'dst-t-' . $key, 'dst-s-' . $key, 'dst-module-toggle' ); ?>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
		<?php
	}
}
