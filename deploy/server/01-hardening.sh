#!/usr/bin/env bash
# 01 - Hardening Ubuntu 26.04: update otomatis, SSH key-only, sysctl, auditd, journald.
# Aman dijalankan ulang.
#
# Pakai: sudo ./01-hardening.sh             terapkan
#        sudo ./01-hardening.sh --dry-run   periksa saja, tampilkan yang akan berubah
#                                           (alias --check; exit 0 sesuai, 2 ada perubahan)
source "$(dirname "$0")/lib.sh"
parse_args "$@"; need_root "$@"; load_env; check_ubuntu
[[ -n "$SSH_ALLOW_USERS$SSH_ALLOW_GROUPS" ]] || die "Isi SSH_ALLOW_USERS dan/atau SSH_ALLOW_GROUPS di server.env."

# ------------------------------------------------------- pra-cek anti terkunci
step 'Memeriksa akses SSH sebelum mengubah apa pun'
conf=/etc/ssh/sshd_config.d/00-hardening.conf
allow="$SSH_ALLOW_USERS"; [[ -n "$allow" && "$SSH_PERMIT_ROOT" == prohibit-password ]] && allow+=' root'
groups="$SSH_ALLOW_GROUPS"
# AllowUsers/AllowGroups dari berkas lain ikut berlaku dan DIGABUNG dengan milik
# script (diuji dengan sshd -T: nilai dari semua berkas ditumpuk). Bila keduanya
# ada, user harus lolos KEDUANYA: namanya di AllowUsers dan ia anggota AllowGroups.
other_allow=''; other_groups=''
for f in /etc/ssh/sshd_config /etc/ssh/sshd_config.d/*.conf; do
  [[ -f "$f" && "$f" != "$conf" ]] || continue
  other_allow+=" $(awk 'tolower($1)=="match"{exit} tolower($1)=="allowusers"{$1="";print}' "$f" | xargs)"
  other_groups+=" $(awk 'tolower($1)=="match"{exit} tolower($1)=="allowgroups"{$1="";print}' "$f" | xargs)"
done
other_allow="$(xargs <<<"$other_allow")"; other_groups="$(xargs <<<"$other_groups")"
[[ -n "$other_allow" ]] && warn "AllowUsers juga diatur di sshd_config lain: $other_allow"
[[ -n "$other_groups" ]] && warn "AllowGroups juga diatur di sshd_config lain: $other_groups"
uniq_words() { tr -s ' ' '\n' <<<"$*" | awk 'NF && !s[$0]++' | xargs; }
eff_allow="$(uniq_words "$allow $other_allow")"; eff_groups="$(uniq_words "$groups $other_groups")"

# ssh_allowed USER -> sukses bila USER lolos AllowUsers dan AllowGroups efektif.
ssh_allowed() {
  local u="$1" p g ug hit
  if [[ -n "$eff_allow" ]]; then
    hit=0
    for p in $eff_allow; do
      # shellcheck disable=SC2053  # pola wildcard sshd (user*, user@host)
      [[ "$u" == ${p%%@*} ]] && { hit=1; break; }
    done
    [[ $hit -eq 1 ]] || return 1
  fi
  if [[ -n "$eff_groups" ]]; then
    ug=" $(id -nG "$u" 2>/dev/null) "
    for g in $eff_groups; do
      # shellcheck disable=SC2053
      for p in $ug; do [[ "$p" == $g ]] && return 0; done
    done
    return 1
  fi
  return 0
}
has_keys() {
  local home n=0
  home="$(getent passwd "$1" | cut -d: -f6)" || return 1
  [[ -s "$home/.ssh/authorized_keys" ]] && n=$(grep -cE '(^| )(ssh-|ecdsa-|sk-)' "$home/.ssh/authorized_keys" || true)
  [[ $n -gt 0 ]]
}

me="$(login_user)"
if ssh_allowed "$me"; then ok "user yang login ($me) tetap boleh SSH"
else guard "User yang sedang login ($me) tidak lolos AllowUsers [$eff_allow] / AllowGroups [$eff_groups]. Tambahkan user atau grupnya dulu agar tidak terkunci."; fi
for u in $SSH_ALLOW_USERS; do
  if ! getent passwd "$u" >/dev/null; then guard "User $u tidak ada di server."; continue; fi
  ssh_allowed "$u" || guard "User $u ada di SSH_ALLOW_USERS tetapi bukan anggota AllowGroups [$eff_groups], jadi tetap ditolak sshd."
  if has_keys "$u"; then ok "$u punya authorized_keys"
  else guard "User $u belum punya kunci di ~$u/.ssh/authorized_keys. Password login akan dimatikan, jadi pasang kunci dulu."; fi
done
if [[ -n "$eff_groups" ]]; then
  # Anggota grup: anggota tambahan (getent group) dan yang grup utamanya grup itu.
  members=''
  for g in $eff_groups; do
    line="$(getent group "$g")" || { warn "Grup $g (AllowGroups) tidak ada di server."; continue; }
    members+=" $(cut -d: -f4 <<<"$line" | tr , ' ')"
    members+=" $(getent passwd | awk -F: -v gid="$(cut -d: -f3 <<<"$line")" '$4==gid{print $1}' | xargs)"
  done
  for u in $(tr ' ' '\n' <<<"$members" | sort -u); do
    ssh_allowed "$u" || continue
    if has_keys "$u"; then ok "$u (lewat AllowGroups) punya authorized_keys"
    else warn "$u boleh SSH lewat AllowGroups tetapi belum punya authorized_keys; ia tidak bisa login dengan password."; fi
  done
fi
if [[ "$SSH_PERMIT_ROOT" == prohibit-password ]] && ! ssh_allowed root; then
  warn 'SSH_PERMIT_ROOT=prohibit-password, tetapi root tidak lolos AllowUsers/AllowGroups.'
fi
if [[ "$SSH_PERMIT_ROOT" == prohibit-password && ! -s /root/.ssh/authorized_keys ]]; then
  warn 'SSH_PERMIT_ROOT=prohibit-password tetapi /root/.ssh/authorized_keys kosong.'
fi

# ------------------------------------------------------------------- paket ---
step 'Update sistem dan paket dasar'
if [[ "$(readlink -f /etc/localtime)" == "/usr/share/zoneinfo/$TIMEZONE" ]]; then same "zona waktu $TIMEZONE"
else plan "zona waktu $TIMEZONE (sekarang: $(readlink -f /etc/localtime | sed 's|/usr/share/zoneinfo/||'))"; run timedatectl set-timezone "$TIMEZONE"; fi
apt_upgrade
apt_install unattended-upgrades apt-listchanges needrestart chrony auditd \
  apparmor apparmor-utils ca-certificates curl jq python3 logrotate
for svc in chrony auditd apparmor; do
  if systemctl is-enabled --quiet "$svc" 2>/dev/null; then same "$svc aktif saat boot"
  else plan "aktifkan $svc"; runq systemctl enable --now "$svc" || true; fi
done

write_file /etc/apt/apt.conf.d/20auto-upgrades <<'CONF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::AutocleanInterval "7";
CONF
write_file /etc/apt/apt.conf.d/52unattended-production <<'CONF'
// Hanya update keamanan Ubuntu (bawaan 50unattended-upgrades). Server tidak
// di-reboot otomatis; cek /var/run/reboot-required dan reboot terjadwal.
Unattended-Upgrade::Automatic-Reboot "false";
Unattended-Upgrade::Remove-Unused-Dependencies "true";
Unattended-Upgrade::Remove-Unused-Kernel-Packages "true";
CONF

# --------------------------------------------------------------------- SSH ---
step 'Hardening SSH (key-only, port 22)'
# sshd memakai nilai PERTAMA yang ditemukan, dan sshd_config.d/* dibaca di awal
# sshd_config. Nama 00-* memastikan nilai ini menang atas 50-cloud-init.conf.
access=''
[[ -n "$allow" ]] && access+="AllowUsers $allow"$'\n'
[[ -n "$groups" ]] && access+="AllowGroups $groups"$'\n'
write_file "$conf" <<CONF
# Dibuat oleh deploy/server/01-hardening.sh
Port 22
PermitRootLogin $SSH_PERMIT_ROOT
PubkeyAuthentication yes
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitEmptyPasswords no
AuthenticationMethods publickey
${access}MaxAuthTries 3
MaxSessions 5
LoginGraceTime 30
ClientAliveInterval 300
ClientAliveCountMax 2
X11Forwarding no
AllowAgentForwarding no
PermitTunnel no
GatewayPorts no
PermitUserEnvironment no
LogLevel VERBOSE
CONF
if [[ $DRY_RUN -eq 1 ]]; then
  echo '  Nilai efektif sshd sekarang:'
  sshd -T 2>/dev/null | grep -E '^(port|permitrootlogin|passwordauthentication|kbdinteractiveauthentication|authenticationmethods|allowusers|allowgroups) ' | sed 's/^/      /' || true
  [[ $WF_CHANGED -eq 1 ]] && run systemctl reload ssh
elif [[ $WF_CHANGED -eq 1 ]]; then
  if ! sshd -t; then
    mv -f "$conf.bak-$STAMP" "$conf" 2>/dev/null || rm -f "$conf"
    die 'Konfigurasi SSH tidak valid; berkas dikembalikan, SSH tidak diubah.'
  fi
  # Cek ulang dengan nilai efektif sshd sebelum reload.
  eff_allow="$(uniq_words "$(sshd -T 2>/dev/null | awk '$1=="allowusers"{print $2}')")"
  eff_groups="$(uniq_words "$(sshd -T 2>/dev/null | awk '$1=="allowgroups"{print $2}')")"
  if ! ssh_allowed "$me"; then
    mv -f "$conf.bak-$STAMP" "$conf" 2>/dev/null || rm -f "$conf"
    die "Dengan konfigurasi baru, $me tidak boleh SSH (AllowUsers [$eff_allow], AllowGroups [$eff_groups]); berkas dikembalikan."
  fi
  systemctl reload ssh 2>/dev/null || systemctl restart ssh
  ok "SSH di-reload: hanya kunci, AllowUsers [$eff_allow], AllowGroups [$eff_groups], root=$SSH_PERMIT_ROOT"
fi

if passwd -S root 2>/dev/null | awk '{print $2}' | grep -q '^L'; then same 'password root terkunci'
else plan 'kunci password root'; runq passwd -l root; fi

# ------------------------------------------------------------------ kernel ---
step 'Parameter kernel (sysctl)'
write_file /etc/sysctl.d/90-hardening.conf <<'CONF'
# Dibuat oleh deploy/server/01-hardening.sh
# ip_forward sengaja tidak diubah: Docker membutuhkannya.
net.ipv4.conf.all.rp_filter = 1
net.ipv4.conf.default.rp_filter = 1
net.ipv4.conf.all.accept_redirects = 0
net.ipv4.conf.default.accept_redirects = 0
net.ipv6.conf.all.accept_redirects = 0
net.ipv6.conf.default.accept_redirects = 0
net.ipv4.conf.all.secure_redirects = 0
net.ipv4.conf.all.send_redirects = 0
net.ipv4.conf.default.send_redirects = 0
net.ipv4.conf.all.accept_source_route = 0
net.ipv6.conf.all.accept_source_route = 0
net.ipv4.conf.all.log_martians = 1
net.ipv4.icmp_echo_ignore_broadcasts = 1
net.ipv4.icmp_ignore_bogus_error_responses = 1
net.ipv4.tcp_syncookies = 1
kernel.kptr_restrict = 2
kernel.dmesg_restrict = 1
kernel.yama.ptrace_scope = 1
kernel.unprivileged_bpf_disabled = 1
kernel.sysrq = 0
fs.protected_symlinks = 1
fs.protected_hardlinks = 1
fs.protected_fifos = 2
fs.protected_regular = 2
fs.suid_dumpable = 0
CONF
[[ $WF_CHANGED -eq 1 ]] && runq sysctl --system
write_file /etc/security/limits.d/90-nocore.conf <<'CONF'
* hard core 0
CONF

# ------------------------------------------------------------- log & audit ---
step 'journald dan auditd'
write_file /etc/systemd/journald.conf.d/90-size.conf <<'CONF'
[Journal]
SystemMaxUse=1G
MaxRetentionSec=3month
CONF
[[ $WF_CHANGED -eq 1 ]] && run systemctl restart systemd-journald

write_file /etc/audit/rules.d/90-server.rules <<'CONF'
# Dibuat oleh deploy/server/01-hardening.sh
-w /etc/passwd -p wa -k identity
-w /etc/group -p wa -k identity
-w /etc/shadow -p wa -k identity
-w /etc/sudoers -p wa -k sudoers
-w /etc/sudoers.d/ -p wa -k sudoers
-w /etc/ssh/sshd_config -p wa -k sshd
-w /etc/ssh/sshd_config.d/ -p wa -k sshd
-w /etc/docker/ -p wa -k docker
-w /etc/mysql/ -p wa -k mariadb
-w /etc/ufw/ -p wa -k firewall
-w /etc/fail2ban/ -p wa -k fail2ban
-w /usr/bin/docker -p x -k docker-cli
CONF
if [[ $WF_CHANGED -eq 1 ]]; then
  runq augenrules --load || warn 'augenrules gagal dimuat; cek: auditctl -l'
fi

# ---------------------------------------------------------------- ringkasan ---
step 'Layanan'
enabled=$(systemctl list-unit-files --type=service --state=enabled --no-legend 2>/dev/null | awk '{print $1}' | tr '\n' ' ')
echo "  Aktif saat boot: ${enabled:-?}"
echo '  Matikan yang tidak dipakai: systemctl disable --now <nama>'
[[ -f /var/run/reboot-required ]] && warn 'Kernel/paket baru menunggu reboot: sudo reboot (setelah uji login SSH baru).'
finish
warn 'Uji login SSH dari terminal BARU sebelum menutup sesi ini.'
echo '  Berikutnya: sudo ./02-firewall.sh'
