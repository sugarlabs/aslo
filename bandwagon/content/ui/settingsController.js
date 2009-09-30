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
 *                 Brian King <brian (at) briks (dot) si>
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

Bandwagon.Controller.Settings = new function()
{
    this.collections = {};

    this.elemBandwagonCollections = null;

    this.stringBundle = null;
    this.stringBundle2 = null;
}

Bandwagon.Controller.Settings.init = function()
{
    Bandwagon.Logger.debug("Initializing Bandwagon.Controller.Settings");

    Bandwagon.Controller.Settings.stringBundle = document.getElementById("bandwagon-strings");
    Bandwagon.Controller.Settings.stringBundle2 = document.getElementById("bandwagon-strings2");

    Bandwagon.Controller.Settings.elemBandwagonCollections = document.getElementById("collections-list");
    Bandwagon.Controller.Settings.elemBandwagonCollections.addEventListener("select", Bandwagon.Controller.Settings.doShowCollection, true);

    // save the collection when the user chooses between global and collection scoped settings...

    document.getElementById("updates-group").addEventListener("command", Bandwagon.Controller.Settings.doSaveCollection, true);
    document.getElementById("notifications-group").addEventListener("command", Bandwagon.Controller.Settings.doSaveCollection, true);
    document.getElementById("perpage-group").addEventListener("command", Bandwagon.Controller.Settings.doSaveCollection, true);

    // ... and when the user changes the collection scoped settings.

    document.getElementById("textbox-updateinterval-quantity-percollection").addEventListener("input", Bandwagon.Controller.Settings.doSaveCollection, true);
    document.getElementById("textbox-updateinterval-quantity-percollection").addEventListener("command", Bandwagon.Controller.Settings.doSaveCollection, false);
    document.getElementById("menulist-updateinterval-units-percollection").addEventListener("command", Bandwagon.Controller.Settings.doSaveCollection, true);
    document.getElementById("checkbox-shownotifications-percollection").addEventListener("command", Bandwagon.Controller.Settings.doSaveCollection, true);
    document.getElementById("textbox-addonsperpage-percollection").addEventListener("input", Bandwagon.Controller.Settings.doSaveCollection, true);
    document.getElementById("textbox-addonsperpage-percollection").addEventListener("command", Bandwagon.Controller.Settings.doSaveCollection, true);

    //document.getElementById("updates-group").addEventListener("command", Bandwagon.Controller.Settings.doUpdateIntervalScopeChange, true);
    //document.getElementById("notifications-group").addEventListener("command", Bandwagon.Controller.Settings.doShowNotificationsScopeChange, true);
    //document.getElementById("perpage-group").addEventListener("command", Bandwagon.Controller.Settings.doSaveCollection, true);

    bandwagonService.registerCollectionListChangeObserver(Bandwagon.Controller.Settings.collectionListChangeObserver);
    bandwagonService.registerAuthenticationStatusChangeObserver(Bandwagon.Controller.Settings.authenticationStatusChangeObserver);

    var apLeadinTxt = Bandwagon.Controller.Settings.stringBundle.getFormattedString("autoleadin.label", [Bandwagon.Util.getHostEnvironmentInfo().appName])
    document.getElementById("auto-leadin").textContent = apLeadinTxt ? apLeadinTxt : " ";

    setTimeout(function() 
    {
        Bandwagon.Controller.Settings._delayedInit();
    }, 
    10);
}

Bandwagon.Controller.Settings._delayedInit = function()
{
    Bandwagon.Controller.Settings._repopulateCollectionsList();

    Bandwagon.Controller.Settings.invalidateEmails();
    Bandwagon.Controller.Settings.invalidateLogin();
    Bandwagon.Controller.Settings.invalidateAutoPublisher();
    Bandwagon.Controller.Settings.invalidateAllowIncompatibleInstall();
    Bandwagon.Controller.Settings.invalidateCustomizeCollectionUI();
    Bandwagon.Controller.Settings.invalidatePaginationSettings();
    Bandwagon.Controller.Settings.invalidate();
}

Bandwagon.Controller.Settings.bindingsReady = function()
{
    var elemBandwagonCollection = Bandwagon.Controller.Settings.elemBandwagonCollections.getElementsByTagName("bandwagonCollection")[0];

    if (elemBandwagonCollection)
    {
        Bandwagon.Controller.Settings.elemBandwagonCollections.selectedItem = elemBandwagonCollection;
        Bandwagon.Controller.Settings.elemBandwagonCollections.focus();
    }
}

Bandwagon.Controller.Settings.uninit = function()
{
    bandwagonService.unregisterCollectionListChangeObserver(Bandwagon.Controller.Settings.collectionListChangeObserver);
    bandwagonService.unregisterAuthenticationStatusChangeObserver(Bandwagon.Controller.Settings.authenticationStatusChangeObserver);

    // now is a good time to save collections to storage
    if (Bandwagon.COMMIT_NOW)
        bandwagonService.commitAll();
}

Bandwagon.Controller.Settings.collectionListChangeObserver = function()
{
    Bandwagon.Controller.Settings._repopulateCollectionsList();

    var elemBandwagonCollection = Bandwagon.Controller.Settings.elemBandwagonCollections.getElementsByTagName("bandwagonCollection")[0];

    if (elemBandwagonCollection)
    {
        Bandwagon.Controller.Settings.elemBandwagonCollections.selectedItem = elemBandwagonCollection;
        Bandwagon.Controller.Settings.elemBandwagonCollections.focus();
    }
    else
    {
        Bandwagon.Controller.Settings.doShowCollection();
    }

    Bandwagon.Controller.Settings.invalidate();
}

Bandwagon.Controller.Settings.authenticationStatusChangeObserver = function()
{
    Bandwagon.Controller.Settings.invalidateLogin();
    Bandwagon.Controller.Settings.invalidate();
}

Bandwagon.Controller.Settings.invalidate = function()
{
    var collectionCount = Bandwagon.Controller.Settings.elemBandwagonCollections.getElementsByTagName("bandwagonCollection").length;
    var collectionSelection = Bandwagon.Controller.Settings.elemBandwagonCollections.selectedItem;

    var disabled = (!collectionSelection || collectionCount == 0);

    document.getElementById("remove-button").disabled = disabled;
    document.getElementById("updates-group").disabled = disabled;
    document.getElementById("textbox-updateinterval-quantity-percollection").disabled = disabled;
    document.getElementById("menulist-updateinterval-units-percollection").disabled = disabled;
    document.getElementById("notifications-group").disabled = disabled;
    document.getElementById("label-addonsshow-percollection").disabled = disabled;
    document.getElementById("label-addonsperpage-percollection").disabled = disabled;
    document.getElementById("textbox-addonsperpage-percollection").disabled = disabled;
    document.getElementById("perpage-default").disabled = disabled;
    document.getElementById("perpage-custom").disabled = disabled;
}

Bandwagon.Controller.Settings.invalidatePaginationSettings = function()
{
    document.getElementById("pagination-box").collapsed = (Bandwagon.ENABLE_PAGINATION?false:true);
    document.getElementById("global-pagination-box").collapsed = (Bandwagon.ENABLE_PAGINATION?false:true);
}

Bandwagon.Controller.Settings.invalidateCustomizeCollectionUI = function()
{
    document.getElementById("textbox-updateinterval-quantity-percollection").disabled = (document.getElementById("updates-group").selectedIndex == 0);
    document.getElementById("menulist-updateinterval-units-percollection").disabled = (document.getElementById("updates-group").selectedIndex == 0);
    document.getElementById("checkbox-updateinterval-percollection").disabled = (document.getElementById("updates-group").selectedIndex == 0);

    document.getElementById("checkbox-shownotifications-percollection").disabled = (document.getElementById("notifications-group").selectedIndex == 0);

    document.getElementById("label-addonsshow-percollection").disabled = (document.getElementById("perpage-group").selectedIndex == 0);
    document.getElementById("textbox-addonsperpage-percollection").disabled = (document.getElementById("perpage-group").selectedIndex == 0);
    document.getElementById("label-addonsperpage-percollection").disabled = (document.getElementById("perpage-group").selectedIndex == 0);
}

Bandwagon.Controller.Settings.invalidateLogin = function()
{
    var isLoggedIn = bandwagonService.isAuthenticated();

    if (isLoggedIn)
    {
        var loginEmail = Bandwagon.Preferences.getPreference("login");
        document.getElementById("login-status-text").value = Bandwagon.Controller.Settings.stringBundle.getFormattedString("login.status.with.email", [loginEmail]);
        
        document.getElementById("login-button").collapsed = true;
        document.getElementById("logout-button").removeAttribute("collapsed");
    }
    else
    {
        document.getElementById("login-status-text").value = Bandwagon.Controller.Settings.stringBundle.getString("logout.status");
        document.getElementById("login-button").removeAttribute("collapsed");
        document.getElementById("logout-button").collapsed = true;

        while (Bandwagon.Controller.Settings.elemBandwagonCollections.hasChildNodes())
        {
            Bandwagon.Controller.Settings.elemBandwagonCollections.removeChild(Bandwagon.Controller.Settings.elemBandwagonCollections.firstChild);
        }
    }

    document.getElementById("auto-name").disabled = !isLoggedIn;
    document.getElementById("auto-create-button").disabled = !isLoggedIn;
    document.getElementById("auto-delete-button").disabled = !isLoggedIn;
    document.getElementById("auto-update-button").disabled = !isLoggedIn;
    document.getElementById("auto-list").disabled = !isLoggedIn;
    document.getElementById("auto-only-publish-enabled").disabled = !isLoggedIn;
    document.getElementById("auto-type-extensions").disabled = !isLoggedIn;
    document.getElementById("auto-type-themes").disabled = !isLoggedIn;
    document.getElementById("auto-type-dicts").disabled = !isLoggedIn;
    document.getElementById("auto-type-langpacks").disabled = !isLoggedIn;
}

Bandwagon.Controller.Settings.invalidateAutoPublisher = function()
{
    var localAutoPublisher = bandwagonService.getLocalAutoPublisher();

    if (localAutoPublisher != null)
    {
        document.getElementById("auto-spinner").collapsed = true;
        document.getElementById("auto-create-button").collapsed = true;
        document.getElementById("auto-delete-button").removeAttribute("collapsed");
        document.getElementById("auto-update-button").removeAttribute("collapsed");
        document.getElementById("auto-name").value = localAutoPublisher.name;
        document.getElementById("auto-list").checked = localAutoPublisher.listed;
    }
}

Bandwagon.Controller.Settings.invalidateAllowIncompatibleInstall = function()
{
    var isExtensionsCheckCompatibility = Bandwagon.Preferences.getGlobalPreference("extensions.checkCompatibility", true);

    if (isExtensionsCheckCompatibility == null || isExtensionsCheckCompatibility == true)
    {
        document.getElementById("section-enable-on-checkcompatibilty-pref").setAttribute("collapsed", true);
    }
    else
    {
        document.getElementById("section-enable-on-checkcompatibilty-pref").removeAttribute("collapsed");
    }
}

Bandwagon.Controller.Settings.invalidateEmails = function()
{
    var previouslySharedEmailAddresses = bandwagonService.getPreviouslySharedEmailAddresses();

    document.getElementById("clear-emails-text").value = Bandwagon.Controller.Settings.stringBundle.getFormattedString("saved.emails.text", [previouslySharedEmailAddresses.length]);
    document.getElementById("clear-emails-button").disabled = (previouslySharedEmailAddresses.length == 0);
}

Bandwagon.Controller.Settings.doShowCollection = function()
{
    var collection = null;
    var selectedItem = Bandwagon.Controller.Settings.elemBandwagonCollections.selectedItem;

    if (selectedItem && Bandwagon.Controller.Settings.elemBandwagonCollections.selectedItem.collection)
        collection = Bandwagon.Controller.Settings.collections[Bandwagon.Controller.Settings.elemBandwagonCollections.selectedItem.collection.resourceURL];

    Bandwagon.Logger.debug("showing collection: " + (collection ? collection.name : "<none>"));

    Bandwagon.Controller.Settings.invalidateCustomizeCollectionUI();

    if (!collection)
    {
        Bandwagon.Controller.Settings.invalidate();
        return;
    }

    // ui defaults for customizing go here

    document.getElementById("textbox-updateinterval-quantity-percollection").value = document.getElementById("textbox-updateinterval-quantity").value;
    document.getElementById("menulist-updateinterval-units-percollection").selectedIndex = document.getElementById("menulist-updateinterval-units").selectedIndex;
    document.getElementById("checkbox-shownotifications-percollection").selectedIndex = document.getElementById("notify-all-group").selectedIndex;
    document.getElementById("textbox-addonsperpage-percollection").value = document.getElementById("textbox-addonsperpage-global").value;

    // customized update interval for this collection?

    if (collection.updateInterval == -1)
    {
        document.getElementById("updates-group").selectedIndex = 0;
    }
    else
    {
        document.getElementById("updates-group").selectedIndex = 1;
        
        var interval = Bandwagon.Util.intervalMillisecondsToUnits(collection.updateInterval*1000);
        document.getElementById("textbox-updateinterval-quantity-percollection").valueNumber = interval.interval;
        document.getElementById("menulist-updateinterval-units-percollection").selectedIndex = interval.units-1;
    }

    // customized notifications setting for this collection?
    
    if (collection.showNotifications == -1)
    {
        document.getElementById("notifications-group").selectedIndex = 0;
    }
    else
    {
        document.getElementById("notifications-group").selectedIndex = 1;

        if (collection.showNotifications == 1)
        {
            document.getElementById("checkbox-shownotifications-percollection").selectedIndex = 0;
        }
        else
        {
            document.getElementById("checkbox-shownotifications-percollection").selectedIndex = 1;
        }
    }

    // customized addonsperpage setting for this collection?
    
    if (collection.addonsPerPage == -1)
    {
        document.getElementById("perpage-group").selectedIndex = 0;
    }
    else
    {
        document.getElementById("perpage-group").selectedIndex = 1;

        document.getElementById("textbox-addonsperpage-percollection").value = collection.addonsPerPage;
    }

    Bandwagon.Controller.Settings.invalidate();
    Bandwagon.Controller.Settings.invalidateCustomizeCollectionUI();
}

Bandwagon.Controller.Settings.doSaveCollection = function()
{
    Bandwagon.Logger.debug("in doSaveCollection()");

    // save settings to local copy of the collection objects

    var collection = Bandwagon.Controller.Settings.collections[Bandwagon.Controller.Settings.elemBandwagonCollections.selectedItem.collection.resourceURL];

    if (!collection)
        return;

    // save update interval

    if (document.getElementById("updates-group").selectedIndex == 0)
    {
        collection.updateInterval = -1;
    }
    else
    {
        collection.updateInterval = Bandwagon.Util.intervalUnitsToMilliseconds(document.getElementById("textbox-updateinterval-quantity-percollection").valueNumber, document.getElementById("menulist-updateinterval-units-percollection").selectedIndex+1) / 1000;
    }
    
    // save notifications setting
    
    if (document.getElementById("notifications-group").selectedIndex == 0)
    {
        collection.showNotifications = -1;
    }
    else
    {
        collection.showNotifications = (document.getElementById("checkbox-shownotifications-percollection").selectedIndex==0?1:0);
    }

    // save addons per page
    
    if (document.getElementById("perpage-group").selectedIndex == 0)
    {
        collection.addonsPerPage = -1
    }
    else
    {
        collection.addonsPerPage = document.getElementById("textbox-addonsperpage-percollection").value;
    }

    if (document.getElementById("bandwagon-settings").instantApply)
    {
        Bandwagon.Controller.Settings.doAccept();
    }

    Bandwagon.Controller.Settings.invalidateCustomizeCollectionUI();
}

Bandwagon.Controller.Settings._repopulateCollectionsList = function()
{
    Bandwagon.Logger.debug("in _repopulateCollectionsList");

    // first clear the list

    while (Bandwagon.Controller.Settings.elemBandwagonCollections.hasChildNodes())
    {
        Bandwagon.Controller.Settings.elemBandwagonCollections.removeChild(Bandwagon.Controller.Settings.elemBandwagonCollections.firstChild);
    }

    // repopulate with collections

    for (var id in bandwagonService.collections)
    {
        var collection = bandwagonService.collections[id];

        if (collection == null)
            return;

        if (!collection.subscribed)
            continue;

        const XULNS = "http://www.mozilla.org/keymaster/gatekeeper/there.is.only.xul";
        var elemBandwagonCollection = document.createElementNS(XULNS, "bandwagonCollection");
        elemBandwagonCollection.setAttribute("view", "settings");

        elemBandwagonCollection.collection = collection;
        elemBandwagonCollection.controller = Bandwagon.Controller.Settings;

        Bandwagon.Controller.Settings.elemBandwagonCollections.appendChild(elemBandwagonCollection);
    }

    // create the local copy of the collections hash with the properties we need

    Bandwagon.Controller.Settings.collections = {};

    for (var id in bandwagonService.collections)
    {
        var collection = bandwagonService.collections[id];

        if (!collection.subscribed)
            continue;

        Bandwagon.Controller.Settings.collections[id] =
        {
            name: collection.name,
            url: collection.resourceURL,
            updateInterval: collection.updateInterval,
            showNotifications: collection.showNotifications,
            addonsPerPage: collection.addonsPerPage
        };
    }
}

Bandwagon.Controller.Settings.doLogin = function()
{
    // open the extensions manager window.
    
    const EMTYPE = "Extension:Manager";
    var wm = Components.classes["@mozilla.org/appshell/window-mediator;1"]
        .getService(Components.interfaces.nsIWindowMediator);
    var theEM = wm.getMostRecentWindow(EMTYPE);

    if (theEM)
    {
        theEM.focus();
        theEM.showView('bandwagon-collections');
    }
    else
    {
        const EMURL = "chrome://mozapps/content/extensions/extensions.xul";
        const EMFEATURES = "chrome,menubar,extra-chrome,toolbar,dialog=no,resizable";
        window.openDialog(EMURL, "", EMFEATURES);
    }

    window.close();
}

Bandwagon.Controller.Settings.doLogout = function()
{
    bandwagonService.deauthenticate();
}

Bandwagon.Controller.Settings._openLocalizedURL = function(url)
{
    var locale = Bandwagon.Util.getBrowserLocale();

    if (locale && locale != "")
        url = url.replace(/en-US/, locale, "g");

    var mainWindow = Bandwagon.Util.getMostRecentAppWindow();

    if (mainWindow)
    {
        var tab = mainWindow.getBrowser().addTab(url);
        mainWindow.getBrowser().selectedTab = tab;
        mainWindow.focus();
    }
    else
    {
        window.open(url);
    }
}

Bandwagon.Controller.Settings._autoCreateToggleUIEnabledState = function(disableUI)
{
    document.getElementById("auto-create-button").disabled = disableUI;
    document.getElementById("auto-name").disabled = disableUI;
    document.getElementById("auto-list").disabled = disableUI;
    document.getElementById("auto-type-extensions").disabled = disableUI;
    document.getElementById("auto-type-themes").disabled = disableUI;
    document.getElementById("auto-type-dicts").disabled = disableUI;
    document.getElementById("auto-type-langpacks").disabled = disableUI;
    document.getElementById("auto-spinner").collapsed = !disableUI;
}

Bandwagon.Controller.Settings.doCreateAutoPublisher = function()
{
    // check form
    document.getElementById("auto-error").value = "";
    document.getElementById("auto-error").style.color = 'red';

    var collectionName =  document.getElementById("auto-name").value;

    if (!collectionName || collectionName == "" || !collectionName.match(/.*\w.*/))
    {
        document.getElementById("auto-error").value = Bandwagon.Controller.Settings.stringBundle.getFormattedString("auto.invalid.name", [collectionName]);
        return;
    }

    var autoPublishExtensions = document.getElementById("auto-type-extensions").checked;
    var autoPublishThemes = document.getElementById("auto-type-themes").checked;
    var autoPublishDicts = document.getElementById("auto-type-dicts").checked;
    var autoPublishLangPacks = document.getElementById("auto-type-langpacks").checked;
    var autoPublishDisabled = !document.getElementById("auto-only-publish-enabled").checked;

    if (!autoPublishExtensions && !autoPublishThemes && !autoPublishDicts && !autoPublishLangPacks)
    {
        document.getElementById("auto-error").value = Bandwagon.Controller.Settings.stringBundle.getString("auto.please.select.type");
        return;
    }

    // disable ui settings, show throbber

    Bandwagon.Controller.Settings._autoCreateToggleUIEnabledState(true);

    // create the collection object
    //
    // TODO this logic should not be in the UI controller

    var collection = new Bandwagon.Model.Collection(Bandwagon);

    collection.name = collectionName;
    collection.description = Bandwagon.Controller.Settings.stringBundle.getString("auto.create.description");
    collection.listed = document.getElementById("auto-list").checked;
    collection.writable = true;
    collection.showNotifications = false;
    collection.updateInterval = 60 * 60 * 24;
    collection.addonsPerPage = Bandwagon.Preferences.getPreference("global.addonsperpage");
    collection.status = collection.STATUS_NEW;

    collection.autoPublishExtensions = autoPublishExtensions;
    collection.autoPublishThemes = autoPublishThemes;
    collection.autoPublishDicts = autoPublishDicts;
    collection.autoPublishLangPacks = autoPublishLangPacks;
    collection.autoPublishDisabled = autoPublishDisabled;
    
    Bandwagon.Preferences.setPreferenceList("autopublished.extensions", []);

    // send the api call
       
    var newCollectionCallback = function(event)
    {
        if (event.isError())
        {
            if (event.getError().getMessage() == "invalid_parameters")
            {
                try
                {
                  document.getElementById("auto-error").value = Bandwagon.Controller.Settings.stringBundle.getString("auto.create.invalid.parameters");
                }
                catch (e)
                {
                  // l10n fallback string
                  document.getElementById("auto-error").value = Bandwagon.Controller.Settings.stringBundle.getString("auto.create.internal.error");
                }
            }
            else
            {
                document.getElementById("auto-error").value = Bandwagon.Controller.Settings.stringBundle.getString("auto.create.internal.error");
            }

            Bandwagon.Controller.Settings._autoCreateToggleUIEnabledState(false);
        }
        else
        {
            Bandwagon.Preferences.setPreference("local.autopublisher", collection.resourceURL);

            // we also need to subscribe to this collection
            bandwagonService.subscribeToCollection(collection);

            // publish extensions to this collection
            bandwagonService.autopublishExtensions();

            // on callback of above, tell user we're done
            document.getElementById("auto-error").style.color = 'green';
            document.getElementById("auto-error").value = Bandwagon.Controller.Settings.stringBundle.getString("auto.create.done");

            // clean-up
            document.getElementById("auto-create-button").collapsed = true;
            document.getElementById("auto-delete-button").collapsed = false;
            document.getElementById("auto-update-button").collapsed = false;
            document.getElementById("auto-spinner").collapsed = true;

            Bandwagon.Controller.Settings._autoCreateToggleUIEnabledState(false);
        }
    }
    
    bandwagonService.newCollection(collection, newCollectionCallback);
}

Bandwagon.Controller.Settings.doUpdateAutoPublisher = function()
{
    document.getElementById("auto-error").value = "";
    document.getElementById("auto-error").style.color = 'red';

    var collectionName =  document.getElementById("auto-name").value;

    if (!collectionName || collectionName == "" || !collectionName.match(/.*\w.*/))
    {
        document.getElementById("auto-error").value = Bandwagon.Controller.Settings.stringBundle.getFormattedString("auto.invalid.name", [collectionName]);
        return;
    }

    var promptService;

    try
    {
        promptService = Components.classes["@mozilla.org/embedcomp/prompt-service;1"].getService();
        promptService = promptService.QueryInterface(Components.interfaces.nsIPromptService);
    } catch (e) { return; }

    var promptTitle = document.getElementById("auto-update-button").label;
    var promptMsg = Bandwagon.Controller.Settings.stringBundle.getString("auto.update.confirm.message");
    var proceed = promptService.confirm(
            window,
            promptTitle,
            promptMsg
    );

    if (!proceed)
    {
        return;
    }

    document.getElementById("auto-spinner").collapsed = false;

    var collection = bandwagonService.getLocalAutoPublisher();

    collection.name = document.getElementById("auto-name").value;
    collection.listed = document.getElementById("auto-list").checked;
    collection.autoPublishExtensions = document.getElementById("auto-type-extensions").checked;
    collection.autoPublishThemes = document.getElementById("auto-type-themes").checked;
    collection.autoPublishDicts = document.getElementById("auto-type-dicts").checked;
    collection.autoPublishLangPacks = document.getElementById("auto-type-langpacks").checked;
    collection.autoPublishDisabled = !document.getElementById("auto-only-publish-enabled").checked;
 
    var callback = function(event)
    {
        document.getElementById("auto-spinner").collapsed = true;

        if (event.isError())
        {
            document.getElementById("auto-error").value = Bandwagon.Controller.Settings.stringBundle.getString("auto.update.internal.error");
        }
        else
        {
            Bandwagon.Preferences.setPreference("local.autopublisher", collection.resourceURL);

            // on callback of above, refresh collection list
            bandwagonService.forceCheckAllForUpdatesAndUpdateCollectionsList();

            Bandwagon.Controller.Settings._autoCreateToggleUIEnabledState(false);

            // on callback of above, tell user we're done
            document.getElementById("auto-error").style.color = 'green';
            document.getElementById("auto-error").value = Bandwagon.Controller.Settings.stringBundle.getString("auto.update.done");

            // clean-up
            document.getElementById("auto-create-button").collapsed = true;
            document.getElementById("auto-update-button").collapsed = false;
            document.getElementById("auto-delete-button").collapsed = false;
            document.getElementById("auto-spinner").collapsed = true;
        }
    }

    bandwagonService.updateCollectionDetails(collection, callback);
}

Bandwagon.Controller.Settings.doDeleteAutoPublisher = function()
{
    document.getElementById("auto-spinner").collapsed = false;
    document.getElementById("auto-error").value = "";
    document.getElementById("auto-error").style.color = 'red';

    var localAutoPublisher = bandwagonService.getLocalAutoPublisher();

    var callback = function(event)
    {
        document.getElementById("auto-spinner").collapsed = true;

        if (event.isError())
        {
            document.getElementById("auto-error").value = Bandwagon.Controller.Settings.stringBundle.getString("auto.delete.internal.error");
        }
        else
        {
            Bandwagon.Preferences.setPreference("local.autopublisher", "");

            // on callback of above, refresh collection list
            bandwagonService.forceCheckAllForUpdatesAndUpdateCollectionsList();

            Bandwagon.Controller.Settings._autoCreateToggleUIEnabledState(false);

            // on callback of above, tell user we're done
            document.getElementById("auto-error").style.color = 'green';
            document.getElementById("auto-error").value = Bandwagon.Controller.Settings.stringBundle.getString("auto.delete.done");

            // clean-up
            document.getElementById("auto-create-button").collapsed = false;
            document.getElementById("auto-update-button").collapsed = true;
            document.getElementById("auto-delete-button").collapsed = true;
            document.getElementById("auto-spinner").collapsed = true;
        }
    }

    bandwagonService.deleteCollection(localAutoPublisher, callback);
}

Bandwagon.Controller.Settings.doClearEmails = function()
{
    bandwagonService.clearPreviouslySharedEmailAddresses();

    Bandwagon.Controller.Settings.invalidateEmails();
}

Bandwagon.Controller.Settings.doAccept = function()
{
    Bandwagon.Logger.debug("In doAccept()");

    // called:
    // - when user clicks 'Ok' (on systems that show the ok button)
    // - when user changes collection properties (on systems that don't show buttons)

    // copy the locally update settings over the global collection settings

    for (var id in Bandwagon.Controller.Settings.collections)
    {
        var localCollection = Bandwagon.Controller.Settings.collections[id];
        var bwCollection = bandwagonService.collections[id];

        if (!localCollection || !bwCollection)
            continue;

        if (bwCollection.addonsPerPage != localCollection.addonsPerPage)
        {
            bwCollection.addonsPerPage = localCollection.addonsPerPage;
            Bandwagon.Preferences.notifyObservers("addonsperpage:" + bwCollection.resourceURL);
        }

        bwCollection.updateInterval = localCollection.updateInterval;
        bwCollection.showNotifications = localCollection.showNotifications;
    }
}

Bandwagon.Controller.Settings.doCancel = function()
{
    // nothing
}

Bandwagon.Controller.Settings.doUnsubscribe = function()
{
    var collection = Bandwagon.Controller.Settings.elemBandwagonCollections.selectedItem.collection;

    if (collection == null)
        return;

    var promptService = Components.classes["@mozilla.org/embedcomp/prompt-service;1"].getService(Components.interfaces.nsIPromptService);
    var check = {value: false};
    var flags = promptService.BUTTON_POS_0 * promptService.BUTTON_TITLE_IS_STRING + promptService.BUTTON_POS_1 * promptService.BUTTON_TITLE_IS_STRING;
    var button = promptService.confirmEx(
        window,
        Bandwagon.Controller.Settings.stringBundle2.getString("unsubscribe.confirm.title"),
        Bandwagon.Controller.Settings.stringBundle2.getString("unsubscribe.confirm.label"),
        flags,
        Bandwagon.Controller.Settings.stringBundle2.getString("unsubscribe.confirm.button0"),
        Bandwagon.Controller.Settings.stringBundle2.getString("unsubscribe.confirm.button1"),
        null,
        null,
        check);

    if (button == 0)
    {
        var callback = function(event)
        {
            if (event.isError())
            {
                window.alert(Bandwagon.Controller.Settings.stringBundle2.getString("unsubscribe.error"));
            }
        }

        bandwagonService.unsubscribeFromCollection(collection, callback);
    }
}

Bandwagon.Controller.Settings.doUpdateIntervalScopeChange = function(event)
{
    if (document.getElementById("updates-group").selectedIndex == 0) // Use default
    {
        document.getElementById("extensions.bandwagon.global.update.interval").value = document.getElementById("textbox-updateinterval-quantity").valueNumber;
        document.getElementById("extensions.bandwagon.global.update.units").value = document.getElementById("menulist-updateinterval-units").selectedIndex + 1;
    }
    else
    {
        // Not sure if anything needs to be done here
    }

    Bandwagon.Controller.Settings.doShowCollection();
}

Bandwagon.Controller.Settings.doShowNotificationsScopeChange = function(event)
{
    document.getElementById("extensions.bandwagon.global.notify.enabled").value = (document.getElementById("notifications-group").selectedIndex == 0);

    Bandwagon.Controller.Settings.doShowCollection();
}


window.addEventListener("load", Bandwagon.Controller.Settings.init, true);
window.addEventListener("unload", Bandwagon.Controller.Settings.uninit, true);
