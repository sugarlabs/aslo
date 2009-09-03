<?php
/* ***** BEGIN LICENSE BLOCK *****
 * Version: MPL 1.1/GPL 2.0/LGPL 2.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is addons.mozilla.org site.
 *
 * The Initial Developer of the Original Code is
 * The Mozilla Foundation.
 * Portions created by the Initial Developer are Copyright (C) 2009
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   RJ Walsh <rwalsh@mozilla.com>
 *
 * Alternatively, the contents of this file may be used under the terms of
 * either the GNU General Public License Version 2 or later (the "GPL"), or
 * the GNU Lesser General Public License Version 2.1 or later (the "LGPL"),
 * in which case the provisions of the GPL or the LGPL are applicable instead
 * of those above. If you wish to allow use of your version of this file only
 * under the terms of either the GPL or the LGPL, and not to allow others to
 * use your version of this file under the terms of the MPL, indicate your
 * decision by deleting the provisions above and replace them with the notice
 * and other provisions required by the GPL or the LGPL. If you do not delete
 * the provisions above, a recipient may use your version of this file under
 * the terms of any one of the MPL, the GPL or the LGPL.
 *
 * ***** END LICENSE BLOCK ***** */



/**
 * CONFIG
 *
 * Require site config.
 */
require_once(dirname(__FILE__).'/../../config/config.php');
require_once(dirname(__FILE__).'/../../config/constants.php');

// Our HTTP Lib
class Object {/* Go fuck yourself PHP. */ }
require_once(dirname(__FILE__).'/../../controllers/components/httplib.php');
$http =& new HttplibComponent();

$data = array('cmd' => '_notify-validate');

foreach ($_POST as $key => $value) {
    $value = stripslashes($value);
    $data[$key] = $value;
}

// post back to PayPal system to validate
list($res, $info) = $http->post(PAYPAL_CGI_URL, $data);

// Grab paypal data for processing
$item_number = $data['item_number'];
$payment_status = $data['payment_status'];
$payment_amount = $data['mc_gross'];
$txn_id = $data['txn_id'];

if (strcmp ($res, "VERIFIED") == 0) {

    // Try to connect to the DB
    $dbh = @mysql_connect(DB_HOST.':'.DB_PORT, DB_USER, DB_PASS);
    if (!is_resource($dbh)) die('Could not connect to DB');
    if (!@mysql_select_db(DB_NAME, $dbh)) die('Could not select DB');

    // Make sure the payment is valid and yet unprocessed
    if ($payment_status != 'Completed') die('Payment not completed');

    $exists = "SELECT COUNT(*) AS cnt FROM `stats_contributions` WHERE `transaction_id` = '{$txn_id}'";
    $resource = @mysql_query($exists);
    if (!$resource) die('Could not determine if contribution already logged');

    $result = mysql_fetch_array($resource);
    if ($result[0]['cnt'] !== '0') die('Transaction already processed');

    // Build the query - item_number is the uuid we created
    $post_data = mysql_real_escape_string(serialize($_POST));
    $query = "UPDATE `stats_contributions` " .
        "SET `transaction_id` = '{$txn_id}', " .
        "`amount` = '{$payment_amount}', " .
        "`uuid` = '', " .
        "`post_data` = '{$post_data}' " .
        "WHERE `uuid` = '{$item_number}'";

    // Log the contribution
    if (!@mysql_query($query)) die('Query failed');

    // Success!
    echo 'Success!';

 } else if (strcmp ($res, "INVALID") == 0) {
    // log for manual investigation
    die('Invalid Confirmation');
 }

?>
