#!/usr/bin/env bash
# 05 - Fail2ban: SSH (journal) dan log WAF (ModSecurity, rate limit, bot scan).
#      F2B_SCOPE=waf: hanya menambah jail WAF, bila fail2ban sudah dikelola Tim Infra.
#      Ban untuk web dipasang di chain DOCKER-USER, karena trafik ke container
#      WAF tidak melewati INPUT. IP VPN dan network Docker tidak pernah di-ban.
#
# Pakai: sudo ./05-fail2ban.sh             terapkan
#        sudo ./05-fail2ban.sh --dry-run   periksa saja (alias --check;
#                                          exit 0 sesuai, 2 ada perubahan)
source "$(dirname "$0")/lib.sh"
parse_args "$@"; need_root "$@"; load_env
f2b_changed=0

step 'Install fail2ban'
apt_install fail2ban python3-systemd iptables logrotate
command -v fail2ban-client >/dev/null && ok "$(fail2ban-client --version 2>/dev/null | head -n1)"

# ------------------------------------------------------------- folder log WAF ---
step "Folder log WAF ($WAF_LOG_DIR)"
# Container WAF menulis ke sini lewat bind mount (/var/log/waf). Berkas dibuat
# sekarang agar jail bisa start sebelum WAF berjalan.
for f in access.log error.log modsec_audit.json; do
  if [[ -f "$WAF_LOG_DIR/$f" ]]; then same "$WAF_LOG_DIR/$f"; else plan "buat $WAF_LOG_DIR/$f"; fi
done
if [[ -d "$WAF_LOG_DIR" && "$(stat -c '%u:%g' "$WAF_LOG_DIR")" != "$WAF_LOG_OWNER" ]]; then
  plan "pemilik $WAF_LOG_DIR menjadi $WAF_LOG_OWNER (sekarang $(stat -c '%u:%g' "$WAF_LOG_DIR"))"
fi
if [[ $DRY_RUN -eq 0 ]]; then
  mkdir -p "$WAF_LOG_DIR"
  touch "$WAF_LOG_DIR/access.log" "$WAF_LOG_DIR/error.log" "$WAF_LOG_DIR/modsec_audit.json"
  chown -R "$WAF_LOG_OWNER" "$WAF_LOG_DIR"
  chmod 750 "$WAF_LOG_DIR"; chmod 640 "$WAF_LOG_DIR"/*
fi
write_file /etc/logrotate.d/waf <<CONF
$WAF_LOG_DIR/*.log $WAF_LOG_DIR/*.json {
    daily
    rotate 14
    compress
    delaycompress
    missingok
    notifempty
    copytruncate
}
CONF
echo "  Pemilik log: $WAF_LOG_OWNER (user nginx di image WAF), rotasi harian 14 hari"

# DOCKER-USER biasanya dibuat Docker/UFW (02-firewall.sh). Pastikan ada agar
# aksi fail2ban tidak gagal saat Docker belum terpasang.
if iptables -n -L DOCKER-USER >/dev/null 2>&1; then same 'chain DOCKER-USER ada'
else plan 'buat chain DOCKER-USER (Docker/UFW belum membuatnya)'; runq iptables -N DOCKER-USER; fi

# ------------------------------------------------------------------- filter ---
step 'Filter'
write_file /etc/fail2ban/filter.d/waf-modsec.conf <<'CONF'
# Blokir ModSecurity v3 di error.log Nginx (container owasp/modsecurity-crs).
# Hanya cocok saat MODSEC_RULE_ENGINE=On; mode DetectionOnly menulis "Warning".
[Definition]
failregex = \[client <HOST>\] ModSecurity: Access denied with code \d+
ignoreregex =
CONF
[[ $WF_CHANGED -eq 1 ]] && f2b_changed=1
echo '  Filter bawaan yang juga dipakai: sshd, nginx-limit-req, nginx-botsearch, recidive'

# --------------------------------------------------------------------- jail ---
step "Jail (F2B_SCOPE=$F2B_SCOPE)"
ignore="$(xargs <<<"127.0.0.1/8 ::1 $ADMIN_CIDRS $DOCKER_CIDR")"
# Setelan umum: di [DEFAULT] bila server ini dikelola penuh oleh script, atau di
# setiap jail WAF bila fail2ban milik Tim Infra (F2B_SCOPE=waf) agar [DEFAULT],
# jail sshd, dan recidive mereka tidak berubah.
common="bantime  = $F2B_BANTIME
findtime = $F2B_FINDTIME
# IP VPN Tim Infrastruktur dan network Docker tidak pernah di-ban.
ignoreip = $ignore
bantime.increment = true
bantime.maxtime   = 1w"
if [[ "$F2B_SCOPE" == waf ]]; then
  head="# Dibuat oleh deploy/server/05-fail2ban.sh (F2B_SCOPE=waf: hanya jail WAF)"
  per_jail="$common"$'\n'
  jail_file=/etc/fail2ban/jail.d/91-waf.local; other_file=/etc/fail2ban/jail.d/90-server.local
else
  head="# Dibuat oleh deploy/server/05-fail2ban.sh
[DEFAULT]
$common
maxretry = 5

[sshd]
enabled   = true
backend   = systemd
port      = 22
maxretry  = 4
banaction = ufw"
  per_jail=''
  jail_file=/etc/fail2ban/jail.d/90-server.local; other_file=/etc/fail2ban/jail.d/91-waf.local
fi
recidive=''
[[ "$F2B_SCOPE" == all ]] && recidive="
# Pelanggar berulang: blok total (host dan container) selama 1 minggu.
[recidive]
enabled  = true
backend  = auto
logpath  = /var/log/fail2ban.log
bantime  = 1w
findtime = 1d
maxretry = 3
action   = iptables-allports[name=recidive, chain=INPUT]
           iptables-allports[name=recidive-docker, chain=DOCKER-USER]"
write_file "$jail_file" <<CONF
$head

# ---- Log WAF (container). Ban dipasang di chain DOCKER-USER lewat opsi "chain"
# jail (bukan banaction=...[chain=...], karena action bawaan menimpanya dengan
# chain=INPUT). Port tujuan di DOCKER-USER sudah port container (8080/8443),
# jadi semua port diblokir (allports) agar tidak salah port.
[waf-modsec]
enabled   = true
filter    = waf-modsec
backend   = auto
logpath   = $WAF_LOG_DIR/error.log
maxretry  = 5
chain     = DOCKER-USER
banaction = iptables-allports
${per_jail}
[nginx-limit-req]
enabled   = true
backend   = auto
logpath   = $WAF_LOG_DIR/error.log
maxretry  = 10
chain     = DOCKER-USER
banaction = iptables-allports
${per_jail}
[nginx-botsearch]
enabled   = true
backend   = auto
logpath   = $WAF_LOG_DIR/access.log
maxretry  = 20
chain     = DOCKER-USER
banaction = iptables-allports
${per_jail}${recidive}
CONF
[[ $WF_CHANGED -eq 1 ]] && f2b_changed=1
if [[ -f "$other_file" ]]; then
  plan "hapus $other_file (dari mode F2B_SCOPE sebelumnya)"; runq rm -f "$other_file"; f2b_changed=1
fi
echo "  ignoreip: $ignore"
if [[ "$F2B_SCOPE" == waf ]]; then
  echo '  Jail milik Tim Infra (sshd, recidive, [DEFAULT]) tidak diubah. Jail aktif sekarang:'
  fail2ban-client status 2>/dev/null | sed -n 's/.*Jail list:[[:space:]]*/      /p' || true
fi

# ---------------------------------------------------------- validasi & start ---
step 'Validasi dan start'
if [[ $DRY_RUN -eq 1 ]]; then
  if systemctl is-active --quiet fail2ban 2>/dev/null; then
    same 'fail2ban berjalan'; fail2ban-client status 2>/dev/null | sed 's/^/      /' || true
    [[ $f2b_changed -eq 1 ]] && run fail2ban-client reload
  else
    plan 'jalankan fail2ban (dan saat boot)'
  fi
else
  fail2ban-client -t >/dev/null || die 'Konfigurasi fail2ban tidak valid (cek: fail2ban-client -t).'
  systemctl enable fail2ban >/dev/null 2>&1 || true
  if ! systemctl is-active --quiet fail2ban; then systemctl restart fail2ban; sleep 3
  elif [[ $f2b_changed -eq 1 ]]; then
    # reload memuat jail baru tanpa menghentikan jail lain (ban yang ada tetap).
    fail2ban-client reload >/dev/null; sleep 3
  fi
  fail2ban-client status
fi
finish
cat <<TXT

  Cek filter WAF setelah ada lalu lintas:
    sudo fail2ban-regex $WAF_LOG_DIR/error.log /etc/fail2ban/filter.d/waf-modsec.conf
  Status / lepas ban:
    sudo fail2ban-client status waf-modsec
    sudo fail2ban-client set waf-modsec unbanip <IP>
  Setelah "sudo ufw reload", jalankan "sudo systemctl restart fail2ban"
  (ufw reload mengosongkan chain DOCKER-USER beserta ban di dalamnya).
TXT
