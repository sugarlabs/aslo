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
 * Portions created by the Initial Developer are Copyright (C) 2008
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Justin Scott <fligtar@mozilla.com> (Original Author)
 *
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

// Prevent running from the web
if (isset($_SERVER['HTTP_HOST'])) {
    exit;
}

// Include class files
require_once('../database.class.php');

$db = new Database();

$_translations = $db->read("SELECT * FROM translations WHERE localized_string LIKE '%&amp;%'");

while ($translation = mysql_fetch_array($_translations)) {
    echo "[Translation {$translation['id']} ({$translation['locale']})]";

    $new = $translation['localized_string'];

    // Many translations ended up like &amp;amp;amp;amp;umlaut;
    while (strpos($new, '&amp;') !== false) {
        $new = str_replace('&amp;', '&', $new);
    }

    $new = html_entity_decode($new);

    // Some translations had HTMl code we don't want to inject
    // <textarea> => &lt;textarea&gt; (we can't tell where the author had entities and where cake screwed stuff up)
    $new = str_replace('<', '&lt;', $new);
    $new = str_replace('>', '&gt;', $new);

    $qry = "UPDATE translations SET localized_string='".mysql_real_escape_string($new)."' WHERE id={$translation['id']} AND locale='{$translation['locale']}' LIMIT 1";

    if ($argv[1] == 'dryrun') {
        echo "Query would be: [{$qry}]";
    }
    else {
        $db->write($qry);
        echo "Query run.";
    }

    echo "\n";
}

?>
