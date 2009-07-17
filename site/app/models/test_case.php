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

class TestCase extends AppModel
{
	var $name = 'TestCase';
	var $belongsTo = array('TestGroup');
	var $hasMany_full = array(
		'TestResult' =>
		array(
			'className'   => 'TestResult',
			'conditions'  => '',
			'order'       => '',
			'limit'       => '',
			'foreignKey'  => 'test_case_id',
			'dependent'   => true,
			'exclusive'   => false,
			'finderSql'   => ''
		)
	);

	/**
	 * Tie into Cake's callback to populate the array with localized strings
	 * @param array $results the results of the find operation
	 * @return array our modified results
	 */
	function afterFind($results) {

		// Don't modify these arrays, as they are used
		// as a translation mechanism to localize test names
		$names = array(
			'11' => ___('test_case_name_all_general_verifyExtension', 'General Verification'),
			'12' => ___('test_case_name_all_general_verifyInstallRDF', 'install.rdf Verification Tests'),
			'13' => ___('test_case_name_all_general_verifyFileTypes', 'File type Blacklist'),
			'21' => ___('test_case_name_all_security_filterUnsafeJS', 'Unsafe JavaScript'),
			'22' => ___('test_case_name_all_security_filterUnsafeSettings', 'Unsafe Settings'),
			'23' => ___('test_case_name_all_security_filterRemoteJS', 'Remote JavaScript'),
			'121' => ___('test_case_name_extension_security_checkGeolocation', 'Geolocation Check'),
			'122' => ___('test_case_name_extension_security_checkConduit', 'Conduit Toolbar Check'),
			'211' => ___('test_case_name_dictionary_general_verifyFileLayout', 'File Layout'),
			'212' => ___('test_case_name_dictionary_general_checkExtraFiles', 'File Type Verification'),
			'213' => ___('test_case_name_dictionary_general_checkSeaMonkeyFiles', 'SeaMonkey Valid Files Check'),
			'221' => ___('test_case_name_dictionary_security_checkInstallJS', 'install.js Verification'),
			'311' => ___('test_case_name_langpack_general_verifyFileLayout', 'File Layout'),
			'312' => ___('test_case_name_langpack_general_checkExtraFiles', 'File Type Verification'),
			'321' => ___('test_case_name_langpack_security_filterUnsafeHTML', 'HTML Check'),
			'322' => ___('test_case_name_langpack_security_checkRemoteLoading', 'Remote Loading Check'),
			'323' => ___('test_case_name_langpack_security_checkChromeManifest', 'chrome.manifest Check'),
			'411' => ___('test_case_name_theme_general_verifyFileLayout', 'File Layout'),
			'421' => ___('test_case_name_theme_security_checkChromeManifest', 'chrome.manifest Check')
		);

		// Might not even need this ...
		$descriptions = array(
			'11' => ___('test_case_name_all_general_verifyExtension', 'General Verification'),
			'12' => ___('test_case_name_all_general_verifyInstallRDF', 'install.rdf Verification Tests'),
			'13' => ___('test_case_name_all_general_verifyFileTypes', 'File type Blacklist'),
			'21' => ___('test_case_name_all_security_filterUnsafeJS', 'Unsafe JavaScript'),
			'22' => ___('test_case_name_all_security_filterUnsafeSettings', 'Unsafe Settings'),
			'23' => ___('test_case_name_all_security_filterRemoteJS', 'Remote JavaScript'),
			'121' => ___('test_case_name_extension_security_checkGeolocation', 'Geolocation Check'),
			'122' => ___('test_case_name_extension_security_checkConduit', 'Conduit Toolbar Check'),
			'211' => ___('test_case_name_dictionary_general_verifyFileLayout', 'File Layout'),
			'212' => ___('test_case_name_dictionary_general_checkExtraFiles', 'File Type Verification'),
			'213' => ___('test_case_name_dictionary_general_checkSeaMonkeyFiles', 'SeaMonkey Valid Files Check'),
			'221' => ___('test_case_name_dictionary_security_checkInstallJS', 'install.js Verification'),
			'311' => ___('test_case_name_langpack_general_verifyFileLayout', 'File Layout'),
			'312' => ___('test_case_name_langpack_general_checkExtraFiles', 'File Type Verification'),
			'321' => ___('test_case_name_langpack_security_filterUnsafeHTML', 'HTML Check'),
			'322' => ___('test_case_name_langpack_security_checkRemoteLoading', 'Remote Loading Check'),
			'323' => ___('test_case_name_langpack_security_checkChromeManifest', 'chrome.manifest Check'),
			'411' => ___('test_case_name_theme_general_verifyFileLayout', 'File Layout'),
			'421' => ___('test_case_name_theme_security_checkChromeManifest', 'chrome.manifest Check')
		);

		foreach ($results as $key => $result) {
			if (!empty($result['TestCase'][0])) { // Doing a find all...
				foreach ($result['TestCase'] as $idx => $data) {
					$id = $result['TestCase'][$idx]['id'];
					$results[$key]['TestCase'][$idx]['name'] = $names[$id];
					$results[$key]['TestCase'][$idx]['description'] = $descriptions[$id];
				}
			} else {
				// Temporary hook for missing data
				if (empty($result['TestCase'])) return $results;

				$id = $result['TestCase']['id'];
				$results[$key]['TestCase']['name'] = $names[$id];
				$results[$key]['TestCase']['description'] = $descriptions[$id];
			}
		}
		return $results;
	}
}
?>
