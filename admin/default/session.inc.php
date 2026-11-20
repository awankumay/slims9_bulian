<?php
/**
 *
 * SESSION Settings
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 * Taken and modified from phpMyAdmin's Session library
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

use SLiMS\Plugins;
use SLiMS\Session\Factory as SessionFactory;
use SLiMS\Session\Driver\Files;

// be sure that this file not accessed directly
if (INDEX_AUTH != 1) {
    die("can not access this file directly");
}

require_once __DIR__ . '/origin_check.inc.php';
// Mode awal hanya mencatat ke error_log (docker logs). Ubah ke true setelah log bersih.
$adminOriginCheckEnforce = false;
$adminForeignReason = adminForeignRequestReason($_SERVER);
if ($adminForeignReason !== '') {
    error_log(sprintf('[admin-origin-check] %s %s %s (%s) referer=%s',
        $adminOriginCheckEnforce ? 'BLOCK' : 'REPORT',
        $_SERVER['REQUEST_METHOD'] ?? '-',
        strtok($_SERVER['REQUEST_URI'] ?? '-', '?'),
        $adminForeignReason,
        parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST) ?: '-'
    ));
    if ($adminOriginCheckEnforce) {
        http_response_code(403);
        exit('Request ditolak: asal request tidak sah.');
    }
}
unset($adminForeignReason, $adminOriginCheckEnforce);

// Cleanup SQL Injection and Common XSS
$sanitizer->cleanUp(
    filter: [false, true, true] /* escape_sql, trim, strip_tag */, 
    exception: ['contentDesc','comment','classic_footer_about_us','classic_map_desc']
);

// use session factory to handle session based on default SLiMS or user handler
SessionFactory::use(config('customSession', Files::class))->start('admin');

if (!ob_get_level()) ob_start();

// hooking after session started
Plugins::run(Plugins::ADMIN_SESSION_AFTER_START);