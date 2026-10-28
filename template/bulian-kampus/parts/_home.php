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
  ['t' => bk_t('Survei kepuasan', 'Satisfaction survey'), 'd' => bk_t('Bantu kami menilai layanan', 'Help us rate our services'), 'u' => bk_url('bk_link_skm')],
  ['t' => 'SP4N-LAPOR!', 'd' => bk_t('Sampaikan pengaduan dan aspirasi', 'Complaints and feedback'), 'u' => bk_url('bk_link_lapor')],
  ['t' => bk_t('Hasil survei', 'Survey results'), 'd' => bk_t('Indeks kepuasan masyarakat', 'Public satisfaction index'), 'u' => bk_url('bk_link_skm_result')],
  ['t' => bk_t('Standar pelayanan', 'Service standards'), 'd' => bk_t('Persyaratan, waktu, dan biaya layanan', 'Requirements, time and fees'), 'u' => bk_url('bk_link_standar')],
];
$bk_public = array_values(array_filter($bk_public, fn ($q) => $q['u'] !== ''));
?>

<section id="section1 container-fluid">
    <header class="c-header bk-header">
      <?php include '_navbar.php'; ?>
    </header>
</section>

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
    <slims-group-subject url="index.php?p=api/subject/latest"></slims-group-subject>
    <slims-collection url="index.php?p=api/biblio/latest"></slims-collection>
</section>
<?php endif; ?>

<section class="bk-section bk-wrap bk-info" aria-label="<?= bk_t('Informasi layanan', 'Service information') ?>">
    <div class="bk-card bk-hours">
        <h2 class="bk-h3"><i class="far fa-clock" aria-hidden="true"></i> <?= bk_t('Jam layanan', 'Opening hours') ?></h2>
        <p><?= bk_opt('bk_hours') ?></p>
        <p class="bk-muted"><?= bk_t('Kunjungan dicatat dengan memindai kartu anggota di buku tamu.', 'Visits are recorded by scanning your member card at the guest book.') ?></p>
    </div>
    <div class="bk-card bk-maklumat">
        <h2 class="bk-h3"><?= bk_t('Maklumat pelayanan', 'Service pledge') ?></h2>
        <blockquote><?= nl2br(bk_opt('bk_maklumat')) ?></blockquote>
        <?php if ($u = bk_url('bk_link_standar')) : ?><a href="<?= $u ?>"><?= bk_t('Lihat standar pelayanan', 'See service standards') ?> →</a><?php endif; ?>
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
