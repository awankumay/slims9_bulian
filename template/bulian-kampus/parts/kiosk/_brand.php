<?php
/** bulian-kampus kiosk: logo dan nama perpustakaan ($bk_logo, $bk_brand_suffix opsional) */
?>
<header class="bk-kiosk__brand">
    <?php if ($bk_logo) : ?>
    <img src="<?= $bk_logo ?>" alt="">
    <?php else : ?>
    <span class="bk-kiosk__logo-empty" aria-hidden="true">Logo</span>
    <?php endif; ?>
    <span>
        <strong><?= $sysconf['library_name'] ?></strong>
        <small><?= bk_opt('bk_parent_name') ?><?= isset($bk_brand_suffix) ? ' · ' . $bk_brand_suffix : '' ?></small>
    </span>
</header>
