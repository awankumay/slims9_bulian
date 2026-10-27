# Deployment SLiMS tanpa Docker (Apache/Nginx + PHP native)

Panduan ini untuk memasang fork di server biasa (tanpa Docker), misalnya VPS atau
server lokal perpustakaan. Sudah diuji di Ubuntu 24.04 dengan Apache 2.4, PHP
8.3.36 (mod_php), dan MariaDB 10.11. PHP 8.4.27 juga didukung.

## 1. Kebutuhan

| Komponen | Versi | Catatan |
|---|---|---|
| PHP | 8.3.x atau 8.4.x | Ekstensi: `intl gd gettext mbstring zip mysqli pdo_mysql curl xml` |
| Web server | Apache 2.4 (mod_php/FPM) atau Nginx + PHP-FPM | `mod_rewrite` dan `mod_headers` aktif untuk Apache |
| Database | MariaDB 10.5+ atau MySQL 8 | |
| Composer | 2.x | Untuk memasang `vendor/` jika memakai source, bukan paket rilis |

Paket rilis dari `tools/release/build-release.sh` sudah berisi `vendor/`, jadi
Composer tidak wajib di server produksi.

## 2. Langkah instalasi

```bash
# 1. Taruh kode di document root, misalnya /var/www/slims
#    (dari paket rilis slims-bulian-<tag>.tar.gz, atau git clone + composer install --no-dev)

# 2. Siapkan direktori yang bisa ditulis web server
chown -R www-data:www-data files images repository config

# 3. Buat database dan akun non-root
mysql -uroot -p -e "CREATE DATABASE senayan CHARACTER SET utf8mb4;
  CREATE USER 'slims'@'localhost' IDENTIFIED BY '<password-kuat>';
  GRANT ALL ON senayan.* TO 'slims'@'localhost';"

# 4. Buka http://<server>/ di browser, ikuti installer:
#    System check -> Install SLiMS -> isi koneksi DB -> buat akun super user
#    Password super user minimal 12 karakter, kombinasi huruf besar/kecil,
#    angka, dan simbol, serta tidak memuat username.

# 5. WAJIB: hapus folder install setelah selesai
rm -rf install
```

Login sebagai super user, lalu segera ubah profil dan periksa menu *System*.

## 3. Socket MySQL (penting untuk PHP yang di-compile sendiri)

Jika PHP di-compile dari source (bukan dari paket distribusi), lokasi socket
MySQL sering tidak terset, sehingga koneksi ke host `localhost` gagal dengan
pesan *"No such file or directory"* meski DB berjalan. Set socket di `php.ini`:

```ini
mysqli.default_socket = /run/mysqld/mysqld.sock
pdo_mysql.default_socket = /run/mysqld/mysqld.sock
```

Cek lokasi socket dengan `mysqladmin -uroot variables | grep socket`. PHP dari
paket apt/yum biasanya sudah benar dan tidak perlu langkah ini. Alternatif lain:
pakai `127.0.0.1` (bukan `localhost`) sebagai DATABASE HOST di installer, yang
memaksa koneksi TCP, bukan socket.

## 4. Hardening (setara image Docker)

Image Docker memasang beberapa pengaman di level web server. Untuk instalasi
native, terapkan hal yang sama:

- **Jangan eksekusi PHP di folder upload.** `.htaccess` bawaan di `files/`,
  `images/`, dan `repository/` sudah mematikan engine PHP. Pastikan
  `AllowOverride All` aktif agar `.htaccess` terbaca, atau salin aturannya ke
  konfigurasi vhost.
- **Blokir folder sensitif dari web:** `install/` (hapus), `config/`, `vendor/`,
  `upgrade/`. Contoh untuk Apache vhost:
  ```apache
  <DirectoryMatch "/(config|vendor|upgrade)/">
      Require all denied
  </DirectoryMatch>
  ```
- **File backup DB.** Backup ditulis ke `files/backup/`. `.htaccess` di folder
  itu sudah menolak semua akses web langsung; unduhan tetap jalan lewat menu
  *System -> Database Backup*. Pastikan `AllowOverride` aktif, atau tambahkan
  aturan `Require all denied` untuk `files/backup/` di vhost.
- **Sembunyikan versi PHP/Apache** (`expose_php = Off`, `ServerTokens Prod`,
  `ServerSignature Off`) dan set `session.upload_progress.enabled = 0` di
  `php.ini` (mitigasi PHP2Shell; tetap gunakan PHP >= 8.3.36 / 8.4.27).

Setelah deployment, verifikasi cepat:

```bash
curl -sI http://127.0.0.1/ | grep -i "server\|x-content-type\|x-powered"
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1/config/database.php   # harus 403
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1/install/              # harus 404
```

## 5. HTTPS

Pasang reverse proxy atau sertifikat langsung di Apache/Nginx. SLiMS melayani
HTTP; untuk cookie aman tambahkan `session.cookie_secure = 1` di `php.ini` saat
sudah memakai HTTPS.

## 6. Kustomisasi kartu anggota

Teks default di kartu anggota (nama perpustakaan, alamat, kota, pejabat) diisi
lewat *Membership -> Member Card Printing -> Member card print settings*.
Template *Old* masih memuat teks contoh "My Library" dan `slims.web.id` di sisi
belakang; ganti lewat pengaturan yang sama sebelum kartu dipakai.

## 7. Uji dan rilis

Lihat `docs/fork/maintenance.md` untuk uji lokal dan prosedur rilis.
