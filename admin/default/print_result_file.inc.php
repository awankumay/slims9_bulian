<?php
/**
 * Helper for the admin "print result" pages (member cards, catalog cards,
 * labels, barcodes and the statistic reports).
 *
 * These pages are written as plain .html files into the public files/ tree and
 * then opened in the staff member's browser. SLiMS used to name them after the
 * logged-in username (for example member_card_gen_print_result_admin.html), so
 * the URL was trivially guessable and anyone could fetch another library's
 * member cards or reports - with member names, addresses, phone numbers and
 * photos - without logging in at all.
 *
 * This helper gives each generated page an unguessable, per-user filename and
 * removes the same user's previous page before writing a new one, so an old
 * result does not linger in the public folder. Pages of the same kind older
 * than an hour are removed too, including ones named in the old guessable way.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

if (!defined('INDEX_AUTH')) {
    die('can not access this file directly');
}

/**
 * Build an unguessable filename for a print-result page and delete the same
 * user's previous result files in that directory.
 *
 * @param string $dir    filesystem directory the page is written to (with trailing separator)
 * @param string $prefix stable prefix identifying the kind of page
 * @param string $user   current staff username (from $_SESSION['uname'])
 * @return string filename (no directory) to use for both the file and its URL
 */
function slimsPrintResultName($dir, $prefix, $user)
{
    $user = strtolower(preg_replace('/[^a-zA-Z0-9_]+/', '_', (string) $user));
    $base = $prefix . '_' . $user . '_';

    foreach (glob($dir . $base . '*.html') ?: [] as $old) {
        @unlink($old);
    }
    foreach (glob($dir . $prefix . '_*.html') ?: [] as $old) {
        if (filemtime($old) < time() - 3600) @unlink($old);
    }

    try {
        $token = bin2hex(random_bytes(16));
    } catch (\Exception $e) {
        $token = sha1(uniqid((string) mt_rand(), true));
    }

    return $base . $token . '.html';
}
