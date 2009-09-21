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

var bandwagonService;

Bandwagon.Controller.BrowserOverlay = new function() {}

Bandwagon.Controller.BrowserOverlay.initBandwagon = function()
{
    Bandwagon.Controller.BrowserOverlay._removeLoadListener();

    try
    {
        netscape.security.PrivilegeManager.enablePrivilege("UniversalXPConnect");

        bandwagonService = Components.classes["@addons.mozilla.org/bandwagonservice;1"]
            .getService().wrappedJSObject;

        // We can safely call init() for each new browser window; the service
        // will only be initialized once.

        // TODO should we observe "app-startup" instead?

        bandwagonService.init();

        // Set up uninstall observer
        bandwagonService.startUninstallObserver();
    }
    catch (e)
    {
        Bandwagon.Logger.error("Error initializing bandwagon: " + e);
    }

    if (Bandwagon.Util.getHostEnvironmentInfo().appName == "Firefox")
        gBrowser.addEventListener("DOMContentLoaded", Bandwagon.Controller.BrowserOverlay.addSubscriptionRefreshEventListenerToDocument, true);
}

Bandwagon.Controller.BrowserOverlay.addSubscriptionRefreshEventListenerToDocument = function(event)
{
    Bandwagon.Logger.debug("In Bandwagon.Controller.BrowserOverlay.addSubscriptionRefreshEventListenerToDocument()");

    if (event.originalTarget instanceof HTMLDocument)
    {
        var doc = event.originalTarget;

        if (event.originalTarget.defaultView.frameElement)
        {
            while (doc.defaultView.frameElement)
            {
                doc=doc.defaultView.frameElement.ownerDocument;
            }
        }

        try
        {
            if (doc && doc.defaultView && doc.defaultView.location && doc.defaultView.location.host)
            {
                var docHost = doc.defaultView.location.host;

                if (docHost == Bandwagon.Preferences.getPreference("amo_host"))
                {
                    Bandwagon.Logger.debug("This document is on $amo_host, will add bandwagonRefresh listener");

                    doc.addEventListener("bandwagonRefresh", Bandwagon.Controller.BrowserOverlay.handleSubscriptionRefreshEvent, true);
                }
            }
        }
        catch (e)
        {
            Bandwagon.Logger.debug("Error adding subscription refresh event listener to document (probably harmless): " + e);
        }
    }
}

Bandwagon.Controller.BrowserOverlay.handleSubscriptionRefreshEvent = function(event)
{
    Bandwagon.Logger.info("Received bandwagon subscription refresh event");

    bandwagonService.updateCollectionsList();
}

Bandwagon.Controller.BrowserOverlay._removeLoadListener = function()
{
    window.removeEventListener("load", Bandwagon.init, true);
}

Bandwagon.Controller.BrowserOverlay.openFirstRunLandingPage = function()
{
    Bandwagon.Logger.debug("opening firstrun landing page");

    var url = Bandwagon.FIRSTRUN_LANDING_PAGE.replace("%%AMO_HOST%%", Bandwagon.Preferences.getPreference("amo_host"));

    if (Bandwagon.Util.getHostEnvironmentInfo().appName == "Firefox")
    {
        window.setTimeout(function()
        {
            var tab = window.getBrowser().addTab(url);
            window.getBrowser().selectedTab = tab;
        },
        1000);
    }
    // XX TODO A Thunderbird firstrun story
}

/* Toolbar button action - Open add-ons window with Subscriptions tab focused */
Bandwagon.Controller.BrowserOverlay.openAddons = function()
{
    var wm = Components.classes["@mozilla.org/appshell/window-mediator;1"]
                   .getService(Components.interfaces.nsIWindowMediator);
    var win = wm.getMostRecentWindow("Extension:Manager");
    if (win) {
        win.focus();
    }
    else
    {
        const EMURL = "chrome://mozapps/content/extensions/extensions.xul";
        const EMFEATURES = "chrome,menubar,extra-chrome,toolbar,dialog=no,resizable";
        if (Bandwagon.Util.getHostEnvironmentInfo().appVersion.substring(0, 1) == "2") {
            window.openDialog(EMURL, "", EMFEATURES);
        }
        else
            window.openDialog(EMURL, "", EMFEATURES, "bandwagon-collections");
    }
}

Bandwagon.Controller.BrowserOverlay.showNewAddonsAlert = function(collection)
{
    Bandwagon.Logger.debug("in showNewAddonsAlert()");

    if (Bandwagon.Util.getHostEnvironmentInfo().appName == "Fennec")
    {
        var localAutoInstaller = bandwagonService.getLocalAutoInstaller();

        if (!localAutoInstaller.equals(collection))
        {
            Bandwagon.Logger.debug("[Fennec] not showing new addons alert for this collection because it is not an auto installer");
            return;
        }
    }

    var bandwagonStrings = document.getElementById("bandwagon-strings");

    var alertsService;
    
    try 
    {
        alertsService = Components.classes["@mozilla.org/alerts-service;1"]
            .getService(Components.interfaces.nsIAlertsService);
    }
    catch (e)
    {
        Bandwagon.Logger.warn("Can't get a reference to the alerts service on this platform: " + e);
        return;
    }

    var unnotifiedCount = collection.getUnnotifiedAddons().length;
    var collectionName = (collection.name?collection.name:collection.resourceURL);
    var collectionURL = collection.resourceURL;

    var listener =
    {
        observe: function(subject, topic, data)
        {
            if (topic == "alertclickcallback")
            {
                var collectionURL = data;

                const EMTYPE = "Extension:Manager";
                var wm = Components.classes["@mozilla.org/appshell/window-mediator;1"]
                    .getService(Components.interfaces.nsIWindowMediator);
                var theEM = wm.getMostRecentWindow(EMTYPE);

                if (theEM)
                {
                    theEM.focus();
                    theEM.showView('bandwagon-collections');

                    // if the em is open, select the bandwagon-collections pane, but don't select the collection itself.
                    // the user will be notified by the unread count in the left hand side bar

                    //theEM.setTimeout(function() { Bandwagon.Controller.CollectionsPane._selectCollection(collection); }, 500);
                }
                else
                {
                    const EMURL = "chrome://mozapps/content/extensions/extensions.xul";
                    const EMFEATURES = "chrome,menubar,extra-chrome,toolbar,dialog=no,resizable";

                    // the em is not open - open it, and select this collection

                    if (window)
                        window.openDialog(EMURL, "", EMFEATURES, {selectCollection: collectionURL});
                    else
                        Bandwagon.Logger.error("No browser windows open - can't open EM");
                }

            }
        }
    }

    var icon = ((collection.iconURL != "")?collection.iconURL:"chrome://bandwagon/skin/images/icon32.png");

    try
    {
        alertsService.showAlertNotification(
                icon,
                bandwagonStrings.getString("newaddons.alert.title"),
                bandwagonStrings.getFormattedString("newaddons.alert.text", [collectionName]),
                true, 
                collectionURL,
                listener
                );
    }
    catch (e)
    {
        Bandwagon.Logger.warn("Error showing alert notification on this platform: " + e);
    }
}

Bandwagon.Controller.BrowserOverlay.doFennecLogin = function()
{
    var promptService = Components.classes["@mozilla.org/embedcomp/prompt-service;1"].getService(Components.interfaces.nsIPromptService);
    var bandwagonStrings = document.getElementById("bandwagon-strings");
    var title = bandwagonStrings.getString("fennec.prompt.title");

    var login = Bandwagon.Preferences.getPreference("login");
    var password = Bandwagon.Preferences.getPreference("password");

    var displayLoginError = function()
    {
        promptService.alert(null, title, bandwagonStrings.getString("fennec.login.error"));
    }

    if (!login || login == "" || !login.match(/.*\w.*/)
        || !password || password == "" || !password.match(/.*\w.*/))
    {
        displayLoginError();
        return;
    }

    var callback = function(event)
    {
        if (event.isError())
        {
            displayLoginError();
        }
        else
        {
            promptService.alert(null, title, bandwagonStrings.getString("fennec.login.ok"));
            Bandwagon.Preferences.setPreference("password", "");
        }
    }

    bandwagonService.authenticate2(login, password, callback);

}

window.addEventListener("load", Bandwagon.Controller.BrowserOverlay.initBandwagon, true);

