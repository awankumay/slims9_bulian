#!/bin/bash
# run-local.sh: build and test the Docker stack locally, without GitHub Actions.
#
#   tools/test/run-local.sh [--php 8.3|8.4|<image>] [--port N] [--no-build] [--keep]
#
#   --php       PHP base image (default 8.3 = php:8.3.36-apache-bookworm)
#   --port      host port for the web UI (default 18881)
#   --no-build  use an existing image named by $SLIMS_IMAGE instead of building
#   --keep      leave the stack running afterwards (default: remove it with its volumes)
#
# It starts a throwaway compose project with sample data, then checks the fresh
# install, the admin login, runtime hardening, file exposure, a 176-URL smoke
# test, and persistence across restart and recreate. Output goes to
# tools/test/out/<tag>/. Exit code is the number of failed checks.
set -u

PHP83=php:8.3.36-apache-bookworm@sha256:cbd3b3d9011668443d54ab40ec31db56c73d91b4c8198935d639aa80d2d815c1
PHP84=php:8.4.27-apache-bookworm@sha256:e653ff90d7f6e2e6f81eddb07c864f1eae224ccbf5143f82a44f25e2f110e320

PHP_IMAGE=$PHP83; PORT=18881; BUILD=--build; KEEP=0
while [ $# -gt 0 ]; do
  case "$1" in
    --php) case "$2" in 8.3) PHP_IMAGE=$PHP83 ;; 8.4) PHP_IMAGE=$PHP84 ;; *) PHP_IMAGE=$2 ;; esac; shift 2 ;;
    --port) PORT=$2; shift 2 ;;
    --no-build) BUILD=--no-build; shift ;;
    --keep) KEEP=1; shift ;;
    *) echo "unknown option: $1" >&2; exit 2 ;;
  esac
done

ROOT=$(cd "$(dirname "$0")/../.." && pwd)
TESTDIR="$ROOT/tools/test"
TAG=$(echo "$PHP_IMAGE" | sed -E 's/^[^:]*:([0-9.]+).*/\1/' | tr -d .)
OUT="$TESTDIR/out/$TAG"; mkdir -p "$OUT"
PROJECT="slims-test-$TAG"
BASE="http://127.0.0.1:$PORT"
export SLIMS_IMAGE=${SLIMS_IMAGE:-slims-bulian:test-$TAG}

DB_PASS=$(openssl rand -hex 12); DB_ROOT_PASS=$(openssl rand -hex 12)
cat > "$OUT/test.env" <<EOF
APP_ENV=${APP_ENV:-production}
HTTP_BIND=127.0.0.1
HTTP_PORT=$PORT
DB_NAME=senayan
DB_USER=slims
DB_PASS=$DB_PASS
DB_ROOT_PASS=$DB_ROOT_PASS
LOAD_SAMPLE_DATA=true
PHP_IMAGE=$PHP_IMAGE
SLIMS_IMAGE=$SLIMS_IMAGE
EOF
chmod 600 "$OUT/test.env"

dc() { docker compose -p "$PROJECT" --env-file "$OUT/test.env" -f "$ROOT/docker-compose.yml" "$@"; }
FAILS=0
pass() { echo "PASS  $1"; }
fail() { echo "FAIL  $1"; FAILS=$((FAILS + 1)); }
check() { if [ "$2" = "$3" ]; then pass "$1 ($2)"; else fail "$1 (got '$2', expected '$3')"; fi; }
code() { curl -s -o /dev/null -w '%{http_code}' "$BASE/$1"; }
wait_up() {
  for _ in $(seq 1 150); do
    [ "$(code index.php)" = 200 ] && return 0
    sleep 2
  done
  return 1
}

echo "== $PROJECT  image=$SLIMS_IMAGE  base=$PHP_IMAGE"
dc down -v --remove-orphans >/dev/null 2>&1
if ! dc up -d $BUILD > "$OUT/up.log" 2>&1; then
  tail -20 "$OUT/up.log"; echo "FAIL  docker compose up"; exit 1
fi

echo "-- fresh install"
wait_up || fail "web UI did not answer 200 within 300s"
dc logs slims > "$OUT/slims.log" 2>&1
check "OPAC index.php" "$(code index.php)" 200
check "PHP version" "$(dc exec -T slims php -r 'echo PHP_VERSION;')" "$(echo "$PHP_IMAGE" | sed -E 's/^[^:]*:([0-9.]+).*/\1/')"
ADMIN_PASS=$(sed -n 's/.*Password : \([A-Za-z0-9]*\).*/\1/p' "$OUT/slims.log" | tail -1)
"$TESTDIR/login.sh" "$BASE" "$ADMIN_PASS" "$OUT/cookies.txt" > "$OUT/login.txt"
check "admin login" "$(grep -c 'ADMIN_LOGGED_IN=yes' "$OUT/login.txt")" 1
check "admin home" "$(grep -oE 'admin_home=[0-9]+' "$OUT/login.txt")" "admin_home=200"

echo "-- secrets and network"
check "DB password absent from slims log" "$(grep -c -- "$DB_PASS" "$OUT/slims.log")" 0
check ".env absent from image" "$(dc exec -T slims sh -c 'ls -a /var/www/html | grep -c "^\.env" || true')" 0
check "install/ absent from image" "$(dc exec -T slims sh -c 'test -e /var/www/html/install && echo yes || echo no')" no
check "DB port not published" "$(docker port "$(dc ps -q db)" | wc -l)" 0
check "app connects as non-root" "$(dc exec -T slims php -r 'echo (require "/var/www/html/config/database.php")["nodes"]["SLiMS"]["username"];')" slims

echo "-- runtime hardening"
check "session.upload_progress.enabled" "$(dc exec -T slims php -r 'echo ini_get("session.upload_progress.enabled") ? "1" : "0";')" 0
HDR=$(curl -s -D - -o /dev/null "$BASE/index.php")
check "X-Content-Type-Options" "$(echo "$HDR" | grep -i "^x-content-type-options:" | tr -d "\r" | tr "A-Z" "a-z")" "x-content-type-options: nosniff"
check "no X-Powered-By" "$(echo "$HDR" | grep -ci '^x-powered-by')" 0
check "Server header" "$(echo "$HDR" | grep -i '^server:' | tr -d '\r')" "Server: Apache"

echo "-- file exposure (expect not 200)"
for p in composer.lock composer.json vendor/autoload.php vendor/composer/installed.json config/database.php \
         config/env.php .env js/quaggaJS/server.pem js/quaggaJS/simple-https-server.py README.md install/index.php; do
  c=$(code "$p"); [ "$c" != 200 ] && pass "$p ($c)" || fail "$p is served (200)"
done
dc exec -T slims sh -c 'echo "<?php echo \"EXEC\".\"UTED\";" > /var/www/html/files/zz-probe.php; echo "<?php echo \"EXEC\".\"UTED\";" > /var/www/html/images/zz-probe.php'
for p in files/zz-probe.php images/zz-probe.php; do
  body=$(curl -s "$BASE/$p")
  case "$body" in *EXECUTED*) fail "PHP executed in $p" ;; *) pass "PHP not executed in $p ($(code "$p"))" ;; esac
done
dc exec -T slims rm -f /var/www/html/files/zz-probe.php /var/www/html/images/zz-probe.php

echo "-- smoke (176 URLs)"
"$TESTDIR/smoke.sh" "$BASE" "$OUT/cookies.txt" "$OUT/smoke.txt"
echo "      http codes: $(awk -F'|' '{print $1}' "$OUT/smoke.txt" | sort | uniq -c | tr -s ' ' | tr '\n' ' ')"
echo "      details: $OUT/smoke.txt"
check "smoke URLs checked" "$(wc -l < "$OUT/smoke.txt" | tr -d ' ')" 176

echo "-- persistence"
dc exec -T slims sh -c 'echo marker > /var/www/html/files/zz-persist.txt'
dc restart >/dev/null 2>&1; wait_up
check "OPAC after restart" "$(code index.php)" 200
dc up -d --no-build --force-recreate slims >/dev/null 2>&1; wait_up
check "OPAC after recreate" "$(code index.php)" 200
check "file kept after recreate" "$(dc exec -T slims cat /var/www/html/files/zz-persist.txt 2>/dev/null)" marker
"$TESTDIR/login.sh" "$BASE" "$ADMIN_PASS" "$OUT/cookies2.txt" > "$OUT/login2.txt"
check "admin login after recreate" "$(grep -c 'ADMIN_LOGGED_IN=yes' "$OUT/login2.txt")" 1
dc exec -T slims rm -f /var/www/html/files/zz-persist.txt

if [ "$KEEP" = 0 ]; then dc down -v >/dev/null 2>&1; else echo "stack kept: $BASE (project $PROJECT)"; fi
echo "== $PROJECT: $FAILS failed check(s)"
exit "$FAILS"
