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

const nsISupports = Components.interfaces.nsISupports;
const CLASS_ID = Components.ID("5c896f09-126c-466d-b28a-4e8b87a29916");
const CLASS_NAME = "";
const CONTRACT_ID = "@addons.mozilla.org/bandwagonservice;1";

const Cc = Components.classes;
const Ci = Components.interfaces;

const WindowMediator = Cc["@mozilla.org/appshell/window-mediator;1"];
const Timer = Cc["@mozilla.org/timer;1"];
const ExtensionsManager = Cc["@mozilla.org/extensions/manager;1"];
const Storage = Cc["@mozilla.org/storage/service;1"];
const DirectoryService = Cc["@mozilla.org/file/directory_service;1"];
const ObserverService = Cc["@mozilla.org/observer-service;1"];
const CookieManager = Cc["@mozilla.org/cookiemanager;1"];
const LoginManager = Cc["@mozilla.org/login-manager;1"];
const UpdateItem = Cc["@mozilla.org/updates/item;1"];

const nsIWindowMediator = Ci.nsIWindowMediator;
const nsITimer = Ci.nsITimer;
const nsIExtensionManager = Ci.nsIExtensionManager;
const mozIStorageService = Ci.mozIStorageService;
const nsIProperties = Ci.nsIProperties;
const nsIFile = Ci.nsIFile;
const nsIObserverService = Ci.nsIObserverService;
const nsICookieManager = Ci.nsICookieManager;
const nsILoginManager = Ci.nsILoginManager;
const nsIUpdateItem = Ci.nsIUpdateItem;

var Bandwagon;
var bandwagonService;

var gEmGUID;
var gUninstallObserverInited = false;

/* Restore settings added or changed by the extension:
 *  - extension preferences
 *  - logins stored in the Login Manager?
 */
function cleanupSettings()
{
  // Cleanup preferences
  var prefs = Components.classes["@mozilla.org/preferences-service;1"]
                        .getService(Components.interfaces.nsIPrefBranch);
  try {
    prefs.deleteBranch("extensions.bandwagon");
  }
  catch(e) {}
}

/* Returns host application string
 */
function getAppName()
{
  var info = Components.classes["@mozilla.org/xre/app-info;1"]
      .getService(Components.interfaces.nsIXULAppInfo);
  // Get the name of the application running us
  return info.name; // Returns "Firefox" for Firefox
}

function BandwagonService()
{
    this.wrappedJSObject = this;
    gEmGUID = "sharing@addons.mozilla.org";
}

BandwagonService.prototype = {

    collections: {},

    _initialized: false,
    _service: null,
    _collectionUpdateObservers: [],
    _collectionListChangeObservers: [],
    _authenticationStatusChangeObservers: [],
    _storageConnection: null,
    _collectionFactory: null, 
    _collectionUpdateTimer: null,
    _bwObserver: null,
    _serviceDocument: null,

    init: function()
    {
        if (this._initialized)
            return;

        // get access to Bandwagon.* singletons
        var app = getAppName();
        var appWinString = "navigator:browser"; // default, Firefox
        if (app == "Thunderbird")
            appWinString = "mail:3pane";
        var appWindow = WindowMediator.getService(nsIWindowMediator).getMostRecentWindow(appWinString);

        if (!appWindow || !appWindow.Bandwagon)
        {
            debug("Bandwagon: could not get access to Bandwagon singletons from last window");
            return;
        }

        Bandwagon = appWindow.Bandwagon;
        bandwagonService = this;

        Bandwagon.Logger.info("Initializing Bandwagon");

        // init rpc service

        this._service = new Bandwagon.RPC.Service();
        this._service.registerLogger(Bandwagon.Logger);
        this._service.registerObserver(this._getCollectionObserver);
        this._service.registerObserver(this._getServiceDocumentObserver);
        this._service.registerObserver(this._notAuthorizedObserver);

        this.registerCollectionUpdateObserver(this._collectionUpdateObserver);
        // init sqlite storage (also creating tables in sqlite if needed). create factory objects.

        this._initStorage();

        // first run stuff

        if (Bandwagon.Preferences.getPreference("firstrun") == true)
        {
            Bandwagon.Preferences.setPreference("firstrun", false);
            this.firstrun();
        }

        // start the update timer

        this._initUpdateTimer();

        // observe when the app shuts down so we can uninit

        ObserverService.getService(nsIObserverService).addObserver(this._bwObserver, "quit-application", false);

        // storage initialized, tables created - open the collections and service document

        var callback = function()
        {
            // kick off the auto-publish functionality

            bandwagonService.autopublishExtensions();

            // kick off the auto-install functionality

            bandwagonService.autoinstallExtensions();

            bandwagonService._initialized = true;

            Bandwagon.Logger.info("Bandwagon has been initialized");
        }
        
        this._initCollections(callback);
    },

    _initCollections: function(callback)
    {
        this._collectionFactory.openCollections(function(result, storageCollections)
        {
            if (result == Bandwagon.STMT_OK)
            {
                for (var id in storageCollections)
                {
                    bandwagonService.collections[id] = storageCollections[id];
                    bandwagonService.collections[id].setAllNotified();

                    if (bandwagonService.collections[id].isLocalAutoPublisher())
                    {
                        bandwagonService.collections[id].autoPublishExtensions = Bandwagon.Preferences.getPreference("local.autopublisher.publish.extensions");
                        bandwagonService.collections[id].autoPublishThemes = Bandwagon.Preferences.getPreference("local.autopublisher.publish.themes");
                        bandwagonService.collections[id].autoPublishDicts = Bandwagon.Preferences.getPreference("local.autopublisher.publish.dictionaries");
                        bandwagonService.collections[id].autoPublishLangPacks = Bandwagon.Preferences.getPreference("local.autopublisher.publish.language.packs");
                        bandwagonService.collections[id].autoPublishDisabled = !Bandwagon.Preferences.getPreference("local.autopublisher.only.publish.enabled");
                    }

                    Bandwagon.Logger.debug("opened collection from storage: " + id);
                }
            }
                
            bandwagonService._collectionFactory.openServiceDocument(function(result, serviceDocument)
            {
                bandwagonService._service._serviceDocument = serviceDocument;

                if (result != Bandwagon.STMT_OK || serviceDocument == null)
                {
                    // no service document in storage, we never had it or we've lost it - go fetch it
                    Bandwagon.Logger.info("No service document found in storage, fetching...");
                    bandwagonService.updateCollectionsList();
                }
            });

            if (callback)
                callback();
        });
    },

    _initUpdateTimer: function()
    {
        this._bwObserver = 
        {
            observe: function(aSubject, aTopic, aData)
            {
                if (aTopic == "timer-callback")
                {
                    bandwagonService.checkAllForUpdates();
                }
                else if (aTopic == "quit-application")
                {
                    bandwagonService.uninit();
                }
            }
        };

        this._collectionUpdateTimer = Timer.createInstance(nsITimer);
        this._collectionUpdateTimer.init(
            this._bwObserver,
            (Bandwagon.Preferences.getPreference("debug")?240*1000:Bandwagon.COLLECTION_UPDATE_TIMER_DELAY*1000),
            nsITimer.TYPE_REPEATING_SLACK
            );
    },

    uninit: function()
    {
        this._collectionUpdateTimer = null;
        this.commitAll();
        this._service = null;
        this._collectionFactory = null;
        Bandwagon = null;
        bandwagonService = null;
    },
    
    getLocalAutoInstaller: function()
    {
        for (var id in bandwagonService.collections)
        {
            if (bandwagonService.collections[id].isLocalAutoInstaller())
            {
                return bandwagonService.collections[id];
            }
        }

        return null;
    },

    autoinstallExtensions: function(callback)
    {
        Bandwagon.Logger.debug("in autoinstallExtensions()");

        if (!Bandwagon.Preferences.getGlobalPreference("xpinstall.enabled", true))
        {
            Bandwagon.Logger.warn("Can't auto install extensions because xp installs are disabled");
            return;
        }

        var localAutoInstaller = bandwagonService.getLocalAutoInstaller();

        if (localAutoInstaller == null)
        {
            Bandwagon.Logger.debug("No auto installer collection found");
            return;
        }

        var installedExtensions = Bandwagon.Util.getInstalledExtensions();

        addons: for (var id in localAutoInstaller.addons)
        {
            var addon = localAutoInstaller.addons[id];

            for (var i=0; i<installedExtensions.length; i++)
            {
                if (installedExtensions[i].id == addon.guid)
                {
                    Bandwagon.Logger.debug("autoinstallExtensions: addon '" + addon.name + "' is already installed");
                    break addons;
                }
            }

            var installer = addon.getInstaller(Bandwagon.Util.getHostEnvironmentInfo().os);

            if (!installer)
            {
                Bandwagon.Logger.warn("Can't auto install '" + addon.name + "' because it is not compatible with " + Bandwagon.Util.getHostEnvironmentInfo().os);
                break;
            }

            // TODO accept eula here

            var params = [];
            params[addon.name] = installer;

            var callback = function(url, status)
            {
                Bandwagon.Logger.info("Finished installing '" + url + "'; status = " + status);
            }

            Bandwagon.Util.getMainWindow().InstallTrigger.install(params, callback);
        }
    },

    getLocalAutoPublisher: function()
    {
        for (var id in bandwagonService.collections)
        {
            if (bandwagonService.collections[id].isLocalAutoPublisher())
            {
                return bandwagonService.collections[id];
            }
        }

        return null;
    },

    autopublishExtensions: function(callback)
    {
        Bandwagon.Logger.debug("in autopublishExtensions()");

        var localAutoPublisher = bandwagonService.getLocalAutoPublisher();

        if (localAutoPublisher == null)
        {
            Bandwagon.Preferences.setPreferenceList("autopublished.extensions", []);
            return;
        }

        var internalCallback = function(event)
        {
            if (!event.isError())
            {
                bandwagonService._notifyCollectionUpdateObservers(localAutoPublisher);
            }

            if (callback)
            {
                callback(event);
            }
        }

        var installedExtensions = Bandwagon.Util.getInstalledExtensions();
        var autopublishedExtensions = Bandwagon.Preferences.getPreferenceList("autopublished.extensions");
        var willAutopublishExtensions = [];

        for (var i=0; i<installedExtensions.length; i++)
        {
            //Bandwagon.Logger.debug("checking addon '" + installedExtensions[i].id + "' against user auto pub prefs (type=" +  installedExtensions[i].type + ")");

            // check if user wants to publish this extension (enabled, type)
            
            if ((
                Bandwagon.Util.getExtensionProperty(installedExtensions[i].id, "isDisabled") == "true"
                ||
                Bandwagon.Util.getExtensionProperty(installedExtensions[i].id, "appDisabled") == "true"
                ||
                Bandwagon.Util.getExtensionProperty(installedExtensions[i].id, "userDisabled") == "true"
                )
                && !localAutoPublisher.autoPublishDisabled)
            {
                //Bandwagon.Logger.debug("addon '" + installedExtensions[i].id + "' is disabled, so won't publish");
                continue;
            }

            if (installedExtensions[i].type & installedExtensions[i].TYPE_EXTENSION 
                && !localAutoPublisher.autoPublishExtensions)
            {
                //Bandwagon.Logger.debug("addon '" + installedExtensions[i].id + "' is an extension, so won't publish");
                continue;
            }

            if (installedExtensions[i].type & installedExtensions[i].TYPE_THEME 
                && !localAutoPublisher.autoPublishThemes)
            {
                //Bandwagon.Logger.debug("addon '" + installedExtensions[i].id + "' is a theme, so won't publish");
                continue;
            }

            if (installedExtensions[i].type & installedExtensions[i].TYPE_LOCALE 
                && !localAutoPublisher.autoPublishLangPacks)
            {
                //Bandwagon.Logger.debug("addon '" + installedExtensions[i].id + "' is a locale, so won't publish");
                continue;
            }

            /** TODO
            if (installedExtensions[i].type & installedExtensions[i].TYPE_DICT 
                && !localAutoPublisher.autoPublishDicts)
            {
                Bandwagon.Logger.debug("addon '" + installedExtensions[i].id + "' is a dict, so won't publish");
                continue;
            }
            */

            // check if we have already published this extension
            
            var hasPublished = false;

            for (var j=0; j<autopublishedExtensions.length; j++)
            {
                if (installedExtensions[i].id == autopublishedExtensions[j])
                {
                    hasPublished = true;
                    break;
                }
            }

            if (hasPublished == false)
            {
                //Bandwagon.Logger.debug("addon '" + installedExtensions[i].id + "' added to auto-publish queue");
                willAutopublishExtensions.push(installedExtensions[i]);
            }
            else
            {
                //Bandwagon.Logger.debug("addon '" + installedExtensions[i].id + "' has already been published");
            }
        }

        if (willAutopublishExtensions.length > 0)
        {
            for (var i=0; i<willAutopublishExtensions.length; i++)
            {
                //Bandwagon.Logger.debug("Will autopublish extension '" + willAutopublishExtensions[i].id + "' to collection '" + localAutoPublisher.resourceURL + "'");

                var extension =
                {
                    guid: willAutopublishExtensions[i].id,
                    name: willAutopublishExtensions[i].name
                }

                bandwagonService.publishToCollection(extension, localAutoPublisher, "", internalCallback);

                // add to autopublish
                autopublishedExtensions.push(willAutopublishExtensions[i].id);
            }

            Bandwagon.Preferences.setPreferenceList("autopublished.extensions", autopublishedExtensions);
        }
    },

    _getCollectionObserver: function(event)
    {
        Bandwagon.Logger.info("in _getCollectionObserver()");

        if (event.getType() == Bandwagon.RPC.Constants.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_GET_COLLECTION_COMPLETE)
        {
            var collection = event.collection;

            if (event.isError())
            {
                Bandwagon.Logger.error("RPC error: '" + event.getError().getMessage() + "'");
            }
            else
            {
                if (collection != null && collection.resourceURL != null)
                {
                    Bandwagon.Logger.info("Finished getting updates for collection '" + collection.resourceURL + "'");
                    bandwagonService.collections[collection.resourceURL] = collection;
                }
            }

            // we want to notify the observers even if there's been an error

            bandwagonService._notifyCollectionUpdateObservers(collection);
        }
    },

    /* Watch all API responses for an "unauthorized" error.
       This implies we're not authenticated, so we should log out the user from
       the UI.
     */
    _notAuthorizedObserver: function(event)
    {
        if (event.isError() && (event.getError().getMessage() == "unauthorized" || event.getError().getCode() == Bandwagon.RPC.Constants.BANDWAGON_RPC_SERVICE_ERROR_UNAUTHORIZED))
        {
            Bandwagon.Logger.debug("in _notAuthorizedObserver(), response says this client is not authorized; deauthenticating ui");
            bandwagonService.deauthenticate();
        }
    },
 
    _getServiceDocumentObserver: function(event)
    {
        Bandwagon.Logger.info("in _getServiceDocumentObserver()");

        if (event.getType() == Bandwagon.RPC.Constants.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_GET_SERVICE_DOCUMENT_COMPLETE)
        {
            if (event.isError())
            {
                Bandwagon.Logger.error("Could not update collections list: " + event.getError().toString());
            }
            else
            {
                bandwagonService._serviceDocument = event.serviceDocument;
                bandwagonService._service._serviceDocument = bandwagonService._serviceDocument;

                var collections = bandwagonService._serviceDocument.collections;

                Bandwagon.Logger.debug("Updating collections list: saw " + collections.length + " collections");

                for (var id in bandwagonService.collections)
                {
                    var isStaleCollection = true;

                    for (var jd in collections)
                    {
                        if (bandwagonService.collections[id].equals(collections[jd]))
                        {
                            isStaleCollection = false;
                            break;
                        }
                    }

                    if (isStaleCollection)
                    {
                        Bandwagon.Logger.debug("Updating collections list: removing stale collection: " + bandwagonService.collections[id].toString());

                        bandwagonService.unlinkCollection(bandwagonService.collections[id]);
                    }
                }

                for (var id in collections)
                {
                    var collection = collections[id];

                    if (bandwagonService.collections[collection.resourceURL]
                        && (bandwagonService.collections[collection.resourceURL].subscribed == collection.subscribed))
                    {
                        // we have already added this collection and its subscribed status hasn't changed
                    }
                    else
                    {
                        // this is a new collection
                        Bandwagon.Logger.debug("Updating collections list: adding new collection: " + collection.toString());

                        bandwagonService.collections[collection.resourceURL] = collection;
                    }
                }

                bandwagonService.forceCheckAllForUpdates();
                
                bandwagonService._notifyListChangeObservers();

                if (Bandwagon.COMMIT_NOW)
                    bandwagonService.commitAll();
            }
        }
    },

    _notifyCollectionUpdateObservers: function(collection)
    {
        Bandwagon.Logger.debug("Notifying collection update observers");

        for (var i=0; i<bandwagonService._collectionUpdateObservers.length; i++)
        {
            if (bandwagonService._collectionUpdateObservers[i])
            {
                bandwagonService._collectionUpdateObservers[i](collection);
            }
        }
    },

    registerCollectionUpdateObserver: function(observer)
    {
        Bandwagon.Logger.debug("Registering collection update observer");
        this._collectionUpdateObservers.push(observer);
    },

    unregisterCollectionUpdateObserver: function(observer)
    {
        Bandwagon.Logger.debug("Unregistering collection update observer");

        for (var i=0; i<this._collectionUpdateObservers.length; i++)
        {
            if (this._collectionUpdateObservers[i] == observer)
            {
                delete this._collectionUpdateObservers[i];
            }
        }
    },

    _notifyAuthenticationStatusChangeObservers: function()
    {
        Bandwagon.Logger.debug("Notifying authentication status change observers");

        for (var i=0; i<bandwagonService._authenticationStatusChangeObservers.length; i++)
        {
            if (bandwagonService._authenticationStatusChangeObservers[i])
            {
                bandwagonService._authenticationStatusChangeObservers[i]();
            }
        }
    },

    registerAuthenticationStatusChangeObserver: function(observer)
    {
        Bandwagon.Logger.debug("Registering authentication status change observer");
        this._authenticationStatusChangeObservers.push(observer);
    },

    unregisterAuthenticationStatusChangeObserver: function(observer)
    {
        Bandwagon.Logger.debug("Unregistering authentication status change observer");

        for (var i=0; i<this._authenticationStatusChangeObservers.length; i++)
        {
            if (this._authenticationStatusChangeObservers[i] == observer)
            {
                delete this._authenticationStatusChangeObservers[i];
            }
        }
    },

    _notifyListChangeObservers: function()
    {
        Bandwagon.Logger.debug("Notifying collection list change observers");

        for (var i=0; i<bandwagonService._collectionListChangeObservers.length; i++)
        {
            if (bandwagonService._collectionListChangeObservers[i])
            {
                bandwagonService._collectionListChangeObservers[i]();
            }
        }
    },

    registerCollectionListChangeObserver: function(observer)
    {
        Bandwagon.Logger.debug("Registering collection list change observer");
        this._collectionListChangeObservers.push(observer);
    },

    unregisterCollectionListChangeObserver: function(observer)
    {
        Bandwagon.Logger.debug("Unregistering collection list change observer");

        for (var i=0; i<this._collectionListChangeObservers.length; i++)
        {
            if (this._collectionListChangeObservers[i] == observer)
            {
                delete this._collectionListChangeObservers[i];
            }
        }
    },

    authenticate: function(login, password, callback)
    {
        Bandwagon.Logger.debug("in authenticate()");

        var service = this;

        Bandwagon.Preferences.setPreference(Bandwagon.PREF_AUTH_TOKEN, "");
        Bandwagon.Preferences.setPreference("login", "");

        // The following is a workaround to allow auth-token based
        // authentication to work when an AMO cookie is also present. Full
        // description in bug 496612.
        // XXX. Comment out when bug 496612 is addressed.
        //this.deleteAMOCookie();

        var internalCallback = function(event)
        {
            if (!event.isError())
            {
                Bandwagon.Preferences.setPreference("login", login);

                service._notifyAuthenticationStatusChangeObservers();
            }

            if (callback)
                callback(event);
        }

        this._service.authenticate(login, password, internalCallback);
    },

    authenticate2: function(login, password, callback)
    {
        Bandwagon.Logger.debug("in authenticate2()");

        Bandwagon.Preferences.setPreference(Bandwagon.PREF_AUTH_TOKEN, "");

        this._service.authenticate(login, password, callback);
    },

    deauthenticate: function(callback)
    {
        Bandwagon.Preferences.setPreference(Bandwagon.PREF_AUTH_TOKEN, "");
        Bandwagon.Preferences.setPreference("login", "");

        this._notifyAuthenticationStatusChangeObservers();

        if (callback)
            callback();
    },

    getLoginManagerAMOAuthCreds: function(usernameHint)
    {
        var hostname = "https://" + Bandwagon.Preferences.getPreference("amo_host");
        var formSubmitURL = "";
        var httprealm = null;
        var username;
        var password;

        try
        {
            var loginManager = LoginManager.getService(nsILoginManager);

            var logins = loginManager.findLogins({}, hostname, formSubmitURL, httprealm);

            for (var i=0; i < logins.length; i++)
            {
                if (logins[i].username == usernameHint)
                {
                    return {username: logins[i].username, password: logins[i].password};
                }
            }

            if (logins[0])
            {
                return {username: logins[0].username, password: logins[0].password};
            }

        } catch (e) {}

        return null;
    },

    updateCollectionsList: function(callback)
    {
        Bandwagon.Logger.debug("Updating collections list...");

        this.updateServiceDocument(callback);
    },

    updateServiceDocument: function(callback)
    {
        if (!this.isAuthenticated())
            return;

        this._service.getServiceDocument(callback);
    },

    checkForUpdates: function(collection)
    {
        if (!this.isAuthenticated())
            return;

        this._service.getCollection(collection);

        var now = new Date();

        collection.dateLastCheck = now;
    },

    checkAllForUpdates: function()
    {
        Bandwagon.Logger.debug("in checkAllForUpdates()");

        var now = new Date();

        for (var id in this.collections)
        {
            var collection = this.collections[id];

            if (!collection.subscribed)
                continue;

            if (collection.updateInterval == -1)
            {
                // use global setting
                
                var dateLastCheck = new Date(Bandwagon.Preferences.getPreference("updateall.datelastcheck")*1000);
                var dateNextCheck = new Date(dateLastCheck.getTime() + Bandwagon.Util.intervalUnitsToMilliseconds(
                    Bandwagon.Preferences.getPreference("global.update.interval"),
                    Bandwagon.Preferences.getPreference("global.update.units")
                    ));

                if (dateNextCheck.getTime() > now.getTime())
                {
                    return;
                }
                else
                {
                    this.checkForUpdates(collection);
                }
            }
            else
            {
                // use per-collection setting
                
                var dateLastCheck = null;
                var dateNextCheck = null;

                if (collection.dateLastCheck != null)
                {
                    dateLastCheck = collection.dateLastCheck;
                    dateNextCheck = new Date(dateLastCheck.getTime() + collection.updateInterval*1000);
                }
                else
                {
                    dateLastCheck = null;
                    dateNextCheck = now;
                }

                if (dateLastCheck == null || dateNextCheck.getTime() <= now.getTime())
                {
                    this.checkForUpdates(collection);
                }
            }
        }

        Bandwagon.Preferences.setPreference("updateall.datelastcheck", now.getTime()/1000);
    },

    forceCheckForUpdates: function(collection)
    {
        if (!this.isAuthenticated())
            return;

        this._service.getCollection(collection);
        collection.dateLastCheck = new Date();
    },

    forceCheckAllForUpdates: function()
    {
        Bandwagon.Logger.debug("in forceCheckAllForUpdates()");

        for (var id in this.collections)
        {
            var collection = this.collections[id];

            Bandwagon.Logger.debug("in forceCheckAllForUpdates() with collection = " + collection.toString() + ", subscribed = " + collection.subscribed);

            if (!collection.subscribed)
                continue;

            this.forceCheckForUpdates(collection);
        }
    },

    forceCheckAllForUpdatesAndUpdateCollectionsList: function(callback)
    {
        // All updates to the collections list are forced, i.e. they are always
        // caused by *some* user interaction, never in the background.
        // Updating the collections list also forces the collections to be updated.
        
        this.updateCollectionsList(callback);
    },

    firstrun: function()
    {
        Bandwagon.Logger.info("This is bandwagon's firstrun. Welcome!");

        // the last check date is now

        var now = new Date();
        Bandwagon.Preferences.setPreference("updateall.datelastcheck", now.getTime()/1000);

        // open the firstrun landing page

        Bandwagon.Controller.BrowserOverlay.openFirstRunLandingPage();
    },

    _addDefaultCollection: function(url, name)
    {
        var collection = this._collectionFactory.newCollection();
        collection.resourceURL = url;
        collection.name = name;
        collection.showNotifications = 0;

        this.collections[collection.resourceURL] = collection;

        if (Bandwagon.COMMIT_NOW)
            this.commit(collection);

        this.forceCheckForUpdates(collection);
        this.subscribe(collection);
    },

    uninstall: function()
    {
        // TODO
    },

    commit: function(collection)
    {
        this.commitAll();
    },

    commitAll: function()
    {
        Bandwagon.Logger.debug("In commitAll()");

        bandwagonService._collectionFactory.commitCollections(bandwagonService.collections);

        if (bandwagonService._serviceDocument)
            bandwagonService._collectionFactory.commitServiceDocument(bandwagonService._serviceDocument); 
    },

    removeAddonFromCollection: function(guid, collection, callback)
    {
        Bandwagon.Logger.debug("In removeAddonFromCollection()");

        if (!this.isAuthenticated())
            return;

        this._service.removeAddonFromCollection(guid, collection, callback);
    },

    newCollection: function(collection, callback)
    {
        Bandwagon.Logger.debug("In newCollection()");

        if (!this.isAuthenticated())
            return;

        var internalCallback = function(event)
        {
            if (!event.isError())
            {
                var collection = event.collection;

                bandwagonService.collections[collection.resourceURL] = collection;
                //bandwagonService._notifyCollectionUpdateObservers(collection);
                bandwagonService._notifyListChangeObservers();
            }

            if (callback)
            {
                callback(event);
            }
        }

        this._service.newCollection(collection, internalCallback);
    },

    unlinkCollection: function(collection)
    {
        this._collectionFactory.deleteCollection(collection, function(aReason)
        {
            if (aReason == Bandwagon.STMT_OK)
            {
                for (var id in bandwagonService.collections)
                {
                    if (collection.equals(bandwagonService.collections[id]))
                    {
                        delete bandwagonService.collections[id];

                        bandwagonService._notifyListChangeObservers();

                        break;
                    }
                }
            }
        }); 
    },

    deleteCollection: function(collection, callback)
    {
        if (!this.isAuthenticated())
            return;

        this._service.deleteCollection(collection, callback);
    },

    subscribeToCollection: function(collection, callback)
    {
        if (!this.isAuthenticated())
            return;

        var internalCallback = function(event)
        {
            if (!event.isError())
            {
                collection.subscribed = true;
                bandwagonService._notifyListChangeObservers();
            }

            if (callback)
            {
                callback(event);
            }
        }

        this._service.subscribeToCollection(collection, internalCallback);
    },

    unsubscribeFromCollection: function(collection, callback)
    {
        if (!this.isAuthenticated())
            return;

        var internalCallback = function(event)
        {
            if (!event.isError())
            {
                bandwagonService.unlinkCollection(collection);
            }

            if (callback)
            {
                callback(event);
            }
        }

        this._service.unsubscribeFromCollection(collection, internalCallback);
    },

    updateCollectionDetails: function(collection, callback)
    {
        if (!this.isAuthenticated())
            return;

        this._service.updateCollectionDetails(collection, callback);
    },

    /** This function is called when a 3rd party extension is uninstalled by
     * the user.  If this extension is part of a local auto publisher, we
     * remove it from that autopublisher.
     */
    processOtherExtensionUninstall: function(guid, callback)
    {
        Bandwagon.Logger.debug("In processOtherExtensionUninstall() with guid = " + guid);

        var localAutoPublisher = bandwagonService.getLocalAutoPublisher();

        if (!localAutoPublisher)
            return;

        var internalCallback = function(event)
        {
            if (!event.isError())
            {
                // update list of autopublished extensions to remove this one
                var autopublishedExtensions = Bandwagon.Preferences.getPreferenceList("autopublished.extensions");

                for (var j=0; j<autopublishedExtensions.length; j++)
                {
                    if (autopublishedExtensions[j] == guid)
                    {
                        delete autopublishedExtensions[j];
                        break;
                    }
                }

                Bandwagon.Preferences.setPreferenceList("autopublished.extensions", autopublishedExtensions);

                bandwagonService.forceCheckForUpdates(localAutoPublisher);

                if (callback)
                    callback(event);
            }
        }

        for (var id in localAutoPublisher.addons)
        {
            if (localAutoPublisher.addons[id].guid == guid)
            {
                Bandwagon.Logger.debug("Found this extension in local auto publisher: '" + localAutoPublisher.addons[id].guid + "' vs '" + guid + "', will remove.");

                this.removeAddonFromCollection(guid, localAutoPublisher, internalCallback);

                break;
            }
        }
    },

    getAddonsPerPage: function(collection)
    {
        // returns this collection's custom items per page, or else the global value

        var addonsPerPage;
        
        if (collection.addonsPerPage != -1)
        {
            addonsPerPage = collection.addonsPerPage;
        }
        else
        {
            addonsPerPage = Bandwagon.Preferences.getPreference("global.addonsperpage");
        }

        if (addonsPerPage < 1)
        {
            addonsPerPage = 1;
        }

        return addonsPerPage;
    },

    getPreviouslySharedEmailAddresses: function()
    {
        return Bandwagon.Preferences.getPreferenceList("publish.shared.emails");
    },

    clearPreviouslySharedEmailAddresses: function()
    {
        Bandwagon.Preferences.setPreferenceList("publish.shared.emails", []);
    },

    addPreviouslySharedEmailAddress: function(emailAddress)
    {
        emailAddress = emailAddress.replace(/^\s+/, "");
        emailAddress = emailAddress.replace(/\s+$/, "");
        emailAddress = emailAddress.replace(/^,/, "");
        emailAddress = emailAddress.replace(/,$/, "");

        var previouslySharedEmailAddresses = this.getPreviouslySharedEmailAddresses();

        for (var i=0; i<previouslySharedEmailAddresses.length; i++)
        {
            if (previouslySharedEmailAddresses[i] == emailAddress)
            {
                return;
            }
        }

        previouslySharedEmailAddresses.push(emailAddress);

        Bandwagon.Preferences.setPreferenceList("publish.shared.emails", previouslySharedEmailAddresses);
    },

    addPreviouslySharedEmailAddresses: function(commaSeparatedEmailAddresses)
    {
        var bits = commaSeparatedEmailAddresses.split(",");

        for (var i=0; i<bits.length; i++)
        {
            if (bits[i].match(/.*@.*/))
            {
                this.addPreviouslySharedEmailAddress(bits[i]);
            }
        }
    },

    publishToCollection: function(extension, collection, personalNote, callback)
    {
        if (!this.isAuthenticated())
            return;

        this._service.publishToCollection(extension, collection, personalNote, callback);
    },

    shareToEmail: function(extension, emailAddress, personalNote, callback)
    {
        if (!this.isAuthenticated())
            return;

        // trim any commas from a multi-email string

        emailAddress = emailAddress.replace(/^,/, "");
        emailAddress = emailAddress.replace(/,$/, "");

        this._service.shareToEmail(extension, emailAddress, personalNote, callback);
    },

    /**
     * Performs a 'soft' check for authenication. I.e. do we have a token from a previous auth. This method doesn't
     * check if that token is still valid on the server.
     */
    isAuthenticated: function()
    {
        return (Bandwagon.Preferences.getPreference(Bandwagon.PREF_AUTH_TOKEN) != "");
    },

    deleteAMOCookie: function()
    {
        var cm = CookieManager.getService(nsICookieManager);

        var iterator = cm.enumerator;

        var cookieHost = Bandwagon.AMO_AUTH_COOKIE_HOST.replace("%%AMO_HOST%%", Bandwagon.Preferences.getPreference("amo_host"));

        while (iterator.hasMoreElements())
        {
            var cookie = iterator.getNext();

            if (cookie instanceof Ci.nsICookie)
            {
                if (cookie.host == cookieHost && cookie.name == Bandwagon.AMO_AUTH_COOKIE_NAME)
                {
                    // KILL!
                    cm.remove(cookie.host, cookie.name, cookie.path, false);
                }
            }
        }
    },

    _collectionUpdateObserver: function(collection)
    {
        // called when a collection is updated

        // if there are new items, notify the user if notifications are enabled for this user and it's not a preview of a collection

        Bandwagon.Logger.debug("in _collectionUpdateObserver() with collection '" + collection + "', unnotified collection items = " + collection.getUnnotifiedAddons().length)

        var showNotificationsForThisCollection;

        if (collection.showNotifications == -1)
        {
            showNotificationsForThisCollection = Bandwagon.Preferences.getPreference("global.notify.enabled");
        }
        else
        {
            showNotificationsForThisCollection = collection.showNotifications;
        }

        if (showNotificationsForThisCollection && collection.getUnnotifiedAddons().length > 0)
        {
            var app = getAppName();
            var appWinString = "navigator:browser"; // default, Firefox
            if (app == "Thunderbird")
                appWinString = "mail:3pane";
            var appWindow = WindowMediator.getService(nsIWindowMediator).getMostRecentWindow(appWinString);

            if (appWindow)
            {
                appWindow.Bandwagon.Controller.BrowserOverlay.showNewAddonsAlert(collection);
            }
            else
            {
                Bandwagon.Logger.error("Can't find a browser window to notify the user");
            }

            collection.setAllNotified();
        }

        // commit the collection

        if (Bandwagon.COMMIT_NOW)
            bandwagonService.commit(collection);
    },

    _initStorage: function()
    {
        var storageService = Storage.getService(mozIStorageService);

        var file = DirectoryService.getService(nsIProperties).get("ProfD", nsIFile);
        file.append(Bandwagon.EMID);

        if (!file.exists() || !file.isDirectory())
        {
            file.create(nsIFile.DIRECTORY_TYPE, 0777);
        }

        file.append(Bandwagon.SQLITE_FILENAME);

        try
        {
            this._storageConnection = storageService.openUnsharedDatabase(file);
        }
        catch (e)
        {
            Bandwagon.Logger.error("Error opening Storage connection: " + e);
            return;
        }

        if (this._storageConnection.executeAsync)
            this._collectionFactory = new Bandwagon.Factory.CollectionFactory2(this._storageConnection, Bandwagon);
        else
            this._collectionFactory = new Bandwagon.Factory.CollectionFactory(this._storageConnection, Bandwagon);

        this._initStorageTables();
    },

    _initStorageTables: function()
    {
        if (!this._storageConnection)
            return;

        // create tables (if they're not already created)

        this._storageConnection.beginTransaction();

        try
        {
            this._storageConnection.executeSimpleSQL(
                "CREATE TABLE IF NOT EXISTS serviceDocument "
                + "(emailResourceURL TEXT NOT NULL, "
                + "collectionListResourceURL TEXT NOT NULL)"
                );
            this._storageConnection.executeSimpleSQL(
                "CREATE TABLE IF NOT EXISTS collections "
                + "(id INTEGER PRIMARY KEY AUTOINCREMENT, "
                + "url TEXT NOT NULL UNIQUE, "
                + "name TEXT NOT NULL, "
                + "description TEXT, "
                + "dateAdded INTEGER NOT NULL, "
                + "dateLastCheck INTEGER, "
                + "updateInterval INTEGER NOT NULL, "
                + "showNotifications INTEGER NOT NULL, "
                + "autoPublish INTEGER NOT NULL, "
                + "active INTEGER NOT NULL DEFAULT 1, "
                + "addonsPerPage INTEGER NOT NULL, "
                + "creator TEXT, "
                + "listed INTEGER NOT NULL DEFAULT 1, "
                + "writable INTEGER NOT NULL DEFAULT 0, "
                + "subscribed INTEGER NOT NULL DEFAULT 1, "
                + "lastModified INTEGER, "
                + "addonsResourceURL TEXT, "
                + "type TEXT)"
                );
            this._storageConnection.executeSimpleSQL(
                "CREATE TABLE IF NOT EXISTS collectionsLinks "
                + "(id INTEGER PRIMARY KEY AUTOINCREMENT, "
                + "collection INTEGER NOT NULL, "
                + "name TEXT NOT NULL, "
                + "href TEXT NOT NULL)"
                );
            this._storageConnection.executeSimpleSQL(
                "CREATE TABLE IF NOT EXISTS collectionsAddons "
                + "(id INTEGER PRIMARY KEY AUTOINCREMENT, "
                + "collection INTEGER NOT NULL, "
                + "addon INTEGER NOT NULL, "
                + "read INTEGER NOT NULL DEFAULT 0)"
                );
            this._storageConnection.executeSimpleSQL(
                "CREATE TABLE IF NOT EXISTS addons "
                + "(id INTEGER PRIMARY KEY AUTOINCREMENT, "
                + "guid TEXT NOT NULL UNIQUE, "
                + "name TEXT NOT NULL, "
                + "type INTEGER NOT NULL, "
                + "version TEXT NOT NULL, "
                + "status INTEGER NOT NULL, "
                + "summary TEXT, "
                + "description TEXT, "
                + "icon TEXT, "
                + "eula TEXT, "
                + "thumbnail TEXT, "
                + "learnmore TEXT NOT NULL, "
                + "author TEXT, "
                + "category TEXT, "
                + "dateAdded INTEGER NOT NULL)"
                );
            this._storageConnection.executeSimpleSQL(
                "CREATE TABLE IF NOT EXISTS addonCompatibleApplications "
                + "(addon INTEGER NOT NULL, "
                + "name TEXT NOT NULL, "
                + "applicationId INTEGER NOT NULL, "
                + "minVersion TEXT NOT NULL, "
                + "maxVersion TEXT NOT NULL, "
                + "guid TEXT NOT NULL)"
                );
            this._storageConnection.executeSimpleSQL(
                "CREATE TABLE IF NOT EXISTS addonCompatibleOS "
                + "(addon INTEGER NOT NULL, "
                + "name TEXT NOT NULL)"
                );
            this._storageConnection.executeSimpleSQL(
                "CREATE TABLE IF NOT EXISTS addonInstalls "
                + "(addon INTEGER NOT NULL, "
                + "url TEXT NOT NULL, "
                + "hash TEXT NOT NULL, "
                + "os TEXT NOT NULL)"
                );
            this._storageConnection.executeSimpleSQL(
                "CREATE TABLE IF NOT EXISTS addonComments "
                + "(addon INTEGER NOT NULL, "
                + "comment TEXT NOT NULL, "
                + "author TEXT NOT NULL)"
                );
            this._storageConnection.executeSimpleSQL(
                "CREATE TABLE IF NOT EXISTS addonAuthors "
                + "(addon INTEGER NOT NULL, "
                + "author TEXT NOT NULL)"
                );
        }
        catch (e)
        {
            Bandwagon.Logger.error("Error creating sqlite table: " + e);
            this._storageConnection.rollbackTransaction();
            return;
        }

        if (this._storageConnection.schemaVersion < 103)
        {
            // sql schema updates for bandwagon 1.0.3

            try
            {
                this._storageConnection.executeSimpleSQL("ALTER TABLE collections ADD COLUMN iconURL TEXT");
            }
            catch (e)
            {
                Bandwagon.Logger.warn("Error updating sqlite schema (possibly harmless): " + e);
            }

            this._storageConnection.schemaVersion = 103;
        }

        if (this._storageConnection.schemaVersion < 105)
        {
            // sql schema updates for bandwagon 1.0.5

            try
            {
                this._storageConnection.executeSimpleSQL("ALTER TABLE addons ADD COLUMN type2 TEXT");
            }
            catch (e)
            {
                Bandwagon.Logger.warn("Error updating sqlite schema (possibly harmless): " + e);
            }

            this._storageConnection.schemaVersion = 105;
        }

        if (this._storageConnection.schemaVersion < 106)
        {
            // XXX future 1.0.6 schema updates go here
            // this._storageConnection.schemaVersion = 106;
        }

        this._storageConnection.commitTransaction();
    },

    startUninstallObserver : function ()
    {
        if (gUninstallObserverInited) return;

        var extService = Components.classes["@mozilla.org/extensions/manager;1"]
            .getService(Components.interfaces.nsIExtensionManager);

        if (extService && ("uninstallItem" in extService))
        {
            var observerService = Components.classes["@mozilla.org/observer-service;1"]
                .getService(Components.interfaces.nsIObserverService);
            observerService.addObserver(this.addonsAction, "em-action-requested", false);
            gUninstallObserverInited = true;
        }
        else
        {
            try
            {
                extService.datasource.AddObserver(this.addonsObserver);
                gUninstallObserverInited = true;
            }
            catch (e) { }
        }
    },

    addonsObserver:
    {
        onAssert: function (ds, subject, predicate, target)
        {
            if ((predicate.Value == "http://www.mozilla.org/2004/em-rdf#toBeUninstalled")
                    &&
                    (target instanceof Components.interfaces.nsIRDFLiteral)
                    &&
                    (target.Value == "true"))
            {
                if (subject.Value == "urn:mozilla:extension:" + gEmGUID)
                {
                    // This is case where bandwagon is being uninstalled - clean up

                    cleanupSettings();
                }
                else
                {
                    // This is case where some other extension is being uninstalled

                    var val = subject.Value;
                    val = val.replace(/urn:mozilla:extension:/, "");

                    bandwagonService.processOtherExtensionUninstall(val);
                }
            }
        },

        onUnassert: function (ds, subject, predicate, target) {},
        onChange: function (ds, subject, predicate, oldtarget, newtarget) {},
        onMove: function (ds, oldsubject, newsubject, predicate, target) {},
        onBeginUpdateBatch: function() {},
        onEndUpdateBatch: function() {}
    },

    addonsAction:
    {
        observe: function (subject, topic, data)
        {
            if ((data == "item-uninstalled") &&
                (subject instanceof Components.interfaces.nsIUpdateItem))
            {
                if (subject.id == gEmGUID)
                {
                    // This is case where bandwagon is being uninstalled - clean up

                    cleanupSettings();
                }
                else
                {
                    // This is case where some other extension is being uninstalled

                    bandwagonService.processOtherExtensionUninstall(subject.id);
                }
            }
        }
    },

    // for nsISupports
    QueryInterface: function(aIID)
    {
        // add any other interfaces you support here
        if (!aIID.equals(nsISupports))
            throw Components.results.NS_ERROR_NO_INTERFACE;
                
        return this;
    }
}

var BandwagonServiceFactory = {
    singleton: null,
    createInstance: function (aOuter, aIID)
    {
        if (aOuter != null)
            throw Components.results.NS_ERROR_NO_AGGREGATION;

        if (this.singleton == null)
            this.singleton = new BandwagonService();

        return this.singleton.QueryInterface(aIID);
    }
};

var BandwagonServiceModule = {
    registerSelf: function(aCompMgr, aFileSpec, aLocation, aType)
    {
        aCompMgr = aCompMgr.QueryInterface(Components.interfaces.nsIComponentRegistrar);
        aCompMgr.registerFactoryLocation(CLASS_ID, CLASS_NAME, CONTRACT_ID, aFileSpec, aLocation, aType);
    },

    unregisterSelf: function(aCompMgr, aLocation, aType)
    {
        aCompMgr = aCompMgr.QueryInterface(Components.interfaces.nsIComponentRegistrar);
        aCompMgr.unregisterFactoryLocation(CLASS_ID, aLocation);        
    },

    getClassObject: function(aCompMgr, aCID, aIID)
    {
        if (!aIID.equals(Components.interfaces.nsIFactory))
            throw Components.results.NS_ERROR_NOT_IMPLEMENTED;

        if (aCID.equals(CLASS_ID))
            return BandwagonServiceFactory;

        throw Components.results.NS_ERROR_NO_INTERFACE;
    },

    canUnload: function(aCompMgr) { return true; }
};

//module initialization
function NSGetModule(aCompMgr, aFileSpec) { return BandwagonServiceModule; }

