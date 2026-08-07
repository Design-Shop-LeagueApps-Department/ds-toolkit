<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Cloudflare Turnstile protection (fleet-wide — any blueprint generation).
 *
 * One place to hold the Cloudflare keys, applied to both surfaces that actually
 * get attacked on these sites:
 *
 *  1. **wp-login.php** — login, registration and lost-password. Implemented here,
 *     because nothing else on the stack protects them.
 *  2. **Forminator forms** — Forminator has NATIVE Turnstile support, so this
 *     feature does not re-implement it. It pushes the keys into Forminator's own
 *     options (`forminator_turnstile_key` / `_secret`) and can flip existing
 *     captcha fields to the turnstile provider. Reusing Forminator's rendering
 *     and validation is far more reliable than injecting a second widget into
 *     someone else's form markup.
 *
 * WHY THIS DEFAULTS OFF
 * ---------------------
 * A captcha on the login form is a lockout risk: wrong keys and nobody gets in,
 * on every site it reaches. So the feature ships off, and even when enabled it
 * refuses to enforce unless BOTH keys are present. Layered escape hatches:
 *
 *  - `DS_TURNSTILE_OFF` in wp-config.php disables enforcement outright.
 *  - Monitor mode logs what WOULD have been blocked and lets everyone through.
 *  - A transport failure reaching Cloudflare FAILS OPEN on login (an outage at
 *    Cloudflare must not lock admins out of their own site). A genuine
 *    verification failure fails closed.
 *  - WP-CLI, cron and REST/XML-RPC authentication are never gated.
 *
 * Note this is Turnstile only — it does not put Cloudflare's proxy/WAF in front
 * of the site. That is a DNS/hosting change, not a plugin setting.
 */
class DS_Cloudflare {

	const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
	const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
	const FIELD      = 'cf-turnstile-response';

	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = is_array( $settings ) ? $settings : array();
	}

	public function init() {
		// Keep Forminator's own Turnstile keys in step with ours, so editors set
		// the API keys in ONE place. Cheap: only writes when they differ.
		add_action( 'init', array( $this, 'sync_forminator_keys' ), 20 );

		if ( ! $this->keys_ready() ) {
			return; // never render a widget we cannot verify
		}

		if ( $this->protect( 'login' ) ) {
			add_action( 'login_form', array( $this, 'render_widget' ) );
			add_filter( 'authenticate', array( $this, 'verify_login' ), 25, 1 );
		}
		if ( $this->protect( 'register' ) ) {
			add_action( 'register_form', array( $this, 'render_widget' ) );
			add_filter( 'registration_errors', array( $this, 'verify_register' ), 10, 1 );
		}
		if ( $this->protect( 'lostpassword' ) ) {
			add_action( 'lostpassword_form', array( $this, 'render_widget' ) );
			add_action( 'lostpassword_post', array( $this, 'verify_lostpassword' ), 10, 1 );
		}
		if ( $this->any_login_surface() ) {
			add_action( 'login_enqueue_scripts', array( $this, 'enqueue' ) );
			add_action( 'login_head', array( $this, 'widget_css' ) );
		}
	}

	/* ------------------------------------------------------------------ state */

	private function opt( $key, $default = '' ) {
		return isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $default;
	}

	public function site_key() {
		return trim( (string) $this->opt( 'cf_turnstile_site_key' ) );
	}

	private function secret_key() {
		return trim( (string) $this->opt( 'cf_turnstile_secret_key' ) );
	}

	/** Both keys present — the precondition for doing anything at all. */
	public function keys_ready() {
		return '' !== $this->site_key() && '' !== $this->secret_key();
	}

	/** Log instead of block, so a rollout can be watched before it bites. */
	private function monitor_mode() {
		return ! empty( $this->settings['cf_turnstile_monitor'] );
	}

	private function protect( $surface ) {
		return ! empty( $this->settings[ 'cf_turnstile_protect_' . $surface ] );
	}

	private function any_login_surface() {
		return $this->protect( 'login' ) || $this->protect( 'register' ) || $this->protect( 'lostpassword' );
	}

	/**
	 * Hard off-switch. Anything that is not a human posting the login form is
	 * exempt, so automation and app passwords keep working.
	 */
	private function bypassed() {
		if ( defined( 'DS_TURNSTILE_OFF' ) && DS_TURNSTILE_OFF ) {
			return true;
		}
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) {
			return true;
		}
		if ( defined( 'XMLRPC_REQUEST' ) || defined( 'REST_REQUEST' ) ) {
			return true;
		}
		return false;
	}

	/** True only for a real POST of the wp-login.php form. */
	private function is_login_post() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return false;
		}
		return isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'];
	}

	/* ----------------------------------------------------------------- render */

	public function enqueue() {
		wp_enqueue_script( 'ds-turnstile', self::SCRIPT_URL, array(), null, true );
	}

	/**
	 * Keep the widget inside the wp-login card.
	 *
	 * Turnstile renders a fixed-width iframe (~300px min) which overflows the
	 * login box on narrow phones, so below that width it is scaled down from the
	 * left edge. The wrapper height is reduced by the same factor, otherwise the
	 * scale transform leaves a gap under the widget.
	 */
	public function widget_css() {
		echo '<style id="ds-turnstile-css">'
			. '.ds-turnstile{margin:0 0 16px;max-width:100%;}'
			. '.ds-turnstile iframe{max-width:100%;}'
			. '@media screen and (max-width:352px){'
			. '.ds-turnstile{transform:scale(.82);transform-origin:left top;height:56px;}'
			. '}'
			. '</style>' . "\n";
	}

	public function render_widget() {
		printf(
			'<div class="ds-turnstile cf-turnstile" data-sitekey="%s" data-theme="%s" data-size="flexible"></div>',
			esc_attr( $this->site_key() ),
			esc_attr( in_array( $this->opt( 'cf_turnstile_theme', 'auto' ), array( 'auto', 'light', 'dark' ), true ) ? $this->opt( 'cf_turnstile_theme', 'auto' ) : 'auto' )
		);
	}

	/* ----------------------------------------------------------------- verify */

	/**
	 * Ask Cloudflare about a token.
	 *
	 * @return array{ok:bool,transport_error:bool,codes:array}
	 */
	public function verify_token( $token ) {
		if ( '' === $token ) {
			return array( 'ok' => false, 'transport_error' => false, 'codes' => array( 'missing-input-response' ) );
		}
		$res = wp_remote_post(
			self::VERIFY_URL,
			array(
				'timeout' => 8,
				'body'    => array(
					'secret'   => $this->secret_key(),
					'response' => $token,
					'remoteip' => $this->client_ip(),
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'transport_error' => true, 'codes' => array( $res->get_error_code() ) );
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $body ) ) {
			return array( 'ok' => false, 'transport_error' => true, 'codes' => array( 'bad-response' ) );
		}
		return array(
			'ok'              => ! empty( $body['success'] ),
			'transport_error' => false,
			'codes'           => isset( $body['error-codes'] ) ? (array) $body['error-codes'] : array(),
		);
	}

	private function client_ip() {
		// Cloudflare's own header first, then the platform's, then REMOTE_ADDR.
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $k ) {
			if ( empty( $_SERVER[ $k ] ) ) {
				continue;
			}
			$ip = trim( explode( ',', wp_unslash( $_SERVER[ $k ] ) )[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
		return '';
	}

	/**
	 * Shared decision: should this request be blocked?
	 *
	 * @return string '' to allow, otherwise a human-readable reason.
	 */
	private function block_reason( $context ) {
		if ( $this->bypassed() ) {
			return '';
		}
		$token  = isset( $_POST[ self::FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::FIELD ] ) ) : '';
		$result = $this->verify_token( $token );

		if ( $result['ok'] ) {
			return '';
		}
		// A Cloudflare outage must never lock people out of their own site.
		if ( $result['transport_error'] ) {
			$this->log( $context, 'allowed (could not reach Cloudflare)', $result['codes'] );
			return '';
		}
		if ( $this->monitor_mode() ) {
			$this->log( $context, 'WOULD BLOCK (monitor mode)', $result['codes'] );
			return '';
		}
		$this->log( $context, 'blocked', $result['codes'] );
		return __( 'Human verification failed. Please try again.', 'ds-toolkit' );
	}

	private function log( $context, $outcome, $codes ) {
		if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) && ! $this->monitor_mode() ) {
			return;
		}
		error_log( sprintf(
			'[ds-toolkit turnstile] %s: %s%s',
			$context,
			$outcome,
			$codes ? ' — ' . implode( ',', array_map( 'sanitize_text_field', $codes ) ) : ''
		) );
	}

	/**
	 * Login. Runs on `authenticate` AFTER WordPress's own username/password
	 * checks, and only for a genuine wp-login.php POST — so REST, XML-RPC and
	 * application passwords are untouched.
	 *
	 * @param  WP_User|WP_Error|null $user
	 * @return WP_User|WP_Error|null
	 */
	public function verify_login( $user ) {
		if ( is_wp_error( $user ) || ! $this->is_login_post() ) {
			return $user;
		}
		$reason = $this->block_reason( 'login' );
		return '' === $reason ? $user : new WP_Error( 'ds_turnstile_failed', $reason );
	}

	/**
	 * Registration.
	 *
	 * @param  WP_Error $errors
	 * @return WP_Error
	 */
	public function verify_register( $errors ) {
		$reason = $this->block_reason( 'register' );
		if ( '' !== $reason && is_wp_error( $errors ) ) {
			$errors->add( 'ds_turnstile_failed', $reason );
		}
		return $errors;
	}

	/**
	 * Lost password.
	 *
	 * @param WP_Error $errors
	 */
	public function verify_lostpassword( $errors ) {
		$reason = $this->block_reason( 'lostpassword' );
		if ( '' !== $reason && is_wp_error( $errors ) ) {
			$errors->add( 'ds_turnstile_failed', $reason );
		}
	}

	/* -------------------------------------------------------------- forminator */

	/**
	 * Mirror our keys into Forminator's own Turnstile options so the API keys are
	 * managed in ONE place. Forminator then renders and validates the widget with
	 * its native captcha field — we deliberately do not inject a second widget.
	 *
	 * Only writes when a value actually differs, so this is safe on every load.
	 */
	public function sync_forminator_keys() {
		if ( empty( $this->settings['cf_turnstile_sync_forminator'] ) ) {
			return;
		}
		if ( ! defined( 'FORMINATOR_VERSION' ) && ! class_exists( 'Forminator' ) ) {
			return;
		}
		$map = array(
			'forminator_turnstile_key'    => $this->site_key(),
			'forminator_turnstile_secret' => $this->secret_key(),
		);
		foreach ( $map as $option => $value ) {
			if ( '' === $value ) {
				continue;
			}
			if ( (string) get_option( $option, '' ) !== $value ) {
				update_option( $option, $value );
			}
		}
	}

	/**
	 * Point every existing Forminator captcha FIELD at the turnstile provider.
	 *
	 * Not run automatically — it edits partner form definitions, so it is exposed
	 * as an explicit action from the settings screen. Returns a per-form report.
	 *
	 * @return array<string,string>
	 */
	public static function convert_forminator_fields_to_turnstile() {
		$out = array();
		$forms = get_posts( array(
			'post_type'      => 'forminator_forms',
			'post_status'    => 'any',
			'posts_per_page' => 200,
			'fields'         => 'ids',
		) );
		foreach ( $forms as $form_id ) {
			$meta = get_post_meta( $form_id, 'forminator_form_meta', true );
			if ( ! is_array( $meta ) || empty( $meta['fields'] ) || ! is_array( $meta['fields'] ) ) {
				continue;
			}
			$changed = 0;
			foreach ( $meta['fields'] as $i => $field ) {
				if ( ! is_array( $field ) || ( $field['type'] ?? '' ) !== 'captcha' ) {
					continue;
				}
				if ( ( $field['captcha_provider'] ?? '' ) === 'turnstile' ) {
					continue;
				}
				$meta['fields'][ $i ]['captcha_provider'] = 'turnstile';
				$changed++;
			}
			$title = get_the_title( $form_id );
			if ( $changed ) {
				update_post_meta( $form_id, 'forminator_form_meta', $meta );
				$out[ $title ] = sprintf( '%d captcha field(s) switched to Turnstile', $changed );
			} else {
				$has = false;
				foreach ( $meta['fields'] as $field ) {
					if ( is_array( $field ) && ( $field['type'] ?? '' ) === 'captcha' ) { $has = true; break; }
				}
				$out[ $title ] = $has ? 'already Turnstile' : 'NO captcha field — add one in Forminator to protect this form';
			}
		}
		return $out;
	}
}
