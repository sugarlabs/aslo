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
 * Portions created by the Initial Developer are Copyright (C) 2006
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Frederic Wenzel <fwenzel@mozilla.com> (Original Author)
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
 * These tests are for behavior that's common to all models.
 */
class App_ModelTest extends UnitTestCase {
    
    function App_ModelTest() {
        loadModel('Addon');
    }

    /**
     * Check if default field handling works as expected
     */
    function testDefaultFields() {
        $_id = 7;
        $this->Addon = new Addon();

        $this->assertFalse(empty($this->Addon->default_fields), 'Addon model needs some default fields defined');

        // now do a default fetch and make sure the default fields and only
        // those are returned
        $addon = $this->Addon->findById($_id);
        foreach ($this->Addon->default_fields as $field) {
            if (false !== $_field = strstr($field, '.')) {
                // get raw field name
                $_field = substr($_field, 1);
                $_field = trim($_field, '`');
            } else {
                $_field = $field;
            }

            // translated field or regular field?
            if (in_array($_field, $this->Addon->translated_fields))
                $_model = 'Translation';
            else
                $_model = 'Addon';

            $_wasreturned = array_key_exists($_field, $addon[$_model]);
            $this->assertTrue($_wasreturned, 'Default field "'.$_field.'" is returned');

            if ($_wasreturned) {
                // remove the field so that we can detect excessive returns later
                unset($addon[$_model][$_field]);
            }
        }

        $this->assertTrue(empty($addon['Addon']) && (!isset($addon['Translation']) || empty($addon['Translation'])), 'No excessive retrieval beyond list of default fields');
    }

    /**
     * Test extended field validation
     */
    function testExtendedValidation() {
        $testdata = array(
            'ValidationModel' => array(
                'testfield' => 'do not fail'
        ));

        // validation function should be called once
        $vmodel = &new TallyValidationModel();
        $vmodel->expectOnce('clean_testfield', array($testdata['ValidationModel']['testfield']));
        $vmodel->invalidFields($testdata);
        $vmodel->tally();

        // now instanciate the real model and check if validationErrors is amended correctly
        $vmodel = &new MockValidationModel();
        $vmodel->invalidFields($testdata);
        $this->assertTrue(empty($vmodel->validationErrors), 'valid field does not cause extended validation error');

        $vmodel = &new MockValidationModel();
        $testdata['ValidationModel']['testfield'] = 'fail';
        $vmodel->invalidFields($testdata);
        $this->assertFalse(empty($vmodel->validationErrors), 'invalid field causes extended validation error');
    }

    /**
     * Test field validation for bulk translations
     */
    function testTranslationValidation() {
        $testdata = array(
            'testfield' => array(
                'en-US' => 'okay',
                'ab-CD' => 'fail',
                'de'    => 'okay'
            )
        );

        // validation function should be called at least once
        $vmodel = &new TallyValidationModel();
        $vmodel->expectAtLeastOnce('clean_testfield');
        $vmodel->expectMaximumCallCount('clean_testfield', count($testdata['testfield'])); // do not "over-validate"
        $vmodel->validateTranslations($testdata);
        $vmodel->tally();

        // invalid translations should fail
        $vmodel = &new MockValidationModel();
        $res = $vmodel->validateTranslations($testdata);
        $this->assertFalse($res, 'invalid translation leads to extended validation error');

        // valid translations should not fail
        $vmodel = &new MockValidationModel();
        unset($testdata['testfield']['ab-CD']); // remove invalid data
        $res = $vmodel->validateTranslations($testdata);
        $this->assertTrue($res, '(only) valid translations should not lead to extended validation error');
    }
}

class ValidationModel extends AppModel {
    var $name = 'ValidationModel';
    var $validate = array('testfield' => VALID_NOT_EMPTY);
    var $translated_fields = array('testfield');
    /**
     * extended validation function: invalidates testfield iff it equals "fail"
     */
    function clean_testfield($input) {
        if ($input == 'fail') $this->invalidate('testfield');
    }
}
Mock::generatePartial('ValidationModel', 'TallyValidationModel', array('clean_testfield'));
Mock::generatePartial('ValidationModel', 'MockValidationModel', array());
?>
