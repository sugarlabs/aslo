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

Bandwagon.Controller.ExtensionsOverlay = new function()
{
    this._publishButton = null;

    this.stringBundle = null;
}

Bandwagon.Controller.ExtensionsOverlay.init = function()
{
    Bandwagon.Logger.debug("In Bandwagon.Controller.ExtensionsOverlay.init()");

    Bandwagon.Controller.ExtensionsOverlay.stringBundle = document.getElementById("bandwagon-strings");
    Bandwagon.Controller.ExtensionsOverlay._invalidatePublishButton();

    // Some themes, e.g. Mac default on FF3, have no images in panel selectors
    // Some themes have the image on the radio, not the radiogroup
    // So lets hackily hide ours in that circumstance

    var extView = document.getElementById("extensions-view");
    var bfv = document.getElementById("bandwagon-collections-view");
    var lsi = extView.ownerDocument.defaultView.getComputedStyle( extView, '' ).getPropertyCSSValue("list-style-image").cssText;

    if (lsi == "none")
    {
        bfv.setAttribute("noimage", "true");
    }
    else
    {
        bfv.removeAttribute("noimage");
    }

    // Move Get Add-ons/Search to after Themes
    if (!Bandwagon.Util.isTB2())
    {
        Bandwagon.Controller.ExtensionsOverlay._moveSearchTab();
 
        try
        {
            // If we don't do the next call, the Search tab still thinks it is at the start and it causes some display glitches
            // dmcnamara - this is failing on linux
            updateVisibilityFlags();
        } catch (e) {}
    }
    
    // Add publish button to extension binding when selected

    document.getElementById("extensionsView").addEventListener("select", Bandwagon.Controller.ExtensionsOverlay._stuffPublishUI, true);

    // Bug 470268 - the Add-ons window is too narrow by default, enforce a minimum size
    // This is a one time only deal, so get/set the pref

    var w = document.documentElement.getAttribute("width");
    var sized = Bandwagon.Preferences.getPreference("addonswindow.resized");

    if (w < 700 && !sized)
    {
        document.documentElement.setAttribute("width", 700);
    }

    Bandwagon.Preferences.setPreference("addonswindow.resized", true);

    // Handle window arguments

    if (window.arguments)
    {
        var inArgs = window.arguments[0];

        if (inArgs)
        {
            if (inArgs.selectCollection)
            {
                Bandwagon.Logger.debug("Have window argument selectCollection = " + inArgs.selectCollection);

                if (bandwagonService.collections[inArgs.selectCollection])
                {
                    Bandwagon.Controller.CollectionsPane.preferredCollection = bandwagonService.collections[inArgs.selectCollection];
                }

                setTimeout(function() 
                {
                    Bandwagon.Controller.ExtensionsOverlay._showCollectionsPaneView();
                }, 
                500);
            }
        }
    }
}

Bandwagon.Controller.ExtensionsOverlay.doPublishToCollection = function(collection)
{
    Bandwagon.Logger.debug("In Bandwagon.Controller.ExtensionsOverlay.doPublishToCollection() with collection = '" + collection.toString() + "'");

    var extension = Bandwagon.Controller.ExtensionsOverlay._getSelectedExtension();

    var params =
    {
        publishType: 1,
        publishDestination: collection,
        publishExtension: extension
    };
    
    Bandwagon.Controller.ExtensionsOverlay._openPublishDialog(params);
}

Bandwagon.Controller.ExtensionsOverlay.doRemoveFromCollection = function(collection)
{
    var extension = Bandwagon.Controller.ExtensionsOverlay._getSelectedExtension();

    Bandwagon.Logger.debug("In Bandwagon.Controller.ExtensionsOverlay.doRemoveFromCollection() with collection = '" + collection.toString() + "' and extension: name = '" + extension.name + "', guid = '" + extension.guid + "'");

    var promptService = Components.classes["@mozilla.org/embedcomp/prompt-service;1"].getService(Components.interfaces.nsIPromptService);
    var check = {value: false};
    var flags = promptService.BUTTON_POS_0 * promptService.BUTTON_TITLE_IS_STRING + promptService.BUTTON_POS_1 * promptService.BUTTON_TITLE_IS_STRING;
    var button = promptService.confirmEx(
        window,
        Bandwagon.Controller.ExtensionsOverlay.stringBundle.getString("publish.remove.title"),
        Bandwagon.Controller.ExtensionsOverlay.stringBundle.getFormattedString("publish.remove.label", [extension.name, collection.name]),
        flags,
        Bandwagon.Controller.ExtensionsOverlay.stringBundle.getString("publish.remove.button0"),
        Bandwagon.Controller.ExtensionsOverlay.stringBundle.getString("publish.remove.button1"),
        null,
        null,
        check);

    var callback = function(event)
    {
        if (!event.isError())
        {
            bandwagonService.forceCheckForUpdates(collection);
        }
    }

    if (button == 0)
    {
        bandwagonService.removeAddonFromCollection(extension.guid, collection, callback);
    }
}

Bandwagon.Controller.ExtensionsOverlay.doShareToEmail = function(emailAddress)
{
    Bandwagon.Logger.debug("In Bandwagon.Controller.ExtensionsOverlay.doShareToEmail() with email = '" + emailAddress + "'");

    var extension = Bandwagon.Controller.ExtensionsOverlay._getSelectedExtension();

    var params =
    {
        publishType: 2,
        publishDestination: emailAddress,
        publishExtension: extension
    };
    
    Bandwagon.Controller.ExtensionsOverlay._openPublishDialog(params);
}

Bandwagon.Controller.ExtensionsOverlay._getSelectedExtension = function()
{
    var extension = {guid: "", name: ""};

    if (document.getElementById("extensionsView").selectedItem)
    {
        var selectedAddon = document.getElementById("extensionsView").selectedItem;
        extension.guid = selectedAddon.getAttribute("addonID");
        extension.name = selectedAddon.getAttribute("name");
    }
    else if (Bandwagon.Controller.CollectionsPane &&  Bandwagon.Controller.CollectionsPane.elemBandwagonAddons && Bandwagon.Controller.CollectionsPane.elemBandwagonAddons.selectedItem)
    {
        var selectedAddon = Bandwagon.Controller.CollectionsPane.elemBandwagonAddons.selectedItem;
        extension.guid = selectedAddon.guid;
        extension.name = selectedAddon.name;
    }
    else
    {
        extension.name = "??";
    }

    return extension;
}

Bandwagon.Controller.ExtensionsOverlay.doNewCollection = function()
{
    Bandwagon.Logger.debug("In Bandwagon.Controller.ExtensionsOverlay.doNewCollection()");

    var extension = Bandwagon.Controller.ExtensionsOverlay._getSelectedExtension();

    if (extension && extension.guid)
    {
        Bandwagon.Controller.CollectionsPane._openLocalizedURL(Bandwagon.COLLECTIONSPANE_DO_NEW_COLLECTION_URL + "?guid=" + extension.guid);
    }
    else
    {
        Bandwagon.Controller.CollectionsPane._openLocalizedURL(Bandwagon.COLLECTIONSPANE_DO_NEW_COLLECTION_URL);
    }
}

Bandwagon.Controller.ExtensionsOverlay.doAddNewShareEmail = function()
{
    Bandwagon.Logger.debug("In Bandwagon.Controller.ExtensionsOverlay.doAddNewShareEmail()");

    var extension = Bandwagon.Controller.ExtensionsOverlay._getSelectedExtension();

    var params =
    {
        publishType: 3,
        publishDestination: null,
        publishExtension: extension
    };
    
    Bandwagon.Controller.ExtensionsOverlay._openPublishDialog(params);

    Bandwagon.Controller.ExtensionsOverlay._invalidatePublishButton();
}

Bandwagon.Controller.ExtensionsOverlay.doCreateAccount = function()
{
    Bandwagon.Controller.CollectionsPane._openLocalizedURL(Bandwagon.LOGINPANE_DO_NEW_ACCOUNT);
}

Bandwagon.Controller.ExtensionsOverlay._openPublishDialog = function(params)
{
    window.openDialog("chrome://bandwagon/content/ui/publish.xul", "",
        "chrome,titlebar,centerscreen,modal", params);
}

Bandwagon.Controller.ExtensionsOverlay._moveSearchTab = function()
{
    var parNode = document.getElementById("viewGroup");
    var newNode = document.getElementById("search-view").cloneNode(false);
    var refNode = document.getElementById("updates-view");
    var insertedNode = parNode.insertBefore(newNode, refNode);
    parNode.removeChild(parNode.firstChild);
}

Bandwagon.Controller.ExtensionsOverlay._stuffPublishUI = function()
{
    var elemExtension = document.getElementById("extensionsView").selectedItem;

    if (!elemExtension)
        return;

    var elemSelectedButtons = document.getAnonymousElementByAttribute(elemExtension, "anonid", "selectedButtons");

    if (!elemSelectedButtons)
        return;

    // No publish for plugins and items that can't be updated, the latter includes the default theme
    if (elemExtension.getAttribute("plugin") == "true" || elemExtension.getAttribute("updateable") == "false")
        return;

    if (Bandwagon.Controller.ExtensionsOverlay._publishButton && Bandwagon.Controller.ExtensionsOverlay._publishButton.parentNode)
    {
        Bandwagon.Controller.ExtensionsOverlay._publishButton.parentNode.removeChild(Bandwagon.Controller.ExtensionsOverlay._publishButton);
    }

    if (!bandwagonService.isAuthenticated())
        return;

    Bandwagon.Controller.ExtensionsOverlay._invalidatePublishButton();

    for (var i=0; i<elemSelectedButtons.childNodes.length; i++)
    {
        if (elemSelectedButtons.childNodes[i]
            && elemSelectedButtons.childNodes[i].nodeType == Node.ELEMENT_NODE
            && (elemSelectedButtons.childNodes[i].getAttribute("class").match(/enableButton/)
                || elemSelectedButtons.childNodes[i].getAttribute("class").match(/addonInstallButton/)))
        {
            elemSelectedButtons.insertBefore(Bandwagon.Controller.ExtensionsOverlay._publishButton,
                                             elemSelectedButtons.childNodes[i]);
            break;
        }
    }
}

Bandwagon.Controller.ExtensionsOverlay._invalidatePublishButton = function()
{
    if (!Bandwagon.Controller.ExtensionsOverlay._publishButton)
    {
        Bandwagon.Controller.ExtensionsOverlay._publishButton = document.createElement("bandwagonPublishButton");
    }

    Bandwagon.Controller.ExtensionsOverlay._publishButton.emailAddresses = Bandwagon.Controller.ExtensionsOverlay._getEmailAddresses();
    Bandwagon.Controller.ExtensionsOverlay._publishButton.writableCollections = Bandwagon.Controller.ExtensionsOverlay._getWritableCollections();

    try
    {
        Bandwagon.Controller.ExtensionsOverlay._publishButton.invalidate();
    }
    catch (e) {}
}

Bandwagon.Controller.ExtensionsOverlay._getWritableCollections = function()
{
    var writableCollections = [];

    var extension = Bandwagon.Controller.ExtensionsOverlay._getSelectedExtension();

    for (var id in bandwagonService.collections)
    {
        var collection = bandwagonService.collections[id];

        //if (1 || (bandwagonService.collections[id].writable && !bandwagonService.collections[id].preview))
        
        if (collection.writable)
        {
            // Check if extension is in collection
            collection.__containsCurrentlySelectedExtension = false;

            for (var id in collection.addons)
            {
                if (extension.guid == collection.addons[id].guid)
                {
                    collection.__containsCurrentlySelectedExtension = true;
                    break;
                }
            }
            
            writableCollections.push(collection);
        }
    }  

    writableCollections.sort();

    return writableCollections;
}

Bandwagon.Controller.ExtensionsOverlay._getEmailAddresses = function()
{
    var previouslySharedEmailAddresses =  bandwagonService.getPreviouslySharedEmailAddresses();

    previouslySharedEmailAddresses.sort();

    return previouslySharedEmailAddresses;
}

Bandwagon.Controller.ExtensionsOverlay._showCollectionsPaneView = function()
{
    Bandwagon.Logger.debug("in _showCollectionsPaneView()");

    updateLastSelected("bandwagon-collections");
    gView = "bandwagon-collections";

    document.getElementById("installFileButton").hidden = true;
    document.getElementById("checkUpdatesAllButton").hidden = true;
    document.getElementById("installUpdatesAllButton").hidden = true;
    document.getElementById("skipDialogButton").hidden = true;
    document.getElementById("continueDialogButton").hidden = true;
    document.getElementById("themePreviewArea").hidden = true;
    document.getElementById("themeSplitter").hidden = true;
    document.getElementById("extensionsView").hidden = true;
    if (document.getElementById("showUpdateInfoButton")) { // FF3/TB3+
        document.getElementById("showUpdateInfoButton").hidden = true;
        document.getElementById("hideUpdateInfoButton").hidden = true;
        document.getElementById("searchPanel").hidden = true;
        document.getElementById("extensionsView").parentNode.hidden = true;
    }
    else { // TB2
        document.getElementById("getMore").hidden = true;
    }

    document.getElementById("continueDialogButton").removeAttribute("default");
    document.getElementById("installUpdatesAllButton").removeAttribute("default");

    AddonsViewBuilder.clearChildren(gExtensionsView);

    document.getElementById("bandwagon-collections-panel").hidden = false;

    updateGlobalCommands();

    Bandwagon.Controller.CollectionsPane.init();
    Bandwagon.Controller.CollectionsPane.onViewSelect();
}

// magic

Bandwagon.Controller.ExtensionsOverlay._defaultShowView = showView;

Bandwagon.Controller.ExtensionsOverlay._showView = function(aView)
{
    if (aView == "bandwagon-collections")
    {
        Bandwagon.Controller.ExtensionsOverlay._showCollectionsPaneView();
    }
    else
    {
        document.getElementById("bandwagon-collections-panel").hidden = true;
        document.getElementById("extensionsView").hidden = false;
        document.getElementById("extensionsView").parentNode.hidden = false;

        Bandwagon.Controller.ExtensionsOverlay._defaultShowView(aView);
    }
}

showView = Bandwagon.Controller.ExtensionsOverlay._showView;

window.addEventListener("load", Bandwagon.Controller.ExtensionsOverlay.init, true);
