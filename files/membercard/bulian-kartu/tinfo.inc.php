<?php
/**
 * Tema kartu anggota "bulian-kartu": kartu CR80 (8,56 × 5,4 cm), sisi depan
 * dengan barcode Code128, sisi belakang dengan QR untuk buku tamu.
 * Barcode dan QR keduanya berisi nomor anggota (member_id) apa adanya.
 */

$bk = $sysconf['print'][$theme_key] ?? [];
foreach (include __DIR__ . '/defaults.inc.php' as $bk_key => [$bk_stock, $bk_own]) {
  $bk_now = html_entity_decode((string)($bk[$bk_key] ?? ''), ENT_QUOTES, 'UTF-8');
  if ($bk_now === '' || strpos($bk_now, $bk_stock) !== false) $bk[$bk_key] = $bk_own;
}

require_once __DIR__ . '/card-lib.inc.php';
[$bk_w, $bk_h] = bk_card_size($bk, false);

$sysconf['print'][$theme_key] = [
  'template' => [
    'dbfield' => 'template',
    'label' => 'Template',
    'type' => 'hidden',
    'default' => 'bulian-kartu'
  ],
  'front_header1_text' => [
    'dbfield' => 'data[front_header1_text]',
    'label' => 'Judul kartu',
    'type' => 'text',
    'default' => $bk['front_header1_text'] ?? 'Kartu Anggota Perpustakaan',
    'width' => '100'
  ],
  'front_header2_text' => [
    'dbfield' => 'data[front_header2_text]',
    'label' => 'Nama perpustakaan',
    'type' => 'text',
    'default' => $bk['front_header2_text'] ?? 'UPT Perpustakaan',
    'width' => '100'
  ],
  'parent_text' => [
    'dbfield' => 'data[parent_text]',
    'label' => 'Nama perguruan tinggi / instansi induk',
    'type' => 'text',
    'default' => $bk['parent_text'] ?? '[Nama Perguruan Tinggi]',
    'width' => '100'
  ],
  'back_header1_text' => [
    'dbfield' => 'data[back_header1_text]',
    'label' => 'Nama di sisi belakang',
    'type' => 'text',
    'default' => $bk['back_header1_text'] ?? 'UPT Perpustakaan',
    'width' => '100'
  ],
  'back_header2_text' => [
    'dbfield' => 'data[back_header2_text]',
    'label' => 'Alamat, telepon, situs',
    'type' => 'text',
    'default' => $bk['back_header2_text'] ?? '[Alamat perpustakaan] · [Telepon] · [Situs/OPAC]',
    'width' => '100'
  ],
  'primary_color' => [
    'dbfield' => 'data[primary_color]',
    'label' => 'Warna utama',
    'type' => 'text',
    'width' => '20',
    'default' => $bk['primary_color'] ?? '#0067B8',
    'class' => 'colorpicker'
  ],
  'accent_color' => [
    'dbfield' => 'data[accent_color]',
    'label' => 'Warna aksen',
    'type' => 'text',
    'width' => '20',
    'default' => $bk['accent_color'] ?? '#F9DA00',
    'class' => 'colorpicker'
  ],
  'box_width' => [
    'dbfield' => 'data[box_width]',
    'label' => __('Box Width') . ' (cm)',
    'type' => 'text',
    'default' => $bk_w,
    'width' => '20'
  ],
  'box_height' => [
    'dbfield' => 'data[box_height]',
    'label' => __('Box Height') . ' (cm)',
    'type' => 'text',
    'default' => $bk_h,
    'width' => '20'
  ],
  'show_qr' => [
    'dbfield' => 'data[show_qr]',
    'label' => 'QR buku tamu di sisi belakang',
    'type' => 'dropdown',
    'default' => $bk['show_qr'] ?? 1,
    'data' => [[1, 'Tampilkan'], [0, 'Sembunyikan']]
  ],
  'qr_label' => [
    'dbfield' => 'data[qr_label]',
    'label' => 'Teks di bawah QR',
    'type' => 'text',
    'default' => $bk['qr_label'] ?? 'Pindai di buku tamu',
    'width' => '50'
  ],
  'rules' => [
    'dbfield' => 'data[rules]',
    'label' => __('Rules'),
    'type' => 'ckeditor',
    'default' => $bk['rules'],
  ],
  'city' => [
    'dbfield' => 'data[city]',
    'label' => __('City'),
    'type' => 'text',
    'default' => $bk['city'] ?? '[Kota]'
  ],
  'title' => [
    'dbfield' => 'data[title]',
    'label' => __('Title'),
    'type' => 'text',
    'default' => $bk['title'] ?? 'Kepala UPT Perpustakaan'
  ],
  'officials' => [
    'dbfield' => 'data[officials]',
    'label' => __('Officials'),
    'type' => 'text',
    'default' => $bk['officials'] ?? '[Nama Kepala]'
  ],
  'officials_id' => [
    'dbfield' => 'data[officials_id]',
    'label' => __('Officials ID'),
    'type' => 'text',
    'default' => $bk['officials_id'] ?? 'NIP [18 digit]'
  ],
  'items_per_row' => [
    'dbfield' => 'data[items_per_row]',
    'label' => 'Kartu per baris (lembar cetak)',
    'type' => 'dropdown',
    'default' => $bk['items_per_row'] ?? 1,
    'data' => [[1, '1 (depan + belakang berdampingan)'], [2, '2'], [3, '3']]
  ],
];
