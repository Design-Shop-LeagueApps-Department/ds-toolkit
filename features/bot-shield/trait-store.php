<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Bot Shield: where counters live (the cache backend) and the per-address log.
 *
 * One part of DS_Bot_Shield, which was a single 1,200-line class until the 2026-11 audit. The methods were
 * moved here as they were: a trait, so they are still methods of the feature (same visibility, same $this).
 * The decision itself (who is let through, slowed or refused) stays in class-ds-bot-shield.php.
 */
trait DS_Bot_Shield_Store {

    // -------------------------------------------------------------- counters

    /**
     * Backend: persistent object cache (memcached on WPE) or APCu. Null when
     * neither exists; callers then skip counting entirely. Overridable in
     * tests via the ds_bot_shield_backend filter.
     */
    private function resolve_backend() {
        if ( null !== $this->backend ) {
            return $this->backend ?: null;
        }
        $backend = null;
        if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() ) {
            $backend = 'object-cache';
        } elseif ( function_exists( 'apcu_inc' ) && filter_var( ini_get( 'apc.enabled' ), FILTER_VALIDATE_BOOLEAN ) ) {
            $backend = 'apcu';
        }
        $backend       = apply_filters( 'ds_bot_shield_backend', $backend );
        $this->backend = $backend ?: false; // false caches the "none" answer
        return $backend;
    }

    /**
     * 'object-cache', 'apcu', or '' when the host has neither. Without one nothing is counted and no rate
     * limit can run, which the settings page must say instead of showing an empty day (DS_Status).
     */
    public function backend_name() {
        return (string) $this->resolve_backend();
    }

    private function cache_key( $key ) {
        return 'ds_bs_' . $key;
    }

    private function cache_incr( $key, $ttl ) {
        $backend = $this->resolve_backend();
        if ( 'object-cache' === $backend ) {
            wp_cache_add( $this->cache_key( $key ), 0, self::CACHE_GROUP, $ttl );
            $n = wp_cache_incr( $this->cache_key( $key ), 1, self::CACHE_GROUP );
            return false === $n ? 0 : (int) $n;
        }
        if ( 'apcu' === $backend ) {
            apcu_add( $this->cache_key( $key ), 0, $ttl );
            $n = apcu_inc( $this->cache_key( $key ) );
            return false === $n ? 0 : (int) $n;
        }
        if ( 'array' === $backend ) { // test harness backend
            $n = (int) apply_filters( 'ds_bot_shield_test_incr', 0, $this->cache_key( $key ), $ttl );
            return $n;
        }
        return 0;
    }

    private function cache_get( $key ) {
        $backend = $this->resolve_backend();
        if ( 'object-cache' === $backend ) {
            return wp_cache_get( $this->cache_key( $key ), self::CACHE_GROUP );
        }
        if ( 'apcu' === $backend ) {
            $ok = false;
            $v  = apcu_fetch( $this->cache_key( $key ), $ok );
            return $ok ? $v : false;
        }
        if ( 'array' === $backend ) {
            return apply_filters( 'ds_bot_shield_test_get', false, $this->cache_key( $key ) );
        }
        return false;
    }

    private function cache_set( $key, $value, $ttl ) {
        $backend = $this->resolve_backend();
        if ( 'object-cache' === $backend ) {
            wp_cache_set( $this->cache_key( $key ), $value, self::CACHE_GROUP, $ttl );
        } elseif ( 'apcu' === $backend ) {
            apcu_store( $this->cache_key( $key ), $value, $ttl );
        } elseif ( 'array' === $backend ) {
            apply_filters( 'ds_bot_shield_test_set', null, $this->cache_key( $key ), $value, $ttl );
        }
    }

    // ----------------------------------------------------------- ip logging

    /**
     * Per-IP offender log, flood-safe by construction: hit counts and one
     * sample (UA + path + verdict) per IP live in the object cache; an
     * aggregated top-200 table is persisted to the DB at most once every
     * 2 minutes and kept for today + yesterday. Memcached cannot enumerate
     * keys, so a capped per-day index array makes the entries findable.
     */
    private function log_ip( $verdict, $ip, $ua, $path ) {
        if ( '' === $ip || null === $this->resolve_backend() ) {
            return;
        }
        $day = gmdate( 'Ymd' );
        $k   = substr( md5( $ip ), 0, 12 );

        $this->cache_incr( 'ipn_' . $k . '_' . $day, 2 * DAY_IN_SECONDS );

        if ( false === $this->cache_get( 'ipi_' . $k . '_' . $day ) ) {
            $this->cache_set( 'ipi_' . $k . '_' . $day, array(
                'ip'      => $ip,
                'verdict' => $verdict,
                'ua'      => substr( (string) $ua, 0, 120 ),
                'path'    => substr( (string) $path, 0, 120 ),
            ), 2 * DAY_IN_SECONDS );

            $idx = $this->cache_get( 'ipidx_' . $day );
            $idx = is_array( $idx ) ? $idx : array();
            if ( count( $idx ) < 500 && ! in_array( $k, $idx, true ) ) {
                $idx[] = $k;
                $this->cache_set( 'ipidx_' . $day, $idx, 2 * DAY_IN_SECONDS );
            }
        }

        if ( ! $this->cache_get( 'ipflush' ) ) {
            $this->cache_set( 'ipflush', 1, 60 );
            $this->flush_ip_log( $day );
            $this->roll_up_stats( $day );
        }
    }

    /** Merge the cache aggregate into the persisted option. */
    public function flush_ip_log( $day = null ) {
        $day = $day ?: gmdate( 'Ymd' );
        $idx = $this->cache_get( 'ipidx_' . $day );
        if ( ! is_array( $idx ) || ! $idx ) {
            return;
        }
        $rows = array();
        foreach ( $idx as $k ) {
            $info = $this->cache_get( 'ipi_' . $k . '_' . $day );
            $n    = (int) $this->cache_get( 'ipn_' . $k . '_' . $day );
            if ( is_array( $info ) && $n > 0 ) {
                $rows[ $info['ip'] ] = array_merge( $info, array( 'n' => $n ) );
            }
        }
        if ( ! $rows ) {
            return;
        }
        uasort( $rows, function ( $a, $b ) { return $b['n'] - $a['n']; } );
        $rows = array_slice( $rows, 0, 200, true );

        $log = get_option( self::IPLOG_OPT, array() );
        $log = is_array( $log ) ? $log : array();

        // Migrate the older shape (day keys at the top level) into 'days'.
        if ( ! isset( $log['days'] ) ) {
            $days = array();
            foreach ( $log as $dk => $dv ) {
                if ( is_array( $dv ) ) {
                    $days[ (string) $dk ] = $dv;
                }
            }
            $all = array();
            foreach ( $days as $bucket ) {
                foreach ( (array) $bucket as $ip => $row ) {
                    if ( ! is_array( $row ) || ! isset( $row['n'] ) ) {
                        continue;
                    }
                    $cur = isset( $all[ $ip ]['n'] ) ? (int) $all[ $ip ]['n'] : 0;
                    $all[ $ip ] = array_merge( $row, array( 'n' => $cur + (int) $row['n'] ) );
                }
            }
            $log = array( 'days' => $days, 'all' => $all, 'seen' => array() );
        }
        foreach ( array( 'days', 'all', 'seen' ) as $bucket ) {
            if ( ! isset( $log[ $bucket ] ) || ! is_array( $log[ $bucket ] ) ) {
                $log[ $bucket ] = array();
            }
        }

        // Fold today's snapshot into a running per-IP total. The cache counter
        // is daily and can be wiped, so track what was last folded and add the
        // delta — same reset-aware rule the stat counters use. Without this the
        // crawler table would restart every midnight.
        if ( ! isset( $log['days'][ $day ] ) || ! is_array( $log['days'][ $day ] ) ) {
            $log['days'][ $day ] = array();
        }
        foreach ( $rows as $ip => $row ) {
            $n     = (int) $row['n'];
            $last  = isset( $log['seen'][ $day ][ $ip ] ) ? (int) $log['seen'][ $day ][ $ip ] : 0;
            $delta = ( $n >= $last ) ? ( $n - $last ) : $n;

            $day_cur = isset( $log['days'][ $day ][ $ip ]['n'] ) ? (int) $log['days'][ $day ][ $ip ]['n'] : 0;
            $all_cur = isset( $log['all'][ $ip ]['n'] )          ? (int) $log['all'][ $ip ]['n']          : 0;

            $base = array(
                'ip'      => $row['ip'],
                'verdict' => $row['verdict'],
                'ua'      => $row['ua'],
                'path'    => $row['path'],
            );
            // Accumulate — never overwrite. The cache counter is daily AND can
            // be wiped mid-day (a purge, an eviction, a deploy), so replacing
            // the stored row with the post-wipe snapshot silently discarded
            // everything counted before it.
            $log['days'][ $day ][ $ip ] = array_merge( $base, array( 'n' => $day_cur + $delta ) );
            $log['all'][ $ip ]          = array_merge( $base, array( 'n' => $all_cur + $delta ) );
            $log['seen'][ $day ][ $ip ] = $n;
        }

        // Daily snapshots and their fold-markers are kept for 30 days; the
        // running 'all' totals are never pruned by date, only capped by size.
        $cutoff = gmdate( 'Ymd', time() - ( 30 * DAY_IN_SECONDS ) );
        foreach ( array( 'days', 'seen' ) as $bucket ) {
            foreach ( array_keys( $log[ $bucket ] ) as $d ) {
                // PHP casts numeric-string keys to int — compare as strings.
                if ( (string) $d < (string) $cutoff ) {
                    unset( $log[ $bucket ][ $d ] );
                }
            }
        }
        if ( count( $log['all'] ) > 300 ) {
            uasort( $log['all'], function ( $a, $b ) { return $b['n'] - $a['n']; } );
            $log['all'] = array_slice( $log['all'], 0, 300, true );
        }

        update_option( self::IPLOG_OPT, $log, false );
    }

    /** Running per-IP offender totals since logging began. */
    public function ip_log_total() {
        $log = get_option( self::IPLOG_OPT, array() );
        if ( ! is_array( $log ) || ! isset( $log['all'] ) || ! is_array( $log['all'] ) ) {
            return array();
        }
        $all = $log['all'];
        uasort( $all, function ( $a, $b ) { return $b['n'] - $a['n']; } );
        return $all;
    }

    /** Persisted offender rows for a day, sorted by hit count. */
    public function ip_log( $day = null ) {
        $day = $day ?: gmdate( 'Ymd' );
        $log = get_option( self::IPLOG_OPT, array() );
        if ( ! is_array( $log ) ) {
            return array();
        }
        if ( isset( $log['days'] ) ) {
            return isset( $log['days'][ $day ] ) ? $log['days'][ $day ] : array();
        }
        return isset( $log[ $day ] ) ? $log[ $day ] : array(); // pre-migration
    }
}
