<?php
/**
 * bulian-kampus: buku tamu (kiosk) berbasis pindai kartu.
 *
 * - Pemindai USB (barcode 1D atau QR) bekerja seperti keyboard: kolom ID
 *   selalu fokus, pemindai mengetik ID lalu Enter.
 * - Kamera (opsional, opsi tema bk_kiosk_camera) membaca QR dan barcode
 *   memakai html5-qrcode; peramban hanya mengizinkan kamera di HTTPS/localhost.
 * - Pindai ulang kartu yang sama dalam bk_kiosk_repeat_seconds diabaikan
 *   di sisi peramban agar tidak tercatat berkali-kali.
 * - Tiga tata letak (parts/kiosk/*.php): split (panel terbelah), center
 *   (ringkas, untuk tablet tegak) dan camera (kamera utama, gelap). Dipilih di
 *   Kustomisasi tema atau per perangkat lewat ?layout=.
 *
 * Pencatatan tetap lewat lib/contents/visitor.inc.php (POST counter=1).
 */

$main_template_path = __DIR__ . '/login_template.inc.php';
$imagesDisk = \SLiMS\Filesystems\Storage::images();
include_once __DIR__ . '/classic.php';

// set default language (sama dengan tema default)
if (isset($_GET['select_lang'])) {
    $select_lang = trim(strip_tags($_GET['select_lang']));
    $cookie = ['path' => SWB, 'domain' => '', 'secure' => false, 'httponly' => true, 'samesite' => 'Lax'];
    if (isset($_COOKIE['select_lang'])) {
        @setcookie('select_lang', $select_lang, ['expires' => time() - 14400] + $cookie);
    }
    @setcookie('select_lang', $select_lang, ['expires' => time() + 14400] + $cookie);
    $sysconf['default_lang'] = $select_lang;
} else if (isset($_COOKIE['select_lang'])) {
    $sysconf['default_lang'] = trim(strip_tags($_COOKIE['select_lang']));
}

// layout kiosk: setelan tema bk_kiosk_layout, bisa ditimpa per perangkat dengan
// ?p=visitor&layout=split|center|camera (mis. layar kamera di pintu masuk)
$bk_layouts = ['split', 'center', 'camera'];
$bk_layout = (string)($sysconf['template']['bk_kiosk_layout'] ?? 'split');
if (isset($_GET['layout']) && in_array($_GET['layout'], $bk_layouts, true)) $bk_layout = $_GET['layout'];
if (!in_array($bk_layout, $bk_layouts, true)) $bk_layout = 'split';

// layout kamera selalu menyalakan pembaca kamera
$bk_camera = (int)($sysconf['template']['bk_kiosk_camera'] ?? 0) === 1 || $bk_layout === 'camera';
$bk_repeat = max(0, min(600, (int)($sysconf['template']['bk_kiosk_repeat_seconds'] ?? 10)));

// ruang kunjungan (?room=KODE dari Sistem > Ruang pengunjung); nama hanya untuk ditampilkan
$bk_room = '';
$bk_room_name = '';
if (isset($_GET['room']) && is_scalar($_GET['room']) && $_GET['room'] !== '') {
    $bk_room = '&room=' . urlencode((string)$_GET['room']);
    $bk_room_q = \SLiMS\DB::getInstance()->prepare('SELECT name FROM mst_visitor_room WHERE unique_code = ?');
    $bk_room_q->execute([$_GET['room']]);
    $bk_room_name = htmlspecialchars((string)$bk_room_q->fetchColumn(), ENT_QUOTES, 'UTF-8');
}

$bk_logo = '';
if (isset($sysconf['logo_image']) && $sysconf['logo_image'] != '' && $imagesDisk->isExists($path = 'default/' . $sysconf['logo_image'])) {
    $bk_logo = SWB . 'lib/minigalnano/createthumb.php?filename=images/' . $path . '&width=160';
}

$bk_kiosk_conf = [
    'url' => 'index.php?p=visitor' . $bk_room,
    'csrfName' => \Volnix\CSRF\CSRF::getTokenName(),
    'csrfToken' => \Volnix\CSRF\CSRF::getToken(),
    'repeatSeconds' => $bk_repeat,
    'camera' => $bk_camera,
    'autoCamera' => $bk_layout === 'camera',
    'layout' => $bk_layout,
    'voice' => (bool)($sysconf['template']['visitor_log_voice'] ?? 0),
    'lang' => str_replace('_', '-', bk_lang()),
    'imageBase' => SWB . 'images/persons/',
    'text' => [
        'ready' => bk_t('Siap memindai', 'Ready to scan'),
        'welcome' => bk_t('Selamat datang', 'Welcome'),
        'attention' => bk_t('Perhatian', 'Attention'),
        'needInstitution' => __('Sorry, Please fill institution field if you are not library member'),
        'wait' => bk_t('Memproses…', 'Processing…'),
        'repeat' => bk_t('Kartu ini baru saja tercatat. Silakan masuk.', 'This card was just recorded. Please come in.'),
        'invalid' => bk_t('Isi kartu tidak dikenali. Coba pindai lagi atau ketik nomor anggota.', 'Card content not recognised. Scan again or type the member number.'),
        'error' => bk_t('Gagal mencatat kunjungan. Hubungi petugas.', 'Could not record the visit. Please ask the staff.'),
        'guest' => bk_t('Bukan anggota? Isi nama dan instansi.', 'Not a member? Fill in your name and institution.'),
        'recorded' => bk_t('Tercatat', 'Recorded'),
        'already' => bk_t('Sudah tercatat', 'Already recorded'),
        'expired' => bk_t('Keanggotaan kedaluwarsa', 'Membership expired'),
        'unknown' => bk_t('ID tidak terdaftar — isi form tamu', 'ID not registered — fill in the guest form'),
        'camOn' => bk_t('Matikan kamera', 'Stop camera'),
        'camOff' => bk_t('Pindai dengan kamera', 'Scan with camera'),
        'camActive' => bk_t('Kamera aktif — arahkan QR kartu ke kamera', 'Camera on — hold the card QR to the camera'),
        'camInsecure' => bk_t('Kamera hanya bisa dipakai lewat HTTPS atau localhost. Pemindai USB tetap bisa dipakai.', 'The camera only works over HTTPS or localhost. The USB scanner still works.'),
        'camFail' => bk_t('Kamera tidak dapat dibuka. Pemindai USB tetap bisa dipakai.', 'Could not open the camera. The USB scanner still works.'),
    ],
];
?>
<div class="bk-kiosk bk-kiosk--<?= $bk_layout ?>" id="bk-kiosk" data-layout="<?= $bk_layout ?>">
<?php include __DIR__ . '/parts/kiosk/' . $bk_layout . '.php'; ?>
</div>

<?php if ($bk_camera) : ?>
<script src="<?= bk_assets('js/vendor/html5-qrcode.min.js') ?>"></script>
<?php endif; ?>
<script>
window.BK_KIOSK = <?= json_encode($bk_kiosk_conf, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= bk_assets(v('js/kiosk.js')) ?>"></script>
