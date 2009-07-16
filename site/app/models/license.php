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
 *
 * Contributor(s):
 *   Jeff Balogh <jbalogh@mozilla.com> (Original Author)
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

class License extends AppModel {
    var $name = 'License';
    var $hasMany = array('Version' =>
                         array('className' => 'Version',
                               'foreignKey' => 'license_id'
                         )
                   );
    var $translated_fields = array('text');

    function getNamesAndUrls() {
        global $licenses;
        return $licenses;
    }
    
    function getBuiltin($license_num) {
        $license = $this->findByName($license_num);
        if ($license == false) {
            $data['License']['name'] = $license_num;
            $this->save($data);
            return $this->getLastInsertId();
        } else {
            return $license['License']['id'];
        }
    }

    function getName($license_id) {
        $license = $this->findById($license_id);
        if ($license['License']['name'] == -1) {
            return ___('license_custom');
        } else {
            $licenses = $this->getNamesAndUrls();
            
            return $licenses[$license['License']['name']]['name'];
        }
    }
    

    function getText($license_id) {
        $license = $this->findById($license_id);
        $name = $license['License']['name'];
        if ($name == -1) {
            return $license['Translation']['text']['string'];
        }
        else {
            return $this->getBuiltinText($name);
        }
    }

    function getBuiltinText($id) {
        // This is gross!  Fixing in 5.0.6.
        $path = APP.DS.WEBROOT_DIR.DS.'licenses'.DS.$id.'.txt';
        return file_get_contents($path);
    }
}
?>
