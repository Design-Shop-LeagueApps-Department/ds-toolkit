# Tripwire fleet lists

Two files, fetched hourly by every site running DS Toolkit 1.9.142 or later. **A hash committed here is live
on every site within one cron cycle. No release, no fleet push.** One measured caveat: GitHub raw is served
from a CDN whose nodes refresh independently, so for a few minutes after a commit some sites can fetch the
previous copy and keep it for their cycle. Expect fleet-wide within one to two hours, not minutes. To force one
site: `wp transient delete ds_tripwire_rl_allow` then `wp eval 'DS_Tripwire::content_scan_now(60);'`.

Proven end to end 2026-09-20 on southlandsoccerleague.com: a one-line commit (`5db68bd`, Forminator 1.57.2
`upload.php`) went from `fetched count=8` to `count=9`, the merged known-good set from 16,176 to 16,177, and a
live HIGH 55 finding on that file stopped being reported, while `dsscan_scan_file()` on its own still scored
it HIGH 55. The rule is untouched; only the gate changed.

| File | What a line does | Safety property |
|---|---|---|
| `tripwire-allow.md5` | Suppresses findings on a file with that exact md5 (a verified false positive) | Can only ADD to the bundled `includes/known-good.md5`; can never remove |
| `tripwire-deny.md5` | Names a finding on a file with that exact md5 (`KNOWN MALWARE: <name>`) | Only labels a finding the engine already scored; can never create one |

## Adding a false positive (the daily case)

1. Read the file on the live site: `md5sum <file>`, size, mtime.
2. Prove it. Free plugin: download the exact version from `downloads.wordpress.org/plugin/<slug>.<ver>.zip` and `md5sum` the same path; it must be **identical**. Premium plugin: read the code and record why the flagged construct is legitimate.
3. Append one line to `tripwire-allow.md5`: `<md5>  <plugin> <version> <path> - <evidence> (<date>)`.
4. Commit to `main`. Done. Also add it to `fleet-audit/bin/gen-known-good.sh` so the next release bundles it.

Never add a hash you have not read the file for. A path or a name is never a reason; only the hash is.

## How it fails (on purpose)

The fetcher is built for a fleet of thousands where the worst bug is an email flood. Every failure leaves
the bundled list in charge, which is exactly the behaviour of every release before 1.9.141:

- not HTTP 200, not `text/*`, over 512 KB, transport error, anything thrown -> **bundle only**
- a body with **zero valid hashes** (an error page, a rate-limit notice, a truncated response, or a comment-only file) -> treated as a failed fetch, **never** as an empty list
- a failure after an earlier good fetch -> the **last good copy** keeps serving (30-day cache)
- failures are retried every 15 minutes, successes cached one cycle plus a per-site jitter so ~1,100 sites do not hit GitHub in the same second

Per-site health is in `wp option get ds_tripwire_state --format=json` under `content.remote`.

## Why public

The lists hold md5s of files and nothing secret. A private repo would put a read token on every site,
where the first compromised site leaks it. What protects the fleet is **write access to `main`**.
