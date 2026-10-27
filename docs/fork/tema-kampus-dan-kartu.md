# Tema OPAC `bulian-kampus`, buku tamu pindai, dan kartu `bulian-kartu`

Panduan pemasangan untuk perpustakaan perguruan tinggi di bawah kementerian.
Desain diturunkan dari mockup "Opsi D · Kampus Kementerian".

## 1. Tema OPAC `bulian-kampus`

Lokasi: `template/bulian-kampus/`. Tema ini turunan tema `default`; pustaka
bersama (Bootstrap, Vue, ikon, gambar) tetap diambil dari
`template/default/assets/`, jadi folder `default` tidak boleh dihapus.

Aktifkan lewat **Sistem → Tema → bulian-kampus → Aktifkan**, lalu **Kustomisasi**:

| Opsi | Keterangan |
|---|---|
| Nama perguruan tinggi, nama kementerian | Tampil di strip "Laman resmi", kepala halaman, dan footer. Nama perpustakaan diambil dari Sistem → Pengaturan. |
| Logo kementerian | Path relatif (mis. `images/default/logo-kementerian.png`) atau URL `https://`. Logo kampus memakai logo perpustakaan di Sistem → Pengaturan. Logo kementerian tampil di **kanan** logo kampus. |
| Warna utama, warna aksen | Pilihan warna yang aman kontras. |
| Jam layanan, maklumat pelayanan, alamat | Teks beranda dan footer. |
| Tautan standar pelayanan, SKM, hasil SKM, SP4N-LAPOR!, PPID, kebijakan privasi | Kosong = tidak ditampilkan. |
| Tautan repositori, e-resources, bebas pustaka, unggah karya akhir, cek similaritas, SSO | Kartu "Layanan cepat" dan menu hanya muncul bila tautannya diisi. SLiMS belum punya fitur ini; arahkan ke sistem kampus yang sudah ada. |

Fitur aksesibilitas: ukuran teks (A−/A/A+), mode kontras tinggi (tersimpan di
peramban), tautan "Langsung ke konten", fokus keyboard yang terlihat, dan
pilihan bahasa ID/EN. Font Lato dan Saira (SIL OFL) disertakan lokal sehingga
OPAC tetap rapi di jaringan tanpa internet.

Opsi "Enable this theme for mobile?" sebaiknya **Yes**. Bila **No**, pengunjung
ponsel dialihkan ke tema `lightweight`.

## 2. Buku tamu dengan pindai kartu

Alamat kiosk: `index.php?p=visitor` (bisa ditambah `&room=KODE`).

- **Pemindai USB (barcode atau QR)**: siap pakai. Kolom ID selalu fokus;
  pemindai mengetik nomor anggota lalu Enter.
- **Kamera (opsional)**: aktifkan opsi tema *Buku tamu: izinkan pindai kamera*.
  Membaca QR, Code128, Code39, dan EAN-13 lewat `html5-qrcode` 2.3.8
  (Apache-2.0, disertakan di `assets/js/vendor/`). Peramban hanya mengizinkan
  kamera di **HTTPS** atau `localhost`; di alamat IP LAN biasa tombol kamera
  menampilkan pesan tersebut.
- **Pindai ganda**: kartu yang sama diabaikan selama *N* detik (opsi tema,
  bawaan 10) sehingga kamera yang terus melihat kartu tidak mengirim data
  berulang.
- **Isi QR**: nomor anggota polos (bawaan kartu `bulian-kartu`). Kiosk juga
  menerima URL berisi `member_id=`/`id=` dan awalan `MEMBER:`/`ANGGOTA:`.
- **Tamu**: ID yang tidak terdaftar membuka form tamu (nama + instansi).
- Pencatatan tetap lewat `lib/contents/visitor.inc.php`; aturan IP kiosk
  (`allowed_counter_ip`) dan batas kunjungan berlaku seperti biasa.

## 3. Kartu anggota `bulian-kartu`

Lokasi: `files/membercard/bulian-kartu/`. Aktifkan lewat **Keanggotaan → Cetak
Kartu Anggota → Member card print settings → bulian-kartu → Activate**, lalu
**Customize**.

- Ukuran CR80 (8,6 × 5,4 cm), dicetak sisi depan dan belakang berdampingan.
  Saat mencetak, pilih skala 100% (matikan "Fit to page").
- Depan: logo, nama perpustakaan dan perguruan tinggi, jenis anggota, foto,
  nama, nomor anggota, masa berlaku, instansi/prodi, barcode Code128.
- Belakang: ketentuan anggota, QR buku tamu, alamat, kota/tanggal, jabatan,
  tanda tangan dan stempel, nama dan NIP pejabat.
- Barcode dan QR digambar sebagai SVG di halaman cetak, jadi tidak bergantung
  pada `images/barcodes/*.png`.
- Tanda tangan dan stempel: simpan `signature.png` dan `stamp.png`
  (transparan) di `files/membercard/bulian-kartu/images/`. Bila tidak ada,
  ruang tanda tangan dibiarkan kosong.
- Selama setelan masih berisi teks contoh bawaan SLiMS ("Library Member
  Card", "My Library", dst.), kartu memakai teks Bahasa Indonesia dari
  `defaults.inc.php`.

### Instalasi Docker yang sudah berjalan

`files/` disimpan di volume `appfiles`. Volume yang sudah ada tidak ikut
diperbarui saat image baru dipasang, sehingga `bulian-kartu` belum muncul.
Salin sekali secara manual:

```
docker compose cp files/membercard/bulian-kartu slims:/var/www/html/files/membercard/
docker compose exec slims chown -R www-data:www-data /var/www/html/files/membercard/bulian-kartu
```
