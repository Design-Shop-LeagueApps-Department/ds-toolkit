<?php
// Run: php tests/scan-disable-suffix-test.php        (plain PHP, no WordPress, no network)
//
// Guards the disable-suffix family in includes/ds-scan-engine.php.
//
// We disable a mu-plugin by renaming it. `.off` was the first convention; sessions have since used
// `.disabled` (timetosign tts-dev-home.php.disabled - FIVE CRITICAL emails 8-13 Oct 2026) and
// `.moved-to-functions` (dspowerfc). The engine knew only `.off`, so the others were scored 120 as
// "extension is camouflage" and mailed daily.
//
// The carve-out waives POLYGLOT credit only, never behaviour. These assertions hold that line:
// a disabled file that really holds a shell must still be convicted, and a suffix we do NOT document
// must still score as camouflage.

$ENGINE = dirname(__DIR__) . '/includes/ds-scan-engine.php';
$D = sys_get_temp_dir() . '/ds-disable-' . getmypid();
@mkdir($D, 0777, true);
$PASS = 0; $FAIL = 0;
$O = '<' . '?php';

function tier($engine, $path) {
    $out = shell_exec('printf %s ' . escapeshellarg($path) . " | php " . escapeshellarg($engine) . ' --min=1 2>/dev/null');
    foreach (explode("\n", (string) $out) as $line) {
        $p = explode('|', $line);
        if (isset($p[1]) && $p[1] !== '') { return trim($p[1]); }
    }
    return 'NONE';
}
function ok(&$P, &$F, $cond, $label, $got = '') {
    if ($cond) { $P++; } else { $F++; echo "  FAIL $label" . ($got !== '' ? " (got $got)" : '') . "\n"; }
}

// A real, benign disabled mu-plugin of ours: plugin header, ordinary WordPress calls.
$benign = $O . "\n/**\n * Plugin Name: TTS Front Page\n * Description: hand-coded home page, disabled.\n */\n"
        . "defined('ABSPATH') || exit;\n"
        . "add_filter('theme_page_templates', function (\$t) { \$t['x'] = 'X'; return \$t; });\n";

// 1. Every suffix WE document stands down.
foreach (array('off', 'disabled', 'moved-to-functions') as $ext) {
    $f = "$D/tts-dev-home.php.$ext";
    file_put_contents($f, $benign);
    $got = tier($ENGINE, $f);
    ok($PASS, $FAIL, $got === 'NONE', "a benign disabled mu-plugin named .$ext must not be reported", $got);
}

// 2. THE LINE THAT MATTERS: behaviour still convicts, whatever it is renamed to.
$shell = $O . ' @eval($_POST["x"]); /* padding so it clears the executable size floor ............ */';
foreach (array('off', 'disabled', 'moved-to-functions') as $ext) {
    $f = "$D/evil.php.$ext";
    file_put_contents($f, $shell);
    $got = tier($ENGINE, $f);
    ok($PASS, $FAIL, $got === 'CRIT', "a SHELL renamed .$ext must still be CRIT", $got);
}

// 3. A suffix we do NOT document keeps full camouflage weight - the carve-out must not widen.
foreach (array('bak', 'old', 'save') as $ext) {
    $f = "$D/tts-dev-home.php.$ext";
    file_put_contents($f, $benign);
    $got = tier($ENGINE, $f);
    ok($PASS, $FAIL, $got !== 'NONE', "an UNdocumented suffix .$ext must still be reported", $got);
}

echo "disable-suffix test: $PASS passed, $FAIL failed\n";
array_map('unlink', glob("$D/*")); @rmdir($D);
exit($FAIL ? 1 : 0);
