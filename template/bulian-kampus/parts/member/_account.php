<?php
/**
 * bulian-kampus: tab "Akun saya" di area anggota.
 * Data dari sesi anggota; ubah kata sandi memakai proses bawaan
 * lib/contents/member.inc.php (changePass) dan aturan password_policy_* yang sama.
 */

$bk_token = htmlspecialchars($_SESSION['member_csrf_token'] ?? '', ENT_QUOTES, 'UTF-8');
$bk_strong = !empty($sysconf['password_policy_strong']);
$bk_min = max(1, (int)($sysconf['password_policy_min_length'] ?? 8));
$bk_rules = $bk_strong ? [
  'len' => sprintf(bk_t('Minimal %d karakter', 'At least %d characters'), $bk_min),
  'upper' => bk_t('Ada huruf kapital', 'An uppercase letter'),
  'digit' => bk_t('Ada angka', 'A number'),
  'symbol' => bk_t('Ada simbol, misalnya # atau !', 'A symbol, such as # or !'),
] : [];
$bk_rows = [
  [bk_t('Nama anggota', 'Member name'), $bk_e('m_name'), ''],
  [bk_t('ID anggota', 'Member ID'), $bk_e('mid'), 'bk-mono'],
  [bk_t('Surel', 'E-mail'), $bk_e('m_email') ?: '-', 'bk-wrap-any'],
  [bk_t('Tipe keanggotaan', 'Membership type'), $bk_e('m_member_type') ?: '-', ''],
  [bk_t('Institusi', 'Institution'), $bk_e('m_institution') ?: '-', ''],
  [bk_t('Terdaftar sejak', 'Registered on'), bk_date($_SESSION['m_register_date'] ?? '-'), ''],
];
$bk_left = $bk_status['key'] === 'expired' ? bk_t('sudah berakhir', 'already ended')
  : ($bk_status['days'] === 0 ? bk_t('berakhir hari ini', 'ends today') : $bk_status['days'] . ' ' . bk_t('hari lagi', 'days left'));
?>
<div class="bk-account">
  <section class="bk-card bk-account__data" aria-labelledby="bk-data-h">
    <div class="bk-card__head">
      <h2 id="bk-data-h"><?= bk_t('Data keanggotaan', 'Membership details') ?></h2>
      <span class="bk-state bk-state--<?= $bk_status['key'] ?>"><?= $bk_status['label'] ?></span>
    </div>
    <dl class="bk-facts">
      <?php foreach ($bk_rows as [$k, $v, $cls]) : ?>
      <div><dt><?= $k ?></dt><dd<?= $cls ? ' class="' . $cls . '"' : '' ?>><?= $v ?></dd></div>
      <?php endforeach; ?>
    </dl>
    <div class="bk-validity">
      <div class="bk-validity__row">
        <span><?= bk_t('Masa berlaku', 'Valid until') ?></span>
        <span><strong><?= bk_t('s.d.', '') ?> <?= $bk_status['expire'] ?></strong> <span class="bk-validity__left bk-validity__left--<?= $bk_status['key'] ?>">· <?= $bk_left ?></span></span>
      </div>
      <div class="bk-meter bk-meter--<?= $bk_status['key'] ?>" aria-hidden="true"><span style="width: <?= $bk_status['pct'] ?>%"></span></div>
    </div>
    <p class="bk-note"><?= bk_t('Data ini dikelola petugas perpustakaan. Ada yang keliru? Hubungi meja layanan sirkulasi.', 'These details are managed by the library staff. Something wrong? Ask at the circulation desk.') ?></p>
  </section>

  <?php if (config('auth.sections.member') == 'native') : ?>
  <section class="bk-card bk-account__pass" aria-labelledby="bk-pass-h">
    <div class="bk-card__head bk-card__head--stack">
      <h2 id="bk-pass-h"><?= bk_t('Ubah kata sandi', 'Change password') ?></h2>
      <p><?= bk_t('Dipakai untuk masuk ke area anggota OPAC.', 'Used to sign in to the OPAC member area.') ?></p>
    </div>
    <form id="bkChangePassword" method="post" action="index.php?p=member&amp;sec=my_account" class="bk-form" novalidate
          data-strong="<?= $bk_strong ? '1' : '0' ?>" data-min="<?= $bk_min ?>">
      <input type="hidden" name="__member_csrf" value="<?= $bk_token ?>">
      <div class="bk-field">
        <label for="bk-cur"><?= bk_t('Kata sandi saat ini', 'Current password') ?></label>
        <div class="bk-pw">
          <input id="bk-cur" type="password" name="currPass" autocomplete="current-password" required>
          <button type="button" class="bk-pw__eye" data-bk-eye="bk-cur" aria-label="<?= bk_t('Tampilkan kata sandi', 'Show password') ?>" aria-pressed="false"><i class="fas fa-eye" aria-hidden="true"></i></button>
        </div>
      </div>
      <div class="bk-field">
        <label for="bk-new"><?= bk_t('Kata sandi baru', 'New password') ?></label>
        <div class="bk-pw">
          <input id="bk-new" type="password" name="newPass" autocomplete="new-password" required<?= $bk_rules ? ' aria-describedby="bk-rules"' : '' ?>>
          <button type="button" class="bk-pw__eye" data-bk-eye="bk-new bk-conf" aria-label="<?= bk_t('Tampilkan kata sandi', 'Show password') ?>" aria-pressed="false"><i class="fas fa-eye" aria-hidden="true"></i></button>
        </div>
        <?php if ($bk_rules) : ?>
        <div class="bk-strength" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
        <ul id="bk-rules" class="bk-rules">
          <?php foreach ($bk_rules as $key => $text) : ?><li data-rule="<?= $key ?>"><?= $text ?></li><?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>
      <div class="bk-field">
        <label for="bk-conf"><?= bk_t('Ulangi kata sandi baru', 'Repeat new password') ?></label>
        <div class="bk-pw">
          <input id="bk-conf" type="password" name="newPass2" autocomplete="new-password" required aria-describedby="bk-conf-msg">
        </div>
        <span id="bk-conf-msg" class="bk-field__err" hidden><?= bk_t('Belum sama dengan kata sandi baru.', 'Does not match the new password yet.') ?></span>
      </div>
      <button type="submit" name="changePass" value="1" class="bk-btn bk-btn--primary"><?= bk_t('Simpan kata sandi', 'Save password') ?></button>
    </form>
  </section>
  <?php endif; ?>
</div>
