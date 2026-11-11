#!/usr/bin/env bash
# SLiMS production dengan Docker: MariaDB di host, network slims-net, container
# di-hardening dan dibatasi resource-nya (lihat compose.yaml).
#
# Pakai: ./slims-prod.sh check       periksa saja, tidak mengubah apa pun (default;
#                                    alias --dry-run; exit 0 sesuai, 2 ada perubahan,
#                                    1 pra-syarat gagal)
#        sudo ./slims-prod.sh init-env [FILE]   buat .env dari .env.example + kredensial
#                                    (FILE default: DB_CRED_FILE atau /root/db-credentials.env)
#        ./slims-prod.sh up [--build]   buat network, jalankan container (image di-build
#                                    bila belum ada; --build untuk build ulang)
#        ./slims-prod.sh down        hentikan container (volume dan network tetap)
#        ./slims-prod.sh status|logs
set -Eeuo pipefail
cd "$(dirname "$0")"
ENV_FILE="$PWD/.env"
CRED="${DB_CRED_FILE:-/root/db-credentials.env}"

if [[ -t 1 ]]; then B=$'\e[1m'; G=$'\e[32m'; Y=$'\e[33m'; R=$'\e[31m'; C=$'\e[36m'; N=$'\e[0m'; else B=''; G=''; Y=''; R=''; C=''; N=''; fi
step() { printf '\n%s==> %s%s\n' "$B$C" "$*" "$N"; }
ok()   { printf '  %s[ok]%s %s\n' "$G" "$N" "$*"; }
same() { printf '  %s[sesuai]%s %s\n' "$G" "$N" "$*"; }
plan() { printf '  %s[akan]%s %s\n' "$Y" "$N" "$*"; CHANGES=$((CHANGES + 1)); }
warn() { printf '  %s[!]%s %s\n' "$Y" "$N" "$*" >&2; }
fail() { printf '  %s[gagal]%s %s\n' "$R" "$N" "$*" >&2; FAILS=$((FAILS + 1)); }
die()  { printf '%s[gagal]%s %s\n' "$R" "$N" "$*" >&2; exit 1; }
CHANGES=0; FAILS=0

cmd="${1:-check}"; [[ "$cmd" == --dry-run || "$cmd" == --check ]] && cmd=check
BUILD=0; [[ "${2:-}" == --build ]] && BUILD=1
TMPE="$(mktemp)"; trap 'rm -f "$TMPE"' EXIT

load_env() {
  [[ -f "$ENV_FILE" ]] || return 1
  set -a
  # shellcheck disable=SC1090,SC1091
  source "$ENV_FILE"
  set +a
  : "${DB_HOST:=192.168.121.33}" "${DB_PORT:=3306}" "${DB_NAME:=senayan}" "${DB_USER:=slims}" "${DB_PASS:=}"
  : "${SLIMS_NETWORK:=slims-net}" "${SLIMS_SUBNET:=192.168.121.32/27}" "${SLIMS_GATEWAY:=192.168.121.33}"
  : "${SLIMS_BRIDGE:=br-slims}" "${SLIMS_IP_RANGE:=192.168.121.48/28}" "${SLIMS_IP:=192.168.121.35}" "${TRUSTED_PROXY:=192.168.121.34}" "${HTTP_BIND:=127.0.0.1}" "${HTTP_PORT:=8881}"
  : "${SLIMS_IMAGE:=slims-bulian:prod}" "${SLIMS_CONTAINER:=slims-app}"
}
dc() { docker compose -f compose.yaml --env-file "$ENV_FILE" "$@"; }
SCHEMA=../../install/senayan.sql
# Login dari host ke IP gateway: sumber koneksinya IP gateway itu sendiri, sehingga
# cocok dengan host user aplikasi (192.168.121.%), sama seperti dari container.
db() { MYSQL_PWD="$DB_PASS" mariadb -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$@"; }
db_tables() { db -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME'" 2>"$TMPE"; }
in_cidr() { python3 -c 'import ipaddress,sys; sys.exit(0 if ipaddress.ip_address(sys.argv[1]) in ipaddress.ip_network(sys.argv[2], strict=False) else 1)' "$1" "$2"; }

# ------------------------------------------------------------------ init-env --
init_env() {
  [[ -f "$ENV_FILE" ]] && die ".env sudah ada; tidak diubah. Hapus dulu bila ingin membuat ulang."
  [[ -n "${2:-}" ]] && CRED="$2"
  [[ $EUID -eq 0 ]] || die "Jalankan dengan sudo (membaca $CRED)."
  [[ -r "$CRED" ]] || die "$CRED tidak ada. Jalankan deploy/server/04-mariadb-config.sh dulu."
  local pass name user
  pass="$(sed -n 's/^SLIMS_DB_PASS=//p' "$CRED" | tail -n1)"
  name="$(sed -n 's/^SLIMS_DB_NAME=//p' "$CRED" | tail -n1)"
  user="$(sed -n 's/^SLIMS_DB_USER=//p' "$CRED" | tail -n1)"
  [[ -n "$pass" ]] || die "SLIMS_DB_PASS tidak ditemukan di $CRED."
  umask 077
  sed -e "s|^DB_PASS=.*|DB_PASS=$pass|" -e "s|^DB_NAME=.*|DB_NAME=${name:-senayan}|" \
      -e "s|^DB_USER=.*|DB_USER=${user:-slims}|" .env.example >"$ENV_FILE"
  [[ -n "${SUDO_USER:-}" ]] && chown "$SUDO_USER" "$ENV_FILE"
  chmod 600 "$ENV_FILE"
  ok ".env dibuat dari .env.example, DB_* diambil dari $CRED (chmod 600)"
}

# --------------------------------------------------------------------- check --
check() {
  step 'Docker'
  command -v docker >/dev/null || { fail 'docker tidak ditemukan'; return; }
  docker info >/dev/null 2>&1 || { fail 'docker daemon tidak bisa dihubungi (jalankan dengan sudo / user di grup docker?)'; return; }
  docker compose version >/dev/null 2>&1 || { fail 'plugin docker compose belum terpasang'; return; }
  ok "$(docker --version), $(docker compose version --short 2>/dev/null)"

  step '.env'
  if ! load_env; then fail ".env belum ada. Jalankan: sudo ./slims-prod.sh init-env"; return; fi
  local perm; perm="$(stat -c '%a' "$ENV_FILE")"
  if [[ "$perm" == 600 || "$perm" == 400 ]]; then same ".env chmod $perm"; else warn ".env chmod $perm; sebaiknya 600 (berisi password)."; fi
  if [[ -n "$DB_PASS" ]]; then same 'DB_PASS terisi'; else fail 'DB_PASS kosong'; fi
  in_cidr "$SLIMS_GATEWAY" "$SLIMS_SUBNET" || fail "SLIMS_GATEWAY $SLIMS_GATEWAY di luar SLIMS_SUBNET $SLIMS_SUBNET"
  in_cidr "$TRUSTED_PROXY" "$SLIMS_SUBNET" || fail "TRUSTED_PROXY $TRUSTED_PROXY di luar SLIMS_SUBNET $SLIMS_SUBNET"
  in_cidr "$SLIMS_IP" "$SLIMS_SUBNET" || fail "SLIMS_IP $SLIMS_IP di luar SLIMS_SUBNET $SLIMS_SUBNET"
  for ip in "$SLIMS_GATEWAY" "$TRUSTED_PROXY" "$SLIMS_IP"; do
    in_cidr "$ip" "$SLIMS_IP_RANGE" && fail "$ip berada di SLIMS_IP_RANGE $SLIMS_IP_RANGE (rentang IP otomatis); pilih IP di luar rentang itu"
  done
  [[ "$(printf '%s\n' "$SLIMS_GATEWAY" "$TRUSTED_PROXY" "$SLIMS_IP" | sort -u | wc -l)" -eq 3 ]] \
    || fail 'SLIMS_GATEWAY, TRUSTED_PROXY dan SLIMS_IP harus berbeda'
  [[ "$DB_HOST" == "$SLIMS_GATEWAY" ]] || warn "DB_HOST ($DB_HOST) bukan gateway slims-net ($SLIMS_GATEWAY)."
  [[ "$HTTP_BIND" == 127.0.0.1 ]] || warn "HTTP_BIND=$HTTP_BIND: port $HTTP_PORT terbuka di luar server (Docker melewati UFW)."
  if dc config -q 2>"$TMPE"; then same 'compose.yaml + .env valid'
  else fail "docker compose config gagal: $(head -n3 "$TMPE")"; fi

  step "Network $SLIMS_NETWORK"
  if docker network inspect "$SLIMS_NETWORK" >/dev/null 2>&1; then
    local sub gw br rng
    sub="$(docker network inspect -f '{{range .IPAM.Config}}{{.Subnet}}{{end}}' "$SLIMS_NETWORK")"
    gw="$(docker network inspect -f '{{range .IPAM.Config}}{{.Gateway}}{{end}}' "$SLIMS_NETWORK")"
    br="$(docker network inspect -f '{{index .Options "com.docker.network.bridge.name"}}' "$SLIMS_NETWORK")"
    rng="$(docker network inspect -f '{{range .IPAM.Config}}{{.IPRange}}{{end}}' "$SLIMS_NETWORK" | grep -v invalid || true)"
    if [[ "$sub" == "$SLIMS_SUBNET" && "$gw" == "$SLIMS_GATEWAY" ]]; then same "$SLIMS_NETWORK $sub gw $gw bridge ${br:-?}"
    else fail "$SLIMS_NETWORK sudah ada dengan subnet $sub gw $gw (diharapkan $SLIMS_SUBNET gw $SLIMS_GATEWAY). Hapus/ubah manual."; fi
    [[ "$rng" == "$SLIMS_IP_RANGE" ]] || warn "ip-range $SLIMS_NETWORK: ${rng:-tidak diset} (diharapkan $SLIMS_IP_RANGE); container lain bisa mengambil IP tetap WAF."
    local holder
    holder="$(docker network inspect -f '{{range .Containers}}{{.Name}}={{.IPv4Address}} {{end}}' "$SLIMS_NETWORK" | tr ' ' '\n' \
      | grep -F "=$SLIMS_IP/" | cut -d= -f1 || true)"
    [[ -z "$holder" || "$holder" == "$SLIMS_CONTAINER" ]] || fail "IP $SLIMS_IP sudah dipakai container $holder"
  else
    local clash
    clash="$(docker network ls -q | xargs -r docker network inspect -f '{{.Name}} {{range .IPAM.Config}}{{.Subnet}} {{end}}' \
      | python3 -c 'import ipaddress,sys
want=ipaddress.ip_network(sys.argv[1])
for line in sys.stdin:
    name,*subs=line.split()
    for s in subs:
        if ipaddress.ip_network(s,strict=False).overlaps(want): print(name, s)' "$SLIMS_SUBNET")"
    if [[ -n "$clash" ]]; then fail "Subnet $SLIMS_SUBNET bentrok dengan network lain: $clash"
    else plan "buat network $SLIMS_NETWORK $SLIMS_SUBNET gw $SLIMS_GATEWAY ip-range $SLIMS_IP_RANGE bridge $SLIMS_BRIDGE"; fi
  fi

  step "MariaDB di host ($DB_HOST:$DB_PORT)"
  if ss -ltn 2>/dev/null | awk '{print $4}' | grep -qE "^($DB_HOST|0\.0\.0\.0|\*):$DB_PORT$"; then
    same "MariaDB mendengarkan $DB_HOST:$DB_PORT"
  else
    fail "Tidak ada yang mendengarkan $DB_HOST:$DB_PORT. Jalankan deploy/server/04-mariadb-config.sh (DB_BIND=docker-gw)."
  fi
  if ! command -v mariadb >/dev/null; then
    warn 'client mariadb tidak ada di host; login DB tidak diuji.'
  elif ! ip -o -4 addr show | grep -q " $DB_HOST/"; then
    warn "Login DB diuji saat up: $DB_HOST baru ada setelah network $SLIMS_NETWORK dibuat."
  else
    local n
    if n="$(db_tables)"; then
      same "login $DB_USER@$DB_HOST ke $DB_NAME berhasil"
      if [[ "$n" -gt 0 ]]; then same "skema SLiMS: $n tabel"
      else plan "muat skema $SCHEMA ke $DB_NAME (database masih kosong)"; fi
    else
      fail "login $DB_USER@$DB_HOST gagal: $(head -n1 "$TMPE")"
    fi
  fi

  step 'Image dan container'
  if docker image inspect "$SLIMS_IMAGE" >/dev/null 2>&1; then same "image $SLIMS_IMAGE ada ($(docker image inspect -f '{{.Created}}' "$SLIMS_IMAGE" | cut -c1-19))"
  else plan "build image $SLIMS_IMAGE (beberapa menit)"; fi
  if docker container inspect "$SLIMS_CONTAINER" >/dev/null 2>&1; then
    local st; st="$(docker inspect -f '{{.State.Status}} {{if .State.Health}}{{.State.Health.Status}}{{end}}' "$SLIMS_CONTAINER")"
    same "container $SLIMS_CONTAINER: $st"
    if docker compose version --short 2>/dev/null | awk -F. '{exit !($1>2 || ($1==2 && $2>=22))}'; then
      local dry; dry="$(dc up -d --no-build --dry-run 2>&1 | grep -E 'Recreate|Create|Start' | grep -v 'Running' || true)"
      [[ -n "$dry" ]] && plan "compose akan: $(tr '\n' ' ' <<<"$dry")"
    fi
  else
    plan "jalankan container $SLIMS_CONTAINER"
  fi
  echo "  Batasan: CPU ${SLIMS_CPUS:-2}, RAM ${SLIMS_MEMORY:-1g}, PIDs ${SLIMS_PIDS:-512}, cap_drop ALL, no-new-privileges"
  echo "  IP container: $SLIMS_IP (WAF: $TRUSTED_PROXY), port uji: ${HTTP_BIND}:${HTTP_PORT} -> 80"
}

summary() {
  echo
  if [[ $FAILS -gt 0 ]]; then printf '%s%d pra-syarat gagal.%s\n' "$R" "$FAILS" "$N"; return 1
  elif [[ $CHANGES -gt 0 ]]; then printf '%s%d perubahan akan dilakukan oleh "./slims-prod.sh up".%s\n' "$Y" "$CHANGES" "$N"; return 2
  else printf '%sSemua sesuai.%s\n' "$G" "$N"; return 0; fi
}

# ------------------------------------------------------------------------ up --
up() {
  check || true
  [[ $FAILS -eq 0 ]] || { summary || true; die 'Perbaiki pra-syarat di atas dulu.'; }
  step 'Menerapkan'
  if ! docker network inspect "$SLIMS_NETWORK" >/dev/null 2>&1; then
    docker network create --driver bridge --subnet "$SLIMS_SUBNET" --gateway "$SLIMS_GATEWAY" --ip-range "$SLIMS_IP_RANGE" \
      -o "com.docker.network.bridge.name=$SLIMS_BRIDGE" "$SLIMS_NETWORK" >/dev/null
    ok "network $SLIMS_NETWORK dibuat"
  fi
  if command -v mariadb >/dev/null; then
    local n
    n="$(db_tables)" || die "login $DB_USER@$DB_HOST gagal: $(head -n1 "$TMPE")"
    if [[ "$n" -eq 0 ]]; then
      db "$DB_NAME" <"$SCHEMA" || die "gagal memuat $SCHEMA"
      ok "skema SLiMS dimuat ke $DB_NAME ($(db_tables) tabel)"
    fi
  else
    warn 'client mariadb tidak ada; pastikan skema SLiMS sudah dimuat (deploy/server/04 dengan SLIMS_SQL).'
  fi
  if [[ $BUILD -eq 1 ]] || ! docker image inspect "$SLIMS_IMAGE" >/dev/null 2>&1; then dc build; fi
  dc up -d --no-build
  printf '  Menunggu container healthy'
  local st=''
  for _ in $(seq 1 60); do
    st="$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}{{.State.Status}}{{end}}' "$SLIMS_CONTAINER" 2>/dev/null || true)"
    [[ "$st" == healthy ]] && break
    [[ "$st" == exited ]] && break
    printf '.'; sleep 3
  done
  echo
  if [[ "$st" == healthy ]]; then ok "$SLIMS_CONTAINER healthy"
  else docker logs --tail 40 "$SLIMS_CONTAINER" >&2 || true; die "$SLIMS_CONTAINER tidak healthy (status: $st)."; fi
  docker logs "$SLIMS_CONTAINER" 2>&1 | grep -A3 'ADMIN CREDENTIALS' || echo '  Password admin sudah pernah dibuat sebelumnya.'
  echo "  Uji: curl -I http://$HTTP_BIND:$HTTP_PORT/"
}

case "$cmd" in
  check)    check || true; summary ;;
  init-env) init_env "$@" ;;
  up)       up ;;
  down)     load_env || die '.env belum ada.'; dc down ;;
  status)   load_env || die '.env belum ada.'; dc ps; docker stats --no-stream "$SLIMS_CONTAINER" 2>/dev/null || true ;;
  logs)     load_env || die '.env belum ada.'; dc logs --tail 100 -f ;;
  *) sed -n '2,14p' "$0"; exit 1 ;;
esac
