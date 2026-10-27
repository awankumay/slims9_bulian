<?php
/**
 * bulian-kampus: kerangka halaman login dan buku tamu
 * (turunan login_template.inc.php tema default, tanpa slide latar).
 */

if (isset($_GET['p']) && $_GET['p'] === 'visitor') {
  $imagesDisk = \SLiMS\Filesystems\Storage::images();
  include_once __DIR__ . "/classic.php";
  include "parts/header.php";
  echo $main_content;
  echo '</body></html>';
} else {
  include "index_template.inc.php";
}
