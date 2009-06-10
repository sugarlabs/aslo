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
 *   Wil Clouser <wclouser@mozilla.com> (Original Author)
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

class ImageViewTest extends WebTestHelper {
    
    
    function ImageTest() {
        $this->WebTestCase("Views->Image->Show Tests");
    }
    
    function imageVerify($url, $mimetype) {
        $this->get($url);
        $this->assertResponse('200');
        $imageinfo = getimagesize($url);
        $imagetype = image_type_to_mime_type($imageinfo[2]);
        $this->assertMime($mimetype);
    }
    function testAddonIconValid() {
        $model =& new Addon();
        $model->caching = false;
        $id=7;//$_GET['id'];
        
        $data = $model->find("Addon.id=$id", array('icontype'), null , 2);
        $mimetype = $data['Addon']['icontype'];
        $url = $this->actionURI("/images/addon_icon/" . $id);
        
        $this->imageVerify($url, $mimetype);
    }
    function testPreviewValid() {
        $model =& new Addon();
        $model->bindFully();
        $model->caching = false;
        $id=7;//$_GET['id'];
        
        $data = $model->find("Addon.id=$id", null , null , 2);
        $mimetype = $data['Preview'][0]['thumbtype'];
        $url = $this->actionURI("/images/addon_preview/$id/1");
        
        $this->imageVerify($url, $mimetype);
    }

    function xfail_testPreviewLargeValid() {
        $model =& new Addon();
        $model->bindFully();
        $model->caching = false;
        $id=7;//$_GET['id'];
        
        $data = $model->find("Addon.id=$id", null , null , 2);
        $filename = $data['Preview'][0]['filename'];
        $mimetype = $data['Preview'][0]['filetype'];
        $url = $this->actionURI("/images/preview/" . $filename);
        
        $this->imageVerify($filename, $url, $mimetype);
    }
    function xfail_testApplicationIconValid() {
        $model =& new Application();
        $model->caching = false;
        $id=7;//$_GET['id'];
        
        $data = $model->find("Application.id=$id", null , null , 2);
        $filename = $data['Application']['icon'];
        $mimetype = $data['Application']['icontype'];
        $url = $this->actionURI("/images/application_icon/" . $filename);
        
        $this->imageVerify($filename, $url, $mimetype);
    }
    function xfail_testPlatformIconValid() {
        $model =& new Platform();
        $model->caching = false;
        $id=7;//$_GET['id'];
        
        $data = $model->find("Platform.id=$id", null , null , 2);
        $filename = $data['Platform']['icon'];
        $mimetype = $data['Platform']['icontype'];
        $url = $this->actionURI("/images/platform_icon/" . $filename);
        
        $this->imageVerify($filename, $url, $mimetype);
    }
    
}
?>
