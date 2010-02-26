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
 *   Mike Morgan <morgamic@mozilla.com>
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

class Version extends AppModel
{
    var $name = 'Version';
    var $belongsTo_full = array('Addon' =>
                                array('className' => 'Addon'),
                                'License' =>
                                array('className'  => 'License',
                                      'conditions' => '',
                                      'order'      => '',
                                      'foreignKey' => 'license_id'
                                )
                          );

    var $hasMany = array('Review' =>
                         array('className'   => 'Review',
                               'conditions'  => '',
                               'order'       => '',
                               'limit'       => '',
                               'foreignKey'  => 'version_id',
                               'dependent'   => true,
                               'exclusive'   => false,
                               'finderSql'   => ''
                         ),
                       'File' =>
                         array('className'   => 'File',
                               'conditions'  => '',
                               'order'       => '',
                               'limit'       => '',
                               'foreignKey'  => 'version_id',
                               'dependent'   => true,
                               'exclusive'   => false,
                               'finderSql'   => ''
                         )
                  );
    var $hasAndBelongsToMany = array('Application' =>
                                      array('className'  => 'Application',
                                            'joinTable'  => 'applications_versions',
                                            'foreignKey' => 'version_id',
                                            'associationForeignKey'=> 'application_id',
                                            'conditions' => '',
                                            'order'      => '',
                                            'limit'      => '',
                                            'unique'     => false,
                                            'finderSql'  => '',
                                            'deleteQuery'=> ''
                                      )
                               );

    var $translated_fields = array(
                'releasenotes'
            );    

    var $validate = array(
                          'addon_id' => VALID_NUMBER,
                          'version' => VALID_NOT_EMPTY
                    );

    /**
     * Return the id or ids of valid versions by add-on id.
     *
     * @param mixed $id
     * @param array $status non-empty array
     * @return aray|boolean array of valid version_ids on success or false on failure
     */
    function getVersionIdsByAddonId($id, $status = array(STATUS_PUBLIC)) {

        // Implode our status array
        $status_sql = implode(',',$status);

        $id_sql = is_array($id) ? implode(',',$id) : $id;

        $sql = "
            SELECT DISTINCT
                Version.id
            FROM
                versions AS Version
            INNER JOIN
                files AS File ON File.status IN ({$status_sql}) AND File.version_id = Version.id 
            WHERE
                Version.addon_id IN ({$id_sql})
            ORDER BY
                Version.created DESC
        ";

        return $this->query($sql);
    }

    /**
     * Return the latest version id by add-on id.
     *
     * @param int $id
     * @param array $status non-empty array
     * @return int $id of the latest version or 0
     */
    function getVersionByAddonId($id, $status = array(STATUS_PUBLIC), $app_ver = null) {
        if (!is_array($status)) $status = array($status);
        $status_sql = implode(',',$status);

        $sp = null;
        if (isset($app_ver))
            if ($app_ver != 'any')
                $sp = $app_ver;
        else {
            if (preg_match('/OLPC\/0\.([^-]*)-/', env('HTTP_USER_AGENT'), $matches)) {
                if (floatval($matches[1]) <= 4.6)
                    $sp = '0.82';
                else
                    $sp = '0.84';
            } else {
                if (preg_match('/Sugar Labs\/([0-9]+)\.([0-9]+)/', env('HTTP_USER_AGENT'), $matches))
                    $sp = $matches[1].'.'.$matches[2];
                else
                    $sp = SITE_SUGAR_STABLE;
            }
        }

        $sql = "
            SELECT 
                Version.id
            FROM
                versions AS Version
            INNER JOIN
                files AS File ON File.status IN ({$status_sql}) AND File.version_id = Version.id 
            INNER JOIN
                applications_versions A ON A.version_id = Version.id
            INNER JOIN
                appversions as B ON B.id = A.min
            INNER JOIN
                appversions as C ON C.id = A.max
            WHERE
                Version.addon_id = {$id}
            ORDER BY";
        if (isset($sp))
            $sql .= "
                IF({$sp} AND ({$sp} < CAST(B.version AS DECIMAL(3,3)) OR {$sp} > CAST(C.version AS DECIMAL(3,3))), 1, 1000000) + CAST(Version.version AS DECIMAL) DESC";
        else
            $sql .= "
                Version.created DESC";
        $sql .= "
            LIMIT 1
        ";

        $buf = $this->query($sql);

        if (!empty($buf[0]['Version']['id'])) {
            return $buf[0]['Version']['id'];
        }

        return 0;
    }
    
    /**
     * Get the apps compatible with a given addon version
     */
    function getCompatibleApps($id) {
        global $app_shortnames;
        
        $supported_app_ids = implode(',',array_values($app_shortnames));
        $sql = "
            SELECT
                Application.application_id,
                Min_Version.version,
                Max_Version.version
            FROM
                applications_versions AS Application
            INNER JOIN
                appversions AS Min_Version ON (Min_Version.id = Application.`min`)
            INNER JOIN
                appversions AS Max_Version ON (Max_Version.id = Application.`max`)
            WHERE
                Application.version_id = '{$id}'
            AND
                Application.application_id IN ({$supported_app_ids})
            ORDER BY
                (Application.application_id = '".APP_ID."') DESC,
                FIELD(Application.application_id,{$supported_app_ids})
        ";
        return $this->query($sql, true);
    }
    
    /**
     * Gets the apps compatible with a given add-on version in the form of ids
     * instead of the actual version numbers and organizes them by application_id
     */
    function getCompatibleAppIds($version_id) {
        $apps = $this->query("
            SELECT
                `applications_versions`.`application_id`,
                `min`.`id`,
                `max`.`id`
            FROM
                `applications_versions`
            INNER JOIN
                `appversions` AS `min` ON `applications_versions`.`min`=`min`.`id`
            INNER JOIN
                `appversions` AS `max` ON `applications_versions`.`max`=`max`.`id`
            WHERE
                `applications_versions`.`version_id`='{$version_id}'
            ", true);
        
        $list = array();
        
        if (!empty($apps)) {
            foreach ($apps as $app) {
                $list[$app['applications_versions']['application_id']] = array(
                    'min' => $app['min']['id'],
                    'max' => $app['max']['id']
                );
            }
        }
        
        return $list;
    }
    
    /**
     * Adds a compatible application to the specified version
     * @param int $version_id version id
     * @param int $application_id application id
     * @param int $minVersion appversion id (not the actual version string)
     * @param int $maxVersion appversion id (not the actual version string)
     */
    function addCompatibleApp($version_id, $application_id, $minVersion, $maxVersion) {
        $this->execute("
                INSERT INTO
                    applications_versions (
                        application_id,
                        version_id,
                        min,
                        max
                    )
                    VALUES (
                        {$application_id},
                        {$version_id},
                        {$minVersion},
                        {$maxVersion}
                    )
            ");
    }
    
    /**
     * Removes a compatible application from the specified version
     * @param int $version_id version id
     * @param int $application_id application id
     */
    function removeCompatibleApp($version_id, $application_id) {
        $this->execute("
                DELETE FROM
                    applications_versions
                WHERE
                    version_id={$version_id} AND
                    application_id={$application_id}
            ");
    }
    
    /**
     * Updates compatiblity for the specified app and version
     * @param int $version_id version id
     * @param int $application_id application id
     * @param int $minVersion appversion id (not the actual version string)
     * @param int $maxVersion appversion id (not the actual version string)
     */
    function updateCompatibility($version_id, $application_id, $minVersion, $maxVersion) {
        $this->execute("
                UPDATE
                    applications_versions
                SET
                    min={$minVersion},
                    max={$maxVersion}
                WHERE
                    version_id={$version_id} AND
                    application_id={$application_id}
            ");
    }
    
    /**
     * Returns an array of file ids associated with the given version.
     * @param int $version_id version id
     * @return array
     */
    function getFileIDs($version_id) {
        $files = $this->query("SELECT id FROM files WHERE version_id={$version_id}");
        $file_ids = array();
        
        if (!empty($files)) {
            foreach ($files as $file) {
                $file_ids[] = $file['files']['id'];
            }
        }
        
        return $file_ids;
    }

    function getReleaseNotesLocales($version_id) {
        $sql = "   
            SELECT 
                Translations.locale,
                Translations.localized_string
            FROM
                versions AS Version
            INNER JOIN
                translations AS Translations ON Translations.id = Version.releasenotes
            WHERE
                Version.id = {$version_id}
        ";

        $out = array();

        foreach ($this->query($sql) as $i)
            $out[$i['Translations']['locale']] = $i['Translations']['localized_string'];

        return $out;
    }
}
?>
