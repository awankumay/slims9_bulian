# Pemeliharaan Fork SLiMS 9 Bulian

Panduan ini untuk pengelola fork `awankumay/slims9_bulian`: cara menaruh kustomisasi, cara menguji di mesin lokal, dan cara mengambil perubahan dari upstream `slims/slims9_bulian`.

## 1. Di mana kustomisasi ditempatkan

| Kebutuhan | Tempat | Ikut git? |
|---|---|---|
| Tema OPAC milik fork | `template/bulian-<nama>/` (salin dari `template/default/`) | Ya |
| Plugin milik fork | `plugins/bulian_<nama>/` | Ya |
| Tema/plugin lain (unduhan, uji coba) | `template/*`, `plugins/*` | Tidak |
| Kredensial, URL, mode env | `config/*.php` atau `.env` (Docker) | Tidak, jangan pernah di-commit |
| Perbaikan bug core | Langsung di kode core, lewat PR ke `master` | Ya |

`.gitignore` mengabaikan seluruh isi `template/` dan `plugins/`, kecuali `plugins/read_counter/` dari upstream serta folder yang memakai awalan `bulian-` (tema) atau `bulian_` (plugin). Jika ingin awalan lain, ubah dua baris pengecualian di `.gitignore`.

Hindari mengedit core untuk kebutuhan satu client. Gunakan hook plugin (`lib/Plugins.php`) atau tema turunan.

## 2. Uji lokal (tanpa GitHub Actions)

Semua skrip ada di `tools/test/` dan hanya membutuhkan Docker, `curl`, dan `openssl`.

```bash
# Build image dan uji lengkap di PHP 8.3.36 (default)
tools/test/run-local.sh

# Uji yang sama di PHP 8.4.27, di port lain
tools/test/run-local.sh --php 8.4 --port 18884

# Lint seluruh file PHP yang di-track git
tools/test/lint.sh php:8.3.36-cli-bookworm
tools/test/lint.sh php:8.4.27-cli-bookworm
```

`run-local.sh` membuat stack Compose sementara dengan data sampel, lalu memeriksa:

- instalasi baru: OPAC dan admin HTTP 200, versi PHP sesuai, login admin berhasil;
- rahasia dan jaringan: password DB tidak ada di log, `.env` dan `install/` tidak ada di image, port DB tidak dipublikasikan, aplikasi tidak memakai `root`;
- hardening: `session.upload_progress.enabled=0`, header `X-Content-Type-Options: nosniff`, tanpa `X-Powered-By`, `Server: Apache` tanpa versi;
- paparan file: `composer.*`, `vendor/`, `config/`, `.env`, `*.pem`, `*.py`, `*.md`, `install/` tidak tersaji, dan file PHP di `files/`/`images/` tidak dieksekusi;
- smoke test 176 URL (OPAC, API, dan semua halaman admin);
- cek library: export XLSX, kirim email SMTP (ke SMTP sink lokal), HTTP client Guzzle, PDF mPDF + FPDI, barcode Zend;
- persistensi setelah restart dan recreate.

Hasil tersimpan di `tools/test/out/<versi>/` (diabaikan git). Kode keluar = jumlah pemeriksaan yang gagal. Opsi `--keep` membiarkan stack tetap hidup untuk diperiksa manual, dan `APP_ENV=development` menampilkan detail error PHP.

### Membaca hasil smoke test

Baseline v9.8.0 di PHP 8.3.36 dan 8.4.27 (mode production) adalah **148 × 200, 1 × 302, 3 × 404, 24 × 500**. Ke-24 URL 500 adalah file pustaka atau popup admin yang memang tidak dirancang dibuka langsung tanpa parameter (mis. `submenu.php`, `File/MARC.php`, `pop_p2p.php`). Yang perlu diperhatikan adalah **perubahan** terhadap baseline: URL yang sebelumnya 200 lalu menjadi 500, atau 500 baru.

```bash
awk -F'|' '$1==500 {print $2}' tools/test/out/8336/smoke.txt | sort > /tmp/now.txt
# bandingkan dengan hasil run sebelumnya
```

## 3. Sinkronisasi dengan upstream

Remote yang dipakai:

```bash
git remote add upstream https://github.com/slims/slims9_bulian.git   # sekali saja
git fetch upstream
```

Langkah rutin (disarankan bulanan, atau segera saat ada advisory keamanan):

1. `git fetch upstream`
2. Lihat perubahan baru:
   ```bash
   git log --oneline master..upstream/master
   git log --oneline master..upstream/develop
   ```
3. Tinjau setiap commit atau PR (keamanan, kompatibilitas PHP, risiko migrasi DB). Jangan mengambil perubahan yang menghapus kompatibilitas data lama, misalnya migrasi hash password MD5 lama.
4. Buat branch kerja dari `master`, lalu ambil commit terpilih:
   ```bash
   git switch -c sync/upstream-<tanggal> master
   git cherry-pick -x <sha> ...
   ```
   Opsi `-x` mencatat SHA asal di pesan commit agar mudah dilacak.
5. Jalankan `tools/test/run-local.sh` untuk PHP 8.3 dan 8.4, lalu buka PR ke `master`.

Jika ingin cermin murni upstream di fork, buat branch `upstream-sync` yang hanya di-update dengan `git push origin upstream/master:upstream-sync`. Branch ini tidak pernah diubah manual.

## 4. Penamaan versi

Rilis fork memakai format `v<versi upstream>-bulian.<n>`, misalnya `v9.8.0-bulian.1`. Versi upstream tetap terbaca, nomor fork naik di setiap rilis internal.

## 5. Membuat rilis

1. Pastikan semua PR rilis sudah di-merge ke `master`, dan `tools/test/run-local.sh` lulus untuk PHP 8.3 dan 8.4.
2. Perbarui `SENAYAN_VERSION_TAG` di `sysconfig.inc.php` dan `CHANGELOG-bulian.md`.
3. Buat tag dan push:
   ```bash
   git tag -a v9.8.0-bulian.1 -m "SLiMS 9 Bulian fork release 1"
   git push origin v9.8.0-bulian.1
   ```
4. Bangun paket di mesin yang punya internet:
   ```bash
   tools/release/build-release.sh v9.8.0-bulian.1 --image
   ```
   Hasilnya ada di `dist/<tag>/`:
   - `slims-bulian-<tag>.tar.gz`: kode dan `vendor/`, untuk instalasi native;
   - `slims-bulian-<tag>-docker.tar.gz`: image Docker, dimuat dengan `docker load`;
   - `SHA256SUMS`.
5. Lampirkan ketiga file itu ke GitHub Release untuk tag tersebut, lalu catat versinya di inventaris client.

Paket native masih berisi `install/` untuk instalasi pertama lewat web. Hapus folder itu setelah instalasi. Installer juga menolak berjalan lagi bila `config/database.php` sudah ada.
