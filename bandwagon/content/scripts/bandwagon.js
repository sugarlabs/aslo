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

top.Bandwagon = new function() {}
Bandwagon.Model = new function() {}
Bandwagon.Factory = new function() {}
Bandwagon.Controller = new function() {}
Bandwagon.RPC = new function() {}

Bandwagon.EMID = "sharing@addons.mozilla.org";
Bandwagon.SQLITE_FILENAME = "bandwagon.sqlite";

// This is the interval between checking if the collections need updating (in
// seconds) (this will always be 120 seconds in debug mode).  Note, this is not
// the delay between instances of when the feeds are pulled down (this is
// decided by "extensions.bandwagon.global.update.*" or per-collection
// settings), but rather the minimum delay.

Bandwagon.COLLECTION_UPDATE_TIMER_DELAY = 10 * 60;

// Note: %%AMO_HOST%% replacement done in bandwagonService._initAMOHost()
// XX Brian - this should really be done on the fly to avoid repitition of urls throughtout the code
//   and to lessen maintenance.

Bandwagon.LOGINPANE_DO_NEW_ACCOUNT = "https://%%AMO_HOST%%/users/register";
Bandwagon.COLLECTIONSPANE_DO_SUBSCRIBE_URL = "https://%%AMO_HOST%%/collections";
Bandwagon.COLLECTIONSPANE_DO_NEW_COLLECTION_URL = "https://%%AMO_HOST%%/collections/add";
Bandwagon.FIRSTRUN_LANDING_PAGE = "https://%%AMO_HOST%%/pages/collector_firstrun";
Bandwagon.AMO_AUTH_COOKIE_HOST = "%%AMO_HOST%%";
Bandwagon.AMO_AUTH_COOKIE_NAME = "AMOv3";
Bandwagon.PREF_AUTH_TOKEN = "authtoken";

Bandwagon.COMMIT_NOW = 0; // 1=commit on the fly. 0=commit when browser exit.
Bandwagon.ENABLE_PAGINATION = 0; // 1=enable "add-ons per page" settings, limit number of add-ons displayed in EM. 0=disable these settings, show all add-ons in EM.


