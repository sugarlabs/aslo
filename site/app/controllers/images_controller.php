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
 *   Justin Scott <fligtar@mozilla.com>
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
class ImagesController extends AppController
{
    var $name = 'Images';
    var $uses = array('Addon', 'Collection', 'Preview');
    var $components = array('Image');
    var $autoRender = false;

    var $securityLevel = 'low';

    function beforeFilter() {
        // Disable ACLs because this controller is entirely public.
        $this->SimpleAuth->enabled = false;
        $this->SimpleAcl->enabled = false;
    }
    
    /**
     * Renders the icon for the specified add-on
     * @param int $addon_id ID of the add-on
     * @param int $timestamp timestamp of last update (optional)
     * @param bool $cache whether to memcache or not (optional)
     */
    function addon_icon($addon_id, $timestamp = '', $cache = true) {
        if ($cache !== true) {
            $this->Addon->caching = false;
        }
        
        if (!empty($addon_id)) {
            $this->Image->renderAddonIcon($addon_id);
        }
    }
    
    /**
     * Renders the icon for the specified add-on
     * @param int $addon_id ID of the add-on
     * @param int $timestamp timestamp of last update (optional)
     * @param bool $cache whether to memcache or not (optional)
     */
    function collection_icon($collection_id, $timestamp = '', $cache = true) {
        if ($cache !== true) {
            $this->Collection->caching = false;
        }

        if (!empty($collection_id)) {
            $this->Image->renderCollectionIcon($collection_id);
        }
    }

    /**
     * Renders the full-size preview with the ID specified
     * @param int $preview_id ID of the preview to render
     * @param int $timestamp timestamp of last update (optional)
     * @param bool $cache whether to memcache or not (optional)
     */
    function p($preview_id, $timestamp = '', $cache = true) {
        if ($cache !== true) {
            $this->Preview->caching = false;
        }
        
        if (!empty($preview_id)) {
            $this->Image->renderAddonPreview($preview_id, 'full');
        }
    }
    
    /**
     * Renders the thumbnail preview with the ID specified
     * @param int $preview_id ID of the thumbnail to render
     * @param int $timestamp timestamp of last update (optional)
     * @param bool $cache whether to memcache or not (optional)
     */
    function t($preview_id, $timestamp = '', $cache = true) {
        if ($cache !== true) {
            $this->Preview->caching = false;
        }
        
        if (!empty($preview_id)) {
            $this->Image->renderAddonPreview($preview_id, 'thumbnail');
        }
    }
    
    /**
     * Supports the deprecated URL structure using incremental preview numbers.
     * *** Do not create any more URLs that use this; use t() instead
     * @deprecated
     */
    function addon_preview($addon_id, $preview_num) {
        $this->Image->LEGACY_renderAddonThumbnail($addon_id, $preview_num);
    }
    
    /**
     * Supports the deprecated URL structure using incremental preview numbers.
     * *** Do not create any more URLs that use this; use p() instead
     * @deprecated
     */
    function preview($addon_id, $preview_num) {
        $this->Image->LEGACY_renderAddonPreview($addon_id, $preview_num);
    }
    
   /**
    * Return a localized image, as stored in app/locale/images/name.png
    * @param string $name The image name
    */
    function localized_image($name) {
        $image_path = APP.'locale'.DS.'%s'.DS.'images'.DS.$name;

        $lang = str_replace('-', '_', LANG);
        if (file_exists(sprintf($image_path, $lang))) {
            $image = sprintf($image_path, $lang);
        } elseif (file_exists(sprintf($image_path, 'en_US'))) {
            $image = sprintf($image_path, 'en_US');
        } else {
            return ''; // no luck!
        }
        $imageData = file_get_contents($image);
        
        $this->publish('imagedata', $imageData, false);
        $this->publish('mimetype', 'image/png');
        $this->render('show', 'ajax');
    }
}
?>
