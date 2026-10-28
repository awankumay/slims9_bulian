<?php
/**
 * bulian-kampus kiosk: halaman pilih ruang, tampil bila ?p=visitor dibuka tanpa
 * kode ruang (lihat visitor_template.php). Pilih tampilan, lalu klik ruang: kiosk
 * terbuka di ?p=visitor&room=KODE&layout=... Tanpa JS tetap jalan (tautan biasa);
 * assets/js/kiosk-rooms.js menambah "ingat di perangkat ini" dan pencarian.
 */
$bk_e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$bk_kiosk_href = function ($room, $layout) {
    return 'index.php?p=visitor&' . ($room === '' ? 'noroom=1' : 'room=' . urlencode($room)) . '&layout=' . $layout;
};
?>
<div class="bk-rooms" id="bk-rooms" data-pick="<?= isset($_GET['pick']) || $bk_room_invalid ? '1' : '0' ?>">
    <header class="bk-rooms__head">
        <div class="bk-rooms__wrap bk-rooms__bar">
            <div class="bk-rooms__brand">
                <?php if ($bk_logo) : ?>
                <img src="<?= $bk_logo ?>" alt="">
                <?php endif; ?>
                <span>
                    <strong><?= $sysconf['library_name'] ?></strong>
                    <small><?= bk_t('Buku tamu · Pemasangan perangkat', 'Guest book · Device setup') ?></small>
                </span>
            </div>
            <a class="bk-rooms__back" href="index.php"><i class="fas fa-chevron-left" aria-hidden="true"></i> <?= bk_t('Kembali ke OPAC', 'Back to OPAC') ?></a>
        </div>
    </header>

    <main class="bk-rooms__wrap bk-rooms__main">
        <div class="bk-rooms__intro">
            <h1><?= bk_t('Kiosk ini dipasang di ruang mana?', 'Which room is this kiosk in?') ?></h1>
            <p><?= bk_t('Pilih tampilan, lalu klik ruangnya. Kiosk langsung terbuka dan setiap kunjungan dari perangkat ini tersimpan dengan kode ruangnya.', 'Pick a layout, then click the room. The kiosk opens right away and every visit from this device is saved with its room code.') ?></p>
        </div>

        <?php if ($bk_room_invalid) : ?>
        <p class="bk-rooms__alert" role="alert"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> <?= str_replace('{code}', '<strong>' . $bk_e($_GET['room']) . '</strong>', bk_t('Kode ruang {code} tidak terdaftar. Pilih salah satu ruang di bawah.', 'Room code {code} is not registered. Choose a room below.')) ?></p>
        <?php endif; ?>

        <section class="bk-rooms__box" aria-labelledby="bk-rooms-layout">
            <h2 id="bk-rooms-layout"><span class="bk-rooms__step" aria-hidden="true">1</span><?= bk_t('Pilih tampilan kiosk', 'Choose the kiosk layout') ?></h2>
            <div class="bk-rooms__layouts">
                <?php foreach ($bk_layout_names as $bk_id => $bk_names) : ?>
                <a class="bk-rooms__layout" href="index.php?p=visitor&amp;pick=1&amp;layout=<?= $bk_id ?>" data-layout="<?= $bk_id ?>"<?= $bk_id === $bk_layout ? ' aria-current="true"' : '' ?>>
                    <span class="bk-rooms__thumb bk-rooms__thumb--<?= $bk_id ?>" aria-hidden="true"><i></i><i></i></span>
                    <span><strong><?= $bk_names[0] ?></strong><small><?= $bk_names[1] ?></small></span>
                </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="bk-rooms__box" aria-labelledby="bk-rooms-list">
            <div class="bk-rooms__boxhead">
                <h2 id="bk-rooms-list"><span class="bk-rooms__step" aria-hidden="true">2</span><?= bk_t('Klik ruang untuk membuka kiosk', 'Click a room to open the kiosk') ?></h2>
                <?php if (count($bk_rooms) > 6) : ?>
                <div class="bk-rooms__search">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <label for="bk-rooms-q" class="sr-only"><?= bk_t('Cari ruang', 'Search rooms') ?></label>
                    <input id="bk-rooms-q" type="search" placeholder="<?= bk_t('Cari nama atau kode ruang', 'Search room name or code') ?>">
                </div>
                <?php endif; ?>
            </div>
            <ul class="bk-rooms__grid" role="list">
                <?php foreach ($bk_rooms as $bk_r) : ?>
                <li>
                    <a class="bk-rooms__room" href="<?= $bk_e($bk_kiosk_href($bk_r['unique_code'], $bk_layout)) ?>" data-room="<?= $bk_e($bk_r['unique_code']) ?>">
                        <strong><?= $bk_e($bk_r['name']) ?></strong>
                        <span><code><?= $bk_e($bk_r['unique_code']) ?></code><em><?= bk_t('Buka', 'Open') ?> <i class="fas fa-arrow-right" aria-hidden="true"></i></em></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <p class="bk-rooms__empty" id="bk-rooms-empty" hidden><?= bk_t('Tidak ada ruang yang cocok.', 'No matching room.') ?></p>
            <p class="bk-rooms__note"><?= str_replace('{menu}', __('Master File') . ' → ' . __('Visitor Room'), bk_t('Daftar ruang diambil dari menu admin {menu}. Ruang baru langsung muncul di sini.', 'Rooms come from the admin menu {menu}. New rooms show up here right away.')) ?></p>
        </section>
    </main>

    <footer class="bk-rooms__foot">
        <div class="bk-rooms__wrap bk-rooms__bar">
            <label class="bk-rooms__remember">
                <input type="checkbox" id="bk-rooms-remember" checked>
                <span><?= bk_t('Ingat pilihan di perangkat ini', 'Remember this choice on this device') ?></span>
            </label>
            <a class="bk-rooms__noroom" href="<?= $bk_e($bk_kiosk_href('', $bk_layout)) ?>" data-room=""><?= bk_t('Lanjut tanpa ruang', 'Continue without a room') ?></a>
        </div>
    </footer>
</div>
<script>
window.BK_ROOMS = <?= json_encode([
    'storeKey' => 'bk_kiosk_device',
    'layouts' => array_keys($bk_layout_names),
    'rooms' => array_column($bk_rooms, 'unique_code'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= bk_assets_v('js/kiosk-rooms.js') ?>"></script>
