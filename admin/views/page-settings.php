<?php
/**
 * Features tab content.
 * Rendered inside the shared wrap + header + tabs in DS_Toolkit_Admin::render_page().
 *
 * The cards are not written here: DS_Settings_Cards draws them from the schema (DS_Features), group by
 * group, with on/total counts. This file only adds what the schema cannot describe, as $dst_extras:
 * the login logo picker, the ACF mapping table, the copyright text box and the live status lines.
 *
 * Layout (settings redesign): a toolbar (find, show All / On / Off / Changed, jump to a group), then the
 * groups on the left and a panel on the right with the Status rows, what changed since the last save and
 * a few facts about this site. The filter, the chips, the counts, the "changed" tags, the list of changes
 * and the save bar live in assets/js/admin.js; without it the page still reads and saves.
 *
 * @var array $opts       the saved settings
 * @var array $dst_status status lines computed by DS_Toolkit_Admin: key => html
 * @var array $dst_health rows of the staff-only Status panel (DS_Status::rows()): label, state, text. Empty = no panel
 * @var array $dst_site   blueprint, update channel and where this copy runs, for the "This site" panel
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

// [ds_copyright]: the text it prints (this was the whole General tab until the settings redesign).
$dst_extras['copyright_shortcode_enabled']['after'] = function ( $opts ) {
	$text = isset( $opts['footer_copyright_text'] ) && '' !== $opts['footer_copyright_text'] ? (string) $opts['footer_copyright_text'] : '&copy; {year} LeagueApps Design Shop';
	?>
	<div class="dst-card-fields dst-card-extra">
		<div class="dst-field dst-field--text">
			<label for="dst-f-footer_copyright_text">Copyright text</label>
			<span class="dst-field-control">
				<input type="text" id="dst-f-footer_copyright_text" name="ds_toolkit_settings[footer_copyright_text]" value="<?php echo esc_attr( $text ); ?>" aria-describedby="dst-f-footer_copyright_text-help">
				<span class="dst-field-help" id="dst-f-footer_copyright_text-help">Use {year} for the current year. Basic inline tags (a, strong, em, span, br) are allowed. Preview: <?php echo wp_kses_post( str_replace( '{year}', gmdate( 'Y' ), $text ) ); ?></span>
			</span>
		</div>
	</div>
	<?php
};

$dst_jumps = array( 'security' => 'Security', 'partner' => 'Partner', 'modules' => 'Modules', 'site' => 'Site & Content', 'dev' => 'Shortcodes & Dev' );
?>
<form method="post" action="options.php" id="dst-features-form">
	<?php settings_fields( 'ds_toolkit_options' ); ?>

	<div class="dst-toolbar">
		<div class="dst-filter-bar">
			<span class="dashicons dashicons-search" aria-hidden="true"></span>
			<label class="screen-reader-text" for="dst-filter">Find a feature</label>
			<input type="search" id="dst-filter" placeholder="Find a feature: bot, shortcode, hero, login..." autocomplete="off">
		</div>
		<div class="dst-toolbar-row">
			<span class="dst-toolbar-label" id="dst-show-label">Show</span>
			<span class="dst-chips" role="group" aria-labelledby="dst-show-label">
				<button type="button" class="dst-chipbtn is-active" data-dst-show="all" aria-pressed="true">All <span data-dst-n="all"></span></button>
				<button type="button" class="dst-chipbtn" data-dst-show="on" aria-pressed="false">On <span data-dst-n="on"></span></button>
				<button type="button" class="dst-chipbtn" data-dst-show="off" aria-pressed="false">Off <span data-dst-n="off"></span></button>
				<button type="button" class="dst-chipbtn" data-dst-show="changed" aria-pressed="false">Changed <span data-dst-n="changed"></span></button>
			</span>
			<span class="dst-toolbar-sep" aria-hidden="true"></span>
			<span class="dst-toolbar-label" id="dst-jump-label">Jump to</span>
			<nav class="dst-jumps" aria-labelledby="dst-jump-label">
				<?php foreach ( $dst_jumps as $dst_g => $dst_l ) : ?>
				<a class="dst-jump" href="#dst-g-<?php echo esc_attr( $dst_g ); ?>" data-dst-jump="<?php echo esc_attr( $dst_g ); ?>"><?php echo esc_html( $dst_l ); ?> <span class="dst-jump-count" data-dst-jump-count="<?php echo esc_attr( $dst_g ); ?>"></span></a>
				<?php endforeach; ?>
			</nav>
		</div>
	</div>

	<div class="dst-layout">
		<div class="dst-main">
			<p class="dst-filter-empty" id="dst-filter-empty" hidden>No feature matches that. <button type="button" class="button-link" id="dst-filter-reset">Show everything</button></p>

			<?php ( new DS_Settings_Cards( $opts, $dst_extras ) )->render(); ?>

			<div class="dst-savebar" id="dst-savebar">
				<span class="dst-savebar-state" id="dst-savebar-state" role="status" aria-live="polite">No unsaved changes</span>
				<button type="button" class="button" id="dst-discard" hidden>Discard changes</button>
				<span class="dst-savebar-meta">
					<a href="<?php echo esc_url( DS_Env::REPO_URL ); ?>" target="_blank" rel="noopener">GitHub</a>
					&nbsp;&middot;&nbsp; By LeagueApps Design Shop
				</span>
				<?php submit_button( 'Save Changes', 'primary', 'submit', false ); ?>
			</div>
		</div>

		<aside class="dst-rail" aria-label="About this site">
			<?php if ( ! empty( $dst_health ) ) : ?>
			<section class="dst-panel dst-health" id="dst-health" aria-labelledby="dst-health-h">
				<div class="dst-panel-head">
					<span class="dashicons dashicons-heart" aria-hidden="true"></span>
					<h2 id="dst-health-h">Status</h2>
					<span class="dst-chip dst-chip--staff">Staff only</span>
				</div>
				<dl class="dst-health-list">
					<?php
					$dst_words = array( 'ok' => 'OK', 'warn' => 'Check', 'bad' => 'Problem', 'off' => 'Off' );
					foreach ( $dst_health as $dst_row ) :
						?>
					<div class="dst-health-row">
						<dt><span class="dst-health-state dst-health-state--<?php echo esc_attr( $dst_row[1] ); ?>"><?php echo esc_html( $dst_words[ $dst_row[1] ] ?? '' ); ?></span> <?php echo esc_html( $dst_row[0] ); ?></dt>
						<dd><?php echo esc_html( $dst_row[2] ); ?></dd>
					</div>
					<?php endforeach; ?>
				</dl>
			</section>
			<?php endif; ?>

			<section class="dst-panel" aria-labelledby="dst-changed-h">
				<div class="dst-panel-head"><h2 id="dst-changed-h">Changed since the last save</h2></div>
				<ul class="dst-changed-list" id="dst-changed-list" hidden></ul>
				<p class="dst-panel-note" id="dst-changed-none">Nothing yet. Flip a switch and it is listed here until you save.</p>
			</section>

			<section class="dst-panel" aria-labelledby="dst-site-h">
				<div class="dst-panel-head"><h2 id="dst-site-h">This site</h2></div>
				<dl class="dst-facts">
					<dt>Blueprint</dt><dd>Launchpad <?php echo (int) $dst_site['blueprint']; ?></dd>
					<dt>Update channel</dt><dd><?php echo esc_html( $dst_site['channel'] ); ?></dd>
					<dt>Address</dt><dd><?php echo esc_html( $dst_site['where'] ); ?></dd>
					<dt>Features on</dt><dd id="dst-on-total">&nbsp;</dd>
				</dl>
				<p class="dst-panel-links">
					<a href="<?php echo esc_url( DS_Toolkit_Admin::HQ_DOCS_URL ); ?>" target="_blank" rel="noopener">Toolkit docs on Design Shop HQ</a>
					<a href="<?php echo esc_url( DS_Env::REPO_URL . '/releases' ); ?>" target="_blank" rel="noopener">Release notes</a>
				</p>
			</section>
		</aside>
	</div>
</form>
