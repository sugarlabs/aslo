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
 * Portions created by the Initial Developer are Copyright (C) 2009
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Jeff Balogh <jbalogh@mozilla.com> (Original Author)
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

/* This doesn't deserve a class.  It's just a namespace.  With the
 * Cake *Component mess tacked on.
 */
class HttplibComponent extends Object {

    /**
     * Performs a curl request and returns (content, info).
     *
     * @param resource $curl: a curl_init'd resource
     * @return (string content, array info)
     */
    function request($curl) {
        // Error checking is for sissies.
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => True,
        ));
        $content = curl_exec($curl);
        $info = curl_getinfo($curl);
        curl_close($curl);
        return array($content, $info);
    }

    /**
     * HTTP GET $url, returning (content, info).
     */
    function get($url) {
        return $this->request(curl_init($url));
    }

    /**
     * Turn an array into a query string.
     */
    function urlify($arr) {
        $d = array();
        foreach ($arr as $k => $v) {
            $d[] = sprintf('%s=%s', urlencode($k), urlencode($v));
        }
        return join('&', $d);
    }

    /**
     * HTTP POST $url with $data, returning (content, info).
     */
    function post($url, $data) {
        if (is_array($data)) {
            /* Converting to a string because PHP isn't outputting
             * the form data when it's in an array. php++
             */
            $data = $this->urlify($data);
        }

        $c = curl_init($url);
        curl_setopt_array($c, array(
            CURLOPT_POST => True,
            CURLOPT_POSTFIELDS => $data,
        ));
        return $this->request($c);
    }

    /**
     * Decode a query string and returns a mapping of key => value.
     *
     * Doesn't handle multiple keys because that's annoying.
     */
    function parse_qs($qs) {
        $items = explode('&', $qs);
        $a = array();
        foreach ($items as $item) {
            list($k, $v) = explode('=', $item);
            $a[urldecode($k)] = urldecode($v);
        }
        return $a;
    }

}
