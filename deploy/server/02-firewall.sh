#!/usr/bin/env bash
# 02 - Firewall UFW: SSH dan web hanya dari VPN, MariaDB hanya dari network Docker,
#      plus aturan DOCKER-USER untuk port yang di-publish Docker (Docker melewati UFW).
#
# Pakai: sudo ./02-firewall.sh             tambah/ubah aturan (aman diulang)
#        sudo ./02-firewall.sh --reset     hapus semua aturan UFW lama dulu
#        sudo ./02-firewall.sh --dry-run   periksa saja, tampilkan yang akan berubah
#                                          (alias --check; exit 0 sesuai, 2 ada perubahan)
source "$(dirname "$0")/lib.sh"
parse_args "$@"; need_root "$@"; load_env; check_ubuntu
RESET=0; has_arg --reset && RESET=1

# ------------------------------------------------------- pra-cek anti terkunci
step 'Memeriksa sesi SSH ini'
client="$(ssh_client_ip)"
if [[ -n "$client" ]]; then
  # shellcheck disable=SC2086
  if ip_in_cidrs "$client" $ADMIN_CIDRS; then ok "IP sesi ini $client termasuk ADMIN_CIDRS"
  else guard "IP sesi ini ($client) tidak termasuk ADMIN_CIDRS ($ADMIN_CIDRS). Firewall akan memutus Anda; perbaiki server.env."; fi
elif [[ $DRY_RUN -eq 1 ]]; then
  warn 'IP klien SSH tidak terdeteksi (konsol lokal?); pastikan ADMIN_CIDRS benar.'
else
  warn 'IP klien SSH tidak terdeteksi (konsol lokal?).'
  read -r -p '  Lanjutkan? Pastikan ADMIN_CIDRS benar. (y/N) ' a </dev/tty; [[ "$a" =~ ^[yY]$ ]] || exit 1
fi
warn "Hanya IP sesi ini yang bisa dicek. Bila ada jalur VPN lain (mis. OpenVPN dan FortiClient), pastikan subnetnya juga ada di ADMIN_CIDRS ($ADMIN_CIDRS)."
EXT="$(detect_ext_if)"
[[ -n "$EXT" ]] || die 'Interface publik tidak terdeteksi; isi EXT_IF di server.env.'
ok "interface publik: $EXT"

apt_install ufw iptables
have_ufw=0; command -v ufw >/dev/null && have_ufw=1

# ---------------------------------------------------------------- aturan UFW
step 'Aturan UFW'
# Aturan yang diinginkan, dalam format "ufw show added" (dipakai untuk cek).
web_from="$ADMIN_CIDRS $WEB_EXTRA_CIDRS"
want=()
for c in $ADMIN_CIDRS; do want+=("allow from $c to any port 22 proto tcp comment 'SSH dari VPN'"); done
if [[ "$WEB_ACCESS" == any ]]; then
  want+=("allow 80,443/tcp comment 'Web publik (WAF)'")
else
  for c in $web_from; do want+=("allow from $c to any port 80,443 proto tcp comment 'Web dari VPN (uji)'"); done
fi
want+=("allow from $DOCKER_CIDR to any port 3306 proto tcp comment 'MariaDB dari container'")

added=""; [[ $have_ufw -eq 1 ]] && added="$(ufw show added 2>/dev/null || true)"
if [[ $RESET -eq 1 ]]; then
  plan 'reset semua aturan UFW (cadangan ke /root/ufw-backup-<waktu>)'
  run cp -a /etc/ufw "/root/ufw-backup-$STAMP"
  runq ufw --force reset
  added=""
fi
policy() { grep -E "^DEFAULT_$1_POLICY=" /etc/default/ufw 2>/dev/null | cut -d'"' -f2 || true; }
for d in incoming:deny:INPUT:DROP outgoing:allow:OUTPUT:ACCEPT routed:deny:FORWARD:DROP; do
  IFS=: read -r dir act var want_pol <<<"$d"
  cur="$(policy "$var")"
  if [[ "$cur" == "$want_pol" ]]; then same "default $dir: $act"
  else plan "default $dir: $act (sekarang: ${cur:-belum ada})"; runq ufw default "$act" "$dir"; fi
done
if grep -qE '^LOGLEVEL=low' /etc/ufw/ufw.conf 2>/dev/null; then same 'logging low'
else plan 'logging UFW: low'; runq ufw logging low; fi
for r in "${want[@]}"; do
  key="${r%% comment*}"
  if grep -qF "ufw $key" <<<"$added"; then same "ufw $key"
  else plan "ufw $r"; eval "runq ufw $r"; fi
done
# Aturan web publik dari run sebelumnya dihapus bila kembali ke mode vpn.
if [[ "$WEB_ACCESS" == vpn ]] && grep -qF 'ufw allow 80,443/tcp' <<<"$added"; then
  plan 'hapus aturan web publik (WEB_ACCESS=vpn)'; runq ufw delete allow 80,443/tcp
fi
# Aturan lain yang membuka SSH/3306 untuk semua orang (mis. "ufw allow 22/tcp"
# atau "ufw allow OpenSSH") dihapus: akses SSH sudah dijamin aturan VPN di atas.
while IFS= read -r line; do
  [[ -z "$line" ]] && continue
  plan "hapus aturan terbuka untuk semua: $line"
  eval "runq ufw delete ${line#ufw }"
done < <(grep -E "^ufw allow (in )?(22|22/tcp|OpenSSH|3306|3306/tcp)( comment .*)?$" <<<"$added" || true)

# ------------------------------------------------- DOCKER-USER (port Docker)
step 'Aturan DOCKER-USER untuk port container'
# Port yang di-publish Docker diteruskan lewat chain FORWARD, sehingga aturan UFW
# di atas tidak berlaku untuknya. Blok ini dimuat UFW ke chain DOCKER-USER:
# dari interface publik, hanya 80/443 (port WAF) yang boleh, dan pada mode vpn
# hanya dari subnet uji. Port container lain (8080 EPrints, 8881 SLiMS) ditolak.
block="$(
  echo '# BEGIN deploy-server DOCKER-USER (dibuat oleh 02-firewall.sh, jangan diedit manual)'
  echo '*filter'
  echo ':DOCKER-USER - [0:0]'
  echo '-A DOCKER-USER -m conntrack --ctstate RELATED,ESTABLISHED -j RETURN'
  echo "-A DOCKER-USER ! -i $EXT -j RETURN"
  if [[ "$WEB_ACCESS" == any ]]; then
    for p in 80 443; do echo "-A DOCKER-USER -i $EXT -p tcp -m conntrack --ctorigdstport $p --ctdir ORIGINAL -j RETURN"; done
  else
    for c in $web_from; do for p in 80 443; do
      echo "-A DOCKER-USER -i $EXT -s $c -p tcp -m conntrack --ctorigdstport $p --ctdir ORIGINAL -j RETURN"
    done; done
  fi
  echo "-A DOCKER-USER -i $EXT -j DROP"
  echo 'COMMIT'
  echo '# END deploy-server DOCKER-USER'
)"
rules=/etc/ufw/after.rules
if [[ -f "$rules" ]]; then
  { sed '/^# BEGIN deploy-server DOCKER-USER/,/^# END deploy-server DOCKER-USER/d' "$rules"; echo "$block"; } \
    | write_file "$rules" 0640
else
  plan "$rules belum ada (ufw belum terpasang); blok DOCKER-USER akan ditambahkan"
  WF_CHANGED=1
fi
rules_changed=$WF_CHANGED

# ------------------------------------------------------------------ aktifkan
step 'Mengaktifkan UFW'
if [[ $have_ufw -eq 1 ]] && ufw status 2>/dev/null | grep -q '^Status: active'; then
  same 'UFW aktif'
  [[ $rules_changed -eq 1 || $RESET -eq 1 ]] && runq ufw reload
else
  plan 'aktifkan UFW (dan saat boot)'
  runq ufw --force enable
  runq systemctl enable ufw
fi
# ufw reload/enable mengosongkan DOCKER-USER, termasuk ban fail2ban di sana.
if [[ $DRY_RUN -eq 0 ]] && systemctl is-active --quiet fail2ban; then
  systemctl restart fail2ban; ok 'fail2ban dimulai ulang (ban dipasang lagi)'
fi
if [[ $DRY_RUN -eq 1 ]]; then
  echo '  DOCKER-USER sekarang:'; iptables -S DOCKER-USER 2>/dev/null | sed 's/^/      /' || echo '      (belum ada)'
else
  ufw status verbose; echo; iptables -S DOCKER-USER; echo
fi
finish
warn 'Uji SSH dari terminal BARU sebelum menutup sesi ini.'
echo '  Saat rilis publik: ubah WEB_ACCESS="any" di server.env lalu jalankan ulang script ini.'
echo '  Berikutnya: sudo ./03-mariadb-install.sh'
