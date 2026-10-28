<?php
/**
 * bulian-kampus: tab "Keranjang" di area anggota.
 * Aksi memakai proses bawaan lib/contents/member.inc.php
 * (basketRemove, clear_biblio, sendReserve) dengan token CSRF anggota.
 */

$bk_basket = $bk_basket ?? bk_basket_items($dbs);
$bk_token = htmlspecialchars($_SESSION['member_csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
$bk_max = (int)($sysconf['max_biblio_mark'] ?? 0);
$bk_n = count($bk_basket);
// pemesanan ditutup untuk anggota kedaluwarsa/menunggu, atau tipe yang tidak boleh memesan
$bk_block = '';
if (in_array($bk_status['key'], ['expired', 'pending'], true)) {
  $bk_block = bk_t('Pemesanan tidak tersedia selama keanggotaan belum aktif.', 'Requests are unavailable while your membership is not active.');
} elseif (isset($_SESSION['m_can_reserve']) && (string)$_SESSION['m_can_reserve'] === '0') {
  $bk_block = bk_t('Tipe keanggotaan Anda tidak mengizinkan pemesanan judul.', 'Your membership type does not allow title requests.');
}
?>
<section class="bk-basket" aria-labelledby="bk-basket-h">
  <div class="bk-panel-head">
    <div>
      <h2 id="bk-basket-h"><?= bk_t('Keranjang judul', 'Title basket') ?></h2>
      <p><?= bk_t('Judul di keranjang belum dipesan. Kirim pesanan agar petugas menyiapkan bukunya.', 'Titles in the basket are not requested yet. Send the request so the staff can prepare the books.') ?></p>
    </div>
    <span class="bk-panel-head__aside"><strong><?= $bk_n ?></strong> <?= bk_t('dari', 'of') ?> <?= $bk_max ?> <?= bk_t('judul maksimal', 'titles max') ?></span>
  </div>

  <?php if ($bk_n) : ?>
  <form id="bkBasketForm" class="bk-card bk-basket__box" method="post" action="index.php?p=member&amp;sec=title_basket"
        data-confirm-remove="<?= bk_t('Hapus %n judul terpilih?', 'Remove %n selected titles?') ?>"
        data-confirm-remove-text="<?= bk_t('Judul yang dipilih dihapus dari keranjang.', 'The selected titles are removed from the basket.') ?>">
    <input type="hidden" name="__member_csrf" value="<?= $bk_token ?>">
    <input type="hidden" name="basketRemove" value="1">
    <div class="bk-basket__bar">
      <div class="bk-basket__group">
        <label class="bk-check"><input type="checkbox" data-bk-all> <?= bk_t('Pilih semua', 'Select all') ?></label>
        <button type="submit" class="bk-btn bk-btn--ghost bk-btn--sm" data-bk-remove disabled><i class="fas fa-trash-alt" aria-hidden="true"></i> <?= bk_t('Hapus terpilih', 'Remove selected') ?> <span data-bk-picked></span></button>
      </div>
      <div class="bk-basket__group">
        <button type="submit" form="bkBasketClear" class="bk-btn bk-btn--text-danger bk-btn--sm"><?= bk_t('Kosongkan keranjang', 'Clear basket') ?></button>
        <button type="button" id="bk-reserve" class="bk-btn bk-btn--primary" data-bk-reserve<?= $bk_block ? ' disabled aria-describedby="bk-reserve-why"' : '' ?>
                data-title="<?= bk_t('Kirim pesanan', 'Send request for') ?> <?= $bk_n ?> <?= bk_t('judul?', 'titles?') ?>"
                data-text="<?= bk_t('Pesanan dikirim ke petugas perpustakaan. Buku yang sedang dipinjam disiapkan setelah dikembalikan.', 'The request goes to the library staff. Books on loan are prepared once they are returned.') ?>"
                data-wait="<?= bk_t('Mengirim pesanan…', 'Sending request…') ?>"
                data-fail="<?= bk_t('Pesanan gagal dikirim. Muat ulang halaman lalu coba lagi.', 'The request could not be sent. Reload the page and try again.') ?>">
          <i class="fas fa-paper-plane" aria-hidden="true"></i> <?= bk_t('Kirim pesanan', 'Send request') ?> (<?= $bk_n ?> <?= bk_t('judul', 'titles') ?>)
        </button>
      </div>
    </div>
    <?php if ($bk_block) : ?><p id="bk-reserve-why" class="bk-basket__why"><?= $bk_block ?></p><?php endif; ?>
    <ul class="bk-basket__list">
      <?php foreach ($bk_basket as $b) :
        $id = (int)$b['biblio_id'];
        $title = htmlspecialchars($b['title'], ENT_QUOTES, 'UTF-8');
        $img = trim((string)$b['image']) !== '' && $imagesDisk->isExists('docs/' . $b['image'])
          ? SWB . 'lib/minigalnano/createthumb.php?filename=' . urlencode('images/docs/' . $b['image']) . '&amp;width=120' : '';
        $meta = array_filter([htmlspecialchars((string)$b['author'], ENT_QUOTES, 'UTF-8'), htmlspecialchars((string)$b['publish_year'], ENT_QUOTES, 'UTF-8')]);
      ?>
      <li class="bk-basket__item">
        <input type="checkbox" name="basket[]" value="<?= $id ?>" class="bk-basket__pick" aria-label="<?= bk_t('Pilih', 'Select') ?> <?= $title ?>">
        <span class="bk-cover<?= $img ? '' : ' bk-cover--empty' ?>" aria-hidden="true"><?php if ($img) : ?><img src="<?= $img ?>" alt="" loading="lazy"><?php else : ?><?= mb_strtoupper(mb_substr(strip_tags($b['title']), 0, 1)) ?><?php endif; ?></span>
        <div class="bk-basket__info">
          <a class="bk-basket__title" href="index.php?p=show_detail&amp;id=<?= $id ?>"><?= $title ?></a>
          <?php if ($meta) : ?><span class="bk-basket__meta"><?= implode(' · ', $meta) ?></span><?php endif; ?>
          <span class="bk-basket__tags">
            <?php if (trim((string)$b['call_number']) !== '') : ?><span class="bk-callno"><?= htmlspecialchars($b['call_number'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
            <?php if ($b['available'] > 0) : ?>
            <span class="bk-avail bk-avail--ok"><?= bk_t('Tersedia', 'Available') ?> <?= $b['available'] ?> <?= bk_t('eksemplar', 'copies') ?></span>
            <?php elseif ((int)$b['copies'] > 0) : ?>
            <span class="bk-avail bk-avail--out"><?= bk_t('Sedang dipinjam', 'On loan') ?></span>
            <?php else : ?>
            <span class="bk-avail bk-avail--none"><?= bk_t('Belum ada eksemplar', 'No copies yet') ?></span>
            <?php endif; ?>
          </span>
        </div>
        <button type="submit" form="bkRemove<?= $id ?>" class="bk-iconbtn" aria-label="<?= bk_t('Hapus', 'Remove') ?> <?= $title ?> <?= bk_t('dari keranjang', 'from the basket') ?>"><i class="fas fa-times" aria-hidden="true"></i></button>
      </li>
      <?php endforeach; ?>
    </ul>
  </form>

  <?php // form terpisah untuk hapus satu judul dan kosongkan keranjang (tidak boleh bersarang) ?>
  <?php foreach ($bk_basket as $b) : ?>
  <form id="bkRemove<?= (int)$b['biblio_id'] ?>" method="post" action="index.php?p=member&amp;sec=title_basket" hidden>
    <input type="hidden" name="__member_csrf" value="<?= $bk_token ?>">
    <input type="hidden" name="basketRemove" value="1">
    <input type="hidden" name="basket[]" value="<?= (int)$b['biblio_id'] ?>">
  </form>
  <?php endforeach; ?>
  <form id="bkBasketClear" method="post" action="index.php?p=member&amp;sec=title_basket" hidden
        data-confirm="<?= bk_t('Kosongkan keranjang?', 'Clear the basket?') ?>"
        data-confirm-text="<?= $bk_n ?> <?= bk_t('judul akan dihapus dari keranjang. Judulnya tetap ada di katalog.', 'titles will be removed from the basket. They stay in the catalogue.') ?>"
        data-ok="<?= bk_t('Kosongkan', 'Clear') ?>">
    <input type="hidden" name="__member_csrf" value="<?= $bk_token ?>">
    <input type="hidden" name="clear_biblio" value="1">
  </form>

  <?php else : ?>
  <div class="bk-empty">
    <span class="bk-empty__icon" aria-hidden="true"><i class="fas fa-shopping-basket"></i></span>
    <h3><?= bk_t('Keranjang masih kosong', 'Your basket is empty') ?></h3>
    <p><?= bk_t('Cari judul di katalog, lalu klik <strong>Tambah ke keranjang</strong> di hasil pencarian atau halaman detail.', 'Search the catalogue, then click <strong>Add to basket</strong> in the results or on a detail page.') ?></p>
    <a class="bk-btn bk-btn--primary" href="index.php?search=search&amp;keywords="><i class="fas fa-search" aria-hidden="true"></i> <?= bk_t('Cari koleksi', 'Search the collection') ?></a>
  </div>
  <?php endif; ?>
</section>

<dialog class="bk-dialog" id="bkDialog" aria-labelledby="bkDialogTitle">
  <form method="dialog" class="bk-dialog__box">
    <h2 id="bkDialogTitle"></h2>
    <p data-bk-dialog-text></p>
    <ul data-bk-dialog-list hidden></ul>
    <div class="bk-dialog__foot">
      <button value="cancel" class="bk-btn bk-btn--ghost" data-bk-dialog-cancel><?= bk_t('Batal', 'Cancel') ?></button>
      <button value="ok" class="bk-btn bk-btn--primary" data-bk-dialog-ok></button>
    </div>
  </form>
</dialog>
<script type="application/json" id="bkBasketData"><?= json_encode([
  'labels' => ['remove' => bk_t('Hapus', 'Remove'), 'send' => bk_t('Kirim pesanan', 'Send request'), 'close' => bk_t('Tutup', 'Close'),
    'sent' => bk_t('Pesanan terkirim. Petugas akan menghubungi Anda bila buku siap diambil.', 'Request sent. The staff will contact you when the books are ready.'),
    'results' => bk_t('Hasil pemesanan', 'Request results')],
  'titles' => array_map(fn ($b) => ['title' => strip_tags($b['title']), 'ok' => $b['available'] > 0], $bk_basket),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
