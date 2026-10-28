<?php
/** bulian-kampus kiosk: form tamu / bukan anggota */
?>
<details id="bk-guest" class="bk-kiosk__guest">
    <summary><i class="fas fa-<?= $bk_layout === 'camera' ? 'user-plus' : 'chevron-right' ?>" aria-hidden="true"></i> <?= bk_t('Tamu / bukan anggota', 'Guest / not a member') ?></summary>
    <form id="bk-guest-form" autocomplete="off">
        <label for="bk-guest-name"><?= bk_t('Nama lengkap', 'Full name') ?></label>
        <input id="bk-guest-name" type="text" required maxlength="100">
        <label for="bk-guest-inst"><?= __('Institution') ?></label>
        <input id="bk-guest-inst" type="text" required maxlength="100" placeholder="<?= bk_t('Nama instansi / kampus asal', 'Institution / home campus') ?>">
        <button type="submit"><?= 'Check In' ?></button>
    </form>
</details>
