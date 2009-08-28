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
            $this->flash(___('File not found!'), '/');
            return;
        }
        $this->Addon->id = $file['Version']['addon_id'];
        $addon = $this->Addon->read();

        // Compatability redirect
        $compat_apps = array();
        if (!empty($addon['Version'])) {
            foreach ($addon['Version'] as $version) {
                $compat_apps = array_merge($this->Version->getCompatibleApps($version['id']), $compat_apps);
            }
        }
        $redirect = $this->Amo->_app_redirect($compat_apps);
        if ($redirect) {
            $this->redirect("{$redirect}/files/browse/{$file_id}/{$review}", null, true, false);
            return;
        }
        
        $isDeveloper = false;
        $user = $this->Session->read('User');
        if (!empty($addon['User'])) {
            foreach ($addon['User'] as $userInfo) {
                if ($user['id'] == $userInfo['id']) {
                    $isDeveloper = true;
                    break;
                }
            }
        }

        //Only display add-on if: 1) opted in 2) user owns this plugin 3) user is an editor/admin
        if ($addon['Addon']['viewsource'] != 1 && !$isDeveloper &&            
            !$this->SimpleAcl->actionAllowed('Editors', '*', $user)) {
            $this->flash(___('This add-on is not viewable here.'), '/addon/'.$this->Addon->id);
            return;
        }

        $addontype = $addon['Addon']['addontype_id'];
        $startfile = 'install.rdf';
        $path = REPO_PATH.'/'.$this->Addon->id.'/'.$file['File']['filename'];

        if (!file_exists($path)) {
            if ($review == 1) {
                // Redirect to the review version
                $this->flash(___('File not found!'), '/editors/review/'.$file['File']['version_id']);
            }
            else {
                $this->flash(___('File not found!'), '/addon/'.$this->Addon->id);
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
            $this->_unwrap($path, $files);
        }

		//If a specific start file is requested, override it
		//We use the query string since the file could be in a directory
		if (!empty($_GET['start'])) {
			$startfile = $_GET['start'];
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

    /* Recursively go through a zippy
     * @param $path path to .xpi or .jar file
     * @param $files empty array. Get's populated by buildContentsArray()
     * @param $basename Leave empty. For internal use to track where we are.
     */
    function _unwrap($path, &$files, $basename = '') {
        $zip = new Archive_Zip($path);
        $contents = $zip->listContent();

        foreach ($contents as $content) {
            $isJar = false;
            $filename = $content['filename'];
            $ext = substr($filename, strrpos($filename, '.'));
            $basefilename = ($basename ? $basename . '/' . $filename : $filename);

            if ($ext == '.jar' || $ext == '.xpi') {
                $content['folder'] = 1;
                $isJar = true;
            }

            $this->Filebrowser->buildContentsArray($basefilename, $content['folder'], false, $files);

            if ($isJar == true) {
                $jarfilename = substr($filename, strrpos($filename, '/'));
                $jarfile = $zip->extract(array('extract_as_string' => true, 'by_name' => array($filename)));
                $jarpath = REPO_PATH. '/temp' . $jarfilename;

                file_put_contents($jarpath, $jarfile[0]['content']);
                $this->_unwrap($jarpath, $files, $basefilename);
                unlink($jarpath);
            }
        }
    }

    /* Like _unwrap, but kept seperate since this one is much more intensive.
     * Yeah, duplicate code sucks. sorry.
     */
    function _unwrapDiff($sandbox_path, $public_path, &$files, $basename = '') {
        $zip_sandbox = new Archive_Zip($sandbox_path);
        $zip_public = new Archive_Zip($public_path);

        $public_contents = $zip_public->extract(array('extract_as_string' => true));
        $sandbox_contents = $zip_sandbox->extract(array('extract_as_string' => true));

        foreach ($sandbox_contents as $content) {
            $isJar = false;
            $changed = false;
            $filename = $content['filename'];
            $ext = substr($filename, strrpos($filename, '.'));
            $basefilename = ($basename ? $basename . '/' . $filename : $filename);

            // Checks if this file exists in the recent public version
            $i = $this->Filebrowser->getFilenameIndex($public_contents, $filename);

            if ($ext == '.jar' || $ext == '.xpi') {
                $content['folder'] = 1;
                $isJar = true;
            } else if ($content['folder'] == false) {
                if ($i != -1) {
                    $sha1_public = sha1($public_contents[$i]['content']);
                    $sha1 = sha1($content['content']);
                    $changed = ($sha1_public != $sha1);
                } else {
                    $changed = true;
                }
            }

            $this->Filebrowser->buildContentsArray($basefilename, $content['folder'], $changed, $files);

            if ($isJar == true && $i != -1) {
                $jarfilename = substr($filename, strrpos($filename, '/'));
                $sandbox_jarpath = tempnam(REPO_PATH. '/temp/', $jarfilename);
                $public_jarpath = tempnam(REPO_PATH. '/temp/', $jarfilename);

                file_put_contents($sandbox_jarpath, $content['content']);
                file_put_contents($public_jarpath, $public_contents[$i]['content']);
                $this->_unwrapDiff($sandbox_jarpath, $public_jarpath, $files, $basefilename);

                unlink($sandbox_jarpath);
                unlink($public_jarpath);
            }
        }
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
            $this->flash(___('File not found!'), '/');
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
            $this->flash(___('File not found!'), '/');
            return;
        }

        $sandbox_file = $file;
        $prev = $this->Version->getVersionIdsByAddonId($file['Version']['addon_id'],
            array(STATUS_PUBLIC));
        
        if (count($prev) == 0) {
            $this->flash(___('File not found!'), '/');
            return;
        }
        $this->Version->id = $prev[0]['Version']['id'];
        $public_file = $this->Version->read();

        $this->Addon->id = $file['Version']['addon_id'];
        $addon = $this->Addon->read();

        //Only display add-on if opted in or if the user is a reviewer
        if ($addon['Addon']['viewsource'] != 1 && !$this->SimpleAcl->actionAllowed('Editors', '*', $this->Session->read('User'))) {
            $this->flash(___('This add-on is not viewable here.'), '/addon/'.$this->Addon->id);
            return;
        }

        $addontype = $addon['Addon']['addontype_id'];
        $path = REPO_PATH.'/'.$this->Addon->id.'/'.$file['File']['filename'];

        if (!file_exists($path)) {
            $this->flash(___('File not found!'), '/reviewers/review/'.$this->Addon->id);
            return;
        }

        if (!empty($_GET['compare'])) {
            $public_path = REPO_PATH.'/'.$this->Addon->id.'/'.$public_file['File'][0]['filename'];
            $sandbox_path = REPO_PATH.'/'.$this->Addon->id.'/'.$sandbox_file['File']['filename'];
            $this->_compare($sandbox_path, $public_path, html_entity_decode($_GET['compare'], ENT_QUOTES, 'UTF-8'), $addontype);

            return;
        }

        $files = array();
        $this->_unwrapDiff($path, REPO_PATH.'/'.$this->Addon->id.'/'.$public_file['File'][0]['filename'], $files);

        $this->publish('id', $file_id);
        $this->publish('contents', '');
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
    function &_get_contents($path, $file, $addontype) {
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

        if (preg_match("/^(.+?\.(jar|xpi))\/(.+)$/is", $file, $matches)) {
            $file = $matches[1];
            $jarfile = $matches[3];
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
            $jarpath = REPO_PATH . '/temp' . $jarfilename;
            file_put_contents($jarpath, $contents);

            $contents = &$this->_get_contents($jarpath, $jarfile, $addontype);

            unlink($jarpath);
        }

        return $contents;
    }
}
?>
