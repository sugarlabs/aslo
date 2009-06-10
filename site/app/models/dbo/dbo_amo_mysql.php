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
 *   Frederic Wenzel <fwenzel@mozilla.com> (Original Author)
 *   Justin Scott <fligtar@mozilla.com>
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
 * This datasource completely inherits Cake's MySQL datasource, except
 * it doesn't filter out our dynamic translations in the __filterResults()
 * function, like Cake does by default.
 *
 * It also provides support for shadow databases not working.
 */


/**
 * Include Cake's DBO model for MySQL, which we are extending here
 */
uses('model'.DS.'dbo'.DS.'dbo_mysql');

class DboAmoMysql extends DboMysql {
    /**
     * Let Cake do its filtering, but strip out translations before and
     * re-add them afterwards.
     * Note: Because translated fields do not have an associated table name
     * (they are renamed in the query) Cake stores them as follows:
     * array( 'Addon' => (addon data),
     *        0 => (translation data)
     *      )
     */
    function __filterResults(&$results, &$model, $filtered = array()) {
        // if there's no translations, we don't change anything
        // (also: if there's no results for the queried model, this is
        // probably a custom query. Don't do anything either)
        if (empty($results) || !isset($results[0][$model->name]) || !isset($results[0][0]))
            return parent::__filterResults($results, $model, $filtered);

        // Used to figure out direction of language and/or fallback
        global $rtl_languages;

        // rename the missing table name to "Translation"
        foreach ($results as $key => $item) {
            $results[$key]['Translation'] = $results[$key][0];
            unset($results[$key][0]);
        }

        $trans_filtered = in_array('Translation', $filtered); // were Translations handled before?
        
        // run Cake's filter magic
        if (!$trans_filtered) $filtered[] = 'Translation'; // make sure Cake doesn't touch our translations
        $filtering = parent::__filterResults($results, $model, $filtered);
        
        // if we haven't done this before, format translations uniformly
        if (!$trans_filtered) {
            // strip the raw translations from the results array
            $translations = array();
            foreach ($results as $_key => $_val) {
                $translations[$_key] = $results[$_key]['Translation'];
                unset($results[$_key]['Translation']);
            }
            // re-add translations in the desired format.
            foreach ($results as $_key => $_result) {
                $_temp = array();
                foreach ($model->translated_fields as $field) {
                    if (array_key_exists($field, $translations[$_key])) { // only translated fields that were actually selected
                        $_temp[$field]['string'] = $translations[$_key][$field];
                        $_temp[$field]['locale'] = $translations[$_key]["{$field}_locale"];
                        $_temp[$field]['textdir'] = in_array($_temp[$field]['locale'], $rtl_languages) ? 'rtl' : 'ltr';
                        if (empty($_temp[$field]['locale']) || $model->getLang() == $_temp[$field]['locale']) {
                            $_temp[$field]['locale_html'] = '';
                        } else {
                            $_temp[$field]['locale_html'] = ' lang="'.$_temp[$field]['locale'].'" dir="'.$_temp[$field]['textdir'].'" ';
                        }
                    }
                }
                $results[$_key]['Translation'] = $_temp;
            }

            // now add translations to the "filtered models" array we'll return,
            // so we don't touch them again when we come back.
            $filtering[] = 'Translation';
        }
        return $filtering;
    }

/**
 * Attempts to connect to a database. If a connection can't be established
 * and we were attempting to connect to a shadow database, we'll try the
 * remaining shadow databases before disabling shadow databases and forcing
 * queries to use the master.
 *
 * @return boolean True if the database could be connected, else false
 */
    function connect() {
        if (parent::connect()) {
            // Connected to database.
            return true;
        }
        elseif ($this->config['shadow']) {
            /* Couldn't connect to shadow.
              We can't redefine the SHADOW constants, so let's just try
              to find something we *can* connect to, regardless of weight */
            global $shadow_databases;
            if (!empty($shadow_databases)) {
                // Save known bad info
                $bad = $this->config;
                
                foreach ($shadow_databases as $shadow_database) {
                    // Make sure this isn't the known-bad one
                    if ($bad['host'] == $shadow_database['DB_HOST'] &&
                        $bad['database'] == $shadow_database['DB_NAME']) {
                        continue;
                    }
                    
                    $this->config['host'] = $shadow_database['DB_HOST'].':'.$shadow_database['DB_PORT'];
                    $this->config['login'] = $shadow_database['DB_USER'];
                    $this->config['password'] = $shadow_database['DB_PASS'];
                    $this->config['database'] = $shadow_database['DB_NAME'];
                    
                    if (parent::connect()) {
                        // DB worked - let's get out of here!
                        return true;
                    }
                    // else - no luck. try next shadow db
                }
            }
            /* If we're still here, none of the other shadow db's worked.
              We'll disable queries to the SHADOW db, which means they'll
              pull from the master unless that's down too */
            define('SHADOW_DISABLED', true);
        }
        // else, we couldn't connect to master db. Umm...run away!
        // mail('morgamic@gmail.com', 'Hey guess what!!!!');
        
        return false;
    }
    
    /**
     * Outputs a pretty version of the Cake query log that is hidden by default.
     */
    function showLog($sorted = false) {
        // If using CLI, use normal showLog method for handling
        if (php_sapi_name() == 'cli') {
            parent::showLog($sorted);
            return;
        }
        
        // Sort log if requested
        $logs = $sorted ? sortByKey($this->_queriesLog, 'took', 'desc', SORT_NUMERIC) : $this->_queriesLog;
        
        // Create unique id for this log
        $id = 'querylog-'.str_replace(array(' ', '.'), '', microtime());
        
        // CSS
        echo '<style type="text/css">';
        echo "div.querylog { border: 1px solid black; margin: 10px 0; }";
        echo "table.querylog-summary { width: 100%; }";
        echo "table.querylog-summary:hover { background-color: #DDDDFF; cursor: pointer; }";
        echo "#{$id} { width: 100%; border-top: 1px solid black; }";
        echo "#{$id} tr.alt td { background-color: #EEEEEE; }";
        echo "#{$id} th, #{$id} td { text-align: left; margin: 0; padding: 2px;}";
        echo "#{$id} th { font-weight: bold; }";
        echo '</style>';
        
        // Summary table
        echo '<div class="querylog">';
        echo '<table class="querylog-summary" onclick="var table = document.getElementById(\''.$id.'\'); if (table.style.display == \'none\') { table.style.display = \'\'; } else { table.style.display = \'none\'; }"><tbody><tr>';
        echo '<td style="font-weight: bold;">Query Log</td>';
        echo "<td style=\"text-align: center;\">{$this->_queriesCnt} ".($this->_queriesCnt > 1 ? 'queries' : 'query')." took {$this->_queriesTime} ms</td>";
        echo '<td style="text-align: center;">Query distribution: ';
        if (!empty($this->_configQueriesCnt)) {
            foreach ($this->_configQueriesCnt as $config => $count) {
                echo $config.': '.round(($count / $this->_queriesCnt) * 100, 2).'%';
            }
        }
        echo '</td>';
        echo '<td style="text-align: right;">Click to Toggle Queries</td>';
        echo '</tr></tbody></table>';
        
        // Log table
        echo '<table id="'.$id.'" style="display: none;"><thead>';
        echo '<tr>';
        echo '<th>#</th>';
        echo '<th>Query</th>';
        echo '<th>Error</th>';
        echo '<th>Affected</th>';
        echo '<th>Num. Rows</th>';
        echo '<th>Took (ms)</th>';
        echo '<th>DB config</th>';
        echo '</tr>';
        echo '</thead><tbody>';
        
        foreach ($logs as $k => $log) {
            echo '<tr'.($k % 2 == 0 ? ' class="alt"' : '').'>';
            echo '<td>'.($k + 1).'</td>';
            echo '<td>'.h($log['query']).'</td>';
            echo "<td>{$log['error']}</td>";
            echo "<td>{$log['affected']}</td>";
            echo "<td>{$log['numRows']}</td>";
            echo "<td>{$log['took']}</td>";
            echo "<td>{$log['db']}</td>";
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        echo '</div>';
    }
    
    /**
     * Logs given SQL query.
     * Modified Cake default to add support for configKeyName
     */
    function logQuery($sql) {
        $this->_queriesCnt++;
        $this->_queriesTime += $this->took;
        $this->_queriesLog[] = array(
                        'query'         => $sql,
                        'error'		=> $this->error,
                        'affected'	=> $this->affected,
                        'numRows'	=> $this->numRows,
                        'took'		=> $this->took,
                        'db'            => $this->configKeyName
                    );
        
        if (!empty($this->_configQueriesCnt[$this->configKeyName])) {
            $this->_configQueriesCnt[$this->configKeyName]++;
        }
        else {
            $this->_configQueriesCnt[$this->configKeyName] = 1;
        }
        
        if ($this->error) {
            return false;
        }
    }
}
?>
