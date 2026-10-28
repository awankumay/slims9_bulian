<?php
/** bulian-kampus kiosk: kartu hasil pindai (foto, sapaan, pesan) */
?>
<div id="bk-result" class="bk-kiosk__card" data-state="idle" aria-live="assertive">
    <span class="bk-kiosk__photo">
        <img id="bk-result-photo" src="" alt="" hidden>
        <span class="bk-kiosk__nophoto" aria-hidden="true"><svg viewBox="0 0 64 64" width="64" height="64"><circle cx="32" cy="22" r="12" fill="currentColor"/><path d="M8 58c0-13 11-22 24-22s24 9 24 22z" fill="currentColor"/></svg></span>
    </span>
    <p id="bk-result-title" class="bk-kiosk__welcome"><?= bk_t('Selamat datang', 'Welcome') ?></p>
    <p id="bk-result-text" class="bk-kiosk__msg"><?= bk_t('di', 'to') ?> <?= $sysconf['library_name'] ?> <?= bk_opt('bk_parent_name') ?></p>
</div>
