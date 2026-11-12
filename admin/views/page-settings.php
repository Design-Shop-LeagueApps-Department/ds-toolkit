<?php
/**
 * Features tab content.
 * Rendered inside the shared wrap + header + tabs in DS_Toolkit_Admin::render_page().
 *
 * The cards are not written here: DS_Settings_Cards draws them from the schema (DS_Features), group by
 * group, with on/total counts. This file only adds what the schema cannot describe, as $dst_extras:
 * the login logo picker, the ACF mapping table and the live status lines. Group open/closed state, the
 * filter box, the unsaved-changes bar and the "all modules" switch live in assets/js/admin.js.
 *
 * @var array $opts       the saved settings
 * @var array $dst_status status lines computed by DS_Toolkit_Admin: key => html
 * @var array $dst_health  rows of the staff-only Status card (DS_Status::rows()): label, state, text. Empty = no card
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$dst_extras = array();
foreach ( (array) $dst_status as $dst_key => $dst_html ) {
	$dst_extras[ $dst_key ]['status'] = $dst_html;
}

// Login branding: the logo picker (assets/js/admin.js drives the buttons by these ids).
$dst_extras['enable_login_branding']['after'] = function ( $opts ) {
	$logo_id = ! empty( $opts['login_logo_id'] ) ? absint( $opts['login_logo_id'] ) : 0;
	$logo    = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
	?>
	<div class="dst-card-row dst-card-extra" id="dst-logo-row">
		<div class="dst-card-icon"><span class="dashicons dashicons-format-image" aria-hidden="true"></span></div>
		<div class="dst-logo-label">
			<strong>Login Logo</strong>
			<span>Replaces the default LeagueApps logo on the login page.</span>
		</div>
		<div class="dst-logo-picker">
			<div class="dst-logo-preview">
				<img id="dst-logo-img" src="<?php echo esc_url( $logo ? $logo : DS_TOOLKIT_URL . 'assets/images/cropped-LA-circle-logo-1.png' ); ?>" alt="Login logo">
			</div>
			<div class="dst-logo-actions">
				<input type="hidden" id="dst-logo-id" name="ds_toolkit_settings[login_logo_id]" value="<?php echo esc_attr( $logo_id ); ?>">
				<button type="button" class="button" id="dst-logo-select">Select Logo</button>
				<button type="button" class="button" id="dst-logo-remove"<?php echo $logo_id ? '' : ' hidden'; ?>>Use Default</button>
			</div>
		</div>
	</div>
	<?php
};

// ACF to CSS variables: the mapping rows (admin.js adds and removes rows by these ids).
$dst_extras['acf_css_vars_enabled']['after'] = function ( $opts ) {
	$rows = ! empty( $opts['acf_css_vars_mappings'] ) && is_array( $opts['acf_css_vars_mappings'] ) ? array_values( $opts['acf_css_vars_mappings'] ) : array();
	?>
	<div class="dst-card-row dst-card-extra dst-mapping-wrap" id="dst-acf-mappings-row">
		<div class="dst-mapping-container">
			<table class="dst-mapping-table" id="dst-mappings-table">
				<thead>
					<tr>
						<th scope="col">ACF Field Name</th>
						<th scope="col">CSS Variable</th>
						<th scope="col">Fallback Value</th>
						<th scope="col"><span class="screen-reader-text">Remove</span></th>
					</tr>
				</thead>
				<tbody id="dst-mappings-tbody">
					<?php foreach ( $rows as $i => $row ) : ?>
					<tr class="dst-mapping-row">
						<td><input type="text" class="regular-text" aria-label="ACF field name" name="ds_toolkit_settings[acf_css_vars_mappings][<?php echo (int) $i; ?>][acf_field]" value="<?php echo esc_attr( $row['acf_field'] ?? '' ); ?>" placeholder="acf_field_name"></td>
						<td><input type="text" class="regular-text" aria-label="CSS variable" name="ds_toolkit_settings[acf_css_vars_mappings][<?php echo (int) $i; ?>][css_var]" value="<?php echo esc_attr( $row['css_var'] ?? '' ); ?>" placeholder="--css-variable-name"></td>
						<td><input type="text" class="regular-text" aria-label="Fallback value" name="ds_toolkit_settings[acf_css_vars_mappings][<?php echo (int) $i; ?>][fallback]" value="<?php echo esc_attr( $row['fallback'] ?? '' ); ?>" placeholder="optional"></td>
						<td><button type="button" class="button dst-remove-mapping" aria-label="Remove this mapping">&#x2715;</button></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<button type="button" class="button dst-add-mapping" id="dst-add-mapping" data-count="<?php echo (int) count( $rows ); ?>">+ Add Mapping</button>
		</div>
	</div>
	<?php
};
?>
<form method="post" action="options.php" id="dst-features-form">
	<?php settings_fields( 'ds_toolkit_options' ); ?>

	<div class="dst-filter-bar">
		<span class="dashicons dashicons-search" aria-hidden="true"></span>
		<label class="screen-reader-text" for="dst-filter">Filter features</label>
		<input type="search" id="dst-filter" placeholder="Filter features… (e.g. bot, shortcode, hero)" autocomplete="off">
	</div>
	<p class="dst-filter-empty" id="dst-filter-empty" hidden>No feature matches that.</p>

	<?php
	if ( ! empty( $dst_health ) ) :
		$dst_words  = array( 'ok' => 'OK', 'warn' => 'Check', 'bad' => 'Problem', 'off' => 'Off' );
		$dst_counts = array_count_values( array_column( $dst_health, 1 ) );
		$dst_worst  = ! empty( $dst_counts['bad'] ) ? 'bad' : ( ! empty( $dst_counts['warn'] ) ? 'warn' : 'ok' );
		$dst_sum    = 'bad' === $dst_worst ? $dst_counts['bad'] . ' to fix' : ( 'warn' === $dst_worst ? $dst_counts['warn'] . ' to check' : 'All good' );
		?>
	<details class="dst-health" id="dst-health"<?php echo 'ok' === $dst_worst ? '' : ' open'; ?>>
		<summary class="dst-health-head">
			<span class="dst-health-title"><span class="dashicons dashicons-heart" aria-hidden="true"></span> Status <span class="dst-chip dst-chip--staff">Staff only</span></span>
			<span class="dst-health-sum dst-state--<?php echo esc_attr( $dst_worst ); ?>"><?php echo esc_html( $dst_sum ); ?></span>
		</summary>
		<dl class="dst-health-list">
			<?php foreach ( $dst_health as $dst_row ) : ?>
			<div class="dst-health-row">
				<dt><?php echo esc_html( $dst_row[0] ); ?></dt>
				<dd><span class="dst-health-state dst-health-state--<?php echo esc_attr( $dst_row[1] ); ?>"><?php echo esc_html( $dst_words[ $dst_row[1] ] ?? '' ); ?></span><span class="dst-health-text"><?php echo esc_html( $dst_row[2] ); ?></span></dd>
			</div>
			<?php endforeach; ?>
		</dl>
	</details>
	<?php endif; ?>

	<?php ( new DS_Settings_Cards( $opts, $dst_extras ) )->render(); ?>

	<div class="dst-savebar" id="dst-savebar">
		<span class="dst-savebar-state" id="dst-savebar-state" role="status" aria-live="polite">No unsaved changes</span>
		<span class="dst-savebar-meta">
			<a href="https://github.com/Design-Shop-LeagueApps-Department/ds-toolkit" target="_blank" rel="noopener">GitHub</a>
			&nbsp;&middot;&nbsp; By LeagueApps Design Shop
		</span>
		<?php submit_button( 'Save Changes', 'primary', 'submit', false ); ?>
	</div>
</form>
