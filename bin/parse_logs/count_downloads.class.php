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
 * Portions created by the Initial Developer are Copyright (C) 2007
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Justin Scott <fligtar@mozilla.com> (Original Author)
 *   Andrei Hajdukewycz <sancus@off.net>
 *   Wil Clouser <wclouser@mozilla.com>
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
 * Counts downloads from access logs and stores in database
 */
class Count_Downloads {
    var $countedIPs = array(); // list of counted IP addresses
    var $db;
    var $totalSkipped = array('blacklist' => 0, 'SJ' => 0, 'NL' => 0, 'CN' => 0);
    var $totalCounted = 0;
    var $counts = array( 'totdown' => array(), 'perday' => array(), 'collections_and_addons' => array() );
    
   /**
    * Initializes download count parser
    */
    function Count_Downloads(&$db) {
        $this->db =& $db;
    }
    
   /**
    * Increment relevant properties of each add-on for each date
    *
                //@TODO XXX: Currently the collection ID isn't passed back to us so we have no way of knowing what collection an add-on came from.
                // Convienently, we only have one collection ID at the moment so this is hardcoded to 1 in this function.  This needs to be fixed 
                // before bandwagon starts!
    *
    * @param array $details details from the parsed log line
    */
    function count($details) {
        // Clean up IP array
        $this->cleanIPs($details['unixtime']);
        
        // Make sure IP is not in blacklist and is not coming from Mozilla .nl
        if (isset($this->countedIPs[$details['ip']])) {
            $this->totalSkipped['blacklist']++;
            outputIfVerbose("[DownloadCounter] IP ({$details['ip']}) in blacklist; skipped");
            return;
        }

        $_addon_ids = array();

        if ($details['type'] == 'collections' && !empty($details['addon_ids'])) {
            if (!empty($this->counts['collections_and_addons'][1]['total'])) {
                $this->counts['collections_and_addons'][1]['total'] += 1;
            } else {
                $this->counts['collections_and_addons'][1]['total'] = 1;
            }
            $_addon_ids = $details['addon_ids'];
        } elseif (!empty($details['fileid'])) {
            $addon_id_result = $this->db->read("SELECT versions.addon_id FROM files LEFT JOIN versions ON files.version_id = versions.id WHERE files.id={$details['fileid']}");
            $addon_id = mysql_fetch_array($addon_id_result);

            if (!empty($addon_id['addon_id'])) {
                $_addon_ids = array($addon_id['addon_id']);
            }
        }

        if (empty($_addon_ids)) {
            outputIfVerbose("[DownloadCounter] No add-on ids found in path: {$details['path']}");
        }

        // Increment stats for each downloaded add-on
        foreach ($_addon_ids as $_addon_id) {

            // If it's a collection it gets counted in that group too
            if ($details['type'] == 'collections') {
                // This array is in the format:
                //     [collections_and_addons] => Array
                //      (
                //          [$collection_id] => Array
                //              (
                //                  [total] => 4
                //                  [addon_ids] => Array
                //                      (
                //                          [$addon_id] =>  $total_downloads_of_this_addon_from_that_collection
                //                            ...
                //                      )
                //              )
                //      )
                if (!empty($this->counts['collections_and_addons'][1]['addon_ids'][$_addon_id])) {
                    $this->counts['collections_and_addons'][1]['addon_ids'][$_addon_id] += 1;
                } else {
                    $this->counts['collections_and_addons'][1]['addon_ids'][$_addon_id] = 1;
                }
            }
        
            // update total downloads
            if (!empty($this->counts['totdown'][$_addon_id])) {
                $this->counts['totdown'][$_addon_id] += 1;
            } else {
                $this->counts['totdown'][$_addon_id] = 1;
            }

            // update per-day
            if ( !isset( $this->counts['perday'][$_addon_id] ) ) {
                $this->counts['perday'][$_addon_id] = array();
            }

            if (!empty($this->counts['perday'][$_addon_id][date('Y-m-d', $details['unixtime'])])) {
                $this->counts['perday'][$_addon_id][date('Y-m-d', $details['unixtime'])] += 1;
            } else {
                $this->counts['perday'][$_addon_id][date('Y-m-d', $details['unixtime'])] = 1;
            }

            $this->totalCounted++;
            outputIfVerbose("[DownloadCounter] Updated count for add-on id: {$_addon_id}");
        }
            
        // Blacklist IP from being counted again for 30 seconds
        $this->countedIPs[$details['ip']] = $details['unixtime']; 
    }

   /**
    * Cleans array of blacklisted IPs of IPs that downloaded over 30 seconds ago
    */
    function cleanIPs($currentTime) {
        foreach ($this->countedIPs as $ip => $downloadTime) {
            if (($currentTime - $downloadTime) > 30)
                unset($this->countedIPs[$ip]);
        }
    }

   /**
    * Callback for after a logfile is parsed.
    */
    function logfileParsedCallback() {
        // update total downloads
        foreach ( $this->counts['totdown'] as $id => $ct ) {
            $this->db->write("UPDATE addons SET totaldownloads=totaldownloads+{$ct} WHERE id={$id}");
        }

        // now the dailies
        foreach ( $this->counts['perday'] as $id => $days ) {
            foreach ( $days as $day => $ct ) {
                    $this->db->write("INSERT INTO download_counts(addon_id, count, date) VALUES({$id}, {$ct}, '{$day}') 
                                      ON DUPLICATE KEY UPDATE count=count+{$ct}");
            }
        }

        // now the collections
        foreach ( $this->counts['collections_and_addons'] as $collection_id => $details) {
            $this->db->write("UPDATE collections SET downloads = downloads + {$details['total']} WHERE id={$collection_id} LIMIT 1");
            foreach ($details['addon_ids'] as $addon_id => $count ) {
                $this->db->write("UPDATE addons_collections SET downloads = downloads + {$count} WHERE addon_id={$addon_id} AND collection_id={$collection_id} LIMIT 1");
            }
        }

        // Garbage collection on counts array after the log file is parsed and
        // database is updated.
        $this->counts['totdown'] = array();
        $this->counts['perday'] = array();
        $this->counts['collections_and_addons'] = array();
    }
}

?>
