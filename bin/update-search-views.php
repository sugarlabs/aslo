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
 *   Chris Pollett (cpollett@gmail.com)
 *   Mike Morgan <morgamic@mozilla.com>
 *   Justin Scott <fligtar@gmail.com>
 *
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
 * The script should be run as a cron job to periodically update the text_search_view
 *
 * This script should not ever be accessed over HTTP, and instead run via cron.
 * Only sysadmins should be responsible for operating this script.
 *
 * @package amo
 * @subpackage bin
 */


// Before doing anything, test to see if we are calling this from the command
// line.  If this is being called from the web, HTTP environment variables will
// be automatically set by Apache.  If these are found, exit immediately.
if (isset($_SERVER['HTTP_HOST'])) {
    exit;
}

// If we get here, we're on the command line, which means we can continue.
// Include config file
require_once('../site/app/config/config.php');
require_once('../site/app/config/constants.php');

global $valid_status;

/*  
   First, we set up an array of sql commands we will execute to update our two views that are used by search
   In testing it seemed faster to delete the table completely and rebuild it rather than incrementally maintain it
*/
$sql_commands = array();
$sql_commands[] = "BEGIN";
$sql_commands[] = "DELETE FROM `text_search_summary`";

$sql_commands[] = "INSERT INTO `text_search_summary`
                   SELECT  a.id AS id, 
                       `tr_name`.locale AS locale,  
                       a.addontype_id AS addontype, 
                       a.status AS status, 
                       a.inactive AS inactive, 
                       a.averagerating AS averagerating, 
                       a.weeklydownloads AS weeklydownloads,
                       `tr_name`.localized_string AS name, 
                       `tr_summary`.localized_string AS summary, 
                       `tr_description`.localized_string AS description,
                       tags
                   FROM addons AS a 
                   LEFT JOIN translations AS `tr_name` ON (`tr_name`.id = a.`name`) 
                   LEFT JOIN translations AS `tr_summary` ON (`tr_summary`.id = a.`summary` AND  `tr_name`.locale = `tr_summary`.locale) 
                   LEFT JOIN translations AS `tr_description` 
	                       ON (`tr_description`.id = a.`description` AND  `tr_name`.locale = `tr_description`.locale) 
				   LEFT JOIN 	                       		
				   ( select uta.addon_id, GROUP_CONCAT(distinct  replace(t.tag_text, ' ', '') SEPARATOR ',') as tags
						from users_tags_addons uta, tags t
						where uta.tag_id = t.id and t.blacklisted = 0 
						group by uta.addon_id ) addon_tags ON ( a.id = addon_tags.addon_id)
		           WHERE `tr_name`.locale IS NOT NULL AND (
                       `tr_name`.localized_string IS NOT NULL 
                       OR `tr_summary`.localized_string IS NOT NULL 
                       OR `tr_description`.localized_string IS NOT NULL
                   ) 
                   ORDER BY a.id ASC, locale DESC;";

$sql_commands[] = "DELETE FROM `versions_summary`";

$sql_commands[] = "INSERT INTO `versions_summary`
                       SELECT DISTINCT v.addon_id, v.id, av.application_id, v.created, v.modified, av.min, av.max
                       FROM (SELECT DISTINCT v.addon_id AS addon_id, MAX(v.created) AS created
                             FROM versions AS v
                             INNER JOIN files AS f ON (f.version_id = v.id AND f.status IN (".implode(',',$valid_status)."))
                             GROUP BY v.addon_id) AS mrv
                            NATURAL JOIN versions AS v
                            LEFT JOIN applications_versions AS av
                       ON (av.version_id = v.id )";

$sql_commands[] = "DELETE FROM `collections_search_summary`";

$sql_commands[] = "INSERT INTO `collections_search_summary`
                   SELECT  `c`.`id` AS `id`, 
                       `tr_name`.`locale` AS `locale`,  
                       `tr_name`.`localized_string` AS `name`, 
                       `tr_description`.`localized_string` AS `description`
                   FROM `collections` AS `c` 
                   LEFT JOIN `translations` AS `tr_name` ON (`tr_name`.`id` = `c`.`name`) 
                   LEFT JOIN `translations` AS `tr_description` 
	                       ON (`tr_description`.`id` = `c`.`description` AND  `tr_name`.`locale` = `tr_description`.`locale`)
                   WHERE `tr_name`.`locale` IS NOT NULL AND (
                       `tr_name`.`localized_string` IS NOT NULL 
                       OR `tr_description`.`localized_string` IS NOT NULL
                   ) 
                   ORDER BY `c`.`id` ASC, `locale` DESC";

$sql_commands[] = "COMMIT";

// Connect to our database and execute the command list above.

$write = mysql_connect(DB_HOST, DB_USER, DB_PASS) or die('Could not connect: ' . mysql_error());
mysql_select_db(DB_NAME, $write) or die('Could not select database '.DB_NAME);

foreach($sql_commands as $sql_command) {
    if(!mysql_query($sql_command)) {
        mysql_query("ROLLBACK");
        die("The update '$sql_command' failed: ".mysql_error());
    }
} 																																					

mysql_close();
exit;
?>
