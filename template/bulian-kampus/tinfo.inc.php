<?php
/**
 * Tema OPAC "bulian-kampus" untuk perpustakaan perguruan tinggi di bawah
 * kementerian. Turunan tema default SLiMS; pustaka bersama (bootstrap, vue,
 * ikon) diambil dari template/default/assets.
 */

$sysconf['template']['base'] = 'php';
$sysconf['template']['responsive'] = 1;

// identitas instansi
$sysconf['template']['bk_parent_name'] = '[Nama Politeknik / Institut / Universitas]';
$sysconf['template']['bk_ministry_name'] = '[Nama Kementerian]';
$sysconf['template']['bk_ministry_logo'] = '';
$sysconf['template']['bk_primary'] = '#0067B8';
$sysconf['template']['bk_accent'] = '#F9DA00';

// layanan dan kepatuhan
$sysconf['template']['bk_hours'] = 'Senin–Kamis 07.30–16.00 · Jumat 07.30–16.30 WIB';
$sysconf['template']['bk_maklumat'] = 'Dengan ini kami menyatakan sanggup menyelenggarakan pelayanan sesuai standar pelayanan yang telah ditetapkan dan apabila tidak menepati janji ini, kami siap menerima sanksi sesuai peraturan perundang-undangan yang berlaku.';
$sysconf['template']['bk_link_standar'] = '';
$sysconf['template']['bk_link_skm'] = '';
$sysconf['template']['bk_link_skm_result'] = '';
$sysconf['template']['bk_link_lapor'] = 'https://www.lapor.go.id';
$sysconf['template']['bk_link_ppid'] = '';
$sysconf['template']['bk_link_privacy'] = '';

// layanan akademik (kosong = kartu tidak ditampilkan)
$sysconf['template']['bk_link_repository'] = '';
$sysconf['template']['bk_link_eresources'] = '';
$sysconf['template']['bk_link_bebaspustaka'] = '';
$sysconf['template']['bk_link_upload'] = '';
$sysconf['template']['bk_link_similarity'] = '';
$sysconf['template']['bk_link_sso'] = '';

$sysconf['template']['bk_address'] = '[Alamat lengkap perpustakaan] · [Telepon] · [Email resmi]';
$sysconf['template']['bk_new_collection'] = 1;
$sysconf['template']['bk_theme_credit'] = 'show';

// buku tamu (kiosk)
$sysconf['template']['bk_kiosk_layout'] = 'split';
$sysconf['template']['bk_kiosk_camera'] = 0;
$sysconf['template']['bk_kiosk_repeat_seconds'] = 10;
$sysconf['template']['visitor_log_voice'] = 0;

// nilai lama tema default yang masih dibaca oleh beberapa bagian SLiMS
$sysconf['template']['classic_library_subname'] = 1;
$sysconf['template']['classic_library_disableslide'] = 1;
$sysconf['template']['classic_popular_collection'] = 0;
$sysconf['template']['classic_new_collection'] = 1;
$sysconf['template']['classic_new_collection_item'] = 6;
$sysconf['template']['classic_popular_collection_item'] = 6;
$sysconf['template']['classic_top_reader'] = 0;
$sysconf['template']['classic_suggestion'] = 1;
$sysconf['template']['classic_map'] = 0;

$bk_yes_no = [[1, __('Show')], [0, __('Hide')]];
$bk_link = function ($dbfield, $label) {
    return ['dbfield' => $dbfield, 'label' => $label, 'type' => 'text', 'default' => '', 'width' => '100'];
};

$sysconf['template']['option'][$sysconf['template']['theme']] = [
    'responsive' => [
        'dbfield' => 'responsive',
        'label' => __('Enable this theme for mobile?'),
        'type' => 'dropdown',
        'default' => 1,
        'data' => [
            [1, __('Yes, please!')],
            [0, __('No, I want use lighweight theme')]
        ]
    ],
    'parent' => [
        'dbfield' => 'bk_parent_name',
        'label' => 'Nama perguruan tinggi',
        'type' => 'text',
        'default' => $sysconf['template']['bk_parent_name'],
        'width' => '100'
    ],
    'ministry' => [
        'dbfield' => 'bk_ministry_name',
        'label' => 'Nama kementerian',
        'type' => 'text',
        'default' => $sysconf['template']['bk_ministry_name'],
        'width' => '100'
    ],
    'ministry-logo' => [
        'dbfield' => 'bk_ministry_logo',
        'label' => 'Logo kementerian',
        'info' => 'Unggah file, atau isi URL (https://...) / path (mis. images/default/logo-kementerian.png). File unggahan menggantikan isian. Kosongkan isian = tidak tampil.',
        'type' => 'image',
        'default' => '',
        'width' => '100'
    ],
    'primary' => [
        'dbfield' => 'bk_primary',
        'label' => 'Warna utama',
        'type' => 'dropdown',
        'default' => '#0067B8',
        'data' => [
            ['#0067B8', 'Biru'],
            ['#0A5C36', 'Hijau'],
            ['#7A1F2B', 'Marun'],
            ['#3B3F8F', 'Indigo'],
            ['#1F2937', 'Arang'],
        ]
    ],
    'accent' => [
        'dbfield' => 'bk_accent',
        'label' => 'Warna aksen',
        'type' => 'dropdown',
        'default' => '#F9DA00',
        'data' => [
            ['#F9DA00', 'Kuning'],
            ['#F2B705', 'Emas'],
            ['#9BD1F2', 'Biru muda'],
        ]
    ],
    'hours' => [
        'dbfield' => 'bk_hours',
        'label' => 'Jam layanan',
        'type' => 'text',
        'default' => $sysconf['template']['bk_hours'],
        'width' => '100'
    ],
    'maklumat' => [
        'dbfield' => 'bk_maklumat',
        'label' => 'Maklumat pelayanan',
        'type' => 'longtext',
        'default' => $sysconf['template']['bk_maklumat'],
        'width' => '100',
        'max' => '1000'
    ],
    'address' => [
        'dbfield' => 'bk_address',
        'label' => 'Alamat dan kontak (footer)',
        'type' => 'text',
        'default' => $sysconf['template']['bk_address'],
        'width' => '100'
    ],
    'link-standar' => $bk_link('bk_link_standar', 'Tautan standar pelayanan'),
    'link-skm' => $bk_link('bk_link_skm', 'Tautan survei kepuasan masyarakat (SKM)'),
    'link-skm-result' => $bk_link('bk_link_skm_result', 'Tautan hasil SKM'),
    'link-lapor' => $bk_link('bk_link_lapor', 'Tautan pengaduan SP4N-LAPOR!'),
    'link-ppid' => $bk_link('bk_link_ppid', 'Tautan PPID / informasi publik'),
    'link-privacy' => $bk_link('bk_link_privacy', 'Tautan kebijakan privasi (UU PDP)'),
    'link-repository' => $bk_link('bk_link_repository', 'Tautan repositori institusi'),
    'link-eresources' => $bk_link('bk_link_eresources', 'Tautan e-resources / e-journal'),
    'link-bebaspustaka' => $bk_link('bk_link_bebaspustaka', 'Tautan layanan bebas pustaka'),
    'link-upload' => $bk_link('bk_link_upload', 'Tautan unggah mandiri karya akhir'),
    'link-similarity' => $bk_link('bk_link_similarity', 'Tautan pengajuan cek similaritas'),
    'link-sso' => $bk_link('bk_link_sso', 'Tautan masuk SSO kampus (kosong = pakai Area Anggota)'),
    'new-collection' => [
        'dbfield' => 'bk_new_collection',
        'label' => __('New Collection'),
        'type' => 'dropdown',
        'default' => 1,
        'data' => $bk_yes_no
    ],
    'theme-credit' => [
        'dbfield' => 'bk_theme_credit',
        'label' => 'Kredit pembuat tema di footer (CV. Mitra Coretech)',
        'type' => 'dropdown',
        'default' => 'show',
        'data' => [
            ['show', 'Tampil'],
            ['subtle', 'Samar (teks kecil, tautan tetap terlihat)'],
            ['hide', 'Sembunyi (hanya meta tag "designer" di kepala halaman)'],
        ]
    ],
    'kiosk-layout' => [
        'dbfield' => 'bk_kiosk_layout',
        'label' => 'Buku tamu: tata letak (per perangkat bisa ditimpa dengan index.php?p=visitor&layout=split|center|camera)',
        'type' => 'dropdown',
        'default' => 'split',
        'data' => [
            ['split', 'Panel terbelah: form terang + sapaan gelap (layar lebar)'],
            ['center', 'Ringkas: satu panel di tengah (tablet tegak)'],
            ['camera', 'Kamera utama: pindai QR lewat webcam, gelap (butuh HTTPS)'],
        ]
    ],
    'kiosk-camera' => [
        'dbfield' => 'bk_kiosk_camera',
        'label' => 'Buku tamu: izinkan pindai kamera (QR & barcode, butuh HTTPS)',
        'type' => 'dropdown',
        'default' => 0,
        'data' => [[1, 'Ya'], [0, 'Tidak (pemindai USB saja)']]
    ],
    'kiosk-repeat' => [
        'dbfield' => 'bk_kiosk_repeat_seconds',
        'label' => 'Buku tamu: abaikan pindai ulang kartu yang sama selama (detik)',
        'type' => 'text',
        'default' => 10,
        'width' => '20'
    ],
    'voice' => [
        'dbfield' => 'visitor_log_voice',
        'label' => __('Visitor Log Voice'),
        'type' => 'dropdown',
        'default' => 0,
        'data' => [[1, __('Yes')], [0, __('No')]]
    ],
];
