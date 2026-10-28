<?php
/**
 * bulian-kampus: beranda
 */

$bk_quick = [
  ['t' => bk_t('Bebas pustaka', 'Library clearance'), 'd' => bk_t('Surat keterangan bebas pinjam untuk yudisium/wisuda', 'Clearance letter for graduation'), 'u' => bk_url('bk_link_bebaspustaka'), 'i' => 'fa-file-alt'],
  ['t' => bk_t('Unggah karya akhir', 'Submit final thesis'), 'd' => bk_t('Serahkan skripsi/TA/tesis ke repositori', 'Deposit your thesis to the repository'), 'u' => bk_url('bk_link_upload'), 'i' => 'fa-upload'],
  ['t' => bk_t('Cek similaritas', 'Similarity check'), 'd' => bk_t('Ajukan pemeriksaan kemiripan naskah', 'Request a similarity check'), 'u' => bk_url('bk_link_similarity'), 'i' => 'fa-copy'],
  ['t' => bk_t('Repositori', 'Repository'), 'd' => bk_t('Karya ilmiah sivitas akademika', 'Scholarly works of the campus'), 'u' => bk_url('bk_link_repository'), 'i' => 'fa-university'],
  ['t' => 'E-Resources', 'd' => bk_t('E-journal dan e-book langganan', 'Subscribed e-journals and e-books'), 'u' => bk_url('bk_link_eresources'), 'i' => 'fa-globe'],
  ['t' => __('Member Area'), 'd' => bk_t('Pinjaman, perpanjangan, riwayat, dan kartu', 'Loans, renewals, history and card'), 'u' => 'index.php?p=member', 'i' => 'fa-id-card'],
  ['t' => bk_t('Pencarian lanjutan', 'Advanced search'), 'd' => bk_t('Cari menurut judul, pengarang, subjek, ISBN', 'Search by title, author, subject, ISBN'), 'u' => '#', 'i' => 'fa-search-plus', 'modal' => '#adv-modal'],
  ['t' => bk_t('Profil & layanan', 'Profile & services'), 'd' => bk_t('Informasi perpustakaan dan tata tertib', 'Library information and rules'), 'u' => 'index.php?p=libinfo', 'i' => 'fa-info-circle'],
];
$bk_quick = array_values(array_filter($bk_quick, fn ($q) => $q['u'] !== ''));

$bk_public = [
  ['t' => bk_t('Standar pelayanan', 'Service standards'), 'd' => bk_t('Persyaratan, prosedur, waktu layanan', 'Requirements, procedures and service times'), 'u' => bk_url('bk_link_standar')],
  ['t' => bk_t('Survei kepuasan (SKM)', 'Satisfaction survey (SKM)'), 'd' => bk_t('Isi survei setelah menerima layanan', 'Fill in the survey after being served'), 'u' => bk_url('bk_link_skm')],
  ['t' => bk_t('Hasil SKM', 'SKM results'), 'd' => bk_t('Indeks kepuasan per semester', 'Satisfaction index per semester'), 'u' => bk_url('bk_link_skm_result')],
  ['t' => 'SP4N-LAPOR!', 'd' => bk_t('Sampaikan pengaduan pelayanan publik', 'Report a public service complaint'), 'u' => bk_url('bk_link_lapor')],
];
$bk_public = array_values(array_filter($bk_public, fn ($q) => $q['u'] !== ''));
?>


<main id="bk-main">
<section class="bk-hero" aria-labelledby="bk-hero-title">
    <div class="bk-wrap">
        <p class="bk-hero__eyebrow"><?= bk_t('Katalog daring', 'Online catalogue') ?> · OPAC</p>
        <h2 id="bk-hero-title" class="bk-hero__title"><?= bk_t('Temukan koleksi dan karya ilmiah', 'Find collections and scholarly works') ?></h2>
        <?php include '_search-form.php'; ?>
        <p class="bk-hero__also">
            <?= bk_t('Cari juga di', 'Also search') ?>:
            <?php if ($u = bk_url('bk_link_repository')) : ?><a href="<?= $u ?>" target="_blank" rel="noopener"><?= bk_t('Repositori institusi', 'Institutional repository') ?></a> · <?php endif; ?>
            <?php if ($u = bk_url('bk_link_eresources')) : ?><a href="<?= $u ?>" target="_blank" rel="noopener">E-Resources</a> · <?php endif; ?>
            <a href="https://onesearch.id" target="_blank" rel="noopener">Indonesia OneSearch</a>
        </p>
    </div>
</section>

<div id="slims-home">
<section class="bk-section bk-wrap" aria-labelledby="bk-quick-title">
    <h2 id="bk-quick-title" class="bk-h2"><?= bk_t('Layanan cepat', 'Quick services') ?></h2>
    <ul class="bk-quick">
        <?php foreach ($bk_quick as $q) :
            $ext = preg_match('@^https?://@', $q['u']);
        ?>
        <li>
            <a href="<?= $q['u'] ?>"<?= $ext ? ' target="_blank" rel="noopener"' : '' ?><?= isset($q['modal']) ? ' data-toggle="modal" data-target="' . $q['modal'] . '"' : '' ?>>
                <i class="fas <?= $q['i'] ?>" aria-hidden="true"></i>
                <span class="bk-quick__t"><?= $q['t'] ?></span>
                <span class="bk-quick__d"><?= $q['d'] ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</section>

<?php if ($sysconf['template']['bk_new_collection'] ?? 1) : ?>
<section class="bk-section bk-wrap" aria-labelledby="bk-new-title">
    <h2 id="bk-new-title" class="bk-h2"><?= bk_t('Koleksi terbaru', 'New collections') ?></h2>
    <?php $bk_latest = bk_latest_biblio($dbs, $sysconf['template']['classic_new_collection_item'] ?? 6); ?>
    <?php if ($bk_latest) : ?>
    <ul class="bk-books">
        <?php foreach ($bk_latest as $b) :
            $b_title = htmlspecialchars(strip_tags(stripslashes($b['title'])), ENT_QUOTES, 'UTF-8');
            $b_img = trim((string)$b['image']) !== '' && $imagesDisk->isExists('docs/' . $b['image'])
                ? SWB . 'lib/minigalnano/createthumb.php?filename=' . urlencode('images/docs/' . $b['image']) . '&width=240' : '';
        ?>
        <li class="bk-book">
            <a href="index.php?p=show_detail&amp;id=<?= (int)$b['biblio_id'] ?>">
                <span class="bk-book__cover<?= $b_img ? '' : ' bk-book__cover--empty' ?>">
                    <?php if ($b_img) : ?><img loading="lazy" src="<?= $b_img ?>" alt=""><?php else : ?><i class="fas fa-book" aria-hidden="true"></i><?php endif; ?>
                </span>
                <span class="bk-book__title"><?= $b_title ?></span>
            </a>
            <?php if (!empty($b['author'])) : ?>
            <span class="bk-book__author"><?= htmlspecialchars($b['author'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php else : ?>
    <p class="bk-muted"><?= __('No Result') ?></p>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="bk-section bk-wrap bk-info" aria-label="<?= bk_t('Informasi layanan', 'Service information') ?>">
    <div class="bk-card bk-hours">
        <h2 class="bk-h3"><?= bk_t('Jam layanan', 'Opening hours') ?></h2>
        <?php $bk_hours_rows = bk_hours_rows($sysconf['template']['bk_hours'] ?? ''); ?>
        <?php if ($bk_hours_rows) : ?>
        <dl class="bk-hours__list">
            <?php foreach ($bk_hours_rows as [$day, $time]) : ?>
            <div><dt><?= $day ?></dt><dd><?= $time ?></dd></div>
            <?php endforeach; ?>
        </dl>
        <?php else : ?>
        <p><?= bk_opt('bk_hours') ?></p>
        <?php endif; ?>
    </div>
    <div class="bk-card bk-maklumat">
        <h2 class="bk-h3"><?= bk_t('Maklumat pelayanan', 'Service pledge') ?></h2>
        <blockquote>&ldquo;<?= nl2br(bk_opt('bk_maklumat')) ?>&rdquo;</blockquote>
        <p class="bk-maklumat__by"><?= bk_t('Kepala', 'Head of') ?> <?= $sysconf['library_name'] ?></p>
    </div>
</section>

<?php if ($bk_public) : ?>
<section class="bk-section bk-wrap" aria-labelledby="bk-public-title">
    <h2 id="bk-public-title" class="bk-h2"><?= bk_t('Partisipasi publik', 'Public participation') ?></h2>
    <ul class="bk-public">
        <?php foreach ($bk_public as $q) : ?>
        <li><a href="<?= $q['u'] ?>" target="_blank" rel="noopener"><span class="bk-quick__t"><?= $q['t'] ?></span><span class="bk-quick__d"><?= $q['d'] ?></span></a></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php endif; ?>
</div>
</main>
