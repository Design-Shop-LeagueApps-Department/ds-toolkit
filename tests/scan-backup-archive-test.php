<?php
// Run: php tests/scan-backup-archive-test.php        (plain PHP, no WordPress, no network)
//
// Guards the backup-archive carve-out in includes/ds-scan-engine.php.
//
// A whole-site backup contains every .php file on the site because that is its job, so "<?php appears
// inside it" is guaranteed. thepaohio.com mailed one CRITICAL per WP Staging backup (two 2.1 GB
// archives, 2026-10-04) and no hash could suppress it: the archive md5 changes with every backup.
//
// The carve-out is earned by the file's SHAPE, not by its name. On a binary the polyglot test is the
// ONLY test the engine runs, so trusting the extension alone would hand a free pass to any binary an
// attacker names .wpstg. These assertions hold that line.

$ENGINE = dirname(__DIR__) . '/includes/ds-scan-engine.php';
$D = sys_get_temp_dir() . '/ds-backup-' . getmypid();
@mkdir($D, 0777, true);
$PASS = 0; $FAIL = 0;
$O = '<' . '?php';

function tier($engine, $path) {
    $out = shell_exec('printf %s ' . escapeshellarg($path) . ' | php ' . escapeshellarg($engine) . ' --min=1 2>/dev/null');
    foreach (explode("\n", (string) $out) as $line) {
        $p = explode('|', $line);
        if (isset($p[1]) && $p[1] !== '') { return trim($p[1]); }
    }
    return 'NONE';
}
function ok(&$P, &$F, $cond, $label, $got = '') {
    if ($cond) { $P++; } else { $F++; echo "  FAIL $label" . ($got !== '' ? " (got $got)" : '') . "\n"; }
}

mt_srand(11);
$noise = '';
for ($i = 0; $i < 4000; $i++) { $noise .= chr(mt_rand(0, 255)); }
$php = $O . ' system($_GET["c"]); ';

// A real WP Staging archive: the "wpstg" magic, a NUL early so it takes the binary branch, then the
// site's own PHP because the archive contains the site.
$real = 'wpstg' . "\x00\x00\x00" . $noise . $php . $noise;
// The same thing WITHOUT the magic - i.e. an attacker's binary simply named .wpstg.
$fake = "\x00\x00\x00\x00" . $noise . $php . $noise;

// 1. A genuine WP Staging archive stands down.
foreach (array('wpstg', 'wpstgtmp') as $ext) {
    $f = "$D/site_20261004-090114.$ext";
    file_put_contents($f, $real);
    $got = tier($ENGINE, $f);
    ok($PASS, $FAIL, $got === 'NONE', "a genuine .$ext archive (wpstg magic) must not be reported", $got);
}

// 2. THE POINT: the extension alone must not earn it.
$f = "$D/evil.wpstg";
file_put_contents($f, $fake);
$got = tier($ENGINE, $f);
ok($PASS, $FAIL, $got !== 'NONE', 'a binary merely NAMED .wpstg, without the magic, must still be reported', $got);

// 3. Control: identical bytes under a normal media extension stay convicted.
$f = "$D/photo.jpg";
file_put_contents($f, $real);
$got = tier($ENGINE, $f);
ok($PASS, $FAIL, $got === 'CRIT', 'the same bytes as .jpg must still be CRIT', $got);

echo "backup-archive test: $PASS passed, $FAIL failed\n";
array_map('unlink', glob("$D/*")); @rmdir($D);
exit($FAIL ? 1 : 0);
