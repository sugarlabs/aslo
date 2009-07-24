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
 * Contributor(s):
 * Scott McCammon <smccammon@mozilla.com>
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
class MarkdownTest extends UnitTestCase {
    //Setup the Markdown Component
    function setUp() {
        $this->controller =& new AppController();
        loadComponent('Markdown');
        $this->Markdown =& new MarkdownComponent();
        $this->Markdown->startup($this->controller);
    }
	
    function testMarkdown() {
        $text = "__testo__";
        $html = $this->Markdown->html($text);

        $this->assertPattern('#<p><strong>testo</strong></p>#', $html, "markdown is functional");
    }

    function testSanitize() {
        $text = '<strong>testo</strong>';
        $html = $this->Markdown->html($text);

        $this->assertPattern('#&lt;strong&gt;testo&lt;/strong&gt;#', $html, "markdown is sanitizing angle brackets");

    }

    function testFencedCode() {
        $text = "~~~ {.python}\nimport antigravity\n~~~";
        $html = $this->Markdown->html($text);

        $this->assertPattern('#<pre class="brush:python">\s*import antigravity\s*</pre>#s', $html, "fenced code is being formatted");
        $this->assertNoPattern('/~~~|[{}]|\.python/', $html, "no lingering fenced code markers");
    }
}
?>
