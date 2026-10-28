<?php
/** bulian-kampus kiosk: tombol dan pratinjau kamera (layout split/center) */
if (!$bk_camera) return;
?>
<div class="bk-kiosk__camera">
    <button type="button" id="bk-cam-toggle" class="bk-kiosk__ghost"><i class="fas fa-camera" aria-hidden="true"></i> <span><?= bk_t('Pindai dengan kamera', 'Scan with camera') ?></span></button>
    <div class="bk-kiosk__camwrap" id="bk-cam-wrap" hidden>
        <div id="bk-cam-view" class="bk-kiosk__camview"></div>
        <span class="bk-kiosk__frame" aria-hidden="true"><i></i></span>
        <span class="bk-kiosk__formats">QR · Code128 · Code39 · EAN-13</span>
    </div>
</div>
