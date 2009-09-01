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

class ListingHelper extends Helper
{
    var $helpers = array('Html');

    function listFiles($files, $id, $is_diff = false, $n = 0) {
        $output = '';
        $modified = '';

        if (array_key_exists('changed', $files) && $files['changed'])
            $modified = ' modified';

        if (!empty($files)) {             
            if ($files['dir'] == 1 || $n == 0) {
                if ($n != 0) {
                    $class = str_replace('/', '_', $files['path']);
                    $output .= '<li class="directory'.$modified.'">'.$this->Html->link($files['filename'], 'javascript: void(0);', array("onClick" => "toggleNode('{$class}');")).'</li>';
                    $output .= '<ul class="'.$class.'" style="display: none;">';
                }

                foreach ($files as $file => $item) {
                    if ($file != 'path' && $file != 'dir' && $file != 'filename' && $file != 'changed') {
                        $output .= $this->listFiles($item, $id, $is_diff, $n+1);
                    }
                }

                if ($n != 0) {
                    $output .= '</ul>';
                }
            }
            elseif ($is_diff) {
                $files['path'] = urlencode($files['path']);
                $output .= '<li class="file'.$modified.'">'.$this->Html->link($files['filename'], 'javascript: void(0);', array('onClick' => "viewFile('".$this->Html->url('/files/diff')."/{$id}/?compare={$files['path']}');")).'</li>';
            }
            else {
                $files['path'] = urlencode($files['path']);
                $output .= '<li class="file">'.$this->Html->link($files['filename'], 'javascript: void(0);', array('onClick' => "viewFile('".$this->Html->url('/files/browse')."/{$id}/?view={$files['path']}');")).'</li>';
            }
        }

        if ($n == 0) {
            return $this->output($output);
        }
        else {
            return $output;
        }
    }
    
    function json($json, $tab = 0, $encode = false) {
        echo "{\n";
        if (!empty($json)) {
            $prefix = ''; // no prefix for first item
            foreach ($json as $key => $value) {
                echo $prefix;
                // subsequent items get a prefix
                if (empty($prefix))
                    $prefix = ",\n";

                echo str_repeat("\t", $tab).'"'.addslashes($key).'": ';
                if (is_array($value))
                    $this->json($value, $tab + 1, $encode);
                else
                    if (is_numeric($value) && strpos($value, '.') === false)
                        echo $value;
                    elseif ($encode)
                        echo '"'.urlencode($value).'"';
                    else
                        echo "'".preg_replace('/\n/', '\n', addslashes($value))."'";
            }
            if (!empty($prefix))
                echo "\n"; // no comma after the last item! (if any)
        }
        echo str_repeat("\t", $tab).'}';
    }
    
}
?>
