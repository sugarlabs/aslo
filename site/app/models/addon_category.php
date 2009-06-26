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
 * Portions created by the Initial Developer are Copyright (C) 2008
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Wil Clouser <clouserw@mozilla.com> (Original Author)
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

class AddonCategory extends AppModel
{
    // This is sort of experimental.  I need to retrieve information stored with this 
    // relationship (in the addons_categories model).  Cake 1.1 doesn't provide a way to do that
    // so I'm sort of abusing it here and making the Addon model do an extra hasMany join
    // against this model (it's already doing an HABTM join against Category using this table).
    // Cake 1.2 fixes this problem and we can rip all this code out... --clouserw

    var $name = 'AddonCategory';
    var $useTable = 'addons_categories';
    var $belongsTo = array('Addon', 'Category');

    var $recursive = -1;

   /**
    * Returns add-ons for the given category (recommended or not).
    *
    * @param mixed single category or array of categories
    * @param bool return recommended or non-recommended add-ons?
    * @param int limit amount of add-ons to return
    * @param string SQL order, default random
    * @return array Add-on IDs that match the criteria
    */
    function getRandomAddons($category, $recommended=false, $limit=null, $order='RAND()', $addontype=null) {
        global $valid_status;
        
        if (!is_array($category)) $category = array($category);
        if (!is_null($addontype) && !is_array($addontype)) $addontype = array($addontype);
        
        $raw_addons = $this->query(
            "SELECT DISTINCT Addon.id "
            ."FROM addons_categories AS AddonCategory "
            ."INNER JOIN addons AS Addon ON (AddonCategory.addon_id = Addon.id)"
            ."WHERE "
                ."AddonCategory.category_id IN (".implode(',', $category).') AND '
                ."AddonCategory.feature = ".($recommended ? '1' : '0')." AND "
                ."Addon.status IN (".implode(',', $valid_status).') AND '
                .'Addon.inactive = 0 '
                .(!empty($addontype) ? ' AND Addon.addontype_id IN ('.implode(',', $addontype).') ' : '')
            ."ORDER BY $order "
            .(!empty($limit) ? "LIMIT $limit" : '')
            );
        
        $idlist = array();
        if (!empty($raw_addons))
            foreach ($raw_addons as $addon) $idlist[] = $addon['Addon']['id'];
        
        return $idlist;
    }
}
?>
