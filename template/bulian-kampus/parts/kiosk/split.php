<?php
/**
 * bulian-kampus kiosk, layout "split": panel pindai terang di kiri, panel
 * sapaan gelap di kanan. Layout "center" memakai markup yang sama.
 */
?>
<section class="bk-kiosk__scan" aria-labelledby="bk-kiosk-title">
    <?php include __DIR__ . '/_brand.php'; ?>
    <div class="bk-kiosk__body">
        <h1 id="bk-kiosk-title" class="bk-kiosk__title"><?= bk_t('Pindai kartu anggota', 'Scan your member card') ?></h1>
        <p class="bk-kiosk__lead"><?= bk_t('Arahkan barcode atau QR di kartu ke pemindai. Kunjungan langsung tercatat.', 'Hold the barcode or QR on your card to the scanner. Your visit is recorded instantly.') ?></p>
        <?php include __DIR__ . '/_scan-form.php'; ?>
        <?php include __DIR__ . '/_camera.php'; ?>
        <?php include __DIR__ . '/_guest.php'; ?>
    </div>
    <footer class="bk-kiosk__foot"><?= __('Powered by ') ?>SLiMS · <span id="bk-clock"></span></footer>
</section>

<section class="bk-kiosk__result">
    <?php include __DIR__ . '/_room.php'; ?>
    <?php include __DIR__ . '/_result.php'; ?>
    <p class="bk-kiosk__hint"><?= bk_opt('bk_hours') ?></p>
</section>
