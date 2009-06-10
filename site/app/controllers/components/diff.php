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
 * Mozilla Corporation.
 * Portions created by the Initial Developer are Copyright (C) 2008
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 * Cesar Oliveira <a.sacred.line@gmail.com>
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
class DiffComponent extends Object {

    /**
     * Parses a unified diff output
     * @param array $_text an entire section of a unified diff (between @@ lines)
     * @param char  $_check a '+' or '-' denoting whether we're looking for lines
     *                      added or removed
     * @return 
     */
    function parse_diff($_text, $_check) {
        $start = 0;     // Start of the diff
        $length = 0;        // number of lines to recurse
        $changes = array(); // all the changes

        $regs = array();

        if (preg_match("/^@@ ([\+\-])([0-9]+)(?:,([0-9]+))? [\+\-]([0-9]+)(?:,([0-9]+))? @@$/", array_shift($_text), $regs) == false) {
            return;
        }
        
        $start = $regs[4];

        $length = count($_text);
        $instance = new Changes();

        /* We don't count removed lines when looking at start of a change. For
         * example, we have this :
         * - foo
         * + bar
         * bar starts at line 1, not line 2.
         */
        $minus = 0;

        for ($i = 0; $i < $length; $i++) {
            $line = $_text[$i];

            // empty line? EOF?
            if (strlen($line) == 0) {
                if ($instance->length > 0) {
                    array_push($changes, $instance);
                    $instance = new Changes();
                }
                continue;
            }

            if ($_check == '-' && $_check == $line[0]) {
                if ($instance->length == 0) {
                    $instance->line = $start + $i - $minus;
                    $instance->symbol = $line[0];
                    $instance->length++;
                }
                array_push($instance->oldline, substr($line, 1));
            }
            elseif ($_check == '+' && $_check == $line[0]) {
                if ($instance->length == 0) {
                    $instance->line = $start + $i - $minus;
                    $instance->symbol = $line[0];
                }
                $instance->length++;
            }
            else {
                if ($instance->length > 0) {
                    array_push($changes, $instance);
                    $instance = new Changes();
                }
            }

            if ($line[0] == '-')
                $minus++;
        }

        if ($instance->length > 0) {
            array_push($changes, $instance);
            $instance = new Changes();
        }

        return $changes;
    }

    /**
     * Appends or Replaces text
     * @param array &$_text Array of Line objects
     * @param array $_change Array of Change objects
     * @param int &$offset how many lines to skip due to previous additions
     */
    function apply_change(&$_text, $_change, &$offset = 0) {
        $index = 0;
        
        // $i is the change we are on
        for ($i = 0; $i < count($_change); $i++) {
            $lines = $_change[$i];
            // $j is the line within the change
            for ($j = 0; $j < $lines->length; $j++) {
                $linenum = $lines->line - 1;
                $line = $_text[$linenum+$j+$offset];
                $color = "green";
                
                if (strlen(ltrim($line->text)) == 0) {
                    continue;
                }

                if ($lines->symbol == '-') {
                    $add = $lines->oldline;
                    array_splice($_text, $linenum + $j + $offset, 0, $add);
                    // $k is the counter for the old lines we
                    // removed from the previous version
                    for ($k = 0; $k < count($add); $k++) {
                        $l = new Line();
                        $l->changed = true;
                        $l->symbol = '-';
                        $l->text = sprintf("%s <span class='diff-remove'>%s</span>\n", "-", rtrim($add[$k], "\r\n"));

                        $_text[$linenum+$j+$k+$offset] = $l;
                    }
                    $offset += count($add);
                } else {
                    $l = new Line();
                    $l->symbol = '+';
                    $l->changed = true;
                    $l->text = sprintf("%s <span class='diff-add'>%s</span>\n", $lines->symbol, rtrim($line->text, "\r\n"));
                    $_text[$linenum+$j] = $l;
                }
            }
        }
    }

}

class Changes {
    public $line = 0;
    public $length = 0;
    public $symbol;
    public $oldline = array(); // only for code removed
}

// This object is created for every line of text in the file.
// It was either this, or some funk string changes
class Line {
    public $text = '';
    public $symbol = '';
    public $changed = false;
}
?>
