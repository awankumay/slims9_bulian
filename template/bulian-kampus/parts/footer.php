<?php
// bulian-kampus: footer turunan tema default SLiMS (Waris Agung Widodo)
?>


<footer class="bk-footer">
    <div class="bk-wrap bk-footer__grid">
        <div>
            <p class="bk-footer__name"><?= $sysconf['library_name'] ?></p>
            <p><?= bk_opt('bk_parent_name') ?><br><?= bk_opt('bk_ministry_name') ?></p>
            <p class="bk-footer__addr"><?= bk_opt('bk_address') ?></p>
        </div>
        <nav aria-label="<?= bk_t('Tautan layanan publik', 'Public service links') ?>">
            <p class="bk-footer__h"><?= bk_t('Layanan publik', 'Public service') ?></p>
            <ul>
                <?php
                $bk_footer_links = [
                  [bk_t('Standar pelayanan', 'Service standards'), bk_url('bk_link_standar')],
                  [bk_t('Survei kepuasan', 'Satisfaction survey'), bk_url('bk_link_skm')],
                  ['SP4N-LAPOR!', bk_url('bk_link_lapor')],
                  [bk_t('PPID / Informasi publik', 'Public information (PPID)'), bk_url('bk_link_ppid')],
                  [bk_t('Kebijakan privasi', 'Privacy policy'), bk_url('bk_link_privacy')],
                ];
                foreach ($bk_footer_links as $l) {
                  if ($l[1] === '') continue;
                  echo '<li><a href="' . $l[1] . '"' . (preg_match('@^https?://@', $l[1]) ? ' target="_blank" rel="noopener"' : '') . '>' . $l[0] . '</a></li>';
                }
                ?>
            </ul>
        </nav>
        <nav aria-label="<?= bk_t('Tautan perpustakaan', 'Library links') ?>">
            <p class="bk-footer__h"><?= bk_t('Perpustakaan', 'Library') ?></p>
            <ul>
                <li><a href="index.php?p=libinfo"><?= __('Information') ?></a></li>
                <li><a href="index.php#bk-quick-title"><?= __('Services') ?></a></li>
                <li><a href="index.php?p=librarian"><?= __('Librarian') ?></a></li>
                <li><a href="index.php?p=member"><?= __('Member Area') ?></a></li>
                <li><a href="https://onesearch.id" target="_blank" rel="noopener">Indonesia OneSearch</a></li>
            </ul>
        </nav>
    </div>
    <div class="bk-wrap bk-footer__bottom">
        <span>&copy; <?= date('Y') ?> <?= $sysconf['library_name'] ?> · <?= bk_opt('bk_parent_name') ?></span>
        <span><?= __('Powered by ') ?><a href="https://slims.web.id" target="_blank" rel="noopener">SLiMS</a></span>
    </div>
</footer>

<?php if ($sysconf['chat_system']['enabled'] && $sysconf['chat_system']['opac']) : ?>
    <div id="show-pchat2" style="position: fixed; bottom: 16px; right: 16px" class="shadow rounded">
        <button title="Chat" class="btn btn-primary"><i class="fas fa-comments mr-2"></i><?= __('Chat'); ?></button>
    </div>
<?php endif; ?>

<?php
// Chat Engine
include LIB . "contents/chat.php"; ?>

<!-- // Load modal -->
<?php include "_modal_topic.php"; ?>
<?php include "_modal_advanced.php"; ?>
<?php include "_modal_social_media.php"; ?>

<!-- // Load highlight -->
<script src="<?= JWB; ?>highlight.js"></script>
<?php if(isset($engine) && $searchableInJsArray = $this->generateKeywords($engine->searchable_fields)) : ?>
<script>
  $('.card-body > *').highlight(<?= $searchableInJsArray ?>);
</script>
<?php endif; ?>

<!-- // load our vue app.js -->
<script src="<?php echo assets(v('js/app.js')); ?>"></script>
<script src="<?php echo assets(v('js/app_jquery.js')); ?>"></script>
<script src="<?php echo bk_assets_v('js/bulian-kampus.js'); ?>"></script>
<?php if ($sysconf['chat_system']['enabled'] && $sysconf['chat_system']['opac']) : ?>
    <script>
        $('#show-pchat').click(() => {
            $('.s-chat').hide()
            $('#show-pchat2').show()
        })
        $('#show-pchat2').click(() => {
            $('.s-chat').show(300, () => {
                $('#show-pchat2').hide()
            })
        })
    </script>
<?php endif; ?>
</body>
</html>
