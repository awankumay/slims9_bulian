# Changelog Fork SLiMS 9 Bulian

Rilis fork `awankumay/slims9_bulian` memakai format `v<versi upstream>-bulian.<n>`.
Riwayat SLiMS upstream ada di `changes.txt`.

## v9.8.0-bulian.1

Basis: SLiMS v9.8.0 (`slims/slims9_bulian` master `1d89259`). Diuji pada PHP 8.3.36 dan 8.4.27 dengan MariaDB LTS.

### Runtime dan Docker
- Image memakai `php:8.3.36-apache-bookworm` (di-pin digest). PHP 8.4.27 didukung lewat `PHP_IMAGE`. Versi ini sudah memuat perbaikan PHP2Shell (CVE-2026-109618, CVE-2026-109619, CVE-2026-115560).
- `session.upload_progress` dimatikan, versi PHP dan Apache disembunyikan, dan PHP tidak dieksekusi di `files/`, `images/`, maupun `repository/`.
- `vendor/`, `config/`, `composer.*`, `.env`, `*.pem`, `*.py`, dan `*.md` tidak bisa diakses lewat web.
- Instalasi Docker baru tidak lagi HTTP 500 (`csp.php` dan `auth.php` dibuat otomatis).
- Password DB tidak lagi tercetak di log, dan `.env` tidak masuk image.
- Database tidak dipublikasikan ke host. Aplikasi memakai akun DB non-root.
- Ditambahkan healthcheck dan restart policy. Data sampel kini opsional.
- **Perubahan bagi instalasi Docker lama:** `DB_ROOT_PASS` kini wajib. Lihat `docs/deployment/docker.md`.

### Keamanan aplikasi
- Installer menolak dijalankan ulang setelah `config/database.php` ada. Nilai config ditulis dengan escaping.
- SQL injection diperbaiki di AJAX lookup dan parameter numerik (bibliografi, laporan unduhan, langganan serial).
- Handler AJAX lookup, vocabulary, dan check-ID hanya menerima tabel `biblio`, `item`, `member`, dan `mst_*`, dan nama kolom berupa identifier.
- Stored XSS komentar OPAC diperbaiki di tampilan OPAC dan daftar komentar admin.
- Validasi CSRF:
  - Halaman Loan History Maintenance kini memakai POST dengan token CSRF.
  - Token CSRF OPAC juga diterima lewat header dan POST.
- Cookie sesi memakai `HttpOnly` dan `SameSite=Lax`.
- Halaman Environment Setting menulis `env.php` dengan aman.
- `unserialize()` tidak lagi membuat objek dari data tersimpan.
- Header `X-Content-Type-Options: nosniff` diperbaiki dari `nonsniff`.
- Halaman hasil cetak (kartu anggota, kartu katalog, label, barcode, dan laporan statistik) kini memakai nama file acak dan menghapus file lama milik pengguna. Sebelumnya nama file bisa ditebak (mis. `member_card_gen_print_result_admin.html`) sehingga data anggota bisa diunduh tanpa login.
- Folder `files/backup` (berisi dump SQL dengan hash password) ditutup dari akses web langsung; unduhan tetap lewat menu Database Backup.

### Dependensi
- PHPMailer 6.1.8 → 6.12.0.
- Guzzle 7.4.0 → 7.16.1, PSR-7 2.1.0 → 2.13.2, Promises 1.5.1 → 2.6.0.
- PhpSpreadsheet 1.29.0 → 1.30.7, ditambah `composer/pcre` 3.4.0.
- FPDI 2.6.4 → 2.6.9, baik di `lib/` maupun `composer.lock`.
- `composer.json` kini mewajibkan PHP `>=8.3 <8.5` dan memakai lisensi SPDX `GPL-3.0-or-later`.
- `composer audit` tidak menemukan advisory untuk versi-versi ini.

### Perbaikan fungsional
- Import item CSV baru tidak lagi selalu gagal (upstream #382).
- Indeks pencarian ikut dibuat saat import CSV, dan generator kode item tidak lagi melompati nomor.
- Cache thumbnail sampul kini benar-benar dipakai (upstream #381).
- Teks yang memakai `sprintf` kini ikut diterjemahkan (upstream #383).
- Tombol email notifikasi di laporan Overdue dan Due Date berfungsi untuk ID anggota yang mengandung karakter khusus (upstream #377 dan #378). Tombol Due Date tetap memakai endpoint `duedate_mail.php`.
- Template sitasi tidak lagi memunculkan warning variabel, dan pesan di halaman sukses installer diperbaiki (upstream #351).
- Upload foto user lewat file dan webcam serta pengaturan 2FA diperbaiki.
- Query `GROUP BY` kompatibel dengan `ONLY_FULL_GROUP_BY`.
- Blok "koleksi populer" di beranda OPAC tidak lagi error pada instalasi baru tanpa peminjaman (query `NOT IN ()` diperbaiki).
- Fitur Database Backup berfungsi lagi di build yang memakai `vendor/` (Docker dan paket rilis). Dependensi Composer `ifsnop/mysqldump-php` dihapus karena menimpa salinan `lib/mysqldump-php` yang sudah dimodifikasi SLiMS (konstruktor berbasis `Connection`); sebelumnya backup gagal dengan "Empty DSN string".

### Kompatibilitas PHP 8.4
- Objek barcode Zend EAN-13, EAN-8, EAN-5, UPC-A, UPC-E, Code25, dan Identcode serta validator ISBN bisa dimuat lagi di PHP 8. Plugin label barcode (Code128) tidak lagi memunculkan deprecation `imagefilledpolygon()`.
- Konstanta `E_STRICT` (deprecated di PHP 8.4) tidak lagi dipakai di `lib/Error.php`.
- Tidak ada deprecation PHP 8.4 di kode milik SLiMS.

### Lain-lain
- Ditambahkan kit uji lokal `tools/test/` dan skrip rilis `tools/release/build-release.sh`.
- Ditambahkan dokumentasi `docs/deployment/docker.md`, `docs/deployment/native.md`, dan `docs/fork/maintenance.md`.
- File contoh `js/quaggaJS/server.pem` dan `simple-https-server.py` dihapus.

### Batasan yang diketahui
- Aksi member area (reservasi, keranjang, bookmark) belum memakai token CSRF. Cookie `SameSite=Lax` sudah memitigasi serangan dari browser modern.
- Folder `install/` dihapus dari image Docker, sehingga upgrade skema dari SLiMS lama (≤ 9.7) harus lewat instalasi native atau menjalankan installer secara manual.
- Library `lib/` lain (Symfony 5.x, Carbon, gettext) masih memunculkan deprecation PHP 8.4. Statusnya hanya peringatan dan disembunyikan di mode production.
