# Fungsi bersama untuk script setup server. Di-source oleh 01-05, bukan dijalankan.
# shellcheck shell=bash

set -Eeuo pipefail
umask 022   # berkas konfigurasi tidak boleh world-writable (MariaDB menolaknya)
export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_MODE=a

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
STAMP="$(date +%Y%m%d-%H%M%S)"

if [[ -t 1 ]]; then C_B=$'\e[1m'; C_G=$'\e[32m'; C_Y=$'\e[33m'; C_R=$'\e[31m'; C_0=$'\e[0m'
else C_B=''; C_G=''; C_Y=''; C_R=''; C_0=''; fi

step() { printf '\n%s==> %s%s\n' "$C_B" "$*" "$C_0"; }
ok()   { printf '  %s[ok]%s %s\n' "$C_G" "$C_0" "$*"; }
warn() { printf '  %s[!]%s %s\n' "$C_Y" "$C_0" "$*" >&2; }
die()  { printf '%s[gagal]%s %s\n' "$C_R" "$C_0" "$*" >&2; exit 1; }
trap 'die "Berhenti di baris $LINENO: $BASH_COMMAND"' ERR

need_root() { [[ $EUID -eq 0 ]] || die "Jalankan dengan sudo: sudo $0 $*"; }

# ------------------------------------------------------------ mode dry-run ---
# --dry-run (alias --check, -n): tidak mengubah apa pun. Setiap berkas
# dibandingkan dengan isinya sekarang (diff), paket/aturan/user yang belum ada
# dilaporkan, dan perintah yang akan dijalankan dicetak.
# Exit code dry-run: 0 = sudah sesuai, 2 = ada yang akan berubah,
# 1 = pra-syarat gagal (run sungguhan akan berhenti).
DRY_RUN=0; CHANGES=0; GUARD_FAIL=0; ARGS=()
parse_args() {
  local a
  for a in "$@"; do
    case "$a" in
      --dry-run|--check|-n) DRY_RUN=1 ;;
      -h|--help) sed -n '2,/^[^#]/{/^#/s/^# \{0,1\}//p}' "$0"; exit 0 ;;
      *) ARGS+=("$a") ;;
    esac
  done
  if [[ $DRY_RUN -eq 1 ]]; then
    printf '%sMODE DRY-RUN: hanya memeriksa, tidak ada yang diubah.%s\n' "$C_B$C_Y" "$C_0"
  fi
  return 0
}
has_arg() { local x; for x in "${ARGS[@]}"; do [[ "$x" == "$1" ]] && return 0; done; return 1; }

plan() {
  CHANGES=$((CHANGES + 1))
  if [[ $DRY_RUN -eq 1 ]]; then printf '  %s[akan]%s %s\n' "$C_Y" "$C_0" "$*"
  else printf '  %s[ubah]%s %s\n' "$C_G" "$C_0" "$*"; fi
}
same() { printf '  %s[sesuai]%s %s\n' "$C_G" "$C_0" "$*"; }

# Pra-syarat: run sungguhan berhenti; dry-run mencatat lalu lanjut memeriksa.
guard() {
  if [[ $DRY_RUN -eq 1 ]]; then
    GUARD_FAIL=1; printf '  %s[akan gagal]%s %s\n' "$C_R" "$C_0" "$*" >&2
  else die "$*"; fi
}

# run CMD...: jalankan, atau cetak saja pada dry-run. runq = sama, output dibuang.
run()  { if [[ $DRY_RUN -eq 1 ]]; then printf '  %s[perintah]%s %s\n' "$C_Y" "$C_0" "$*"; else "$@"; fi; }
runq() { if [[ $DRY_RUN -eq 1 ]]; then printf '  %s[perintah]%s %s\n' "$C_Y" "$C_0" "$*"; else "$@" >/dev/null; fi; }

# write_file PATH [MODE]: isi dari stdin. Bila sama dengan yang ada: tidak disentuh.
# WF_CHANGED=1 bila berkas berubah (atau akan berubah pada dry-run).
WF_CHANGED=0
write_file() {
  local path="$1" mode="${2:-0644}" tmp
  tmp="$(mktemp)"; cat >"$tmp"; WF_CHANGED=0
  if [[ -f "$path" ]] && cmp -s "$tmp" "$path"; then
    same "$path"; rm -f "$tmp"; return 0
  fi
  WF_CHANGED=1
  if [[ $DRY_RUN -eq 1 ]]; then
    if [[ -f "$path" ]]; then plan "ubah $path:"; diff -u "$path" "$tmp" | tail -n +3 | sed 's/^/      /' || true
    else plan "buat $path:"; sed 's/^/      + /' "$tmp"; fi
    rm -f "$tmp"; return 0
  fi
  [[ -f "$path" ]] && cp -a "$path" "$path.bak-$STAMP"
  mkdir -p "$(dirname "$path")"
  install -m "$mode" "$tmp" "$path"; rm -f "$tmp"
  ok "ditulis: $path"
}

# Ringkasan di akhir setiap script.
finish() {
  [[ $DRY_RUN -eq 1 ]] || return 0
  step 'Ringkasan dry-run'
  if [[ $GUARD_FAIL -eq 1 ]]; then
    warn 'Ada pra-syarat yang gagal (lihat [akan gagal]); run sungguhan akan berhenti.'; exit 1
  elif [[ $CHANGES -eq 0 ]]; then
    ok 'Sudah sesuai; tidak ada yang akan diubah.'; exit 0
  else
    warn "$CHANGES perubahan akan dilakukan. Jalankan tanpa --dry-run untuk menerapkan."; exit 2
  fi
}

pkg_installed() { dpkg-query -W -f='${Status}' "$1" 2>/dev/null | grep -q 'ok installed'; }

load_env() {
  local f="$HERE/server.env"
  [[ -f "$f" ]] || die "server.env belum ada. Jalankan: cp $HERE/server.env.example $f lalu sesuaikan."
  # shellcheck source=server.env.example
  source "$f"
  : "${ADMIN_CIDRS:?ADMIN_CIDRS wajib diisi di server.env}"
  : "${SSH_ALLOW_USERS:=}" "${SSH_ALLOW_GROUPS:=}"
  [[ -n "$SSH_ALLOW_USERS$SSH_ALLOW_GROUPS" ]] || die "Isi SSH_ALLOW_USERS dan/atau SSH_ALLOW_GROUPS di server.env."
  : "${SSH_PERMIT_ROOT:=no}" "${TIMEZONE:=Asia/Jakarta}" "${WEB_ACCESS:=vpn}" "${WEB_EXTRA_CIDRS:=}"
  : "${EXT_IF:=}" "${DOCKER_CIDR:=192.168.120.0/23}" "${DB_BUFFER_POOL:=}" "${DB_APP_HOST:=192.168.121.%}"
  : "${EPRINTS_DB_NAME:=eprints}" "${EPRINTS_DB_USER:=eprints}" "${SLIMS_DB_NAME:=senayan}" "${SLIMS_DB_USER:=slims}"
  : "${SLIMS_SQL:=}" "${DB_BACKUP_DIR:=/var/backups/mariadb}" "${DB_BACKUP_KEEP_DAYS:=14}" "${DB_BACKUP_TIME:=01:30}"
  : "${WAF_LOG_DIR:=/var/log/waf}" "${WAF_LOG_OWNER:=101:101}" "${F2B_BANTIME:=1h}" "${F2B_FINDTIME:=10m}"
  local c
  for c in $ADMIN_CIDRS $WEB_EXTRA_CIDRS $DOCKER_CIDR; do
    valid_cidr "$c" || die "CIDR tidak valid di server.env: $c"
  done
  [[ "$WEB_ACCESS" == vpn || "$WEB_ACCESS" == any ]] || die "WEB_ACCESS harus vpn atau any."
  [[ "$SSH_PERMIT_ROOT" == no || "$SSH_PERMIT_ROOT" == prohibit-password ]] \
    || die "SSH_PERMIT_ROOT harus no atau prohibit-password."
}

valid_cidr() { python3 -c 'import ipaddress,sys; ipaddress.ip_network(sys.argv[1], strict=False)' "$1" 2>/dev/null; }

# ip_in_cidrs IP CIDR... -> sukses bila IP berada di salah satu CIDR.
ip_in_cidrs() {
  python3 - "$@" <<'PY'
import ipaddress, sys
ip = ipaddress.ip_address(sys.argv[1])
sys.exit(0 if any(ip in ipaddress.ip_network(c, strict=False) for c in sys.argv[2:]) else 1)
PY
}

# IP klien SSH sesi ini (juga bekerja di bawah sudo, lewat `who -m`).
ssh_client_ip() {
  if [[ -n "${SSH_CONNECTION:-}" ]]; then echo "${SSH_CONNECTION%% *}"; return; fi
  who -m 2>/dev/null | sed -n 's/.*(\([0-9a-fA-F.:]*\)).*/\1/p' | head -n1
}

# User yang login (bukan root hasil sudo).
login_user() { echo "${SUDO_USER:-$(logname 2>/dev/null || id -un)}"; }

detect_ext_if() {
  [[ -n "$EXT_IF" ]] && { echo "$EXT_IF"; return; }
  ip -o route get 1.1.1.1 2>/dev/null | sed -n 's/.* dev \([^ ]*\).*/\1/p'
}

# Simpan salinan berkas sebelum diubah (sekali per run).
backup_file() {
  [[ $DRY_RUN -eq 1 || ! -e "$1" ]] && return 0
  cp -a "$1" "$1.bak-$STAMP" && ok "cadangan: $1.bak-$STAMP"
}

# apt-get tanpa banjir output; log lengkap ditampilkan bila gagal.
APT_LOG=/var/log/deploy-server-apt.log
apt_q() {
  apt-get -y -q -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold "$@" >>"$APT_LOG" 2>&1 \
    || { tail -n 25 "$APT_LOG" >&2; die "apt-get $1 gagal (log: $APT_LOG)"; }
}
apt_install() {
  if [[ $DRY_RUN -eq 1 ]]; then
    local p missing=()
    for p in "$@"; do pkg_installed "$p" || missing+=("$p"); done
    if [[ ${#missing[@]} -gt 0 ]]; then plan "install paket: ${missing[*]}"; else same "paket terpasang: $*"; fi
    return 0
  fi
  apt_q install "$@"
}
apt_upgrade() {
  if [[ $DRY_RUN -eq 1 ]]; then
    local n; n=$(apt-get -s -o Debug::NoLocking=1 full-upgrade 2>/dev/null | grep -c '^Inst' || true)
    if [[ $n -gt 0 ]]; then plan "upgrade $n paket (menurut daftar paket terakhir)"; else same 'tidak ada paket untuk di-upgrade'; fi
    return 0
  fi
  apt_q update; apt_q full-upgrade
}

check_ubuntu() {
  # shellcheck source=/dev/null
  . /etc/os-release
  [[ "${ID:-}" == ubuntu ]] || die "Script ini untuk Ubuntu (terdeteksi: ${ID:-?})."
  [[ "${VERSION_ID:-}" == 26.04 ]] || warn "Dirancang untuk Ubuntu 26.04; server ini ${VERSION_ID:-?}."
}

# Password acak huruf+angka (tanpa pipe dari /dev/urandom yang memicu SIGPIPE di pipefail).
rand_pass() {
  python3 -c 'import secrets, string, sys; a = string.ascii_letters + string.digits; print("".join(secrets.choice(a) for _ in range(int(sys.argv[1]))))' "${1:-24}"
}
