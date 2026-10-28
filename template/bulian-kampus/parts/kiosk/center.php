<?php
/**
 * bulian-kampus kiosk, layout "center": satu kolom di tengah layar gelap,
 * hasil pindai tampil di atas form. Cocok untuk tablet tegak.
 */
?>
<section class="bk-kiosk__panel" aria-labelledby="bk-kiosk-title">
    <div class="bk-kiosk__top">
        <?php include __DIR__ . '/_brand.php'; ?>
        <?php include __DIR__ . '/_room.php'; ?>
    </div>
    <?php include __DIR__ . '/_result.php'; ?>
    <h1 id="bk-kiosk-title" class="bk-kiosk__title"><?= bk_t('Pindai kartu anggota', 'Scan your member card') ?></h1>
    <p class="bk-kiosk__lead"><?= bk_t('Arahkan barcode atau QR di kartu ke pemindai. Kunjungan langsung tercatat.', 'Hold the barcode or QR on your card to the scanner. Your visit is recorded instantly.') ?></p>
    <?php include __DIR__ . '/_scan-form.php'; ?>
    <?php include __DIR__ . '/_camera.php'; ?>
    <?php include __DIR__ . '/_guest.php'; ?>
    <footer class="bk-kiosk__foot"><?= bk_opt('bk_hours') ?><br><?= __('Powered by ') ?>SLiMS · <span id="bk-clock"></span></footer>
</section>
