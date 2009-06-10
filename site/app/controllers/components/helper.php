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
 * The Original Code is http://m3nt0r.de/blog/2007/08/12/cakephp-helpercomponent/.
 *
 * The Initial Developer of the Original Code is
 * Kjell
 * Portions created by the Initial Developer are Copyright (C) 2007
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *     Frederic Wenzel <fwenzel@mozilla.com>
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
 * This component allows helpers to be used as if they were components. This
 * is useful in the rare case that helper functionality is needed outside a
 * view, inside a controller, in order not to duplicate code.
 *
 * Usage:
 * Add this component, and an array like:
 *      var $actionHelpers = array('html');
 * to your controller, then use the respective helper(s) like components.
 */
class HelperComponent extends Object {
    var $controller;

    function startup(&$controller) {
        $this->controller = $controller;
        if (isset($controller->actionHelpers)) {
            $this->pushHelpers();
        }
    }

    function pushHelpers() {
        foreach($this->controller->actionHelpers as &$helper) {
            $_helper = ucfirst($helper);
            loadHelper($_helper);
            if ($_helper == 'Html') { // replace regular HTML helper with Addons HTML helper
                loadHelper('AddonsHtml');
                $_helperClassName = 'AddonsHtmlHelper';
            } else {
                $_helperClassName = $helper.'Helper';
            }
            $this->controller->{$helper} = new $_helperClassName();
            $this->controller->{$helper}->base = $this->controller->base; // for URL magic
        }
    }
}
?>
