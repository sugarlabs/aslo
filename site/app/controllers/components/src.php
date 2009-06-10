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
 * Justin Scott <fligtar@gmail.com>.
 * Portions created by the Initial Developer are Copyright (C) 2006
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
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

class SrcComponent extends Object {
    /**
     * Parses install.rdf using Rdf_parser class
     * @param string $manifestData
     * @return array $data["manifest"]
     */
    function parse($file) {
        //If the file doesn't exist, we assume the file contents are being passed directly
        if (file_exists($file)) {
            $contents = file_get_contents($file);
        }
        else {
            $contents = $file;
        }

        $contents = utf8_encode($contents);
        $contents = str_replace("\n", "", $contents);
        
        //Get the attributes of the search element
        if (preg_match("/<search([^<]*)>/i", $contents, $matches)) {
            $search = $matches[1];
            //Get the description to be used as the add-on name
            if (preg_match("/description=\"([^\"]*)\"/", $search, $matches)) {
                return $matches[1];
            }
            else {
                return false;
            }
        }
        else {
            return false;
        }
    }   
}
?>
