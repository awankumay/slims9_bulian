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

Kepala halaman **lengket (sticky)**: saat digulir, strip dan identitas ikut
tergulir dan hanya menu utama yang tetap di atas layar, dengan bayangan tipis.
Tingginya diukur ulang oleh `assets/js/bulian-kampus.js` bila ukuran teks atau
lebar layar berubah, dan tautan jangkar (`#...`) tidak tertutup menu. Tanpa
JavaScript kepala halaman kembali biasa (tidak lengket).

Beranda menampilkan **Koleksi terbaru** langsung dari basis data (judul,
sampul, pengarang pertama; jumlah dari opsi lama `classic_new_collection_item`,
bawaan 6). Area anggota menampilkan kartu ringkas data keanggotaan di atas tab
bawaan SLiMS; tanggal harus kembali yang tinggal 3 hari atau lewat diberi warna.

Opsi "Enable this theme for mobile?" sebaiknya **Yes**. Bila **No**, pengunjung
ponsel dialihkan ke tema `lightweight`.

## 2. Buku tamu dengan pindai kartu

Alamat kiosk: `index.php?p=visitor` (bisa ditambah `&room=KODE`; nama ruang
tampil di pojok layar bila kodenya terdaftar).

Ada tiga tata letak. Pilih bawaan di Kustomisasi tema (*Buku tamu: tata
letak*), atau timpa per perangkat dengan parameter `layout`, mis. layar
kamera di pintu masuk dan layar biasa di meja sirkulasi:

| `layout` | Tampilan | Cocok untuk |
|---|---|---|
| `split` (bawaan) | Panel pindai terang di kiri, sapaan gelap di kanan | Monitor lebar dengan pemindai USB |
| `center` | Satu panel di tengah, hasil di atas form | Tablet tegak, layar kecil |
| `camera` | Kamera utama gelap, petunjuk 3 langkah di kanan; kamera langsung menyala | Webcam / tablet tanpa pemindai USB (butuh HTTPS) |

Contoh: `index.php?p=visitor&room=R2&layout=camera`. Nilai lain diabaikan.
Layout `camera` selalu memuat pembaca kamera; kolom ID untuk pemindai USB
tetap ada di bawah kamera.

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

### Keamanan kiosk

- **Sistem → Pengaturan → Allowed Counter IP**: isi IP perangkat kiosk saja,
  dipisah `;`. Format: `192.168.1.20`, `192.168.1.*`, `192.168.1.` atau
  `192.168.1.0/24`. Akses dari server sendiri (`127.0.0.1`/`::1`) cocok
  dengan aturan `127.0.0.1` atau `localhost`.
- **Visitor Limitation by Time**: aktifkan. Penahan pindai ganda di kiosk hanya
  berjalan di peramban; batas kunjungan di server yang mencegah data ganda.
- `$sysconf['visitor_rate_limit']` (bawaan 60 POST per menit per IP) menahan
  tebakan nomor anggota. Naikkan bila satu kiosk melayani antrean sangat padat.
- Server produksi: `config/env.php` harus `production` agar pesan error teknis
  tidak terkirim ke kiosk.
- Mode load balancer (`config/loadbalanced.php`) mengambil IP dari
  `X-Forwarded-For`. Aktifkan hanya bila proxy menimpa header itu, karena
  klien bisa memalsukannya.

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
