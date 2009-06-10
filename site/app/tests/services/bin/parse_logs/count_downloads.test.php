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

// Uses LogParserTest::_getTestData().  NOTE: for some reason this means all the LogParser tests run
// when this class is tested to...
require_once TESTS.'/services/bin/parse_logs/log_parser.test.php';

class CountDownloadsServiceTest extends UnitTestCase {
	
    /**
     * Sets up default vars and required modules.
     */
	function setUp() {

        // It's stupid we have to hardcode this here but that's the way things are written...
        $config = array('user' => TEST_DB_USER, 'pass' => TEST_DB_PASS, 'host' => TEST_DB_HOST, 'name' => TEST_DB_NAME);

        $db = new Database($config, $config);

        $this->cd = new Count_Downloads($db);

	}
	
    function testCleanIPs() {

        $this->_addFakeCountedIps();

        $this->cd->cleanIPs(strtotime('now'));

        // Make sure it removed an element
        $this->assertTrue(count($this->cd->countedIPs), 3);

        // Make sure it removed the right one
        $this->assertFalse(array_key_exists('0.0.0.0', $this->cd->countedIPs));
    }

    function testCount() {

        $this->_resetCountData();

        // Make sure data is zeroed to begin with
        $this->assertEqual($this->cd->totalCounted, 0);
        $this->assertEqual($this->cd->totalSkipped['blacklist'], 0);
        $this->assertTrue(empty($this->cd->counts['totdown']));
        $this->assertTrue(empty($this->cd->counts['perday']));
        $this->assertTrue(empty($this->cd->counts['collections_and_addons']));

        $this->_countTestData();

        // Check a few numbers to make sure we're adding properly
        $this->assertEqual($this->cd->totalCounted, 23);
        $this->assertEqual($this->cd->totalSkipped['blacklist'], 1);
        $this->assertEqual($this->cd->counts['totdown'][7], 8);
        $this->assertEqual($this->cd->counts['totdown'][10], 2);
        $this->assertEqual($this->cd->counts['perday'][6]['2008-11-25'], 3);
        $this->assertEqual($this->cd->counts['perday'][3369]['2008-11-25'], 1);
        $this->assertEqual($this->cd->counts['collections_and_addons'][1]['total'], 4);
        $this->assertEqual($this->cd->counts['collections_and_addons'][1]['addon_ids'][3677], 1);
        $this->assertEqual($this->cd->counts['collections_and_addons'][1]['addon_ids'][8], 2);

    }

    function testLogFileParsedCallback() {

        $this->_resetCountData();

        $_total_total = mysql_fetch_array($this->cd->db->read("SELECT totaldownloads FROM addons WHERE id=7"));
        $_collection_total = mysql_fetch_array($this->cd->db->read("SELECT downloads from collections WHERE id=1"));
        $_addons_collection_total = mysql_fetch_array($this->cd->db->read("SELECT downloads from addons_collections WHERE addon_id=8 and collection_id=1"));

        // We have to do this cheesy hack because there is no UNIQUE constraint on the addon_id and date columns.
        $_result = $this->cd->db->read("SELECT count FROM download_counts WHERE addon_id=9 AND date='2008-11-25'");
        $_daily_total = 0;
        while ($row = mysql_fetch_array($_result)) {
            $_daily_total +=$row[0];
        }

        $this->_countTestData();

        $this->cd->logfileParsedCallback();

        $_new_total_total = mysql_fetch_array($this->cd->db->read("SELECT totaldownloads FROM addons WHERE id=7"));
        $_new_collection_total = mysql_fetch_array($this->cd->db->read("SELECT downloads from collections WHERE id=1"));
        $_new_addons_collection_total = mysql_fetch_array($this->cd->db->read("SELECT downloads from addons_collections WHERE addon_id=8 and collection_id=1"));

        // We have to do this cheesy hack because there is no UNIQUE constraint on the addon_id and date columns.
        $_result = $this->cd->db->read("SELECT count FROM download_counts WHERE addon_id=9 AND date='2008-11-25'");
        $_new_daily_total = 0;
        while ($row = mysql_fetch_array($_result)) {
            $_new_daily_total +=$row[0];
        }

        $this->assertEqual($_total_total[0]+8, $_new_total_total[0]);
        $this->assertEqual($_daily_total[0], $_new_daily_total[0]);
        $this->assertEqual($_collection_total[0]+4, $_new_collection_total[0]);
        $this->assertEqual($_addons_collection_total[0]+2, $_new_addons_collection_total[0]);

    }

    function _countTestData() {
        $_data = LogParserTest::_getTestData();
        foreach (explode("\n", $_data) as $line) {
            $details = log_parser::parseLine($line);
            $this->cd->count($details);
        }
    }

    function _resetCountData() {
        $this->cd->count['totdown'] = array();
        $this->cd->count['perday'] = array();
        $this->cd->count['collections_and_addons'] = array();
    }

    function _addFakeCountedIps() {
        $this->cd->countedIPs = array(
            '0.0.0.0'         => strtotime('-40 seconds'),
            '127.0.0.1'       => strtotime('-20 seconds'),
            '255.255.255.255' => strtotime('-10 seconds'),
            '10.0.0.0'        => strtotime('now')
            );
    }
}
?>
