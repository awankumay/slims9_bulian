<?php
/**
 * Tema kartu anggota "bulian-kartu".
 *
 * Variabel dari admin/modules/membership/member_card_generator.php:
 * $chunked_card_arrays, $card_path, $card_logo, $card_stamp, $card_signature,
 * $sysconf['print']['membercard'].
 *
 * Barcode (Code128) dan QR digambar sebagai SVG di sini, sehingga kartu
 * tidak bergantung pada file images/barcodes/*.png dan tetap tajam saat dicetak.
 */

$bk_conf = $sysconf['print']['membercard'];
foreach (include __DIR__ . '/defaults.inc.php' as $bk_key => [$bk_stock, $bk_own]) {
  $bk_now = html_entity_decode((string)($bk_conf[$bk_key] ?? ''), ENT_QUOTES, 'UTF-8');
  if ($bk_now === '' || strpos($bk_now, $bk_stock) !== false) $bk_conf[$bk_key] = $bk_own;
}

require_once __DIR__ . '/card-lib.inc.php';

$bk_primary = bk_card_color($bk_conf['primary_color'] ?? '', '#0067B8');
$bk_accent = bk_card_color($bk_conf['accent_color'] ?? '', '#F9DA00');
[$bk_w, $bk_h] = bk_card_size($bk_conf, false);
$bk_show_qr = (int)($bk_conf['show_qr'] ?? 1) === 1;

// logo: setelan logo perpustakaan (Sistem > Pengaturan) bila ada, kalau tidak logo tema kartu
$bk_logo = $card_logo;
if (!empty($sysconf['logo_image']) && is_file(IMGBS . 'default' . DS . $sysconf['logo_image'])) {
  $bk_logo = SWB . IMG . '/default/' . rawurlencode($sysconf['logo_image']);
}
$bk_card_dir = UPLOAD . 'membercard' . DS . $bk_conf['template'] . DS . 'images' . DS;
$bk_has_sign = !empty($bk_conf['signature_file']) && is_file($bk_card_dir . $bk_conf['signature_file']);
$bk_has_stamp = !empty($bk_conf['stamp_file']) && is_file($bk_card_dir . $bk_conf['stamp_file']);

$bk_months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$bk_date = function ($date) use ($bk_months) {
  $t = strtotime((string)$date);
  if (!$t) return '';
  return date('j', $t) . ' ' . $bk_months[(int)date('n', $t)] . ' ' . date('Y', $t);
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= __('Member Card Printing') ?></title>
<style>
@font-face { font-family: 'Lato'; font-weight: 400; src: url('<?= $card_path ?>fonts/lato-400.woff2') format('woff2'); }
@font-face { font-family: 'Lato'; font-weight: 700; src: url('<?= $card_path ?>fonts/lato-700.woff2') format('woff2'); }
@font-face { font-family: 'Lato'; font-weight: 900; src: url('<?= $card_path ?>fonts/lato-900.woff2') format('woff2'); }

@page { size: A4; margin: 10mm; }
* { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
body { margin: 0; font-family: 'Lato', 'Segoe UI', Arial, sans-serif; color: #12202F; background: #F1F3F6; }
p { margin: 0; }

.sheet { display: flex; flex-direction: column; gap: 4mm; padding: 6mm; }
.sheet-row { display: flex; flex-wrap: wrap; gap: 4mm; }

.card {
  width: <?= $bk_w ?>cm; height: <?= $bk_h ?>cm;
  position: relative; overflow: hidden;
  background: #FFFFFF; border: 0.2mm solid #C9D1DB; border-radius: 3mm;
  page-break-inside: avoid; break-inside: avoid;
  font-size: 2.3mm; line-height: 1.25;
}

/* sisi depan */
.front-head { height: 13mm; padding: 2.2mm 3.2mm; background: <?= $bk_primary ?>; color: #FFFFFF; display: flex; align-items: center; gap: 2.4mm; }
.front-head img { height: 8.6mm; width: 8.6mm; object-fit: contain; background: #FFFFFF; border-radius: 1.4mm; padding: 0.6mm; }
.front-head .names { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.front-head .kicker { font-size: 1.75mm; font-weight: 900; letter-spacing: 0.12em; text-transform: uppercase; color: <?= $bk_accent ?>; }
.front-head .lib { font-size: 3.1mm; font-weight: 900; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.front-head .parent { font-size: 1.9mm; opacity: 0.9; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.front-head .type { align-self: flex-start; background: #FFFFFF; color: <?= $bk_primary ?>; font-weight: 900; font-size: 1.8mm; padding: 0.6mm 1.8mm; border-radius: 3mm; white-space: nowrap; }

.front-body { position: absolute; top: 13mm; bottom: 1.4mm; left: 0; right: 0; padding: 2.4mm 3.2mm 2mm; display: flex; gap: 3mm; }
.photo { flex: 0 0 15mm; height: 20mm; border-radius: 1.4mm; background: #E9EEF4; border: 0.3mm solid #D8DEE6; overflow: hidden; display: flex; align-items: center; justify-content: center; color: #6B778A; font-size: 1.8mm; }
.photo img { width: 100%; height: 100%; object-fit: cover; }
.info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1.1mm; }
.info .name { font-size: 3.3mm; font-weight: 900; line-height: 1.12; max-height: 7.6mm; overflow: hidden; }
.info .facts { display: flex; gap: 3.5mm; }
.info .label { display: block; font-size: 1.5mm; font-weight: 700; letter-spacing: 0.06em; color: #4A5568; text-transform: uppercase; }
.info .value { font-size: 2.5mm; font-weight: 900; }
.info .mono { font-family: 'DejaVu Sans Mono', 'Consolas', 'Courier New', monospace; letter-spacing: 0.04em; }
.info .inst { font-size: 1.9mm; color: #4A5568; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.barcode { margin-top: auto; height: 6.4mm; width: 100%; }
.barcode svg, .barcode img { width: 100%; height: 100%; display: block; }
.stripe { position: absolute; left: 0; right: 0; bottom: 0; height: 1.4mm; background: <?= $bk_accent ?>; }

/* sisi belakang */
.back { display: flex; flex-direction: column; padding: 3mm 3.2mm 2.4mm; border-top: 1.4mm solid <?= $bk_primary ?>; }
.back-top { display: flex; gap: 3mm; flex: 1; min-height: 0; }
.rules { flex: 1; min-width: 0; font-size: 1.85mm; }
.rules strong.h { display: block; font-size: 2.2mm; font-weight: 900; color: <?= $bk_primary ?>; margin-bottom: 0.8mm; }
.rules ul, .rules ol { margin: 0; padding-left: 3mm; }
.rules li { margin-bottom: 0.4mm; }
.rules p { margin: 0 0 0.6mm; }
.qr { flex: 0 0 17mm; display: flex; flex-direction: column; align-items: center; gap: 0.6mm; }
.qr svg { width: 17mm; height: 17mm; display: block; }
.qr span { font-size: 1.55mm; font-weight: 700; text-align: center; color: #4A5568; }
.back-bottom { display: flex; justify-content: space-between; align-items: flex-end; gap: 3mm; margin-top: 1mm; }
.addr { flex: 1; min-width: 0; font-size: 1.6mm; color: #4A5568; }
.addr b { display: block; color: #12202F; font-size: 1.9mm; }
.sign { flex: 0 0 24mm; text-align: center; font-size: 1.6mm; position: relative; }
.sign .space { height: 6mm; position: relative; }
.sign .space img.signature { height: 6mm; }
.sign .space img.stamp { position: absolute; left: 0; top: -1mm; height: 8mm; opacity: 0.85; }
.sign .who { font-weight: 900; font-size: 1.8mm; }

.toolbar { padding: 10px 6mm 0; font-family: 'Lato', Arial, sans-serif; }
.toolbar a { display: inline-block; background: <?= $bk_primary ?>; color: #FFFFFF; text-decoration: none; padding: 10px 16px; border-radius: 6px; font-weight: 700; }
.toolbar small { margin-left: 10px; color: #4A5568; }
@media print {
  body { background: #FFFFFF; }
  .toolbar { display: none; }
  .sheet { padding: 0; }
  .card { border-color: #BFC7D1; }
}
</style>
</head>
<body>
<div class="toolbar">
  <a href="#" onclick="window.print(); return false;"><?= __('Print Again') ?></a>
  <small>Ukuran kartu <?= $bk_w ?> × <?= $bk_h ?> cm. Matikan "Fit to page" dan pilih skala 100% saat mencetak.</small>
</div>
<div class="sheet">
<?php foreach ($chunked_card_arrays as $membercard_rows) : ?>
  <div class="sheet-row">
  <?php foreach ($membercard_rows as $card) :
    $member_id = (string)$card['member_id'];
    $photo = '';
    if (!empty($card['member_image']) && is_file(IMGBS . 'persons' . DS . basename($card['member_image']))) {
      $photo = SWB . IMG . '/persons/' . rawurlencode(basename($card['member_image']));
    }
    $barcode = bk_card_barcode($member_id);
    $qr = $bk_show_qr ? bk_card_qr($member_id) : null;
  ?>
    <section class="card front" aria-label="Kartu anggota sisi depan">
      <div class="front-head">
        <img src="<?= $bk_logo ?>" alt="">
        <div class="names">
          <span class="kicker"><?= bk_card_text($bk_conf['front_header1_text'] ?? 'Kartu Anggota Perpustakaan') ?></span>
          <span class="lib"><?= bk_card_text($bk_conf['front_header2_text'] ?? '') ?></span>
          <span class="parent"><?= bk_card_text($bk_conf['parent_text'] ?? '') ?></span>
        </div>
        <?php if (!empty($card['member_type_name'])) : ?>
        <span class="type"><?= bk_card_text($card['member_type_name']) ?></span>
        <?php endif; ?>
      </div>
      <div class="front-body">
        <div class="photo">
          <?php if ($photo) : ?><img src="<?= $photo ?>" alt=""><?php else : ?>Foto 3×4<?php endif; ?>
        </div>
        <div class="info">
          <p class="name"><?= bk_card_text($card['member_name']) ?></p>
          <div class="facts">
            <p><span class="label">No. anggota</span><span class="value mono"><?= bk_card_text($member_id) ?></span></p>
            <p><span class="label">Berlaku s.d.</span><span class="value"><?= bk_card_text(date('d-m-Y', strtotime($card['expire_date']))) ?></span></p>
          </div>
          <?php if (!empty($card['inst_name'])) : ?>
          <p class="inst"><?= bk_card_text($card['inst_name']) ?></p>
          <?php endif; ?>
          <div class="barcode">
            <?php if ($barcode) : echo $barcode; else : ?>
            <img src="<?= SWB . IMG . '/barcodes/' . rawurlencode(str_replace(' ', '_', $member_id)) ?>.png" alt="Barcode <?= bk_card_text($member_id) ?>">
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="stripe"></div>
    </section>

    <section class="card back" aria-label="Kartu anggota sisi belakang">
      <div class="back-top">
        <div class="rules">
          <strong class="h">Ketentuan anggota</strong>
          <?php
          // setelan lama bisa menyisakan "rn" dari \r\n yang di-stripslashes; buang di antara tag
          $bk_rules = html_entity_decode((string)($bk_conf['rules'] ?? ''), ENT_QUOTES, 'UTF-8');
          echo preg_replace('/>(?:\s|rn)+</', '><', $bk_rules);
          ?>
        </div>
        <?php if ($qr) : ?>
        <div class="qr">
          <?= $qr ?>
          <span><?= bk_card_text($bk_conf['qr_label'] ?? 'Pindai di buku tamu') ?></span>
        </div>
        <?php endif; ?>
      </div>
      <div class="back-bottom">
        <div class="addr">
          <b><?= bk_card_text($bk_conf['back_header1_text'] ?? '') ?></b>
          <?= bk_card_text($bk_conf['back_header2_text'] ?? '') ?>
        </div>
        <div class="sign">
          <div><?= bk_card_text($bk_conf['city'] ?? '') ?>, <?= $bk_date($card['register_date']) ?></div>
          <div><?= bk_card_text($bk_conf['title'] ?? '') ?></div>
          <div class="space">
            <?php if ($bk_has_stamp) : ?><img class="stamp" src="<?= $card_stamp ?>" alt=""><?php endif; ?>
            <?php if ($bk_has_sign) : ?><img class="signature" src="<?= $card_signature ?>" alt=""><?php endif; ?>
          </div>
          <div class="who"><?= bk_card_text($bk_conf['officials'] ?? '') ?></div>
          <div><?= bk_card_text($bk_conf['officials_id'] ?? '') ?></div>
        </div>
      </div>
    </section>
  <?php endforeach; ?>
  </div>
<?php endforeach; ?>
</div>
<script>self.print();</script>
</body>
</html>
