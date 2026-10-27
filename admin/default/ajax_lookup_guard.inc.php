<?php
/**
 * Table and column guard for the admin AJAX lookup handlers
 * (AJAX_lookup_handler.php, AJAX_vocabolary_control.php, AJAX_check_id.php).
 *
 * These handlers build SQL from table and column names sent by the browser,
 * so any logged-in staff account could otherwise read any table (for example
 * password hashes in `user`). Only identifiers are accepted, and only tables
 * that the lookup forms actually use. Plugins can allow more tables with
 * $sysconf['ajax_lookup_allowed_tables'] = ['my_table'];
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
 * @param string $table  table name as received from the client
 * @param array  $fields column names as received from the client
 * @return bool true when the table is allowed and every name is a plain identifier
 */
function ajaxLookupIsAllowed(string $table, array $fields): bool
{
    $identifier = '/^[a-zA-Z0-9_]+$/';
    if (!preg_match($identifier, $table)) return false;

    $allowed = array_merge(['biblio', 'item', 'member'], (array) config('ajax_lookup_allowed_tables', []));
    if (!in_array($table, $allowed, true) && strpos($table, 'mst_') !== 0) return false;

    if (empty($fields)) return false;
    foreach ($fields as $field) {
        if (!preg_match($identifier, trim($field))) return false;
    }
    return true;
}
