# Setup server production (Ubuntu 26.04)

Lima script untuk menyiapkan VPS sebelum container WAF, EPrints, dan SLiMS dipasang.
Rencana lengkapnya ada di dokumen "Rencana Deploy Production EPrints & SLiMS".

| Script | Isi |
| --- | --- |
| `01-hardening.sh` | Update + `unattended-upgrades` (keamanan saja, tanpa reboot otomatis), SSH key-only di port 22 (`AllowUsers`, root mati), password root dikunci, sysctl, core dump mati, chrony, auditd, AppArmor, batas journald |
| `02-firewall.sh` | UFW: SSH 22 dan web 80/443 hanya dari VPN, 3306 hanya dari network Docker. Aturan `DOCKER-USER` agar port container lain (8080 EPrints, 8881 SLiMS) tidak bisa dibuka dari luar, karena Docker melewati UFW |
| `03-mariadb-install.sh` | MariaDB dari repo Ubuntu (11.8 di Ubuntu 26.04), diamankan setara `mariadb-secure-installation`, tuning dasar, masih hanya `127.0.0.1` |
| `04-mariadb-config.sh` | Buka 3306 untuk container: lewat UFW (`DB_BIND=any`) atau hanya di IP gateway Docker (`DB_BIND=docker-gw`), database + user `eprints` dan `senayan`/`slims` dengan password acak, skema SLiMS (opsional), backup harian via systemd timer |
| `05-fail2ban.sh` | Jail `sshd`, `waf-modsec`, `nginx-limit-req`, `nginx-botsearch`, `recidive` (atau hanya jail WAF dengan `F2B_SCOPE=waf`). Ban web masuk ke chain `DOCKER-USER`. IP VPN dan Docker tidak pernah di-ban |
| `check-all.sh` | Menjalankan 01-05 dengan `--dry-run` sekaligus dan menampilkan ringkasan; tidak mengubah apa pun |

## Cara pakai

Jalankan dari sesi SSH lewat VPN, sebagai user yang boleh SSH menurut `SSH_ALLOW_USERS`/`SSH_ALLOW_GROUPS`:

```bash
# Script ini sama persis di repo eprints-docker dan slims9_bulian; cukup dari salah satu.
git clone -b develop https://github.com/awankumay/eprints-docker.git
cd eprints-docker/deploy/server     # atau: slims9_bulian/deploy/server
cp server.env.example server.env && chmod 600 server.env
nano server.env              # ADMIN_CIDRS wajib untuk 02, SSH_ALLOW_USERS/GROUPS wajib untuk 01

sudo ./check-all.sh          # lihat dulu apa yang akan berubah
sudo ./01-hardening.sh       # lalu uji login SSH dari terminal BARU
sudo ./02-firewall.sh        # lalu uji login SSH dari terminal BARU
sudo ./03-mariadb-install.sh
sudo ./04-mariadb-config.sh
sudo ./05-fail2ban.sh
```

### Server yang hardening, firewall, dan fail2ban-nya dikelola Tim Infra

Bila Anda vendor/tim aplikasi dan SSH, firewall, serta fail2ban sudah disiapkan Tim Infra,
jangan jalankan `01` dan `02`. Cukup jalankan `--dry-run` keduanya dan serahkan hasilnya ke
Tim Infra sebagai masukan. Untuk bagian aplikasi, isi `server.env`:

```bash
DB_BIND="docker-gw"   # MariaDB hanya di 127.0.0.1 + gateway Docker, tanpa bergantung UFW
F2B_SCOPE="waf"       # hanya tambah jail WAF; sshd/recidive/[DEFAULT] milik Infra tidak diubah
```

lalu:

```bash
sudo ./check-all.sh 01 02 > laporan-untuk-infra.txt   # hanya dibaca, tidak mengubah apa pun
sudo ./check-all.sh 03 04 05                          # cek bagian aplikasi
sudo ./03-mariadb-install.sh
sudo ./04-mariadb-config.sh       # database + user; network Docker belum perlu ada
sudo ./05-fail2ban.sh
```

`ADMIN_CIDRS` dan `SSH_ALLOW_USERS`/`SSH_ALLOW_GROUPS` boleh dikosongkan bila `01`/`02` tidak
dijalankan. Di `05`, `ADMIN_CIDRS` hanya dipakai sebagai daftar IP yang tidak pernah di-ban.

Dengan `DB_BIND="docker-gw"`, `04` menulis `bind-address = 127.0.0.1,192.168.121.1,192.168.121.33`
dan `net.ipv4.ip_nonlocal_bind = 1` agar MariaDB tetap bisa start walaupun bridge Docker
belum ada (saat boot atau setelah `docker compose down`). Bila UFW aktif, Tim Infra perlu
mengizinkan 3306 dari `DOCKER_CIDR`; `04` memperingatkan bila aturannya belum ada.

## Menjalankan aplikasi (setelah 03 dan 04)

Kedua aplikasi memakai MariaDB di host, network Docker dengan subnet tetap, container yang
di-hardening (`no-new-privileges`, `cap_drop ALL` + cap minimal, `/tmp` tmpfs, rotasi log),
serta batas CPU/RAM/PIDs. Port aplikasi hanya dibuka di `127.0.0.1` untuk uji dari server;
pengunjung masuk lewat WAF. Keduanya punya mode periksa yang tidak mengubah apa pun.

| Network | Subnet | Gateway (MariaDB) | WAF | Aplikasi | IP otomatis |
| --- | --- | --- | --- | --- | --- |
| `eprints-net` (`br-eprints`) | `192.168.121.0/27` | `.1` | `.2` | `.3` (port 8080) | `.16/28` |
| `slims-net` (`br-slims`) | `192.168.121.32/27` | `.33` | `.34` | `.35` (port 8881) | `.48/28` |

Container lain (mis. container uji) hanya mendapat IP dari kolom "IP otomatis", sehingga IP
WAF yang dipercaya untuk header `X-Forwarded-For` tidak bisa diambil container lain.

EPrints (dari root repo `eprints-docker`):

```bash
sudo ./install.sh --docker -y --production --db-credentials /root/db-credentials.env \
  --hostname repository.kampus.ac.id --dry-run      # periksa: exit 0 sesuai, 2 ada perubahan, 1 gagal
sudo ./install.sh --docker -y --production --db-credentials /root/db-credentials.env \
  --hostname repository.kampus.ac.id                # pasang
curl -I -H 'Host: repository.kampus.ac.id' http://127.0.0.1:8080/
```

SLiMS (dari `slims9_bulian/deploy/production`):

```bash
sudo ./slims-prod.sh init-env    # .env dari .env.example + password dari /root/db-credentials.env
                                 # (lokasi lain: sudo ./slims-prod.sh init-env /opt/secrets/db-credentials.env)
./slims-prod.sh check            # periksa (alias --dry-run): exit 0 sesuai, 2 ada perubahan, 1 gagal
./slims-prod.sh up               # buat network, muat skema bila DB kosong, jalankan container
curl -I http://127.0.0.1:8881/
```

Batas resource bisa diubah: EPrints dengan `--cpus`, `--memory`, `--pids`; SLiMS lewat
`SLIMS_CPUS`, `SLIMS_MEMORY`, `SLIMS_PIDS` di `.env`.

## Mode periksa (`--dry-run` / `--check`)

Setiap script menerima `--dry-run` (alias `--check` atau `-n`). Pada mode ini tidak ada yang
diubah: berkas konfigurasi dibandingkan dengan isinya sekarang (ditampilkan sebagai diff),
paket, aturan UFW, user/database, dan layanan yang belum sesuai dilaporkan, dan perintah
yang akan dijalankan dicetak. Pengaman anti-terkunci tetap diperiksa.

```bash
sudo ./02-firewall.sh --dry-run     # satu script
sudo ./check-all.sh                 # 01-05 sekaligus, dengan ringkasan
sudo ./check-all.sh -q              # ringkasan saja
sudo ./check-all.sh 03 04 05        # hanya script tertentu
```

Tanda di output: `[sesuai]` sudah sesuai, `[akan]` akan diubah, `[perintah]` perintah yang
akan dijalankan, `[akan gagal]` pra-syarat yang membuat run sungguhan berhenti.

| Exit code | Arti |
| --- | --- |
| 0 | Sudah sesuai, tidak ada yang akan berubah |
| 2 | Ada yang akan berubah (berkas, paket, aturan, user) |
| 1 | Pra-syarat gagal, mis. IP sesi di luar VPN atau UFW belum aktif |

Karena itu `check-all.sh` juga bisa dipakai berkala untuk mendeteksi konfigurasi yang
berubah di luar script (drift), mis. dari cron yang mengirim email bila exit code bukan 0.
Saat dijalankan sungguhan, item yang berubah ditandai `[ubah]`.

Urutannya penting: firewall dipasang sebelum MariaDB dibuka ke jaringan, dan `04` menolak
jalan bila UFW belum aktif (kecuali `DB_BIND="docker-gw"`). Semua script aman dijalankan ulang.

## Pengaman agar tidak terkunci

- `01` membaca juga `AllowUsers`/`AllowGroups` yang sudah ada di `/etc/ssh/sshd_config` dan
  `sshd_config.d/`. sshd menggabungkan semuanya, dan bila keduanya ada, user harus lolos
  **keduanya**. Contoh: `sshd_config` berisi `AllowGroups admin devops sshusers` dan
  `SSH_ALLOW_USERS="infra"`, maka hanya `infra` yang boleh, dan itu pun hanya bila `infra`
  anggota salah satu grup tadi. Bila Tim Infra memakai akun masing-masing dalam satu grup,
  isi `SSH_ALLOW_USERS=""` dan `SSH_ALLOW_GROUPS="admin devops sshusers"`.
- `01` berhenti bila user yang sedang login akan ditolak sshd, bila ada user di
  `SSH_ALLOW_USERS` yang tidak ada, tidak lolos `AllowGroups`, atau belum punya
  `~/.ssh/authorized_keys`. Anggota grup tanpa kunci dilaporkan sebagai peringatan.
  Konfigurasi SSH diuji dengan `sshd -t`, lalu nilai efektif `sshd -T` diperiksa sekali lagi
  untuk user yang login; bila gagal, berkasnya dikembalikan. Berkasnya bernama
  `00-hardening.conf` agar menang atas `50-cloud-init.conf` (sshd memakai nilai pertama yang
  ditemukan, kecuali `AllowUsers`/`AllowGroups` yang ditumpuk).
- `02` berhenti bila IP sesi SSH ini tidak termasuk `ADMIN_CIDRS`.
- Biarkan satu sesi SSH tetap terbuka dan uji login dari terminal baru setelah `01` dan `02`.
- `02` hanya bisa mengecek IP sesi yang sedang dipakai. Bila server diakses lewat lebih dari
  satu VPN (mis. OpenVPN dan FortiClient), cek IP asal tiap jalur dengan
  `sudo ss -tnp state established '( sport = :22 )'` (kolom Peer) atau minta pool IP klien
  VPN ke Tim Infra, lalu masukkan semua subnetnya ke `ADMIN_CIDRS`. Untuk jaga-jaga, pasang
  rollback otomatis sebelum `02`:

  ```bash
  sudo systemd-run --on-active=10m --unit=ufw-rollback ufw disable
  sudo ./02-firewall.sh
  # uji login dari semua jalur VPN di terminal baru, lalu batalkan rollback:
  sudo systemctl stop ufw-rollback.timer
  ```
  Bila tetap terkunci, gunakan konsol VPS dari panel penyedia: `ufw disable` dan
  `rm /etc/ssh/sshd_config.d/00-hardening.conf && systemctl reload ssh`.

## Hasil dan berkas penting

| Lokasi | Isi |
| --- | --- |
| `/root/db-credentials.env` (`DB_CRED_FILE`) | Nama DB, user, password EPrints dan SLiMS (root:root 600). Bisa dipindah lewat `DB_CRED_FILE` di `server.env`, lihat di bawah |
| `/var/backups/mariadb/` | Dump harian `eprints-*`, `senayan-*`, `mysql-*` (14 hari). Salin juga ke luar server |
| `/var/log/waf/` | Log container WAF yang dibaca fail2ban (`access.log`, `error.log`, `modsec_audit.json`) |
| `/var/log/deploy-server-apt.log` | Log apt dari script |
| `*.bak-<waktu>` | Cadangan berkas konfigurasi sebelum diubah |

Dari container, MariaDB dijangkau lewat IP gateway network Docker masing-masing:
`192.168.121.1` (eprints-net) dan `192.168.121.33` (slims-net).

### Memindahkan berkas kredensial

Lokasi berkas kredensial diatur `DB_CRED_FILE` di `server.env`, mis. ke `/opt/secrets/` bila
semua berkas deploy ditaruh di `/opt`. Lokasi tidak mengubah keamanannya: yang menjaga adalah
izin `root:root 600` (folder `700`). Siapa pun yang punya `sudo` atau masuk grup `docker`
tetap bisa membacanya, jadi batasi kedua akses itu untuk vendor lain.

Bila berkas sudah pernah dibuat di `/root`, pindahkan dulu; `04` berhenti dan menampilkan
perintahnya, agar tidak membuat password baru yang memutus container yang sudah jalan:

```bash
install -d -m 700 /opt/secrets && mv /root/db-credentials.env /opt/secrets/db-credentials.env
sed -i 's|^DB_CRED_FILE=.*|DB_CRED_FILE="/opt/secrets/db-credentials.env"|' server.env   # atau tambahkan barisnya
sudo ./04-mariadb-config.sh --dry-run    # harus "sesuai", tanpa password baru
```

## Saat rilis publik

Ubah `WEB_ACCESS="any"` di `server.env`, lalu `sudo ./02-firewall.sh`. Script ini juga
memulai ulang fail2ban, karena `ufw reload` mengosongkan chain `DOCKER-USER` beserta ban
di dalamnya.

## Catatan

- Versi MariaDB di host (11.8) lebih baru dari staging EPrints (`mariadb:10.11`). Uji fresh
  install EPrints di staging dengan `MARIADB_IMAGE=mariadb:11.8` sebelum migrasi data, dan
  pin SLiMS ke versi yang sama.
- Dump SLiMS memakai `--lock-tables` karena SLiMS memakai MyISAM; tabel terkunci sebentar
  saat backup jam 01:30.
- Jail `waf-modsec` baru memicu setelah WAF memakai `MODSEC_RULE_ENGINE=On`.
- Diuji pada container Ubuntu 26.04 (MariaDB 11.8.6, fail2ban 1.1.0, ufw/iptables-nft):
  aturan UFW dan `DOCKER-USER`, user/DB/skema SLiMS, backup, ban fail2ban ke
  `DOCKER-USER`, serta `--dry-run` di server kosong (tidak ada yang berubah), setelah
  setup (semua `sesuai`), dan setelah konfigurasi diubah manual (drift terdeteksi lalu
  diperbaiki run berikutnya). Login SSH, systemd timer, dan auditd belum diuji di VPS sungguhan.
