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
 *   Mike Shaver <shaver@mozilla.org> (Original Author)
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

class SearchResultTest extends WebTestHelper {
     
    function SearchResultTest() {
        $this->WebTestCase("Views->Search->Results Display Tests");
    }
    
    function testBasicResults() {
        $this->getAction("/search/?q=the");
        $this->assertNoUnwantedPattern("/".___('No results found.')."/", "No results found.");
        $this->assertFieldById('query', 'the', "previous query pre-populated");
    }
    
    function testUTF8Exploit() {
        $this->getAction("/search?q=%C0%22%20onmouseover=alert(/xss/.source)%20\&cat=all");
        $this->assertWantedPattern('/&quot; onmouseover=alert&#40;\/xss\/\.source&#41; \\\\/i', 'UTF-8 exploit not found');
    }
}
 
?>
