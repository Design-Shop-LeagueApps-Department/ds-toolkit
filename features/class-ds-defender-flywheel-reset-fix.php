<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Repairs WordPress password-reset links on Flywheel sites that run Defender's
 * Mask Login Area.
 *
 * Flywheel strips the wp-resetpass cookie on the masked login path, so Defender
 * (on Flywheel only) replaces that cookie with a short-lived transient keyed off a
 * `wd-ml-token` parameter it appends to the emailed link. Defender 6.2.x appends the
 * token with a str_replace() that looks for the link in the form
 * `wp-login.php?action=rp&key=…&login=…`, but WordPress 7 builds the link as
 * `?login=…&key=…&action=rp`. The search never matches, the token is never added,
 * the flow falls back to the (stripped) cookie, and every reset link reads
 * "Your password reset link appears to be invalid." Seen fleet-wide after the
 * 2026-09-03 admin password reset (Lower Alabama Volleyball, Zendesk thread).
 *
 * Fix: after Defender has rewritten the message, append `wd-ml-token=<login>` to
 * any reset link that carries `action=rp` and `key=` but no token. Runs only when
 * Defender's Mask Login is enabled AND the host is Flywheel, so it is inert on
 * WP Engine and on sites without Defender. Safe to remove once Defender matches
 * the WordPress 7 link order.
 */
class DS_Defender_Flywheel_Reset_Fix {

    public function __construct( $settings = array() ) {}

    public function init() {
        // Defender hooks retrieve_password_message at 10; run after it.
        add_filter( 'retrieve_password_message', array( $this, 'append_token' ), 20, 4 );
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
