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

Login sebagai `admin` dengan password dari log, lalu **segera ganti password**. Password acak ini hanya dibuat sekali (penanda `files/.admin_initialized`).

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

`.env` tidak ikut masuk image (`.dockerignore`) dan tidak di-commit (`.gitignore`).

## HTTPS

Container hanya melayani HTTP di port 80. Untuk produksi, pasang reverse proxy (Nginx, Caddy, atau Traefik) yang menangani sertifikat, lalu set `HTTP_BIND=127.0.0.1` agar port aplikasi tidak terbuka langsung.

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

## Pemeriksaan setelah deployment

```bash
docker compose ps                      # kedua service "healthy"
curl -sI http://127.0.0.1:8881/ | grep -i "server\|x-content-type\|x-powered"
docker compose exec slims php -v       # 8.3.36 atau 8.4.27
```

Untuk uji lengkap sebelum rilis, lihat `docs/fork/maintenance.md` bagian uji lokal.
