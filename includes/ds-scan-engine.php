<?php
/**
 * ds-scan-engine.php - content-based PHP malware scorer for the LeagueApps fleet.
 *
 * TWO WAYS TO RUN IT
 *   1. As a library, from WordPress (DS Tripwire) or any PHP caller:
 *        require_once __DIR__ . '/ds-scan-engine.php';       // defines dsscan_* only, no side effects
 *        $r = dsscan_scan_file($path, ['tokenize_cap' => 262144]);   // array|null, never echoes
 *        $stats = dsscan_scan_list($paths, ['deadline' => time() + 15], function ($finding) { ... });
 *      Every symbol is prefixed dsscan_ / DSSCAN_ and guarded, so including it twice, or next to a
 *      plugin that happens to define emit() or strip_quotes(), cannot fatal. Nothing at file scope
 *      touches ini settings, error reporting, stdin, stdout or shutdown handlers in library mode.
 *   2. As a CLI tool (what fw-check-site-v2.sh and wp-manifest-verify.sh ship to the container):
 *        find /www -type f | php ds-scan-engine.php --min=55 [--stats]
 *      One line per finding, streamed as found:  DSCAN|<tier>|<score>|<md5>|<path>|<reasons>
 *      The CLI block at the bottom runs ONLY when this file is the script PHP was started with.
 *
 * WHY THIS EXISTS (read fleet-audit/SCANNER-SPEC.md sections 1b and 4 first):
 *   The previous scanner decided IN ADVANCE what to look at - a static list of filenames
 *   (Nx###.php, kir.php, egl.php) and a handful of grep markers. dallaskicsfc.com proved the
 *   flaw: four shells were caught ONLY by the hardcoded "Nx" + 3-digit pattern, a fifth file
 *   (dsa.php, an eval(base64_decode(strrev(curl()))) loader) was caught by NO rule at all, and the
 *   fake plugin holding them carried a valid WordPress header so the "unregistered plugin" check
 *   waved it through. Rename the shells and the site reads CLEAN.
 *
 *   Grep itself is defeated on this fleet: w5x9k2m7q4.php calls @("e"."x"."e"."c")(...) and
 *   ("sy"."st"."em")(...), assembling every dangerous function name from string fragments SO THAT
 *   grep 'system(' finds nothing. So this engine does not grep source, it TOKENISES it.
 *
 * WHAT IT DOES: token_get_all() the file, then judge it by BEHAVIOUR, not name or location:
 *   - resolves call targets even when the name is built by "sy"."st"."em" concatenation, held in
 *     a variable ($f="sys"."tem"; $f($_POST)), or spelled from a range("~"," ") character table;
 *   - taint-lite: follows request input ($_GET/$_POST/.../php://input) through assignments and
 *     fires only when tainted data or a decoder actually reaches a dangerous sink - so "$_GET"
 *     alone is never a finding (the brsoccer.org lesson);
 *   - sees through comment splitting and whitespace, which the token stream discards.
 *
 * WHAT MAKES IT SAFE TO BE AGGRESSIVE: the CALLER suppresses findings whose md5 is known-good
 * (fleet manifest, wordpress.org checksums, the toolkit's own release hashes). The engine itself
 * exempts nothing by content, because anything content-based is spoofable by the attacker.
 *
 * It SCORES and REPORTS. It never edits, moves, or deletes. A human quarantines with evidence.
 * PHP 7.4+ (98 fleet sites still run 7.4). Core tokenizer only, no Composer, no extensions.
 */

if (!defined('DSSCAN_VERSION'))    define('DSSCAN_VERSION', '1.1.0');
if (!defined('DSSCAN_CRIT_SCORE')) define('DSSCAN_CRIT_SCORE', 100);   // any single CRIT rule adds >= 100
if (!defined('DSSCAN_HIGH_SCORE')) define('DSSCAN_HIGH_SCORE', 55);    // one strong or two moderate signals

/* One guard for the whole library: if any copy of the engine is already loaded (two toolkit copies,
   a plugin that bundled it), this include is a no-op instead of a "cannot redeclare" fatal. */
if (!function_exists('dsscan_scan_file')) {

/* ---- the sink vocabulary. Grouped by what a hit MEANS. ------------------------------------- */
function dsscan_vocab() {
    static $v = null;
    if ($v !== null) return $v;
    $exec = ['system','exec','shell_exec','passthru','proc_open','popen','pcntl_exec','proc_close','expect_popen'];
    $code = ['assert','create_function'];                 // eval + backticks handled specially
    $v = [
        'exec' => $exec,
        'code' => $code,
        'callback' => ['call_user_func','call_user_func_array','array_map','array_filter','array_walk',
                       'array_reduce','usort','uasort','uksort','register_shutdown_function','register_tick_function',
                       'ob_start','preg_replace_callback','forward_static_call','forward_static_call_array','iterator_apply'],
        // ONLY decoders used to hide CODE. pack/unpack/chr/bin2hex/urldecode are everyday helpers:
        // including them made PhpSpreadsheet's legitimate OLE writer score 1470 as a "dropper".
        'decoders' => ['base64_decode','gzinflate','gzuncompress','gzdecode','str_rot13','hex2bin',
                       'convert_uudecode','openssl_decrypt','mcrypt_decrypt','strrev'],
        'filewrite' => ['file_put_contents','fwrite','fputs','fopen','copy','rename','move_uploaded_file','symlink','link'],
        'req_super' => ['$_GET','$_POST','$_REQUEST','$_COOKIE','$_FILES','$_SERVER','$_ENV','$HTTP_RAW_POST_DATA'],
        // dangerous when the NAME is assembled at runtime: a legit developer never does this
        'danger_all' => array_merge($exec, $code, ['eval','assert','preg_replace','call_user_func','call_user_func_array','file_put_contents','fwrite','include','require']),
        /* campaign fingerprints: exact strings tied to THIS campaign. +70 each (strong HIGH, not CRIT
           alone): real malware pairs a marker with behaviour and lands well past CRIT; a lone marker
           (a detector list, a scan log) stays HIGH for the caller's hash gate to clear. */
        'fingerprints' => [
            'WDG-CORE' => 'WDG self-resurrecting loader block',
            'flf_pnpur_ybt' => 'BypassServ (str_rot13 of sys_cache_log)',
            'BypassServ' => 'BypassServ stealth shell',
            'WORKSPACE MANAGER' => 'BypassServ rendered title',
            'Byte_bunk' => 'Byte_bunk remote-eval loader (dsa.php family)',
            'datakeystone' => 'datakeystone AES C2 beacon',
            'amabstfr' => 'datakeystone C2 path',
            'ServiceC08B' => 'eysoccer class-wp-logo shell',
            'Nx-zD' => 'Nx### uploader/RCE banner',
            'S-RECOVERY: ACTIVE' => 'BypassServ recovery banner',
        ],
    ];
    return $v;
}

function dsscan_tier($score) {
    return $score >= DSSCAN_CRIT_SCORE ? 'CRIT' : ($score >= DSSCAN_HIGH_SCORE ? 'HIGH' : 'REVIEW');
}

/* memory_limit as bytes; -1 = unlimited; 0 = unknown */
function dsscan_mem_limit_bytes() {
    $s = trim((string) @ini_get('memory_limit'));
    if ($s === '' ) return 0;
    if ($s === '-1') return -1;
    $u = strtolower(substr($s, -1)); $n = (int) $s;
    if ($u === 'g') return $n * 1073741824;
    if ($u === 'm') return $n * 1048576;
    if ($u === 'k') return $n * 1024;
    return (int) $s;
}

/**
 * Scan one file. Returns null (nothing to report) or
 *   ['path','score','reasons'=>[],'md5','tier','tokenised'=>bool]
 * $opts:
 *   max_read      bytes read from the file (default 3 MB; a bigger file is read only to 1 MB)
 *   tokenize_cap  do not token_get_all() a file larger than this (default 1.5 MB on the CLI).
 *                 INSIDE WORDPRESS PASS 262144: MEASURED worst case is 346 bytes of memory per
 *                 source byte (a 1.17 MB token-dense one-liner peaked at 386 MB RSS), so 1.5 MB
 *                 would fatal a 256 MB request. Real shells are tiny (largest seen 27 KB); bigger
 *                 files get the raw regex fallback, which still catches eval-of-decoder and
 *                 input-to-exec. Under 256 MB + 256 KB cap the corpus still detects 63/63.
 *   mem_factor    bytes of headroom required per source byte before tokenising (default 400,
 *                 i.e. the measured 346 with margin); below headroom -> raw fallback, never a fatal
 *   self_paths    array of absolute paths that are the scanner's OWN files (Tripwire, this engine):
 *                 exempted by exact PATH, never by content. The caller must also hash-verify them.
 */
function dsscan_scan_file($path, $opts = []) {
    $V = dsscan_vocab();
    $EXEC_SINKS = $V['exec']; $CODE_SINKS = $V['code']; $CALLBACK_SINKS = $V['callback'];
    $DECODERS = $V['decoders']; $FILEWRITE = $V['filewrite']; $REQ_SUPER = $V['req_super'];
    $DANGER_ALL = $V['danger_all']; $FINGERPRINTS = $V['fingerprints'];
    $MAXSZ    = isset($opts['max_read'])     ? (int) $opts['max_read']     : 3000000;
    $TOKCAP   = isset($opts['tokenize_cap']) ? (int) $opts['tokenize_cap'] : 1500000;
    $MEMF     = isset($opts['mem_factor'])   ? (int) $opts['mem_factor']   : 400;
    $selfPaths = isset($opts['self_paths']) && is_array($opts['self_paths']) ? $opts['self_paths'] : [];

    if (!is_file($path)) return null;
    $sz = @filesize($path);
    if ($sz === false || $sz === 0) return null;
    /* Our own scanner files carry every marker and tell in their SOURCE by definition. Exempt them
       by exact path only (content-based self-recognition is trivially spoofable by an attacker);
       the caller is responsible for verifying these paths against the shipped release hashes. */
    if ($selfPaths) {
        $rp = @realpath($path);
        foreach ($selfPaths as $sp) { if ($sp !== '' && ($sp === $path || ($rp !== false && $sp === $rp))) return null; }
    }

    $score = 0; $reasons = []; $tokenised = false;
    /* Each RULE contributes at most once per file. A file containing one shell construct and a file
       containing the same construct twenty times are both "that construct": letting repetition
       accumulate turned TablePress's mb_stripos selection into 360 (CRIT) and PhpSpreadsheet's OLE
       writer into 1470. Different rules still stack - that is the combination evidence we want. */
    $fired = [];
    $add = function ($key, $pts, $why) use (&$score, &$reasons, &$fired) {
        if (isset($fired[$key])) return;
        $fired[$key] = true; $score += $pts; $reasons[] = $why;
    };
    /* Read strategy. A PHP-family or text file is read whole (bounded). A file that is BINARY in its
       first 8 KB is almost always an image/archive in uploads, and the only question we ask of it is
       "was PHP appended or embedded?" - so read the head (8 KB, catches EXIF/IDAT embedding) and the
       tail (64 KB, catches appended payloads) instead of the whole thing. On a site with 900 uploads
       that is ~65 MB of I/O per full pass instead of ~270 MB, which matters inside a cron budget. */
    $big  = $sz > $MAXSZ;
    $head = @file_get_contents($path, false, null, 0, 8192);
    if ($head === false || $head === '') return null;
    if (strpos($head, "\0") !== false && $sz > 8192) {
        $tail = '';
        if ($sz > 8192) {
            $fh = @fopen($path, 'rb');
            if ($fh) { @fseek($fh, $sz > 65536 + 8192 ? -65536 : 8192, $sz > 65536 + 8192 ? SEEK_END : SEEK_SET); $tail = (string) @stream_get_contents($fh); @fclose($fh); }
        }
        $src = $head . "\0" . $tail;   // the NUL keeps the binary branch below; head+tail is all we need
    } else {
        $src = $big ? @file_get_contents($path, false, null, 0, 1000000) : ($sz <= 8192 ? $head : @file_get_contents($path, false, null, 0, $sz));
        if ($src === false || $src === '') return null;
    }
    $done = function () use (&$score, &$reasons, $path, &$tokenised) {
        if ($score <= 0) return null;
        $md5 = @md5_file($path);
        return ['path' => $path, 'score' => $score, 'reasons' => $reasons, 'md5' => $md5 ? $md5 : '-',
                'tier' => dsscan_tier($score), 'tokenised' => $tokenised];
    };

    // Does the file verify a nonce / capability / credential anywhere? Computed up front (the check
    // may lexically FOLLOW the dispatch it guards). Used to stand down the "dynamic dispatch" signals,
    // which is how legit AJAX routers (Beaver Builder, ACF, Forminator) work.
    $fileVerifies = (bool) preg_match('/wp_verify_nonce|check_admin_referer|check_ajax_referer|current_user_can|wp_authenticate|wp_check_password|password_verify|->verify\b|is_user_logged_in/i', $src);

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $php_ext = in_array($ext, ['php','phtml','php3','php4','php5','php7','php8','phar','pht','inc','module','install'], true);

    /* --- binary? A NUL in the first 8 KB means it is not source. Never tokenise it, and do not run
       the text regexes over megabytes of pixels either.
       2026-09-17, found by running this on a live site: an earlier test accepted
       preg_match('/<\?[^x]/'), which RANDOM BINARY IMAGE BYTES satisfy. The engine then fed
       multi-megabyte JPEGs to token_get_all() and PHP died with a memory fatal (exit 255), so on
       every real site (uploads present) the whole content scan silently produced NOTHING while the
       scanner still printed a verdict. Never infer "is code" from a loose pattern. */
    $isBinary = (strpos(substr($src, 0, 8192), "\0") !== false);
    if ($isBinary) {
        /* The ONLY meaningful test on a binary is polyglot: real PHP appended to real image bytes
           (the akismet-husk .png dropper). Require the full "<?php" + whitespace. Testing for "<?="
           flagged three untouched JPEG/PNG uploads: a 3-byte sequence occurs in ~2% of 300 KB
           binaries BY CHANCE. A signal that short is noise, not evidence. */
        if (!$php_ext && preg_match('/<\?php(\s|$)/', $src)) {
            $score += 120;
            $reasons[] = "polyglot: real PHP open tag inside a .$ext binary (extension is camouflage; stageable via include())";
        } elseif ($php_ext) {
            $raw = dsscan_raw_fallback($src, $DECODERS);
            if ($raw['score'] > 0) { $score += $raw['score']; foreach ($raw['reasons'] as $x) $reasons[] = $x; }
        }
        return $done();
    }

    /* --- text from here on. Both open-tag forms are safe to trust in text. */
    $has_open = (strpos($src, '<?php') !== false) || (strpos($src, '<?=') !== false);

    /* --- polyglot for a TEXT file with a non-PHP extension. Position matters: a file that OPENS with
       <?php is a PHP file wearing a costume (the akismet-husk dropper was 61 KB of pure PHP named
       logo-*.png). A tag MID-file is usually a template leak: Beaver Builder / UABB compile
       "<?php echo $settings->enable_first; ?>" into cached *-layout.js (verified on dslaunchpad6).
       nginx never hands .js to PHP-FPM, so it cannot execute - report, do not cry wolf; the
       tokeniser below still judges the file on behaviour, so a .js that really holds a shell fires. */
    if (!$php_ext && $has_open) {
        $head = substr($src, 0, 64);
        if (strpos($head, '<?php') !== false || strpos($head, '<?=') !== false) {
            $score += 120;
            $reasons[] = "polyglot: this .$ext file IS PHP (opens with a PHP tag) - extension is camouflage, stageable via include()";
        } else {
            $score += 30;
            $reasons[] = "a PHP open tag appears inside a .$ext file (often a page-builder template leak into cached JS/CSS; confirm it is not a staged payload)";
        }
    }

    /* --- text but no PHP: pure-HTML doorway (gambling SEO, cloaking, GSC verification file). */
    if (!$has_open) {
        $d = dsscan_doorway_html_score($src, $ext, $path);
        if ($d['score'] > 0) { $score += $d['score']; foreach ($d['reasons'] as $x) $reasons[] = $x; }
        if (preg_match('/^\s*google-site-verification:\s*\S+\.html/i', $src)) {
            $score += 60; $reasons[] = "Search Console verification file body (attacker may be claiming this domain in Google)";
        }
        return $done();
    }

    /* --- too big, or not enough memory headroom, to tokenise safely? Raw fallback, never a fatal.
       token_get_all() + the normalised stream peak at ~350 bytes of memory per source byte. */
    $limit = dsscan_mem_limit_bytes();
    $need  = strlen($src) * $MEMF;
    if (strlen($src) > $TOKCAP || ($limit > 0 && (memory_get_usage() + $need) > $limit)) {
        $raw = dsscan_raw_fallback($src, $DECODERS);
        if ($raw['score'] > 0) { $score += $raw['score']; foreach ($raw['reasons'] as $x) $reasons[] = $x; }
        return $done();
    }

    /* --- tokenise. Lenient (no TOKEN_PARSE) so obfuscated-but-valid code still tokenises. ----- */
    $toks = @token_get_all($src);
    if (!is_array($toks) || count($toks) < 3) {
        unset($toks);
        $raw = dsscan_raw_fallback($src, $DECODERS);
        if ($raw['score'] > 0) { $score += $raw['score']; foreach ($raw['reasons'] as $x) $reasons[] = $x; }
        return $done();
    }
    $tokenised = true;

    /* fingerprints scan the raw text incl. comments (a marker can sit in "// Byte_bunk Signature") */
    foreach ($FINGERPRINTS as $needle => $desc) {
        if (stripos($src, $needle) !== false) {
            $add('fp:' . $needle, 70, "campaign fingerprint: $desc (\"$needle\")");
        }
    }

    /* Normalise to a compact stream: [ ['t'=>id|null,'s'=>text], ... ], dropping comments and
       whitespace. Then FREE the raw token array: keeping both alive doubled peak memory. */
    $stream = [];
    foreach ($toks as $t) {
        if (is_array($t)) {
            $id = $t[0];
            if ($id === T_WHITESPACE || $id === T_COMMENT || $id === T_DOC_COMMENT) continue;
            $stream[] = ['t' => $id, 's' => $t[1]];
        } else {
            $stream[] = ['t' => null, 's' => $t]; // punctuation: ( ) ; . , ` etc.
        }
    }
    unset($toks);
    $n = count($stream);

    /* ---------- taint-lite: which variables carry request input or decoded input ---------- */
    $tainted = [];  // varname => true
    for ($pass = 0; $pass < 3; $pass++) {
        $changed = false;
        for ($i = 0; $i < $n; $i++) {
            if ($stream[$i]['t'] === T_VARIABLE && isset($stream[$i+1]) && $stream[$i+1]['s'] === '=' ) {
                $rhsHasInput = false;
                for ($j = $i + 2, $k = 0; $j < $n && $k < 60 && $stream[$j]['s'] !== ';'; $j++, $k++) {
                    $sj = $stream[$j]['s'];
                    if ($stream[$j]['t'] === T_VARIABLE && dsscan_is_input_var($sj, $tainted)) $rhsHasInput = true;
                    if ($stream[$j]['t'] === T_STRING && stripos($sj, 'php://input') !== false) $rhsHasInput = true;
                    if ($stream[$j]['t'] === T_CONSTANT_ENCAPSED_STRING && stripos($sj, 'php://input') !== false) $rhsHasInput = true;
                }
                if ($rhsHasInput && empty($tainted[$stream[$i]['s']])) { $tainted[$stream[$i]['s']] = true; $changed = true; }
            }
        }
        if (!$changed) break;
    }

    /* ---------- variable = concatenated string literal  (resolve variable-function names) ------ */
    $varString = [];    // varname => resolved literal string (from "a"."b"."c" assignment)
    $varAssembled = []; // varname => n, value built by concatenating CHARACTER-TABLE subscripts
    for ($i = 0; $i < $n; $i++) {
        if ($stream[$i]['t'] === T_VARIABLE && isset($stream[$i+1]) && $stream[$i+1]['s'] === '=') {
            $acc = ''; $ok = true; $sawStr = false;
            for ($j = $i + 2, $k = 0; $j < $n && $k < 40 && $stream[$j]['s'] !== ';'; $j++, $k++) {
                $tj = $stream[$j]['t']; $sj = $stream[$j]['s'];
                if ($tj === T_CONSTANT_ENCAPSED_STRING) { $acc .= dsscan_strip_quotes($sj); $sawStr = true; }
                else if ($sj === '.' ) { continue; }
                else { $ok = false; break; }
            }
            if ($ok && $sawStr && $acc !== '') $varString[$stream[$i]['s']] = $acc;

            /* The 2026-09-16 webshell builds its function names from a character TABLE, not from
               string literals: $t = range("~"," "); then $fn = $t[42].$t[65].$t[62].$t[65]; $fn(...).
               Literal resolution cannot see through that, but the SHAPE is unmistakable. >=4 because
               a name worth hiding is at least 4 chars ("eval","exec"); at 3 this flagged brick/math. */
            $sub = 0;
            for ($j = $i + 2, $k = 0; $j < $n && $k < 90 && !dsscan_is_punct($stream[$j], ';'); $j++, $k++) {
                if ($stream[$j]['t'] === T_VARIABLE && isset($stream[$j+1]) && dsscan_is_punct($stream[$j+1], '[')) $sub++;
            }
            if ($sub >= 4) $varAssembled[$stream[$i]['s']] = $sub;
        }
    }

    /* ---------- walk calls ---------- */
    $hasEvalDecode = false; $hasExecInput = false;
    $hasSelfRewrite = false; $hasAuthCookie = false; $hasInsertUser = false; $hasAdminLookup = false;
    $hasNonceOrCap = false; $hasMoveUpload = false; $hasMailLoop = false; $gotoCount = 0;
    $hasNestedHash = false; $backtickInput = false; $writesPhp = false;

    for ($i = 0; $i < $n; $i++) {
        $tok = $stream[$i];
        $s = $tok['s'];

        if ($tok['t'] === T_GOTO || ($tok['t'] === T_STRING && $s === 'goto')) $gotoCount++;

        /* backtick shell operator. The operator is a PUNCTUATION token (type null); a backtick
           CHARACTER inside a double-quoted SQL string ("`$col`") is a T_ENCAPSED_AND_WHITESPACE
           token whose text is also "`" - do NOT confuse them (that flagged every SQL-quoting file).
           Legit vendor code also runs static commands this way (monolog `git`), so require a
           REQUEST-controlled/tainted variable inside the backticks. */
        if (dsscan_is_punct($tok, '`')) {
            for ($j = $i + 1; $j < $n && !dsscan_is_punct($stream[$j], '`') && $j < $i + 40; $j++) {
                if ($stream[$j]['t'] === T_VARIABLE && dsscan_is_input_var($stream[$j]['s'], $tainted)) { $backtickInput = true; }
            }
        }

        /* eval( ... ) */
        if ($tok['t'] === T_EVAL) {
            $arg = dsscan_arg_range($stream, $i + 1, $n);
            if ($arg !== null) {
                $refInput = dsscan_range_has_input($stream, $arg[0], $arg[1], $tainted);
                $refDecode = dsscan_range_has_decoder($stream, $arg[0], $arg[1], $DECODERS);
                if ($refDecode || $refInput) { $hasEvalDecode = true; }
            }
            $add('evalpresent', 12, "eval() present");
        }

        /* named or resolved call. CRUCIAL: skip METHOD calls and function DEFINITIONS.
           $obj->passthru(), Foo::exec() and `function system(...)` are user code that merely shares
           a name with a PHP builtin (ninja-tables has a LazyCollection::passthru()). */
        $callName = null; $callPos = null; $assembled = false;
        $prevT = ($i > 0) ? $stream[$i-1]['t'] : null;
        $prevS = ($i > 0) ? $stream[$i-1]['s'] : '';
        $isMemberOrDef = in_array($prevT, [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, T_CONST], true)
                       || (defined('T_NULLSAFE_OBJECT_OPERATOR') && $prevT === T_NULLSAFE_OBJECT_OPERATOR)
                       || $prevS === '->' || $prevS === '::' || $prevT === T_DOUBLE_ARROW;
        if ($tok['t'] === T_STRING && !$isMemberOrDef && isset($stream[$i+1]) && $stream[$i+1]['s'] === '(') {
            $callName = strtolower($s); $callPos = $i + 1; $assembled = false;
        } elseif ($tok['t'] === T_VARIABLE && isset($stream[$i+1]) && $stream[$i+1]['s'] === '(' && !$isMemberOrDef) {
            if (isset($varString[$s])) { $callName = strtolower($varString[$s]); $callPos = $i + 1; $assembled = true; }
            elseif (isset($varAssembled[$s])) {
                $add('chartable:' . $s, 90, "calls a function whose name was assembled from a character table (" . $varAssembled[$s] . " concatenated subscripts) - deliberate scanner evasion");
                $callName = null;
            }
            else {
                // name unknown (e.g. built by an XOR loop). A dynamic call whose ARGUMENT is request
                // input is a weak signal: TablePress picks mb_stripos vs stripos this way too.
                $ar = dsscan_arg_range($stream, $i + 1, $n);
                if ($ar !== null && dsscan_range_has_input($stream, $ar[0], $ar[1], $tainted) && !$fileVerifies) {
                    $add('varfninput', 25, "call to a variable function whose argument is request input (weak: legit code also picks mb_* variants this way)");
                }
                $callName = null;
            }
        } elseif ($tok['s'] === ')' && isset($stream[$i+1]) && $stream[$i+1]['s'] === '(') {
            // ( "sy"."st"."em" )( ... )  -> reconstruct the parenthesised concat immediately before
            $concat = dsscan_concat_before($stream, $i);
            if ($concat !== null && $concat !== '') { $callName = strtolower($concat); $callPos = $i + 1; $assembled = true; }
        }

        if ($callName !== null && $callPos !== null) {
            $arg = dsscan_arg_range($stream, $callPos, $n);
            $isExec = in_array($callName, $EXEC_SINKS, true);
            $isCode = in_array($callName, $CODE_SINKS, true);
            $isCb   = in_array($callName, $CALLBACK_SINKS, true);
            $isWrite= in_array($callName, $FILEWRITE, true);

            // the call NAME was assembled at runtime (concat or variable) AND resolves to danger
            if ($assembled && in_array($callName, $DANGER_ALL, true)) {
                $add('assembled:' . $callName, 120, "runtime-assembled call to a dangerous function: $callName() (name built from string fragments/variable to evade scanners)");
            }

            if ($arg !== null) {
                $refInput  = dsscan_range_has_input($stream, $arg[0], $arg[1], $tainted);
                $refDecode = dsscan_range_has_decoder($stream, $arg[0], $arg[1], $DECODERS);
                $firstArg  = dsscan_first_arg_range($stream, $arg[0], $arg[1]);

                if ($isExec && $refInput) { $hasExecInput = true; }
                // A bare exec sink with only literal/static args is legit library code (Symfony
                // Process, Ramsey UUID). Not scored: only input-fed or runtime-assembled exec is actionable.
                if ($isCode && ($refInput || $refDecode)) { $hasEvalDecode = true; }
                if ($callName === 'preg_replace') {
                    $pat = dsscan_first_string($stream, $arg[0], $arg[1]);
                    if ($pat !== null && preg_match('/^\s*(.)[\s\S]*\1[a-zA-Z]*e[a-zA-Z]*\s*$/', $pat)) {
                        $add('prege', 120, "preg_replace() with /e modifier (executes the replacement as PHP)");
                    }
                }
                if ($isCb) {
                    // Callback sinks are dangerous ONLY through the CALLBACK argument, never the data.
                    // array_map('trim',$_POST) is benign; array_map($_GET['f'],$x) is not.
                    $cbLiteral = dsscan_first_string($stream, $arg[0], $arg[1]);
                    $danglist = array_merge($EXEC_SINKS, $CODE_SINKS, ['eval','system','assert','call_user_func','call_user_func_array']);
                    if ($cbLiteral !== null && in_array(strtolower(trim($cbLiteral)), $danglist, true)
                        && ! dsscan_literal_in_array_callable($stream, $arg[0], $arg[1], trim($cbLiteral))) {
                        $add('smuggle:' . $callName, 110, "$callName() smuggles a call to '" . trim($cbLiteral) . "'");
                    } else {
                        // a callback taken DIRECTLY from request input, only where arg 1 IS the callback,
                        // only when it is a bare value (not array($this,$m) method dispatch, which is how
                        // Beaver Builder/ACF/Forminator route AJAX behind a nonce)
                        $cbFirst = in_array($callName, ['call_user_func','call_user_func_array','array_map','register_shutdown_function','register_tick_function','ob_start','forward_static_call','forward_static_call_array'], true);
                        if ($cbFirst && $firstArg !== null && dsscan_range_has_input($stream, $firstArg[0], $firstArg[1], $tainted)
                            && !dsscan_range_is_method_dispatch($stream, $firstArg[0], $firstArg[1]) && !$fileVerifies) {
                            $add('cbinput:' . $callName, 60, "$callName() callback is a bare request value (dynamic call of an attacker-named function)");
                        }
                    }
                }
                if ($isWrite) {
                    if (dsscan_write_target_is_php($stream, $arg[0], $arg[1])) $writesPhp = true;
                    if ($callName === 'move_uploaded_file') $hasMoveUpload = true;
                    if (($callName === 'file_put_contents' || $callName === 'fwrite' || $callName === 'fputs') && $refDecode) {
                        $add('writedecoded', 70, "$callName() writes decoded (base64/gz) content to disk (dropper)");
                    }
                    // self-rewrite: fopen(__FILE__ ... 'a')  or copy(__FILE__, ...)
                    if (dsscan_range_has_file_const($stream, $arg[0], $arg[1])) {
                        if ($callName === 'copy') { $hasSelfRewrite = true; }
                        if ($callName === 'fopen' && dsscan_range_has_append_mode($stream, $arg[0], $arg[1])) { $hasSelfRewrite = true; }
                    }
                }
                if ($callName === 'mail' && $refInput) { $hasMailLoop = true; }
                if ($callName === 'range') {
                    // range("~"," ") builds a printable-character table: stage one of the 2026-09-16 shell
                    $a1 = dsscan_first_string($stream, $arg[0], $arg[1]);
                    if ($a1 !== null && strlen($a1) === 1) {
                        $add('chartable-range', 45, "range() over single characters builds a character table (used to spell function names past a scanner)");
                    }
                }
                if ($callName === 'md5') {
                    if (isset($stream[$callPos+1]) && $stream[$callPos+1]['t'] === T_STRING && strtolower($stream[$callPos+1]['s']) === 'md5') { $hasNestedHash = true; }
                }
                if ($callName === 'wp_set_auth_cookie') $hasAuthCookie = true;
                if ($callName === 'wp_insert_user') $hasInsertUser = true;
                if ($callName === 'get_users' || $callName === 'get_user_by' || $callName === 'wp_set_current_user') $hasAdminLookup = true;
                if (in_array($callName, ['wp_verify_nonce','check_admin_referer','check_ajax_referer','current_user_can'], true)) $hasNonceOrCap = true;
                if ($callName === 'move_uploaded_file' && $refInput) { $add('mvinput', 10, "move_uploaded_file() fed by request input"); }
            }
        }

        /* include/require of a variable or remote URL */
        if (in_array($tok['t'], [T_INCLUDE, T_REQUIRE, T_INCLUDE_ONCE, T_REQUIRE_ONCE], true)) {
            $rem = false;
            for ($j = $i + 1, $k = 0; $j < $n && $k < 8 && $stream[$j]['s'] !== ';'; $j++, $k++) {
                if ($stream[$j]['t'] === T_VARIABLE && in_array($stream[$j]['s'], $REQ_SUPER, true)) $rem = true;
                if ($stream[$j]['t'] === T_CONSTANT_ENCAPSED_STRING && preg_match('#^[\'"]?(https?|ftp|php)://#i', $stream[$j]['s'])) $rem = true;
                if ($stream[$j]['t'] === T_STRING && stripos($stream[$j]['s'], 'php://input') !== false) $rem = true;
            }
            if ($rem) { $add('rfi', 90, "include/require of a remote URL or request input (remote file inclusion)"); }
        }
    }
    unset($stream);

    /* ---------- combine the behavioural verdicts into score ---------- */
    // credential / nonce verification present? Then wp_set_auth_cookie is a real login flow, not a
    // backdoor: every legit FP (WPE sign-on, Google Sign-In, 2FA, Forminator registration) verifies
    // something first; the WDG loader just picks the first administrator and sets the cookie.
    $verifiesCreds = preg_match('/wp_authenticate|wp_check_password|check_password|wp_verify_nonce|check_admin_referer|check_ajax_referer|password_verify|wp_signon|is_email|oauth|openid|verify_token|verify_otp|->verify\b|two.?factor|get_password_reset_key/i', $src);

    if ($hasExecInput)    { $score += 130; $reasons[] = "request input flows into a command-execution sink (shell_exec/system/exec/passthru/popen)"; }
    if ($hasEvalDecode)   { $score += 130; $reasons[] = "eval/assert/create_function executes decoded or request-controlled data"; }
    if ($backtickInput)   { $score += 110; $reasons[] = "shell backtick operator runs request-controlled data"; }
    if ($hasSelfRewrite)  { $score += 110; $reasons[] = "self-rewriting: reads its own source (__FILE__) and appends/copies it elsewhere (resurrection loader)"; }
    if ($hasMoveUpload && !$hasNonceOrCap) { $score += 45; $reasons[] = "move_uploaded_file() with no nonce/capability check in the file (possible unauthenticated upload)"; }
    if ($hasAuthCookie && ($hasInsertUser || $hasAdminLookup) && !$verifiesCreds) {
        $score += 90; $reasons[] = "forges an admin session: wp_set_auth_cookie() + user creation/lookup with NO credential/nonce check (password-less backdoor login)";
    }
    if ($hasMailLoop)     { $score += 55; $reasons[] = "mail() fed by request input (spam mailer)"; }
    // goto only counts alongside eval/a decoder (a real state machine uses goto and has neither)
    if ($gotoCount >= 3 && ($hasEvalDecode || preg_match('/base64_decode|gzinflate|gzuncompress|str_rot13|eval\s*\(/i', $src))) {
        $score += 60; $reasons[] = "goto flow-obfuscation ($gotoCount goto) combined with eval/decoders";
    }
    if ($hasNestedHash)   { $score += 30; $reasons[] = "nested md5(md5(...)) password gate (shell auth pattern)"; }
    if ($writesPhp)       { $score += 20; }

    /* ---- raw-text tells. These read the SOURCE, so they are the reason this engine must never scan
       its own file (its regexes contain the very words): the caller exempts scanner paths by hash. */
    if (preg_match('/\$(auth_pass|default_action|default_use_ajax|c99sh|wso_version)\b/i', $src)
        && preg_match('/FilesMan|SQL|Sm0key|c99|r57|wso|b374k|IndoXploit|phpspy/i', $src)) {
        $score += 110; $reasons[] = "known web-shell UI fingerprint (\$auth_pass + FilesMan/panel strings)";
    }
    if (preg_match('/HTTP_(USER_AGENT|REFERER)/', $src) && preg_match('/googlebot|bingbot|crawl|spider|yandex|slurp/i', $src)
        && preg_match('/header\s*\(\s*[\'"]\s*location|wp_redirect|http_response_code\s*\(\s*(301|302)/i', $src)) {
        $score += 65; $reasons[] = "search-engine cloaking: sniffs crawler UA/referer then redirects (doorway)";
    }
    if (preg_match('/left:\s*-\s*\d{6,}px/i', $src) && preg_match('/<a\s+href/i', $src)) {
        $score += 80; $reasons[] = "hidden off-canvas <a href> link injection (left:-<big>px wrapping an anchor)";
    }
    if (preg_match('/<iframe[^>]{0,200}(display\s*:\s*none|visibility\s*:\s*hidden|width\s*=\s*["\']?\s*0|height\s*=\s*["\']?\s*0|width\s*:\s*0)/i', $src)
        && preg_match('/<iframe[^>]{0,200}src\s*=\s*["\']?https?:\/\//i', $src)) {
        $score += 70; $reasons[] = "hidden/zero-size off-site <iframe> injected into output";
    }
    if (preg_match('/(file_put_contents|fwrite|fputs)\s*\([^;]*(robots\.txt|sitemap[\w-]*\.xml)/i', $src)
        && preg_match('/\$_(GET|POST|REQUEST|COOKIE)/', $src)) {
        $score += 55; $reasons[] = "writes request-controlled content into robots.txt / a sitemap (search-spam cloaking)";
    }
    if (preg_match('/google-site-verification/i', $src) && preg_match('/wp_head|add_action|echo|printf/i', $src)
        && preg_match('/[A-Za-z0-9_\-]{30,}/', $src)) {
        if ($hasAuthCookie || $hasInsertUser || preg_match('/wp_insert_user|set_role|administrator/i', $src)) {
            $score += 40; $reasons[] = "injects a Search Console verification meta AND touches user/admin state";
        }
    }

    return $done();
}

/**
 * Scan many paths with a time budget. Never echoes. Returns
 *   ['scanned'=>n, 'findings'=>n, 'skipped'=>n, 'stopped_at'=>int|null, 'elapsed'=>float, 'peak_mb'=>float]
 * $opts as dsscan_scan_file plus:
 *   min       report findings with score >= min (default 1)
 *   deadline  unix timestamp; stop BEFORE the next file once reached and return its index in
 *             'stopped_at' so the caller can resume there next run (WP-Cron must be time-boxed:
 *             a long run holds the cron lock and delays every other plugin's scheduled events)
 * $sink($finding) is called for each finding (and for each unscannable file, with 'skipped'=>true).
 */
function dsscan_scan_list($paths, $opts = [], $sink = null) {
    $min = isset($opts['min']) ? (int) $opts['min'] : 1;
    $deadline = isset($opts['deadline']) ? (int) $opts['deadline'] : 0;
    $t0 = microtime(true);
    $stats = ['scanned' => 0, 'findings' => 0, 'skipped' => 0, 'stopped_at' => null, 'elapsed' => 0.0, 'peak_mb' => 0.0];
    $idx = -1;
    foreach ($paths as $path) {
        $idx++;
        if ($deadline > 0 && time() >= $deadline) { $stats['stopped_at'] = $idx; break; }
        $path = is_string($path) ? rtrim($path, "\r\n") : '';
        if ($path === '') continue;
        try {
            $res = dsscan_scan_file($path, $opts);
        } catch (\Throwable $e) {
            // ONE unscannable file must never kill the pass, and must never read as clean.
            $stats['skipped']++;
            if ($sink) $sink(['path' => $path, 'score' => 50, 'tier' => 'REVIEW', 'md5' => '-', 'skipped' => true,
                              'reasons' => ['could NOT be scanned (' . get_class($e) . ': ' . substr($e->getMessage(), 0, 80) . ') - not cleared, check by hand']]);
            continue;
        }
        $stats['scanned']++;
        if ($res === null || $res['score'] < $min) continue;
        $stats['findings']++;
        if ($sink) $sink($res);
    }
    $stats['elapsed'] = round(microtime(true) - $t0, 2);
    $stats['peak_mb'] = round(memory_get_peak_usage(true) / 1048576, 1);
    return $stats;
}

/* ---------- helpers ---------- */

function dsscan_strip_quotes($s) {
    $s = trim($s);
    if (strlen($s) >= 2) {
        $q = $s[0];
        if (($q === '"' || $q === "'") && substr($s, -1) === $q) $s = substr($s, 1, -1);
    }
    return stripcslashes($s);
}

// [start,end] token indices of the argument list after an opening '(' at $openPos
function dsscan_arg_range($stream, $openPos, $n) {
    if (!isset($stream[$openPos]) || $stream[$openPos]['s'] !== '(') {
        $f = null;
        for ($j = $openPos; $j < min($openPos + 2, $n); $j++) if ($stream[$j]['s'] === '(') { $f = $j; break; }
        if ($f === null) return null; $openPos = $f;
    }
    $depth = 0;
    for ($j = $openPos; $j < $n; $j++) {
        if ($stream[$j]['s'] === '(') $depth++;
        elseif ($stream[$j]['s'] === ')') { $depth--; if ($depth === 0) return [$openPos + 1, $j - 1]; }
        if ($j - $openPos > 400) break; // bounded
    }
    return [$openPos + 1, min($openPos + 60, $n - 1)];
}

// the first top-level argument [start,end] inside an already-computed arg range [a,b]
function dsscan_first_arg_range($stream, $a, $b) {
    if ($a > $b) return null;
    $depth = 0; $cnt = count($stream);
    for ($j = $a; $j <= $b && $j < $cnt; $j++) {
        $s = $stream[$j]['s'];
        if ($s === '(' || $s === '[') $depth++;
        elseif ($s === ')' || $s === ']') $depth--;
        elseif ($s === ',' && $depth === 0) return [$a, $j - 1];
    }
    return [$a, $b];
}

// the strong, unambiguously attacker-controlled inputs. $_SERVER/$_ENV are deliberately NOT here:
// legit libraries read them constantly (Symfony Process merges them), and the attacker-controllable
// header parts of $_SERVER (HTTP_USER_AGENT/REFERER) are handled by the dedicated cloaking rule.
function dsscan_is_input_var($v, $tainted) {
    if (in_array($v, ['$_GET','$_POST','$_REQUEST','$_COOKIE','$_FILES','$HTTP_RAW_POST_DATA'], true)) return true;
    return !empty($tainted[$v]);
}
function dsscan_is_punct($tok, $ch) { return $tok['t'] === null && $tok['s'] === $ch; }

// does the argument range look like a method dispatch - array($obj,$m), 'Class::method', $o->m - as
// opposed to a bare callable string/variable? Method dispatch on a fixed class is not arbitrary exec.
function dsscan_range_is_method_dispatch($stream, $a, $b) {
    $cnt = count($stream);
    for ($j = $a; $j <= $b && $j < $cnt; $j++) {
        $t = $stream[$j]['t']; $x = $stream[$j]['s'];
        if ($t === T_ARRAY || ($t === null && $x === '[')) return true;
        if ($t === T_DOUBLE_COLON || $x === '::' || $t === T_OBJECT_OPERATOR || $x === '->') return true;
        if ($t === T_CONSTANT_ENCAPSED_STRING && strpos($x, '::') !== false) return true;
    }
    return false;
}
// Is the dangerous callable literal the METHOD half of an array callable - array($obj,'eval') or
// [$obj,'eval'] - rather than a bare callable string? A method named like a builtin is not the
// builtin: the Redis client exposes an eval() METHOD (Redis runs Lua server-side), and WPMU DEV
// Hummingbird's Redis object cache calls call_user_func_array( array( $this->redis, 'eval' ), $args )
// on every cache read. Stock Hummingbird 3.21.2 scored CRITICAL on 1812sports.com 2026-09-17 and the
// cleanup quarantined a vendor file. The ENCLOSING BRACKET is the only thing that separates the two
// cases: usort($a,'system') and array($o,'system') are identical token shapes locally, so walk back to
// the bracket that encloses the literal and ask whether it opened an array.
function dsscan_literal_in_array_callable($stream, $a, $b, $needle) {
    $cnt = count($stream); $needle = strtolower($needle);
    for ($j = $a; $j <= $b && $j < $cnt; $j++) {
        if ($stream[$j]['t'] !== T_CONSTANT_ENCAPSED_STRING) continue;
        if (strtolower(dsscan_strip_quotes($stream[$j]['s'])) !== $needle) continue;
        $depth = 0;
        for ($k = $j - 1; $k >= $a; $k--) {
            $x = $stream[$k]['s'];
            if ($x === ')' || $x === ']') { $depth++; continue; }
            if ($x === '(' || $x === '[') {
                if ($depth > 0) { $depth--; continue; }
                if ($x === '[') return true;
                for ($m = $k - 1; $m >= $a; $m--) {
                    if ($stream[$m]['t'] === T_WHITESPACE) continue;
                    return $stream[$m]['t'] === T_ARRAY;
                }
                return false;
            }
        }
        return false;
    }
    return false;
}
function dsscan_range_has_input($stream, $a, $b, $tainted) {
    $cnt = count($stream);
    for ($j = $a; $j <= $b && $j < $cnt; $j++) {
        if ($stream[$j]['t'] === T_VARIABLE && dsscan_is_input_var($stream[$j]['s'], $tainted)) return true;
        if (($stream[$j]['t'] === T_CONSTANT_ENCAPSED_STRING || $stream[$j]['t'] === T_STRING) && stripos($stream[$j]['s'], 'php://input') !== false) return true;
    }
    return false;
}
function dsscan_range_has_decoder($stream, $a, $b, $DECODERS) {
    $cnt = count($stream);
    for ($j = $a; $j <= $b && $j < $cnt; $j++) {
        if ($stream[$j]['t'] === T_STRING && in_array(strtolower($stream[$j]['s']), $DECODERS, true)) return true;
    }
    return false;
}
function dsscan_first_string($stream, $a, $b) {
    $cnt = count($stream);
    for ($j = $a; $j <= $b && $j < $cnt; $j++) {
        if ($stream[$j]['t'] === T_CONSTANT_ENCAPSED_STRING) return dsscan_strip_quotes($stream[$j]['s']);
    }
    return null;
}
function dsscan_write_target_is_php($stream, $a, $b) {
    $cnt = count($stream);
    for ($j = $a; $j <= $b && $j < $cnt; $j++) {
        if ($stream[$j]['t'] === T_CONSTANT_ENCAPSED_STRING && preg_match('/\.(php|phtml|php5|php7|phar|pht|inc)\b/i', $stream[$j]['s'])) return true;
    }
    return false;
}
function dsscan_range_has_file_const($stream, $a, $b) {
    $cnt = count($stream);
    for ($j = $a; $j <= $b && $j < $cnt; $j++) {
        if ($stream[$j]['t'] === T_FILE) return true;
        if ($stream[$j]['t'] === T_STRING && strtolower($stream[$j]['s']) === '__file__') return true;
    }
    return false;
}
function dsscan_range_has_append_mode($stream, $a, $b) {
    $cnt = count($stream);
    for ($j = $a; $j <= $b && $j < $cnt; $j++) {
        if ($stream[$j]['t'] === T_CONSTANT_ENCAPSED_STRING && preg_match('/^[\'"][arwxc]\+?b?[\'"]$/', $stream[$j]['s'])) {
            $m = dsscan_strip_quotes($stream[$j]['s']);
            if (strpos($m, 'a') !== false || strpos($m, 'w') !== false || strpos($m, 'c') !== false) return true;
        }
    }
    return false;
}
// reconstruct a parenthesised concat immediately BEFORE index $i (which points at ')')
function dsscan_concat_before($stream, $i) {
    $depth = 0; $start = null;
    for ($j = $i; $j >= 0; $j--) {
        if ($stream[$j]['s'] === ')') $depth++;
        elseif ($stream[$j]['s'] === '(') { $depth--; if ($depth === 0) { $start = $j; break; } }
        if ($i - $j > 60) break;
    }
    if ($start === null) return null;
    $acc = ''; $sawStr = false;
    for ($j = $start + 1; $j < $i; $j++) {
        $tj = $stream[$j]['t']; $sj = $stream[$j]['s'];
        if ($tj === T_CONSTANT_ENCAPSED_STRING) { $acc .= dsscan_strip_quotes($sj); $sawStr = true; }
        elseif ($sj === '.') continue;
        else return null;
    }
    return $sawStr ? $acc : null;
}
function dsscan_doorway_html_score($src, $ext, $path) {
    $score = 0; $reasons = [];
    $gamble = '/togel|olxtoto|slot\s*gacor|situs\s*(slot|gacor)|pg\s*soft|judi\s*bola|link\s*slot|sbobet|bandar\s*(togel|bola)|pragmatic\s*play|maxwin|rtp\s*(slot|live)/i';
    $hits = preg_match_all($gamble, $src);
    if ($hits >= 8) { $score += 90; $reasons[] = "gambling/SEO-spam doorway: $hits betting terms in the page body"; }
    elseif ($hits >= 3) { $score += 30; $reasons[] = "gambling/SEO-spam terms present ($hits)"; }
    if (preg_match('/rel=["\']amphtml["\'][^>]*pages\.dev/i', $src)) { $score += 60; $reasons[] = "amphtml link to a *.pages.dev mirror (togel doorway tell)"; }
    if (preg_match('/<iframe[^>]+(display:\s*none|width=["\']?0|height=["\']?0)/i', $src) && preg_match('/https?:\/\//i', $src)) { $score += 40; $reasons[] = "hidden zero-size iframe"; }
    return ['score' => $score, 'reasons' => $reasons];
}
// used when the file could not (or must not) be tokenised: never go silent
function dsscan_raw_fallback($src, $DECODERS) {
    $score = 0; $reasons = [];
    if (preg_match('/\beval\s*\(\s*(base64_decode|gzinflate|gzuncompress|str_rot13|strrev)/i', $src)) { $score += 130; $reasons[] = "eval() of a decoder (raw scan; file was not tokenised)"; }
    if (preg_match('/(system|exec|shell_exec|passthru|popen|proc_open)\s*\([^)]*\$_(GET|POST|REQUEST|COOKIE)/i', $src)) { $score += 130; $reasons[] = "command sink fed by request input (raw scan)"; }
    if (preg_match('/\$_(GET|POST|REQUEST|COOKIE)/', $src) && preg_match('/\beval\s*\(/i', $src)) { $score += 60; $reasons[] = "eval + request input in a file that was not tokenised (evasion attempt)"; }
    return ['score' => $score, 'reasons' => $reasons];
}

} /* end library guard */

/* ============================================================================================
   CLI. Runs ONLY when PHP was started with this file as the script (never on include/require
   from WordPress). Everything with side effects lives here: ini tweaks, shutdown reporter,
   stdin loop, streamed stdout.
   ============================================================================================ */
if (PHP_SAPI === 'cli' && !defined('DSSCAN_NO_CLI') && isset($argv[0]) && @realpath($argv[0]) === __FILE__) {
    error_reporting(0);
    @ini_set('display_errors', '0');
    // CLI is its own short-lived process: a generous limit lets a big file degrade to a skip, not a fatal.
    @ini_set('memory_limit', '384M');
    @ini_set('pcre.backtrack_limit', '2000000');

    $dsscan_opts = ['min' => 1, 'max_read' => 3000000, 'tokenize_cap' => 1500000];
    $dsscan_stats_flag = false;
    foreach ($argv as $dsscan_a) {
        if (strpos($dsscan_a, '--min=') === 0)   $dsscan_opts['min'] = (int) substr($dsscan_a, 6);
        if (strpos($dsscan_a, '--max=') === 0)   $dsscan_opts['max_read'] = (int) substr($dsscan_a, 6);
        if (strpos($dsscan_a, '--tokcap=') === 0) $dsscan_opts['tokenize_cap'] = (int) substr($dsscan_a, 9);
        if ($dsscan_a === '--stats') $dsscan_stats_flag = true;
    }

    /* A scanner must FAIL LOUDLY. If PHP dies (memory, parser) the shutdown handler names the file
       it died on, so the run is never silently empty and mistaken for "nothing found". Paired with
       streaming output: everything already found has been printed before any crash. */
    $GLOBALS['DSSCAN_CURRENT'] = '';
    register_shutdown_function(function () {
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            echo 'DSCAN|HIGH|60|-|' . $GLOBALS['DSSCAN_CURRENT'] . '|content scan ABORTED here (' . substr($e['message'], 0, 90)
               . '). Files after this one were NOT scanned - this site is NOT cleared.' . "\n";
        }
    });

    // Output is streamed in discovery order (NOT sorted) on purpose: buffering would mean a crash near
    // the end threw away every finding. Sort for humans with: sort -t'|' -k3 -rn
    $dsscan_emit = function ($r) {
        $seen = []; $reasons = [];
        foreach ($r['reasons'] as $why) { if (!isset($seen[$why])) { $seen[$why] = 1; $reasons[] = $why; } }
        $reasons = array_slice($reasons, 0, 8);
        echo 'DSCAN|' . $r['tier'] . '|' . $r['score'] . '|' . ($r['md5'] ? $r['md5'] : '-') . '|' . $r['path'] . '|'
           . str_replace(['|', "\n", "\r"], [' ', ' ', ' '], implode('; ', $reasons)) . "\n";
        flush();
    };
    $dsscan_paths = function () {
        $in = fopen('php://stdin', 'r');
        while (($line = fgets($in)) !== false) {
            $p = rtrim($line, "\r\n");
            if ($p !== '') { $GLOBALS['DSSCAN_CURRENT'] = $p; yield $p; }
        }
        fclose($in);
    };
    $dsscan_stats = dsscan_scan_list($dsscan_paths(), $dsscan_opts, $dsscan_emit);
    if ($dsscan_stats_flag) {
        fwrite(STDERR, sprintf("dsscan: scanned=%d findings=%d skipped=%d elapsed=%ss peak=%sMB\n",
            $dsscan_stats['scanned'], $dsscan_stats['findings'], $dsscan_stats['skipped'], $dsscan_stats['elapsed'], $dsscan_stats['peak_mb']));
    }
}
