<?php
/**
 * LeagueApps tab: the site IDs and public API keys the Programs module reads.
 * Rendered inside the shared wrap + header + tabs in DS_Toolkit_Admin::render_page().
 * Variables available: $leagueapps_sites, $programs_module_on, $la_sports,
 * $la_activity (DS_Programs_Data::ledger_summary()), $la_ledger (recent fetches)
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

        <?php if ( $leagueapps_sites ) :
            $la_why = array( 'first' => 'first load', 'expired' => 'cache expired', 'flush' => 'manual refresh' );
        ?>
        <div class="dst-card-row">
            <div class="dst-card-icon"><span class="dashicons dashicons-chart-line"></span></div>
            <div class="dst-card-info">
                <strong>Feed activity</strong>
                <span>
                    <?php if ( empty( $la_activity['last'] ) ) : ?>
                        No fetch recorded yet. The first page view of a Programs module will make one.
                    <?php else : ?>
                        Last 24 hours: <strong><?php echo (int) $la_activity['fetches']; ?></strong> fetches from LeagueApps
                        (<?php echo (int) $la_activity['requests']; ?> HTTP requests including retries,
                        <?php echo (int) $la_activity['failures']; ?> failed).
                        The 10-minute cache allows at most <?php echo (int) $la_activity['budget']; ?> a day for
                        <?php echo count( $leagueapps_sites ); ?> site<?php echo count( $leagueapps_sites ) === 1 ? '' : 's'; ?>,
                        and a site nobody visits sends none.
                        <?php if ( ! empty( $la_activity['over'] ) ) : ?>
                            <strong style="color:#b32d2e">That is more than the cache should allow: the host's object cache is dropping the feed between visits, so it is being refetched on demand. Listings still render, and each burst is still limited to one fetch, but tell Alipio.</strong>
                        <?php endif; ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <?php if ( $la_ledger ) : ?>
        <div class="dst-card-row dst-mapping-wrap">
            <div class="dst-mapping-container">
                <table class="dst-mapping-table dst-la-ledger">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Site</th>
                            <th>Result</th>
                            <th>Requests</th>
                            <th>Took</th>
                            <th>Programs</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $la_ledger as $e ) : $e = (array) $e; ?>
                        <tr>
                            <td title="<?php echo esc_attr( date_i18n( 'Y-m-d H:i:s', (int) $e['t'] + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) ); ?>"><?php echo esc_html( human_time_diff( (int) $e['t'], time() ) ); ?> ago</td>
                            <td><?php echo esc_html( $e['site'] ?? '' ); ?></td>
                            <td><?php if ( ! empty( $e['ok'] ) ) : ?><span style="color:#1a7f37">OK</span><?php else : ?><span style="color:#b32d2e" title="<?php echo esc_attr( $e['err'] ?? '' ); ?>">Failed<?php echo ! empty( $e['code'] ) ? ' (HTTP ' . (int) $e['code'] . ')' : ''; ?></span><?php endif; ?></td>
                            <td><?php echo (int) ( $e['tries'] ?? 1 ); ?></td>
                            <td><?php echo (int) ( $e['ms'] ?? 0 ); ?> ms</td>
                            <td><?php echo ! empty( $e['ok'] ) ? (int) ( $e['rows'] ?? 0 ) : '&ndash;'; ?></td>
                            <td><?php echo esc_html( $la_why[ $e['why'] ?? '' ] ?? ( $e['why'] ?? '' ) ); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; endif; ?>
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
