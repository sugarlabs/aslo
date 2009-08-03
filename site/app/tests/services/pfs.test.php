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
 * The Initial Developer of the Original Code is The Mozilla Foundation.
 *
 * Portions created by the Initial Developer are Copyright (C) 2006
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *      Mike Morgan <morgamic@mozilla.com> (Original Author)
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

/**
 * This class tests the Plugin Finder Service (PFS).
 *
 * PFS serves RDF data to requesting clients via the pfs.datasource.url pref.
 *
 * Incoming URL example (wrapped for readability):
 *      https://pfs.mozilla.org/PluginFinderService.php?
 *          mimetype=application/x-shockwave-flash
 *          &appID={ec8030f7-c20a-464f-9b0e-13a3a9e97384}
 *          &appVersion=2007020307
 *          &clientOS=Windows%20XP
 *          &chromeLocale=en-US
 */
 
class  PluginFinderServiceTest extends UnitTestCase {
	
    var $_args;

    function PluginFinderServiceTest() {
        $this->UnitTestCase('Services->PluginFinderService');
    }

    /**
     * Sets up default vars and required modules.
     */
	function setUp() {

        // Load RDF component.
        loadComponent('Rdf');

        $this->_args = array(
            'mimetype'=>'application/x-shockwave-flash',
            'appID'=>'{ec8030f7-c20a-464f-9b0e-13a3a9e97384}',
            'appVersion'=>'2007020307',
            'clientOS'=>'Windows%20XP',
            'chromeLocale'=>'en-US'
        );
	}
	
    /**
     * Make sure there are no PHP errors.
     */
	function testNoErrors() {
		$this->assertNoErrors();
	}

    /**
     * No plugin data exists for maligned values.  Assert in each of
     * these cases that the resulting RDF is parsable.
     */
    function testNoPluginDataForBadInputs() {

        foreach ($this->_args as $key=>$val) {

            // Store args in a temp array so we can fudge it up.
            $_tmp = $this->_args;

            // We want to mess up a value on purpose.
            $_tmp[$key] = '/?:LIKJPO(&O(*&_)(*_)(!*@#K';

            // Get the XML for a malformed URI.
            $_xml = $this->_getXml($_tmp);

            // The resulting document should be parsable.
            $this->assertTrue($this->_isParsable($_xml), 'Document still parsable when '.$key.'='.$_tmp[$key]);
        }
    }

    /**
     * No plugin data exists for missing values.  Assert in each of
     * these cases that the resulting RDF is parsable.
     */
    function testNoPluginDataForMissingInputs() {

        foreach ($this->_args as $key=>$val) {

            // Store args in a temp array so we can fudge it up.
            $_tmp = $this->_args;

            // We want to mess up a value on purpose.
            $_tmp[$key] = '';

            // Get the XML for a malformed URI.
            $_xml = $this->_getXml($_tmp);

            // The resulting document should be parsable.
            $this->assertTrue($this->_isParsable($_xml), 'Document still parsable when '.$key.' is empty.');
        }
    }

    /**
     * Test that Flash comes back as expected when passed the appropriate
     * args.
     */
     function testPluginDataFound() {
        $_xml = $this->_getXml($this->_args);

        // The resulting document should be parsable.
        $this->assertTrue($this->_isParsable($_xml), 'Document parsable when Flash plugin data was requested.');

        $this->assertPattern('/.*Adobe Flash Player.*/',$_xml,'Plugin name exists in XML.');
        $this->assertPattern('/.*'.preg_quote('application/x-shockwave-flash','/').'.*/',$_xml,'Plugin mime-type exists in XML.');
        $this->assertPattern('/.*{4cfaef8a-a6c9-41a0-8e6f-967eb8f49143}.*/',$_xml,'Plugin GUID exists in XML.');
        $this->assertPattern('/.*'.preg_quote('http://fpdownload.macromedia.com/get/flashplayer/xpi/current/flashplayer-win.xpi','/').'.*/',$_xml,'XPILocation exists in XML.');
        $this->assertPattern('/.*'.preg_quote('http://www.adobe.com/go/getflashplayer','/').'.*/',$_xml,'ManualInstallationURL exists in XML.');
        $this->assertPattern('/.*'.preg_quote('http://www.adobe.com/go/eula_flashplayer','/').'.*/',$_xml,'licenseURL exists in XML.');
     }


    /**
     * Test that no XPI is offered for Flash on any platform for Firefox 3.
     */
    function testNoXPIForFirefox3() {
        $_xml = $this->_getXml(
            array(
                'mimetype'=>'application/x-shockwave-flash',
                'appID'=>'{ec8030f7-c20a-464f-9b0e-13a3a9e97384}',
                'appVersion'=>'2007020307',
                'appRelease'=>'3.0',
                'clientOS'=>'Windows%20NT%206.0',
                'chromeLocale'=>'en-US'
            )
        );
        $this->assertNoPattern('/.*'.preg_quote('http://fpdownload.macromedia.com/get/flashplayer/xpi/current/flashplayer-win.xpi','/').'.*/',$_xml,'No Flash XPI offered for Firefox 3 on Vista.');

        $_xml = $this->_getXml(
            array(
                'mimetype'=>'application/x-shockwave-flash',
                'appID'=>'{ec8030f7-c20a-464f-9b0e-13a3a9e97384}',
                'appVersion'=>'2007020307',
                'appRelease'=>'3.0',
                'clientOS'=>'Linux%20i686',
                'chromeLocale'=>'en-US'
            )
        );
        $this->assertPattern('/.*'.preg_quote('http://fpdownload.macromedia.com/get/flashplayer/xpi/current/flashplayer-linux.xpi','/').'.*/',$_xml,'Flash XPI offered for Firefox 3 on Linux.');

        $_xml = $this->_getXml(
            array(
                'mimetype'=>'application/x-shockwave-flash',
                'appID'=>'{ec8030f7-c20a-464f-9b0e-13a3a9e97384}',
                'appVersion'=>'2007020307',
                'appRelease'=>'3.0',
                'clientOS'=>'Intel%20Mac%20OS%20X%2010.5',
                'chromeLocale'=>'en-US'
            )
        );
        $this->assertPattern('/.*'.preg_quote('http://fpdownload.macromedia.com/get/flashplayer/xpi/current/flashplayer-mac.xpi','/').'.*/',$_xml,'Flash XPI offered for Firefox 3 on Intel Mac OS X.');
    }

    /**
     * Test that XPIs are offered for Flash on Firefox 2.
     */
    function testXPIForFirefox2() {
        $_xml = $this->_getXml(
            array(
                'mimetype'=>'application/x-shockwave-flash',
                'appID'=>'{ec8030f7-c20a-464f-9b0e-13a3a9e97384}',
                'appVersion'=>'2007020307',
                'appRelease'=>'2.0',
                'clientOS'=>'Windows%20NT%205.0',
                'chromeLocale'=>'en-US'
            )
        );
        $this->assertPattern('/.*'.preg_quote('http://fpdownload.macromedia.com/get/flashplayer/xpi/current/flashplayer-win.xpi','/').'.*/',$_xml,'Flash XPI offered for Firefox 2 on XP.');

        $_xml = $this->_getXml(
            array(
                'mimetype'=>'application/x-shockwave-flash',
                'appID'=>'{ec8030f7-c20a-464f-9b0e-13a3a9e97384}',
                'appVersion'=>'2007020307',
                'appRelease'=>'2.0',
                'clientOS'=>'Windows%20NT%206.0',
                'chromeLocale'=>'en-US'
            )
        );
        $this->assertNoPattern('/.*'.preg_quote('flashplayer-win.xpi','/').'.*/',$_xml,'Flash XPI _NOT_ offered for Firefox 2 on Vista.');
        $this->assertNoPattern('/.*'.preg_quote('http://www.adobe.com/go/eula_flashplayer','/').'.*/',$_xml,'Flash licenseURL _NOT_ offered for Firefox 2 on Vista.');

        $_xml = $this->_getXml(
            array(
                'mimetype'=>'application/x-shockwave-flash',
                'appID'=>'{ec8030f7-c20a-464f-9b0e-13a3a9e97384}',
                'appVersion'=>'2007020307',
                'appRelease'=>'2.0',
                'clientOS'=>'Linux%20i686',
                'chromeLocale'=>'en-US'
            )
        );
        $this->assertPattern('/.*'.preg_quote('http://fpdownload.macromedia.com/get/flashplayer/xpi/current/flashplayer-linux.xpi','/').'.*/',$_xml,'Flash XPI offered for Firefox 2 on Linux.');

        $_xml = $this->_getXml(
            array(
                'mimetype'=>'application/x-shockwave-flash',
                'appID'=>'{ec8030f7-c20a-464f-9b0e-13a3a9e97384}',
                'appVersion'=>'2007020307',
                'appRelease'=>'2.0',
                'clientOS'=>'Intel%20Mac%20OS%20X%2010.5',
                'chromeLocale'=>'en-US'
            )
        );
        $this->assertPattern('/.*'.preg_quote('http://fpdownload.macromedia.com/get/flashplayer/xpi/current/flashplayer-mac.xpi','/').'.*/',$_xml,'Flash XPI offered for Firefox 2 on Intel Mac OS X.');
    }


    /**
     * Build a PFS URI based on _args.
     * @param array $args URI arguments
     * @return string resulting URI
     */
    function _buildUri($args=array()) {
        $_buf = array();
        foreach ($args as $key=>$val) {
            $_buf[] = $key.'='.$val;
        }
        return SERVICE_URL.'/pfs.php?'.implode('&',$_buf);
    }

    /**
     * Retrieve XML document based on _args.
     * @param array $args update URI arguments
     * @return string resulting XML document as a string
     */
    function _getXml($args=array()) {
        return trim(file_get_contents($this->_buildUri($args)));
    }

    /**
     * Returns a boolean saying whether or not the XML passed was parsable.
     * @param string $xml an XML document
     * @return boolean true if parsable, false if not
     */
    function _isParsable($xml) {
        $rdf = new Rdf_parser();
        $rdf->rdf_parser_create(null);
        $rdf->rdf_set_user_data($data);
        $rdf->rdf_set_statement_handler(array('RdfComponent', 'mfStatementHandler'));
        $rdf->rdf_set_base("");

        return $rdf->rdf_parse($xml, strlen($xml), true);
    }

}
?>
