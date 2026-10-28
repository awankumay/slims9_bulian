<?php
/**
 * @Created by          : Waris Agung Widodo (ido.alit@gmail.com)
 * @Date                : 2019-01-30 20:58
 * @File name           : _member.php
 */

?>

<?php if ($is_login) : ?>

    <div class="member-area">

        <div id="bk-main" class="bk-wrap bk-page bk-member">
          <?php
          // ringkasan keanggotaan dari sesi anggota (data yang sama dengan sidebar bawaan)
          $bk_e = fn ($k) => htmlspecialchars((string)($_SESSION[$k] ?? ''), ENT_QUOTES, 'UTF-8') ?: '-';
          $bk_status = !empty($_SESSION['m_membership_pending']) ? bk_t('Menunggu persetujuan', 'Pending')
            : (!empty($_SESSION['m_is_expired']) ? bk_t('Kedaluwarsa', 'Expired') : '');
          ?>
          <section class="bk-profile" aria-label="<?= bk_t('Data keanggotaan', 'Membership details') ?>">
            <span class="bk-profile__avatar" aria-hidden="true"><?= bk_initials($_SESSION['m_name'] ?? '') ?></span>
            <dl class="bk-profile__grid">
              <div><dt><?= __('Member Name') ?></dt><dd><?= $bk_e('m_name') ?></dd></div>
              <div><dt><?= __('Member ID') ?></dt><dd class="bk-mono"><?= $bk_e('mid') ?></dd></div>
              <div><dt><?= __('Member Type') ?></dt><dd><?= $bk_e('m_member_type') ?></dd></div>
              <div><dt><?= __('Register Date') ?></dt><dd><?= $bk_e('m_register_date') ?></dd></div>
              <div><dt><?= __('Expiry Date') ?></dt><dd><?= $bk_e('m_expire_date') ?><?php if ($bk_status) : ?> <span class="bk-badge bk-badge--warn"><?= $bk_status ?></span><?php endif; ?></dd></div>
              <div><dt><?= __('Institution') ?></dt><dd><?= $bk_e('m_institution') ?></dd></div>
            </dl>
          </section>
          <?php echo $main_content; ?>
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
