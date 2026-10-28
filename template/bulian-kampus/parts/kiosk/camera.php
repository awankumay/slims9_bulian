<?php
/**
 * bulian-kampus kiosk, layout "camera": kamera utama di kiri (langsung
 * menyala), petunjuk di kanan, kolom ID tetap ada untuk pemindai USB.
 */
$bk_brand_suffix = bk_t('Buku Tamu', 'Guest Book');
$bk_scan_compact = true;
?>
<header class="bk-kiosk__bar">
    <?php include __DIR__ . '/_brand.php'; ?>
    <div class="bk-kiosk__bar-end">
        <?php include __DIR__ . '/_room.php'; ?>
        <p id="bk-scan-state" class="bk-kiosk__state" aria-live="polite"><span class="bk-dot"></span><span class="bk-kiosk__state-text"><?= bk_t('Pemindai USB juga siap', 'USB scanner also ready') ?></span></p>
        <span id="bk-clock" class="bk-kiosk__clock"></span>
    </div>
</header>

<div class="bk-kiosk__stage">
    <section class="bk-kiosk__cam" aria-label="<?= bk_t('Kamera pemindai', 'Scanner camera') ?>">
        <div class="bk-kiosk__camwrap" id="bk-cam-wrap">
            <div id="bk-cam-view" class="bk-kiosk__camview"></div>
            <span class="bk-kiosk__frame" aria-hidden="true"><i></i></span>
            <p id="bk-cam-note" class="bk-kiosk__camnote"><?= bk_t('Posisikan QR di dalam kotak kuning', 'Place the QR inside the yellow box') ?></p>
            <?php include __DIR__ . '/_result.php'; ?>
        </div>
        <?php include __DIR__ . '/_scan-form.php'; ?>
    </section>

    <section class="bk-kiosk__help" aria-labelledby="bk-kiosk-title">
        <h1 id="bk-kiosk-title" class="bk-kiosk__title"><?= bk_t('Tunjukkan QR kartu anggota ke kamera', 'Show your member card QR to the camera') ?></h1>
        <ol class="bk-kiosk__steps">
            <li><strong><?= bk_t('Siapkan QR kartu anggota', 'Get your member card QR ready') ?></strong><span><?= bk_t('Kartu cetak atau kartu digital di ponsel.', 'Printed card or digital card on your phone.') ?></span></li>
            <li><strong><?= bk_t('Dekatkan ke kamera', 'Hold it to the camera') ?></strong><span><?= bk_t('QR, Code128, Code39 dan EAN-13 terbaca otomatis.', 'QR, Code128, Code39 and EAN-13 are read automatically.') ?></span></li>
            <li><strong><?= bk_t('Layar hijau = tercatat', 'Green screen = recorded') ?></strong><span><?= $bk_repeat > 0 ? str_replace('{n}', $bk_repeat, bk_t('Pindai ulang dalam {n} detik diabaikan.', 'Rescans within {n} seconds are ignored.')) : '' ?></span></li>
        </ol>
        <?php include __DIR__ . '/_guest.php'; ?>
        <p class="bk-kiosk__hint"><?= bk_opt('bk_hours') ?> · <?= __('Powered by ') ?>SLiMS</p>
    </section>
</div>
