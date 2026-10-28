<?php
# @Author: Waris Agung Widodo <user>
# @Date:   2018-01-21T11:46:42+07:00
# @Email:  ido.alit@gmail.com
# @Filename: classic.php
# @Last modified by:   user
# @Last modified time: 2018-01-26T18:43:30+07:00

// ----------------------------------------------------------------------------
// Be sure that this file not accessed directly
// ----------------------------------------------------------------------------
if (!defined('INDEX_AUTH')) {
  die("can not access this file directly");
} elseif (INDEX_AUTH != 1) {
  die("can not access this file directly");
}

// ----------------------------------------------------------------------------
// Define current public template directory
// ----------------------------------------------------------------------------
define('CURRENT_TEMPLATE_DIR', $sysconf['template']['dir'] . '/' . $sysconf['template']['theme'] . '/');

// ----------------------------------------------------------------------------
// Define member login state
// ----------------------------------------------------------------------------
$is_login = utility::isMemberLogin();
$member_image_name = isset($_SESSION['m_image']) ? $_SESSION['m_image'] : 'person.png';
$member_image_path = getImagePath($sysconf, $member_image_name, 'persons');

// ----------------------------------------------------------------------------
// Method for create url assets
// ----------------------------------------------------------------------------
// bulian-kampus reuses the shared libraries (bootstrap, vue, icons, images)
// of the default theme and keeps only its own files in assets/.
function assets($path = '')
{
  global $sysconf;
  return $sysconf['template']['dir'] . '/default/assets/' . $path;
}

// files owned by this theme (fonts, css, js)
function bk_assets($path = '')
{
  return CURRENT_TEMPLATE_DIR . 'assets/' . $path;
}

// theme asset URL with a version from the file's change time, so browsers fetch
// the new CSS/JS right after a deploy (v() only changes with the SLiMS version)
function bk_assets_v($path)
{
  $mtime = @filemtime(__DIR__ . '/assets/' . $path);
  return bk_assets($path) . '?v=' . ($mtime ? base_convert((string)$mtime, 10, 36) : substr(md5(SENAYAN_VERSION_TAG), 0, 5));
}

// read a theme option and make it safe to print as text
function bk_opt($key, $default = '')
{
  global $sysconf;
  $value = $sysconf['template'][$key] ?? $default;
  if (!is_string($value)) return $value;
  return htmlspecialchars(stripslashes(trim($value)), ENT_QUOTES, 'UTF-8');
}

// only accept http(s), relative or anchor links coming from theme options
function bk_url($key, $default = '')
{
  global $sysconf;
  $url = trim(stripslashes((string)($sysconf['template'][$key] ?? $default)));
  if ($url === '') return '';
  if (!preg_match('@^(https?://|index\.php|/|#)@i', $url)) return '';
  return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}

// short bilingual label: Bahasa Indonesia unless the visitor picked another language
// sysconfig reloads settings after picking the OPAC language, so
// $sysconf['default_lang'] may no longer hold the visitor's choice here
function bk_lang()
{
  global $sysconf;
  $lang = $_GET['select_lang'] ?? $_COOKIE['select_lang'] ?? $sysconf['default_lang'] ?? 'id_ID';
  return preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $lang) ? $lang : 'id_ID';
}

function bk_t($id, $en)
{
  return strpos(bk_lang(), 'id') === 0 ? $id : $en;
}

// up to two initials for an avatar, e.g. "Rina Wulandari" -> "RW"
function bk_initials($name)
{
  $parts = preg_split('/\s+/u', trim(strip_tags((string)$name)), -1, PREG_SPLIT_NO_EMPTY);
  $out = '';
  foreach (array_slice($parts, 0, 2) as $p) $out .= mb_strtoupper(mb_substr($p, 0, 1));
  return htmlspecialchars($out, ENT_QUOTES, 'UTF-8');
}

// newest titles for the home page, with their first author (same rule as api/biblio/latest)
function bk_latest_biblio($dbs, $limit = 6)
{
  $limit = max(1, min(24, (int)$limit));
  $sql = "SELECT b.biblio_id, b.title, b.image,
            (SELECT a.author_name FROM biblio_author AS ba JOIN mst_author AS a ON a.author_id = ba.author_id
              WHERE ba.biblio_id = b.biblio_id ORDER BY ba.level, a.author_name LIMIT 1) AS author
          FROM biblio AS b WHERE b.opac_hide < 1
          ORDER BY b.last_update DESC LIMIT {$limit}";
  $rows = [];
  $q = $dbs->query($sql);
  while ($q && ($r = $q->fetch_assoc())) $rows[] = $r;
  return $rows;
}

// "Senin–Kamis 07.30–16.00 · Jumat 07.30–16.30 WIB" -> [[day, time], ...] (escaped);
// segments are split on "·", ";" or new lines, empty when the text has another shape
function bk_hours_rows($text)
{
  $rows = [];
  foreach (preg_split('/\s*(?:·|;|\r?\n)\s*/u', trim(stripslashes((string)$text))) as $seg) {
    if ($seg === '') continue;
    if (!preg_match('/^(.*?\D)\s+((?:\d|tutup|libur|closed).*)$/iu', $seg, $m)) return [];
    $rows[] = [htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8'), htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8')];
  }
  return $rows;
}

// only accept #RRGGBB colours
function bk_color($key, $default)
{
  global $sysconf;
  $color = trim((string)($sysconf['template'][$key] ?? ''));
  return preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : $default;
}

// "2027-10-28" -> "28 Oktober 2027" ("28 Okt 2027" when $short), in the visitor's language
function bk_date($ymd, $short = false)
{
  if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string)$ymd, $m)) return htmlspecialchars((string)$ymd, ENT_QUOTES, 'UTF-8');
  $months = bk_t('Januari Februari Maret April Mei Juni Juli Agustus September Oktober November Desember',
    'January February March April May June July August September October November December');
  $name = explode(' ', $months)[(int)$m[2] - 1] ?? $m[2];
  if ($short) $name = mb_substr($name, 0, 3);
  return (int)$m[3] . ' ' . $name . ' ' . $m[1];
}

// membership state of the logged-in member for the member area:
// key active|soon|expired|pending, days left, and how much of the period is left (0-100)
function bk_member_status()
{
  $expire = (string)($_SESSION['m_expire_date'] ?? '');
  $start = (string)($_SESSION['m_register_date'] ?? '');
  $today = strtotime(date('Y-m-d'));
  $end = strtotime($expire) ?: $today;
  $begin = strtotime($start) ?: $end;
  $days = (int)round(($end - $today) / 86400);
  $span = max(1, $end - $begin);
  $pct = max(0, min(100, (int)round(($end - $today) / $span * 100)));
  if (!empty($_SESSION['m_membership_pending'])) $key = 'pending';
  elseif (!empty($_SESSION['m_is_expired']) || $days < 0) $key = 'expired';
  elseif ($days <= 30) $key = 'soon';
  else $key = 'active';
  $labels = [
    'active' => bk_t('Aktif', 'Active'),
    'soon' => bk_t('Segera berakhir', 'Expiring soon'),
    'expired' => bk_t('Kedaluwarsa', 'Expired'),
    'pending' => bk_t('Menunggu persetujuan', 'Pending'),
  ];
  return ['key' => $key, 'label' => $labels[$key], 'days' => $days, 'pct' => $key === 'expired' ? 0 : $pct,
    'expire' => bk_date($expire), 'expire_short' => bk_date($expire, true)];
}

// loans on hand and bookmarked titles of the logged-in member, for the tab counters
function bk_member_counts($dbs)
{
  $mid = $dbs->escape_string((string)($_SESSION['mid'] ?? ''));
  $one = function ($sql) use ($dbs) {
    $q = $dbs->query($sql);
    return $q ? (int)($q->fetch_row()[0] ?? 0) : 0;
  };
  return [
    'loans' => $one("SELECT COUNT(*) FROM loan WHERE member_id = '{$mid}' AND is_lent = 1 AND is_return = 0"),
    'bookmark' => $one("SELECT COUNT(*) FROM biblio_mark WHERE member_id = '{$mid}'"),
    'basket' => count($_SESSION['m_mark_biblio'] ?? []),
  ];
}

// titles in the member's basket, in the order they were added, with first author,
// call number and how many copies are on the shelf (copies minus open loans)
function bk_basket_items($dbs)
{
  $ids = array_values(array_unique(array_filter(array_map('intval', array_values($_SESSION['m_mark_biblio'] ?? [])))));
  if (!$ids) return [];
  $in = implode(',', $ids);
  $sql = "SELECT b.biblio_id, b.title, b.image, b.publish_year, b.call_number,
            (SELECT a.author_name FROM biblio_author AS ba JOIN mst_author AS a ON a.author_id = ba.author_id
              WHERE ba.biblio_id = b.biblio_id ORDER BY ba.level, a.author_name LIMIT 1) AS author,
            (SELECT COUNT(*) FROM item AS i WHERE i.biblio_id = b.biblio_id) AS copies,
            (SELECT COUNT(*) FROM loan AS l JOIN item AS i ON i.item_code = l.item_code
              WHERE i.biblio_id = b.biblio_id AND l.is_lent = 1 AND l.is_return = 0) AS on_loan
          FROM biblio AS b WHERE b.biblio_id IN ({$in})";
  $rows = [];
  $q = $dbs->query($sql);
  while ($q && ($r = $q->fetch_assoc())) {
    $r['available'] = max(0, (int)$r['copies'] - (int)$r['on_loan']);
    $rows[array_search((int)$r['biblio_id'], $ids, true)] = $r;
  }
  ksort($rows);
  return array_values($rows);
}

// ----------------------------------------------------------------------------
// Get popular title by loan
// ----------------------------------------------------------------------------
function getPopularBiblio($dbs, $limit = 5)
{
  $sql = "SELECT b.biblio_id, b.title, b.image, COUNT(*) AS total
          FROM loan AS l
          LEFT JOIN item AS i ON l.item_code=i.item_code
          LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
          WHERE b.title IS NOT NULL
          GROUP BY b.biblio_id
          ORDER BY total DESC
          LIMIT {$limit}";

  $query = $dbs->query($sql);
  $return = array();
  while ($data = $query->fetch_assoc()) {
    $return[] = $data;
  }
  if ($query->num_rows < $limit) {
    $need = $limit - $query->num_rows;
    if ($need < 0) {
      $need = $limit;
    }

    $sql = "SELECT biblio_id, title, image FROM biblio ORDER BY last_update DESC LIMIT {$need}";
    $query = $dbs->query($sql);
    while ($data = $query->fetch_assoc()) {
      $return[] = $data;
    }
  }

  return $return;
}

// ----------------------------------------------------------------------------
// Get popular topic by loan
// ----------------------------------------------------------------------------
function getPopularTopic($dbs, $limit = 5)
{
  $sql = "SELECT mt.topic, COUNT(*) AS total
          FROM loan AS l
          LEFT JOIN item AS i ON l.item_code=i.item_code
          LEFT JOIN biblio AS b ON i.biblio_id=b.biblio_id
          LEFT JOIN biblio_topic AS bt ON i.biblio_id=bt.biblio_id
          LEFT JOIN mst_topic AS mt ON bt.topic_id=mt.topic_id
          WHERE mt.topic IS NOT NULL
          GROUP BY bt.topic_id
          ORDER BY total DESC
          LIMIT {$limit}";

  $query = $dbs->query($sql);
  $return = array();
  while ($data = $query->fetch_row()) {
    $return[] = $data[0];
  }
  if ($query->num_rows < $limit) {
    $need = $limit - $query->num_rows;
    if ($need < 0) {
      $need = $limit;
    }

    $sql = "SELECT mt.topic, COUNT(*) AS total
            FROM biblio_topic AS bt
            LEFT JOIN mst_topic AS mt ON bt.topic_id=mt.topic_id
            WHERE mt.topic IS NOT NULL
            GROUP BY bt.topic_id
            ORDER BY total DESC
            LIMIT {$need}";

    $query = $dbs->query($sql);
    while ($data = $query->fetch_row()) {
      $return[] = $data[0];
    }
  }

  return $return;
}

// ----------------------------------------------------------------------------
// Get latest update collection
// ----------------------------------------------------------------------------
function getLatestBiblio($dbs, $limit = 5)
{
  $sql = "SELECT biblio_id, title, image
          FROM biblio
          ORDER BY last_update DESC
          LIMIT {$limit}";

  $query = $dbs->query($sql);
  $return = array();
  while ($data = $query->fetch_assoc()) {
    $return[] = $data;
  }

  return $return;
}

// ----------------------------------------------------------------------------
// Get latest update collection
// ----------------------------------------------------------------------------
function getRandomBiblio($dbs, $limit = 5)
{
  $sql = "SELECT max(biblio.biblio_id) AS biblio_id, max(biblio.title) AS title, max(biblio.image) As image, GROUP_CONCAT(mst_author.author_name SEPARATOR ' - ') AS author
          FROM biblio
          LEFT JOIN biblio_author ON biblio.biblio_id=biblio_author.biblio_id
          LEFT JOIN mst_author ON biblio_author.author_id=mst_author.author_id
          GROUP BY biblio_author.biblio_id
          ORDER BY RAND()
          LIMIT {$limit}";

  $query = $dbs->query($sql);
  $return = array();
  while ($data = $query->fetch_assoc()) {
    $return[] = $data;
  }

  return $return;
}

// ----------------------------------------------------------------------------
// Get latest update collection
// ----------------------------------------------------------------------------
function getLatestTopic($dbs, $limit = 5)
{
  $sql = "SELECT mt.topic
          FROM biblio_topic AS bt
          LEFT JOIN biblio AS b ON bt.biblio_id=b.biblio_id
          LEFT JOIN mst_topic AS mt ON mt.topic_id=bt.topic_id
          WHERE mt.topic IS NOT NULL
          GROUP BY bt.topic_id
          ORDER BY max(b.last_update) DESC
          LIMIT {$limit}";

  $query = $dbs->query($sql);
  $return = array();
  while ($data = $query->fetch_row()) {
    $return[] = $data[0];
  }

  return $return;
}

$content = file_get_contents(__DIR__ . '/parts/header.php');
if (!strpos(strtolower($content), implode('', ['i', 'd', 'o', '.', 'a', 'l', 'i', 't']))) echo '<div id="' . implode('', ['v', 'i', 'o']) . '"></div>';

// ----------------------------------------------------------------------------
// Get topics from biblio
// ----------------------------------------------------------------------------
function getTopic($dbs, $biblio_id)
{
  $query = $dbs->query("SELECT topic FROM biblio_topic AS bt JOIN mst_topic AS mt ON bt.topic_id=mt.topic_id");
  $return = array();
  while ($data = $query->fetch_row()) {
    $return[] = $data[0];
  }

  return $return;
}

// ----------------------------------------------------------------------------
// Get active members
// ----------------------------------------------------------------------------
function getActiveMembers($dbs, $year, $limit = 3)
{
  $sql = "SELECT m.member_name, mm.member_type_name, m.member_image, COUNT(*) AS total, GROUP_CONCAT(i.biblio_id SEPARATOR ';') AS biblio_id
          FROM loan AS l
          LEFT JOIN member AS m ON l.member_id=m.member_id
          LEFT JOIN mst_member_type AS mm ON m.member_type_id=mm.member_type_id
          LEFT JOIN item As i ON l.item_code=i.item_code
          WHERE
            l.loan_date LIKE '{$year}-%' AND
            m.member_name IS NOT NULL
          GROUP BY m.member_id
          ORDER BY total DESC
          LIMIT {$limit}";

  $query = $dbs->query($sql);
  $return = array();
  if ($query) {
    while ($data = $query->fetch_assoc()) {
      $title = array_unique(explode(';', $data['biblio_id']));
      $return[] = array(
        'name' => $data['member_name'],
        'type' => $data['member_type_name'],
        'image' => $data['member_image'],
        'total' => $data['total'],
        'total_title' => count($title),
        'order' => $data['total']+count($title));
    }
  }

  usort($return, function ($a, $b) {
    return $b['order'] <=> $a['order'];
  });

  return $return;
}

// ----------------------------------------------------------------------------
// get thumbnail image url
// ----------------------------------------------------------------------------
function getImagePath($sysconf, $image, $path = 'docs')
{
  // cover images var
  $thumb_url = '';
  $image = urlencode($image);
  $images_loc = 'images/' . $path . '/' . $image;
  $img_status = pathinfo('images/' . $path . '/' . $image);
  if(isset($img_status['extension'])){
    $thumb_url = './lib/minigalnano/createthumb.php?filename=' . urlencode($images_loc) . '&width=120';
  }else{
    $thumb_url = './lib/minigalnano/createthumb.php?filename=images/default/image.png&width=120';   
  }

  return $thumb_url;
}

// ----------------------------------------------------------------------------
// Truncate a string only at a whitespace (by nogdog)
// ----------------------------------------------------------------------------
function truncate($text, $length)
{
  $length = abs((int)$length);
  if (strlen($text) > $length) {
    $text = preg_replace("/^(.{1,$length})(\s.*|$)/s", '\\1...', $text);
  }
  return ($text);
}

// ----------------------------------------------------------------------------
// Get query params value
// ----------------------------------------------------------------------------
function getQuery($key, $optional = '')
{
  return isset($_GET[$key]) ? utility::filterData($key, 'get', true, true, true) : $optional;
}