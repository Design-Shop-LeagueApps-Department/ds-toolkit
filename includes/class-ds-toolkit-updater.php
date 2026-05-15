<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class DS_Toolkit_Updater {

    private $slug = 'ds-toolkit';
    private $repo = 'Design-Shop-LeagueApps-Department/ds-toolkit';

    /**
     * Returns true if the site is a local/development environment.
     * No wp-config.php constants needed — detection is fully automatic:
     *  - WP_ENVIRONMENT_TYPE = 'production'                        → stable
     *  - WP_ENVIRONMENT_TYPE = 'local' / 'development' / 'staging' → beta
     *  - WP_ENVIRONMENT_TYPE not set → falls back to site URL:
     *      *.local / *.test / *.dev / localhost / 127.x / 192.168.x → beta
     *      anything else (live domain)                               → stable
     */
    private function is_beta_channel() {
        if ( defined( 'WP_ENVIRONMENT_TYPE' ) ) {
            return WP_ENVIRONMENT_TYPE !== 'production';
        }
        $host = parse_url( get_site_url(), PHP_URL_HOST );
        return (bool) preg_match( '/\.(local|test|dev)$|^localhost$|^127\.|^192\.168\./', $host );
    }

    public function init() {
        // Fires when WP writes the update transient (after a WP-Cron/manual check).
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
        // Fires every time WP reads the update transient — powers the automatic badge/nag
        // on the Plugins page and Dashboard without needing WP-Cron to have run first.
        add_filter( 'site_transient_update_plugins', array( $this, 'inject_update_info' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
        add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );
        add_filter( 'plugin_action_links_' . $this->plugin_file(), array( $this, 'add_check_update_link' ) );
        add_action( 'admin_init', array( $this, 'handle_check_update' ) );
    }

    /**
     * Returns the plugin's actual file path relative to the plugins directory.
     * Works regardless of what the plugin folder is currently named.
     * e.g. "ds-toolkit-0.5.4/ds-toolkit.php" or "ds-toolkit/ds-toolkit.php"
     */
    private function plugin_file() {
        return plugin_basename( DS_TOOLKIT_PATH . $this->slug . '.php' );
    }

    /**
     * Hooked to pre_set_site_transient_update_plugins.
     * Runs when WP is about to persist the update transient (WP-Cron or manual check).
     * Requires $transient->checked to be populated — skips otherwise.
     */
    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }
        return $this->inject_update_info( $transient );
    }

    /**
     * Hooked to site_transient_update_plugins.
     * Fires on every admin page load that reads plugin update data — shows the update
     * badge/nag automatically without requiring WP-Cron or a manual check click.
     */
    public function inject_update_info( $transient ) {
        if ( ! is_object( $transient ) ) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if ( ! $release ) {
            return $transient;
        }

        $latest_version  = ltrim( $release['tag_name'], 'v' );
        $current_version = DS_TOOLKIT_VERSION;
        $plugin_file     = $this->plugin_file();

        if ( $this->is_newer_version( $latest_version, $current_version ) ) {
            $transient->response[ $plugin_file ] = (object) array(
                'slug'        => $this->slug,
                'plugin'      => $plugin_file,
                'new_version' => $latest_version,
                'url'         => 'https://github.com/' . $this->repo,
                'package'     => $this->get_download_url( $release ),
            );
        } else {
            // Plugin is up to date — clear any stale response entry and mark as checked.
            unset( $transient->response[ $plugin_file ] );
            if ( ! isset( $transient->no_update ) ) {
                $transient->no_update = array();
            }
            $transient->no_update[ $plugin_file ] = (object) array(
                'slug'        => $this->slug,
                'plugin'      => $plugin_file,
                'new_version' => $current_version,
                'url'         => 'https://github.com/' . $this->repo,
                'package'     => '',
            );
        }

        return $transient;
    }

    /**
     * Returns true if $latest should be offered as an update over $current.
     * Handles stable → stable, stable → beta (beta channel), and beta.N → beta.N+1.
     */
    private function is_newer_version( $latest, $current ) {
        $is_newer = version_compare( $latest, $current, '>' );

        if ( ! $is_newer && $this->is_beta_channel() && strpos( $latest, '-beta.' ) !== false ) {
            $latest_base  = preg_replace( '/-beta\.\d+$/', '', $latest );
            $current_base = preg_replace( '/-beta\.\d+$/', '', $current );

            if ( $latest_base === $current_base && strpos( $current, '-beta.' ) !== false ) {
                // Same base, both beta — compare suffix number: 0.9.9-beta.3 > 0.9.9-beta.2
                $latest_n  = (int) preg_replace( '/^.*-beta\./', '', $latest );
                $current_n = (int) preg_replace( '/^.*-beta\./', '', $current );
                $is_newer  = $latest_n > $current_n;
            } elseif ( version_compare( $latest_base, $current_base, '>' ) ) {
                // Beta of a strictly newer base over stable: 0.9.12-beta.1 > 0.9.11
                $is_newer = true;
            }
        }

        return $is_newer;
    }

    public function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' || $args->slug !== $this->slug ) {
            return $result;
        }

        $release = $this->get_latest_release();
        if ( ! $release ) {
            return $result;
        }

        $changelog_file = DS_TOOLKIT_PATH . 'CHANGELOG.md';
        $changelog_raw  = file_exists( $changelog_file ) ? file_get_contents( $changelog_file ) : '';

        // Convert Markdown headings/bullets to basic HTML for the WP plugin info popup
        $changelog_html = '';
        if ( $changelog_raw ) {
            $lines = explode( "\n", $changelog_raw );
            foreach ( $lines as $line ) {
                if ( preg_match( '/^## (.+)/', $line, $m ) ) {
                    $changelog_html .= '<h4>' . esc_html( $m[1] ) . '</h4>';
                } elseif ( preg_match( '/^### (.+)/', $line, $m ) ) {
                    $changelog_html .= '<strong>' . esc_html( $m[1] ) . '</strong><br>';
                } elseif ( preg_match( '/^[-*] (.+)/', $line, $m ) ) {
                    $changelog_html .= '&bull; ' . esc_html( $m[1] ) . '<br>';
                } elseif ( trim( $line ) === '---' || trim( $line ) === '' ) {
                    $changelog_html .= '<br>';
                }
            }
        }

        return (object) array(
            'name'          => 'DS Toolkit',
            'slug'          => $this->slug,
            'version'       => ltrim( $release['tag_name'], 'v' ),
            'author'        => 'Alipio Gabriel',
            'homepage'      => 'https://github.com/' . $this->repo,
            'sections'      => array(
                'description' => 'Design Shop custom features and build toolkit.',
                'changelog'   => $changelog_html,
            ),
            'download_link' => $this->get_download_url( $release ),
        );
    }

    /**
     * Rename the extracted zip folder to ds-toolkit/ regardless of what it was called.
     * Handles both fresh installs (Upload Plugin) and auto-updates.
     * Confirms it's our plugin by checking for ds-toolkit.php inside the extracted folder.
     */
    public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = array() ) {
        global $wp_filesystem;

        // Skip only when the upgrader explicitly says this is a theme/core
        // job. Some cron/CLI auto-update paths (incl. WP Engine Smart Plugin
        // Manager) omit hook_extra['type'] entirely — bailing on a missing
        // type would skip the rename in exactly the context this fixes. The
        // ds-toolkit.php existence check below is the real safety gate.
        if ( isset( $hook_extra['type'] ) && $hook_extra['type'] !== 'plugin' ) {
            return $source;
        }

        // Confirm the extracted folder actually contains our plugin file
        if ( ! $wp_filesystem->exists( trailingslashit( $source ) . $this->slug . '.php' ) ) {
            return $source;
        }

        $corrected = trailingslashit( $remote_source ) . $this->slug;

        if ( untrailingslashit( $source ) !== $corrected ) {
            $wp_filesystem->move( $source, $corrected );
        }

        return trailingslashit( $corrected );
    }

    public function add_check_update_link( $links ) {
        $url     = wp_nonce_url(
            add_query_arg( 'ds_toolkit_check_update', '1', admin_url( 'plugins.php' ) ),
            'ds_toolkit_check_update'
        );
        $channel = $this->is_beta_channel() ? ' (beta channel)' : '';
        $links[] = '<a href="' . esc_url( $url ) . '">Check for Updates' . esc_html( $channel ) . '</a>';
        return $links;
    }

    public function handle_check_update() {
        if ( ! isset( $_GET['ds_toolkit_check_update'] ) ) {
            return;
        }
        check_admin_referer( 'ds_toolkit_check_update' );
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        delete_transient( 'ds_toolkit_latest_release' );
        delete_transient( 'ds_toolkit_latest_release_beta' );
        delete_site_transient( 'update_plugins' );
        wp_update_plugins();
        wp_safe_redirect( admin_url( 'plugins.php' ) );
        exit;
    }

    /**
     * Prefer the attached release asset zip (correct folder name inside)
     * over GitHub's raw zipball (which uses owner-repo-hash as folder name).
     */
    private function get_download_url( $release ) {
        if ( ! empty( $release['assets'] ) ) {
            foreach ( $release['assets'] as $asset ) {
                if ( isset( $asset['name'] ) && $asset['name'] === $this->slug . '.zip' ) {
                    return $asset['browser_download_url'];
                }
            }
        }
        return $release['zipball_url'];
    }

    /**
     * Fetch the latest release from GitHub.
     *
     * Caches for 12 hours. Click "Check for Updates" to clear the cache and
     * fetch immediately — the button always shows the result right away.
     */
    private function get_latest_release() {
        $is_beta   = $this->is_beta_channel();
        $cache_key = $is_beta ? 'ds_toolkit_latest_release_beta' : 'ds_toolkit_latest_release';
        $cached    = get_transient( $cache_key );

        if ( $cached !== false && isset( $cached['tag_name'] ) ) {
            return $cached;
        }

        // Beta channel: fetch all releases (includes pre-releases), pick the most recent.
        // Stable channel: fetch /releases/latest which skips pre-releases automatically.
        $url = $is_beta
            ? 'https://api.github.com/repos/' . $this->repo . '/releases'
            : 'https://api.github.com/repos/' . $this->repo . '/releases/latest';

        $response = wp_remote_get( $url, array(
            'headers' => array( 'Accept' => 'application/vnd.github+json' ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $is_beta ) {
            if ( empty( $data ) || ! isset( $data[0]['tag_name'] ) ) {
                return false;
            }
            $data = $data[0];
        } else {
            if ( empty( $data['tag_name'] ) ) {
                return false;
            }
        }

        // Cache for 12 hours. "Check for Updates" clears this and fetches fresh immediately.
        set_transient( $cache_key, $data, 12 * HOUR_IN_SECONDS );

        return $data;
    }
}
