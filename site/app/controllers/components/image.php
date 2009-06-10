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
 *   Mike Morgan <morgamic@mozilla.com>
 *   Frederic Wenzel <fwenzel@mozilla.com>
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

class ImageComponent extends Object {

    /**
     * Startup method
     */
    function startup(&$controller) {
        $this->controller =& $controller;
        
        if (!defined('NO_MICROTIME'))
            define('NO_MICROTIME', true);
    }
    
    /**
     * Renders the specified image data
     * @param string $data the image data
     * @param string $type the mime type
     */
    function renderImage($data, $mime) {
        header('Content-length: '.strlen($data));
        header("Content-type: {$mime}");
        
        $expires = time() + 60 * 60 * 24 * 365 * 10;
        header("Expires: ".gmstrftime("%a, %d %b %Y %T GMT", $expires));
        
        echo $data;
        exit;
    }
    
    /**
     * Renders the icon for the add-on
     * @param int $addon_id the add-on ID
     */
    function renderAddonIcon($addon_id) {
        $addon = $this->controller->Addon->findById($addon_id, array('icondata', 'icontype'), null, -1);
        
        $this->renderImage($addon['Addon']['icondata'], $addon['Addon']['icontype']);
    }

    /**
     * Renders the icon for a collection
     * @param int $collection_id the add-on ID
     */
    function renderCollectionIcon($collection_id) {
        $collection = $this->controller->Collection->findById($collection_id, array('icondata', 'icontype'), null, -1);

        $this->renderImage($collection['Collection']['icondata'], $collection['Collection']['icontype']);
    }

    /**
     * Renders the preview or thumbnail for an add-on
     * @param int $preview_id the preview id
     * @param string $type type of image -- full or thumbnail
     */
    function renderAddonPreview($preview_id, $type = 'full') {
        if ($type == 'full') {
            $fields = array(
                        'data' => 'filedata',
                        'type' => 'filetype'
                    );
        }
        elseif ($type == 'thumbnail') {
            $fields = array(
                        'data' => 'thumbdata',
                        'type' => 'thumbtype'
                    );
        }
        
        $preview = $this->controller->Preview->findById($preview_id, array($fields['data'], $fields['type']));
        
        $this->renderImage($preview['Preview'][$fields['data']], $preview['Preview'][$fields['type']]);
    }
    
    /**
     * Gets the URL for the icon of the specified add-on
     * @param int $addon_id the add-on id
     * @return string the URL
     */
    function getAddonIconURL($addon_id) {
        $addon = $this->controller->Addon->findById($addon_id, array('addontype_id', 'icontype', 'modified'), null, -1);
        
        if (empty($addon['Addon']['icontype'])) {
            if ($addon['Addon']['addontype_id'] == ADDON_THEME) {
                return "{$this->controller->base}/img/theme.png";
            }
            else {
                return "{$this->controller->base}/img/default_icon.png";
            }
        }
        else {
            return "{$this->controller->base}/en-US/firefox/images/addon_icon/{$addon_id}/".strtotime($addon['Addon']['modified']);
        }
    }
    
    /**
     * Gets the URL for the icon of the specified collection
     * @param int $addon_id the add-on id
     * @return string the URL
     */
    function getCollectionIconURL($collection_id) {
        $collection = $this->controller->Collection->findById(
            $collection_id, array(
                'icontype', 'collection_type', 'uuid', 'modified'
            ), null, -1
        );
        
        if (empty($collection['Collection']['icontype'])) {
            return "{$this->controller->base}/img/collection.png";
        } else {
            return "{$this->controller->base}/en-US/firefox/images/collection_icon/{$collection_id}/".strtotime($collection['Collection']['modified']);
        }
    }
    
    /**
     * Gets the URL for the highlighted preview for an add-on
     * @param int $addon_id add-on ID
     * @param string $type type of preview
     * @return string the url
     */
    function getHighlightedPreviewURL($addon_id, $type = 'thumbnail') {
        // Make sure preview model is loaded
        $this->_loadPreviewModel();
        
        $preview = $this->controller->Preview->find("addon_id={$addon_id}", array('id'), 'highlight DESC, created', null, -1);
        
        return $this->getPreviewURL($preview['Preview']['id'], $type);
    }
    
    /**
     * Gets the URL for the specified preview
     * @param int $preview_id id of the preview
     * @param string $type type of preview -- full or thumbnail
     * @return string the URL
     */
    function getPreviewURL($preview_id, $type = 'thumbnail') {
        // Make sure preview model is loaded
        $this->_loadPreviewModel();
        
        if ($type == 'full') {
            $type = array(
                'type' => 'full',
                'url' => 'p',
                'field' => 'filetype'
            );
        }
        elseif ($type == 'thumbnail') {
            $type = array(
                'type' => 'thumbnail',
                'url' => 't',
                'field' => 'thumbtype'
            );
        }
        
        $preview = $this->controller->Preview->findById($preview_id, array($type['field'], 'modified'));
        
        if (empty($preview['Preview'][$type['field']])) {
            return "{$this->controller->base}/img/no-preview.png";
        }
        else {
            // Always use en-US and firefox and append modified timestamp for
            // best caching results. Serves 150-200 million. Refrigerate after serving.
            return "{$this->controller->base}/en-US/firefox/images/{$type['url']}/{$preview_id}/".strtotime($preview['Preview']['modified']);
        }
    }
    
    /**
     * Loads the Preview model if it's not already loaded
     */
    function _loadPreviewModel() {
        if (!isset($this->controller->Preview)) {
            loadModel('Preview');
            $this->controller->Preview = new Preview();
        }
    }
    
    /**
     * Deprecated; do not use.
     * @deprecated
     */
    function LEGACY_renderAddonPreview($addon, $previewNum) {
        $entry = $this->controller->Preview->findAll(array("Addon_id" => $addon),
                                                     array("filedata", "filetype"),
                                                     "Preview.highlight DESC, Preview.id ASC", 1, $previewNum, null);
        $entry = $entry[0]['Preview'];
        
        $this->renderImage($entry['filedata'], $entry['filetype']);
    }
    
    /**
     * Deprecated; do not use.
     * @deprecated
     */
    function LEGACY_renderAddonThumbnail($addon, $previewNum) {
        $entry = $this->controller->Preview->findAll(array("Addon_id" => $addon),
                                                     array("thumbdata", "thumbtype"),
                                                     "Preview.highlight DESC, Preview.id ASC", 1, $previewNum, null);
        $entry = $entry[0]['Preview'];
        
        $this->renderImage($entry['thumbdata'], $entry['thumbtype']);                                       
    }
}
?>
