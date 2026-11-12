<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Bot Shield: telling a real search engine from one that only says so (reverse DNS check), and the blocked user agents.
 *
 * One part of DS_Bot_Shield, which was a single 1,200-line class until the 2026-11 audit. The methods were
 * moved here as they were: a trait, so they are still methods of the feature (same visibility, same $this).
 * The decision itself (who is let through, slowed or refused) stays in class-ds-bot-shield.php.
 */
trait DS_Bot_Shield_Crawlers {

    /**
     * Is this request a genuine search-engine crawler?
     *
     * Reverse-DNS then forward-confirm, the method Google and Bing document.
     * A spoofed user agent fails because the requesting IP's PTR record will
     * not resolve into the search engine's own domain. Results are cached per
     * IP for a day. The lookup runs for a request that claims to be a crawler
     * and is about to be refused, and for a few new crawler addresses per 10 s
     * on arrival (SE_VERIFY_BURST), so it is rare and cannot be driven by a flood.
     */
    private function is_verified_se( $ua, $ip ) {
        if ( null === $this->se_checked ) {
            $this->se_checked = $this->verify_crawler( $ua, $ip );
        }
        return $this->se_checked;
    }

    private function verify_crawler( $ua, $ip ) {
        if ( '' === $ua || '' === $ip ) {
            return false;
        }
        $hosts = $this->se_domains( $ua );
        if ( null === $hosts ) {
            return false; // does not even claim to be one
        }

        $key    = 'se_' . md5( $ip );
        $cached = $this->cache_get( $key );
        if ( false !== $cached ) {
            return (bool) $cached;
        }

        $ok   = false;
        $host = $this->dns( 'ptr', $ip );
        if ( $host && $host !== $ip ) {
            $host_l = strtolower( rtrim( $host, '.' ) );
            foreach ( $hosts as $suffix ) {
                if ( substr( $host_l, -strlen( $suffix ) ) === $suffix ) {
                    // Forward-confirm so a hostile PTR record cannot fake it.
                    $forward = $this->dns( 'a', $host_l );
                    if ( is_array( $forward ) && in_array( $ip, $forward, true ) ) {
                        $ok = true;
                    }
                    break;
                }
            }
        }
        $this->cache_set( $key, $ok ? 1 : 0, DAY_IN_SECONDS );
        return $ok;
    }

    /** The domains a crawler with this user agent must resolve into, or null when it does not claim to be one. */
    private function se_domains( $ua ) {
        $ua_l = strtolower( (string) $ua );
        if ( '' === $ua_l ) {
            return null;
        }
        foreach ( self::SE_VERIFY as $needle => $domains ) {
            if ( false !== strpos( $ua_l, $needle ) ) {
                return $domains;
            }
        }
        return null;
    }

    /** One DNS lookup ('ptr' = address to name, 'a' = name to addresses). The filter lets a test answer instead. */
    private function dns( $kind, $value ) {
        $pre = apply_filters( 'ds_bot_shield_dns', null, $kind, $value );
        if ( null !== $pre ) {
            return $pre;
        }
        return 'ptr' === $kind ? @gethostbyaddr( $value ) : @gethostbynamel( $value ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
    }

    // ------------------------------------------------------------------- ua

    private function ua_blocklist() {
        $raw = (string) $this->opt( 'bot_shield_ua_blocklist', '' );
        return preg_split( '/[\r\n,]+/', strtolower( $raw ), -1, PREG_SPLIT_NO_EMPTY );
    }

    private function ua_matches( $ua, $needles ) {
        $ua = strtolower( $ua );
        foreach ( (array) $needles as $needle ) {
            $needle = trim( (string) $needle );
            if ( '' !== $needle && false !== strpos( $ua, $needle ) ) {
                return true;
            }
        }
        return false;
    }
}
