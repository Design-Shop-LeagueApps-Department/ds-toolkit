<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Repairs WordPress password-reset links on Flywheel sites that run Defender's
 * Mask Login Area.
 *
 * Two faults, both on Flywheel only:
 *
 * 1. The emailed link. Defender appends a `wd-ml-token` parameter with a
 *    str_replace() that looks for `wp-login.php?action=rp&key=…&login=…`, but
 *    WordPress 7 builds the link as `?login=…&key=…&action=rp`, so the token is
 *    never added. append_token() adds it. Seen fleet-wide after the 2026-09-03
 *    admin password reset (Lower Alabama Volleyball).
 *
 * 2. The cookie hop. Defender 6.3 validates the token, stores `login:key` in a
 *    transient, sets a `wd-rp-<COOKIEHASH>` cookie and redirects to strip the key
 *    from the URL. Flywheel drops every Set-Cookie on GET responses for the masked
 *    path (the login page does not even get wordpress_test_cookie), so the cookie
 *    never arrives and the next request reads "Your password reset link appears to
 *    be invalid". Seen on chicagomsa.org 2026-09-30 with fault 1 already fixed.
 *    handle_reset() runs before Defender (priority 5), does the same validation and
 *    transient, but carries the token in the URL (`ds-rp`) and in a hidden form
 *    field instead of a cookie, then hands it to Defender through $_COOKIE so
 *    Defender's own form and reset_password() do the rest.
 *
 * Inert unless Defender's Mask Login is enabled AND the host is Flywheel. Safe to
 * remove once Defender stops relying on a cookie there.
 */
class DS_Defender_Flywheel_Reset_Fix {

    const PARAM = 'ds-rp';
    const TTL   = 900; // 15 minutes to choose a password; Defender's 2 is tight.

    private $token = '';
    private $value = '';

    public function __construct( $settings = array() ) {}

    public function init() {
        // Defender hooks retrieve_password_message at 10; run after it.
        add_filter( 'retrieve_password_message', array( $this, 'append_token' ), 20, 4 );
        // Defender hooks these at 10; run first.
        add_action( 'login_form_rp', array( $this, 'handle_reset' ), 5 );
        add_action( 'login_form_resetpass', array( $this, 'handle_reset' ), 5 );
    }

    /**
     * @param string  $message    Email body.
     * @param string  $key        Reset key.
     * @param string  $user_login User login.
     * @param WP_User $user_data  User object.
     * @return string
     */
    public function append_token( $message, $key = '', $user_login = '', $user_data = null ) {
        if ( ! is_string( $message ) || '' === $message || '' === (string) $user_login ) {
            return $message;
        }
        if ( false !== strpos( $message, 'wd-ml-token=' ) ) {
            return $message; // Defender already did its job.
        }
        if ( ! self::mask_login_enabled() || ! self::is_flywheel() ) {
            return $message;
        }
        $token = '&wd-ml-token=' . rawurlencode( $user_login );
        return preg_replace_callback(
            '~https?://[^\s<>"\']+~i',
            function ( $m ) use ( $token ) {
                $url = $m[0];
                if ( false === stripos( $url, 'action=rp' ) || false === stripos( $url, 'key=' ) ) {
                    return $url;
                }
                return $url . $token;
            },
            $message
        );
    }

    /** Cookie-free version of Defender's handle_password_reset() hand-off. */
    public function handle_reset() {
        if ( is_user_logged_in() || ! self::mask_login_enabled() || ! self::is_flywheel() ) {
            return;
        }
        $prefix = 'wd-rp-' . COOKIEHASH;

        // Hop 1: the emailed link. Validate, stash, and redirect with our token.
        $action = isset( $_GET['action'] ) ? (string) $_GET['action'] : '';
        $key    = isset( $_GET['key'] ) && is_string( $_GET['key'] ) ? wp_unslash( $_GET['key'] ) : '';
        $login  = isset( $_GET['login'] ) && is_string( $_GET['login'] ) ? wp_unslash( $_GET['login'] ) : '';
        $ml     = isset( $_GET['wd-ml-token'] ) && is_string( $_GET['wd-ml-token'] ) ? wp_unslash( $_GET['wd-ml-token'] ) : '';
        if ( 'rp' === $action && '' !== $key && '' !== $login && $login === $ml ) {
            $user = check_password_reset_key( $key, $login );
            if ( is_wp_error( $user ) ) {
                return; // Defender and core show the invalid-link screen.
            }
            $token = wp_generate_password( 20, false );
            set_site_transient( $prefix . '_' . $token, sprintf( '%s:%s', $login, $key ), self::TTL );
            wp_safe_redirect( add_query_arg( self::PARAM, $token, remove_query_arg( array( 'key', 'login', 'wd-ml-token' ) ) ) );
            exit;
        }

        // Hop 2 (the form) and the form POST: give Defender the cookie it expects.
        $token = '';
        if ( isset( $_POST[ self::PARAM ] ) && is_string( $_POST[ self::PARAM ] ) ) {
            $token = $_POST[ self::PARAM ];
        } elseif ( isset( $_GET[ self::PARAM ] ) && is_string( $_GET[ self::PARAM ] ) ) {
            $token = $_GET[ self::PARAM ];
        }
        if ( ! preg_match( '/^[A-Za-z0-9]{20}$/', $token ) ) {
            return;
        }
        $value = get_site_transient( $prefix . '_' . $token );
        if ( ! is_string( $value ) || false === strpos( $value, ':' ) ) {
            return;
        }
        $this->token     = $token;
        $this->value     = $value;
        $_COOKIE[ $prefix ] = $token;
        add_action( 'resetpass_form', array( $this, 'token_field' ) );
        // Defender deletes the transient on every resetpass POST, even when the two
        // passwords do not match and it shows the form again. Put it back so the
        // second attempt works.
        add_action( 'validate_password_reset', array( $this, 'keep_token' ), 10, 2 );
    }

    public function token_field() {
        echo '<input type="hidden" name="' . esc_attr( self::PARAM ) . '" value="' . esc_attr( $this->token ) . '" />';
    }

    public function keep_token( $errors = null, $user = null ) {
        if ( '' !== $this->token && '' !== $this->value ) {
            set_site_transient( 'wd-rp-' . COOKIEHASH . '_' . $this->token, $this->value, self::TTL );
        }
    }

    /** Defender stores the mask settings as a JSON string (sometimes an array). */
    public static function mask_login_enabled() {
        if ( ! class_exists( 'WP_Defender\Controller\Mask_Login' ) ) {
            return false;
        }
        $raw = get_option( 'wd_masking_login_settings' );
        if ( is_string( $raw ) ) {
            $raw = json_decode( $raw, true );
        }
        return is_array( $raw ) && ! empty( $raw['enabled'] ) && ! empty( $raw['mask_url'] );
    }

    /** Mirror Defender's own host detection when available; fall back to the server header. */
    public static function is_flywheel() {
        if ( class_exists( 'WP_Defender\Component\Security_Tweaks\Servers\Server' ) ) {
            try {
                $server = \WP_Defender\Component\Security_Tweaks\Servers\Server::get_current_server();
                if ( is_string( $server ) && '' !== $server ) {
                    return 'flywheel' === strtolower( $server );
                }
            } catch ( \Throwable $e ) {
                // fall through to the header check
            }
        }
        $software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? (string) $_SERVER['SERVER_SOFTWARE'] : '';
        return false !== stripos( $software, 'flywheel' );
    }
}
