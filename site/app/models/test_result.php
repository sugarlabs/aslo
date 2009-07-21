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

class TestResult extends AppModel
{
    var $name = 'TestResult';
    var $belongsTo = array(
        'File' =>
        array(
            'className'   => 'File',
            'conditions'  => '',
            'order'       => '',
            'foreignKey'  => 'file_id'
        ),
        'TestCase' =>
        array(
            'className'   => 'TestCase',
            'conditions'  => '',
            'order'       => '',
            'foreignKey'  => 'test_case_id'
        )
    );

    /**
     * Deletes old results associated with a test
     * @param int $file_id  the file to delete results for
     * @param int $test_group_id  the group id to delete results for
     */
    function deleteOldResults($file_id, $test_group_id) {

        // We need a custom query here to use the test_cases table as a join table
        $this->query("DELETE `test_results`
FROM `test_results`
INNER JOIN `test_cases`
ON `test_results`.`test_case_id` = `test_cases`.`id`
WHERE `file_id` = $file_id
AND `test_group_id` = $test_group_id");
    }

}
?>
