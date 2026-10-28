# Deployment SLiMS dengan Docker Compose

## Yang disediakan

| Komponen | Isi |
|---|---|
| Image aplikasi | `php:8.3.36-apache-bookworm` (di-pin digest), ekstensi `intl gd gettext mbstring zip mysqli pdo_mysql`, Composer `--no-dev` |
| Database | MariaDB (`mariadb:lts`), tidak dipublikasikan ke host, akun aplikasi non-root |
| Volume | `dbdata`, `appfiles`, `appimages`, `apprepo` |
| Healthcheck | DB: `healthcheck.sh`; aplikasi: `curl` ke `index.php` |
| Restart | `unless-stopped` |

PHP 8.3.36 dan 8.4.27 sudah memuat perbaikan PHP2Shell (CVE-2026-109618, CVE-2026-109619, CVE-2026-115560). Sebagai lapisan tambahan, `docker/php/zz-slims.ini` mematikan `session.upload_progress`, yang tidak dipakai SLiMS.

## Instalasi baru

```bash
cp .env.example .env
chmod 600 .env
# ubah DB_PASS dan DB_ROOT_PASS, atur HTTP_PORT
docker compose up -d --build
docker compose logs slims | grep -A3 "ADMIN CREDENTIALS"
```

Login sebagai `admin` dengan password dari log, lalu **segera ganti password**. Password acak dibuat setiap kali container start dan password `admin` di database masih sama dengan bawaan `install/senayan.sql`, misalnya setelah volume `dbdata` dibuat ulang.

Atau jalankan `./setup.sh`: membuat `.env` (password DB acak, `chmod 600`), menanyakan port dan token Cloudflare Tunnel, build, menunggu container healthy, lalu menampilkan password admin. Jika `.env` sudah ada, file itu tidak diubah.

Data sampel (koleksi contoh) **tidak** dimuat secara default. Untuk demo, set `LOAD_SAMPLE_DATA=true` sebelum start pertama. Skema dan data sampel hanya dimuat saat volume `dbdata` masih kosong.

## Variabel `.env`

| Variabel | Default | Keterangan |
|---|---|---|
| `APP_ENV` | `production` | `production` atau `development` (development menampilkan detail error) |
| `HTTP_BIND` | `0.0.0.0` | Set `127.0.0.1` jika HTTPS ditangani reverse proxy di host yang sama |
| `HTTP_PORT` | `8881` | Port web di host |
| `DB_NAME` | `senayan` | Nama database |
| `DB_USER` | `slims` | Akun DB aplikasi. Jangan `root` untuk instalasi baru |
| `DB_PASS` | wajib | Password akun aplikasi |
| `DB_ROOT_PASS` | wajib | Password root MariaDB, hanya dipakai container DB |
| `LOAD_SAMPLE_DATA` | `false` | Muat data sampel saat inisialisasi pertama |
| `PHP_IMAGE` | 8.3.36 | Ganti ke image 8.4.27 untuk menguji PHP 8.4 (lihat `.env.example`) |
| `MARIADB_IMAGE` | `mariadb:lts` | Pin ke versi mayor tertentu untuk produksi, mis. `mariadb:11.8` |
| `DB_WAIT_TIMEOUT` | `120` | Detik maksimal menunggu DB sebelum container aplikasi berhenti dengan error |
| `COMPOSE_PROFILES` | kosong | Isi `tunnel` untuk menjalankan Cloudflare Tunnel |
| `CLOUDFLARE_TUNNEL_TOKEN` | kosong | Token tunnel dari dashboard Cloudflare. Hanya dipakai profile `tunnel` |
| `CLOUDFLARED_IMAGE` | `cloudflare/cloudflared:latest` | Pin ke versi tertentu untuk produksi |
| `TUNNEL_SUBNET` | `172.31.250.0/29` | Subnet network `tunnel`. Ganti jika bentrok dengan jaringan lain |
| `TUNNEL_PROXY_IP` | `172.31.250.6` | IP tetap `cloudflared` di dalam `TUNNEL_SUBNET`, satu-satunya sumber yang dipercaya untuk header `CF-Connecting-IP` |

`.env` tidak ikut masuk image (`.dockerignore`) dan tidak di-commit (`.gitignore`).

## HTTPS

Container hanya melayani HTTP di port 80. Untuk produksi, pasang reverse proxy (Nginx, Caddy, atau Traefik) yang menangani sertifikat, lalu set `HTTP_BIND=127.0.0.1` agar port aplikasi tidak terbuka langsung.

### Cloudflare Tunnel (opsional)

Service `cloudflared` ada di profile `tunnel` dan tidak jalan tanpa `--profile tunnel`. HTTPS ditangani Cloudflare, server tidak perlu membuka port ke internet.

1. Di dashboard Cloudflare (*Zero Trust → Networks → Tunnels*), buat tunnel tipe *Cloudflared*, salin token-nya.
2. Tambahkan *Public Hostname*, mis. `opac.contoh.ac.id`, dengan service `HTTP` dan URL `slims:80`.
3. Isi `.env`:
   ```
   COMPOSE_PROFILES=tunnel
   CLOUDFLARE_TUNNEL_TOKEN=<token>
   HTTP_BIND=127.0.0.1
   ```
   Jangan lewatkan `HTTP_BIND=127.0.0.1`: dengan nilai bawaan `0.0.0.0`, port aplikasi tetap terbuka langsung sehingga akses bisa melewati Cloudflare (dan aturan WAF/Access di sana).
4. Jalankan seperti biasa:
   ```bash
   docker compose up -d --build
   docker compose logs cloudflared | grep -i "registered tunnel connection"
   ```

`COMPOSE_PROFILES=tunnel` adalah sakelar on/off-nya: selama terisi, semua perintah `docker compose` (`up`, `logs`, `down`) ikut menyertakan `cloudflared` tanpa `--profile`. Untuk mematikan tunnel, hentikan dulu containernya dengan `docker compose rm -sf cloudflared`, lalu kosongkan `COMPOSE_PROFILES`. Mengosongkan nilainya saja tidak menghentikan `cloudflared` yang sudah berjalan, termasuk dengan `up -d --remove-orphans`. Jika log berisi `"cloudflared tunnel run" requires the ID or name of the tunnel`, berarti `CLOUDFLARE_TUNNEL_TOKEN` kosong.

IP pengunjung: Apache (`mod_remoteip`) mengambil IP asli dari header `CF-Connecting-IP`, tetapi hanya jika request datang dari `TUNNEL_PROXY_IP`. Header yang sama dari sumber lain, termasuk lewat port `HTTP_PORT`, diabaikan. Tanpa ini semua pengunjung tercatat dengan IP `cloudflared`, dan rate limit visitor counter berlaku untuk semua orang sekaligus.

Jika sesi login harus hanya lewat HTTPS, tambahkan `session.cookie_secure = 1` di `docker/php/zz-slims.ini`. Akses langsung lewat `http://127.0.0.1:8881` setelah itu tidak bisa login.

## Upgrade dari docker-compose lama (v9.8.0 asli)

Perubahan yang perlu diperhatikan:

1. **Variabel baru wajib `DB_ROOT_PASS`.** Compose lama memakai `DB_PASS` sebagai password root dan aplikasi login sebagai `root`. Untuk volume DB yang sudah ada, isi `.env` seperti ini supaya tetap berjalan:
   ```
   DB_USER=root
   DB_PASS=<password lama>
   DB_ROOT_PASS=<password lama>
   DB_NAME=<nama DB lama>
   ```
   Disarankan setelahnya membuat akun aplikasi non-root:
   ```bash
   docker compose exec db mariadb -uroot -p -e "CREATE USER 'slims'@'%' IDENTIFIED BY '<password baru>'; GRANT ALL ON \`<nama DB>\`.* TO 'slims'@'%';"
   ```
   lalu ganti `DB_USER=slims` dan `DB_PASS=<password baru>`, dan `docker compose up -d`.
   Untuk volume DB **baru**, `DB_USER=root` tidak bisa dipakai.
2. **Port DB tidak lagi dipublikasikan.** Jika butuh akses dari host untuk backup, gunakan `docker compose exec db mariadb-dump ...`.
3. **Nama container tidak lagi tetap** (`slims`, `db`). Gunakan `docker compose exec slims ...` atau `docker compose ps`.
4. **Volume tetap sama** selama nama project Compose sama (default: nama folder). Jalankan compose baru dari folder yang sama agar volume lama terpakai.
5. Rebuild dan recreate: `docker compose up -d --build`. Restart saja tidak mengganti versi PHP.

Uji upgrade ini sudah dijalankan secara lokal: stack lama dengan data → compose baru dengan `DB_USER=root` dan volume yang sama → OPAC 200, login admin dengan password lama berhasil.

## Batasan yang diketahui

- `config/` di dalam image hanya bisa ditulis root. Pengaturan yang disimpan SLiMS ke file `config/` lewat UI admin (menu *System → Environment* dan pengaturan *Captcha*) tidak tersimpan permanen. `env.php` dan `database.php` ditulis ulang dari `.env` setiap start. Untuk captcha, mount file `config/captcha.php` sendiri.
- Folder `install/` dihapus dari image, sehingga migrasi skema saat upgrade dari versi SLiMS lama (mis. 9.7.x) belum didukung lewat Docker. Ini akan ditangani di batch rilis.
- Password admin pertama tercetak sekali di log container. Hapus atau rotasi log setelah instalasi jika log dikirim ke sistem lain.

## Operasional harian (`slims.sh`)

| Perintah | Fungsi |
|---|---|
| `./slims.sh status` | Status container, kode HTTP, CPU/memori, ukuran volume |
| `./slims.sh start` / `stop` | Start / stop semua service (data tetap) |
| `./slims.sh restart [service]` | Restart semua atau satu service (`slims`, `db`, `cloudflared`) |
| `./slims.sh logs [service]` | Ikuti 200 baris log terakhir |
| `./slims.sh db` | Konsol MariaDB sebagai akun aplikasi (kredensial diambil dari container DB, `.env` tidak dibaca) |
| `./slims.sh backup` | Dump DB ke `backups/slims-<waktu>.sql.gz` (folder `700`, file `600`, tidak di-commit) |
| `./slims.sh shell [service]` | Shell di dalam container (bawaan `slims`) |
| `./slims.sh update` | Rebuild image dan recreate container, dijalankan setelah `git pull` |
| `./slims.sh headers [url]` | Tampilkan header keamanan, mis. `./slims.sh headers https://opac.contoh.ac.id/` |

Restore backup: `gunzip -c backups/<file>.sql.gz | docker compose exec -T db sh -c 'MYSQL_PWD="$MARIADB_PASSWORD" mariadb -u"$MARIADB_USER" "$MARIADB_DATABASE"'`. Ini menimpa tabel yang ada.

## Header keamanan

`docker/apache/slims-security.conf` mengirim `X-Content-Type-Options`, `Strict-Transport-Security` (`max-age=31536000; includeSubDomains`), `Referrer-Policy: strict-origin-when-cross-origin`, dan `Permissions-Policy`. `X-Frame-Options` datang dari `.htaccess`, `Content-Security-Policy` dari SLiMS (`config/csp.php`).

- HSTS dikirim juga lewat HTTP biasa. Browser mengabaikannya di HTTP (RFC 6797), jadi akses `http://IP:8881` di LAN tetap jalan. Setelah browser menerima HSTS lewat HTTPS, domain itu (dan subdomainnya) hanya bisa dibuka lewat HTTPS selama 1 tahun.
- `Permissions-Policy` mengizinkan kamera untuk situs sendiri (foto anggota/pengguna, pemindai kamera buku tamu). Mikrofon, lokasi, pembayaran, USB, dan sensor gerak dimatikan.
- Jika HSTS juga diaktifkan di Cloudflare (*SSL/TLS → Edge Certificates*), pastikan nilainya sama agar tidak ada dua header berbeda.

## Pemeriksaan setelah deployment

```bash
./slims.sh status                      # kedua service "healthy", HTTP 200
./slims.sh headers                     # header keamanan lengkap
docker compose exec slims php -v       # 8.3.36 atau 8.4.27
```

Untuk uji lengkap sebelum rilis, lihat `docs/fork/maintenance.md` bagian uji lokal.
