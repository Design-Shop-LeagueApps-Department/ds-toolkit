<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * DS Tripwire — daily indicator-of-compromise check + instant new-admin alert.
 *
 * Born from the Aug/Sep 2026 "WDG" fleet campaign (98 sites, both hosts).
 * Defender sat on nearly every compromised site and never alerted; the kit's
 * tells were nonetheless trivially checkable: rogue files in mu-plugins, fake
 * wdg-<hex>/starter-* plugin folders, and a self-replicating
 * WDG-CORE-START comment block appended to functions.php / index.php /
 * wp-config.php. On the campaign's patient zero the attacker's admin account
 * appeared TWO HOURS before the first file drop — an instant new-admin email
 * would have turned a 98-site campaign into a one-site incident.
 *
 * Design constraints:
 * - Cheap: no full-site scans. A daily cron reads one directory listing, a
 *   plugin-folder listing, and the tails of four files. Milliseconds.
 * - Zero frontend cost: scheduling and hooks are registered in admin/cron/CLI
 *   contexts; ordinary visits never pay for it.
 * - Clone-compatible / fail open: first run seeds baselines silently; a fresh
 *   clone of the blueprint never emails anyone. Hard IOC patterns (the wdg
 *   family) alert regardless of baseline.
 * - No dependencies: wp_mail only, one option row of state.
 */
class DS_Tripwire {

    const CRON_HOOK = 'ds_tripwire_daily';
    const STATE_OPT = 'ds_tripwire_state';

    /**
     * Hard blocklist: mu-plugins basenames that are malware camouflage in this
     * campaign. These alert even if a baseline somehow contains them.
     */
    const MU_BLOCKLIST = array(
        'sso.php',
        'wordfence-security.php',   // the real Wordfence never lives in mu-plugins
        'nox-google-bot-bypass.php',
        'zz-no-cache.php',
    );

    /** A legit mu-plugins/index.php is an empty guard file; loaders are 20KB+. */
    const MU_INDEX_MAX_BYTES = 4096;

    /** Self-heal markers; any hit in a scanned tail is a confirmed infection. */
    const MARKERS = array( 'WDG-CORE-START', '$wdg_k', '$coki' );

    private $settings;

    public function __construct( $settings = array() ) {
        $this->settings = is_array( $settings ) ? $settings : array();
    }

    public function init() {
        // The cron callback must be bound on every request type so a due event
        // can always fire; everything below it is admin/cron/CLI-only.
        add_action( self::CRON_HOOK, array( $this, 'run_checks' ) );

        // Instant alert when an administrator appears. Registration and role
        // grants only ever happen in admin/AJAX/CLI flows, so these hooks are
        // effectively free on the frontend too.
        add_action( 'user_register', array( $this, 'on_user_change' ), 10, 1 );
        add_action( 'set_user_role', array( $this, 'on_role_change' ), 10, 3 );
        add_action( 'add_user_role', array( $this, 'on_role_added' ), 10, 2 );

        if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
            if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
                // 03:10 site time: off-peak, and offset from the :00 herd.
                $first = strtotime( 'tomorrow 03:10', current_time( 'timestamp' ) );
                wp_schedule_event( $first ? $first : time() + DAY_IN_SECONDS, 'daily', self::CRON_HOOK );
            }
        }
    }

    /* ---------------------------------------------------------------- cron */

    public function run_checks() {
        $state    = get_option( self::STATE_OPT, array() );
        $seeded   = ! empty( $state['seeded'] );
        $findings = array();

        $findings = array_merge(
            $findings,
            $this->check_mu_plugins( $state, $seeded ),
            $this->check_plugin_dirs(),
            $this->check_marker_tails(),
            $this->check_webroot(),
            $this->check_admins( $state, $seeded )
        );

        $state['seeded']   = 1;
        $state['last_run'] = time();
        $state['last_findings'] = $findings;
        update_option( self::STATE_OPT, $state, false );

        if ( $findings && $seeded ) {
            $this->alert( "Daily tripwire found indicators of compromise", $findings );
        }
        return $findings;
    }

    /** New PHP files in mu-plugins vs baseline, plus hard IOC names. */
    private function check_mu_plugins( &$state, $seeded ) {
        $out = array();
        $dir = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
        if ( ! is_dir( $dir ) ) return $out;

        $current = array();
        foreach ( (array) glob( $dir . '/*.php' ) as $f ) {
            $base = basename( $f );
            $size = (int) @filesize( $f );
            $current[ $base ] = $size;

            if ( preg_match( '/^wdg-[0-9a-f]{8}\.php$/i', $base ) ) {
                $out[] = "mu-plugins: WDG loader present: {$base} ({$size} bytes)";
            } elseif ( in_array( $base, self::MU_BLOCKLIST, true ) ) {
                $out[] = "mu-plugins: known camouflage file: {$base} ({$size} bytes)";
            } elseif ( 'index.php' === $base && $size > self::MU_INDEX_MAX_BYTES ) {
                $out[] = "mu-plugins: index.php is {$size} bytes — legit guard files are ~0 bytes, campaign loaders are 20KB+";
            }
        }

        $baseline = isset( $state['mu_baseline'] ) ? (array) $state['mu_baseline'] : array();
        if ( $seeded ) {
            foreach ( array_diff_key( $current, $baseline ) as $base => $size ) {
                if ( 'ds-origin-guard.php' === $base ) continue; // toolkit-managed
                $out[] = "mu-plugins: NEW php file since last run: {$base} ({$size} bytes)";
            }
        }
        $state['mu_baseline'] = $current;
        return array_unique( $out );
    }

    /** Fake plugin folders used by the campaign. Pure directory listing. */
    private function check_plugin_dirs() {
        $out = array();
        foreach ( (array) glob( WP_CONTENT_DIR . '/plugins/*', GLOB_ONLYDIR ) as $d ) {
            $base = basename( $d );
            if ( preg_match( '/^(wdg-[0-9a-f]{8}|starter-[a-z]+-[a-z0-9]+)$/i', $base ) ) {
                $out[] = "plugins: campaign-pattern folder present: {$base}";
            }
        }
        return $out;
    }

    /** Marker scan of the four known self-heal injection targets (tails only). */
    private function check_marker_tails() {
        $out     = array();
        $targets = array(
            get_stylesheet_directory() . '/functions.php',
            get_template_directory() . '/functions.php',
            ABSPATH . 'index.php',
        );
        // wp-config can live in ABSPATH or one level up.
        foreach ( array( ABSPATH . 'wp-config.php', dirname( ABSPATH ) . '/wp-config.php' ) as $cfg ) {
            if ( file_exists( $cfg ) ) { $targets[] = $cfg; break; }
        }

        foreach ( array_unique( $targets ) as $file ) {
            if ( ! is_readable( $file ) ) continue;
            $size = (int) @filesize( $file );
            $fh   = @fopen( $file, 'rb' );
            if ( ! $fh ) continue;
            if ( $size > 98304 ) @fseek( $fh, -98304, SEEK_END );
            $tail = (string) stream_get_contents( $fh );
            fclose( $fh );
            foreach ( self::MARKERS as $marker ) {
                if ( false !== strpos( $tail, $marker ) ) {
                    $out[] = 'infected file (marker "' . $marker . '"): ' . $file;
                    break;
                }
            }
        }
        return $out;
    }

    /** Rogue error.php dropped in the web root (campaign IOC). */
    private function check_webroot() {
        $f = ABSPATH . 'error.php';
        if ( file_exists( $f ) ) {
            return array( 'web root: rogue error.php present (' . (int) @filesize( $f ) . ' bytes)' );
        }
        return array();
    }

    /** Administrator roster drift since the previous run. */
    private function check_admins( &$state, $seeded ) {
        $out    = array();
        $admins = array();
        foreach ( get_users( array( 'role' => 'administrator', 'fields' => array( 'ID', 'user_login', 'user_email' ) ) ) as $u ) {
            $admins[ (int) $u->ID ] = $u->user_login . ' <' . $u->user_email . '>';
        }
        $baseline = isset( $state['admin_baseline'] ) ? (array) $state['admin_baseline'] : array();
        if ( $seeded ) {
            foreach ( array_diff_key( $admins, $baseline ) as $id => $label ) {
                $out[] = "NEW administrator since last run: #{$id} {$label}";
            }
        }
        $state['admin_baseline'] = $admins;
        return $out;
    }

    /* ------------------------------------------------- instant admin alerts */

    public function on_user_change( $user_id ) {
        $user = get_userdata( $user_id );
        if ( $user && in_array( 'administrator', (array) $user->roles, true ) ) {
            $this->alert_new_admin( $user, 'registered with the administrator role' );
        }
    }

    public function on_role_change( $user_id, $role, $old_roles ) {
        if ( 'administrator' === $role && ! in_array( 'administrator', (array) $old_roles, true ) ) {
            $user = get_userdata( $user_id );
            if ( $user ) $this->alert_new_admin( $user, 'was promoted to administrator' );
        }
    }

    public function on_role_added( $user_id, $role ) {
        if ( 'administrator' === $role ) {
            $user = get_userdata( $user_id );
            if ( $user ) $this->alert_new_admin( $user, 'was granted the administrator role' );
        }
    }

    private function alert_new_admin( $user, $how ) {
        $actor = 'unknown actor';
        if ( function_exists( 'wp_get_current_user' ) ) {
            $current = wp_get_current_user();
            if ( $current && $current->exists() ) {
                $actor = $current->user_login;
            } elseif ( defined( 'WP_CLI' ) && WP_CLI ) {
                $actor = 'WP-CLI';
            }
        }
        $this->alert(
            'New administrator: ' . $user->user_login,
            array(
                "Account {$user->user_login} <{$user->user_email}> {$how}.",
                "Done by: {$actor}",
                'If nobody on the team did this, treat the site as compromised: rotate credentials and run the incident checklist.',
            )
        );
    }

    /* -------------------------------------------------------------- output */

    private function alert( $subject, array $lines ) {
        // Comma-separated list supported; invalid entries dropped, and the
        // shared Design Shop inbox is the fallback if nothing valid remains.
        $raw = (string) ( $this->settings['tripwire_alert_email'] ?? '' );
        $to  = array_filter( array_map( 'trim', explode( ',', $raw ) ), 'is_email' );
        if ( ! $to ) {
            $to = array( 'design@leagueapps.com' );
        }
        $to   = apply_filters( 'ds_tripwire_alert_email', $to );
        $site = home_url();
        $body = "DS Tripwire alert for {$site}\n"
              . 'Time: ' . gmdate( 'Y-m-d H:i:s' ) . " UTC\n\n"
              . "- " . implode( "\n- ", $lines ) . "\n\n"
              . "Playbook: fleet-audit/2026-09-02-malware-sweep/wpe-remediation/README.md (workspace)\n"
              . "Verify cleans with a full-docroot grep for WDG-CORE-START / wdg_k — never by backup date.\n";
        wp_mail( $to, '[DS Tripwire] ' . $subject . ' — ' . wp_parse_url( $site, PHP_URL_HOST ), $body );
    }
}
