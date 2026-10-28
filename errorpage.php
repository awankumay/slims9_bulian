<?php
/**
 * Halaman error untuk web server.
 *
 * Apache : ErrorDocument 404 /errorpage.php   (lihat .htaccess dan docker/apache)
 * Nginx  : error_page 404 /errorpage.php;     (lihat docs/deployment/nginx-slims.conf)
 *
 * Kode diambil dari REDIRECT_STATUS (Apache) atau parameter ?code=. Tidak
 * memuat sysconfig/database supaya tetap tampil saat aplikasi bermasalah.
 */

require __DIR__ . '/lib/errorpage.inc.php';

$allowed = [400, 401, 403, 404, 405, 408, 410, 413, 414, 429, 500, 502, 503, 504];

$code = (int)($_SERVER['REDIRECT_STATUS'] ?? 0);
if (!in_array($code, $allowed, true)) $code = (int)($_GET['code'] ?? 0);
// dibuka langsung tanpa kode yang dikenal: anggap 404
if (!in_array($code, $allowed, true)) $code = 404;

if (!headers_sent()) {
    http_response_code($code);
    header('Cache-Control: no-store, max-age=0');
    header('X-Robots-Tag: noindex, nofollow');
}

// Klien API dan permintaan AJAX mendapat JSON, bukan HTML
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
$path = slimsErrorPageRequestPath();
$wantsJson = (stripos($accept, 'application/json') !== false && stripos($accept, 'text/html') === false)
    || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
    || preg_match('@/api/@', $path);

if ($wantsJson) {
    [, , $english] = slimsErrorPageInfo($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => false, 'code' => $code, 'message' => $english]);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
echo slimsErrorPage($code);
