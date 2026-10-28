#!/usr/bin/env bash
# Day-to-day operations for the SLiMS Docker Compose stack.
# Run ./slims.sh help for the command list.
set -eu

cd "$(dirname "$0")"

dc() { docker compose "$@"; }

http_url() {
    local port
    port=$(dc port slims 80 2>/dev/null | head -n1 | sed 's/.*://')
    echo "http://127.0.0.1:${port:-8881}/"
}

cmd_status() {
    dc ps
    echo ""
    local url
    url=$(http_url)
    printf 'HTTP %s -> ' "$url"
    curl -s -o /dev/null -w '%{http_code}\n' --max-time 10 "$url" || echo "no response"
    echo ""
    docker stats --no-stream --format 'table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}' \
        $(dc ps -q)
    echo ""
    docker system df -v 2>/dev/null | grep -E "VOLUME NAME|_(dbdata|appfiles|appimages|apprepo) " || true
}

cmd_headers() {
    local url="${1:-$(http_url)}"
    echo "Security headers from $url"
    curl -sSI --max-time 10 "$url" | grep -iE \
        '^(strict-transport|referrer-policy|permissions-policy|x-frame|x-content-type|content-security|server|x-powered)'
}

# Uses the credentials inside the db container, so .env is not read here.
cmd_db() {
    dc exec db sh -c 'MYSQL_PWD="$MARIADB_PASSWORD" exec mariadb -u"$MARIADB_USER" "$MARIADB_DATABASE"'
}

cmd_backup() {
    mkdir -p backups
    chmod 700 backups
    local out
    out="backups/slims-$(date +%Y%m%d-%H%M%S).sql.gz"
    dc exec -T db sh -c 'MYSQL_PWD="$MARIADB_PASSWORD" exec mariadb-dump --no-tablespaces -u"$MARIADB_USER" "$MARIADB_DATABASE"' \
        | gzip >"$out"
    # A failed dump still leaves a gzip header; reject output without SQL.
    if ! gzip -dc "$out" | grep -q "CREATE TABLE"; then
        rm -f "$out"
        echo "ERROR: backup failed, see the error above." >&2
        exit 1
    fi
    chmod 600 "$out"
    echo "Backup written: $out ($(du -h "$out" | cut -f1))"
}

usage() {
    cat <<'EOF'
Usage: ./slims.sh <command> [args]

  status              Containers, health, HTTP check, CPU/memory, volume sizes
  start               Start all services
  stop                Stop all services (data is kept)
  restart [service]   Restart all services or one (slims, db, cloudflared)
  logs [service]      Follow the last 200 log lines (Ctrl+C to quit)
  db                  MariaDB console as the application user
  backup              Dump the database to backups/slims-<timestamp>.sql.gz
  shell [service]     Shell inside a container (default: slims)
  update              Rebuild the image and recreate containers (after git pull)
  headers [url]       Show security headers (default: local HTTP port)
EOF
}

case "${1:-help}" in
    status)  cmd_status ;;
    start)   dc up -d ;;
    stop)    dc stop ;;
    restart) shift; dc restart "$@" ;;
    logs)    shift; dc logs -f --tail=200 "$@" ;;
    db)      cmd_db ;;
    backup)  cmd_backup ;;
    shell)   dc exec "${2:-slims}" bash ;;
    update)  dc up -d --build && dc ps ;;
    headers) cmd_headers "${2:-}" ;;
    help|-h|--help) usage ;;
    *) usage; exit 1 ;;
esac
