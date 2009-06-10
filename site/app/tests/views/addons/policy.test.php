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

class PolicyTest extends WebTestHelper {

    function PolicyTest() {
        $this->WebTestCase("Views->Addons->Privacy Policy/EULA Tests");
    }

    function setUp() {
        $this->id = 7;//$_GET['id'];
        $this->actionPath = $this->actionPath(""); 
        $model =& new Addon();
        $model->caching = false;
        $this->data = $model->find("Addon.id=$this->id", null , null , 2);
    }

    function testPrivacyPolicy() {
        //get display page and test policy link 
        $this->getAction("/addon/" . $this->id);
        
        $this->wantedPattern = "@<a href=\"{$this->actionPath}/addons/policy/0/{$this->id}\" >This add-on has a privacy policy.</a>@";
        $this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));
        
        //get policy page and test page contents
        $this->getAction("/addons/policy/0/" . $this->id);
        $privacypolicy = nl2br($this->data['Translation']['privacypolicy']['string']);
        $privacypolicy = substr($privacypolicy, -22);
        $this->wantedPattern = "^{$privacypolicy}^";
        $this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));
        
        //add-on name
        $this->wantedPattern = "@<h3 class=\"name\">[\s]*{$this->data['Translation']['name']['string']}[\s]*</h3>@";
        $this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));
        
        //author user data
        foreach ($this->data['User'] as $user) {
            $username = $user['nickname'];
            $usertitle = _('addons_display_author_title');
            $this->wantedPattern = "@<h4 class=\"author\"> by <a href=\"{$this->actionPath}/user/{$user['id']}\"  class=\"profileLink\">{$username}</a></h4>@";
            $this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));
        }
    }
    
    function testEULA() {
        
        //get EULA page
        $this->getAction("/addons/policy/0/{$this->id}/{$this->data['Version'][0]['File'][0]['id']}");
        
        //name and version
        $this->wantedPattern = "@<h3 class=\"name\">[\s]*{$this->data['Translation']['name']['string']} *{$this->data['Version'][0]['version']}[\s]*</h3>@";
        $this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));
        
        //get policy page and test page contents
        $eula = nl2br($this->data['Translation']['eula']['string']);
        $eula = substr($eula, 0, 22);
        $this->wantedPattern = "^{$eula}^";
        $this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));
        
        //author user data @TODO: Fix multiple author stuff when we add that
        foreach ($this->data['User'] as $user) {
            $username = $user['nickname'];
            $usertitle = _('addons_display_author_title');
            $this->wantedPattern = "@<h4 class=\"author\"> by <a href=\"{$this->actionPath}/user/{$user['id']}\"  class=\"profileLink\">{$username}</a></h4>@";
            $this->assertWantedPattern($this->wantedPattern, htmlentities($this->wantedPattern));
        }
        
    }
}
?>
