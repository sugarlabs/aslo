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
 * Justin Scott <fligtar@gmail.com>.
 * Portions created by the Initial Developer are Copyright (C) 2006
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Mike Shaver <shaver@mozilla.org>
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
class VersionCompareTest extends UnitTestCase {
	function compareVersions($a, $b) {
		return $this->VersionCompare->CompareVersions($a, $b);
	}

	// $test is in the inclusive range bounded by $lower and $upper
	function assertVersionBetweenClosed($test, $lower, $upper) {
		$this->assertTrue($this->VersionCompare->VersionBetween($test, $lower, $upper));
	}

	// $test is NOT in the inclusive range bounded by $lower and $upper
	function assertVersionNotBetweenClosed($test, $lower, $upper) {
		$this->assertFalse($this->VersionCompare->VersionBetween($test, $lower, $upper));
	}
	
	// The versions are considered equal
	function assertVersionsEqual($a, $b) {
		$this->assertEqual($this->compareVersions($a, $b), 0);
	}

	// The first version is greater than the second
	function assertVersionGreater($greater, $lesser) {
		$this->assertEqual($this->compareVersions($greater, $lesser), 1);
	}
	
    function assertVersionAliases($version, $aliases, $expected) {
        $actual = $this->VersionCompare->_versionAlias($version, $aliases);
        $this->assertEqual($expected, $actual);
    }

	//Setup the VC Component
	function setUp() {
		loadComponent('Versioncompare');
		$this->VersionCompare =& new VersionCompareComponent();
	}

	//Make sure there are no PHP errors
	function testNoErrors() {
		$this->assertNoErrors();
	}

	// Minor update is greater than base version
	function testMinorUpdateGreater() {
		$this->assertVersionGreater('1.0.1.4', '1.0');
	}
	
	// A later update with fewer components is greater than earlier+longer
	function testLaterShorterMinorUpdateGreater() {
		$this->assertVersionGreater('1.0.2', '1.0.1.5');
	}

    // Beta is less than release-stream wildcard
    function testBetaLessThanReleaseStreamWildcard() {
		$this->assertVersionGreater('2.0.0.*', '2.0b1');
	}
	
	// Alpha is less than beta, beta less than final
	function testAlphaBetaRCFinalOrdering() {
		$this->assertVersionGreater('3.0b1', '3.0a2');
		$this->assertVersionGreater('2.0', '2.0b3');
	}

	// A compatible update is between the release version and the compatible-stream wildcard
	function testCompatibleUpdateInReleaseStream() {
		$this->assertVersionBetweenClosed('2.0.0.1', '2.0', '2.0.0.*');
	}
	
	// Incompatible updates aren't
	function testIncompatibleUpdateNotInStream() {
		$this->assertVersionNotBetweenClosed('2.0.1', '2.0', '2.0.0.*');
	}

    // Extra zeroes don't matter
    function testImpliedZeroes() {
		$this->assertVersionsEqual('1.0', '1.0.0.0');
		$this->assertVersionsEqual('1.0.0', '1.0');
		$this->assertVersionsEqual('1.5', '1.5.0.0');
    }

    function testVersionAlias() {
        $aliases = array(
            '1.0' => array('0.5'),
            '3.0' => array('2.0', '3.0'));

        // Check that the key is included in the returned array.
        $this->assertVersionAliases('1.0', $aliases, array('0.5', '1.0'));
        // Check that a version without aliases just returns the given version.
        $this->assertVersionAliases('2.0', $aliases, array('2.0'));
        // Check that the key doesn't show up in the returned array mulitple times.
        $this->assertVersionAliases('3.0', $aliases, array('2.0', '3.0'));
    }
}
?>
