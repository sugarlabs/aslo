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

class TestGroup extends AppModel
{
    var $name = 'TestGroup';
    var $hasMany_full = array(
        'TestCase' =>
        array(
            'className'   => 'TestCase',
            'conditions'  => '',
            'order'       => '',
            'limit'       => '',
            'foreignKey'  => 'test_group_id',
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
            '1' => ___('General'),
            '2' => ___('Security'),
            '3' => ___('L10n'),
            '11' => ___('Extension-specific'),
            '12' => ___('Extension-specific security'),
            '21' => ___('Dictionary-specific'),
            '22' => ___('Dictionary-specific security'),
            '31' => ___('Language Pack-specific'),
            '32' => ___('Language Pack-specific security'),
            '41' => ___('Theme-specific'),
            '42' => ___('Theme-specific security'),
            '51' => ___('Search Engine-specific'),
            '52' => ___('Search Engine-specific security')
        );

        foreach ($results as $key => $result) {
            if (!empty($result['TestGroup'][0])) { // Doing a find all...
                foreach ($result['TestGroup'] as $idx => $data) {
                    $id = $result['TestGroup'][$idx]['id'];
                    $results[$key]['TestGroup'][$idx]['name'] = $names[$id];
                }
            } else {
                $id = $result['TestGroup']['id'];
                $results[$key]['TestGroup']['name'] = $names[$id];
            }
        }
        return $results;
    }

    /**
     * Test Groups are masked based on the addon type.  The types field is basically a
     * 1-indexed bitmap, since the addontypes defined in constants.php are 1-indexed.
     *
     * For example, if you want to find all the test groups supported for ADDON_THEME, 
     * a call might look like: 
     * 
     *   $groups = $this->TestCase->getTestGroupsForAddonType(ADDON_THEME);
     *
     * The extra parameters serve as optional arguments for additional find specificity. 
     *
     * @param int $addonType the type id for the addon
     * @param array $conds any additonal conditions
     * @param array $fields fields to fetch
     * @return array the test groups for the given addon type
     */
    function getTestGroupsForAddonType($addonType, $conds = array(), $fields = array('*')) {
       
        // Subtract 1 to get the 0-indexed value
        $mask = pow(2, $addonType - 1);

        // Bind fully to pull in test case data.  This will let us 
        // determine which groups have no cases, and exclude them.
        $this->bindFully();

        // FindAll takes two parameters, conditions and fields, so we need to 
        // merge our mask condition with the rest of the conditions.  It doesn't look
        // like a normal condition becasuse cake will reject 'TestGroup.types & $mask' 
        // as a field name.
        $results =  $this->findAll(array_merge(array("TestGroup.types & {$mask} = {$mask}"), $conds), $fields);

        // Unset anything with no test cases
        if (!empty($results)) {
            foreach ($results as $key => $item) {
                if (empty($item['TestCase'])) {
                    unset($results[$key]);
                }
            }
        }

        return $results;
    }
}
?>
