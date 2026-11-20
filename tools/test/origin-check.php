<?php
// Uji adminForeignRequestReason() (admin/default/origin_check.inc.php).
// Jalankan: php -d zend.assertions=1 -d assert.exception=1 tools/test/origin-check.php
define('INDEX_AUTH', '1');
require __DIR__ . '/../../admin/default/origin_check.inc.php';

$host = 'library.poltekpin.ac.id';
$mod = '/admin/modules/membership/index.php';
$r = fn(array $s) => adminForeignRequestReason($s + ['HTTP_HOST' => $host]);

// Diizinkan
assert($r(['REQUEST_METHOD' => 'POST', 'SCRIPT_NAME' => $mod, 'HTTP_SEC_FETCH_SITE' => 'same-origin']) === '');
assert($r(['REQUEST_METHOD' => 'GET', 'SCRIPT_NAME' => '/admin/index.php', 'HTTP_SEC_FETCH_SITE' => 'cross-site']) === '', 'entry admin boleh dari tautan luar');
assert($r(['REQUEST_METHOD' => 'GET', 'SCRIPT_NAME' => $mod, 'HTTP_SEC_FETCH_SITE' => 'none']) === '', 'bookmark/ketik alamat');
assert($r(['REQUEST_METHOD' => 'POST', 'SCRIPT_NAME' => '/index.php', 'HTTP_ORIGIN' => 'https://' . $host]) === '', 'browser lama, origin sama');
assert($r(['REQUEST_METHOD' => 'POST', 'SCRIPT_NAME' => $mod]) === '', 'tanpa header sama sekali (curl/browser sangat lama)');
assert($r(['REQUEST_METHOD' => 'POST', 'SCRIPT_NAME' => $mod, 'HTTP_HOST' => '127.0.0.1:8881', 'HTTP_ORIGIN' => 'http://127.0.0.1:8881']) === '', 'port uji lokal');
assert($r(['REQUEST_METHOD' => 'GET', 'SCRIPT_NAME' => $mod, 'HTTP_ORIGIN' => 'https://evil.example']) === '', 'GET tanpa Sec-Fetch tidak bisa dinilai');

// Ditolak
assert($r(['REQUEST_METHOD' => 'POST', 'SCRIPT_NAME' => $mod, 'HTTP_SEC_FETCH_SITE' => 'same-site']) === 'sec-fetch-site=same-site', 'subdomain *.poltekpin.ac.id');
assert($r(['REQUEST_METHOD' => 'POST', 'SCRIPT_NAME' => '/admin/AJAX_check_id.php', 'HTTP_SEC_FETCH_SITE' => 'cross-site']) === 'sec-fetch-site=cross-site');
assert($r(['REQUEST_METHOD' => 'GET', 'SCRIPT_NAME' => '/admin/modules/system/image_maintenance.php', 'HTTP_SEC_FETCH_SITE' => 'cross-site']) === 'sec-fetch-site=cross-site', 'GET modul dari situs lain');
assert($r(['REQUEST_METHOD' => 'post', 'SCRIPT_NAME' => $mod, 'HTTP_ORIGIN' => 'https://repository.poltekpin.ac.id']) === 'origin=repository.poltekpin.ac.id', 'browser lama dari subdomain');
assert($r(['REQUEST_METHOD' => 'POST', 'SCRIPT_NAME' => $mod, 'HTTP_ORIGIN' => 'null']) === 'origin=null');
assert($r(['REQUEST_METHOD' => 'POST', 'SCRIPT_NAME' => $mod, 'HTTP_ORIGIN' => 'https://library.poltekpin.ac.id.evil.example']) !== '', 'suffix domain palsu');

// Kontrol: assert benar-benar aktif
try { assert(false); echo "ASSERT TIDAK AKTIF\n"; exit(1); } catch (AssertionError $e) {}
echo "origin_check: semua assert lolos\n";
