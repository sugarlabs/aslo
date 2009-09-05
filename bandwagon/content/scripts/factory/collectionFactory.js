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

Bandwagon.Factory.CollectionFactory = function(connection, bw)
{
    this.Bandwagon = bw;
    this.connection = connection;

    this.connection.executeSimpleSQL("PRAGMA locking_mode = EXCLUSIVE");
}

Bandwagon.Factory.CollectionFactory.prototype.openServiceDocument = function(callback)
{
    if (!this.connection)
        return null;
        
    var statement = this.connection.createStatement("SELECT * FROM serviceDocument LIMIT 1");

    var serviceDocument = null;

    try
    {
        while (statement.executeStep())
        {
            serviceDocument = new this.Bandwagon.Model.ServiceDocument(this.Bandwagon);
            serviceDocument.emailResourceURL = statement.getUTF8String(0);
            serviceDocument.collectionListResourceURL = statement.getUTF8String(1);
        }
    }
    finally
    {
        statement.reset();
    }

    callback(serviceDocument!=null?this.Bandwagon.STMT_OK:this.Bandwagon.STMT_ERR, serviceDocument);
}

Bandwagon.Factory.CollectionFactory.prototype.commitServiceDocument = function(serviceDocument, callback)
{
    if (!this.connection)
        return;

    this.connection.beginTransaction();

    var statement1 = this.connection.createStatement("DELETE FROM serviceDocument");

    try
    {
        statement1.execute();
    }
    catch (e)
    {
        this.connection.rollbackTransaction();
        throw e;
    }
    finally
    {
        statement1.reset();
    }

    var statement2 = this.connection.createStatement("INSERT INTO serviceDocument VALUES (?1, ?2)");

    try
    {
        statement2.bindUTF8StringParameter(0, serviceDocument.emailResourceURL);
        statement2.bindUTF8StringParameter(1, serviceDocument.collectionListResourceURL);
        statement2.execute();
    }
    catch (e)
    {
        this.connection.rollbackTransaction();
        throw e;
    }
    finally
    {
        statement2.reset();
    }

    this.connection.commitTransaction();

    if (callback)
        callback(Bandwagon.STMT_OK);
}

Bandwagon.Factory.CollectionFactory.prototype.newCollection = function()
{
    return new this.Bandwagon.Model.Collection(this.Bandwagon);
}

Bandwagon.Factory.CollectionFactory.prototype.openCollection = function(collection_id, callback)
{
    if (!this.connection)
        return null;

    var collections = {};
        
    var statement = this.connection.createStatement("SELECT * FROM collections where id = ?1");

    try
    {
        statement.bindInt32Parameter(0, collection_id);
        statement.execute();

        var collection = this._openCollectionFromRS(statement);

        if (!collection)
            return null;

        collection.addons = this._openAddons(collection);
        collection.links = this._openCollectionLinks(collection);

        collections[collection.resourceURL] = collection;
    }
    finally
    {
        statement.reset();
    }

    callback(Bandwagon.STMT_OK, collection);
}

Bandwagon.Factory.CollectionFactory.prototype.openCollections = function(callback)
{
    if (!this.connection)
        return null;

    var collections = {};
        
    var statement = this.connection.createStatement("SELECT * FROM collections");

    try
    {
        while (statement.executeStep())
        {
            var collection = this._openCollectionFromRS(statement);

            if (!collection)
                continue;

            collection.addons = this._openAddons(collection);
            collection.links = this._openCollectionLinks(collection);

            collections[collection.resourceURL] = collection;
        }
    }
    finally
    {
        statement.reset();
    }

    if (callback)
        callback(Bandwagon.STMT_OK, collections);
}

Bandwagon.Factory.CollectionFactory.prototype.commitCollection = function(collection)
{
    if (!this.connection)
        return;

    this.connection.beginTransaction();

    var statement = this.connection.createStatement("REPLACE INTO collections VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7, ?8, ?9, ?10, ?11, ?12, ?13, ?14, ?15, ?16, ?17, ?18, ?19)");

    try
    {
        (collection.storageID==-1?statement.bindNullParameter(0):statement.bindInt32Parameter(0, collection.storageID));
        statement.bindUTF8StringParameter(1, collection.resourceURL);
        statement.bindUTF8StringParameter(2, collection.name);
        statement.bindUTF8StringParameter(3, collection.description);
        statement.bindInt32Parameter(4, collection.dateAdded.getTime()/1000);
        (collection.dateLastCheck == null?statement.bindNullParameter(5):statement.bindInt32Parameter(5, collection.dateLastCheck.getTime()/1000));
        statement.bindInt32Parameter(6, collection.updateInterval);
        statement.bindInt32Parameter(7, collection.showNotifications);
        statement.bindInt32Parameter(8, (collection.autoPublish?1:0));
        statement.bindInt32Parameter(9, (collection.active?1:0));
        statement.bindInt32Parameter(10, collection.addonsPerPage);
        statement.bindUTF8StringParameter(11, collection.creator);
        statement.bindInt32Parameter(12, collection.listed);
        statement.bindInt32Parameter(13, collection.writable);
        statement.bindInt32Parameter(14, collection.subscribed);
        (collection.lastModified == null?statement.bindNullParameter(15):statement.bindInt32Parameter(15, collection.lastModified.getTime()/1000));
        statement.bindUTF8StringParameter(16, collection.addonsResourceURL);
        statement.bindUTF8StringParameter(17, collection.type);
        statement.bindUTF8StringParameter(18, collection.iconURL);
        
        statement.execute();
    }
    catch (e)
    {
        this.connection.rollbackTransaction();
        throw e;
    }
    finally
    {
        statement.reset();
    }

    if (collection.storageID == -1) 
    {
        collection.storageID = this.connection.lastInsertRowID;
    }

    var statement3 = this.connection.createStatement("DELETE FROM collectionsAddons where collection = ?1");

    try
    {
        statement3.bindInt32Parameter(0, collection.storageID);
        statement3.execute();
    }
    catch (e)
    {
        this.connection.rollbackTransaction();
        throw e;
    }
    finally
    {
        statement3.reset();
    }

    for (var id in collection.addons)
    {
        try
        {
            this._commitAddon(collection, collection.addons[id]);
        }
        catch (e)
        {
            this.connection.rollbackTransaction();
            throw e;
        }
    }

    var statement2 = this.connection.createStatement("DELETE FROM collectionsLinks WHERE collection = ?1");

    try
    {
        statement2.bindInt32Parameter(0, collection.storageID);
        statement2.execute();
    }
    catch (e)
    {
        this.connection.rollbackTransaction();
        throw e;
    }
    finally
    {
        statement2.reset();
    }

    for (var id in collection.links)
    {
        try
        {
            this._commitCollectionLink(collection, id);
        }
        catch (e)
        {
            this.connection.rollbackTransaction();
            throw e;
        }
    }

    this.connection.commitTransaction();

    return true;
}

Bandwagon.Factory.CollectionFactory.prototype.commitCollections = function(collections)
{
    for (var id in collections)
    {
        this.commitCollection(collections[id]);
    }
}

Bandwagon.Factory.CollectionFactory.prototype.deleteCollection = function(collection, callback)
{
    if (!this.connection)
        return;

    this.connection.beginTransaction();

    var statement1 = this.connection.createStatement("DELETE FROM collections where id = ?1");
    var statement2 = this.connection.createStatement("DELETE FROM collectionsAddons where collection = ?1");

    try
    {
        statement1.bindInt32Parameter(0, collection.storageID);
        statement1.execute();

        statement2.bindInt32Parameter(0, collection.storageID);
        statement2.execute();
    }
    catch (e)
    {
        this.connection.rollbackTransaction();
        throw e;
    }
    finally
    {
        statement1.reset();
        statement2.reset();
    }

    this.connection.commitTransaction();

    if (callback)
        callback(statement2.lastError>0?Bandwagon.STMT_ERR:Bandwagon.STMT_OK);
}

// private methods

Bandwagon.Factory.CollectionFactory.prototype._openCollectionFromRS = function(resultset)
{
        var collection = new this.Bandwagon.Model.Collection(this.Bandwagon);
        collection.storageID = resultset.getInt32(0);
        collection.resourceURL = resultset.getUTF8String(1);
        collection.name = resultset.getUTF8String(2);
        collection.description = resultset.getUTF8String(3);
        collection.dateAdded = new Date(resultset.getInt32(4)*1000);

        if (!resultset.getIsNull(5))
            collection.dateLastCheck = new Date(resultset.getInt32(5)*1000);

        collection.updateInterval = resultset.getInt32(6);
        collection.showNotifications = resultset.getInt32(7);
        collection.autoPublish = (resultset.getInt32(8)==1?true:false);
        collection.active = (resultset.getInt32(9)==1?true:false);
        collection.addonsPerPage = resultset.getInt32(10);
        collection.creator = resultset.getUTF8String(11);
        collection.listed = resultset.getInt32(12);
        collection.writable = resultset.getInt32(13);
        collection.subscribed = resultset.getInt32(14);

        if (!resultset.getIsNull(15))
            collection.lastModified = new Date(resultset.getInt32(15)*1000);

        collection.addonsResourceURL = resultset.getUTF8String(16);
        collection.type = resultset.getUTF8String(17);
        collection.iconURL = resultset.getUTF8String(18);

        return collection;
}

Bandwagon.Factory.CollectionFactory.prototype._openCollectionLinks = function(collection)
{
    var links = {};

    var statement = this.connection.createStatement("SELECT * FROM collectionsLinks WHERE collection = ?1");

    try
    {
        statement.bindInt32Parameter(0, collection.storageID);
        
        while (statement.executeStep())
        {
            var name = statement.getUTF8String(2);
            var href = statement.getUTF8String(3);
            links[name] = href;
        }
    }
    finally
    {
        statement.reset();
    }

    return links;
}

Bandwagon.Factory.CollectionFactory.prototype._openAddons = function(collection)
{
    var addons = {};

    var statement = this.connection.createStatement("SELECT addons.*, collectionsAddons.id, collectionsAddons.read FROM addons LEFT JOIN collectionsAddons ON addons.id = collectionsAddons.addon WHERE collectionsAddons.collection = ?1");

    try
    {
        statement.bindInt32Parameter(0, collection.storageID);

        while (statement.executeStep())
        {
            var addon = new this.Bandwagon.Model.Addon(Bandwagon);
            addon.Bandwagon = this.Bandwagon;

            addon.storageID = statement.getInt32(0);
            addon.guid = statement.getUTF8String(1);
            addon.name = statement.getUTF8String(2);
            addon.type = statement.getInt32(3);
            addon.version = statement.getUTF8String(4);
            addon.status = statement.getInt32(5);
            addon.summary = statement.getUTF8String(6);
            addon.description = statement.getUTF8String(7);
            addon.icon = statement.getUTF8String(8);
            addon.eula = statement.getUTF8String(9);
            addon.thumbnail = statement.getUTF8String(10);
            addon.learnmore = statement.getUTF8String(11);
            addon.author = statement.getUTF8String(12);
            addon.category = statement.getUTF8String(13);
            addon.dateAdded = new Date(statement.getInt32(14)*1000);
            addon.type2 = statement.getUTF8String(15);
            addon.collectionsAddonsStorageID = statement.getInt32(16);
            addon.read = (statement.getInt32(17)==1?true:false);

            addon.compatibleApplications = this._openAddonCompatibleApplications(addon);
            addon.compatibleOS = this._openAddonCompatibleOS(addon);
            addon.installs = this._openAddonInstalls(addon);
            addon.comments = this._openAddonComments(addon);
            addon.authors = this._openAddonAuthors(addon);

            addons[addon.guid] = addon;
        }
    }
    finally
    {
        statement.reset();
    }

    return addons;
}

Bandwagon.Factory.CollectionFactory.prototype._openAddonCompatibleApplications = function(addon)
{
    var compatibleApplications = {};
        
    var statement = this.connection.createStatement("SELECT * FROM addonCompatibleApplications WHERE addon = ?1");

    try
    {
        statement.bindInt32Parameter(0, addon.storageID);

        while (statement.executeStep())
        {
            var application =
            {
                name: statement.getUTF8String(1).toString().toUpperCase(),
                applicationId: statement.getInt32(2),
                minVersion: statement.getUTF8String(3),
                maxVersion: statement.getUTF8String(4),
                guid: statement.getUTF8String(5)
            };

            compatibleApplications[application.name.toUpperCase()] = application;
        }
    }
    finally
    {
        statement.reset();
    }

    return compatibleApplications;
}

Bandwagon.Factory.CollectionFactory.prototype._openAddonCompatibleOS = function(addon)
{
    var compatibleOS = {};
        
    var statement = this.connection.createStatement("SELECT * FROM addonCompatibleOS WHERE addon = ?1");

    try
    {
        statement.bindInt32Parameter(0, addon.storageID);

        while (statement.executeStep())
        {
            var os = statement.getUTF8String(1).toString().toUpperCase();

            compatibleOS[os] = os;
        }
    }
    finally
    {
        statement.reset();
    }

    return compatibleOS;
}

Bandwagon.Factory.CollectionFactory.prototype._openAddonInstalls = function(addon)
{
    var installs = {};
        
    var statement = this.connection.createStatement("SELECT * FROM addonInstalls WHERE addon = ?1");

    try
    {
        statement.bindInt32Parameter(0, addon.storageID);

        while (statement.executeStep())
        {
            var install =
            {
                url: statement.getUTF8String(1),
                hash: statement.getUTF8String(2),
                os: statement.getUTF8String(3).toString().toUpperCase()
            };

            installs[install.os] = install;
        }
    }
    finally
    {
        statement.reset();
    }

    return installs;
}

Bandwagon.Factory.CollectionFactory.prototype._openAddonComments = function(addon)
{
    var comments = [];
        
    var statement = this.connection.createStatement("SELECT * FROM addonComments WHERE addon = ?1");

    try
    {
        statement.bindInt32Parameter(0, addon.storageID);

        while (statement.executeStep())
        {
            var comment =
            {
                comment: statement.getUTF8String(1),
                author: statement.getUTF8String(2)
            };

            comments.push(comment);
        }
    }
    finally
    {
        statement.reset();
    }

    return comments;
}

Bandwagon.Factory.CollectionFactory.prototype._openAddonAuthors = function(addon)
{
    var authors = [];
        
    var statement = this.connection.createStatement("SELECT * FROM addonAuthors WHERE addon = ?1");

    try
    {
        statement.bindInt32Parameter(0, addon.storageID);

        while (statement.executeStep())
        {
            authors.push(statement.getUTF8String(1));
        }
    }
    finally
    {
        statement.reset();
    }

    return authors;
}

Bandwagon.Factory.CollectionFactory.prototype._commitCollectionLink = function(collection, linkName)
{
    if (!this.connection)
        return;

    var statement = this.connection.createStatement("INSERT INTO collectionsLinks VALUES (?1, ?2, ?3, ?4)");

    try
    {
        statement.bindNullParameter(0);
        statement.bindInt32Parameter(1, collection.storageID);
        statement.bindUTF8StringParameter(2, linkName);
        statement.bindUTF8StringParameter(3, collection.links[linkName]);

        statement.execute();
    }
    finally
    {
        statement.reset();
    }
}

Bandwagon.Factory.CollectionFactory.prototype._commitAddon = function(collection, addon)
{
    if (!this.connection)
        return;

    // addons 
    // if guid already exists - just update the addon
    // if guid doesn't exist  - insert

    var statement = this.connection.createStatement("SELECT id FROM addons where guid = ?1");
    var addonStorageID = null;

    try
    {
        statement.bindUTF8StringParameter(0, addon.guid);
    
        while (statement.executeStep())
        {
            addonStorageID = statement.getInt32(0);
        }
    }
    finally
    {
        statement.reset();
    }

    var statement2 = this.connection.createStatement("REPLACE INTO addons VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7, ?8, ?9, ?10, ?11, ?12, ?13, ?14, ?15, ?16)");

    try
    {
        if (addonStorageID != null)
        {
            // addon already exists (in another collection, or from previous commit of this collection)
            statement2.bindInt32Parameter(0, addonStorageID);
        }
        else if (addon.storageID != -1)
        {
            // addon doesn't already exist, but exists from a previous commit of this collection (?)
            statement2.bindInt32Parameter(0, addon.storageID);
        }
        else
        {
            // new addon
            statement2.bindNullParameter(0)
        }

        statement2.bindUTF8StringParameter(1, addon.guid);
        statement2.bindUTF8StringParameter(2, addon.name);
        statement2.bindInt32Parameter(3, addon.type);
        statement2.bindUTF8StringParameter(4, addon.version);
        statement2.bindInt32Parameter(5, addon.status);
        statement2.bindUTF8StringParameter(6, addon.summary);
        statement2.bindUTF8StringParameter(7, addon.description);
        statement2.bindUTF8StringParameter(8, addon.icon);
        statement2.bindUTF8StringParameter(9, addon.eula);
        statement2.bindUTF8StringParameter(10, addon.thumbnail);
        statement2.bindUTF8StringParameter(11, addon.learnmore);
        statement2.bindUTF8StringParameter(12, addon.author);
        statement2.bindUTF8StringParameter(13, addon.category);
        statement2.bindUTF8StringParameter(14, addon.dateAdded.getTime()/1000);
        statement2.bindUTF8StringParameter(15, addon.type2);
        
        statement2.execute();
    }
    finally
    {
        statement2.reset();
    }

    if (addon.storageID == -1 && addonStorageID == null) 
    {
        addonStorageID = this.connection.lastInsertRowID;
    }

    // add the other addon bits

    for (var id in addon.compatibleApplications)
    {
        this._commitAddonCompatibleApplication(addonStorageID, addon.compatibleApplications[id]);
    }

    for (var id in addon.compatibleOS)
    {
        this._commitAddonCompatibleOS(addonStorageID, addon.compatibleOS[id]);
    }

    for (var id in addon.installs)
    {
        this._commitAddonInstall(addonStorageID, addon.installs[id]);
    }

    for (var i=0; i<addon.comments.length; i++)
    {
        this._commitAddonComment(addonStorageID, addon.comments[i]);
    }

    for (var id in addon.authors)
    {
        this._commitAddonAuthor(addonStorageID, addon.authors[id]);
    }

    // add the addon connector

    var statement3 = this.connection.createStatement("REPLACE INTO collectionsAddons VALUES (?1, ?2, ?3, ?4)");

    try
    {
        if (addon.collectionsAddonsStorageID == -1)
        {
            statement3.bindNullParameter(0);
        }
        else
        {
            statement3.bindInt32Parameter(0, addon.collectionsAddonsStorageID);
        }

        statement3.bindInt32Parameter(1, collection.storageID);
        statement3.bindInt32Parameter(2, addonStorageID);
        statement3.bindInt32Parameter(3, (addon.read?1:0));
        
        statement3.execute();
    }
    finally
    {
        statement3.reset();
    }

    if (addon.collectionsAddonsStorageID == -1)
    {
        addon.collectionsAddonsStorageID = this.connection.lastInsertRowID;
    }

    return true;
}

Bandwagon.Factory.CollectionFactory.prototype._commitAddonCompatibleApplication = function(addonStorageID, application)
{
    var statement = this.connection.createStatement("DELETE FROM addonCompatibleApplications WHERE addon = ?1 AND applicationId = ?2");

    try
    {
        statement.bindInt32Parameter(0, addonStorageID);
        statement.bindInt32Parameter(1, application.applicationId);
        statement.execute();
    }
    finally
    {
        statement.reset();
    }

    var statement2 = this.connection.createStatement("INSERT INTO addonCompatibleApplications VALUES (?1, ?2, ?3, ?4, ?5, ?6)");

    try
    {
        statement2.bindInt32Parameter(0, addonStorageID);
        statement2.bindUTF8StringParameter(1, application.name);
        statement2.bindInt32Parameter(2, application.applicationId);
        statement2.bindUTF8StringParameter(3, application.minVersion);
        statement2.bindUTF8StringParameter(4, application.maxVersion);
        statement2.bindUTF8StringParameter(5, application.guid);
        
        statement2.execute();
    }
    finally
    {
        statement2.reset();
    }
}

Bandwagon.Factory.CollectionFactory.prototype._commitAddonCompatibleOS = function(addonStorageID, os)
{
    var statement = this.connection.createStatement("DELETE FROM addonCompatibleOS WHERE addon = ?1");

    try
    {
        statement.bindInt32Parameter(0, addonStorageID);
        statement.execute();
    }
    finally
    {
        statement.reset();
    }

    var statement2 = this.connection.createStatement("INSERT INTO addonCompatibleOS VALUES (?1, ?2)");

    try
    {
        statement2.bindInt32Parameter(0, addonStorageID);
        statement2.bindUTF8StringParameter(1, os);
        
        statement2.execute();
    }
    finally
    {
        statement2.reset();
    }
}

Bandwagon.Factory.CollectionFactory.prototype._commitAddonInstall = function(addonStorageID, install)
{
    var statement = this.connection.createStatement("DELETE FROM addonInstalls WHERE addon = ?1");

    try
    {
        statement.bindInt32Parameter(0, addonStorageID);
        statement.execute();
    }
    finally
    {
        statement.reset();
    }

    var statement2 = this.connection.createStatement("INSERT INTO addonInstalls VALUES (?1, ?2, ?3, ?4)");

    try
    {
        statement2.bindInt32Parameter(0, addonStorageID);
        statement2.bindUTF8StringParameter(1, install.url);
        statement2.bindUTF8StringParameter(2, install.hash);
        statement2.bindUTF8StringParameter(3, install.os);
        
        statement2.execute();
    }
    finally
    {
        statement2.reset();
    }
}

Bandwagon.Factory.CollectionFactory.prototype._commitAddonComment = function(addonStorageID, comment)
{
    var statement = this.connection.createStatement("DELETE FROM addonComments WHERE addon = ?1");

    try
    {
        statement.bindInt32Parameter(0, addonStorageID);
        statement.execute();
    }
    finally
    {
        statement.reset();
    }

    var statement2 = this.connection.createStatement("INSERT INTO addonComments VALUES (?1, ?2, ?3)");

    try
    {
        statement2.bindInt32Parameter(0, addonStorageID);
        statement2.bindUTF8StringParameter(1, comment.comment);
        statement2.bindUTF8StringParameter(2, comment.author);
        
        statement2.execute();
    }
    finally
    {
        statement2.reset();
    }
}

Bandwagon.Factory.CollectionFactory.prototype._commitAddonAuthor = function(addonStorageID, author)
{
    var statement = this.connection.createStatement("DELETE FROM addonAuthors WHERE addon = ?1");

    try
    {
        statement.bindInt32Parameter(0, addonStorageID);
        statement.execute();
    }
    finally
    {
        statement.reset();
    }

    var statement2 = this.connection.createStatement("INSERT INTO addonAuthors VALUES (?1, ?2)");

    try
    {
        statement2.bindInt32Parameter(0, addonStorageID);
        statement2.bindUTF8StringParameter(1, author);
        
        statement2.execute();
    }
    finally
    {
        statement2.reset();
    }
}


