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
 *      Justin Scott <fligtar@gmail.com>.
 * 
 * Portions created by the Initial Developer are Copyright (C) 2006
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *      Mike Morgan <morgamic@mozilla.com>
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

$_GET['groups'] = array(
    0 => array(
        'name' => 'Controllers',
        'description' => 'All controller tests',
        'cases' => array(
            'controllers/*'
        )
    ),
    1 => array(
        'name' => 'Components',
        'description' => 'All component tests',
        'cases' => array(
            'controllers/components/*'
        )
    ),
    2 => array(
        'name' => 'Services',
        'description' => 'All non-Cake backend services',
        'cases' => array(
            'services/*'
        )
    ),
    3 => array(
        'name' => 'Models',
        'description' => 'All model tests',
        'cases' => array(
            'models/*'
        )
    ),
    4 => array(
        'name' => 'Views',
        'description' => 'All view tests',
        'cases' => array(
            'views/*'
        )
    ),
    5 => array(
        'name' => 'Developers',
        'description' => 'Developer CP tests',
        'cases' => array(
            'controllers/developers_controller.test.php',
            'controllers/components/amo.test.php',
            'controllers/components/developers.test.php',
            'controllers/components/error.test.php',
            'controllers/components/rdf.test.php',
            'controllers/components/src.test.php',
            'views/developers/*'
        )
    ),
);
?>
