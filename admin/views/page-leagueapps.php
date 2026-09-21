<?php
/**
 * LeagueApps tab: the site IDs and public API keys the Programs module reads.
 * Rendered inside the shared wrap + header + tabs in DS_Toolkit_Admin::render_page().
 * Variables available: $leagueapps_sites, $programs_module_on, $la_sports
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$la_flush_url = wp_nonce_url( admin_url( 'admin-post.php?action=ds_programs_flush' ), 'ds_programs_flush' );
?>
<form method="post" action="options.php">
    <?php settings_fields( 'ds_toolkit_options' ); ?>

    <p class="dst-section-title">LeagueApps account</p>

    <div class="dst-card">
        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-list-view"></span></div>
            <div class="dst-card-info">
                <strong>Sites the Programs module can list</strong>
                <span>One row per LeagueApps site. The <em>site ID</em> is the number in the LeagueApps manager URL (<code>manager.leagueapps.com/console/sites/<strong>46287</strong></code>). The <em>API key</em> is the public widget key from Connect &rarr; API Settings (the same key the hosted listing widget uses). Keys are read server-side only and never reach a visitor's browser. A partner with several LeagueApps sites (a main club plus a facility) lists each; a module can show all of them merged or pick one.</span>
                <?php if ( ! $programs_module_on ) : ?>
                    <span style="display:block;margin-top:8px"><strong>The LeagueApps Programs block is switched off.</strong> Turn it on under Features &rarr; LeagueApps Modules for these settings to have any effect.</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="dst-card-row dst-mapping-wrap">
            <div class="dst-mapping-container">
                <table class="dst-mapping-table" id="dst-la-sites-table">
                    <thead>
                        <tr>
                            <th>Label (optional)</th>
                            <th>Site ID</th>
                            <th>Public API key</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="dst-la-sites-tbody">
                        <?php foreach ( $leagueapps_sites as $i => $site ) : ?>
                        <tr class="dst-mapping-row">
                            <td><input type="text" class="regular-text" name="ds_toolkit_settings[leagueapps_sites][<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $site['label'] ); ?>" placeholder="Main site"></td>
                            <td><input type="text" class="regular-text" inputmode="numeric" name="ds_toolkit_settings[leagueapps_sites][<?php echo (int) $i; ?>][site_id]" value="<?php echo esc_attr( $site['site_id'] ); ?>" placeholder="46287"></td>
                            <td><input type="text" class="regular-text" autocomplete="off" spellcheck="false" name="ds_toolkit_settings[leagueapps_sites][<?php echo (int) $i; ?>][api_key]" value="<?php echo esc_attr( $site['api_key'] ); ?>" placeholder="public widget key"></td>
                            <td><button type="button" class="button dst-remove-mapping" title="Remove">&#x2715;</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="button" class="button" id="dst-la-add-site" data-count="<?php echo count( $leagueapps_sites ); ?>">+ Add site</button>
            </div>
        </div>

        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-update"></span></div>
            <div class="dst-card-info">
                <strong>Feed cache</strong>
                <span>Listings are fetched from LeagueApps at most every 10 minutes and the last good copy is kept for 7 days in case LeagueApps is unreachable. Saving this tab clears the cache. Editors also get a "Refresh LeagueApps listings" link in the admin bar on the front end.
                <?php if ( $la_sports ) : ?>
                    Sports seen in the last feed: <?php echo esc_html( implode( ', ', $la_sports ) ); ?>.
                <?php endif; ?>
                </span>
            </div>
            <div class="dst-field-inline">
                <a class="button" href="<?php echo esc_url( $la_flush_url ); ?>">Refresh now</a>
            </div>
        </div>
    </div>

    <div class="dst-footer">
        <?php submit_button( 'Save Changes', 'primary', 'submit', false ); ?>
        <span class="dst-footer-meta">
            <a href="https://github.com/Design-Shop-LeagueApps-Department/ds-toolkit" target="_blank" rel="noopener">GitHub</a>
            &nbsp;&middot;&nbsp; By Alipio Gabriel
        </span>
    </div>
</form>
<script>
( function () {
    var add = document.getElementById( 'dst-la-add-site' );
    var body = document.getElementById( 'dst-la-sites-tbody' );
    if ( ! add || ! body ) { return; }
    add.addEventListener( 'click', function () {
        var i = parseInt( add.getAttribute( 'data-count' ), 10 ) || 0;
        var tr = document.createElement( 'tr' );
        tr.className = 'dst-mapping-row';
        tr.innerHTML =
            '<td><input type="text" class="regular-text" name="ds_toolkit_settings[leagueapps_sites][' + i + '][label]" placeholder="Main site"></td>' +
            '<td><input type="text" class="regular-text" inputmode="numeric" name="ds_toolkit_settings[leagueapps_sites][' + i + '][site_id]" placeholder="46287"></td>' +
            '<td><input type="text" class="regular-text" autocomplete="off" spellcheck="false" name="ds_toolkit_settings[leagueapps_sites][' + i + '][api_key]" placeholder="public widget key"></td>' +
            '<td><button type="button" class="button dst-remove-mapping" title="Remove">&#x2715;</button></td>';
        body.appendChild( tr );
        add.setAttribute( 'data-count', String( i + 1 ) );
    } );
    body.addEventListener( 'click', function ( e ) {
        var b = e.target.closest( '.dst-remove-mapping' );
        if ( b ) { b.closest( 'tr' ).remove(); }
    } );
} )();
</script>
