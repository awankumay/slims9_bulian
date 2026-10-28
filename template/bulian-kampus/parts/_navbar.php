<?php
/**
 * bulian-kampus: kepala halaman resmi instansi
 * (strip laman resmi + aksesibilitas, identitas dua logo, menu utama)
 */

$bk_page = $_GET['p'] ?? (isset($_GET['search']) ? 'search' : 'home');
$bk_sso = bk_url('bk_link_sso');
$bk_repo = bk_url('bk_link_repository');
$bk_eres = bk_url('bk_link_eresources');
$bk_ppid = bk_url('bk_link_ppid');

$main_menus = [
  'home' => ['text' => bk_t('Beranda', 'Home'), 'url' => 'index.php'],
  'search' => ['text' => bk_t('Katalog', 'Catalogue'), 'url' => 'index.php?search=search&keywords='],
];
if ($bk_repo) $main_menus['repository'] = ['text' => bk_t('Repositori', 'Repository'), 'url' => $bk_repo, 'raw' => true];
if ($bk_eres) $main_menus['eresources'] = ['text' => 'E-Resources', 'url' => $bk_eres, 'raw' => true];
$main_menus['libinfo'] = ['text' => bk_t('Layanan & Profil', 'Services & Profile'), 'url' => 'index.php?p=libinfo'];
$main_menus['news'] = ['text' => __('News'), 'url' => 'index.php?p=news'];
$main_menus['librarian'] = ['text' => __('Librarian'), 'url' => 'index.php?p=librarian'];
if ($bk_ppid) $main_menus['ppid'] = ['text' => bk_t('Informasi Publik', 'Public Information'), 'url' => $bk_ppid, 'raw' => true];

// language list (same source as the default theme)
$langstr = '';
$current_lang = ['name' => '', 'code' => 'id'];
$select_lang = bk_lang();
foreach ($available_languages ?? [] as $lang_index) {
  $lang_code = $lang_index[0];
  $lang_name = $lang_index[1];
  $code_arr = explode('_', $lang_code);
  $code_flag = strtolower($code_arr[1] ?? $code_arr[0]);
  if ($lang_code == $select_lang) $current_lang = ['name' => $lang_name, 'code' => $code_flag];
  $langstr .= '<a class="dropdown-item" href="index.php?select_lang=' . $lang_code . '"><span class="flag-icon flag-icon-' . $code_flag . ' mr-2"></span> ' . $lang_name . '</a>';
}

$bk_ministry_logo = bk_url('bk_ministry_logo');
if ($bk_ministry_logo === '' && trim((string)($sysconf['template']['bk_ministry_logo'] ?? '')) !== '' && preg_match('@^[A-Za-z0-9_./-]+$@', $sysconf['template']['bk_ministry_logo'])) {
  // relative path inside the SLiMS folder, e.g. images/default/logo-kementerian.png
  $bk_ministry_logo = SWB . ltrim($sysconf['template']['bk_ministry_logo'], '/');
}
?>
<header id="bk-header" class="bk-header">
<div class="bk-topstrip">
    <div class="bk-wrap bk-topstrip__in">
        <span class="bk-official"><span class="bk-flag" aria-hidden="true"><i></i><i></i></span><?= bk_t('Laman resmi', 'Official site of') ?> <?= bk_opt('bk_parent_name') ?> · <?= bk_opt('bk_ministry_name') ?></span>
        <span class="bk-a11y" role="group" aria-label="<?= bk_t('Pengaturan tampilan', 'Display settings') ?>">
            <span class="bk-a11y__label"><?= bk_t('Tampilan', 'Display') ?>:</span>
            <button type="button" data-bk-size="sm" aria-label="<?= bk_t('Perkecil teks', 'Smaller text') ?>">A−</button>
            <button type="button" data-bk-size="" aria-label="<?= bk_t('Ukuran teks normal', 'Normal text') ?>">A</button>
            <button type="button" data-bk-size="lg" aria-label="<?= bk_t('Perbesar teks', 'Larger text') ?>">A+</button>
            <button type="button" data-bk-contrast aria-pressed="false"><?= bk_t('Kontras tinggi', 'High contrast') ?></button>
            <span class="dropdown">
                <button type="button" class="dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="<?= __('Select Language') ?>">
                    <span class="flag-icon flag-icon-<?= $current_lang['code'] ?>"></span> <?= strtoupper(substr($select_lang, 0, 2)) ?>
                </button>
                <span class="dropdown-menu dropdown-menu-right"><?= $langstr ?></span>
            </span>
        </span>
    </div>
</div>

<div class="bk-masthead">
    <div class="bk-wrap bk-masthead__in">
        <a class="bk-brand" href="index.php">
            <?php
            if (isset($sysconf['logo_image']) && $sysconf['logo_image'] != '' && $imagesDisk->isExists($path = 'default/' . $sysconf['logo_image'])) {
                echo '<img class="bk-brand__logo" alt="Logo ' . bk_opt('bk_parent_name') . '" src="' . SWB . 'lib/minigalnano/createthumb.php?filename=images/' . $path . '&width=200">';
            } else {
                echo '<span class="bk-brand__logo bk-brand__logo--empty" aria-hidden="true">' . bk_t('Logo kampus', 'Campus logo') . '</span>';
            }
            ?>
            <span class="bk-brand__text">
                <span class="bk-brand__unit"><?= $sysconf['library_name'] ?></span>
                <span class="bk-brand__parent"><?= bk_opt('bk_parent_name') ?></span>
                <?php if ($sysconf['template']['classic_library_subname'] && !empty($sysconf['library_subname'])) : ?>
                <span class="bk-brand__sub"><?= $sysconf['library_subname'] ?></span>
                <?php endif; ?>
            </span>
        </a>
        <?php if ($bk_ministry_logo) : ?>
        <img class="bk-ministry" src="<?= $bk_ministry_logo ?>" alt="Logo <?= bk_opt('bk_ministry_name') ?>">
        <?php endif; ?>
    </div>
</div>

    <nav class="bk-nav navbar navbar-expand-lg navbar-dark" aria-label="<?= bk_t('Menu utama', 'Main menu') ?>">
        <div class="bk-wrap bk-nav__in">
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#bkMainMenu"
                    aria-controls="bkMainMenu" aria-expanded="false" aria-label="<?= bk_t('Buka menu', 'Open menu') ?>">
                <span class="navbar-toggler-icon"></span> <span class="bk-nav__menu-label"><?= bk_t('Menu', 'Menu') ?></span>
            </button>
            <div class="collapse navbar-collapse" id="bkMainMenu">
                <ul class="navbar-nav">
                    <?php foreach ($main_menus as $key => $menu) :
                        $active = $key === $bk_page ? ' active' : '';
                        $external = !empty($menu['raw']) && preg_match('@^https?://@', $menu['url']);
                    ?>
                    <li class="nav-item<?= $active ?>">
                        <a class="nav-link" href="<?= $menu['url'] ?>"<?= $active ? ' aria-current="page"' : '' ?><?= $external ? ' target="_blank" rel="noopener"' : '' ?>><?= $menu['text'] ?></a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php if ($is_login) :
              $bk_basket = bk_basket_items($dbs);
              $bk_status = bk_member_status();
              $bk_first = htmlspecialchars(explode(' ', trim(ucwords(mb_strtolower((string)($_SESSION['m_name'] ?? '')))))[0] ?? '', ENT_QUOTES, 'UTF-8');
            ?>
            <ul class="navbar-nav bk-nav__member">
                <li class="nav-item dropdown bk-cart<?= ($bk_page === 'member' && ($_GET['sec'] ?? '') === 'title_basket') ? ' active' : '' ?>">
                    <a class="nav-link bk-nav__btn" href="index.php?p=member&amp;sec=title_basket" id="bkCartMenu" data-toggle="dropdown" data-bk-cart aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-shopping-basket" aria-hidden="true"></i>
                        <span class="bk-nav__label"><?= bk_t('Keranjang', 'Basket') ?></span>
                        <span id="count-basket" class="bk-count" data-rendered="<?= count($bk_basket) ?>"><?= count($_SESSION['m_mark_biblio'] ?? []) ?></span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right bk-pop bk-pop--cart" aria-labelledby="bkCartMenu">
                        <div class="bk-pop__head">
                            <strong><?= bk_t('Keranjang judul', 'Title basket') ?></strong>
                            <span><span data-bk-cart-count><?= count($bk_basket) ?></span> <?= bk_t('dari', 'of') ?> <?= (int)$sysconf['max_biblio_mark'] ?> <?= bk_t('judul', 'titles') ?></span>
                        </div>
                        <?php if ($bk_basket) : ?>
                        <ul class="bk-pop__list" data-bk-cart-list>
                            <?php foreach (array_slice($bk_basket, 0, 5) as $b) : ?>
                            <li>
                                <span class="bk-pop__title"><?= htmlspecialchars($b['title'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="bk-avail bk-avail--<?= $b['available'] > 0 ? 'ok' : ((int)$b['copies'] > 0 ? 'out' : 'none') ?>"><?= $b['available'] > 0 ? bk_t('Tersedia', 'Available') . ' ' . $b['available'] . ' ' . bk_t('eksemplar', 'copies') : ((int)$b['copies'] > 0 ? bk_t('Sedang dipinjam', 'On loan') : bk_t('Belum ada eksemplar', 'No copies yet')) ?></span>
                            </li>
                            <?php endforeach; ?>
                            <?php if (count($bk_basket) > 5) : ?><li class="bk-pop__more">+<?= count($bk_basket) - 5 ?> <?= bk_t('judul lain', 'more') ?></li><?php endif; ?>
                        </ul>
                        <?php endif; ?>
                        <p class="bk-pop__empty" data-bk-cart-empty<?= $bk_basket ? ' hidden' : '' ?>><?= bk_t('Keranjang masih kosong. Tambahkan judul dari hasil pencarian.', 'Your basket is empty. Add titles from the search results.') ?></p>
                        <p class="bk-pop__empty" data-bk-cart-stale hidden><?= bk_t('Isi keranjang berubah. Buka keranjang untuk melihat daftarnya.', 'Your basket changed. Open it to see the list.') ?></p>
                        <div class="bk-pop__foot">
                            <a class="bk-btn bk-btn--ghost" href="index.php?p=member&amp;sec=title_basket"><?= bk_t('Lihat keranjang', 'View basket') ?></a>
                            <?php if ($bk_basket) : ?>
                            <a class="bk-btn bk-btn--primary" href="index.php?p=member&amp;sec=title_basket#bk-reserve"><?= bk_t('Kirim pesanan', 'Send request') ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
                <li class="nav-item dropdown<?= $bk_page === 'member' && ($_GET['sec'] ?? '') !== 'title_basket' ? ' active' : '' ?>">
                    <a class="nav-link bk-nav__btn dropdown-toggle" href="index.php?p=member" id="bkMemberMenu" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="<?= bk_t('Menu anggota', 'Member menu') ?>">
                        <span class="bk-avatar" aria-hidden="true"><?= bk_initials($_SESSION['m_name'] ?? '') ?></span>
                        <span class="bk-nav__label"><?= $bk_first ?></span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right bk-pop bk-pop--member" aria-labelledby="bkMemberMenu">
                        <div class="bk-pop__who">
                            <span class="bk-avatar bk-avatar--lg" aria-hidden="true"><?= bk_initials($_SESSION['m_name'] ?? '') ?></span>
                            <span>
                                <strong><?= htmlspecialchars($_SESSION['m_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong>
                                <span>ID <?= htmlspecialchars($_SESSION['mid'] ?? '', ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($_SESSION['m_member_type'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="bk-state bk-state--<?= $bk_status['key'] ?>"><?= $bk_status['label'] ?><?= in_array($bk_status['key'], ['active', 'soon'], true) ? ' · ' . bk_t('s.d.', 'until') . ' ' . $bk_status['expire_short'] : '' ?></span>
                            </span>
                        </div>
                        <a class="dropdown-item" href="index.php?p=member"><i class="fas fa-book" aria-hidden="true"></i> <?= bk_t('Pinjaman saya', 'My loans') ?></a>
                        <a class="dropdown-item" href="index.php?p=member&amp;sec=bookmark"><i class="fas fa-bookmark" aria-hidden="true"></i> <?= bk_t('Judul tertandai', 'Bookmarks') ?></a>
                        <a class="dropdown-item" href="index.php?p=member&amp;sec=title_basket"><i class="fas fa-shopping-basket" aria-hidden="true"></i> <?= bk_t('Keranjang judul', 'Title basket') ?> <span class="bk-pop__n"><?= count($_SESSION['m_mark_biblio'] ?? []) ?></span></a>
                        <a class="dropdown-item" href="index.php?p=member&amp;sec=my_account"><i class="fas fa-key" aria-hidden="true"></i> <?= bk_t('Akun &amp; kata sandi', 'Account &amp; password') ?></a>
                        <a class="dropdown-item" href="index.php?p=member&amp;sec=membercard" target="_blank" rel="noopener"><i class="fas fa-address-card" aria-hidden="true"></i> <?= bk_t('Kartu anggota', 'Library card') ?> <span class="bk-pop__n"><?= bk_t('tab baru', 'new tab') ?></span></a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item bk-pop__out" href="index.php?p=member&amp;logout=1"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> <?= bk_t('Keluar', 'Log out') ?></a>
                    </div>
                </li>
            </ul>
            <?php else : ?>
            <ul class="navbar-nav bk-nav__member">
                <li class="nav-item<?= $bk_page === 'member' ? ' active' : '' ?>">
                    <a class="nav-link bk-nav__cta" href="<?= $bk_sso ?: 'index.php?p=member' ?>"><?= $bk_sso ? bk_t('Masuk SSO', 'SSO Login') : __('Member Area') ?></a>
                </li>
            </ul>
            <?php endif; ?>
        </div>
    </nav>
</header>
