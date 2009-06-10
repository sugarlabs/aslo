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
 * The Initial Developer of the Original Code is The Mozilla Foundation.
 *
 * Portions created by the Initial Developer are Copyright (C) 2006
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *      Wil Clouser <wclouser@mozilla.com> (Original Author)
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

require_once ROOT.'/../bin/parse_logs/log_parser.class.php';

class LogParserTest extends UnitTestCase {
	
    function testParseLine() {
        $_count = 0;
        $_data = $this->_getTestData();

        foreach (explode("\n", $_data) as $line) {

            $lineDetails = log_parser::parseLine($line);

            if (!is_array($lineDetails))
                continue;

            // Versioncheck.php example
            if($lineDetails['ip'] == '10.28.10.131') {
                $_count++;

                $this->assertEqual($lineDetails['type'], 'updatepings');

                $this->assertEqual($lineDetails['addon']['appOS'], 'WINNT');
            }

            // /$lang/firefox/downloads/ example
            if($lineDetails['ip'] == '10.56.10.195') {
                $_count++;

                $this->assertEqual($lineDetails['type'], 'downloads');

                $this->assertEqual($lineDetails['fileid'], 1);
            }

            // /$lang/downloads/file/ example
            if($lineDetails['ip'] == '10.64.10.123') {
                $_count++;

                $this->assertEqual($lineDetails['type'], 'downloads');

                $this->assertEqual($lineDetails['fileid'], 5);
            }

            // /$lang/firefox/collections/ example
            if($lineDetails['ip'] == '10.119.10.189') {
                $_count++;

                $this->assertEqual($lineDetails['type'], 'collections');

                $this->assertEqual(array_diff(array(6, 7, 9), $lineDetails['addon_ids']), array());
            }


        }

        // The above assertions check that details of matches are correct but they don't check that all the matches were there.  $_count has been keeping track
        $this->assertEqual($_count, 5, "Identify all URLs we need for statistics tracking. (Found {$_count})");

    }

    function testGetLineDetails() {
        // Tested in testParseLine()
    }

    function _getTestData() {
        return file_get_contents(TEST_DATA.'/remora-test-log.log');
    }

}
?>
