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

Bandwagon.RPC = new function() {}

Bandwagon.RPC.Service = function()
{
    // keep reference these things in case they go away. (we're in a component - it happens)
    this.Bandwagon = Bandwagon;
    this.Components = Components;

    // private instance variables
    this._observers = new Array();
    this._logger = null;
    this._serviceDocument = null;
    this._serviceRootURL = this.Bandwagon.RPC.Constants.BANDWAGON_RPC_SERVICE_DOCUMENT;

    this.rpcComplete = function(rpcnet, result, response, type, callback)
    {
        var service = this;

        service._logger.debug("Bandwagon.RPC.Service: got rpc complete (id=" + (rpcnet?rpcnet.id:"null") + ",s=" + (rpcnet?rpcnet.status:"null") + ") of type = " + type + " and status = " + result);

        var event = new service.Bandwagon.RPC.Event(type, result, response);
        event.Bandwagon = service.Bandwagon;

        if (result == service.Bandwagon.RPC.Constants.BANDWAGON_RPC_NET_FAILURE)
        {
            // response is error code
            service._logger.debug("Bandwagon.RPC.Service: complete is error with error code: " + response.errorCode + ", message: " + response.errorMessage);
            event.error = new service.Bandwagon.RPC.Error(response.errorCode, response.errorMessage);
            event._response = response.data;
        }
        
        // CALLBACK TYPE 1: if we have a callback, call it

        if (callback && typeof(callback) == 'function')
        {
            callback(event);
        }

        // CALLBACK TYPE 2: if we have observers, notify them

        service.notifyObservers(event);
    };

    this.rpcCompleteWithError = function(rpcnet, errorCode, type)
    {
        this.rpcComplete(rpcnet, this.Bandwagon.RPC.Constants.BANDWAGON_RPC_RPC_FAILURE, errorCode, type);
    }

    this.notifyObservers = function(event)
    {
        //for (var i=0; i<this._observers.length; i++)
        for (var id in this._observers)
        {
            if (this._observers[id])
            {
                try
                {
                    this._observers[id](event);
                }
                catch (e)
                {
                    this._logger.error("Bandwagon.RPC.Service.onComplete: error notifying observer: " + e);
                    //Bandwagon.Util.dumpObject(e);
                }
            }
        }
    };

    this.rpcSend = function(type, callback, action, method, data, url, credentials)
    {
        var service = this;

        var rpcnet = new service.Bandwagon.RPC.Net(service.Bandwagon, service.Components);

        rpcnet.registerLogger(service._logger);
        var isLogin = (type == service.Bandwagon.RPC.Constants.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_LOGIN_COMPLETE);
        var actionUrl = (action.match(/^https?:/)?action:(this._serviceRootURL+action));
        service._logger.debug("Bandwagon.RPC.Service: request going to : " + actionUrl);
        rpcnet.setUrl(actionUrl);
        rpcnet.setType(type);

        if (method && method == "POST")
        {
            rpcnet.setMethod("POST");
            rpcnet.setPostData(data);
        }
        else if (method == "DELETE")
        {
            rpcnet.setMethod("DELETE");
        }
        else if (method == "PUT")
        {
            rpcnet.setMethod("PUT");
            rpcnet.setPostData(data);
        }
        else
        {
            rpcnet.setMethod("GET");

            if (service.Bandwagon.RPC.Constants.BANDWAGON_RPC_ENABLE_CACHE_BUSTER)
            {
                if (data == null)
                    data = [];

                data["__"] = (new Date()).getTime();
            }

            rpcnet.setArguments(data);
        }

        rpcnet.setHeader("X-API-Auth", service.Bandwagon.Preferences.getPreference(service.Bandwagon.PREF_AUTH_TOKEN));

        if (credentials)
        {
            rpcnet.setCredentials(credentials.login, credentials.password);
        }

        rpcnet.onComplete = function(rpc, result, response, type) { service.rpcComplete(rpc, result, response, type, callback); };

        // send immediately
        service._logger.debug("Bandwagon.RPC.Service: sending rpc immediately");
        rpcnet.send();
    };

    this.createAction = function(action, replacements)
    {
        if (replacements)
        {
            for (var i=0; i<replacements.length; i++)
            {
                action = action.replace("%" + (i+1), encodeURIComponent(replacements[i]));
            }
        }

        return action;
    };

    this.getUniqueId = function()
    {
        var id = ((new Date()).getTime() - 1169730000000) + "" + (Math.round(1000*Math.random())+1000);
        return id;
    };
}

/* 
 * Public Utility Methods
 */

Bandwagon.RPC.Service.prototype.registerLogger = function(logger)
{
    this._logger = logger;
}

Bandwagon.RPC.Service.prototype.registerObserver = function(observer)
{
    var id = this.getUniqueId();

    this._observers[id] = observer;

    this._logger.info('Bandwagon.RPC.Service.registerObserver: adding observer, id = ' + id);

    return id;
}

Bandwagon.RPC.Service.prototype.unregisterObserver = function(observerId)
{
    for (var i in this._observers)
    {
        if (i == observerId)
        {
            this._observers[observerId] = null;
            this._logger.debug('Bandwagon.RPC.Service.unregisterObserver: removed observer, id = ' + observerId);
            return;
        }
    }
}

/*
 * Bandwagon Protocol Methods Below Here
 */

Bandwagon.RPC.Service.prototype.authenticate = function(login, password, callback)
{
    var service = this;

    this._logger.debug("Bandwagon.RPC.Service.authenticate: getting auth token for user '" + login + "'");

    var internalCallback2 = function(event)
    {
        if (event.isError())
        {
            service._logger.info("Bandwagon.RPC.Service.authenticate: authentication failed");
        }
        else
        {
            event.authToken = event.getData().attribute("value");

            if (!event.authToken.match(/.*\w.*/))
            {
                // invalid auth token (bug 496612)
                service._logger.error("Bandwagon.RPC.Service.authenticate: invalid auth token: '" + event.authToken + "'");

                event._result = service.Bandwagon.RPC.Constants.BANDWAGON_RPC_SERVICE_ERROR_UNEXPECTED_XML;
            }
            else
            {
                service._logger.debug("Bandwagon.RPC.Service.authenticate: have an auth token: " + event.authToken);

                service.Bandwagon.Preferences.setPreference(service.Bandwagon.PREF_AUTH_TOKEN, event.authToken);
            }
        }

        if (callback)
        {
            callback(event);
        }
    }

    var internalCallback1 = function(event)
    {
        if (event && event.authURL)
        {
            service._logger.debug("Bandwagon.RPC.Service.authenticate: using authURL = " + event.authURL);

            service.rpcSend(service.Bandwagon.RPC.Constants.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_GET_AUTH_DOCUMENT_COMPLETE, 
                         internalCallback2, 
                         event.authURL,
                         "POST",
                         null,
                         null,
                         {login: login, password: password}
                         );
        }
        else if (callback)
        {
            callback(event);
        }
    }

    this.getServiceDocument(internalCallback1);
}

Bandwagon.RPC.Service.prototype.getServiceDocument = function(callback)
{
	var service = this;

    this._logger.debug("Bandwagon.RPC.Service.getServiceDocument: getting service document for logged in user");

    var internalCallback = function(event)
    {
        if (event.isError())
        {
            // in the case of unauthorized access, we are given the href to the auth resource
            event.authURL = "";

            try
            {
                event.authURL = event.getData().attribute("href");
            }
            catch (e) {}

            if (event.authURL != "")
            {
                service._logger.debug("Bandwagon.RPC.Service.getServiceDocument: is error, but have an authURL = " + event.authURL);
            }
            else
            {
                service._logger.debug("Bandwagon.RPC.Service.getServiceDocument: is error and there is no authURL");
            }
        }
        else
        {
            event.serviceDocument = new Bandwagon.Model.ServiceDocument();
            event.serviceDocument.unserialize(event.getData());
        }

        if (callback)
        {
            callback(event);
        }
    }

    this.rpcSend(service.Bandwagon.RPC.Constants.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_GET_SERVICE_DOCUMENT_COMPLETE, 
                 internalCallback, 
                 service.Bandwagon.RPC.Constants.BANDWAGON_RPC_SERVICE_DOCUMENT,
                 "GET",
                 null);
}

Bandwagon.RPC.Service.prototype.getCollection = function(collection, callback)
{
    var service = this;

    this._logger.debug("Bandwagon.RPC.Service.getCollection: getting updates for collection '" + collection.toString() +  "' ...");

    collection.status = collection.STATUS_LOADING;

    var data = null;

    var internalCallback = function(event)
    {
        if (event.isError())
        {
            collection.status = collection.STATUS_LOADERROR;
        }
        else
        {
            collection.unserialize(event.getData());
            collection.status = collection.STATUS_LOADED;
        }

        event.collection = collection;

        if (callback)
        {
            callback(event);
        }
    }

    this.rpcSend(service.Bandwagon.RPC.Constants.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_GET_COLLECTION_COMPLETE, 
                 internalCallback, 
                 collection.resourceURL,
                 "GET",
                 data);
}

Bandwagon.RPC.Service.prototype.newCollection = function(collection, callback)
{
    var service = this;

    this._logger.debug("Bandwagon.RPC.Service.newCollection: creating new collection '" + collection.toString() +  "' ...");

    if (collection.name == "" || service._serviceDocument == null)
    {
        if (callback)
            callback(new this.Bandwagon.RPC.Event());

        return;
    }

    var data = {
        name: collection.name,
        description: collection.description,
        nickname: collection.getNicknameFromName(),
        listed: (collection.listed?1:0)
    };

    var internalCallback = function(event)
    {
        if (event.isError())
        {
            collection.status = collection.STATUS_LOADERROR;
        }
        else
        {
            collection.unserialize(event.getData());

            collection.resourceURL = event.getData().@xmlbase.toString();
            collection.addonsResourceURL = event.getData().@xmlbase.toString() + event.getData().addons.attribute("href").toString();

            service._logger.debug("Bandwagon.RPC.Service.newCollection: create new autopub collection with resourceURL = " + collection.resourceURL);

            collection.status = collection.STATUS_LOADED;
        }

        event.collection = collection;

        if (callback)
        {
            callback(event);
        }
    }

    this.rpcSend(service.Bandwagon.RPC.Constants.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_NEW_COLLECTION_COMPLETE, 
                 internalCallback, 
                 service._serviceDocument.collectionListResourceURL,
                 "POST",
                 data);
}

Bandwagon.RPC.Service.prototype.deleteCollection = function(collection, callback)
{
    var service = this;

    this._logger.debug("Bandwagon.RPC.Service.deleteCollection: deleting collection '" + collection.toString() +  "' ...");

    if (collection == null)
    {
        if (callback)
            callback(new this.Bandwagon.RPC.Event());

        return;
    }

    this.rpcSend(service.Bandwagon.RPC.Constants.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_DELETE_COLLECTION_COMPLETE, 
                 callback, 
                 collection.resourceURL,
                 "DELETE",
                 null);
}

Bandwagon.RPC.Service.prototype.unsubscribeFromCollection = function(collection, callback)
{
    var service = this;

    this._logger.debug("Bandwagon.RPC.Service.unsubscribeFromCollection: unsubscribing from collection '" + collection.toString() +  "' ...");

    if (collection == null)
    {
        if (callback)
            callback(new this.Bandwagon.RPC.Event());

        return;
    }

    var data = {
        "subscribed": "no"
    };

    this.rpcSend(service.Bandwagon.RPC.Constants.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_UNSUBSCRIBE_FROM_COLLECTION_COMPLETE, 
                 callback, 
                 collection.resourceURL,
                 "PUT",
                 data);
}

Bandwagon.RPC.Service.prototype.subscribeToCollection = function(collection, callback)
{
    var service = this;

    this._logger.debug("Bandwagon.RPC.Service.subscribeToCollection: subscribing to collection '" + collection.toString() +  "' ...");

    if (collection == null)
    {
        if (callback)
            callback(new this.Bandwagon.RPC.Event());

        return;
    }

    var data = {
        "subscribed": "yes"
    };

    this.rpcSend(service.Bandwagon.RPC.Constants.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_SUBSCRIBE_TO_COLLECTION_COMPLETE, 
                 callback, 
                 collection.resourceURL,
                 "PUT",
                 data);
}

Bandwagon.RPC.Service.prototype.updateCollectionDetails = function(collection, callback)
{
    var service = this;

    this._logger.debug("Bandwagon.RPC.Service.updateCollectionDetails: updating collection details for '" + collection.toString() +  "' ...");

    if (collection == null)
    {
        if (callback)
            callback(new this.Bandwagon.RPC.Event());

        return;
    }

    collection.status = collection.STATUS_LOADING;

    var data = {
        "name": collection.name,
        "description": collection.description,
        "listed": (collection.listed?"1":"0"),
        "subscribed": (collection.subscribed?"yes":"no")
    };

    var internalCallback = function(event)
    {
        if (event.isError())
        {
            collection.status = collection.STATUS_LOADERROR;
        }
        else
        {
            collection.unserialize(event.getData());
            collection.status = collection.STATUS_LOADED;
        }

        event.collection = collection;

        if (callback)
        {
            callback(event);
        }
    }

    this.rpcSend(service.Bandwagon.RPC.Constants.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_UPDATE_COLLECTION_COMPLETE, 
                 internalCallback, 
                 collection.resourceURL,
                 "PUT",
                 data);
}

Bandwagon.RPC.Service.prototype.removeAddonFromCollection = function(guid, collection, callback)
{
    var service = this;

    Bandwagon.Logger.debug("Bandwagon.RPC.Service.removeAddonFromCollection: extension.guid = '" + guid + "', collection = '" + collection.resourceURL);

    var internalCallback = function(event)
    {
        // don't need to do anything here
        if (callback)
            callback(event);
    }

    this.rpcSend(service.Bandwagon.RPC.Constants.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_REMOVE_ADDON_FROM_COLLECTION_COMPLETE, 
                 internalCallback, 
                 collection.addonsResourceURL + guid,
                 "DELETE",
                 null);
}

Bandwagon.RPC.Service.prototype.publishToCollection = function(extension, collection, personalNote, callback)
{
    var service = this;

    Bandwagon.Logger.debug("Bandwagon.RPC.Service.publishToCollection: extension.guid = '" + extension.guid + "', extension.name = '" + extension.name + "', collection = '" + collection.resourceURL + "', personalNote = '" + personalNote + "'");

    var data = {
        "guid": extension.guid,
        "comments": personalNote
    };

    var internalCallback = function(event)
    {
        // don't need to do anything here
        if (callback)
            callback(event);
    }

    this.rpcSend(service.Bandwagon.RPC.Constants.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_PUBLISH_COMPLETE, 
                 internalCallback, 
                 collection.addonsResourceURL,
                 "POST",
                 data);
}

Bandwagon.RPC.Service.prototype.shareToEmail = function(extension, emailAddress, personalNote, callback)
{
    var service = this;

    Bandwagon.Logger.debug("Bandwagon.RPC.Service.shareToEmail: extension.guid = '" + extension.guid + "', extension.name = '" + extension.name + "', emailAddress = '" + emailAddress + "', personalNote = '" + personalNote + "'");

    if (!extension.guid || extension.guid == "" || !emailAddress || emailAddress == "" || service._serviceDocument == null)
    {
        if (callback)
            callback(new this.Bandwagon.RPC.Event());

        return;
    }

    personalNote = personalNote.replace("\n", "\r", "gi");

    var data = {
        "guid": extension.guid,
        "to": emailAddress,
        "message": personalNote
    };

    var internalCallback = function(event)
    {
        // don't need to do anything here
        if (callback)
            callback(event);
    }

    this.rpcSend(service.Bandwagon.RPC.Constants.BANDWAGON_RPC_EVENT_TYPE_BANDWAGON_RPC_SHARE_TO_EMAIL_COMPLETE, 
                 internalCallback, 
                 service._serviceDocument.emailResourceURL,
                 "POST",
                 data);
}


