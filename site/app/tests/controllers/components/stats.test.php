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
 * Contributor(s):
 * Scott McCammon <smccammon@mozilla.com>
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
class StatsTest extends UnitTestCase {
    var $collections = array(1,2);

    //Setup the Stats Component
    function setUp() {
        $this->controller =& new AppController();
        loadComponent('Stats');
        $this->Stats =& new StatsComponent();
        $this->Stats->startup($this->controller);
    }
	
    function testCollectionAddonDailyDownloads() {
        $startDate = date('Y-m-d', strtotime('-6 days'));
        $stats = $this->Stats->getCollectionAddonDailyDownloads($this->collections[0], $startDate);
        $this->assertFalse(empty($stats), 'single collection addons daily downloads not empty');

        $stats = $this->Stats->getCollectionAddonDailyDownloads($this->collections, $startDate);
        $this->assertFalse(empty($stats), 'multiple collection addons daily downloads not empty');
    }

    function testCollectionAddonTotalDownloads() {
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $stats = $this->Stats->getCollectionAddonTotalDownloads($this->collections[0], $startDate);
        $this->assertFalse(empty($stats), 'collection addons total downloads not empty');
    }

    function testCollectionMetricSums() {
        $id = $this->collections[0];
        $startDate = date('Y-m-d', strtotime('-7 days'));

        $total = $this->Stats->getCollectionSubscriberSum($id, $startDate);
        $this->assertTrue($total > 0, 'collection subscriber sum is positive');

        $total = $this->Stats->getCollectionVotesUpSum($id, $startDate);
        $this->assertTrue($total > 0, 'collection votes up sum is positive');

        $total = $this->Stats->getCollectionVotesDownSum($id, $startDate);
        $this->assertTrue($total > 0, 'collection votes down sum is positive');

        $total = $this->Stats->getCollectionDownloadSum($id, $startDate);
        $this->assertTrue($total > 0, 'collection download sum is positive');
    }

    function testCollectionDailyStats() {
        $startDate = date('Y-m-d', strtotime('-6 days'));
        $stats = $this->Stats->getCollectionDailyStats($this->collections[0], $startDate);
        $this->assertTrue(count($stats) == 7, 'fetched 7 days of single collection daily stats');

        $stats = $this->Stats->getCollectionDailyStats($this->collections, $startDate);
        $this->assertTrue(count($stats) == 7, 'fetched 7 days of multiple collection daily stats');
    }
}
?>
