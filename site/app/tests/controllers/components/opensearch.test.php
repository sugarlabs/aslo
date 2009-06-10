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
 * Portions created by the Initial Developer are Copyright (C) 2006
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *  Gavin Sharp <gavin@gavinsharp.com> (Original author)
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
class OpensearchTest extends UnitTestCase {

  function setUp() {
    loadComponent('opensearch');
    $this->Os =& new OpensearchComponent();
  }

  function testSherlockConversion() {
    $data = '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearchdescription/1.1/">
             <ShortName>OpenSearch Test</ShortName>
             <Alias>OST</Alias>
             <Description>This is a test plugin.</Description>
             <InputEncoding>UTF-8</InputEncoding>
             <Image width="16" height="16">data:image/x-icon;base64,Qk02AwAAAAAAADYAAAAoAAAAEAAAABAAAAABABgAAAAAAAADAAAAAAAAAAAAAAAAAAAAAAAA////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////AAAA////////AAAAAAAA////////AAAAAAAA////////AAAA////AAAA////////AAAA////AAAA////////////AAAA////////AAAA////AAAA////AAAA////////AAAA////AAAA////////////AAAA////////AAAA////AAAAAAAAAAAA////////AAAA////////AAAAAAAA////////AAAAAAAA////////AAAA////AAAA////////////////////////////////////////////////////////////////////////////AAAA////////////AAAA////AAAA////////AAAA////////////////////////AAAAAAAA////////AAAAAAAAAAAA////AAAA////AAAA////////////////////AAAA////////////AAAA////AAAA////AAAA////AAAA////////////////////AAAAAAAAAAAA////////AAAA////////AAAA////AAAA////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////</Image>
             <Url type="text/html" method="get" template="http://test.template.url/search/">
               <Param name="q" value="{searchTerms}"/>
               <Param name="sourceid" value="firefox"/>
             </Url>
             <UpdateUrl>http://fake.update.url/</UpdateUrl>
             <UpdateInterval>7</UpdateInterval>
             <IconUpdateUrl>http://fake.icon.update.url/</IconUpdateUrl>
             <UnknownTag>This tag shouldn\'t be included in the output.</UnknownTag>
             </OpenSearchDescription>';
    $expected = '<SEARCH
  name="OpenSearch Test"
  action="http://test.template.url/search/"
  method="GET"
  description="This is a test plugin."
  queryCharset="UTF-8"
>
<input name="q" user>
<input name="sourceid" value="firefox">
</SEARCH>

<BROWSER
  update="http://fake.update.url/"
  updateIcon="http://fake.icon.update.url/"
  updateCheckDays="7"
>
';

    $engine = $this->Os->parse($data);
    $src = $engine->toSherlock();
    $this->assertEqual($src, $expected, 'Basic plugin was serialized correctly');

    $data = '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearchdescription/1.1/">
             <ShortName>OpenSearch Test</ShortName>
             <Url type="text/html" method="get" template="http://test.template.url/search/{searchTerms}"/>
             </OpenSearchDescription>';
    $expected = '<SEARCH
  name="OpenSearch Test"
  action="http://test.template.url/search/"
  method="GET"
>
<input user>
</SEARCH>
';

    $engine = $this->Os->parse($data);
    $src = $engine->toSherlock();
    $this->assertEqual($src, $expected, '"Append-to-tempate" plugin was serialized correctly');

    $data = '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearchdescription/1.1/">
             <ShortName>OpenSearch Test</ShortName>
             <Url type="text/html" method="get" template="http://test.template.url/search/{searchTerms}/"/>
             </OpenSearchDescription>';
    $expected = NULL;

    $engine = $this->Os->parse($data);
    $src = $engine->toSherlock();
    $this->assertEqual($src, $expected, 'Unsupported template');

    $data = '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearchdescription/1.1/">
             <ShortName>OpenSearch Test</ShortName>
             <Url type="text/html" method="get" template="http://test.template.url/search/"/>
             </OpenSearchDescription>';
    $expected = NULL;

    $engine = $this->Os->parse($data);
    $src = $engine->toSherlock();
    $this->assertEqual($src, $expected, 'No user input');
  }

  function testSimple() {
    $data = '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearchdescription/1.1/">
             <ShortName>OpenSearch Test</ShortName>
             <Alias>OST</Alias>
             <Description>This is a test plugin.</Description>
             <InputEncoding>UTF-8</InputEncoding>
             <Image width="16" height="16">data:image/x-icon;base64,Qk02AwAAAAAAADYAAAAoAAAAEAAAABAAAAABABgAAAAAAAADAAAAAAAAAAAAAAAAAAAAAAAA////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////AAAA////////AAAAAAAA////////AAAAAAAA////////AAAA////AAAA////////AAAA////AAAA////////////AAAA////////AAAA////AAAA////AAAA////////AAAA////AAAA////////////AAAA////////AAAA////AAAAAAAAAAAA////////AAAA////////AAAAAAAA////////AAAAAAAA////////AAAA////AAAA////////////////////////////////////////////////////////////////////////////AAAA////////////AAAA////AAAA////////AAAA////////////////////////AAAAAAAA////////AAAAAAAAAAAA////AAAA////AAAA////////////////////AAAA////////////AAAA////AAAA////AAAA////AAAA////////////////////AAAAAAAAAAAA////////AAAA////////AAAA////AAAA////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////</Image>
             <Url type="text/html" method="get" template="http://test.template.url/search/">
               <Param name="q" value="{searchTerms}"/>
               <Param name="sourceid" value="firefox"/>
             </Url>
             <UpdateUrl>http://fake.update.url/</UpdateUrl>
             <UpdateInterval>7</UpdateInterval>
             <IconUpdateUrl>http://fake.icon.update.url/</IconUpdateUrl>
             <UnknownTag>This tag shouldn\'t be included in the output.</UnknownTag>
             </OpenSearchDescription>';

    $engine = $this->Os->parse($data);
    $this->assertEqual($engine->name,           'OpenSearch Test', 'Correct name');
    $this->assertEqual($engine->alias,          'OST', 'Correct alias');
    $this->assertEqual($engine->description,    'This is a test plugin.', 'Correct description');
    $this->assertEqual($engine->inputEncoding,  'UTF-8', 'Correct inputencoding');
    $this->assertEqual($engine->imageUrl,       'data:image/x-icon;base64,Qk02AwAAAAAAADYAAAAoAAAAEAAAABAAAAABABgAAAAAAAADAAAAAAAAAAAAAAAAAAAAAAAA////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////AAAA////////AAAAAAAA////////AAAAAAAA////////AAAA////AAAA////////AAAA////AAAA////////////AAAA////////AAAA////AAAA////AAAA////////AAAA////AAAA////////////AAAA////////AAAA////AAAAAAAAAAAA////////AAAA////////AAAAAAAA////////AAAAAAAA////////AAAA////AAAA////////////////////////////////////////////////////////////////////////////AAAA////////////AAAA////AAAA////////AAAA////////////////////////AAAAAAAA////////AAAAAAAAAAAA////AAAA////AAAA////////////////////AAAA////////////AAAA////AAAA////AAAA////AAAA////////////////////AAAAAAAAAAAA////////AAAA////////AAAA////AAAA////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////', 'Correct image');
    $this->assertEqual($engine->updateUrl,      'http://fake.update.url/', 'Correct updateUrl');
    $this->assertEqual($engine->iconUpdateUrl,  'http://fake.icon.update.url/', 'Correct iconUpdateUrl');
    $this->assertEqual($engine->updateInterval, '7', 'Correct updateInterval');

    $this->assertEqual($engine->urls['text/html']->method,   'GET', 'Correct URL Method');
    $this->assertEqual($engine->urls['text/html']->template, 'http://test.template.url/search/', 'Correct URL Template');
  }

  function testSubstitution() {
    $data = '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearchdescription/1.1/">
             <ShortName>OpenSearch Test</ShortName>
             <InputEncoding>Shift_JIS</InputEncoding>
             <Url type="text/html" method="get" template="http://test.template.url/search/">
               <Param name="inputEncoding" value="{inputEncoding}"/>
               <Param name="outputEncoding" value="{outputEncoding}"/>
               <Param name="language" value="{language}"/>
               <Param name="startPage" value="{startPage}"/>
               <Param name="startIndex" value="{startPage}"/>
               <Param name="count" value="{count}"/>
               <Param name="optional" value="{count?}"/>
               <Param name="extended" value="{inputEncoding}appended"/>
             </Url>
             </OpenSearchDescription>';

    $engine = $this->Os->parse($data);

    $params = $engine->getParams('text/html');
    $this->assertTrue($params, "Got params!");

    $expected =  array(array('inputEncoding', 'Shift_JIS'),
                       array('outputEncoding', 'UTF-8'),
                       array('language', '*'),
                       array('startPage', '1'),
                       array('startIndex', '1'),
                       array('count', '20'),
                       array('extended', 'Shift_JISappended'));
    $this->assertEqual($params, $expected, "Parameters were correctly replaced");
  }

  function testURLs() {
    $data = '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.0/">
             <ShortName>OpenSearch Test</ShortName>
             <Url type="text/html" method="get" template="http://test.template.url/search/">
               <Param name="q" value="{searchTerms}"/>
               <Param name="sourceid" value="firefox"/>
             </Url>
             <Url type="application/x-suggestions+json" method="get" template="http://test.template.url/suggest/">
               <Param name="q" value="{searchTerms}"/>
               <Param name="q2" value="{searchTerms}2"/>
               <Param name="dupe" value="first"/>
               <Param name="dupe" value="second"/>
             </Url>
             <Url type="x/unknown" method="get" template="http://test.template.url/unknown/">
               <Param name="q" value="{searchTerms}"/>
               <Param name="sourceid" value="firefox-unknown"/>
             </Url>
             </OpenSearchDescription>';

    $engine = $this->Os->parse($data);
    $this->assertEqual($engine->urls['text/html']->method,   'GET', 'Correct HTML URL Method');
    $this->assertEqual($engine->urls['text/html']->template, 'http://test.template.url/search/', 'Correct HTML URL Template');
    $this->assertEqual($engine->urls['text/html']->params[0], array('q', '{searchTerms}'), 'Correct HTML URL Param0');
    $this->assertEqual($engine->urls['text/html']->params[1], array('sourceid', 'firefox'), 'Correct HTML URL Param1');
    $this->assertEqual($engine->urls['application/x-suggestions+json']->method,   'GET', 'Correct Suggest URL Method');
    $this->assertEqual($engine->urls['application/x-suggestions+json']->template, 'http://test.template.url/suggest/', 'Correct Suggest URL Template');
    $this->assertEqual($engine->urls['application/x-suggestions+json']->params[0], array('q', '{searchTerms}'), 'Correct Suggest URL Param0');
    $this->assertEqual($engine->urls['application/x-suggestions+json']->params[1], array('q2', '{searchTerms}2'), 'Correct Suggest URL Param1');
    $this->assertEqual($engine->urls['application/x-suggestions+json']->params[2], array('dupe', 'first'), 'Correct Suggest URL Param2');
    $this->assertEqual($engine->urls['application/x-suggestions+json']->params[3], array('dupe', 'second'), 'Correct Suggest URL Param3');
    $this->assertEqual(count($engine->urls), 2, 'No other URLs');
    $this->assertEqual(count($engine->urls['text/html']->params), 2, 'Correct number of params for text/html URL');
    $this->assertEqual(count($engine->urls['application/x-suggestions+json']->params), 4, 'Correct number of params for suggest URL');

    $data = '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.0/">
             <ShortName>OpenSearch Test</ShortName>
             <Url type="text/html" method="get" template="http://test.template.url/search1/">
               <Param name="q" value="{searchTerms}-1"/>
               <Param name="sourceid" value="firefox-1"/>
             </Url>
             <Url type="text/html" method="post" template="http://test.template.url/search2/">
               <Param name="q" value="{searchTerms}-2"/>
               <Param name="sourceid" value="firefox-2"/>
             </Url>
             </OpenSearchDescription>';
    $engine = $this->Os->parse($data);
    $this->assertEqual($engine->urls['text/html']->method,   'POST',                              'Second method took precedence');
    $this->assertEqual($engine->urls['text/html']->template, 'http://test.template.url/search2/', 'Second URL took precedence');
    $this->assertEqual($engine->urls['text/html']->params[0], array('q', '{searchTerms}-2'),    'Second param0 took precedence');
    $this->assertEqual($engine->urls['text/html']->params[1], array('sourceid', 'firefox-2'),   'Second param1 took precedence');
  }

  function testOverwrite() {
    $data = '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.0/">
             <ShortName>OpenSearch Test</ShortName>
             <ShortName>Second OpenSearch Test</ShortName>
             <Url type="text/html" method="get" template="http://test.template.url/search/">
               <Param name="q" value="{searchTerms}"/>
               <Param name="sourceid" value="firefox"/>
             </Url>
             </OpenSearchDescription>';

    $engine = $this->Os->parse($data);
    $this->assertEqual($engine->name,     'Second OpenSearch Test', 'Second element correctly overwrote the first');
  }

  function testInvalid() {
    $data = '<OpenSearchDescription>
             <ShortName>OpenSearch Test</ShortName>
             <Url type="text/html" method="get" template="http://test.template.url/search/">
               <Param name="q" value="{searchTerms}"/>
               <Param name="sourceid" value="firefox"/>
             </Url>
             </OpenSearchDescription>';

    $engine = $this->Os->parse($data);
    $this->assertFalse($engine, "Invalid namespace");

    $data = '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.0/">
             <Url type="text/html" method="get" template="http://test.template.url/search/">
               <Param name="q" value="{searchTerms}"/>
               <Param name="sourceid" value="firefox"/>
             </Url>
             </OpenSearchDescription>';

    $engine = $this->Os->parse($data);
    $this->assertFalse($engine, "No name");

    $data = '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.0/">
             <ShortName>OpenSearch Test</ShortName>
             </OpenSearchDescription>';

    $engine = $this->Os->parse($data);
    $this->assertFalse($engine, "No Url");

    $data = '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.0/">
             <ShortName>OpenSearch Test</ShortName>
             <Url type="application/x-suggestions+json" method="get" template="http://test.template.url/suggest/">
               <Param name="q" value="{searchTerms}"/>
               <Param name="sourceid" value="firefox-suggest"/>
             </Url>
             </OpenSearchDescription>';

    $engine = $this->Os->parse($data);
    $this->assertFalse($engine, "No text/html Url");

    $data = '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.0/">
             <ShortName>OpenSearch Test</ShortName>
             <Url type="text/html" method="invalid" template="http://test.template.url/search/"/>
             </OpenSearchDescription>';

    $engine = $this->Os->parse($data);
    $this->assertFalse($engine, "Invalid method");

    $data = '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.0/">
             <ShortName>OpenSearch Test</ShortName>
             <Url type="text/html" method="get" template="mailto://test.template.url/search/"/>
             </OpenSearchDescription>';

    $engine = $this->Os->parse($data);
    $this->assertFalse($engine, "Invalid template (non-HTTP)");

    $data = '<OpenSearchDescription/>
             <OpenSearchDescription>
             <ShortName>OpenSearch Test</ShortName>
             <Url type="text/html" method="get" template="http://test.template.url/search/">
               <Param name="q" value="{searchTerms}"/>
               <Param name="sourceid" value="firefox"/>
             </Url>
             </OpenSearchDescription>';
    $engine = $this->Os->parse($data);
    $this->assertFalse($engine, "Multiple root elements");
  }

  function testUrlAttrHandling() {
    $data = '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.0/">
             <ShortName>OpenSearch Test</ShortName>
             <Url type="TEXT/HTML" template="http://test.template.url/Search/"/>
             </OpenSearchDescription>';

    $engine = $this->Os->parse($data);
    $this->assertTrue(isset($engine->urls['text/html']), 'Type attribute is normalized');
    $this->assertEqual($engine->urls['text/html']->method,   'GET', 'Missing method falls back to GET');
    $this->assertEqual($engine->urls['text/html']->template, 'http://test.template.url/Search/',  'Template is case-sensitive');
  }

  function testImage() {
    $data = '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.0/">
             <ShortName>OpenSearch Test</ShortName>
             <Image>data:image/x-icon;base64,Qk02AwAAAAAAADYAAAAoAAAAEAAAABAAAAABABgAAAAAAAADAAAAAAAAAAAAAAAAAAAAAAAA////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////AAAA////////AAAAAAAA////////AAAAAAAA////////AAAA////AAAA////////AAAA////AAAA////////////AAAA////////AAAA////AAAA////AAAA////////AAAA////AAAA////////////AAAA////////AAAA////AAAAAAAAAAAA////////AAAA////////AAAAAAAA////////AAAAAAAA////////AAAA////AAAA////////////////////////////////////////////////////////////////////////////AAAA////////////AAAA////AAAA////////AAAA////////////////////////AAAAAAAA////////AAAAAAAAAAAA////AAAA////AAAA////////////////////AAAA////////////AAAA////AAAA////AAAA////AAAA////////////////////AAAAAAAAAAAA////////AAAA////////AAAA////AAAA////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////</Image>
             <Url type="TEXT/HTML" template="http://test.template.url/Search/"/>
             </OpenSearchDescription>';

    $engine = $this->Os->parse($data);
    $this->assertFalse($engine->imageUrl, 'Image with invalid dimensions');

    $data = '<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.0/">
             <ShortName>OpenSearch Test</ShortName>
             <Image height="16" width="16">data:image/x-icon;base64,Qk02AwAAAAAAADYAAAAoAAAAEAAAABAAAAABABgAAAAAAAADAAAAAAAAAAAAAAAAAAAAAAAA////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////AAAA////////AAAAAAAA////////AAAAAAAA////////AAAA////AAAA////////AAAA////AAAA////////////AAAA////////AAAA////AAAA////AAAA////////AAAA////AAAA////////////AAAA////////AAAA////AAAAAAAAAAAA////////AAAA////////AAAAAAAA////////AAAAAAAA////////AAAA////AAAA////////////////////////////////////////////////////////////////////////////AAAA////////////AAAA////AAAA////////AAAA////////////////////////AAAAAAAA////////AAAAAAAAAAAA////AAAA////AAAA////////////////////AAAA////////////AAAA////AAAA////AAAA////AAAA////////////////////AAAAAAAAAAAA////////AAAA////////AAAA////AAAA////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////</Image>
             <Url type="text/html" template="http://test.template.url/Search/"/>
             </OpenSearchDescription>';

    $engine = $this->Os->parse($data);
    $this->assertEqual(base64_encode($engine->getImage()), 'Qk02AwAAAAAAADYAAAAoAAAAEAAAABAAAAABABgAAAAAAAADAAAAAAAAAAAAAAAAAAAAAAAA////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////AAAA////////AAAAAAAA////////AAAAAAAA////////AAAA////AAAA////////AAAA////AAAA////////////AAAA////////AAAA////AAAA////AAAA////////AAAA////AAAA////////////AAAA////////AAAA////AAAAAAAAAAAA////////AAAA////////AAAAAAAA////////AAAAAAAA////////AAAA////AAAA////////////////////////////////////////////////////////////////////////////AAAA////////////AAAA////AAAA////////AAAA////////////////////////AAAAAAAA////////AAAAAAAAAAAA////AAAA////AAAA////////////////////AAAA////////////AAAA////AAAA////AAAA////AAAA////////////////////AAAAAAAAAAAA////////AAAA////////AAAA////AAAA////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////', 'getImage');
  }
}
?>
