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

Bandwagon.Controller.CollectionsPane = new function()
{
    this.initialized = false;
    this.initializedBindings = false;

    this.preferredCollection = null;

    this.elemBandwagonCollections = null;
    this.elemBandwagonAddons = null;
    this.elemBandwagonButtonViewSite = null;
    //this.elemBandwagonButtonUpdate = null;
    this.elemBandwagonButtonRemove = null;
    this.elemBandwagonCollectionTitle = null;
    this.elemBandwagonCollectionDescription = null;
    this.elemBandwagonCollectionsNotification = null;
    this.elemBandwagonCollectionIcon = null;
    this.elemBandwagonCollectionDeck = null;
    this.stringBundle = null;
    this.loginInProcess = false;

    //this.previewNotificationVal = "bandwagon-collection-preview";
}

Bandwagon.Controller.CollectionsPane.init = function()
{
    if (Bandwagon.Controller.CollectionsPane.initialized == true) return;

    Bandwagon.Logger.debug("Initializing Bandwagon.Controller.CollectionsPane");

    this.elemBandwagonCollections = document.getElementById("bandwagon-collections-list");
    this.elemBandwagonAddons = document.getElementById("bandwagon-addons-list");
    this.elemBandwagonButtonViewSite = document.getElementById("bandwagon-button-viewsite");
    //this.elemBandwagonButtonUpdate = document.getElementById("bandwagon-button-update");
    this.elemBandwagonButtonRemove = document.getElementById("bandwagon-button-remove");
    this.elemBandwagonExtensionsDeck = document.getElementById("bandwagon-extensions-deck");
    this.elemBandwagonCollectionTitle = document.getElementById("bandwagon-collection-title");
    this.elemBandwagonCollectionDescription = document.getElementById("bandwagon-collection-description");
    this.elemBandwagonCollectionsNotification = document.getElementById("bandwagon-collections-notification");
    this.elemBandwagonCollectionDeck = document.getElementById("bandwagon-collection-deck");
    this.elemBandwagonCollectionIcon = document.getElementById("bandwagon-collection-icon");

    Bandwagon.Controller.CollectionsPane._repopulateCollectionsList();
    Bandwagon.Controller.CollectionsPane.invalidate();

    this.elemBandwagonCollections.addEventListener("select", Bandwagon.Controller.CollectionsPane.doShowCollection, true);
    this.elemBandwagonAddons.addEventListener("select", Bandwagon.Controller.CollectionsPane.doExpandAddon, true);

    bandwagonService.registerCollectionUpdateObserver(Bandwagon.Controller.CollectionsPane.collectionUpdateObserver);
    bandwagonService.registerCollectionListChangeObserver(Bandwagon.Controller.CollectionsPane.collectionListChangeObserver);
    bandwagonService.registerAuthenticationStatusChangeObserver(Bandwagon.Controller.CollectionsPane.authenticationStatusChangeObserver);

    Bandwagon.Preferences.addObserver(Bandwagon.Controller.CollectionsPane.prefObserver);
    Bandwagon.Preferences.addGlobalObserver(Bandwagon.Controller.CollectionsPane.prefObserver, "extensions.bandwagon.allow.incompatible.");

    Components.classes["@mozilla.org/observer-service;1"].getService(Components.interfaces.nsIObserverService).addObserver(Bandwagon.Controller.CollectionsPane.prefObserver, "nsPref:changed", false);
}

/**
 * Triggered when the user navigates to the collections pane from a different view in the extensions manager
 */
Bandwagon.Controller.CollectionsPane.onViewSelect = function()
{
    Bandwagon.Logger.debug("in Bandwagon.Controller.CollectionsPane.onViewSelect()");

    if (!bandwagonService.isAuthenticated() || this.loginInProcess == true)
    {
        window.setTimeout(function() { document.getElementById("login").focus(); }, 200);
    }
    else if (Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.selectedItem != null)
    {
        // make sure the expanded collection item is scrolled into view
        var elemsAddonExpanded = Bandwagon.Controller.CollectionsPane.elemBandwagonAddons.getElementsByTagName("bandwagonAddonExpanded");

        if (elemsAddonExpanded && elemsAddonExpanded[0])
        {
            try
            {
                Bandwagon.Controller.CollectionsPane.elemBandwagonAddons.ensureElementIsVisible(elemsAddonExpanded[0]);
            } catch (e) {}
        }
    }
}

Bandwagon.Controller.CollectionsPane.bindingsReady = function()
{
    // the bindings are ready, do any final initialization

    if (Bandwagon.Controller.CollectionsPane.initializedBindings == false)
    {
        Bandwagon.Controller.CollectionsPane._selectPreferredCollection();
        Bandwagon.Controller.CollectionsPane.initializedBindings = true;
    }

    Bandwagon.Controller.CollectionsPane.initialized = true;
}

Bandwagon.Controller.CollectionsPane.uninit = function()
{
    if (Bandwagon.Controller.CollectionsPane.initialized != true) return;

    Bandwagon.Logger.debug("Uninitializing Bandwagon.Controller.CollectionsPane");

    Bandwagon.Controller.CollectionsPane.initialized = false;

    bandwagonService.unregisterCollectionUpdateObserver(Bandwagon.Controller.CollectionsPane.collectionUpdateObserver);
    bandwagonService.unregisterCollectionListChangeObserver(Bandwagon.Controller.CollectionsPane.collectionListChangeObserver);
    bandwagonService.unregisterAuthenticationStatusChangeObserver(Bandwagon.Controller.CollectionsPane.authenticationStatusChangeObserver);

    Bandwagon.Preferences.removeObserver(Bandwagon.Controller.CollectionsPane.prefObserver);
    Bandwagon.Preferences.removeGlobalObserver(Bandwagon.Controller.CollectionsPane.prefObserver, "extensions.bandwagon.allow.incompatible.");

    // now is a good time to save collections to storage
    if (Bandwagon.COMMIT_NOW)
        bandwagonService.commitAll();
}

/**
 * Updates the interface disabled-ness based on the state of the selected collection, etc.
 */
Bandwagon.Controller.CollectionsPane.invalidate = function()
{
    if (Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.selectedItem == null)
    {
        Bandwagon.Controller.CollectionsPane.elemBandwagonButtonViewSite.disabled = true;
        //Bandwagon.Controller.CollectionsPane.elemBandwagonButtonUpdate.disabled = true;
        Bandwagon.Controller.CollectionsPane.elemBandwagonButtonRemove.disabled = true;
        Bandwagon.Controller.CollectionsPane.elemBandwagonCollectionTitle.collapsed = true;
        Bandwagon.Controller.CollectionsPane.elemBandwagonCollectionDescription.collapsed = true;

        Bandwagon.Controller.CollectionsPane._repopulateAddonsList(null);

        Bandwagon.Controller.CollectionsPane._invalidateExtensionsDeck();
    }
    else
    {
        Bandwagon.Controller.CollectionsPane.elemBandwagonButtonViewSite.disabled = false;
        //Bandwagon.Controller.CollectionsPane.elemBandwagonButtonUpdate.disabled = false;
        Bandwagon.Controller.CollectionsPane.elemBandwagonButtonRemove.disabled = false;
        Bandwagon.Controller.CollectionsPane.elemBandwagonCollectionTitle.collapsed = false;
        Bandwagon.Controller.CollectionsPane.elemBandwagonCollectionDescription.collapsed = false;

        var collection = Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.selectedItem.collection;

        if (collection.status == collection.STATUS_LOADING)
        {
            Bandwagon.Controller.CollectionsPane.elemBandwagonCollectionDeck.selectedIndex = 1;
        }
        else if (collection.status == collection.STATUS_LOADERROR)
        {
            Bandwagon.Controller.CollectionsPane.elemBandwagonCollectionDeck.selectedIndex = 3;
        }
        else if (collection.hasAddon() == 0)
        {
            Bandwagon.Controller.CollectionsPane.elemBandwagonCollectionDeck.selectedIndex = 2;
        }
        else
        {
            Bandwagon.Controller.CollectionsPane.elemBandwagonCollectionDeck.selectedIndex = 0;
        }
    }
}

Bandwagon.Controller.CollectionsPane._invalidateExtensionsDeck = function()
{
    var elemsBandwagonCollection = Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.getElementsByTagName("bandwagonCollection");

    if (!bandwagonService.isAuthenticated() || this.loginInProcess == true)
    {
        Bandwagon.Controller.CollectionsPane.elemBandwagonExtensionsDeck.selectedIndex = 2;
        window.setTimeout(function() { document.getElementById("login").focus(); }, 200);
    }
    else if (elemsBandwagonCollection.length == 0)
    {
        Bandwagon.Controller.CollectionsPane.elemBandwagonExtensionsDeck.selectedIndex = 1;
    }
    else
    {
        Bandwagon.Controller.CollectionsPane.elemBandwagonExtensionsDeck.selectedIndex = 0;
    }
}

Bandwagon.Controller.CollectionsPane.collectionListChangeObserver = function()
{
    Bandwagon.Logger.debug("In Bandwagon.Controller.CollectionsPane.collectionListChangeObserver()");

    var prevCollection = null;

    if (Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.selectedItem)
    {
        prevCollection = Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.selectedItem.collection;
    }

    Bandwagon.Controller.CollectionsPane._repopulateCollectionsList();
    Bandwagon.Controller.CollectionsPane._invalidateExtensionsDeck();

    if (prevCollection != null && bandwagonService.collections[prevCollection.resourceURL])
    {
        var retval = Bandwagon.Controller.CollectionsPane._selectCollection(prevCollection);

        if (!retval)
            Bandwagon.Controller.CollectionsPane._selectPreferredCollection();
    }
    else
    {
        Bandwagon.Controller.CollectionsPane._selectPreferredCollection();
    }
}

Bandwagon.Controller.CollectionsPane.collectionUpdateObserver = function(collection)
{
    if (collection == null)
    {
        Bandwagon.Logger.debug("In Bandwagon.Controller.CollectionsPane.collectionUpdateObserver() with collection <null>");
    }
    else
    {
        Bandwagon.Logger.debug("In Bandwagon.Controller.CollectionsPane.collectionUpdateObserver() with collection '" + collection.toString() + "'");

        // update the unread count for this collection

        const XULNS = "http://www.mozilla.org/keymaster/gatekeeper/there.is.only.xul";
        var elemsBandwagonCollection = Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.getElementsByTagNameNS(XULNS, "bandwagonCollection");

        for (var i=0; i<elemsBandwagonCollection.length; i++)
        {
            if (elemsBandwagonCollection[i].collection.equals(collection))
            {
                elemsBandwagonCollection[i].unread = collection.getUnreadAddons().length;
                break;
            }
        }

        // if this collection is currently selected, update the view

        if (Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.selectedItem && collection.equals(Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.selectedItem.collection))
        {
            Bandwagon.Controller.CollectionsPane._repopulateAddonsList(collection);
        }
    }

    Bandwagon.Controller.CollectionsPane.invalidate();
}

Bandwagon.Controller.CollectionsPane.authenticationStatusChangeObserver = function()
{
    Bandwagon.Controller.CollectionsPane._invalidateExtensionsDeck();
}

Bandwagon.Controller.CollectionsPane.doUpdateAll = function(event)
{
    Bandwagon.Controller.CollectionsPane.elemBandwagonCollectionDeck.selectedIndex = 1;

    bandwagonService.forceCheckAllForUpdatesAndUpdateCollectionsList();
}

Bandwagon.Controller.CollectionsPane.doSubscribe = function(event)
{
    Bandwagon.Controller.CollectionsPane._openLocalizedURL(Bandwagon.COLLECTIONSPANE_DO_SUBSCRIBE_URL);
}

Bandwagon.Controller.CollectionsPane.doLogin = function(event)
{
    var uname = document.getElementById("login").value;
    var pwd = document.getElementById("password").value;

    // Some client side checking for blank login details
    if (!uname || uname == "" || !uname.match(/.*\w.*/) ||
        !pwd || pwd == "" || !pwd.match(/.*\w.*/))
    {
        document.getElementById("auth-error").textContent = Bandwagon.Controller.ExtensionsOverlay.stringBundle.getString("login.error");
        document.getElementById("auth-error").collapsed = false;
        return;
    }

    this.loginInProcess = true;

    document.getElementById("auth-button").disabled = true;
    document.getElementById("auth-spinner").style.visibility = "visible";
    document.getElementById("auth-error").collapsed = true;

    var callback2 = function(event)
    {
        document.getElementById("auth-button").disabled = false;
        document.getElementById("auth-spinner").style.visibility = "hidden";
        document.getElementById("password").value = ""; 
        Bandwagon.Controller.CollectionsPane.loginInProcess = false;
        Bandwagon.Controller.CollectionsPane._invalidateExtensionsDeck();
    }

    var callback1 = function(event)
    {
        if (event.isError())
        { 
            // show err
            document.getElementById("auth-error").textContent = Bandwagon.Controller.ExtensionsOverlay.stringBundle.getString("login.error");
            document.getElementById("auth-error").collapsed = false;
            document.getElementById("auth-button").disabled = false;
            document.getElementById("auth-spinner").style.visibility = "hidden";
        }
        else
        {
            bandwagonService.updateCollectionsList(callback2);
        }
    }

    bandwagonService.authenticate(uname, pwd, callback1);
}

Bandwagon.Controller.CollectionsPane.doSettings = function(event)
{
    var prefSvc = Components.classes["@mozilla.org/preferences-service;1"].
        getService(Components.interfaces.nsIPrefService);
    var prefServiceCache = prefSvc.getBranch(null);
    var instantApply = prefServiceCache.getBoolPref("browser.preferences.instantApply");
    var flags = "chrome,titlebar,toolbar,centerscreen" + (instantApply ? ",dialog=no" : ",modal");

    var wm = Components.classes["@mozilla.org/appshell/window-mediator;1"]
        .getService(Components.interfaces.nsIWindowMediator);
    var win = wm.getMostRecentWindow("Bandwagon:Settings");

    if (win)
    {
        win.focus();
    }
    else
    {
        window.openDialog("chrome://bandwagon/content/ui/settings.xul", 
                "bandwagonsettings",
                flags);
    }
}

Bandwagon.Controller.CollectionsPane.doViewSite = function(event)
{
    if (Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.selectedItem == null)
        return;

    var collection = Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.selectedItem.collection;

    if (collection == null || !collection.links["view"])
        return;

    Bandwagon.Controller.CollectionsPane._openURL(collection.links["view"]);
}

Bandwagon.Controller.CollectionsPane.doUpdate = function(event)
{
    if (Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.selectedItem == null)
        return;

    Bandwagon.Controller.CollectionsPane.elemBandwagonCollectionDeck.selectedIndex = 1;

    var collection = Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.selectedItem.collection;

    if (collection == null)
        return;

    bandwagonService.forceCheckForUpdates(collection);
}

Bandwagon.Controller.CollectionsPane.doUnsubscribe = function(event)
{
    if (Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.selectedItem == null)
        return;

    var collection = Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.selectedItem.collection;

    if (collection == null)
        return;

    var promptService = Components.classes["@mozilla.org/embedcomp/prompt-service;1"].getService(Components.interfaces.nsIPromptService);
    var check = {value: false};
    var flags = promptService.BUTTON_POS_0 * promptService.BUTTON_TITLE_IS_STRING + promptService.BUTTON_POS_1 * promptService.BUTTON_TITLE_IS_STRING;
    var button = promptService.confirmEx(
        window,
        Bandwagon.Controller.ExtensionsOverlay.stringBundle.getString("unsubscribe.confirm.title"),
        Bandwagon.Controller.ExtensionsOverlay.stringBundle.getString("unsubscribe.confirm.label"),
        flags,
        Bandwagon.Controller.ExtensionsOverlay.stringBundle.getString("unsubscribe.confirm.button0"),
        Bandwagon.Controller.ExtensionsOverlay.stringBundle.getString("unsubscribe.confirm.button1"),
        null,
        null,
        check);

    if (button == 0)
    {
        var callback = function(event)
        {
            if (event.isError())
            {
                window.alert(Bandwagon.Controller.ExtensionsOverlay.stringBundle.getString("unsubscribe.error"));
            }
        }

        bandwagonService.unsubscribeFromCollection(collection, callback);
    }
}

Bandwagon.Controller.CollectionsPane.doShowCollection = function()
{
    var collection = null;

    var selectedElemBandwagonCollection = Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.selectedItem;

    if (selectedElemBandwagonCollection != null)
    {
        collection = selectedElemBandwagonCollection.collection;
    }

    // if collection == null, then display will be cleared

    // misc. ui 

    Bandwagon.Controller.CollectionsPane.elemBandwagonCollectionTitle.textContent = (collection?(collection.name?collection.name:collection.resourceURL):"");

    if (collection && collection.description != "")
    {
        Bandwagon.Controller.CollectionsPane.elemBandwagonCollectionDescription.textContent = collection.description;
        Bandwagon.Controller.CollectionsPane.elemBandwagonCollectionDescription.removeAttribute("collapsed");
    }
    else
    {
        Bandwagon.Controller.CollectionsPane.elemBandwagonCollectionDescription.textContent = "";
        Bandwagon.Controller.CollectionsPane.elemBandwagonCollectionDescription.setAttribute("collapsed", true);
    }

    if (collection.iconURL != "")
    {
        Bandwagon.Controller.CollectionsPane.elemBandwagonCollectionIcon.src = collection.iconURL;
    }
    else
    {
        Bandwagon.Controller.CollectionsPane.elemBandwagonCollectionIcon.src = "chrome://bandwagon/skin/images/icon32.png";
    }

    Bandwagon.Controller.CollectionsPane.elemBandwagonCollectionsNotification.notificationsHidden = true;

    // show items

    Bandwagon.Controller.CollectionsPane._repopulateAddonsList(collection);

    // invalidate

    Bandwagon.Controller.CollectionsPane.invalidate();

    // show the loading dialog if needed

    if (collection && collection.status == collection.STATUS_LOADING)
    {
        Bandwagon.Controller.CollectionsPane.elemBandwagonCollectionDeck.selectedIndex = 1;
    }

    // set all items in this collection to be "read"
    // if we've just opened this dialog, don't update the read count

    if (collection)
    {
        collection.setAllRead();

        if (Bandwagon.Controller.CollectionsPane.initialized)
        {
            Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.selectedItem.unread = 0;
        }
    }
}

Bandwagon.Controller.CollectionsPane.doExpandAddon = function(event)
{
    if (event)
        event.preventDefault();

    if (Bandwagon.Controller.CollectionsPane.elemBandwagonAddons.selectedItem && Bandwagon.Controller.CollectionsPane.elemBandwagonAddons.selectedItem.nodeName == "bandwagonAddonExpanded")
    {
        return;
    }

    var elemsAddonExpanded = Bandwagon.Controller.CollectionsPane.elemBandwagonAddons.getElementsByTagName("bandwagonAddonExpanded");

    for (var i=0; i<elemsAddonExpanded.length; i++)
    {
        Bandwagon.Controller.CollectionsPane.elemBandwagonAddons.removeChild(elemsAddonExpanded[i]);
    }

    var elemsAddon = Bandwagon.Controller.CollectionsPane.elemBandwagonAddons.childNodes;

    for (var i=0; i<elemsAddon.length; i++)
    {
        elemsAddon[i].collapsed = false;
    }

    var selectedElemBandwagonAddon = Bandwagon.Controller.CollectionsPane.elemBandwagonAddons.selectedItem;

    if (selectedElemBandwagonAddon != null && selectedElemBandwagonAddon.addon != null)
    {
        selectedElemBandwagonAddon.read = true;

        var addon = selectedElemBandwagonAddon.addon;
        addon.read = true;

        Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.selectedItem.unread = Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.selectedItem.collection.getUnreadAddons().length;

        // collapse this, show the expanded binding

        const XULNS = "http://www.mozilla.org/keymaster/gatekeeper/there.is.only.xul";
        var elemBandwagonAddonExpanded = document.createElementNS(XULNS, "bandwagonAddonExpanded");

        elemBandwagonAddonExpanded.addon = addon;

        try
        {
        Bandwagon.Controller.CollectionsPane.elemBandwagonAddonExpanded.setAddon(addon);
        } catch (e) {}

        Bandwagon.Controller.CollectionsPane.elemBandwagonAddons.insertBefore(elemBandwagonAddonExpanded, selectedElemBandwagonAddon);

        selectedElemBandwagonAddon.collapsed = true;
    }
}

Bandwagon.Controller.CollectionsPane.doMoreInfo = function(event)
{
    if (event)
        event.preventDefault();

    if (Bandwagon.Controller.CollectionsPane.elemBandwagonAddons == null)
        return;

    Bandwagon.Controller.CollectionsPane._openURL(Bandwagon.Controller.CollectionsPane.elemBandwagonAddons.selectedItem.addon.learnmore);
}

Bandwagon.Controller.CollectionsPane.doAddToFirefox = function()
{
    if (Bandwagon.Controller.CollectionsPane.elemBandwagonAddons.selectedItem == null)
        return;

    var addon = Bandwagon.Controller.CollectionsPane.elemBandwagonAddons.selectedItem.addon;

    if (!isXPInstallEnabled())
        return;

    if (addon.eula && addon.eula != "")
    {
        var eula = {
          name: addon.name,
          text: addon.eula,
          accepted: false
        };

        window.openDialog("chrome://mozapps/content/extensions/eula.xul", "_blank",
                          "chrome,dialog,modal,centerscreen,resizable=no", eula);

        if (!eula.accepted)
            return;
    }

    var installer = addon.getInstaller(Bandwagon.Util.getHostEnvironmentInfo().os);

    if (!installer)
    {
        Bandwagon.Logger.warn("No compatible os targets found.");
        return;
    }

    var params = [];
    params[addon.name] = installer;

    // TODO do some user feedback here?

    var callback = function(url, status)
    {
        Bandwagon.Logger.info("Finished installing '" + url + "'; status = " + status);

        // TODO some user feedback here?
    }

    InstallTrigger.install(params, callback);
}

Bandwagon.Controller.CollectionsPane.doUpgradeToFirefoxN = function(version)
{
    Bandwagon.Logger.info("in Bandwagon.Controller.CollectionsPane.doUpgradeToFirefoxN() with version = " + version);

    Bandwagon.Controller.CollectionsPane._openURL("http://www.mozilla.com/en-US/firefox/all.html");
}

Bandwagon.Controller.CollectionsPane.doDownloadFirefoxNBeta = function(version)
{
    Bandwagon.Logger.info("in Bandwagon.Controller.CollectionsPane.doDownloadFirefoxNBeta() with version = " + version);

    Bandwagon.Controller.CollectionsPane._openURL("http://www.mozilla.com/en-US/firefox/all-beta.html");
}

/**
 * Refreshes the collection pane
 */
Bandwagon.Controller.CollectionsPane.refresh = function()
{
    var selectedElemBandwagonCollection = Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.selectedItem;

    if (selectedElemBandwagonCollection != null)
    {
        collection = selectedElemBandwagonCollection.collection;
        Bandwagon.Controller.CollectionsPane._repopulateAddonsList(collection);
    }

    Bandwagon.Controller.CollectionsPane.invalidate();
}

Bandwagon.Controller.CollectionsPane.prefObserver = 
{
    observe: function(subject, topic, data)
    {
        if (topic != "nsPref:changed")
            return;

        if (data.match(/addonsperpage/))
        {
            Bandwagon.Logger.debug("In prefObserver; addonsperpage has changed");

            Bandwagon.Controller.CollectionsPane.refresh();
        }
        else if (data.match(/checkCompatibility/) || data.match(/install/))
        {
            Bandwagon.Logger.debug("In prefObserver; checkCompatibility has changed");

            if (Bandwagon.Controller.CollectionsPane.elemBandwagonAddons.selectedItem != null)
            {
                var elemsAddonExpanded = Bandwagon.Controller.CollectionsPane.elemBandwagonAddons.getElementsByTagName("bandwagonAddonExpanded");

                if (elemsAddonExpanded && elemsAddonExpanded[0])
                {
                    elemsAddonExpanded[0].invalidateCompatibilityCheck();
                }
            }
        }
    }
}

/**
 * Function to select a collection ui programmatically based on its collection object.
 */
Bandwagon.Controller.CollectionsPane._selectCollection = function(collection)
{
    // select the collection and show (collection == null clears the selection)

    if (collection == null)
    {
        Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.clearSelection();
        Bandwagon.Controller.CollectionsPane.doShowCollection();
        return false;
    }

    // select the richlistitem

    const XULNS = "http://www.mozilla.org/keymaster/gatekeeper/there.is.only.xul";

    var elemsBandwagonCollection = Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.getElementsByTagNameNS(XULNS, "bandwagonCollection");
    var elemBandwagonCollection = null;

    for (var i=0; i<elemsBandwagonCollection.length; i++)
    {
        if (elemsBandwagonCollection[i].collection.equals(collection))
        {
            elemBandwagonCollection = elemsBandwagonCollection[i];
            break;
        }
    }

    if (elemBandwagonCollection == null)
    {
        Bandwagon.Logger.warn("could not find a richlistitem to select");
        return false;
    }

    try
    {
        Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.selectItem(elemBandwagonCollection);
        Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.ensureElementIsVisible(elemBandwagonCollection);
        Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.focus();
    } catch (e) {}

    return true;
}

Bandwagon.Controller.CollectionsPane._selectPreferredCollection = function()
{
    // select a collection - last selected or the first one
    var elemsBandwagonCollection = Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.getElementsByTagName("bandwagonCollection");

    if (Bandwagon.Controller.CollectionsPane.preferredCollection != null && bandwagonService.collections[Bandwagon.Controller.CollectionsPane.preferredCollection.resourceURL] != null)
    {
        Bandwagon.Logger.debug("selecting preferred collection (defined)");
        Bandwagon.Controller.CollectionsPane._selectCollection(Bandwagon.Controller.CollectionsPane.preferredCollection);
    }
    else if (elemsBandwagonCollection.length > 0)
    {
        Bandwagon.Logger.debug("selecting preferred collection (the first in the list)");
        Bandwagon.Controller.CollectionsPane._selectCollection(elemsBandwagonCollection[0].collection);
    }
    else
    {
        Bandwagon.Logger.debug("preferred collection is none");
        Bandwagon.Controller.CollectionsPane._selectCollection(null);
    }
}

Bandwagon.Controller.CollectionsPane._repopulateCollectionsList = function()
{
    Bandwagon.Logger.debug("Bandwagon.Controller.CollectionsPane: about to repopulate the collections list");

    // first clear the list

    while (Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.hasChildNodes())
    {
        Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.removeChild(Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.firstChild);
    }

    // repopulate with collections
    
    var addCollection = function(collection, styleWithSeparator)
    {
        if (collection == null)
            return;

        const XULNS = "http://www.mozilla.org/keymaster/gatekeeper/there.is.only.xul";
        var elemBandwagonCollection = document.createElementNS(XULNS, "bandwagonCollection");
        elemBandwagonCollection.collection = collection;
        elemBandwagonCollection.controller = Bandwagon.Controller.CollectionsPane;
        elemBandwagonCollection.styleWithSeparator = styleWithSeparator;

        Bandwagon.Controller.CollectionsPane.elemBandwagonCollections.appendChild(elemBandwagonCollection);
    }

    var styleWithSeparator = false;

    for (var id in bandwagonService.collections)
    {
        var collection = bandwagonService.collections[id];

        if (!collection.subscribed)
            continue;

        if (collection.type == collection.TYPE_AUTOPUBLISHER)
        {
            Bandwagon.Logger.debug("Adding autopublisher collection (" + collection.toString() + ") to collection list");
            addCollection(collection);

            styleWithSeparator = true;
        }
    }

    for (var id in bandwagonService.collections)
    {
        var collection = bandwagonService.collections[id];

        if (!collection.subscribed)
            continue;

        if (collection.type != collection.TYPE_AUTOPUBLISHER)
        {
            Bandwagon.Logger.debug("Adding normal collection (" + collection.toString() + ") to collection list");
            addCollection(collection, styleWithSeparator);

            styleWithSeparator = false;
        }
    }
}

Bandwagon.Controller.CollectionsPane._repopulateAddonsList = function(collection)
{
    // first clear the list
   
    while (Bandwagon.Controller.CollectionsPane.elemBandwagonAddons.hasChildNodes())
    {
        Bandwagon.Controller.CollectionsPane.elemBandwagonAddons.removeChild(Bandwagon.Controller.CollectionsPane.elemBandwagonAddons.firstChild);
    }

    Bandwagon.Controller.CollectionsPane.elemBandwagonAddons.clearSelection();

    if (collection == null)
        return;

    Bandwagon.Logger.debug("Bandwagon.Controller.CollectionsPane: repopulating collection '" + collection.resourceURL + "'");

    // sort by addon.dateAdded

    var addonsSorted = collection.getSortedAddons();

    // repopulate with collection items

    const XULNS = "http://www.mozilla.org/keymaster/gatekeeper/there.is.only.xul";

    var addonsToDisplay;

    if (Bandwagon.ENABLE_PAGINATION)
    {
        addonsToDisplay = Math.max(bandwagonService.getAddonsPerPage(collection), addonsSorted.length);
    }
    else
    {
        addonsToDisplay = addonsSorted.length;
    }

    for (var i=0; i<addonsToDisplay; i++)
    {
        var addon = collection.addons[addonsSorted[i].guid];

        if (addon == null)
            continue;

        var elemBandwagonAddon = document.createElementNS(XULNS, "bandwagonAddon");
        elemBandwagonAddon.addon = addon;

        Bandwagon.Controller.CollectionsPane.elemBandwagonAddons.appendChild(elemBandwagonAddon);
    }
}

Bandwagon.Controller.CollectionsPane._openURL = function(url)
{
    Bandwagon.Logger.debug("Opening URL " + url);

    var wm = Components.classes["@mozilla.org/appshell/window-mediator;1"]
                       .getService(Components.interfaces.nsIWindowMediator);
    var mainWindow = wm.getMostRecentWindow("navigator:browser");

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

Bandwagon.Controller.CollectionsPane._openLocalizedURL = function(url)
{
    var locale = Bandwagon.Util.getBrowserLocale();

    Bandwagon.Logger.debug("locale = " + locale);

    if (locale && locale != "")
        url = url.replace(/en-US/, locale, "g");

    url = url.replace("%%AMO_HOST%%", Bandwagon.Preferences.getPreference("amo_host"));

    Bandwagon.Controller.CollectionsPane._openURL(url);
}

// when this window closes, we do any uninit stuff

window.addEventListener("unload", Bandwagon.Controller.CollectionsPane.uninit, true);

