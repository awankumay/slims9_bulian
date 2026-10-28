# Changelog Fork SLiMS 9 Bulian

Rilis fork `awankumay/slims9_bulian` memakai format `v<versi upstream>-bulian.<n>`.
Riwayat SLiMS upstream ada di `changes.txt`.

## Belum dirilis

### Tema dan layanan
- Tema OPAC baru `bulian-kampus` untuk perpustakaan kampus di bawah kementerian: kepala halaman resmi dua logo (kampus kiri, kementerian kanan), menu utama, pencarian di beranda, kartu layanan cepat, jam layanan, maklumat pelayanan, partisipasi publik (SKM, SP4N-LAPOR!), dan footer layanan publik (standar pelayanan, PPID, kebijakan privasi). Semua teks instansi, warna, dan tautan diatur dari Kustomisasi tema.
- Aksesibilitas tema `bulian-kampus`: ukuran teks, mode kontras tinggi, tautan "Langsung ke konten", fokus keyboard terlihat, dan font lokal (Lato, Saira).
- Buku tamu tema `bulian-kampus` menjadi kiosk pindai kartu: pemindai USB barcode/QR, kamera opsional (QR, Code128, Code39, EAN-13; butuh HTTPS), penolakan pindai ganda, dan form tamu otomatis untuk ID yang tidak terdaftar.
- Tema kartu anggota baru `bulian-kartu` (CR80) dengan barcode Code128 di depan dan QR buku tamu di belakang, keduanya digambar sebagai SVG.
- Panduan: `docs/fork/tema-kampus-dan-kartu.md`.

- Tema `bulian-kampus` disesuaikan dengan mockup UI: kepala halaman lengket (hanya menu yang tetap terlihat saat digulir, bayangan tipis, jangkar tidak tertutup), tombol "Pencarian spesifik" dan tombol cari di kolom pencarian, koleksi terbaru dengan pengarang (dirender server), jam layanan berbentuk tabel, kartu hasil pencarian dan saringan baru, detail bibliografi dengan tabel ketersediaan, dan kartu profil di area anggota (tanggal kembali yang dekat/lewat diberi warna).
- Buku tamu `bulian-kampus` punya tiga tata letak (`split`, `center`, `camera`) yang dipilih di Kustomisasi tema atau per perangkat dengan `index.php?p=visitor&layout=...`; layout `camera` menyalakan kamera otomatis. Nama ruang (`&room=`) tampil di layar, dan baris status menampilkan ringkasan (mis. "Tercatat · M0001").

### Perbaikan
- Halaman error bertema untuk URL tidak valid dan error server (400, 401, 403, 404, 405, 408, 410, 413, 414, 429, 500, 502, 503, 504) lewat `errorpage.php`, menggantikan teks polos Apache "Not Found". Aktif di image Docker dan di `.htaccess` (Apache native, SLiMS di root domain); contoh Nginx di `docs/deployment/nginx-slims.conf`. Klien API/AJAX mendapat JSON. Halaman OPAC `?p=` yang tidak dikenal kini menampilkan 404 di dalam tema (sebelumnya "something wrong."). Halaman error mode production (`template/errorproduction.php`) memakai tampilan yang sama, dan URL di versi AJAX-nya kini di-escape.
- Tema `bulian-kampus`: teks tombol Bootstrap (mis. "Lihat kartu anggota", "Keluar" di area anggota) tidak lagi biru di atas biru; tautan footer "Layanan" tidak lagi 404.
- Tema `bulian-kampus`: tombol pilihan pencarian di beranda (Pengarang, Subjek, ISBN/ISSN, Pencarian Spesifik) tidak lagi berteks putih di atas putih.
- Tambah/ubah anggota: pesan jelas untuk nomor anggota ganda, isian terlalu panjang, dan tanggal tidak valid (sebelumnya hanya "Database insert error."). Tanggal kedaluwarsa kosong saat "Auto Set" tidak dicentang kini dihitung dari periode jenis keanggotaan. Kolom form diberi batas panjang.
- Setelan cetak kartu anggota dari data contoh `install/` (panjang string serialisasi salah) tidak lagi membuat halaman tema kartu HTTP 500; data contohnya juga diperbaiki.

### Keamanan buku tamu (`lib/Visitor.php`)
- Pembatasan IP kiosk tidak lagi bisa dilewati dengan header `Host: localhost`, dan pencocokan IP kini persis. Format aturan: IP persis, wildcard `192.168.1.*`, awalan `192.168.1.`, atau CIDR `192.168.1.0/24`. **Perubahan perilaku:** aturan lama seperti `192.168.1` (tanpa titik/bintang di akhir) tidak lagi cocok sebagai awalan; ubah menjadi `192.168.1.*`.
- Pembatas laju POST buku tamu per IP (`$sysconf['visitor_rate_limit']`, bawaan 60 per menit, 0 = mati). Melebihi batas → HTTP 429.
- Input buku tamu divalidasi: nilai non-teks dan `institution` yang hilang tidak lagi memicu HTTP 500, nama/instansi dibatasi 100 karakter, karakter kontrol dibuang, dan tidak lagi tersimpan dengan garis miring terbalik (`O\'Neil`).
- Kode ruang (`?room=`) harus terdaftar di master ruang pengunjung.
- Nama pengunjung di-escape di pesan respons; respons JSON memuat `code` (`checked_in`, `already_checked_in`, `expired`, `need_institution`, `rate_limited`, `csrf`, `empty`, `error`), dan kiosk `bulian-kampus` memakainya.
- Hook plugin `MEMBER_ON_VISIT` kini menerima data anggota (sebelumnya `false`).

### Docker
- Header keamanan baru di image: `Strict-Transport-Security` (1 tahun, `includeSubDomains`), `Referrer-Policy: strict-origin-when-cross-origin`, dan `Permissions-Policy` (kamera hanya untuk situs sendiri; mikrofon, lokasi, pembayaran, USB, sensor gerak dimatikan). Butuh rebuild image.
- Script operasional `setup.sh` (instalasi pertama: `.env` dengan password DB acak, build, tunggu healthy, tampilkan password admin) dan `slims.sh` (status, start/stop/restart, logs, konsol DB, backup DB, shell, update, cek header).

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
  - Aksi area member yang mengubah data (tambah/hapus bookmark, tambah/hapus/kosongkan keranjang, kirim reservasi, ganti sandi) kini memvalidasi token CSRF per-sesi. Token dikirim otomatis lewat header `X-SLiMS-Member-CSRF` pada setiap AJAX member dan sebagai field tersembunyi pada form. Penghapusan keranjang lewat GET (`rm_biblio`) kini juga butuh token sehingga tidak bisa dipicu lewat navigasi lintas situs.
- Endpoint API statistik agregat (`biblio/total/all`, `item/total/*`, `loan/summary`, `loan/getdate`, `loan/summary/[tanggal]`) kini mewajibkan sesi staf login dan menolak pemanggil anonim (HTTP 403). Dashboard admin membaca angka-angka tersebut langsung di server, sehingga tampilan tidak berubah sementara jumlah koleksi/item/sirkulasi tidak lagi bisa diambil tanpa autentikasi.
- Cookie sesi memakai `HttpOnly` dan `SameSite=Lax`.
- Halaman Environment Setting menulis `env.php` dengan aman.
- `unserialize()` tidak lagi membuat objek dari data tersimpan.
- Reflected XSS tanpa login di `admin/modules/circulation/ajax_action.php` (quick return) ditutup: endpoint kini mewajibkan sesi staf login, dan nilai input dikeluarkan lewat `rawurlencode` + `json_encode` sehingga tidak bisa menembus blok `<script>`.
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
- Folder `install/` dihapus dari image Docker, sehingga upgrade skema dari SLiMS lama (≤ 9.7) harus lewat instalasi native atau menjalankan installer secara manual.
- Library `lib/` lain (Symfony 5.x, Carbon, gettext) masih memunculkan deprecation PHP 8.4. Statusnya hanya peringatan dan disembunyikan di mode production.
