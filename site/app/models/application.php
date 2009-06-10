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

class Application extends AppModel
{
    var $name = 'Application';
    var $hasMany = array('Appversion' =>
                         array('className'   => 'Appversion',
                               'conditions'  => '',
                               'order'       => '',
                               'limit'       => '',
                               'foreignKey'  => 'application_id',
                               'dependent'   => true,
                               'exclusive'   => false,
                               'finderSql'   => ''
                         ),
                         'Tag' =>
                         array('className'   => 'Tag',
                               'conditions'  => '',
                               'order'       => '',
                               'limit'       => '',
                               'foreignKey'  => 'application_id',
                               'dependent'   => false,
                               'exclusive'   => false,
                               'finderSql'   => ''
                         ),

                  );
    var $hasAndBelongsToMany = array('Version' =>
                                      array('className'  => 'Version',
                                            'joinTable'  => 'applications_versions',
                                            'foreignKey' => 'application_id',
                                            'associationForeignKey'=> 'version_id',
                                            'conditions' => '',
                                            'order'      => '',
                                            'limit'      => '',
                                            'unique'     => false,
                                            'finderSql'  => '',
                                            'deleteQuery'=> ''
                                      )
                                      );
    var $translated_fields = array(
                'name', 
                'shortname'
            );
    
   /**
    * Returns an array of application GUIDs with their associated names
    */
    function getGUIDList() {
        $list = array();
        $apps = $this->findAll(null, array('guid', 'name'), null, null, null, -1);
        
        if (!empty($apps)) {
            foreach ($apps as $app) {
                $list[$app['Application']['guid']] = $app['Translation']['name']['string'];
            }
        }
        
        return $list;
    }

    /**
     *  Return a list of application ids and names
     *  @deprecated since 3.5 - use getNames()
     */
    function getIDList() {
        return $this->getNames();
    }

    /**
     * Returns an array of all application names and IDs in the form of:
     *         id => name
     */
    function getNames() {
        $_applications = $this->findAll(null, array('Application.id', 'Application.name'), null, null, null, -1);
        
        $applications = array();
        if (!empty($_applications)) {
            foreach ($_applications as $application) {
                $applications[$application['Application']['id']] = $application['Translation']['name']['string'];
            }
            asort($applications);
        }
        return $applications;
    }
    
    /**
     * Returns an array of all application shortnames and IDs in the form of:
     *         id => shortname
     */
    function getShortNames() {
        $_applications = $this->findAll(null, array('Application.id', 'Application.shortname'), null, null, null, -1);
        
        $applications = array();
        if (!empty($_applications)) {
            foreach ($_applications as $application) {
                $applications[$application['Application']['id']] = $application['Translation']['shortname']['string'];
            }
            asort($applications);
        }
        return $applications;
    }

}
?>
