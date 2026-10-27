<?php
/**
 * Copyright (C) 2007,2008  Arie Nugraha (dicarve@yahoo.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
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

/* Circulation AJAX Process */

// key to authenticate
if (!defined('INDEX_AUTH')) {
    define('INDEX_AUTH', '1');
}

require '../../../sysconfig.inc.php';
// start the session so the staff login can be verified
require SB.'admin/default/session.inc.php';
// IP based access limitation
require LIB.'ip_based_access.inc.php';
do_checkIP('smc');
do_checkIP('smc-circulation');
// This endpoint echoes request data back into a <script> block, so it must
// only run for a logged-in staff session. Without this, an unauthenticated
// cross-site POST could inject script that runs in the SLiMS origin.
require SB.'admin/default/session_check.inc.php';

// quick return
if (isset($_POST['quickReturnID'])) {
    $quick_return_id = trim($_POST['quickReturnID']);
    // Send the id to circulation_action.php as a urlencoded query fragment,
    // and emit it with json_encode (HEX flags) so it cannot break out of the
    // JavaScript string or the surrounding <script> element.
    $add_data = json_encode('quickReturnID=' . rawurlencode($quick_return_id), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    echo '<script type="text/javascript">'."\n";
    echo 'parent.$(\'#circulationLayer\').simbioAJAX(\''.MWB.'circulation/circulation_action.php\', {method: \'post\', addData: '.$add_data.'});'."\n";
    echo 'parent.$(\'#quickReturnID\').val(\'\');'."\n";
    echo 'parent.$(\'#quickReturnID\').focus();'."\n";
    echo '</script>';
    exit();
}
