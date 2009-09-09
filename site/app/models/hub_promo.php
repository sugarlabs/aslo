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
 *   Scott McCammon <smccammon@mozilla.com>
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

class HubPromo extends AppModel
{
    var $name = "HubPromo";
    var $useTable = 'hubpromos';

    var $translated_fields = array(
        'heading',
        'body',
    );

    /**
     * Visibility audiences
     */
    const NOBODY = 0;
    const VISITORS = 1;
    const DEVELOPERS = 2;
    const VISITORS_AND_DEVELOPERS = 3;

    static $visibilities = array(
        self::NOBODY => 'nobody',
        self::VISITORS => 'visitors',
        self::DEVELOPERS => 'developers',
        self::VISITORS_AND_DEVELOPERS => 'visitors and developers',
    );

    /**
     * Fetch all promos visible to visitors
     * @return array cake result set
     */
    function getVisitorPromos() {
        return $this->getPromosForAudience(self::VISITORS);
    }

    /**
     * Fetch all promos visible to developers
     * @return array cake result set
     */
    function getDeveloperPromos() {
        return $this->getPromosForAudience(self::DEVELOPERS);
    }

    /**
     * Fetch all promos visible to the specified audience
     *
     * @param int any class audience constant
     * @return array cake result set
     */
    function getPromosForAudience($audience) {
        // the audience integers have been conveniently defined as bitmasks
        // so we can easily filter using a bitwise and operation
        $mask = intval($audience);
        $conditions = "HubPromo.visibility & {$mask} > 0";
        return $this->findAll($conditions, null, 'HubPromo.modified DESC');
    }

    /**
     * Deletes a HubPromo
     *
     * @param int $id - HubPromo id
     */
    function delete($id) {
        $this->translate = false;
        $promo = $this->findById($id);

        // nothing to delete
        if (empty($promo)) {
            return true;
        }

        // grab string keys
        $string_ids = array();
        if (!empty($promo['HubPromo']['heading'])) {
            $string_ids[] = $promo['HubPromo']['heading'];
        }
        if (!empty($promo['HubPromo']['body'])) {
            $string_ids[] = $promo['HubPromo']['body'];
        }

        // delete HubPromo
        $this->execute("DELETE FROM hubpromos WHERE id = {$id}");

        // delete each translation
        // deletion could theoretically fail due to other foreign key references
        // delete each separately so one failure doesn't abort the other
        foreach ($string_ids as $sid) {
            @$this->execute("DELETE FROM translations WHERE id = '{$sid}'");
        }

        return true;
    }

}
