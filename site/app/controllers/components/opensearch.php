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

class OpenSearchEngineUrl extends Object {
    var $params = array();
    var $method, $template;

    function OpenSearchEngineUrl($method, $template) {
        $this->method = $method;
        $this->template = $template;
    }

    /**
     * Adds a parameter to this URL.
     * @param string $name
     * @param string $value
     */
    function addParam($name, $value) {
        if ($name != '' && $value != '') {
            array_push($this->params, array($name, $value));
        }
    }

    /**
     * Performs OpenSearch parameter replacement on $str.
     * This function is meant to eliminate dynamic parameters from the
     * OpenSearch description so that it can be serialized to Sherlock, which
     * doesn't support any dynamic parameters. This function uses the same
     * logic as Firefox to choose reasonable default values for required
     * parameters in the input string. See:
     * http://bonsai.mozilla.org/cvsblame.cgi?file=mozilla/browser/components/search/nsSearchService.js&rev=1.88#754
     * @param string $inputEncoding the value used to replace the
     *               {inputEncoding} parameter, if present.
     * @param string $str the string containing the dynamic parameters to
     *               replace.
     */
    function paramSubstitution($inputEncoding, $str) {
        // Insert the OpenSearch parameters we're confident about
        $supportedParams = array('/\{inputEncoding\??\}/', '/\{language\??\}/',
                                 '/\{outputEncoding\??\}/');

        // Specified inputEncoding, "*" for "all languages", and "UTF-8" for
        // outputEncoding. This matches the values Firefox uses by default.
        $values = array($inputEncoding, '*', 'UTF-8');

        // Replace supported parameters
        $str = preg_replace($supportedParams, $values, $str);

        // Remove all optional parameters (any parameter with a trailing "?")
        $str = preg_replace('/\{\w+\?\}/', '', $str);

        // Replace any remaining parameters that we recognize with reasonable
        // default values.
        $otherParams = array('/\{count\}/', '/\{startIndex\}/',
                             '/\{startPage\}/');

        // 20 search results, start at page 1, index 1. These values are also
        // based on Firefox's defaults.
        $str = preg_replace($otherParams, array('20', '1', '1'), $str);

        return $str;
    }

    function getTemplate($inputEncoding) {
        return $this->paramSubstitution($inputEncoding, $this->template);
    }

    function getParams($inputEncoding) {
        $params = array();
        foreach ($this->params as $param) {
            $name = $param[0];
            $value = $this->paramSubstitution($inputEncoding, $param[1]);

            if ($value) {
                array_push($params, array($name, $value));
            }
        }

        return $params;
    }
}

class OpenSearchEngine extends Object {
    var $urls = array();
    var $name, $alias, $description, $inputEncoding, $outputEncoding;
    var $searchForm, $updateInterval, $updateUrl, $iconUpdateUrl;
    var $imageUrl;

    /**
     * Gets an array of parameters corresponding to a given URL.
     * @param string $urlType the content type of the URL
     * Returns NULL if this engine has no URL of type $urlType.
     */
    function getParams($urlType) {
        if (!isset($this->urls[$urlType])) {
            return NULL;
        }

        return $this->urls[$urlType]->getParams($this->inputEncoding);
    }

    /**
     * Gets the template parameter for a given URL.
     * @param string $urlType the content type of the URL
     * Returns NULL if this engine has no URL of type $urlType.
     */
    function getTemplate($urlType) {
        if (!isset($this->urls[$urlType])) {
            return NULL;
        }

        return $this->urls[$urlType]->getTemplate($this->inputEncoding);
    }

    // Utility function to print a Sherlock attribute.
    function prAttr($attr, $val) {
        if ($val) {
            return "  $attr=\"" . $val . "\"\n";
        }

        return '';
    }

    /**
     * Serialize this engine to a to a valid Sherlock string. Returns NULL if
     * the engine can't be represented by a Sherlock description file.
     */
    function toSherlock() {
        $htmlUrl =& $this->urls['text/html'];

        if ($htmlUrl->method != 'GET') {
            // Sherlock only supports GET plugins
            return NULL;
        }

        $template = $this->getTemplate('text/html');
        $firstInput = '';
        $foundUserInput = false;
        if (preg_match('/{searchTerms}/', $template)) {
            if (!preg_match('/{searchTerms}$/', $template)) {
                // Sherlock can't deal with templates that have the search
                // terms embedded in them, unless the search terms are simply
                // appended to the template.
                return NULL;
            }

            $template = preg_replace('/{searchTerms}$/', '', $template);

            // Add a special input that tells Firefox to simply append the
            // user's search terms to the end of the template. This has to be
            // the first input for this to work. See:
            // http://bonsai.mozilla.org/cvsblame.cgi?file=mozilla/xpfe/components/search/src/nsInternetSearchService.cpp&rev=1.251#4637
            $firstInput = "<input user>\n";
            $foundUserInput = true;
        }

        $queryCharset = $this->inputEncoding;

        // Search section
        $srcString = "<SEARCH\n";
        $srcString .= $this->prAttr('name', $this->name);
        $srcString .= $this->prAttr('action', $template);
        $srcString .= $this->prAttr('method', $htmlUrl->method);
        $srcString .= $this->prAttr('searchForm', $this->searchForm);
        $srcString .= $this->prAttr('description', $this->description);
        $srcString .= $this->prAttr('queryCharset', $queryCharset);
        $srcString .= ">\n";

        // print inputs
        $srcString .= $firstInput;

        $params = $this->getParams('text/html');
        foreach ($params as $param) {
            $name = $param[0]; $value = $param[1];

            if (preg_match('/{searchTerms}/', $value)) {
                if (!preg_match('/^{searchTerms}$/', $value)) {
                    return NULL;
                }

                $srcString .= "<input name=\"$name\" user>\n";
                $foundUserInput = true;
            } else {
                $srcString .= "<input name=\"$name\" value=\"$value\">\n";
            }
        }

        // Check that we found a valid user input
        if (!$foundUserInput) {
            return NULL;
        }

        $srcString .= "</SEARCH>\n";

        // Browser section (Mozilla extension to Sherlock, used for updates)
        if ($this->updateUrl || $this->iconUpdateUrl || $this->updateInterval) {
            $srcString .= "\n<BROWSER\n";
            $srcString .= $this->prAttr('update', $this->updateUrl);
            $srcString .= $this->prAttr('updateIcon', $this->iconUpdateUrl);
            $srcString .= $this->prAttr('updateCheckDays', $this->updateInterval);
            $srcString .= ">\n";
        }

        return $srcString;
    }

    // Returns raw bytes of the engine's image, if it has one, or NULL
    // otherwise
    function getImage() {
      if (!$this->imageUrl ||
          !preg_match('/^data\:/', $this->imageUrl)) {
          return NULL;
      }

      $base64Str = preg_replace('/^data\:.+base64,/', '', $this->imageUrl);
      if (!($bytes = base64_decode($base64Str))) {
          return NULL;
      }

      return $bytes;
    }
}

class OpensearchComponent extends Object {
    // Initialize variables
    var $in = false;
    var $wasIn = false;
    var $curEl = NULL;
    var $curUrlType = NULL;
    var $attrs = array();
    var $tagCount = array();
    var $gotData = array();
    var $depth = 0;
    var $engine = NULL;

    /**
     * XML parsing callback functions.
     */
    function start_element($parser, $name, $attrs) {
        if ($name == "OpenSearchDescription") {
            if ($this->in || $this->wasIn) {
                // Multiple OpenSearchDescription elements - bail out
                return;
            }

            // Test the namespace
            // These are the namespaces that Firefox supports
            $validNamespaces =
                      array("http://a9.com/-/spec/opensearch/1.0/",
                            "http://a9.com/-/spec/opensearch/1.1/",
                            "http://a9.com/-/spec/opensearchdescription/1.1/",
                            "http://a9.com/-/spec/opensearchdescription/1.0/");
            if (isset($attrs['xmlns']) &&
                in_array($attrs['xmlns'], $validNamespaces)) {
                $this->in = true;
                $this->wasIn = true;
                $this->engine =& new OpenSearchEngine();
                return;
            }
        }

        if (!$this->in) {
            return;
        }

        $this->depth++;

        // Since <Url>s are the only elements that can have child elements, and
        // their only valid child elements are <Param>s, we can ignore this
        // element if it has a depth greater than 1 and isn't a <Param> that's
        // a child of a <Url>.
        $processingParam = ($this->curUrlType && $name == "Param");
        if ($this->depth != 1 && !$processingParam) {
            return;
        }

        if ($processingParam) {
            $urls =& $this->engine->urls;
            $urls[$this->curUrlType]->addParam($attrs['name'], $attrs['value']);
            return;
        }

        switch ($name) {
          case "ShortName":
          case "Description":
          case "InputEncoding":
          case "UpdateUrl":
          case "UpdateInterval":
          case "IconUpdateUrl":
          case "Alias":
          case "SearchForm":
          case "OutputEncoding":
            $this->tagCount[$name]++;
            $this->curEl = $name;
            break;
          case "Image":
            // Ignore image elements that aren't supported by Firefox
            if (isset($attrs["width"]) && $attrs["width"] == 16 &&
                isset($attrs["height"]) && $attrs["height"] == 16) {
                $this->tagCount[$name]++;
                $this->curEl = $name;
            }
            break;
          case "Url":
            // Validate the URL element. We only care about https?://
            // templates, and text/html types.
            $OS_SUPPORTED_TYPES = array("text/html", "application/x-suggestions+json");
            $OS_SUPPORTED_METHODS = array("POST", "GET");

            $type = strtolower($attrs["type"]);
            $method = (isset($attrs["method"])) ? strtoupper($attrs["method"])
                                                : 'GET';
            $template = $attrs["template"];

            if (preg_match('/^https?/i', $template) &&
                in_array($type, $OS_SUPPORTED_TYPES) &&
                in_array($method, $OS_SUPPORTED_METHODS)) {

                $this->curUrlType = $type;
                $this->engine->urls[$type] =& new OpenSearchEngineUrl($method, $template);
            }
            break;
        }
    }

    function stop_element($parser, $name) {
        if ($name == "OpenSearchDescription") {
            $this->in = false;
            return;
        }

        $this->depth--;

        $this->curEl = NULL;
        if ($name == "Url") {
            $this->curUrlType = NULL;
        }
    }

    function char_data($parser, $data) {
        if ($this->curEl) {
            $name = $this->curEl;
            if (!$this->gotData[$name]) {
                $this->gotData[$name] = $this->tagCount[$name];
            }

            if ($this->gotData[$name] != $this->tagCount[$name]) {
                // We hit a new tag, clobber the existing data.
                $this->attrs[$name] = $data;
                $this->gotData[$name] = $this->tagCount[$name];
            } else {
                $this->attrs[$name] .= $data;
            }
        }
    }

    /**
     * Resets the component's state so that it's ready to parse another file.
     */
    function reset() {
      $this->in = false;
      $this->wasIn = false;
      $this->curEl = NULL;
      $this->curUrlType = NULL;
      
      // Define empty elements to avoid "undefined index" errors
      // Surely there is a better way to do this?
      $this->attrs = array('ShortName' => '',
                           'Description' => '',
                           'InputEncoding' => '',
                           'UpdateUrl' => '',
                           'UpdateInterval' => '',
                           'IconUpdateUrl' => '',
                           'Alias' => '',
                           'SearchForm' => '',
                           'OutputEncoding' => '',
                           'Image' => '');
      $this->tagCount = array('ShortName' => '',
                              'Description' => '',
                              'InputEncoding' => '',
                              'UpdateUrl' => '',
                              'UpdateInterval' => '',
                              'IconUpdateUrl' => '',
                              'Alias' => '',
                              'SearchForm' => '',
                              'OutputEncoding' => '',
                              'Image' => '');
      $this->gotData = array('ShortName' => '',
                             'Description' => '',
                             'InputEncoding' => '',
                             'UpdateUrl' => '',
                             'UpdateInterval' => '',
                             'IconUpdateUrl' => '',
                             'Alias' => '',
                             'SearchForm' => '',
                             'OutputEncoding' => '',
                             'Image' => '');
      $this->depth = 0;
      $this->engine = NULL;
    }

    /**
     * Parses OpenSearch description files and returns an OpenSearchEngine
     * object.
     * @param $file either a filename pointing to an XML OpenSearch
     *        description file, or a string containing an OS description file's
     *        contents.
     */
    function parse($file) {
      // Reset state
      $this->reset();

      if (file_exists($file)) {
          $contents = file_get_contents($file);
      } else {
          $contents = $file;
      }

      // Create Expat parser
      $parser = xml_parser_create();

      // Set handler functions
      xml_set_object($parser, $this);
      xml_set_element_handler($parser, "start_element", "stop_element");
      xml_set_character_data_handler($parser, "char_data");
      xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);

      // Parse the file
      $ret = xml_parse($parser, $contents);
      xml_parser_free($parser);

      if (!$ret) {
          return NULL;
      }

      // Check to ensure we found all required elements (name, description, and valid Url)
      if ($this->attrs['ShortName'] == '' ||
          $this->attrs['Description'] == '' || 
          !isset($this->engine->urls['text/html'])) {

          return NULL;
      }

      $this->engine->name = $this->attrs['ShortName'];
      $this->engine->alias = $this->attrs['Alias'];
      $this->engine->inputEncoding = $this->attrs['InputEncoding'];
      $this->engine->updateUrl = $this->attrs['UpdateUrl'];
      $this->engine->description = $this->attrs['Description'];
      $this->engine->updateInterval = $this->attrs['UpdateInterval'];
      $this->engine->iconUpdateUrl = $this->attrs['IconUpdateUrl'];
      $this->engine->searchForm = $this->attrs['SearchForm'];
      $this->engine->outputEncoding = $this->attrs['OutputEncoding'];
      $this->engine->imageUrl = $this->attrs['Image'];

      return $this->engine;
    }
}
?>
