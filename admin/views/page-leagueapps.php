<?php
/**
 * LeagueApps tab: one card per LeagueApps site (label, site ID, public key, then how its programs feed and its
 * private roster key are doing), the feed activity of the last day, and the reference text in a side panel.
 * Rendered inside the shared wrap + header + tabs in DS_Toolkit_Admin::render_page().
 *
 * Until the settings redesign the sites were listed twice (a table of keys, then again for the roster keys
 * further down), so a broken roster key sat a screen away from the site it belonged to.
 *
 * The roster key buttons post to admin-post.php through their own forms, which cannot sit inside the
 * settings form: they are printed after it and the fields in each card point at them with form="...".
 *
 * Variables available: $leagueapps_sites, $programs_module_on, $roster_module_on, $la_sports,
 * $la_activity (DS_Programs_Data::ledger_summary()), $la_ledger (recent fetches), $la_ledger_all,
 * $la_hours (DS_Programs_Data::ledger_hours())
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$la_flush_url = wp_nonce_url( admin_url( 'admin-post.php?action=ds_programs_flush' ), 'ds_programs_flush' );
$la_why       = array( 'first' => 'first load', 'expired' => 'cache expired', 'flush' => 'manual refresh', 'refresh' => 'background refresh' );
$la_roster    = ! empty( $roster_module_on ) && class_exists( 'DS_Roster_Data' );
$la_note      = $la_roster ? get_transient( 'ds_la_roster_msg_' . get_current_user_id() ) : false;
if ( $la_note ) {
	delete_transient( 'ds_la_roster_msg_' . get_current_user_id() );
}

// The newest fetch of each site, for its feed line.
$la_last = array();
foreach ( (array) $la_ledger_all as $la_e ) {
	$la_e = (array) $la_e;
	$la_s = (string) ( $la_e['site'] ?? '' );
	if ( '' !== $la_s && ! isset( $la_last[ $la_s ] ) ) {
		$la_last[ $la_s ] = $la_e;
	}
}

/** How one site's programs feed is doing: array( state, sentence ). */
$la_feed = function ( $site_id ) use ( $la_last, $la_why ) {
	$e = $la_last[ (string) $site_id ] ?? null;
	if ( ! $e ) {
		return array( 'off', 'No fetch yet. The first page view of a LeagueApps block makes one.' );
	}
	$ago = human_time_diff( (int) $e['t'], time() );
	$why = $la_why[ $e['why'] ?? '' ] ?? (string) ( $e['why'] ?? '' );
	if ( ! empty( $e['ok'] ) ) {
		$n = (int) ( $e['rows'] ?? 0 );
		return array( 'ok', sprintf( '%d %s, fetched %s ago (%s).', $n, 1 === $n ? 'program' : 'programs', $ago, $why ) );
	}
	$err = ! empty( $e['code'] ) ? 'HTTP ' . (int) $e['code'] : ( '' !== (string) ( $e['err'] ?? '' ) ? (string) $e['err'] : 'no answer' );
	return array( 'warn', sprintf( 'The last fetch failed %s ago (%s). Visitors keep the last good copy while there is one.', $ago, $err ) );
};

/** How one site's private roster key is doing: array( state, sentence, has key ). */
$la_key = function ( $site_id ) use ( $la_roster ) {
	if ( ! $la_roster ) {
		return array( 'off', 'The Roster block is off. Turn it on under Features, LeagueApps Modules, to add a private key.', false );
	}
	$st = DS_Roster_Data::key_status( $site_id );
	if ( ! $st ) {
		return array( 'off', 'No private key yet. Upload the .p12 file to show team rosters.', false );
	}
	$saved = sprintf( 'Saved %s ago (client ID %s..., fingerprint %s).', human_time_diff( $st['added'], time() ), substr( $st['client_id'], 0, 6 ), $st['print'] );
	if ( ! $st['readable'] ) {
		return array( 'bad', $saved . ' It cannot be read on this install: the site was copied or moved, or its secret keys changed. Upload it again.', true );
	}
	$text  = $saved;
	$state = 'ok';
	if ( $st['synced'] ) {
		$text .= sprintf( ' Last sync %s ago, %d teams with rosters.', human_time_diff( $st['synced'], time() ), $st['teams'] );
	}
	if ( $st['partial'] ) {
		$text .= sprintf( ' %s in progress: %d teams read so far, registrations up to %s. It runs in the background a minute at a time.', $st['synced'] ? 'Daily full sync' : 'First sync', $st['partial']['teams'], wp_date( 'M j, Y', $st['partial']['upto'] ) );
		if ( '' !== $st['error'] ) {
			$state = 'warn';
			$text .= ' ' . $st['error'] . ( $st['partial']['retries'] <= DS_Roster_Data::RETRY_MAX ? ' It carries on by itself in two minutes.' : ' It tries again at the next hourly sync.' );
		}
	} elseif ( ! $st['synced'] ) {
		$text .= ' Syncing in the background (a large club takes a few minutes).';
	}
	if ( '' !== $st['error'] && ! $st['partial'] ) {
		$state = 'warn';
		$text .= ' Last sync problem: ' . $st['error'];
	}
	return array( $state, $text, true );
};

$la_words = array( 'ok' => 'OK', 'warn' => 'Check', 'bad' => 'Problem', 'off' => 'Not set' );
$la_rank  = array( 'ok' => 0, 'off' => 0, 'warn' => 1, 'bad' => 2 );
?>
<?php if ( $la_note && is_array( $la_note ) ) : ?>
<div class="notice <?php echo ! empty( $la_note['ok'] ) ? 'notice-success' : 'notice-error'; ?> inline" id="ds-la-roster"><p><?php echo esc_html( (string) $la_note['m'] ); ?></p></div>
<?php endif; ?>

<div class="dst-layout">
	<div class="dst-main">
		<form method="post" action="options.php" id="dst-la-form">
			<?php settings_fields( 'ds_toolkit_options' ); ?>

			<div class="dst-la-intro">
				<div class="dst-la-intro-text">
					<p class="dst-section-title">LeagueApps sites</p>
					<p>One card per LeagueApps site the blocks read. The site ID is the number in the manager address: <code>manager.leagueapps.com/console/sites/<strong>46287</strong></code>.</p>
				</div>
				<button type="button" class="button" id="dst-la-add-site" data-count="<?php echo count( $leagueapps_sites ); ?>">+ Add site</button>
			</div>

			<?php if ( ! $programs_module_on ) : ?>
			<p class="dst-la-notice"><strong>The LeagueApps Programs, Calendar and Roster blocks are all switched off.</strong> Turn one on under Features, LeagueApps Modules, for these settings to have any effect.</p>
			<?php endif; ?>

			<div class="dst-sites" id="dst-la-sites">
				<?php
				foreach ( $leagueapps_sites as $i => $site ) :
					$feed  = $la_feed( $site['site_id'] );
					$key   = $la_key( $site['site_id'] );
					$worst = $la_rank[ $feed[0] ] >= $la_rank[ $key[0] ] ? $feed[0] : $key[0];
					$name  = '' !== $site['label'] ? $site['label'] : 'Site ' . $site['site_id'];
					$n     = (int) $i;
					?>
				<article class="dst-site<?php echo 'bad' === $worst ? ' dst-site--bad' : ''; ?>" aria-labelledby="dst-site-h-<?php echo $n; ?>">
					<div class="dst-site-head">
						<span class="dst-site-dot dst-site-dot--<?php echo esc_attr( $worst ); ?>" aria-hidden="true"></span>
						<h3 id="dst-site-h-<?php echo $n; ?>"><?php echo esc_html( $name ); ?></h3>
						<span class="dst-site-id">Site <?php echo esc_html( $site['site_id'] ); ?></span>
						<button type="button" class="button dst-site-remove" aria-label="<?php echo esc_attr( 'Remove ' . $name ); ?>">Remove</button>
					</div>
					<div class="dst-site-fields">
						<label class="dst-site-field">Label
							<input type="text" name="ds_toolkit_settings[leagueapps_sites][<?php echo $n; ?>][label]" value="<?php echo esc_attr( $site['label'] ); ?>" placeholder="Main site">
						</label>
						<label class="dst-site-field">Site ID
							<input type="text" inputmode="numeric" name="ds_toolkit_settings[leagueapps_sites][<?php echo $n; ?>][site_id]" value="<?php echo esc_attr( $site['site_id'] ); ?>" placeholder="46287">
						</label>
						<div class="dst-site-field">
							<label for="dst-la-key-<?php echo $n; ?>">Public API key</label>
							<span class="dst-keybox">
								<input type="password" id="dst-la-key-<?php echo $n; ?>" autocomplete="new-password" spellcheck="false" name="ds_toolkit_settings[leagueapps_sites][<?php echo $n; ?>][api_key]" value="<?php echo esc_attr( $site['api_key'] ); ?>" placeholder="public widget key">
								<button type="button" class="button dst-key-show" aria-controls="dst-la-key-<?php echo $n; ?>" aria-pressed="false">Show</button>
							</span>
						</div>
					</div>
					<div class="dst-site-status">
						<div class="dst-statebox dst-statebox--<?php echo esc_attr( $feed[0] ); ?>">
							<div class="dst-statebox-head"><span class="dst-health-state dst-health-state--<?php echo esc_attr( $feed[0] ); ?>"><?php echo esc_html( $la_words[ $feed[0] ] ); ?></span><strong>Programs feed</strong></div>
							<p><?php echo esc_html( $feed[1] ); ?></p>
						</div>
						<div class="dst-statebox dst-statebox--<?php echo esc_attr( $key[0] ); ?>">
							<div class="dst-statebox-head"><span class="dst-health-state dst-health-state--<?php echo esc_attr( $key[0] ); ?>"><?php echo esc_html( $la_words[ $key[0] ] ); ?></span><strong>Roster key (private)</strong></div>
							<p><?php echo esc_html( $key[1] ); ?></p>
							<?php if ( $la_roster ) : ?>
								<?php if ( ! $key[2] || 'bad' === $key[0] ) : ?>
								<div class="dst-actions">
									<input type="file" name="key_file" accept=".p12,.pem" form="dst-rk-<?php echo $n; ?>" aria-label="<?php echo esc_attr( 'Private key file for ' . $name ); ?>">
									<input type="text" name="client_id" class="regular-text dst-w-15" form="dst-rk-<?php echo $n; ?>" autocomplete="off" spellcheck="false" placeholder="Client ID (blank = from file name)" aria-label="Client ID">
									<button type="submit" class="button button-primary" form="dst-rk-<?php echo $n; ?>"><?php echo $key[2] ? 'Upload again' : 'Save key'; ?></button>
								</div>
								<?php else : ?>
								<div class="dst-actions">
									<button type="submit" class="button" form="dst-rs-<?php echo $n; ?>">Sync rosters now</button>
									<button type="submit" class="button" form="dst-rd-<?php echo $n; ?>">Remove key</button>
								</div>
								<details class="dst-history">
									<summary>Replace the key</summary>
									<div class="dst-actions">
										<input type="file" name="key_file" accept=".p12,.pem" form="dst-rk-<?php echo $n; ?>" aria-label="<?php echo esc_attr( 'New private key file for ' . $name ); ?>">
										<input type="text" name="client_id" class="regular-text dst-w-15" form="dst-rk-<?php echo $n; ?>" autocomplete="off" spellcheck="false" placeholder="Client ID (blank = from file name)" aria-label="Client ID">
										<button type="submit" class="button" form="dst-rk-<?php echo $n; ?>">Replace key</button>
									</div>
								</details>
								<?php endif; ?>
								<?php $ds_audit = $key[2] ? array_slice( DS_Roster_Data::audit_log( $site['site_id'] ), 0, 5 ) : array(); if ( $ds_audit ) : ?>
								<details class="dst-history"><summary>Key history</summary>
									<ul class="dst-history-list">
									<?php foreach ( $ds_audit as $ds_e ) : ?>
										<li><?php echo esc_html( sprintf( '%s: %s by %s (fingerprint %s)', wp_date( 'Y-m-d H:i', (int) $ds_e['t'] ), $ds_e['event'], $ds_e['user'], '' !== $ds_e['print'] ? $ds_e['print'] : '-' ) ); ?></li>
									<?php endforeach; ?>
									</ul>
								</details>
								<?php endif; ?>
							<?php endif; ?>
						</div>
					</div>
				</article>
				<?php endforeach; ?>
			</div>

			<template id="dst-la-site-template">
				<article class="dst-site dst-site--new">
					<div class="dst-site-head">
						<span class="dst-site-dot" aria-hidden="true"></span>
						<h3>New site</h3>
						<button type="button" class="button dst-site-remove" aria-label="Remove this new site">Remove</button>
					</div>
					<div class="dst-site-fields">
						<label class="dst-site-field">Label
							<input type="text" data-name="label" placeholder="Main site">
						</label>
						<label class="dst-site-field">Site ID
							<input type="text" inputmode="numeric" data-name="site_id" placeholder="46287">
						</label>
						<label class="dst-site-field">Public API key
							<input type="text" data-name="api_key" autocomplete="off" spellcheck="false" placeholder="public widget key">
						</label>
					</div>
					<p class="dst-panel-note">Save Changes, and its feed is checked on the next page view.</p>
				</article>
			</template>

			<?php if ( $leagueapps_sites ) : ?>
			<section class="dst-panel dst-activity dst-mt-20" aria-labelledby="dst-activity-h">
				<div class="dst-activity-head">
					<h2 id="dst-activity-h">Feed activity, last 24 hours</h2>
					<?php if ( ! empty( $la_activity['last'] ) ) : ?>
					<span><?php echo esc_html( sprintf( '%d fetches, %d failed, %d requests with retries. Daily ceiling %d.', (int) $la_activity['fetches'], (int) $la_activity['failures'], (int) $la_activity['requests'], (int) $la_activity['budget'] ) ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( empty( $la_activity['last'] ) ) : ?>
					<p class="dst-panel-note">No fetch recorded yet. The first page view of a Programs block makes one.</p>
				<?php else : ?>
					<?php
					$la_max = max( 1, max( array_column( $la_hours, 'fetches' ) ) );
					$la_fail_hours = count( array_filter( array_column( $la_hours, 'failures' ) ) );
					?>
					<div class="dst-bars" role="img" aria-label="<?php echo esc_attr( sprintf( 'Fetches per hour over the last 24 hours, at most %d in one hour; %d %s with a failure.', $la_max, $la_fail_hours, 1 === $la_fail_hours ? 'hour' : 'hours' ) ); ?>">
						<?php foreach ( $la_hours as $la_h ) : $la_lvl = $la_h['fetches'] ? max( 1, (int) ceil( $la_h['fetches'] / $la_max * 8 ) ) : 0; ?>
						<span class="dst-bar dst-bar--h<?php echo (int) $la_lvl; ?><?php echo $la_h['failures'] ? ' dst-bar--fail' : ''; ?>"></span>
						<?php endforeach; ?>
					</div>
					<div class="dst-bars-axis"><span>24 hours ago</span><span>12 hours ago</span><span>now</span></div>
					<?php if ( ! empty( $la_activity['over'] ) ) : ?>
						<p class="dst-line dst-line--strong dst-bad">That is more than the cache should allow: the host's object cache is dropping the feed between visits, so it is being refetched on demand. Listings still render, and each burst is still limited to one fetch, but tell Alipio.</p>
					<?php endif; ?>
					<?php if ( $la_ledger ) : ?>
					<details>
						<summary>Show the fetch log (<?php echo count( $la_ledger ); ?>)</summary>
						<div class="dst-mapping-container">
							<table class="dst-mapping-table dst-la-ledger">
								<thead>
									<tr>
										<th scope="col">When</th>
										<th scope="col">Site</th>
										<th scope="col">Result</th>
										<th scope="col">Requests</th>
										<th scope="col">Took</th>
										<th scope="col">Programs</th>
										<th scope="col">Reason</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $la_ledger as $e ) : $e = (array) $e; ?>
									<tr>
										<td title="<?php echo esc_attr( date_i18n( 'Y-m-d H:i:s', (int) $e['t'] + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) ); ?>"><?php echo esc_html( human_time_diff( (int) $e['t'], time() ) ); ?> ago</td>
										<td><?php echo esc_html( $e['site'] ?? '' ); ?></td>
										<td><?php if ( ! empty( $e['ok'] ) ) : ?><span class="dst-ok">OK</span><?php else : ?><span class="dst-bad" title="<?php echo esc_attr( $e['err'] ?? '' ); ?>">Failed<?php echo ! empty( $e['code'] ) ? ' (HTTP ' . (int) $e['code'] . ')' : ''; ?></span><?php endif; ?></td>
										<td><?php echo (int) ( $e['tries'] ?? 1 ); ?></td>
										<td><?php echo (int) ( $e['ms'] ?? 0 ); ?> ms</td>
										<td><?php echo ! empty( $e['ok'] ) ? (int) ( $e['rows'] ?? 0 ) : '&ndash;'; ?></td>
										<td><?php echo esc_html( $la_why[ $e['why'] ?? '' ] ?? ( $e['why'] ?? '' ) ); ?></td>
									</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</details>
					<?php endif; ?>
				<?php endif; ?>
			</section>
			<?php endif; ?>

			<div class="dst-footer">
				<?php submit_button( 'Save Changes', 'primary', 'submit', false ); ?>
				<span class="dst-footer-meta">
					Saving this tab clears the feed cache &nbsp;&middot;&nbsp;
					<a href="<?php echo esc_url( DS_Env::REPO_URL ); ?>" target="_blank" rel="noopener">GitHub</a>
				</span>
			</div>
		</form>

		<?php
		// The roster key forms, one set per site: the buttons and fields in each card point here with form="...".
		if ( $la_roster ) :
			foreach ( $leagueapps_sites as $i => $site ) :
				$n = (int) $i;
				foreach ( array( 'rk' => 'ds_la_roster_key', 'rs' => 'ds_la_roster_sync', 'rd' => 'ds_la_roster_key_delete' ) as $la_f => $la_action ) :
					?>
		<form id="dst-<?php echo esc_attr( $la_f . '-' . $n ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"<?php echo 'rk' === $la_f ? ' enctype="multipart/form-data"' : ''; ?><?php echo 'rd' === $la_f ? ' class="dst-confirm" data-confirm="Remove the private key and the stored rosters for this site?"' : ''; ?> hidden>
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( $la_action ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $la_action ); ?>">
			<input type="hidden" name="site_id" value="<?php echo esc_attr( $site['site_id'] ); ?>">
		</form>
					<?php
				endforeach;
			endforeach;
		endif;
		?>
	</div>

	<aside class="dst-rail" aria-label="How the LeagueApps connection works">
		<section class="dst-panel" aria-labelledby="dst-la-cache-h">
			<div class="dst-panel-head"><h2 id="dst-la-cache-h">Feed cache</h2></div>
			<p>Listings refresh at most every 10 minutes in the background. Visitors always get the last good copy at once, and it is kept 7 days if LeagueApps cannot be reached. Editors also get a "Refresh LeagueApps listings" link in the admin bar.</p>
			<?php if ( $la_sports ) : ?>
			<p>Sports in the last feed: <?php echo esc_html( implode( ', ', $la_sports ) ); ?>.</p>
			<?php endif; ?>
			<a class="button" href="<?php echo esc_url( $la_flush_url ); ?>">Refresh all sites now</a>
		</section>
		<section class="dst-panel" aria-labelledby="dst-la-keys-h">
			<div class="dst-panel-head"><h2 id="dst-la-keys-h">Where the keys come from</h2></div>
			<ol>
				<li>Public key: LeagueApps, Connect, API Settings. The same key the hosted listing widget uses; it is read on the server only and never reaches a visitor's browser.</li>
				<li>Private key (rosters only): Connect, API Settings, Generate Private API Key. It downloads a .p12 file named after the client ID.</li>
			</ol>
		</section>
		<?php if ( $la_roster ) : $ds_kek = DS_Roster_Data::kek_in_use(); ?>
		<details class="dst-panel">
			<summary>How private keys are stored</summary>
			<p>The key can read every family's details, so it is stored encrypted and bound to this site and this install: a copied database cannot use it. It is never shown again, is only sent to the LeagueApps login over HTTPS, and only an administrator can add or remove it. Only names, positions, graduation years and heights of rostered players and coaches are kept. Rosters refresh every hour in the background.</p>
			<p>Encryption secret: <?php echo 'salts' === $ds_kek ? 'this site\'s WordPress salts. Stronger: add <code>define( \'DS_LA_KEY_SECRET\', \'&lt;64 random characters&gt;\' );</code> to wp-config.php; it keeps the secret out of reach of a salt rotation by a security plugin. Saved keys switch over by themselves.' : ( 'constant' === $ds_kek ? '<code>DS_LA_KEY_SECRET</code> in wp-config.php.' : '<code>DS_LA_KEY_SECRET</code> environment variable.' ); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed strings ?></p>
		</details>
		<?php endif; ?>
	</aside>
</div>
<script>
( function () {
	var list = document.getElementById( 'dst-la-sites' );
	var add  = document.getElementById( 'dst-la-add-site' );
	var tpl  = document.getElementById( 'dst-la-site-template' );
	if ( ! list ) { return; }
	// Show / hide a public key.
	list.addEventListener( 'click', function ( e ) {
		var show = e.target.closest( '.dst-key-show' );
		if ( show ) {
			var field = document.getElementById( show.getAttribute( 'aria-controls' ) );
			var hide  = 'text' === field.type;
			field.type = hide ? 'password' : 'text';
			show.textContent = hide ? 'Show' : 'Hide';
			show.setAttribute( 'aria-pressed', hide ? 'false' : 'true' );
			return;
		}
		var remove = e.target.closest( '.dst-site-remove' );
		if ( remove ) {
			var card = remove.closest( '.dst-site' );
			card.parentNode.removeChild( card ); // gone from the list when the tab is saved
			if ( add ) { add.focus(); }
		}
	} );
	// Add a site: a blank card from the template, its fields named with the next free row number.
	if ( add && tpl ) {
		add.addEventListener( 'click', function () {
			var i    = parseInt( add.getAttribute( 'data-count' ), 10 ) || 0;
			var card = tpl.content.firstElementChild.cloneNode( true );
			[].slice.call( card.querySelectorAll( '[data-name]' ) ).forEach( function ( f ) {
				f.name = 'ds_toolkit_settings[leagueapps_sites][' + i + '][' + f.getAttribute( 'data-name' ) + ']';
			} );
			list.appendChild( card );
			add.setAttribute( 'data-count', String( i + 1 ) );
			card.querySelector( 'input' ).focus();
		} );
	}
	// Removing a roster key asks first.
	[].slice.call( document.querySelectorAll( 'form.dst-confirm' ) ).forEach( function ( f ) {
		f.addEventListener( 'submit', function ( e ) {
			if ( ! window.confirm( f.getAttribute( 'data-confirm' ) ) ) { e.preventDefault(); }
		} );
	} );
} )();
</script>
