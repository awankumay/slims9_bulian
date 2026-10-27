<?php
/**
 * Nilai bawaan bulian-kartu. SLiMS mengisi setelan kartu dengan teks contoh
 * berbahasa Inggris (admin/admin_template/printed_settings.inc.php); selama
 * setelan masih berisi teks contoh itu, kartu memakai nilai di bawah.
 *
 * return [kunci => [teks contoh SLiMS, nilai bulian-kartu]]
 */
return [
  'front_header1_text' => ['Library Member Card', 'Kartu Anggota Perpustakaan'],
  'front_header2_text' => ['My Library', 'UPT Perpustakaan'],
  'back_header1_text' => ['My Library', 'UPT Perpustakaan'],
  'back_header2_text' => ['My Library Full Address and Website', '[Alamat perpustakaan] · [Telepon] · [Situs/OPAC]'],
  'city' => ['City Name', '[Kota]'],
  'title' => ['Library Manager', 'Kepala UPT Perpustakaan'],
  'officials' => ['Librarian Name', '[Nama Kepala]'],
  'officials_id' => ['Librarian ID', 'NIP [18 digit]'],
  'rules' => ['This card is published by Library.', '<ul><li>Kartu wajib dibawa saat berkunjung dan meminjam.</li><li>Kartu tidak boleh dipinjamkan kepada orang lain.</li><li>Keterlambatan pengembalian dikenai sanksi sesuai aturan.</li><li>Kehilangan kartu segera dilaporkan ke petugas.</li></ul>'],
];
