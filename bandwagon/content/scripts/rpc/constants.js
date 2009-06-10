/* ***** BEGIN LICENSE BLOCK *****
 *   Version: MPL 1.1/GPL 2.0/LGPL 2.1
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
 * The Original Code is bandwagon.
 *
 * The Initial Developer of the Original Code is
 * Mozilla Corporation.
 * Portions created by the Initial Developer are Copyright (C) 2008
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s): David McNamara
 *                 Brian King
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

Bandwagon.RPC.Constants = new function()
{
    this.BANDWAGON_RPC_SERVICE_DOCUMENT = "https://%%AMO_HOST%%/en-US/firefox/api/1.3/sharing/";
    
    this.BANDWAGON_RPC_ENABLE_CACHE_BUSTER = 1;

    this.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_GET_SERVICE_DOCUMENT_COMPLETE  = 100;
    this.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_GET_COLLECTION_COMPLETE  = 200;
    this.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_SHARE_TO_EMAIL_COMPLETE = 300;
    this.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_PUBLISH_COMPLETE  = 400;
    this.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_REMOVE_ADDON_FROM_COLLECTION_COMPLETE  = 500;
    this.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_NEW_COLLECTION_COMPLETE  = 600;
    this.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_DELETE_COLLECTION_COMPLETE  = 700;
    this.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_GET_AUTH_DOCUMENT_COMPLETE  = 800;
    this.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_UNSUBSCRIBE_FROM_COLLECTION_COMPLETE  = 900;
    this.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_SUBSCRIBE_TO_COLLECTION_COMPLETE  = 1000;
    this.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_UPDATE_COLLECTION_COMPLETE  = 1100;

    // xhr layer constants

    this.BANDWAGON_RPC_NET_FAILURE = -1;
    this.BANDWAGON_RPC_NET_SUCCESS = 1;
    this.BANDWAGON_RPC_NET_CREATED = 10;
    this.BANDWAGON_RPC_NET_INPROGRESS = 20;
    this.BANDWAGON_RPC_NET_FINISHED = 30;

    this.BANDWAGON_RPC_NET_ERROR_HTTP = 400;
    this.BANDWAGON_RPC_NET_ERROR_XHR_CONNECTION = 500;
    this.BANDWAGON_RPC_NET_ERROR_XHR_CREATE = 510;
    this.BANDWAGON_RPC_NET_ERROR_XML_PROTOCOL = 520;

    this.BANDWAGON_RPC_SERVICE_ERROR_BAD_XML = 1010;               // http status 200 - 300, but unparsable XML response
    this.BANDWAGON_RPC_SERVICE_ERROR_UNEXPECTED_XML = 1011;        // http status 200 - 300, but unexpected XML response
    this.BANDWAGON_RPC_SERVICE_ERROR = 1050;                       // http status 200 - 300, but "expected" error in XML response

    this.BANDWAGON_RPC_SERVICE_ERROR_BAD_REQUEST = 1400;           //400 BAD REQUEST = Invalid request URI or header, or unsupported nonstandard parameter.
    this.BANDWAGON_RPC_SERVICE_ERROR_UNAUTHORIZED = 1401;          //401 UNAUTHORIZED = Authorization required.
    this.BANDWAGON_RPC_SERVICE_ERROR_FORBIDDEN = 1403;             //403 FORBIDDEN = Unsupported standard parameter, or authentication or authorization failed.
    this.BANDWAGON_RPC_SERVICE_ERROR_NOT_FOUND = 1404;             //404 NOT FOUND = Resource (such as a collection or entry) not found.
    this.BANDWAGON_RPC_SERVICE_ERROR_CONFLICT = 1409;              //409 CONFLICT = Specified version number doesn't match resource's latest version number.
    this.BANDWAGON_RPC_SERVICE_ERROR_BAD_CONTEXT = 1422;           //422 BAD CONTENT = The data within this entry's <content> is not valid. For example, this may indicate not "well-formed" XML
    this.BANDWAGON_RPC_SERVICE_ERROR_INTERNAL_SERVER_ERROR = 1500; //500 INTERNAL SERVER ERROR = Internal error. This is the default code that is used for all unrecognized errors.
    this.BANDWAGON_RPC_SERVICE_ERROR_CRITICAL_ERROR = 1600;        // http status other

}
