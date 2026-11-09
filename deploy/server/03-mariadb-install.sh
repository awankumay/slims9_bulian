#!/usr/bin/env bash
# 03 - Install MariaDB standalone dari repo Ubuntu, amankan seperti
#      mariadb-secure-installation, dan pasang tuning dasar. Masih hanya
#      mendengarkan 127.0.0.1; akses dari container dibuka di 04.
#
# Pakai: sudo ./03-mariadb-install.sh             terapkan
#        sudo ./03-mariadb-install.sh --dry-run   periksa saja (alias --check;
#                                                 exit 0 sesuai, 2 ada perubahan)
source "$(dirname "$0")/lib.sh"
parse_args "$@"; need_root "$@"; load_env; check_ubuntu

step 'Install MariaDB'
[[ $DRY_RUN -eq 1 ]] || apt_q update
apt_install mariadb-server mariadb-client mariadb-backup
db_up=0
if command -v mariadb >/dev/null && mariadb -e 'SELECT 1' >/dev/null 2>&1; then db_up=1
  same "MariaDB $(mariadb -N -e 'SELECT VERSION()') berjalan"
else
  plan 'jalankan MariaDB (dan saat boot)'; runq systemctl enable --now mariadb
  [[ $DRY_RUN -eq 0 ]] && db_up=1
fi
if [[ $DRY_RUN -eq 1 && $db_up -eq 0 ]] && command -v apt-cache >/dev/null; then
  echo "  Versi dari repo: $(apt-cache policy mariadb-server 2>/dev/null | sed -n 's/ *Candidate: //p')"
fi

step 'Mengamankan instalasi (setara mariadb-secure-installation)'
# root@localhost di Ubuntu memakai unix_socket: hanya root Linux (sudo) yang bisa
# masuk, tanpa password. Itu dipertahankan.
if [[ $db_up -eq 1 ]]; then
  bad_users="$(mariadb -N -e "SELECT CONCAT(QUOTE(User), '@', QUOTE(Host)) FROM mysql.user
                              WHERE User = '' OR (User = 'root' AND Host NOT IN ('localhost', '127.0.0.1', '::1'))")"
  test_db="$(mariadb -N -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = 'test'")"
  if [[ -z "$bad_users" && "$test_db" == 0 ]]; then same 'tidak ada user anonim, root remote, atau database test'
  else
    for u in $bad_users; do plan "hapus user $u"; runq mariadb -e "DROP USER IF EXISTS $u"; done
    if [[ "$test_db" != 0 ]]; then plan 'hapus database test'; runq mariadb -e 'DROP DATABASE IF EXISTS test'; fi
    runq mariadb -e "DELETE FROM mysql.db WHERE Db = 'test' OR Db = 'test\\_%'; FLUSH PRIVILEGES;"
  fi
else
  plan 'hapus user anonim, root remote, dan database test (setelah MariaDB terpasang)'
fi

step 'Tuning dasar'
if [[ -z "$DB_BUFFER_POOL" ]]; then
  mem_mb=$(awk '/MemTotal/ {print int($2/1024)}' /proc/meminfo)
  DB_BUFFER_POOL="$(( mem_mb * 40 / 100 ))M"
fi
write_file /etc/mysql/mariadb.conf.d/90-production.cnf <<CONF
# Dibuat oleh deploy/server/03-mariadb-install.sh
[mysqld]
# Akses jaringan diatur di 91-network.cnf (04-mariadb-config.sh).
bind-address            = 127.0.0.1
skip-name-resolve
local-infile            = 0

character-set-server    = utf8mb4
collation-server        = utf8mb4_unicode_ci

innodb_buffer_pool_size = $DB_BUFFER_POOL
innodb_file_per_table   = 1
innodb_flush_log_at_trx_commit = 1
# SLiMS memakai MyISAM: beri cache indeks yang cukup.
key_buffer_size         = 64M
max_connections         = 200
max_allowed_packet      = 64M

slow_query_log          = 1
slow_query_log_file     = /var/log/mysql/mariadb-slow.log
long_query_time         = 2
CONF
if [[ $WF_CHANGED -eq 1 ]]; then
  if [[ $DRY_RUN -eq 0 ]]; then mkdir -p /var/log/mysql && chown mysql:adm /var/log/mysql; fi
  run systemctl restart mariadb
fi

finish
step 'Selesai'
echo "  Versi: $(mariadb -N -e 'SELECT VERSION()')"
warn 'Staging EPrints memakai mariadb:10.11 dan SLiMS mariadb:lts. Samakan image staging dengan versi ini sebelum migrasi.'
echo '  Berikutnya: sudo ./04-mariadb-config.sh'
