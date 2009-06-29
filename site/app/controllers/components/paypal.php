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

class PaypalComponent extends Object {
    var $controller;

    function startup(&$controller) {
        $this->controller =& $controller;

        // It's not a class, it's a namespace.
        loadComponent('Httplib');
        $this->httplib =& new HttplibComponent();
    }

    /**
     * The only thing we keep is the 'bn' parameter, which is some kind of
     * hash of the business name plus other paypal junk.
     */
    function createButton($businessName) {
        $data = array(
            /* Our info. */
            'USER' => PAYPAL_USER,
            'PWD' => PAYPAL_PASSWORD,
            'SIGNATURE' => PAYPAL_SIGNATURE,

            /* Paypal API cruft. */
            'VERSION' => PAYPAL_API_VERSION,
            'BUTTONCODE' => 'cleartext',
            'BUTTONTYPE' => 'DONATE',

            /* The Paypal action we're performing. */
            'METHOD' => 'BMCreateButton',

            /* The developer ID who wants a button. */
            'L_BUTTONVAR0' => "business={$businessName}"
        );

        list($content, $info) = $this->httplib->post(PAYPAL_URL, $data);
        $response = $this->httplib->parse_qs($content);

        // Hooray for HTTP status codes!
        $success = $response['ACK'] == 'Success';
        return array($success, $response);
    }
}
?>
