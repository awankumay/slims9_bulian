<?php
/**
 * bulian-kampus kiosk: tombol pengaturan perangkat di pojok kanan bawah.
 * Harus ditahan 2 detik (kiosk.js) agar pengunjung tidak mengubahnya tanpa
 * sengaja; dialognya mengganti ruang dan layout untuk perangkat ini saja.
 */
?>
<button type="button" id="bk-device-btn" class="bk-device__btn" aria-haspopup="dialog" aria-describedby="bk-device-hint">
    <i class="fas fa-cog" aria-hidden="true"></i><span class="sr-only"><?= bk_t('Pengaturan perangkat', 'Device settings') ?></span>
</button>
<span id="bk-device-hint" class="bk-device__hint" role="status"></span>

<dialog id="bk-device" class="bk-device" aria-labelledby="bk-device-title">
    <form method="dialog" id="bk-device-form">
        <div class="bk-device__head">
            <div>
                <h2 id="bk-device-title"><?= bk_t('Pengaturan perangkat', 'Device settings') ?></h2>
                <p><?= bk_t('Hanya berlaku di perangkat ini.', 'Applies to this device only.') ?></p>
            </div>
            <button type="submit" value="cancel" class="bk-device__close" aria-label="<?= bk_t('Tutup', 'Close') ?>"><i class="fas fa-times" aria-hidden="true"></i></button>
        </div>

        <?php if ($bk_rooms) : ?>
        <div class="bk-device__room">
            <span>
                <small><?= bk_t('Ruang', 'Room') ?></small>
                <strong><?= $bk_room_name !== '' ? $bk_room_name . ' · ' . htmlspecialchars($bk_room_code, ENT_QUOTES, 'UTF-8') : bk_t('Tanpa ruang', 'No room') ?></strong>
            </span>
            <a href="index.php?p=visitor&amp;pick=1&amp;layout=<?= $bk_layout ?>"><?= bk_t('Ganti ruang', 'Change room') ?></a>
        </div>
        <?php endif; ?>

        <fieldset class="bk-device__layouts">
            <legend><?= bk_t('Tampilan', 'Layout') ?></legend>
            <div class="bk-device__seg">
            <?php foreach ($bk_layout_names as $bk_id => $bk_names) : ?>
            <label>
                <input type="radio" name="bk-device-layout" value="<?= $bk_id ?>"<?= $bk_id === $bk_layout ? ' checked' : '' ?>>
                <span><?= $bk_names[0] ?></span>
            </label>
            <?php endforeach; ?>
            </div>
        </fieldset>

        <div class="bk-device__actions">
            <button type="submit" value="cancel" class="bk-device__cancel"><?= bk_t('Batal', 'Cancel') ?></button>
            <button type="submit" value="save"><?= bk_t('Simpan &amp; muat ulang', 'Save &amp; reload') ?></button>
        </div>
    </form>
</dialog>
