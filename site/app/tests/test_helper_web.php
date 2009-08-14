<?php

class WebTestHelper extends WebTestCase {

    function before($method) {
        // The test browser is created in parent::before.
        parent::before($method);
        $this->addHeader('X-Amo-Test: damn right');
    }

    /* Compute protocol and hostname prefix, no trailing slash. */
    function hostPrefix() {
        $http = (!empty($_SERVER["HTTP_MOZ_REQ_METHOD"]) && $_SERVER["HTTP_MOZ_REQ_METHOD"] == 'HTTPS') ? 'https://' : 'http://';
        $uriBase = $http . $_SERVER['HTTP_HOST'];
        return $uriBase;
    }

    /**
     * The default SimpleBrowser tries to parse all responses, even when
     * they're not HTML.  That fails.  We need a better browser.
     *
     *    Creates a new default web browser object.
     *    Will be cleared at the end of the test method.
     */
    function &createBrowser() {
        $browser =& new BetterBrowser();
        return $browser;
    }

    /* Compute the URI for the given action, accounting for us possibly not
     * being at the root of the web space.
     */
    function actionURI($action) {
        /**
            If HTTP_MOZ_REQ_METHOD indicates this was requested via https://,
            use that, otherwise default to http:// 
        */
        return $this->hostPrefix() . $this->actionPath($action);
    }

    /* As above, but just the local path and not a complete URI. */
    function actionPath($action) {
        return preg_replace('/\/tests.*/', $action, setUri());
    }
    
    /* Return a URI computed from the installation base, without locale or app. */
    
    function rawURI($path) {
        return $this->hostPrefix() . $this->rawPath($path);
    }
    
    /* As above, but just the local path and not a complete URI. */
    function rawPath($path) {
        return preg_replace('/\/' . LANG . '\/' . APP_SHORTNAME . '\/tests.*/', $path, setUri());
    }

    /* Make a GET for the given action, accounting for us possibly not being at
     * the root of the web space.
     */
    function getAction($action) {
        $this->get($this->actionURI($action));
    }
    
    /* GET a fully-specified local URI path (needs to include site prefix if any). */
    function getPath($path) {
        $this->get($this->hostPrefix() . $path);
    }
    
   /**
    * Logs in with test account info.
    */
    function login() {
        $username = 'nobody@mozilla.org';
        $password = 'test';
        
        $path = $this->actionURI('/users/login');
        $data = array(
                    'data[Login][email]' => $username,
                    'data[Login][password]' => $password
                );
        
        $this->post($path, $data);
        $this->assertNoUnwantedText(___('Wrong username or password!'), 'Logged in with test account');        
    }

    /**
     * Check if the retrieved XML document is well-formed/trivially parsable
     * (no DTD validity for now)
     */
    function checkXML() {
        $browser = $this->getBrowser();
        $data = $browser->getContent();
        $xmlparser = xml_parser_create();
        return (xml_parse($xmlparser, $data, true) == 1);;
    }

    /**
     * Check that there is a link like <a href=$href>$text</a>.
     */
    function assertLinkLocation($href, $text) {
        phpQuery::newDocument($this->_browser->getContent());
        $q = pq("a[href$={$href}");
        $msg = htmlentities("<a href='{$href}'>{$text}</a> exists");
        $this->assertEqual($q->text(), $text, $msg);
    }

    /**
     * Check that the element $q has the attributes in the $attrs array.
     * $q can be a phpquery object or a selector string.
     */
    function assertAttrs($q, $attrs, $msg) {
        if (is_string($q)) {
            phpQuery::newDocument($this->_browser->getContent());
            $q = pq($q);
        }
        foreach ($attrs as $attr => $val) {
            $this->assertEqual($q->attr($attr), $val, $msg);
        }
    }

    /* Helper to normalize whitespace in a string. */
    function _norm($x) {
        return implode(' ', preg_split('/\s+/', trim($x)));
    }

    /* Assert that the strings have the same content, ignoring whitespace. */
    function assertEquiv($a, $b, $msg=null) {
        $this->assertEqual($this->_norm($a), $this->_norm($b));
    }

    function delete($url, $parameters = false) {
        return $this->_failOnError($this->_browser->delete($url, $parameters));
    }
    
    function put($url, $parameters = false) {
        return $this->_failOnError($this->_browser->put($url, $parameters));
    }
    
    function postMultipart($url, $parameters = false, $files = false) {
        return $this->_failOnError($this->_browser->postMultipart($url, $parameters, $files));
    }
}

class BetterBrowser extends SimpleBrowser {

    /**
     * Overrides _buildPage to only parse responses when the Content Type
     * looks like HTML.
     */
    function &_buildPage($response) {
        $headers = $response->getHeaders()->getRaw();
        if (preg_match('#^Content-Type: (text/html|application/xhtml+xml)#m', $headers)) {
            $page =& parent::_buildPage($response);
        } else {
            $page =& new SimplePage($response);
        }
        return $page;
    }

    /* Define $this->_browser->skipParse to avoid the HTML parsing overhead. */
    function &_fetch($url, $encoding, $depth=0) {
        if (isset($this->skipParse)) {
            $response = &$this->_user_agent->fetchResponse($url, $encoding);
            $page = new SimplePage($response);
            return $page;
        } else {
            return parent::_fetch($url, $encoding, $depth);
        }
    }

    function delete($url, $parameters = false) {
        if (! is_object($url)) {
            $url = new SimpleUrl($url);
        }
        if ($this->getUrl()) {
            $url = $url->makeAbsolute($this->getUrl());
        }
        return $this->_load($url, new SimpleDeleteEncoding($parameters));
    }

    function put($url, $parameters = false) {
        if (! is_object($url)) {
            $url = new SimpleUrl($url);
        }
        if ($this->getUrl()) {
            $url = $url->makeAbsolute($this->getUrl());
        }
        return $this->_load($url, new SimplePutEncoding($parameters));
    }

    /**
     * Multipart POST request. May contain file attachments.
     * @param string $url target URL
     * @param array $parameters array of POST parameters
     * @param array $files further POST parameters of type key => path to file to attach
     */
    function postMultipart($url, $parameters = false, $files = false) {
        if (! is_object($url)) {
            $url = new SimpleUrl($url);
        }
        if ($this->getUrl()) {
            $url = $url->makeAbsolute($this->getUrl());
        }
        // prepare multipart encoding
        $encoding = new SimpleMultipartEncoding($parameters);
        // attach files if needed
        if (!empty($files) && is_array($files)) {
            foreach($files as $k => &$f) {
                if (! file_exists($f)) continue;
                $encoding->attach(
                    $k,
                    implode('', file($f)),
                    basename($f));
            }
        }

        return $this->_load($url, $encoding);
    }
}

/**
 * Bundle of URL parameters for a DELETE request.
 */
class SimpleDeleteEncoding extends SimpleGetEncoding {
    function SimpleDeleteEncoding($query = false) {
        $this->SimpleGetEncoding($query);
    }
    function getMethod() {
        return 'DELETE';
    }
}

/**
 * Bundle of URL parameters for a PUT request.
 */
class SimplePutEncoding extends SimplePostEncoding {
    function SimplePutEncoding($query = false) {
        $this->SimplePostEncoding($query);
    }
    function getMethod() {
        return 'PUT';
    }
}
?>
