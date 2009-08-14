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
 *   Andrei Hajdukewycz <sancus@off.net> (Original Author)
 *   Wil Clouser <clouserw@mozilla.com>
 *   Justin Scott <fligtar@gmail.com>
 *   Frederic Wenzel <fwenzel@mozilla.com>
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

class PreviewsController extends AppController
{
    var $name = 'Previews';
    var $uses = array('Addon', 'Addontype', 'Preview', 'Translation');
    var $components = array('Amo', 'Developers', 'Error', 'Image');
    var $helpers = array('Html', 'Javascript', 'Localization');

    var $securityLevel = 'low';
    
   /**
    * Require login for all actions
    */
    function beforeFilter() {
        // Disable ACLs because this controller is entirely public.
        $this->SimpleAuth->enabled = false;
        $this->SimpleAcl->enabled = false;

        //beforeFilter() is apparently called before components are initialized. Cake++
        $this->Amo->startup($this);
        $this->Amo->checkLoggedIn();

        //Clean post data
        $this->Amo->clean($this->data); 

        $this->layout = 'mozilla';
        $this->pageTitle = ___('Developer Tools').' :: '.sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME);

		$this->cssAdd = array('developers');
        $this->publish('cssAdd', $this->cssAdd);

        $this->publish('suppressJQuery', 1);
        $this->jsAdd = array('developers',
                             'scriptaculous/prototype',
                             'scriptaculous/scriptaculous.js?load=effects'
                             ,'jquery-compressed.js');
        $this->publish('jsAdd', $this->jsAdd);

        $this->breadcrumbs = array(___('Developer Tools') => '/developers/index');
        $this->publish('breadcrumbs', $this->breadcrumbs);
        
        $this->publish('subpagetitle', ___('Developer Tools'));

        global $native_languages;
        $this->publish('nativeLanguages', $native_languages);
        
        // Default "My Add-ons" sidebar data
        $session = $this->Session->read('User');
        $this->publish('all_addons', $this->Addon->getAddonsByUser($session['id']));
        
        // disable query caching so devcp changes are visible immediately
        foreach ($this->uses as $_model) {
            $this->$_model->caching = false;
        }
    }

   /**
    * Add a preview
    * @param int $addon_id The add-on id
    */
    function add($addon_id) {
        $this->Amo->clean($addon_id);
        $this->publish('subpagetitle', ___('Add Preview'));
        $this->breadcrumbs[___('Add Preview')] = '/previews/add/'.$addon_id;
        $this->publish('breadcrumbs', $this->breadcrumbs);  

        if (!$this->Amo->checkOwnership($addon_id)) {
            $this->flash(___('You do not have access to that add-on.'), '/developers/index');
            return;
        }

        $this->Addon->bindFully();
        $this->Addon->id = $addon_id;
        if (!$addon = $this->Addon->read()) {
            $this->flash(___('Add-on not found!'), '/developers/index');
            return;
        }
        
        $this->Preview->id = 0;
        $this->publish('id', $addon_id);

        if (!empty($this->data)) {

            //If highlighting, remove other highlights
            if (!empty($this->data['Preview']['highlight']) && empty($preview['Preview']['highlight'])) {
                $this->Developers->unhighlightOtherPreviews($addon['Addon']['id']);
            }

            if ($previewData = $this->Developers->addPreview($addon_id, $this->data['Preview'])) {
                $this->Preview->save($previewData);
                $id = $this->Preview->getLastInsertId();
                
                $this->Developers->saveTranslations($this->data, array('Preview'));
                
                $this->flash(___('Preview added successfully.'), '/previews/edit/'.$id);
                return;
            }
        }

        if (count($addon['Preview']) > 0) {
            $highlightCheckbox = array('onClick' => 'return confirmMakeDefault(this);');
        }
        else {
            $highlightCheckbox = array('checked' => 'checked', 'disabled' => 'disabled');
        }
        $this->publish('highlightCheckbox', $highlightCheckbox);

        //Retrieve language arrays from bootstrap.
        global $valid_languages, $native_languages;
        foreach (array_keys($valid_languages) as $key) {
            $languages[$key] = $native_languages[$key]['native'];

            $this->Preview->setLang($key, $this);
            $previewL = $this->Preview->read();

            foreach ($this->Preview->translated_fields as $field) {
                $info[$key][$field] = '';
            }
        }
        $this->Preview->setLang(LANG, $this);
        $this->publish('errors', $this->Error->errors);
        
        $this->publish('addon', $addon);

        $localizedFields = array(
                             'caption' => array(
                                            'type'       => 'input',
                                            'display'    => ___('Preview Caption'),
                                            'model'      => 'Preview',
                                            'field'         => 'caption',
                                            'attributes' => array(
                                                              'size' => 40
                                                            )
                                          )
                           );

        //Set up localebox info
        $this->set('localebox', array('info' => $info,
                                      'defaultLocale' => $addon['Addon']['defaultlocale'],
                                      'languages' => $languages,
                                      'localizedFields' => $localizedFields));
        //Javascript localization
        $this->publish('jsLocalization', array(
                                          'makeDefaultNotice' => ___('Making this the default preview will remove default status from the current default preview.')
                                          ));
    }

   /**
    * Edit a preview
    * @param int $id Preview id
    */
    function edit($id) {
        $this->Amo->clean($id);
        $this->set ('subpagetitle', ___('Edit Preview'));
        $this->breadcrumbs[___('Edit Preview')] = '/previews/edit/'.$id;
        $this->publish('breadcrumbs', $this->breadcrumbs);  

        $this->Preview->id = $id;
        if (!$preview = $this->Preview->read()) {
            $this->flash(___('Preview not found!'), '/developers/index');
            return;
        }

        $this->Addon->id = $preview['Preview']['addon_id'];
        if (!$addon = $this->Addon->read()) {
            $this->flash(___('Add-on not found!'), '/developers/index');
            return;
        }
   
        if (!$this->Amo->checkOwnership($this->Addon->id)) {
            $this->flash(___('You do not have access to that add-on.'), '/developers/index');
            return;
        }

        if (!empty($this->data)) {
            if (!empty($_POST['delete'])) {
                $this->_delete($id);
                return;
            }

            //If removing highlight, hightlight another preview
            if (empty($this->data['Preview']['highlight']) && !empty($preview['Preview']['highlight'])) {
                $this->Developers->highlightNextPreview($preview, $addon);
            }

            //If highlighting, remove other highlights
            if (!empty($this->data['Preview']['highlight']) && empty($preview['Preview']['highlight'])) {
                $this->Developers->unhighlightOtherPreviews($addon['Addon']['id']);
            }

            $previewData = $this->Amo->filterFields($this->data['Preview'], array('highlight'));
            $this->Preview->save($previewData);

            //Save translated fields (caption)
            $this->Developers->saveTranslations($this->data, array('Preview'));

            $this->flash(___('Preview updated successfully.'), '/previews/edit/'.$id);
            return;
        }

        if (!empty($preview['Preview']['highlight'])) {
            $highlightCheckbox = array('value' => '1', 'checked' => 'checked', 'onClick' => 'return confirmClearDefault(this);');
        }
        else {
            $highlightCheckbox = array('onClick' => 'return confirmMakeDefault(this);');
        }
        $this->publish('highlightCheckbox', $highlightCheckbox);

        //Javascript localization
        $this->publish('jsLocalization', array(
                                          'makeDefaultNotice' => ___('Making this the default preview will remove default status from the current default preview.'),
                                          'clearDefaultNotice' => ___('Removing this as the default preview will cause another preview to automatically become the default preview.')
                                         ));

        //Retrieve language arrays from bootstrap.
        global $valid_languages, $native_languages;
        foreach (array_keys($valid_languages) as $key) {
            $languages[$key] = $native_languages[$key]['native'];

            $this->Preview->setLang($key, $this);
            $previewL = $this->Preview->read();

            foreach ($previewL['Translation'] as $field => $translation) {
                if ($translation['locale'] == $key) {
                    $info[$key][$field] = $translation['string'];
                }
                else {
                    $info[$key][$field] = '';
                }
            }
        }
        $this->Preview->setLang(LANG, $this);

        $localizedFields = array(
                             'caption' => array(
                                            'type'       => 'input',
                                            'display'    => ___('Preview Caption'),
                                            'model'      => 'Preview',
                                            'field'         => 'caption',
                                            'attributes' => array(
                                                              'size' => 40
                                                            )
                                          )
                           );

        //Set up localebox info
        $this->set('localebox', array('info' => $info,
                                      'defaultLocale' => $addon['Addon']['defaultlocale'],
                                      'languages' => $languages,
                                      'localizedFields' => $localizedFields));
        $this->publish('id', $id);
        $this->publish('addon', $addon);
        $this->publish('previewUrl', $this->Image->getPreviewURL($id));
    }

   /**
    * Delete a preview
    * @param int $id Preview id
    */
    function _delete($id) {
        $this->Amo->clean($id);
        $this->Preview->id = $id;
        $preview = $this->Preview->read();

        $this->Addon->id = $preview['Preview']['addon_id'];
        if (!$addon = $this->Addon->read()) {
            $this->flash(___('Add-on not found!'), '/developers/index');
            return;
        }

        if (!$this->Amo->checkOwnership($addon['Addon']['id'])) {
            $this->flash(___('You do not have access to that add-on.'), '/developers/index');
            return;
        }

        //If currently highlighted, hightlight another preview
        if (!empty($preview['Preview']['highlight'])) {
            $this->Developers->highlightNextPreview($preview, $addon);
        }

        $this->Preview->delete();

        $this->flash(___('Preview deleted successfully.'), '/developers/index/'.$addon['Addon']['id']);
        return;
    }
}

?>
