<?php
/**
 * Pemeriksaan asal request admin (CSRF).
 *
 * Cookie sesi admin sudah SameSite=Lax, tetapi Lax tidak mencegah POST dari subdomain
 * *.poltekpin.ac.id (dianggap same-site) dan tetap mengirim cookie pada navigasi GET dari
 * situs lain. Keputusan memakai header yang diisi browser: Sec-Fetch-Site, atau Origin
 * untuk browser lama yang belum mengirim Sec-Fetch-*.
 */

if (!defined('INDEX_AUTH') || INDEX_AUTH != 1) {
    die("can not access this file directly");
}

/**
 * Kembalikan alasan penolakan, atau string kosong bila request berasal dari admin sendiri.
 *
 * @param array $server bentuk $_SERVER
 */
function adminForeignRequestReason(array $server): string
{
    $method = strtoupper($server['REQUEST_METHOD'] ?? 'GET');
    $isWrite = !in_array($method, ['GET', 'HEAD', 'OPTIONS'], true);
    // Halaman modul hanya dimuat lewat AJAX dari admin sendiri, tidak pernah dibuka dari situs lain.
    $isModulePath = strpos($server['SCRIPT_NAME'] ?? '', '/admin/modules/') !== false;
    if (!$isWrite && !$isModulePath) {
        return '';
    }

    $fetchSite = strtolower($server['HTTP_SEC_FETCH_SITE'] ?? '');
    if ($fetchSite !== '') {
        // "none" = alamat diketik/bookmark, "same-origin" = dari admin sendiri.
        return in_array($fetchSite, ['cross-site', 'same-site'], true) ? 'sec-fetch-site=' . $fetchSite : '';
    }

    $origin = $server['HTTP_ORIGIN'] ?? '';
    if (!$isWrite || $origin === '') {
        return '';
    }
    if ($origin === 'null') {
        return 'origin=null';
    }
    $parts = parse_url($origin);
    $originHost = strtolower(($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : ''));
    return $originHost === strtolower($server['HTTP_HOST'] ?? '') ? '' : 'origin=' . $originHost;
}
