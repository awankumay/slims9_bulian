<?php
/**
 * @Created by          : Waris Agung Widodo (ido.alit@gmail.com)
 * @Date                : 2019-01-30 20:58
 * @File name           : _member.php
 */

?>

<?php if ($is_login) :
    // ringkasan anggota, tab sendiri (dengan jumlah) dan, untuk keranjang & akun,
    // tampilan tema sendiri. Keluaran member.inc.php tetap dimuat (skrip CSRF, pesan,
    // tab lain) tetapi bagian yang sudah digantikan disembunyikan lewat CSS.
    $bk_sec = $_GET['sec'] ?? 'current_loan';
    $bk_own = in_array($bk_sec, ['title_basket', 'my_account'], true);
    $bk_status = bk_member_status();
    $bk_counts = bk_member_counts($dbs);
    $bk_e = fn ($k) => htmlspecialchars((string)($_SESSION[$k] ?? ''), ENT_QUOTES, 'UTF-8');
    $bk_tabs = [
      'current_loan' => [bk_t('Pinjaman terkini', 'Current loans'), 'index.php?p=member', $bk_counts['loans']],
      'bookmark' => [bk_t('Judul tertandai', 'Bookmarks'), 'index.php?p=member&amp;sec=bookmark', $bk_counts['bookmark']],
      'title_basket' => [bk_t('Keranjang', 'Basket'), 'index.php?p=member&amp;sec=title_basket', $bk_counts['basket']],
      'loan_history' => [bk_t('Riwayat pinjam', 'Loan history'), 'index.php?p=member&amp;sec=loan_history', null],
      'my_account' => [bk_t('Akun saya', 'My account'), 'index.php?p=member&amp;sec=my_account', null],
    ];
?>

    <div class="member-area">

        <div id="bk-main" class="bk-wrap bk-page bk-member<?= $bk_own ? ' bk-member--own' : '' ?>">
          <section class="bk-profile" aria-label="<?= bk_t('Ringkasan anggota', 'Member summary') ?>">
            <span class="bk-profile__avatar" aria-hidden="true"><?= bk_initials($_SESSION['m_name'] ?? '') ?></span>
            <div class="bk-profile__body">
              <h1 class="bk-profile__name"><?= $bk_e('m_name') ?></h1>
              <p class="bk-profile__meta">
                <span>ID <strong class="bk-mono"><?= $bk_e('mid') ?></strong></span>
                <span><?= $bk_e('m_member_type') ?></span>
                <?php if (trim((string)($_SESSION['m_institution'] ?? '')) !== '') : ?><span><?= $bk_e('m_institution') ?></span><?php endif; ?>
                <span class="bk-state bk-state--<?= $bk_status['key'] ?>"><?= $bk_status['label'] ?><?php
                  if ($bk_status['key'] === 'active') echo ' · ' . bk_t('berlaku s.d.', 'valid until') . ' ' . $bk_status['expire_short'];
                  elseif ($bk_status['key'] === 'soon') echo ' · ' . $bk_status['expire_short'];
                ?></span>
              </p>
            </div>
            <a class="bk-btn bk-btn--ghost" href="index.php?p=member&amp;sec=membercard" target="_blank" rel="noopener"><i class="fas fa-address-card" aria-hidden="true"></i> <?= bk_t('Kartu anggota', 'Library card') ?></a>
          </section>

          <?php if ($bk_status['key'] !== 'active') : ?>
          <div class="bk-alert bk-alert--<?= $bk_status['key'] === 'soon' ? 'warn' : 'danger' ?>" role="status">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <span><?php
              if ($bk_status['key'] === 'pending') echo bk_t('Keanggotaan Anda masih menunggu persetujuan petugas. Peminjaman dan pemesanan belum bisa dilakukan.', 'Your membership is waiting for approval. Loans and requests are not available yet.');
              elseif ($bk_status['key'] === 'expired') echo bk_t('Keanggotaan Anda sudah berakhir pada ', 'Your membership ended on ') . $bk_status['expire'] . bk_t('. Peminjaman dan pemesanan judul tidak bisa dilakukan sampai diperpanjang di meja layanan.', '. Loans and title requests are unavailable until it is renewed at the service desk.');
              else echo bk_t('Keanggotaan Anda berakhir dalam ', 'Your membership ends in ') . $bk_status['days'] . bk_t(' hari (', ' days (') . $bk_status['expire'] . bk_t('). Perpanjang di meja layanan agar tetap bisa meminjam.', '). Renew it at the service desk to keep borrowing.');
            ?></span>
          </div>
          <?php endif; ?>

          <div id="bk-member-flash" aria-live="polite"></div>

          <nav class="bk-tabs" aria-label="<?= bk_t('Bagian area anggota', 'Member area sections') ?>">
            <?php foreach ($bk_tabs as $key => [$label, $url, $n]) : $on = $key === $bk_sec; ?>
            <a class="bk-tabs__item<?= $on ? ' is-active' : '' ?>" href="<?= $url ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= $label ?><?php if ($n !== null) : ?> <span class="bk-tabs__n"<?= $key === 'title_basket' ? ' data-bk-basket-n' : '' ?>><?= (int)$n ?></span><?php endif; ?></a>
            <?php endforeach; ?>
          </nav>

          <?php
          if ($bk_sec === 'title_basket') include __DIR__ . '/member/_basket.php';
          elseif ($bk_sec === 'my_account') include __DIR__ . '/member/_account.php';
          ?>

          <div class="bk-core"><?php echo $main_content; ?></div>
        </div>

    </div>

<?php else: ?>

    <div class="result-search page-member-area">
        <section class="bk-searchbar">
          <?php
          // ------------------------------------------------------------------------
          // include search form part
          // ------------------------------------------------------------------------
          include '_search-form.php'; ?>
        </section>

        <div id="bk-main" class="container bk-page">
          <div class="row">
              <div class="col-md-8">
                <?php echo $main_content; ?>
              </div>
              <div class="col-md-4"></div>
          </div>
        </div>
    </div>

<?php endif; ?>
