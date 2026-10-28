<?php
/** bulian-kampus kiosk: kolom ID anggota (pemindai USB mengetik ke sini) */
$bk_scan_compact = $bk_scan_compact ?? false;
?>
<form id="bk-scan-form" class="bk-kiosk__form" autocomplete="off">
    <label for="bk-member-id"<?= $bk_scan_compact ? ' class="sr-only"' : '' ?>><?= __('Member ID') ?></label>
    <div class="bk-kiosk__row">
        <input id="bk-member-id" name="memberID" type="text" inputmode="text" autofocus
               placeholder="<?= $bk_scan_compact ? bk_t('Atau ketik / pindai nomor anggota', 'Or type / scan member number') : bk_t('Pindai atau ketik nomor anggota', 'Scan or type member number') ?>">
        <button type="submit" class="bk-kiosk__go"><?php if (!$bk_scan_compact) : ?><i class="fas fa-sign-in-alt" aria-hidden="true"></i> <?php endif; ?><?= 'Check In' ?></button>
    </div>
    <?php if (!$bk_scan_compact) : ?>
    <p id="bk-scan-state" class="bk-kiosk__state" aria-live="polite"><span class="bk-dot"></span><i class="fas fa-barcode" aria-hidden="true"></i><span class="bk-kiosk__state-text"><?= bk_t('Siap memindai', 'Ready to scan') ?></span></p>
    <?php endif; ?>
</form>
