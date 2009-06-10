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
 *   Wil Clouser <clouserw@mozilla.com>
 *   Justin Scott <fligtar@gmail.com>
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
require_once('Archive/Zip.php');

class FilesController extends AppController
{
    var $name = 'Files';
    var $uses = array('Version', 'Addon', 'File');
    var $components = array('Amo', 'Filebrowser', 'Diff');
    var $helpers = array('Html', 'Javascript', 'Ajax', 'Listing');

    var $securityLevel = 'low';
    
    /**
    * Require login for all actions
    */
    function beforeFilter() {
        // Disable ACLs because this controller is entirely public.
        $this->SimpleAuth->enabled = false;
        $this->SimpleAcl->enabled = false;

        //beforeFilter() is apparently called before components are initialized. Cake++
        $this->Amo->startup($this);
        
        $this->Amo->checkLoggedIn();
    }
    
   /**
    * Browse the contents of a package
    * @param int $id the file
    */
    function browse($file_id, $review = 0) {
       $this->Amo->clean($file_id);
        
        $this->File->id = $file_id;
        if (!$file = $this->File->read()) {
            $this->flash(_('error_file_notfound'), '/');
            return;
        }
        $this->Addon->id = $file['Version']['addon_id'];
        $addon = $this->Addon->read();

        //Only display add-on if opted in or if the user is a reviewer
        if ($addon['Addon']['viewsource'] != 1 && !$this->SimpleAcl->actionAllowed('Editors', '*', $this->Session->read('User'))) {
            $this->flash(_('error_addon_notviewable'), '/addon/'.$this->Addon->id);
            return;
        }

        $addontype = $addon['Addon']['addontype_id'];
        $startfile = 'install.rdf';
        $path = REPO_PATH.'/'.$this->Addon->id.'/'.$file['File']['filename'];

        if (!file_exists($path)) {
            if ($review == 1) {
                $this->flash(_('error_file_notfound'), '/reviewers/review/'.$this->Addon->id);
            }
            else {
                $this->flash(_('error_file_notfound'), '/addon/'.$this->Addon->id);
            }
            return;
        }

        //If a specific file is requested, show it
        if (!empty($_GET['view'])) {
            $this->_view($path, $_GET['view'], $addontype);
            return;
        }

        $files = array();

        if ($addontype === ADDON_SEARCH) {
            $startfile = $file['File']['filename'];
            $this->Filebrowser->buildContentsArray($startfile, false, false, $files);
        } else {
            $zip = new Archive_Zip($path);
            $contents = $zip->listContent();

            foreach ($contents as $content) {
                $isJar = false;

                if (substr($content['filename'], strrpos($content['filename'], '.')) == '.jar') {
                    $content['folder'] = 1;
                    $isJar = true;
                }

                $this->Filebrowser->buildContentsArray($content['filename'], $content['folder'], false, $files);

                if ($isJar == true) {
                    $filename = substr($content['filename'], strrpos($content['filename'], '/'));
                    $jarfile = $zip->extract(array('extract_as_string' => true, 'by_name' => array($content['filename'])));

                    //write a .jar file with the .jar contents to extract in a new Archive_Zip
                    //I spent a long time trying to figure out an easier way, but no such luck
                    $jarcontents = $this->Filebrowser->getJarContents(REPO_PATH.'/temp'.$filename, $jarfile[0]['content']);

                    foreach ($jarcontents as $jarcontent) {
                        $this->Filebrowser->buildContentsArray($content['filename'].'/'.$jarcontent['filename'], $jarcontent['folder'], false, $files);
                    }

                }
            }
        }

        $this->publish('id', $file_id);
        $this->publish('files', $files);
        $this->publish('version', $file['Version']['id']);
        $this->publish('addon', $this->Addon->id);
        $this->publish('addonname', $addon['Translation']['name']['string']);
        $this->publish('review', $review);
        $this->publish('addontype', $addontype);
        $this->publish('startfile', $startfile);
        $this->render('browse', 'ajax');
    }
    
   /**
    * View a specific file of the package
    * @param string $path the package
    * @param string $file the file
    */
    function _view($path, $file, $addontype) {
        $file = html_entity_decode(urldecode($file), ENT_QUOTES, 'UTF-8');
        $contents = $this->_get_contents($path, $file, $addontype);

        if (is_bool($contents) && $contents == false) {
            $this->flash(_('error_file_notfound'), '/');
            return;
        }
        $this->publish('filename', $file);
        $this->publish('contents', $contents);
        $this->render('view', 'ajax');
    }

    function diff($file_id) {
        $this->Amo->clean($file_id);
        
        $this->File->id = $file_id;
        if (!$file = $this->File->read()) {
            $this->flash(_('error_file_notfound'), '/');
            return;
        }

        $sandbox_file = $file;
        $prev = $this->Version->getVersionIdsByAddonId($file['Version']['addon_id'],
            array(STATUS_PUBLIC));
        
        if (count($prev) == 0) {
            $this->flash(_('error_file_notfound'), '/');
            return;
        }
        $this->Version->id = $prev[0]['Version']['id'];
        $public_file = $this->Version->read();

        $this->Addon->id = $file['Version']['addon_id'];
        $addon = $this->Addon->read();

        //Only display add-on if opted in or if the user is a reviewer
        if ($addon['Addon']['viewsource'] != 1 && !$this->SimpleAcl->actionAllowed('Editors', '*', $this->Session->read('User'))) {
            $this->flash(_('error_addon_notviewable'), '/addon/'.$this->Addon->id);
            return;
        }

        $addontype = $addon['Addon']['addontype_id'];
        $path = REPO_PATH.'/'.$this->Addon->id.'/'.$file['File']['filename'];

        if (!file_exists($path)) {
            $this->flash(_('error_file_notfound'), '/reviewers/review/'.$this->Addon->id);
            return;
        }

        if (!empty($_GET['compare'])) {
            $public_path = REPO_PATH.'/'.$this->Addon->id.'/'.$public_file['File'][0]['filename'];
            $sandbox_path = REPO_PATH.'/'.$this->Addon->id.'/'.$sandbox_file['File']['filename'];
            $this->_compare($sandbox_path, $public_path, html_entity_decode($_GET['compare'], ENT_QUOTES, 'UTF-8'), $addontype);

            return;
        }

        $zip = new Archive_Zip($path);
        $zip_public = new Archive_Zip(REPO_PATH.'/'.$this->Addon->id.'/'.$public_file['File'][0]['filename']);
        $contents = $zip->extract(array('extract_as_string' => true));     
        $contents_public = $zip_public->extract(array('extract_as_string' => true));

        $files = array();
        foreach ($contents as $content) {
            $isJar = false;
            $changed = false;

            /* Checks if this file exists in the recent public version */
            $i = $this->Filebrowser->getFilenameIndex($contents_public, $content['filename']);

            if (substr($content['filename'], strrpos($content['filename'], '.')) == '.jar') {
                $content['folder'] = 1;
                $isJar = true;
            } else if ($content['folder'] == false) {

                if ($i != -1) {
                    $sha1_public = sha1($contents_public[$i]['content']);
                    $sha1 = sha1($content['content']);
                    $changed = ($sha1_public != $sha1);
                } else {
                    $changed = true;
                }
            }

            $this->Filebrowser->buildContentsArray($content['filename'], $content['folder'], $changed, $files);

            if ($isJar == true) {
                $changed = false;
                $filename = substr($content['filename'], strrpos($content['filename'], '/'));

                //write a .jar file with the .jar contents to extract in a new Archive_Zip
                //I spent a long time trying to figure out an easier way, but no such luck
                $jarcontents = $this->Filebrowser->getJarContents(REPO_PATH.'/temp'.$filename, $content['content']);
                $jarcontents_public = $this->Filebrowser->getJarContents(REPO_PATH.'/temp'.$filename . '-public', $contents_public[$i]['content']);

                foreach ($jarcontents as $jarcontent) {
                    $changed = false;
                    $j = $this->Filebrowser->getFilenameIndex($jarcontents_public, $jarcontent['filename']);

                    if ($jarcontent['folder'] == false) {
                        if ($j != -1) {
                            $sha1_public = sha1($jarcontents_public[$j]['content']);
                            $sha1 = sha1($jarcontent['content']);
                            $changed = ($sha1_public != $sha1);
                        } else {
                            $changed = true;
                        }
                    }

                    $this->Filebrowser->buildContentsArray($content['filename'].'/'.$jarcontent['filename'], $jarcontent['folder'], $changed, $files);
                }

            }

            if ($isJar || $content['filename'][ strlen($content['filename']) - 1 ] == '/')
                $content['content'] = '';
        }

        $this->publish('id', $file_id);
        $this->publish('contents', $contents);
        $this->publish('files', $files);
        $this->publish('version', $file['Version']['id']);
        $this->publish('addon', $this->Addon->id);
        $this->publish('review', 1);
        $this->publish('is_diff', true);
        $this->publish('startfile', 'install.rdf');
        $this->render('browse', 'ajax');
    }

    function _compare($sandbox_path, $public_path, $file, $addontype) {
        $sandbox_contents = $this->_get_contents($sandbox_path, $file, $addontype);
        $public_contents = $this->_get_contents($public_path, $file, $addontype);
        $diff = "";

        if ($sandbox_contents === false) {
            $this->flash('Something went horribly wrong! Please file a bug mentioning the addon you were trying to diff', "/");
            return;
        }

        if (!defined("DIFF_PATH")) {
            $diff = xdiff_string_diff($public_contents, $sandbox_contents);
            $diff = split("[\n]", $diff);
        }
        else {
            $public_file = tempnam(REPO_PATH.'/temp/', 'pdiff-');
            $sandbox_file = tempnam(REPO_PATH.'/temp/', 'sdiff-');

            $handler = fopen($public_file, "w");
            fwrite($handler, $public_contents);
            fclose($handler);

            $handler = fopen($sandbox_file, "w");
            fwrite($handler, $sandbox_contents);
            fclose($handler);

            $status = exec(DIFF_PATH . ' -u --ignore-tab-expansion --ignore-blank-lines ' .
                           ' --ignore-space-change '.$public_file. ' ' . $sandbox_file, $diff);

            unlink($public_file);
            unlink($sandbox_file);
        }

        // santizes any html/xul that is present
        $contents = htmlspecialchars($sandbox_contents);
        $contents = split("[\n]", $contents);

        $total_lines = count($contents);
        for ($i = 0; $i < $total_lines; $i++) {
            $l = new Line();
            $l->text = $contents[$i];
            $contents[$i] = $l;
        }

        // Parses the changes
        $changes = array();
        for ($i = 0; $i < count($diff); $i++) {
            if (strncmp($diff[$i], "@@", 2) == 0) {
                $section = array();
                array_push($section, $diff[$i]);
                for ($j = $i+1; $j < count($diff); $j++) {
                    if (strncmp($diff[$j], "@@", 2) == 0) {
                        break;
                    }
                    else {
                        array_push($section, htmlspecialchars($diff[$j]));
                    }
                }
                array_push($changes, $section);
            }
        }

        foreach ($changes as $item) {
            $tmp = $item;
            $change = $this->Diff->parse_diff($tmp, '+');
            $this->Diff->apply_change($contents, $change);
        }

        // When we apply new lines, it skews the array index which is used to
        // track line numbers. So we have to keep track of how many lines we
        // added
        $offset = 0;
        foreach ($changes as $item) {
            $tmp = $item;
            $change = $this->Diff->parse_diff($tmp, '-');
            $this->Diff->apply_change($contents, $change, $offset);
        }

        $linenum = 1;
        $shift = "  ";
        $output = array();

        foreach ($contents as $line) {
            $linen = "$linenum";
            $space = "  ";

            // Do not write line numbers for code removed
            if ($line->changed && $line->symbol == '-') {
                $linen = str_repeat(' ', strlen($linen));
            }
			else {
                $linenum++;
            }

            // code that hasn't been touched needs the extra space. This
            // just fixes alignment issues
            if ($line->changed) {
                $space = "";
            }

            // Remove excess whitespace. We'll put in our own newline
            $line->text = rtrim($line->text);

            if ($linen == 10 || $linen == 100 || $linen === 1000) {
                // alignment fix
                $shift = substr($shift, 0, -1);
            }
            array_push($output, "<b>$linen</b> $shift$space$line->text\n");
        }

        $this->publish('contents', $output, false);
        $this->publish('filename', $file);
        $this->render('view', 'ajax');
    }

    /* $path must refer to a file that exists */
    function _get_contents($path, $file, $addontype) {
        $this->Amo->clean($path);

        // Search engine. Safe to output directly
        if ($addontype === ADDON_SEARCH) {
            // We have to make sure that the file in $path and $file match.
            // Otherwise we reproduce bug 461253
            if (substr($path, -(strlen($file))) == $file) {
                return file_get_contents($path);
            } else {
                return false;
            }
        }

        if (preg_match("/^(.+\.jar)\/(.+)$/is", $file, $matches)) {
            $file = $matches[1];
            $jarfile = $matches[2];
            $jarfilename = substr($file, strrpos($file, '/'));
        }
        $zip = new Archive_Zip($path);
        $extraction = $zip->extract(array('extract_as_string' => true, 'by_name' => array($file)));

        if (is_array($extraction) && count($extraction) > 0) {
            $contents = (string)$extraction[0]['content'];
        }
        else {
            return false;
        }

        if (!empty($jarfile)) {
            $jarcontents = $this->Filebrowser->getJarContents(REPO_PATH.'/temp'.$jarfilename, $contents, $jarfile);
            if (is_array($jarcontents) && count($jarcontents) > 0) {
                $contents = (string)$jarcontents[0]['content'];
            }
            else {
                $contents = false;
            }
        }

        return $contents;
    }
}
?>
