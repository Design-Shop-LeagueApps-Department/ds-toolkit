#!/bin/bash
# tests/run.sh: lint every PHP file and run every test, one exit code. The gate CI runs before a release zip is built.
#
#   tests/run.sh                         lint + every plain-PHP test
#   tests/run.sh --wp "wp --path=/site"  also the tests marked "@requires wordpress", through `<wp> eval-file`
#   tests/run.sh --no-lint               tests only
#   PHP="php8.2 -n" tests/run.sh         another PHP binary (may carry flags)
#
# A test is a tests/*-test.php file that exits 0 when it passes. One that needs a running WordPress with
# Beaver Builder says so with a "@requires wordpress" line in its header; without --wp those are counted as
# skipped, never as passed.
set -u
cd "$(dirname "$0")/.." || exit 2
PHP_CMD=${PHP:-php}
WP_CMD=""
LINT=1
while [ $# -gt 0 ]; do
  case "$1" in
    --wp) WP_CMD="${2:-}"; shift 2 ;;
    --no-lint) LINT=0; shift ;;
    *) echo "usage: $0 [--wp \"<wp command>\"] [--no-lint]"; exit 2 ;;
  esac
done

fail=0; pass=0; skip=0; failed=""

if [ "$LINT" = 1 ]; then
  n=0; bad=0
  while IFS= read -r f; do
    n=$((n + 1))
    if ! out=$($PHP_CMD -l "$f" 2>&1); then
      bad=$((bad + 1)); echo "LINT  $f"; echo "$out" | head -3
    fi
  done < <(find . -type f \( -name '*.php' -o -name '*.inc' -o -name '*.phtml' \) -not -path './.git/*' | sed 's#^\./##' | LC_ALL=C sort)
  echo "lint: $n files, $bad with errors ($($PHP_CMD -r 'echo PHP_VERSION;'))"
  [ "$bad" -gt 0 ] && fail=1
fi

for t in tests/*-test.php; do
  [ -f "$t" ] || continue
  if grep -q '@requires wordpress' "$t"; then
    if [ -z "$WP_CMD" ]; then skip=$((skip + 1)); continue; fi
    out=$($WP_CMD eval-file "$t" 2>&1); rc=$?
  else
    out=$($PHP_CMD "$t" 2>&1); rc=$?
  fi
  if [ $rc -eq 0 ]; then
    pass=$((pass + 1))
  else
    fail=1; failed="$failed $t"
    echo "FAIL  $t (exit $rc)"; echo "$out" | grep -i -E 'fail|fatal|error' | head -5
  fi
done

echo "tests: $pass passed, $(echo $failed | wc -w | tr -d ' ') failed, $skip skipped (need --wp)"
[ -n "$failed" ] && echo "failed:$failed"
exit $fail
