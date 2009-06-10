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
class LegacyUrlController extends AppController
{
    var $name = 'LegacyUrlController';
    var $components = array('Amo');

    var $securityLevel = 'low';

    function beforeFilter() {
        // Disable ACLs because this controller is entirely public.
        $this->SimpleAuth->enabled = false;
        $this->SimpleAcl->enabled = false;
    }
    
    /**
     * If someone gets to this controller, but can't go further, this function is
     * called.  This would indicate a bug in the routing or some other config!
     */
    function _defaultAction() {
        // Go to the home page
        $this->redirect('/');
    }

    function oldSection($section) {
        switch($section) {
            case "extensions":
                return $this->redirect("/browse/type:" . ADDON_EXTENSION);
            case "themes":
                return $this->redirect("/browse/type:" . ADDON_THEME);
            case "dictionaries":
                return $this->redirect("/browse/type:" . ADDON_DICT);
            case "plugins":
                return $this->redirect("/browse/type:" . ADDON_PLUGIN);

            // Need to show the "browse all search engines" page (bug 426085)
            case "search-engines":
                return $this->redirect("/browse/type:".ADDON_SEARCH."/cat:all?sort=name");

            // Firefox has a default bookmark that says "Get Bookmark Add-ons".  And
            // yeah, I hardcoded that category to 22. :(
            case "bookmarks":
                return $this->redirect("/browse/type:".ADDON_EXTENSION."/cat:22/sort:popular");
            default:
                return $this->_defaultAction();
        }
    }


}

?>
