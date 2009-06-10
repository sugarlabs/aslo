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
class SrcTest extends UnitTestCase {
	var $data = '# Mozilla/Google plug-in by amitp+mozilla@google.com

<search 
   name="Google"
   description="Google Search"
   method="GET"
   action="http://www.google.com/search"
   queryCharset="utf-8"
>

<input name="q" user>
<inputnext name="start" factor="10">
<inputprev>
<input name="ie" value="utf-8">
<input name="oe" value="utf-8">

<interpret 
    browserResultType="result" 
    charset = "UTF-8"
    resultListStart="<!--a-->" 
    resultListEnd="<!--z-->" 
    resultItemStart="<!--m-->" 
    resultItemEnd="<!--n-->"
>
</search>

<browser
    update="https://addons.mozilla.org/searchplugins/updates/google.src"
    updateIcon="https://addons.mozilla.org/searchplugins/updates/google.gif"
    updateCheckDays="0"
>
';
	
	//Setup the Src Component
	function setUp() {
		loadComponent('Src');
		$this->Src =& new SrcComponent();
	}

   /**
    * Test .src parser
    */
	function testSrc() {
		$name = $this->Src->parse($this->data);
		$this->assertEqual($name, 'Google Search');
	}

}
?>