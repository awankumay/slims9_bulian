<?php
/**
 * @Created by          : Waris Agung Widodo (ido.alit@gmail.com)
 * @Date                : 2019-01-29 10:43
 * @File name           : _other.php
 */

?>

<div class="result-search pb-5">
    <?php if ($_GET['p'] !== 'show_detail') : ?>
    <section class="bk-searchbar">
      <?php
      // ------------------------------------------------------------------------
      // include search form part (the detail page goes straight to the record)
      // ------------------------------------------------------------------------
      include '_search-form.php'; ?>
    </section>
    <?php endif; ?>

    <section id="bk-main" class="container bk-page<?= $_GET['p'] === 'show_detail' ? ' bk-page--detail' : '' ?>">
      <?php
      if ($_GET['p'] !== 'show_detail') {
        echo '<h2 class="mb-4">' . $page_title . '</h2><hr>';
        if ($_GET['p'] === 'librarian') {
          echo '<div class="flex flex-row flex-wrap">' . $main_content . '</div>';
        } else {
          echo $main_content;
        }
      } else {
        echo $main_content;
      }
      ?>
    </section>
</div>
