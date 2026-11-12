<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Where this plugin lives and what kind of site it is running on: one place for the names and rules that
 * were typed again in each feature.
 *
 * Before the 2026-11 audit there were three lists of "development" hostnames that did not agree (Tripwire
 * knew two platform temp domains, the Home Templates picker four, the updater none), the Design Shop HQ
 * host was typed in two files, the GitHub repository in two, and one feature compared the staff address
 * against its own copy instead of the DS_TOOLKIT_ADMIN_DOMAIN constant a site can override.
 *
 * Three different questions, kept apart on purpose:
 *   is_local()        a developer's own machine (Local, a .test site).
 *   is_dev_host()     not the partner's own domain yet: a local machine OR the host's temporary address.
 *                     Launching a site IS moving home_url() to the partner's domain, so this is the
 *                     pre-launch signal Tripwire and the Home Templates picker both use.
 *   is_beta_channel() may be offered a release candidate. Never true on a platform temp address: a site
 *                     being built for a partner runs stable releases only.
 *
 * No hooks, no options, no network: safe to load first and to load in a stub test.
 */
if ( ! class_exists( 'DS_Env' ) ) :
class DS_Env {

	/** Design Shop HQ: release roll-out, Tripwire check-ins, the school emblem library, the team's guides. */
	const HQ_HOST = 'designshophq.wpenginepowered.com';
	const HQ_URL  = 'https://' . self::HQ_HOST . '/';

	/** The public repository releases are published from. Load-bearing: every site's updater reads it. */
	const REPO     = 'Design-Shop-LeagueApps-Department/ds-toolkit';
	const REPO_URL = 'https://github.com/' . self::REPO;

	/** The shared Design Shop inbox: where a Tripwire alert goes when a site names nobody. */
	const ALERT_EMAIL = 'design@leagueapps.com';

	/** Addresses the hosts hand out before a site has its own domain (leading dot = any subdomain). */
	const TEMP_HOSTS = array( '.wpenginepowered.com', '.wpengine.com', '.flywheelsites.com', '.flywheelstaging.com' );

	/** Names that only resolve on a developer's machine. Not ".dev": that is a public domain ending now. */
	const LOCAL_HOSTS = array( 'localhost', '.local', '.test' );

	/** The email ending that marks Design Shop staff, with its leading "@". A site may override the constant. */
	public static function staff_domain() {
		return defined( 'DS_TOOLKIT_ADMIN_DOMAIN' ) && '' !== (string) DS_TOOLKIT_ADMIN_DOMAIN ? (string) DS_TOOLKIT_ADMIN_DOMAIN : '@leagueapps.com';
	}

	/** Does this address end in the staff domain? The address alone: callers add their own role check. */
	public static function is_staff_email( $email ) {
		$email = (string) $email;
		return '' !== $email && (bool) preg_match( '/' . preg_quote( self::staff_domain(), '/' ) . '$/i', $email );
	}

	/** A URL on Design Shop HQ. */
	public static function hq_url( $path = '' ) {
		return self::HQ_URL . ltrim( (string) $path, '/' );
	}

	/** This site's public hostname, lower case, or '' when it cannot be read. */
	public static function host() {
		return strtolower( (string) wp_parse_url( (string) home_url(), PHP_URL_HOST ) );
	}

	private static function matches( $host, array $names ) {
		$host = strtolower( (string) $host );
		if ( '' === $host ) {
			return false;
		}
		foreach ( $names as $n ) {
			if ( '.' === $n[0] ? substr( $host, -strlen( $n ) ) === $n : $host === $n ) {
				return true;
			}
		}
		return false;
	}

	/** A developer's own machine: a local name, a loopback or a home-network address. */
	public static function is_local( $host = null ) {
		$host = strtolower( (string) ( null === $host ? self::host() : $host ) );
		return self::matches( $host, self::LOCAL_HOSTS ) || (bool) preg_match( '/^(127\.|192\.168\.)/', $host );
	}

	/** Still on a host's temporary address (not local, not the partner's domain). */
	public static function is_temp_host( $host = null ) {
		return self::matches( null === $host ? self::host() : $host, self::TEMP_HOSTS );
	}

	/**
	 * Not on the partner's own domain yet. An unreadable hostname answers false: when we cannot tell, the
	 * site is treated as live, so a security alert is still mailed.
	 */
	public static function is_dev_host( $host = null ) {
		$host = null === $host ? self::host() : $host;
		return self::is_local( $host ) || self::is_temp_host( $host );
	}

	/** Serving on the partner's own domain. */
	public static function is_launched( $host = null ) {
		return ! self::is_dev_host( $host );
	}

	/**
	 * May this site be offered a release candidate? WP_ENVIRONMENT_TYPE decides when the site sets it
	 * (anything but production); otherwise only a developer's machine. A host's temporary address alone
	 * is never enough: that is a site being built for a partner, and it stays on stable releases.
	 */
	public static function is_beta_channel() {
		if ( defined( 'WP_ENVIRONMENT_TYPE' ) ) {
			return 'production' !== WP_ENVIRONMENT_TYPE;
		}
		return self::is_local( strtolower( (string) wp_parse_url( (string) get_site_url(), PHP_URL_HOST ) ) );
	}
}
endif;
