<?php
/**
 * LeagueApps tab: the site IDs and public API keys the Programs and Calendar modules read.
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
                <strong>Sites the LeagueApps modules can read</strong>
                <span>One row per LeagueApps site. The <em>site ID</em> is the number in the LeagueApps manager URL (<code>manager.leagueapps.com/console/sites/<strong>46287</strong></code>). The <em>API key</em> is the public widget key from Connect &rarr; API Settings (the same key the hosted listing widget uses). Keys are read server-side only and never reach a visitor's browser. A partner with several LeagueApps sites (a main club plus a facility) lists each; a module can show all of them merged or pick one.</span>
                <?php if ( ! $programs_module_on ) : ?>
                    <span style="display:block;margin-top:8px"><strong>The LeagueApps Programs and Calendar blocks are both switched off.</strong> Turn one on under Features &rarr; LeagueApps Modules for these settings to have any effect.</span>
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

<?php if ( ! empty( $roster_module_on ) && class_exists( 'DS_Roster_Data' ) ) :
    $ds_roster_note = get_transient( 'ds_la_roster_msg_' . get_current_user_id() );
    delete_transient( 'ds_la_roster_msg_' . get_current_user_id() );
    $ds_roster_msg  = is_array( $ds_roster_note ) ? (string) $ds_roster_note['m'] : '';
    $ds_roster_ok   = is_array( $ds_roster_note ) && ! empty( $ds_roster_note['ok'] );
?>
<p class="dst-section-title" id="ds-la-roster" style="margin-top:40px">Private API key (rosters)</p>
<div class="dst-card">
    <div class="dst-card-row">
        <div class="dst-card-icon"><span class="dashicons dashicons-groups"></span></div>
        <div class="dst-card-info">
            <strong>For the Roster block (LeagueApps group in the builder)</strong>
            <span>Team rosters are only in LeagueApps' private API. In LeagueApps: Connect &rarr; API Settings &rarr; <em>Generate Private API Key</em> downloads a <code>.p12</code> file named after the client ID. Upload it here for the matching site. The key can read every family's details, so it is stored encrypted (bound to this site and this install, so a copied database cannot use it), is never shown again, is only sent to LeagueApps' login, and only an administrator can add or remove it, over HTTPS. Only names, positions, graduation years and heights of rostered players and coaches are kept; nothing else is saved. Rosters refresh every hour in the background.</span>
            <?php $ds_kek = DS_Roster_Data::kek_in_use(); ?>
            <span style="display:block;margin-top:8px">Encryption secret: <?php echo 'salts' === $ds_kek ? 'this site\'s WordPress salts. <em>Stronger: add <code>define( \'DS_LA_KEY_SECRET\', \'&lt;64 random characters&gt;\' );</code> to wp-config.php; it keeps the secret out of reach of a salt rotation by a security plugin. Saved keys switch over by themselves.</em>' : ( 'constant' === $ds_kek ? '<code>DS_LA_KEY_SECRET</code> in wp-config.php.' : '<code>DS_LA_KEY_SECRET</code> environment variable.' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed strings ?></span>
            <?php if ( '' !== $ds_roster_msg ) : ?>
                <span style="display:block;margin-top:8px;font-weight:600;color:<?php echo $ds_roster_ok ? '#1a7f37' : '#b32d2e'; ?>"><?php echo esc_html( $ds_roster_msg ); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php if ( ! $leagueapps_sites ) : ?>
        <div class="dst-card-row"><div class="dst-card-info"><span>Add the club's site ID and public key above first.</span></div></div>
    <?php else : foreach ( $leagueapps_sites as $site ) :
        $st = DS_Roster_Data::key_status( $site['site_id'] ); ?>
        <div class="dst-card-row" style="display:block">
            <div class="dst-card-info" style="max-width:none">
                <strong><?php echo esc_html( ( '' !== $site['label'] ? $site['label'] . ' ' : '' ) . '(' . $site['site_id'] . ')' ); ?></strong>
                <span>
                <?php if ( ! $st ) : ?>
                    No private key.
                <?php else : ?>
                    Key saved <?php echo esc_html( human_time_diff( $st['added'], time() ) ); ?> ago (client ID <?php echo esc_html( substr( $st['client_id'], 0, 6 ) ); ?>&hellip;, fingerprint <?php echo esc_html( $st['print'] ); ?>).
                    <?php if ( ! $st['readable'] ) : ?><strong style="color:#b32d2e">It cannot be read on this install (the site was copied or moved, or its secret keys changed): upload it again.</strong><?php endif; ?>
                    <?php echo $st['synced'] ? esc_html( sprintf( 'Last sync %s ago, %d teams with rosters.', human_time_diff( $st['synced'], time() ), $st['teams'] ) ) : ''; ?>
                    <?php if ( $st['partial'] ) : ?>
                        <?php echo esc_html( sprintf( '%s in progress: %d teams read so far, registrations up to %s. It runs in the background a minute at a time; a large club takes several minutes.', $st['synced'] ? 'Daily full sync' : 'First sync', $st['partial']['teams'], wp_date( 'M j, Y', $st['partial']['upto'] ) ) ); ?>
                        <?php if ( '' !== $st['error'] ) : ?><strong style="color:#996800"><?php echo esc_html( $st['partial']['retries'] <= DS_Roster_Data::RETRY_MAX ? $st['error'] . ' It carries on by itself in two minutes.' : $st['error'] . ' It tries again at the next hourly sync.' ); ?></strong><?php endif; ?>
                    <?php elseif ( ! $st['synced'] ) : ?>
                        Syncing in the background (a large club takes a few minutes).
                    <?php endif; ?>
                    <?php if ( '' !== $st['error'] && ! $st['partial'] ) : ?><strong style="color:#b32d2e"><?php echo esc_html( 'Last sync problem: ' . $st['error'] ); ?></strong><?php endif; ?>
                <?php endif; ?>
                </span>
            </div>
            <div style="display:flex;gap:8px 12px;flex-wrap:wrap;align-items:center;margin-top:12px">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                    <?php wp_nonce_field( 'ds_la_roster_key' ); ?>
                    <input type="hidden" name="action" value="ds_la_roster_key">
                    <input type="hidden" name="site_id" value="<?php echo esc_attr( $site['site_id'] ); ?>">
                    <input type="text" name="client_id" class="regular-text" style="width:15em" autocomplete="off" spellcheck="false" placeholder="Client ID (blank = from file name)">
                    <input type="file" name="key_file" accept=".p12,.pem">
                    <button type="submit" class="button button-primary"><?php echo $st ? 'Replace key' : 'Save key'; ?></button>
                </form>
                <?php if ( $st ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'ds_la_roster_sync' ); ?>
                    <input type="hidden" name="action" value="ds_la_roster_sync">
                    <input type="hidden" name="site_id" value="<?php echo esc_attr( $site['site_id'] ); ?>">
                    <button type="submit" class="button">Sync rosters now</button>
                </form>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Remove the private key and the stored rosters for this site?');">
                    <?php wp_nonce_field( 'ds_la_roster_key_delete' ); ?>
                    <input type="hidden" name="action" value="ds_la_roster_key_delete">
                    <input type="hidden" name="site_id" value="<?php echo esc_attr( $site['site_id'] ); ?>">
                    <button type="submit" class="button">Remove key</button>
                </form>
                <?php endif; ?>
            </div>
            <?php $ds_audit = array_slice( DS_Roster_Data::audit_log( $site['site_id'] ), 0, 5 ); if ( $ds_audit ) : ?>
                <details style="margin-top:10px"><summary style="cursor:pointer">Key history</summary>
                    <ul style="margin:6px 0 0 18px;list-style:disc">
                    <?php foreach ( $ds_audit as $ds_e ) : ?>
                        <li><?php echo esc_html( sprintf( '%s: %s by %s (fingerprint %s)', wp_date( 'Y-m-d H:i', (int) $ds_e['t'] ), $ds_e['event'], $ds_e['user'], '' !== $ds_e['print'] ? $ds_e['print'] : '-' ) ); ?></li>
                    <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </div>
    <?php endforeach; endif; ?>
</div>
<?php endif; ?>
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
