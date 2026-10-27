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

$bk_camera = (int)($sysconf['template']['bk_kiosk_camera'] ?? 0) === 1;
$bk_repeat = max(0, min(600, (int)($sysconf['template']['bk_kiosk_repeat_seconds'] ?? 10)));
$bk_room = isset($_GET['room']) ? '&room=' . urlencode(simbio_security::xssFree($_GET['room'])) : '';
$bk_kiosk_conf = [
    'url' => 'index.php?p=visitor' . $bk_room,
    'csrfName' => \Volnix\CSRF\CSRF::getTokenName(),
    'csrfToken' => \Volnix\CSRF\CSRF::getToken(),
    'repeatSeconds' => $bk_repeat,
    'camera' => $bk_camera,
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
        'camOn' => bk_t('Matikan kamera', 'Stop camera'),
        'camOff' => bk_t('Pindai dengan kamera', 'Scan with camera'),
        'camInsecure' => bk_t('Kamera hanya bisa dipakai lewat HTTPS atau localhost.', 'The camera only works over HTTPS or localhost.'),
        'camFail' => bk_t('Kamera tidak dapat dibuka.', 'Could not open the camera.'),
    ],
];
?>
<div class="bk-kiosk" id="bk-kiosk">
    <section class="bk-kiosk__scan" aria-labelledby="bk-kiosk-title">
        <header class="bk-kiosk__brand">
            <?php
            if (isset($sysconf['logo_image']) && $sysconf['logo_image'] != '' && $imagesDisk->isExists($path = 'default/' . $sysconf['logo_image'])) {
                echo '<img src="' . SWB . 'lib/minigalnano/createthumb.php?filename=images/' . $path . '&width=160" alt="">';
            }
            ?>
            <span>
                <strong><?= $sysconf['library_name'] ?></strong>
                <small><?= bk_opt('bk_parent_name') ?></small>
            </span>
        </header>

        <h1 id="bk-kiosk-title" class="bk-kiosk__title"><?= bk_t('Pindai kartu anggota', 'Scan your member card') ?></h1>
        <p class="bk-kiosk__lead"><?= bk_t('Arahkan barcode atau QR di kartu ke pemindai. Kunjungan langsung tercatat.', 'Hold the barcode or QR on your card to the scanner. Your visit is recorded instantly.') ?></p>

        <form id="bk-scan-form" class="bk-kiosk__form" autocomplete="off">
            <label for="bk-member-id"><?= __('Member ID') ?></label>
            <div class="bk-kiosk__row">
                <input id="bk-member-id" name="memberID" type="text" inputmode="text" autofocus
                       placeholder="<?= bk_t('Pindai atau ketik nomor anggota', 'Scan or type member number') ?>">
                <button type="submit"><?= __('Check In') ?></button>
            </div>
            <p id="bk-scan-state" class="bk-kiosk__state" aria-live="polite"><span class="bk-dot"></span> <?= bk_t('Siap memindai', 'Ready to scan') ?></p>
        </form>

        <?php if ($bk_camera) : ?>
        <div class="bk-kiosk__camera">
            <button type="button" id="bk-cam-toggle" class="bk-kiosk__ghost"><?= bk_t('Pindai dengan kamera', 'Scan with camera') ?></button>
            <div id="bk-cam-view" class="bk-kiosk__camview" hidden></div>
        </div>
        <?php endif; ?>

        <details id="bk-guest" class="bk-kiosk__guest">
            <summary><?= bk_t('Tamu / bukan anggota', 'Guest / not a member') ?></summary>
            <form id="bk-guest-form" autocomplete="off">
                <label for="bk-guest-name"><?= bk_t('Nama lengkap', 'Full name') ?></label>
                <input id="bk-guest-name" type="text" required maxlength="100">
                <label for="bk-guest-inst"><?= __('Institution') ?></label>
                <input id="bk-guest-inst" type="text" required maxlength="100">
                <button type="submit"><?= __('Check In') ?></button>
            </form>
        </details>

        <footer class="bk-kiosk__foot"><?= __('Powered by ') ?>SLiMS · <span id="bk-clock"></span></footer>
    </section>

    <section class="bk-kiosk__result" aria-live="assertive">
        <div id="bk-result" class="bk-kiosk__card" data-state="idle">
            <img id="bk-result-photo" src="<?= SWB ?>images/persons/photo.png" alt="">
            <p id="bk-result-title" class="bk-kiosk__welcome"><?= bk_t('Selamat datang', 'Welcome') ?></p>
            <p id="bk-result-text" class="bk-kiosk__msg"><?= bk_t('di', 'to') ?> <?= $sysconf['library_name'] ?></p>
        </div>
        <p class="bk-kiosk__hint"><?= bk_opt('bk_hours') ?></p>
    </section>
</div>

<?php if ($bk_camera) : ?>
<script src="<?= bk_assets('js/vendor/html5-qrcode.min.js') ?>"></script>
<?php endif; ?>
<script>
window.BK_KIOSK = <?= json_encode($bk_kiosk_conf, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= bk_assets(v('js/kiosk.js')) ?>"></script>
