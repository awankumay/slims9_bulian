<?php
/**
 * Tema kartu anggota "bulian-kartu-potret" (CR80 tegak, untuk lanyard).
 *
 * Variabel dari admin/modules/membership/member_card_generator.php:
 * $chunked_card_arrays, $card_path, $card_stamp, $card_signature,
 * $sysconf['print']['membercard'].
 *
 * Tema ini mengatur sendiri isi tiap halaman A4 (setelan "Lembar cetak A4"),
 * jadi potongan per baris dari generator digabung lagi.
 */

$bk_conf = $sysconf['print']['membercard'];
foreach (include __DIR__ . '/../bulian-kartu/defaults.inc.php' as $bk_key => [$bk_stock, $bk_own]) {
  $bk_now = html_entity_decode((string)($bk_conf[$bk_key] ?? ''), ENT_QUOTES, 'UTF-8');
  if ($bk_now === '' || strpos($bk_now, $bk_stock) !== false) $bk_conf[$bk_key] = $bk_own;
}

require_once __DIR__ . '/../bulian-kartu/card-lib.inc.php';

$bk_primary = bk_card_color($bk_conf['primary_color'] ?? '', '#0067B8');
$bk_accent = bk_card_color($bk_conf['accent_color'] ?? '', '#F9DA00');
[$bk_w, $bk_h] = bk_card_size($bk_conf, true);
$bk_show_qr = (int)($bk_conf['show_qr'] ?? 1) === 1;
$bk_duplex = ($bk_conf['sheet_layout'] ?? 'pair') === 'duplex';
$bk_font_path = $card_path . '../bulian-kartu/fonts/';

$bk_card_dir = UPLOAD . 'membercard' . DS . $bk_conf['template'] . DS . 'images' . DS;
$bk_has_sign = !empty($bk_conf['signature_file']) && is_file($bk_card_dir . $bk_conf['signature_file']);
$bk_has_stamp = !empty($bk_conf['stamp_file']) && is_file($bk_card_dir . $bk_conf['stamp_file']);

$bk_months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$bk_date = function ($date) use ($bk_months) {
  $t = strtotime((string)$date);
  if (!$t) return '';
  return date('j', $t) . ' ' . $bk_months[(int)date('n', $t)] . ' ' . date('Y', $t);
};

$bk_rules = html_entity_decode((string)($bk_conf['rules'] ?? ''), ENT_QUOTES, 'UTF-8');
// setelan lama bisa menyisakan "rn" dari \r\n yang di-stripslashes; buang di antara tag
$bk_rules = preg_replace('/>(?:\s|rn)+</', '><', $bk_rules);

// isi kartu dihitung sekali, dipakai untuk sisi depan dan belakang
$bk_cards = [];
foreach (array_merge(...array_values($chunked_card_arrays ?: [[]])) as $card) {
  $member_id = (string)$card['member_id'];
  $photo = '';
  if (!empty($card['member_image']) && is_file(IMGBS . 'persons' . DS . basename($card['member_image']))) {
    $photo = SWB . IMG . '/persons/' . rawurlencode(basename($card['member_image']));
  }
  $barcode = bk_card_barcode($member_id);
  // barcode di samping QR selebar ~29 mm; nomor panjang (garis < 0,2 mm) dapat baris sendiri
  $modules = $barcode && preg_match('/viewBox="0 0 ([\d.]+)/', $barcode, $m) ? (float)$m[1] : 0;
  $bk_cards[] = $card + [
    'id' => $member_id,
    'photo' => $photo,
    'barcode' => $barcode,
    'qr' => $bk_show_qr ? bk_card_qr($member_id) : null,
    'wide' => $bk_show_qr && $modules > 145,
  ];
}

// pair: depan dan belakang berdampingan, 3 anggota per halaman.
// duplex: 9 kartu depan, lalu halaman belakang dengan kolom dicerminkan
// sehingga cocok saat dicetak bolak-balik (balik pada tepi panjang).
$bk_pages = [];
if ($bk_duplex) {
  foreach (array_chunk($bk_cards, 9) as $chunk) {
    $bk_pages[] = ['side' => 'front', 'cards' => $chunk];
    $bk_pages[] = ['side' => 'back', 'cards' => $chunk];
  }
} else {
  foreach (array_chunk($bk_cards, 3) as $chunk) $bk_pages[] = ['side' => 'pair', 'cards' => $chunk];
}

$bk_front = function ($card) use ($bk_conf) {
  ob_start(); ?>
    <section class="card front" aria-label="Kartu anggota sisi depan">
      <div class="head">
        <span class="slot"></span>
        <span class="kicker"><?= bk_card_text($bk_conf['front_header1_text'] ?? 'Kartu Anggota Perpustakaan') ?></span>
        <span class="lib"><?= bk_card_text($bk_conf['front_header2_text'] ?? '') ?></span>
        <?php if (trim((string)($bk_conf['parent_text'] ?? '')) !== '') : ?>
        <span class="parent"><?= bk_card_text($bk_conf['parent_text']) ?></span>
        <?php endif; ?>
      </div>
      <div class="photo">
        <?php if ($card['photo']) : ?><img src="<?= $card['photo'] ?>" alt="">
        <?php else : ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="#7E8C88" stroke-width="1.4" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"></path></svg>
        <span>Foto 3×4</span>
        <?php endif; ?>
      </div>
      <div class="who">
        <p class="name"><?= bk_card_text($card['member_name']) ?></p>
        <p class="id"><?= bk_card_text($card['id']) ?></p>
        <p class="meta"><?php
          $meta = array_filter([trim((string)($card['member_type_name'] ?? '')), 'berlaku s.d. ' . date('d-m-Y', strtotime($card['expire_date']))]);
          echo bk_card_text(implode(' · ', $meta));
        ?></p>
        <?php if (!empty($card['inst_name'])) : ?>
        <p class="inst"><?= bk_card_text($card['inst_name']) ?></p>
        <?php endif; ?>
      </div>
      <div class="codes<?= $card['wide'] ? ' wide' : '' ?><?= $card['qr'] ? '' : ' no-qr' ?>">
        <div class="barcode">
          <?php if ($card['barcode']) : echo $card['barcode']; else : ?>
          <img src="<?= SWB . IMG . '/barcodes/' . rawurlencode(str_replace(' ', '_', $card['id'])) ?>.png" alt="Barcode <?= bk_card_text($card['id']) ?>">
          <?php endif; ?>
        </div>
        <?php if ($card['qr']) : ?><div class="qr"><?= $card['qr'] ?></div><?php endif; ?>
      </div>
      <div class="stripe"></div>
    </section>
  <?php return ob_get_clean();
};

$bk_back = function ($card) use ($bk_conf, $bk_rules, $bk_date, $bk_has_sign, $bk_has_stamp, $card_stamp, $card_signature) {
  ob_start(); ?>
    <section class="card back" aria-label="Kartu anggota sisi belakang">
      <div class="head">
        <span class="slot"></span>
        <span class="kicker">Ketentuan anggota</span>
        <span class="lib"><?= bk_card_text($bk_conf['back_header1_text'] ?? '') ?></span>
      </div>
      <div class="rules"><?= $bk_rules ?></div>
      <div class="sign">
        <div><?= bk_card_text($bk_conf['city'] ?? '') ?>, <?= $bk_date($card['register_date']) ?></div>
        <div><?= bk_card_text($bk_conf['title'] ?? '') ?></div>
        <div class="space">
          <?php if ($bk_has_stamp) : ?><img class="stamp" src="<?= $card_stamp ?>" alt=""><?php endif; ?>
          <?php if ($bk_has_sign) : ?><img class="signature" src="<?= $card_signature ?>" alt=""><?php endif; ?>
        </div>
        <div class="official"><?= bk_card_text($bk_conf['officials'] ?? '') ?></div>
        <div><?= bk_card_text($bk_conf['officials_id'] ?? '') ?></div>
      </div>
      <div class="addr"><?= bk_card_text($bk_conf['back_header2_text'] ?? '') ?></div>
      <div class="stripe"></div>
    </section>
  <?php return ob_get_clean();
};
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= __('Member Card Printing') ?></title>
<style>
@font-face { font-family: 'Lato'; font-weight: 400; src: url('<?= $bk_font_path ?>lato-400.woff2') format('woff2'); }
@font-face { font-family: 'Lato'; font-weight: 700; src: url('<?= $bk_font_path ?>lato-700.woff2') format('woff2'); }
@font-face { font-family: 'Lato'; font-weight: 900; src: url('<?= $bk_font_path ?>lato-900.woff2') format('woff2'); }

@page { size: A4; margin: 10mm; }
* { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
body { margin: 0; font-family: 'Lato', 'Segoe UI', Arial, sans-serif; color: #15201E; background: #F1F3F6; }
p { margin: 0; }

/* lembar A4: area cetak 190 × 277 mm, kartu di tengah agar sisi belakang duplex sejajar */
.page { width: 190mm; margin: 0 auto; padding: 6mm 0; }
.page + .page { break-before: page; page-break-before: always; }
.page-label { font-size: 3mm; font-weight: 700; color: #4B5A57; text-align: center; margin-bottom: 2mm; }
.grid { display: grid; grid-template-columns: repeat(3, <?= $bk_w ?>cm); grid-auto-rows: <?= $bk_h ?>cm; gap: 4mm; justify-content: center; }
.pair .grid { grid-template-columns: repeat(2, <?= $bk_w ?>cm); column-gap: 3mm; }

.card {
  width: <?= $bk_w ?>cm; height: <?= $bk_h ?>cm;
  position: relative; overflow: hidden;
  background: #FFFFFF; border: 0.2mm solid #C9D1DB; border-radius: 3mm;
  break-inside: avoid; page-break-inside: avoid;
  display: flex; flex-direction: column; align-items: center;
  font-size: 2mm; line-height: 1.25;
}
.stripe { position: absolute; left: 0; right: 0; bottom: 0; height: 1.2mm; background: <?= $bk_accent ?>; }

/* kepala kartu, dengan penanda lubang lanyard */
.head { width: 100%; flex: 0 0 auto; padding: 3.2mm 3.2mm 0; background: <?= $bk_primary ?>; color: #FFFFFF; display: flex; flex-direction: column; align-items: center; gap: 0.7mm; text-align: center; }
.head .slot { width: 6.2mm; height: 1.1mm; border-radius: 1mm; background: rgba(255, 255, 255, 0.35); margin-bottom: 0.4mm; }
.head .kicker { font-size: 1.6mm; font-weight: 900; letter-spacing: 0.14em; text-transform: uppercase; color: <?= $bk_accent ?>; }
.head .lib { font-size: 3.1mm; font-weight: 900; line-height: 1.1; max-width: 100%; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.head .parent { font-size: 1.8mm; opacity: 0.9; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

/* sisi depan */
.front .head { height: 20mm; }
.photo { flex: 0 0 auto; margin-top: -5.3mm; width: 19.4mm; height: 25.7mm; border-radius: 1.6mm; background: #E9EEEA; border: 0.5mm solid #FFFFFF; box-shadow: 0 0.5mm 1.6mm rgba(0, 0, 0, 0.12); overflow: hidden; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.7mm; color: #4B5A57; font-size: 1.8mm; }
.photo img { width: 100%; height: 100%; object-fit: cover; }
.photo svg { width: 8.5mm; height: 8.5mm; }
.who { width: 100%; padding: 1.9mm 3.6mm 0; display: flex; flex-direction: column; align-items: center; gap: 0.6mm; text-align: center; }
.who .name { font-size: 3.35mm; font-weight: 900; line-height: 1.15; max-height: 7.8mm; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
.who .id { font-family: 'DejaVu Sans Mono', 'Consolas', 'Courier New', monospace; font-size: 3mm; font-weight: 700; letter-spacing: 0.04em; }
.who .meta { font-size: 1.95mm; color: #4B5A57; }
.who .inst { font-size: 1.7mm; color: #4B5A57; max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.codes { margin-top: auto; width: 100%; padding: 0 3.5mm 3mm; display: flex; align-items: center; justify-content: space-between; gap: 1.8mm; }
.codes .barcode { flex: 1; min-width: 0; height: 11mm; }
.codes .barcode svg, .codes .barcode img { width: 100%; height: 100%; display: block; }
.codes .qr { flex: 0 0 14.5mm; height: 14.5mm; }
.codes .qr svg { width: 100%; height: 100%; display: block; }
/* nomor anggota panjang: barcode selebar kartu, QR di bawahnya */
.codes.wide { flex-direction: column; gap: 1.2mm; }
.codes.wide .barcode { flex: 0 0 8mm; width: 100%; }
.codes.wide .qr { flex: 0 0 10.5mm; width: 10.5mm; height: 10.5mm; }
.codes.no-qr .barcode { height: 12mm; }

/* sisi belakang */
.back .head { padding-bottom: 2.6mm; }
.rules { width: 100%; flex: 1; min-height: 0; overflow: hidden; padding: 3mm 3.6mm 0; font-size: 2.15mm; line-height: 1.3; }
.rules ul, .rules ol { margin: 0; padding-left: 3.2mm; }
.rules li { margin-bottom: 0.8mm; }
.rules p { margin: 0 0 0.8mm; }
.sign { width: 100%; padding: 1.6mm 3.6mm 0; text-align: center; font-size: 1.75mm; }
.sign .space { height: 7mm; position: relative; display: flex; justify-content: center; align-items: center; }
.sign .space img.signature { height: 7mm; position: relative; }
.sign .space img.stamp { position: absolute; left: 50%; top: -0.5mm; height: 9mm; margin-left: -12mm; opacity: 0.85; }
.sign .official { font-weight: 900; font-size: 1.95mm; }
.addr { width: calc(100% - 7.2mm); margin: 2mm 3.6mm 3.4mm; padding-top: 1.6mm; border-top: 0.2mm solid #DCDDD6; text-align: center; font-size: 1.6mm; color: #4B5A57; }

.toolbar { padding: 10px 16px 0; font-family: 'Lato', Arial, sans-serif; text-align: center; }
.toolbar a { display: inline-block; background: <?= $bk_primary ?>; color: #FFFFFF; text-decoration: none; padding: 10px 16px; border-radius: 6px; font-weight: 700; }
.toolbar small { display: block; margin-top: 6px; color: #4B5A57; }
@media print {
  body { background: #FFFFFF; }
  .toolbar, .page-label { display: none; }
  .page { padding: 0; }
  .card { border-color: #BFC7D1; }
}
</style>
</head>
<body>
<div class="toolbar">
  <a href="#" onclick="window.print(); return false;"><?= __('Print Again') ?></a>
  <small>
    Kartu tegak <?= $bk_w ?> × <?= $bk_h ?> cm. Pilih kertas A4, skala 100% (matikan "Fit to page").
    <?php if ($bk_duplex) : ?>Cetak bolak-balik, balik pada tepi panjang (long edge).<?php endif; ?>
  </small>
</div>
<?php foreach ($bk_pages as $bk_n => $bk_page) : ?>
<div class="page <?= $bk_page['side'] ?>">
  <?php if ($bk_page['side'] !== 'pair') : ?>
  <p class="page-label">Halaman <?= $bk_n + 1 ?> · <?= $bk_page['side'] === 'front' ? 'sisi depan' : 'sisi belakang (kolom dicerminkan)' ?></p>
  <?php endif; ?>
  <div class="grid">
  <?php foreach ($bk_page['cards'] as $i => $card) :
    if ($bk_page['side'] === 'pair') {
      echo $bk_front($card), $bk_back($card);
      continue;
    }
    $col = $bk_page['side'] === 'front' ? $i % 3 + 1 : 3 - $i % 3; ?>
    <div style="grid-row: <?= intdiv($i, 3) + 1 ?>; grid-column: <?= $col ?>"><?= $bk_page['side'] === 'front' ? $bk_front($card) : $bk_back($card) ?></div>
  <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>
<script>self.print();</script>
</body>
</html>
