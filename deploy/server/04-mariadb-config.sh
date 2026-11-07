#!/usr/bin/env bash
# 04 - Konfigurasi MariaDB untuk EPrints dan SLiMS: buka akses dari network Docker,
#      buat database + user aplikasi, muat skema SLiMS (opsional), backup harian.
#
# Pakai: sudo ./04-mariadb-config.sh             terapkan (aman diulang; password lama dipakai lagi)
#        sudo ./04-mariadb-config.sh --rotate    buat password baru untuk kedua user
#        sudo ./04-mariadb-config.sh --dry-run   periksa saja (alias --check;
#                                                exit 0 sesuai, 2 ada perubahan)
source "$(dirname "$0")/lib.sh"
parse_args "$@"; need_root "$@"; load_env
ROTATE=0; has_arg --rotate && ROTATE=1
CRED=/root/db-credentials.env

db_up=0
if command -v mariadb >/dev/null && mariadb -e 'SELECT 1' >/dev/null 2>&1; then db_up=1
else guard 'MariaDB belum terpasang/berjalan. Jalankan 03-mariadb-install.sh dulu.'; fi

# ------------------------------------------- pastikan firewall sudah menjaga ---
step 'Memeriksa firewall sebelum MariaDB dibuka ke jaringan'
ufw_status="$(ufw status 2>/dev/null || true)"
if ! grep -q '^Status: active' <<<"$ufw_status"; then
  guard 'UFW belum aktif. Jalankan 02-firewall.sh dulu: MariaDB akan mendengarkan semua interface.'
elif ! grep -E '^3306/tcp' <<<"$ufw_status" | grep -q "$DOCKER_CIDR"; then
  guard "Aturan UFW 3306 dari $DOCKER_CIDR tidak ditemukan. Jalankan ulang 02-firewall.sh."
elif grep -E '^3306' <<<"$ufw_status" | grep -q 'Anywhere'; then
  guard 'Port 3306 terbuka untuk Anywhere di UFW. Hapus aturan itu dulu.'
else
  ok "3306 hanya dari $DOCKER_CIDR"
fi

step 'Membuka MariaDB untuk container'
# bind 0.0.0.0, bukan IP bridge Docker: interface bridge baru ada setelah Docker
# start, sehingga MariaDB yang start lebih dulu akan gagal bind. Pembatasnya UFW
# dan host user (DB_APP_HOST).
write_file /etc/mysql/mariadb.conf.d/91-network.cnf <<'CONF'
# Dibuat oleh deploy/server/04-mariadb-config.sh
[mysqld]
bind-address = 0.0.0.0
CONF
[[ $WF_CHANGED -eq 1 ]] && run systemctl restart mariadb

# ------------------------------------------------------- database dan user ---
step 'Database dan user aplikasi'
[[ "$EPRINTS_DB_NAME$EPRINTS_DB_USER$SLIMS_DB_NAME$SLIMS_DB_USER" =~ ^[A-Za-z0-9_]+$ ]] \
  || die 'Nama database/user hanya boleh huruf, angka, dan _.'
if [[ $DRY_RUN -eq 0 ]]; then touch "$CRED"; chmod 600 "$CRED"; fi
cred_get() { [[ -f "$CRED" ]] && sed -n "s/^$1=//p" "$CRED" | tail -n1; return 0; }
cred_set() { sed -i "/^$1=/d" "$CRED"; echo "$1=$2" >>"$CRED"; }
q() { mariadb -N -e "$1"; }

# shellcheck disable=SC2015  # same/plan selalu sukses
setup_db() { # nama_db user prefix_kredensial
  local db="$1" user="$2" key="$3" pass has_db has_user has_grant
  if [[ $db_up -eq 0 ]]; then plan "database $db + user '$user'@'$DB_APP_HOST'"; return 0; fi
  has_db=$(q "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='$db'")
  has_user=$(q "SELECT COUNT(*) FROM mysql.user WHERE User='$user' AND Host='$DB_APP_HOST'")
  has_grant=$(q "SELECT COUNT(*) FROM mysql.db WHERE Db='$db' AND User='$user' AND Host='$DB_APP_HOST' AND Select_priv='Y' AND Drop_priv='Y'")
  pass="$(cred_get "${key}_DB_PASS")"
  [[ "$has_db" == 1 ]] && same "database $db ada" || plan "buat database $db (utf8mb4)"
  [[ "$has_user" == 1 ]] && same "user '$user'@'$DB_APP_HOST' ada" || plan "buat user '$user'@'$DB_APP_HOST'"
  [[ "$has_grant" == 1 ]] && same "hak akses $user pada $db" || plan "GRANT ALL ON $db.* TO $user"
  [[ -n "$pass" ]] || { [[ "$has_user" == 1 ]] && plan "password $user tidak ada di $CRED; akan dibuat baru"; }
  [[ $ROTATE -eq 1 ]] && plan "password baru untuk $user (--rotate)"
  [[ $DRY_RUN -eq 1 ]] && return 0
  # Password lama dipakai lagi agar container yang sudah jalan tidak putus.
  if [[ -z "$pass" || $ROTATE -eq 1 ]]; then
    pass="$(rand_pass 28)"   # huruf+angka saja: batasan installer EPrints
  fi
  mariadb <<SQL
CREATE DATABASE IF NOT EXISTS \`$db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$user'@'$DB_APP_HOST' IDENTIFIED BY '$pass';
ALTER USER '$user'@'$DB_APP_HOST' IDENTIFIED BY '$pass';
GRANT ALL PRIVILEGES ON \`$db\`.* TO '$user'@'$DB_APP_HOST';
FLUSH PRIVILEGES;
SQL
  cred_set "${key}_DB_NAME" "$db"
  cred_set "${key}_DB_USER" "$user"
  cred_set "${key}_DB_PASS" "$pass"
}
setup_db "$EPRINTS_DB_NAME" "$EPRINTS_DB_USER" EPRINTS
setup_db "$SLIMS_DB_NAME" "$SLIMS_DB_USER" SLIMS
[[ $DRY_RUN -eq 0 ]] && ok "kredensial tersimpan di $CRED (hanya root)"

# --------------------------------------------------------------- skema SLiMS ---
if [[ -n "$SLIMS_SQL" ]]; then
  step 'Skema SLiMS'
  if [[ ! -f "$SLIMS_SQL" ]]; then guard "SLIMS_SQL tidak ditemukan: $SLIMS_SQL"
  else
    tables=0
    [[ $db_up -eq 1 ]] && tables=$(q "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$SLIMS_DB_NAME'")
    if [[ "$tables" -gt 0 ]]; then
      same "database $SLIMS_DB_NAME sudah berisi $tables tabel; skema tidak dimuat ulang"
    else
      plan "muat skema SLiMS dari $SLIMS_SQL"
      if [[ $DRY_RUN -eq 0 ]]; then
        mariadb "$SLIMS_DB_NAME" <"$SLIMS_SQL"
        ok "$(q "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$SLIMS_DB_NAME'") tabel dimuat"
      fi
    fi
  fi
fi

# ------------------------------------------------------------- backup harian ---
step 'Backup harian'
if [[ -d "$DB_BACKUP_DIR" ]]; then same "folder $DB_BACKUP_DIR"
else plan "buat folder $DB_BACKUP_DIR (700)"; run install -d -m 700 "$DB_BACKUP_DIR"; fi
backup_changed=0
write_file /usr/local/sbin/mariadb-backup-daily 0700 <<SH
#!/usr/bin/env bash
# Dibuat oleh deploy/server/04-mariadb-config.sh. Dump harian per database.
set -euo pipefail
dir="$DB_BACKUP_DIR"; keep="$DB_BACKUP_KEEP_DAYS"; ts=\$(date +%Y%m%d-%H%M)
umask 077
# EPrints = InnoDB: snapshot konsisten tanpa mengunci tabel.
mariadb-dump --single-transaction --routines --triggers "$EPRINTS_DB_NAME" | gzip >"\$dir/$EPRINTS_DB_NAME-\$ts.sql.gz"
# SLiMS = MyISAM: --single-transaction tidak berlaku, tabel dikunci sebentar saat dump.
mariadb-dump --lock-tables --routines --triggers "$SLIMS_DB_NAME" | gzip >"\$dir/$SLIMS_DB_NAME-\$ts.sql.gz"
mariadb-dump --single-transaction --databases mysql | gzip >"\$dir/mysql-\$ts.sql.gz"
find "\$dir" -name '*.sql.gz' -mtime +"\$keep" -delete
echo "backup selesai: \$dir (\$ts)"
SH
[[ $WF_CHANGED -eq 1 ]] && backup_changed=1
write_file /etc/systemd/system/mariadb-backup-daily.service <<'UNIT'
[Unit]
Description=Backup harian database MariaDB (EPrints, SLiMS)
After=mariadb.service
Requires=mariadb.service

[Service]
Type=oneshot
ExecStart=/usr/local/sbin/mariadb-backup-daily
Nice=10
IOSchedulingClass=idle
UNIT
[[ $WF_CHANGED -eq 1 ]] && backup_changed=1
write_file /etc/systemd/system/mariadb-backup-daily.timer <<UNIT
[Unit]
Description=Jadwal backup harian MariaDB

[Timer]
OnCalendar=*-*-* $DB_BACKUP_TIME:00
Persistent=true
RandomizedDelaySec=10m

[Install]
WantedBy=timers.target
UNIT
[[ $WF_CHANGED -eq 1 ]] && backup_changed=1
if [[ $backup_changed -eq 1 ]]; then runq systemctl daemon-reload; fi
if systemctl is-enabled --quiet mariadb-backup-daily.timer 2>/dev/null; then same 'timer backup aktif'
else plan 'aktifkan timer backup'; runq systemctl enable --now mariadb-backup-daily.timer; fi
n_backup=$(find "$DB_BACKUP_DIR" -name '*.sql.gz' 2>/dev/null | wc -l)
if [[ $n_backup -eq 0 ]]; then
  plan 'jalankan backup pertama sebagai uji'; run systemctl start mariadb-backup-daily.service
else
  same "$n_backup berkas backup di $DB_BACKUP_DIR (terbaru: $(find "$DB_BACKUP_DIR" -name '*.sql.gz' -printf '%TY-%Tm-%Td %TH:%TM\n' | sort | tail -n1))"
fi
warn 'Salin backup ke luar server (rsync/rclone): backup di disk yang sama tidak melindungi dari kehilangan server.'

finish
step 'Selesai'
cat <<TXT
  Host DB dari container (IP gateway network Docker = alamat host):
    EPrints (eprints-net 192.168.121.0/27):  DB_HOST=192.168.121.1
    SLiMS   (slims-net   192.168.121.32/27): DB_HOST=192.168.121.33
  Kredensial: sudo cat $CRED
  Uji dari container, mis.:
    docker run --rm --network eprints-net mariadb:11.8 \\
      mariadb -h 192.168.121.1 -u $EPRINTS_DB_USER -p $EPRINTS_DB_NAME -e 'SELECT 1'
  Berikutnya: sudo ./05-fail2ban.sh
TXT
