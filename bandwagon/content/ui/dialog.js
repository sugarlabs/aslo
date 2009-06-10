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

var Bandwagon;
var bandwagonService;

try
{
    // try and re-use the Bandwagon singletons from a recent browser window

    var bw = Components.classes["@mozilla.org/appshell/window-mediator;1"]
        .getService(Components.interfaces.nsIWindowMediator)
        .getMostRecentWindow("navigator:browser");

    if (bw)
    {
        Bandwagon = bw.Bandwagon;
    }
    else
    {
        // no recent browser window - load required scripts dynamically
        var scriptLoader = Components.classes["@mozilla.org/moz/jssubscript-loader;1"]
            .getService(Components.interfaces.mozIJSSubScriptLoader);

        scriptLoader.loadSubScript("chrome://bandwagon/content/scripts/bandwagon.js");
        scriptLoader.loadSubScript("chrome://bandwagon/content/scripts/logger.js");
        scriptLoader.loadSubScript("chrome://bandwagon/content/scripts/util.js");
        scriptLoader.loadSubScript("chrome://bandwagon/content/scripts/prefs.js");
    }

    // get the service

    netscape.security.PrivilegeManager.enablePrivilege("UniversalXPConnect");

    bandwagonService = Components.classes["@addons.mozilla.org/bandwagonservice;1"]
        .getService().wrappedJSObject;
}
catch (e)
{
    Bandwagon.Logger.error("Error fixing Bandwagon refs: " + e);
}



