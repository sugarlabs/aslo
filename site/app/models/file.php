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

class File extends AppModel
{
    var $name = 'File';
    var $belongsTo = array('Version' =>
                           array('className'  => 'Version',
                                 'conditions' => '',
                                 'order'      => '',
                                 'foreignKey' => 'version_id'
                           ),
                           'Platform' =>
                           array('className'  => 'Platform',
                                 'conditions' => '',
                                 'order'      => '',
                                 'foreignKey' => 'platform_id'
                           )
                     );

    var $hasMany = array('Approval' =>
                         array('className'   => 'Approval',
                               'conditions'  => '',
                               'order'       => '',
                               'limit'       => '',
                               'foreignKey'  => 'file_id',
                               'dependent'   => true,
                               'exclusive'   => false,
                               'finderSql'   => ''
						 ),
				   'TestResult' =>
				   array(
					   'className'   => 'TestResult',
					   'conditions'  => '',
					   'order'       => '',
					   'limit'       => '',
					   'foreignKey'  => 'file_id',
					   'dependent'   => true,
					   'exclusive'   => false,
					   'finderSql'   => ''
				   )
	);
    
    /**
     * Returns the latest public file of the add-on with the specified platform
     * @param int $addon_id the add-on id
     * @param int $platform_id optional platform id
     * @return int the file ID
     */
    function getLatestFileByAddonId($addon_id, $platform_id = null) {
        // Platform WHERE if necessary
        $platform = !empty($platform_id) ? " AND (File.platform_id = ".PLATFORM_ALL." OR File.platform_id = {$platform_id})" : '';
        $sp = parse_sp();

        $sql = "
            SELECT
                File.id
            FROM
                files AS File
            INNER JOIN versions AS Version
                ON File.version_id = Version.id AND Version.addon_id = {$addon_id}
            INNER JOIN
                applications_versions A ON A.version_id = Version.id
            INNER JOIN
                appversions as B ON B.id = A.min
            INNER JOIN
                appversions as C ON C.id = A.max
            WHERE
                File.status = ".STATUS_PUBLIC."
                {$sp} >= CAST(B.version AS DECIMAL(3,3)) AND {$sp} <= CAST(C.version AS DECIMAL(3,3))
                {$platform}
            ORDER BY
                Version.version DESC
            LIMIT 1
        ";
        
        $result = $this->query($sql);
        
        if (!empty($result[0]['File']['id'])) {
            return $result[0]['File']['id'];
        }
        else {
            return 0;
        }
    }
}
?>
