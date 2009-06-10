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
 *   Mike Morgan <morgamic@mozilla.com> (Original Developer)
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

class GroupsController extends AppController {

	var $name = 'Groups';
    var $components = array('Session');
	var $helpers = array('Html', 'Form');
    var $layout = 'mozilla';

   /**
    * Require login for all actions
    */
    function beforeFilter() {

        //beforeFilter() is apparently called before components are initialized. Cake++
        $this->Amo->startup($this);
        $this->Amo->checkLoggedIn();

        $this->breadcrumbs = array('Groups' => '/groups');
        $this->set('breadcrumbs', $this->breadcrumbs);

        global $native_languages;
        $this->set('nativeLanguages', $native_languages);

        $this->set('cssAdd', array('forms'));
    }

	function index() {
        $this->pageTitle = _('admin_group_pagetitle').' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
		$this->Group->recursive = 0;
		$this->set('groups', $this->Group->findAll());
	}

	function add() {
        $this->pageTitle = _('admin_group_add_pagetitle').' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
		if(empty($this->data)) {
			$this->render();
		} else {
			$this->cleanUpFields();
			if($this->Group->save($this->data)) {
				$this->Session->setFlash(_('admin_group_saved'));
				$this->redirect('/groups/index');
			} else {
				$this->Session->setFlash(_('error_formerrors'));
			}
		}
	}

    /**
     * @param int $id
     */
	function edit($id = null) {
        $this->pageTitle = _('admin_group_edit_pagetitle').' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
		if(empty($this->data)) {
			if(!$id) {
				$this->Session->setFlash(_('admin_group_error_invalid_id'));
				$this->redirect('/groups/index');
			}
			$this->data = $this->Group->read(null, $id);
		} else {
			$this->cleanUpFields();
			if($this->Group->save($this->data)) {
				$this->Session->setFlash(_('admin_group_saved'));
				$this->redirect('/groups/index');
			} else {
				$this->Session->setFlash(_('error_formerrors'));
			}
		}
	}

    /**
     * @param int $id
     */
	function delete($id = null) {
        $this->pageTitle = _('admin_group_delete_pagetitle').' :: '.sprintf(_('addons_home_pagetitle'), APP_PRETTYNAME);
		if(!$id) {
			$this->Session->setFlash(_('admin_group_error_invalid_id'));
			$this->redirect('/groups/index');
		}
		if($this->Group->del($id)) {
			$this->Session->setFlash(sprintf(_('admin_group_deleted'), $id));
			$this->redirect('/groups/index');
		}
	}
}
?>
