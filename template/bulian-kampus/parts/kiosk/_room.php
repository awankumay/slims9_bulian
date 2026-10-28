<?php
/** bulian-kampus kiosk: label ruang kunjungan (bila ?room= terdaftar) */
if ($bk_room_name === '') return;
?>
<span class="bk-kiosk__room"><i class="fas fa-door-open" aria-hidden="true"></i> <?= bk_t('Ruang', 'Room') ?>: <?= $bk_room_name ?></span>
