#!/bin/bash
# lint.sh [php image]   e.g. tools/test/lint.sh php:8.4.27-cli-bookworm
# Runs php -l on every tracked PHP file inside the given PHP image.
# Writes tools/test/out/lint-<tag>.txt and prints a summary.
set -eu
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
IMAGE=${1:-php:8.3.36-cli-bookworm}
TAG=$(echo "$IMAGE" | sed -E 's/^[^:]*:([^@]*).*/\1/')
OUT="$ROOT/tools/test/out"; mkdir -p "$OUT"
cd "$ROOT"
git ls-files '*.php' > "$OUT/lint-files.txt"
docker run --rm -i -v "$ROOT":/src:ro -w /src "$IMAGE" sh -c '
  php -v | head -1
  while read -r f; do
    r=$(php -d display_errors=1 -d error_reporting=E_ALL -l "$f" 2>&1)
    case "$r" in
      *"No syntax errors detected"*) case "$r" in *Deprecated*) echo "DEPRECATED|$f";; esac ;;
      *) echo "FAIL|$f|$(echo "$r" | grep -m1 -i error)" ;;
    esac
  done' < "$OUT/lint-files.txt" > "$OUT/lint-$TAG.txt"
head -1 "$OUT/lint-$TAG.txt"
echo "files: $(wc -l < "$OUT/lint-files.txt")  parse errors: $(grep -c '^FAIL|' "$OUT/lint-$TAG.txt" || true)  deprecated: $(grep -c '^DEPRECATED|' "$OUT/lint-$TAG.txt" || true)"
echo "details: $OUT/lint-$TAG.txt"
