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
 *   Justin Scott <fligtar@gmail.com>
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

class Addontype extends AppModel
{
    var $name = 'Addontype';
    
    var $hasMany_full = array('Addon' =>
                              array('className'   => 'Addon',
                                    'conditions'  => '',
                                    'order'       => '',
                                    'limit'       => '',
                                    'foreignKey'  => 'addontype_id',
                                    'dependent'   => false,
                                    'exclusive'   => false,
                                    'finderSql'   => ''
                              ),
                              'Category' =>
                              array('className'   => 'Category',
                                    'conditions'  => '',
                                    'order'       => '',
                                    'limit'       => '',
                                    'foreignKey'  => 'addontype_id',
                                    'dependent'   => false,
                                    'exclusive'   => false,
                                    'finderSql'   => ''
                              )
                  );

    /**
     *  Returns the name of the add-on type requested
     *
     *  @param int id of the add-on type
     *  @return string
     */
    function getName($id) {
        switch($id) {
            case ADDON_EXTENSION:
                return ___('general_addontype_extension');
            case ADDON_THEME: 
                return ___('general_addontype_theme');
            case ADDON_DICT: 
                return ___('general_addontype_dict');
            case ADDON_SEARCH: 
                return ___('general_addontype_search');
            case ADDON_LPAPP: 
                return ___('general_addontype_lpapp');
            case ADDON_LPADDON: 
                return ___('general_addontype_lpaddon');
            case ADDON_PLUGIN: 
                return ___('general_addontype_plugin');
        }
    }
                  
    /**
     * Returns an array of all addontype names and IDs in the form of:
     *         id => name
     * @return array
     */
    function getNames() {
        $addontypes = array(
            ADDON_EXTENSION => ___('general_addontype_extension_plural'),
            ADDON_THEME => ___('general_addontype_theme_plural'),
            ADDON_DICT => ___('general_addontype_dict_plural'),
            ADDON_SEARCH => ___('general_addontype_search_plural'),
            ADDON_LPAPP => ___('general_addontype_lpapp_plural'),
            ADDON_LPADDON => ___('general_addontype_lpaddon_plural'),
            ADDON_PLUGIN => ___('general_addontype_plugin_plural')
        );

        return $addontypes;
    }
}
?>
