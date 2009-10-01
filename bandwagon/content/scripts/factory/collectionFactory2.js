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

Bandwagon.Factory.CollectionFactory2 = function(connection, bw)
{
    this.Bandwagon = bw;
    this.connection = connection;

    //this.connection.executeSimpleSQL("PRAGMA locking_mode = EXCLUSIVE");

    this.Bandwagon.Logger.debug("Initialized CollectionFactory (v2)");
}

Bandwagon.Factory.CollectionFactory2.prototype.handleError = function(aError)
{
    Bandwagon.Logger.warn("Storage Error: " + aError.message);
}

Bandwagon.Factory.CollectionFactory2.prototype.openServiceDocument = function(callback)
{
    var cf = this;

    if (!this.connection)
        return null;

    var serviceDocument = null;

    var statement = this.connection.createStatement("SELECT * FROM serviceDocument LIMIT 1");

    return statement.executeAsync(
    {
        handleResult: function(aResultSet)
        {
            while (row = aResultSet.getNextRow())
            {
                serviceDocument = new cf.Bandwagon.Model.ServiceDocument(cf.Bandwagon);
                serviceDocument.emailResourceURL = row.getResultByName("emailResourceURL");
                serviceDocument.collectionListResourceURL = row.getResultByName("collectionListResourceURL");
            }
        },
        handleError: cf.handleError,
        handleCompletion: function(aReason)
        {
            callback(aReason, serviceDocument);
        }
    });
}

Bandwagon.Factory.CollectionFactory2.prototype.commitServiceDocument = function(serviceDocument, callback)
{
    var cf = this;

    if (!this.connection)
        return null;

    var deleteStatement = this.connection.createStatement("DELETE FROM serviceDocument");

    var insertStatement = this.connection.createStatement("INSERT INTO serviceDocument VALUES (:emailResourceURL, :collectionListResourceURL)");
    insertStatement.params.emailResourceURL = serviceDocument.emailResourceURL;
    insertStatement.params.collectionListResourceURL = serviceDocument.collectionListResourceURL;
 
    return this.connection.executeAsync(
        [deleteStatement, insertStatement],
        2,
        {
            handleResult: function(aResultSet) {}, 
            handleError: cf.handleError,
            handleCompletion: function(aReason)
            {
                if (callback)
                    callback(aReason);
            }
        });
}

Bandwagon.Factory.CollectionFactory2.prototype.newCollection = function()
{
    return new this.Bandwagon.Model.Collection(this.Bandwagon);
}

Bandwagon.Factory.CollectionFactory2.prototype.openCollection = function(collection_id, callback)
{
    // NOT NEEDED FOR NOW
}

Bandwagon.Factory.CollectionFactory2.prototype.openCollections = function(callback)
{
    var cf = this;

    if (!this.connection)
        return null;

    var lastCallback = callback;
    var collections = {};
    var statement = this.connection.createStatement("SELECT * FROM collections");

    return statement.executeAsync(
    {
        handleResult: function(aResultSet)
        {
            while (row = aResultSet.getNextRow())
            {
                var collection = cf._openCollectionFromRS(row);

                if (!collection)
                    continue;

                collections[collection.resourceURL] = collection;
            }
        },
        handleError: cf.handleError,
        handleCompletion: function(aReason)
        {
            if (aReason == cf.Bandwagon.STMT_OK)
            {
                cf._openAddons(collections, lastCallback);
            }
            else
            {
                lastCallback(aReason);
            }
        }
    });
}

Bandwagon.Factory.CollectionFactory2.prototype.commitCollection = function(collection)
{
    var cf = this;

    if (!this.connection)
        return;

    var insertStatement = cf.connection.createStatement("INSERT INTO collections VALUES (:resourceURL, :name, :description, :dateAdded, :dateLastCheck, :updateInterval, :showNotifications, :autoPublish, :active, :addonsPerPage, :creator, :listed, :writable, :subscribed, :lastModified, :addonsResourceURL, :type, :iconURL)");

    insertStatement.params.resourceURL = collection.resourceURL;
    insertStatement.params.name = collection.name;
    insertStatement.params.description = collection.description;
    if (collection.dateAdded && collection.dateAdded instanceof Date)
        insertStatement.params.dateAdded = collection.dateAdded.getTime()/1000;
    else
        insertStatement.params.dateAdded = Date.now()/1000;
    if (collection.dateLastCheck && collection.dateLastCheck instanceof Date)
        insertStatement.params.dateLastCheck = collection.dateLastCheck.getTime()/1000;
    else
        insertStatement.params.dateLastCheck = null;
    insertStatement.params.updateInterval = collection.updateInterval;
    insertStatement.params.showNotifications = collection.showNotifications;
    insertStatement.params.autoPublish = (collection.autoPublish?1:0);
    insertStatement.params.active = (collection.active?1:0);
    insertStatement.params.addonsPerPage = collection.addonsPerPage;
    insertStatement.params.creator = collection.creator;
    insertStatement.params.listed = collection.listed;
    insertStatement.params.writable = collection.writable;
    insertStatement.params.subscribed = collection.subscribed;
    if (collection.lastModified && collection.lastModified instanceof Date)
        insertStatement.params.lastModified = collection.lastModified.getTime()/1000;
    else
        insertStatement.params.lastModified = null;
    insertStatement.params.addonsResourceURL = collection.addonsResourceURL;
    insertStatement.params.type = collection.type;
    insertStatement.params.iconURL = collection.iconURL;

    var statements = [insertStatement];
    statements = statements.concat(cf._commitCollectionsAddons(collection));
    statements = statements.concat(cf._commitCollectionsLinks(collection));

    return statements;
}

Bandwagon.Factory.CollectionFactory2.prototype._commitCollectionsLinks = function(collection)
{
    var cf = this;

    if (!this.connection)
        return;

    var statements = [];

    for (var id in collection.links)
    {
        var insertStatement = this.connection.createStatement("INSERT INTO collectionsLinks VALUES (:resourceURL, :id, :link)");

        insertStatement.params.resourceURL = collection.resourceURL;
        insertStatement.params.id = id;
        insertStatement.params.link = collection.links[id];

        statements.push(insertStatement);
    }

    return statements;
}

Bandwagon.Factory.CollectionFactory2.prototype._commitCollectionsAddons = function(collection)
{
    var cf = this;

    if (!this.connection)
        return;

    var statements = [];

    for (var id in collection.addons)
    {
        var addon = collection.addons[id];

        var statement = cf.connection.createStatement("INSERT OR IGNORE INTO addons VALUES (:guid, :name, :type, :version, :status, :summary, :description, :icon, :eula, :thumbnail, :learnmore, :author, :category, :dateAdded, :type2)");

        statement.params.guid = addon.guid;
        statement.params.name = addon.name;
        statement.params.type = addon.type;
        statement.params.version = addon.version;
        statement.params.status = addon.status;
        statement.params.summary = addon.summary;
        statement.params.description = addon.description;
        statement.params.icon = addon.icon;
        statement.params.eula = addon.eula;
        statement.params.thumbnail = addon.thumbnail;
        statement.params.learnmore = addon.learnmore;
        statement.params.author = null;
        statement.params.category = null;
        statement.params.dateAdded = addon.dateAdded.getTime()/1000;
        statement.params.type2 = addon.type2;

        statements.push(statement);

        for (var id in addon.compatibleApplications)
        {
            statements.push(cf._commitAddonCompatibleApplication(addon, addon.compatibleApplications[id]));
        }

        for (var id in addon.compatibleOS)
        {
            statements.push(cf._commitAddonCompatibleOS(addon, addon.compatibleOS[id]));
        }

        for (var id in addon.installs)
        {
            statements.push(cf._commitAddonInstall(addon, addon.installs[id]));
        }

        for (var i=0; i<addon.comments.length; i++)
        {
            statements.push(cf._commitAddonComment(addon, addon.comments[i]));
        }

        for (var id in addon.authors)
        {
            statements.push(cf._commitAddonAuthor(addon, addon.authors[id]));
        }

        statements.push(cf._commitCollectionsAddonsTuple(addon, collection));
    }

    return statements;
}

Bandwagon.Factory.CollectionFactory2.prototype.commitCollections = function(collections)
{
    var cf = this;

    var statements = [];

    statements.push(this.connection.createStatement("DELETE FROM collections"));
    statements.push(this.connection.createStatement("DELETE FROM collectionsLinks"));
    statements.push(this.connection.createStatement("DELETE FROM collectionsAddons"));
    statements.push(this.connection.createStatement("DELETE FROM addons"));
    statements.push(this.connection.createStatement("DELETE FROM addonCompatibleApplications"));
    statements.push(this.connection.createStatement("DELETE FROM addonCompatibleOS"));
    statements.push(this.connection.createStatement("DELETE FROM addonInstalls"));
    statements.push(this.connection.createStatement("DELETE FROM addonAuthors"));
    statements.push(this.connection.createStatement("DELETE FROM addonComments"));

    for (var id in collections)
    {
        statements = statements.concat(cf.commitCollection(collections[id]));
    }

    return this.connection.executeAsync(
        statements,
        statements.length,
        {
            handleResult: function(aResultSet) {},
            handleError: cf.handleError,
            handleCompletion: function(aReason)
            {
                if (aReason == cf.Bandwagon.STMT_OK)
                {
                    cf.Bandwagon.Logger.debug("finished commitCollections");
                }
            }
        });
}

Bandwagon.Factory.CollectionFactory2.prototype.deleteCollection = function(collection, callback)
{
    var cf = this;

    if (!this.connection)
        return;

    var statement1 = this.connection.createStatement("DELETE FROM collections where url = :resourceURL");
    statement1.params.resourceURL = collection.resourceURL;

    var statement2 = this.connection.createStatement("DELETE FROM collectionsAddons where collection = :resourceURL");
    statement2.params.resourceURL = collection.resourceURL;
 
    return this.connection.executeAsync(
        [statement1, statement2],
        2,
        {
            handleResult: function(aResultSet) {}, 
            handleError: cf.handleError,
            handleCompletion: function(aReason)
            {
                if (callback)
                    callback(aReason);
            }
        });
}

// private methods

Bandwagon.Factory.CollectionFactory2.prototype._openCollectionFromRS = function(row)
{
    var collection = new this.Bandwagon.Model.Collection(this.Bandwagon);

    collection.resourceURL = row.getResultByName("url");
    collection.name = row.getResultByName("name")
    collection.description = row.getResultByName("description");
    collection.dateAdded = row.getResultByName("dateAdded") * 1000;

    var dateLastCheck = row.getResultByName("dateLastCheck") * 1000;
    collection.dateLastCheck = (dateLastCheck?dateLastCheck:null);

    collection.updateInterval = row.getResultByName("updateInterval");
    collection.showNotifications = row.getResultByName("showNotifications");
    collection.autoPublish = (row.getResultByName("autoPublish")==1?true:false);
    collection.active = (row.getResultByName("active")==1?true:false);
    collection.addonsPerPage = row.getResultByName("addonsPerPage");
    collection.creator = row.getResultByName("creator");
    collection.listed = row.getResultByName("listed");
    collection.writable = row.getResultByName("writable");
    collection.subscribed = row.getResultByName("subscribed");

    var lastModified = row.getResultByName("lastModified") * 1000;
    collection.lastModified = (lastModified?lastModified:null);

    collection.addonsResourceURL = row.getResultByName("addonsResourceURL");
    collection.type = row.getResultByName("type");
    collection.iconURL = row.getResultByName("iconURL");

    return collection;
}

Bandwagon.Factory.CollectionFactory2.prototype._openAddons = function(collections, lastCallback)
{
    var cf = this;

    if (!this.connection)
        return null;

    var statement = this.connection.createStatement(
        "SELECT addons.*, collectionsAddons.read, collections.url AS collectionResourceURL"
        + " FROM addons LEFT JOIN collectionsAddons ON addons.guid = collectionsAddons.addon"
        + " LEFT JOIN collections ON collectionsAddons.collection = collections.url"
        + " ORDER BY collections.name");

    return statement.executeAsync(
    {
        handleResult: function(aResultSet)
        {
            while (row = aResultSet.getNextRow())
            {
                var addon = cf._openAddonFromRS(row);
                var collectionResourceURL = row.getResultByName("collectionResourceURL");

                if (!addon || !collectionResourceURL || !collections[collectionResourceURL])
                    continue;

                collections[collectionResourceURL].addons[addon.guid] = addon;
            }
        },
        handleError: cf.handleError,
        handleCompletion: function(aReason)
        {
            if (aReason == cf.Bandwagon.STMT_OK)
            {
                cf._openCollectionsLinks(collections, lastCallback);
            }
            else
            {
                lastCallback(aReason);
            }
        }
    });
}

Bandwagon.Factory.CollectionFactory2.prototype._openAddonFromRS = function(row)
{
    var addon = new this.Bandwagon.Model.Addon(Bandwagon);
    addon.Bandwagon = this.Bandwagon;

    addon.guid = row.getResultByName("guid");
    addon.name = row.getResultByName("name");
    addon.type = row.getResultByName("type");
    addon.version = row.getResultByName("version");
    addon.status = row.getResultByName("status");
    addon.summary = row.getResultByName("summary");
    addon.description = row.getResultByName("description");
    addon.icon = row.getResultByName("icon");
    addon.eula = row.getResultByName("eula");
    addon.thumbnail = row.getResultByName("thumbnail");
    addon.learnmore = row.getResultByName("learnmore");
    addon.author = row.getResultByName("author");
    addon.category = row.getResultByName("category");
    addon.dateAdded = new Date(row.getResultByName("dateAdded")*1000);
    addon.type2 = row.getResultByName("type2");
    addon.read = (row.getResultByName("read")==1?true:false);

    return addon;
}

Bandwagon.Factory.CollectionFactory2.prototype._openCollectionsLinks = function(collections, lastCallback)
{
    var cf = this;

    if (!this.connection)
        return null;

    var statement = this.connection.createStatement(
        "SELECT collectionsLinks.*, collections.url AS collectionResourceURL"
        + " FROM collectionsLinks LEFT JOIN collections ON collectionsLinks.collection = collections.url"
        + " ORDER BY collections.name");

    return statement.executeAsync(
    {
        handleResult: function(aResultSet)
        {
            while (row = aResultSet.getNextRow())
            {
                var collectionResourceURL = row.getResultByName("collectionResourceURL");

                var name = row.getResultByName("name");
                var href = row.getResultByName("href");

                if (!name || !href || !collectionResourceURL || !collections[collectionResourceURL])
                    continue;

                collections[collectionResourceURL].links[name] = href;
            }
        },
        handleError: cf.handleError,
        handleCompletion: function(aReason)
        {
            if (aReason == cf.Bandwagon.STMT_OK)
            {
                cf._openAddonCompatibleApplications(collections, lastCallback);
            }
            else
            {
                lastCallback(aReason);
            }
        }
    });
}

Bandwagon.Factory.CollectionFactory2.prototype._openAddonCompatibleApplications = function(collections, lastCallback)
{
    var cf = this;

    if (!this.connection)
        return null;

    var statement = this.connection.createStatement(
        "SELECT addonCompatibleApplications.*, addons.guid AS addonGUID, collections.url AS collectionResourceURL"
        + " FROM addonCompatibleApplications LEFT JOIN addons ON addonCompatibleApplications.addon = addons.guid"
        + " LEFT JOIN collectionsAddons ON addons.guid = collectionsAddons.addon"
        + " LEFT JOIN collections ON collectionsAddons.collection = collections.url"
        + " ORDER BY collections.name");

    return statement.executeAsync(
    {
        handleResult: function(aResultSet)
        {
            while (row = aResultSet.getNextRow())
            {
                var collectionResourceURL = row.getResultByName("collectionResourceURL");
                var addonGUID = row.getResultByName("addonGUID");

                var application =
                {
                    name: row.getResultByName("name").toUpperCase(),
                    applicationId: row.getResultByName("applicationId"),
                    minVersion: row.getResultByName("minVersion"),
                    maxVersion: row.getResultByName("maxVersion"),
                    guid: row.getResultByName("guid")
                };

                if (!collectionResourceURL || !collections[collectionResourceURL] || !addonGUID || !collections[collectionResourceURL].addons[addonGUID])
                    continue;

                collections[collectionResourceURL].addons[addonGUID].compatibleApplications[application.name.toUpperCase()] = application;
            }
        },
        handleError: cf.handleError,
        handleCompletion: function(aReason)
        {
            if (aReason == cf.Bandwagon.STMT_OK)
            {
                cf._openAddonCompatibleOS(collections, lastCallback);
            }
            else
            {
                lastCallback(aReason);
            }
        }
    });
}

Bandwagon.Factory.CollectionFactory2.prototype._openAddonCompatibleOS = function(collections, lastCallback)
{
    var cf = this;

    if (!this.connection)
        return null;

    var statement = this.connection.createStatement(
        "SELECT addonCompatibleOS.*, addons.guid AS addonGUID, collections.url AS collectionResourceURL"
        + " FROM addonCompatibleOS LEFT JOIN addons ON addonCompatibleOS.addon = addons.guid"
        + " LEFT JOIN collectionsAddons ON addons.guid = collectionsAddons.addon"
        + " LEFT JOIN collections ON collectionsAddons.collection = collections.url"
        + " ORDER BY collections.name");

    return statement.executeAsync(
    {
        handleResult: function(aResultSet)
        {
            while (row = aResultSet.getNextRow())
            {
                var collectionResourceURL = row.getResultByName("collectionResourceURL");
                var addonGUID = row.getResultByName("addonGUID");

                var os = row.getResultByName("name").toUpperCase();

                if (!collectionResourceURL || !collections[collectionResourceURL] || !addonGUID || !collections[collectionResourceURL].addons[addonGUID])
                    continue;

                collections[collectionResourceURL].addons[addonGUID].compatibleOS[os] = os;
            }
        },
        handleError: cf.handleError,
        handleCompletion: function(aReason)
        {
            if (aReason == cf.Bandwagon.STMT_OK)
            {
                cf._openAddonInstalls(collections, lastCallback);
            }
            else
            {
                lastCallback(aReason);
            }
        }
    });
}

Bandwagon.Factory.CollectionFactory2.prototype._openAddonInstalls = function(collections, lastCallback)
{
    var cf = this;

    if (!this.connection)
        return null;

    var statement = this.connection.createStatement(
        "SELECT addonInstalls.*, addons.guid AS addonGUID, collections.url AS collectionResourceURL"
        + " FROM addonInstalls LEFT JOIN addons ON addonInstalls.addon = addons.guid"
        + " LEFT JOIN collectionsAddons ON addons.guid = collectionsAddons.addon"
        + " LEFT JOIN collections ON collectionsAddons.collection = collections.url"
        + " ORDER BY collections.name");

    return statement.executeAsync(
    {
        handleResult: function(aResultSet)
        {
            while (row = aResultSet.getNextRow())
            {
                var collectionResourceURL = row.getResultByName("collectionResourceURL");
                var addonGUID = row.getResultByName("addonGUID");

                var install =
                {
                    url: row.getResultByName("url"),
                    hash: row.getResultByName("hash"),
                    os: row.getResultByName("os")
                };

                if (!collectionResourceURL || !collections[collectionResourceURL] || !addonGUID || !collections[collectionResourceURL].addons[addonGUID])
                    continue;

                collections[collectionResourceURL].addons[addonGUID].installs[install.os] = install;
            }
        },
        handleError: cf.handleError,
        handleCompletion: function(aReason)
        {
            if (aReason == cf.Bandwagon.STMT_OK)
            {
                cf._openAddonComments(collections, lastCallback);
            }
            else
            {
                lastCallback(aReason);
            }
        }
    });
}

Bandwagon.Factory.CollectionFactory2.prototype._openAddonComments = function(collections, lastCallback)
{
    var cf = this;

    if (!this.connection)
        return null;

    var statement = this.connection.createStatement(
        "SELECT addonComments.*, addons.guid AS addonGUID, collections.url AS collectionResourceURL"
        + " FROM addonComments LEFT JOIN addons ON addonComments.addon = addons.guid"
        + " LEFT JOIN collectionsAddons ON addons.guid = collectionsAddons.addon"
        + " LEFT JOIN collections ON collectionsAddons.collection = collections.url"
        + " ORDER BY collections.name");

    return statement.executeAsync(
    {
        handleResult: function(aResultSet)
        {
            while (row = aResultSet.getNextRow())
            {
                var collectionResourceURL = row.getResultByName("collectionResourceURL");
                var addonGUID = row.getResultByName("addonGUID");

                var comment =
                {
                    comment: row.getResultByName("comment"),
                    author: row.getResultByName("author")
                };

                if (!collectionResourceURL || !collections[collectionResourceURL] || !addonGUID || !collections[collectionResourceURL].addons[addonGUID])
                    continue;

                collections[collectionResourceURL].addons[addonGUID].comments.push(comment);
            }
        },
        handleError: cf.handleError,
        handleCompletion: function(aReason)
        {
            if (aReason == cf.Bandwagon.STMT_OK)
            {
                cf._openAddonAuthors(collections, lastCallback);
            }
            else
            {
                lastCallback(aReason);
            }
        }
    });
}

Bandwagon.Factory.CollectionFactory2.prototype._openAddonAuthors = function(collections, lastCallback)
{
    var cf = this;

    if (!this.connection)
        return null;

    var statement = this.connection.createStatement(
        "SELECT addonAuthors.*, addons.guid AS addonGUID, collections.url AS collectionResourceURL"
        + " FROM addonAuthors LEFT JOIN addons ON addonAuthors.addon = addons.guid"
        + " LEFT JOIN collectionsAddons ON addons.guid = collectionsAddons.addon"
        + " LEFT JOIN collections ON collectionsAddons.collection = collections.url"
        + " ORDER BY collections.name");

    return statement.executeAsync(
    {
        handleResult: function(aResultSet)
        {
            while (row = aResultSet.getNextRow())
            {
                var collectionResourceURL = row.getResultByName("collectionResourceURL");
                var addonGUID = row.getResultByName("addonGUID");
                var author = row.getResultByName("author");

                if (!collectionResourceURL || !collections[collectionResourceURL] || !addonGUID || !collections[collectionResourceURL].addons[addonGUID])
                    continue;

                collections[collectionResourceURL].addons[addonGUID].addAuthor(author);
            }
        },
        handleError: cf.handleError,
        handleCompletion: function(aReason)
        {
            lastCallback(aReason, collections);
        }
    });
}

Bandwagon.Factory.CollectionFactory2.prototype._commitAddonCompatibleApplication = function(addon, application)
{
    var insertStatement = this.connection.createStatement("INSERT OR IGNORE INTO addonCompatibleApplications VALUES (:guid, :name, :applicationId, :minVersion, :maxVersion, :appguid)");

    insertStatement.params.guid = addon.guid;
    insertStatement.params.name = application.name;
    insertStatement.params.applicationId = application.applicationId;
    insertStatement.params.minVersion = application.minVersion;
    insertStatement.params.maxVersion = application.maxVersion;
    insertStatement.params.appguid = application.guid;

    return insertStatement;
}

Bandwagon.Factory.CollectionFactory2.prototype._commitAddonCompatibleOS = function(addon, os)
{
    var insertStatement = this.connection.createStatement("INSERT OR IGNORE INTO addonCompatibleOS VALUES (:guid, :os)");

    insertStatement.params.guid = addon.guid;
    insertStatement.params.os = os;

    return insertStatement
}

Bandwagon.Factory.CollectionFactory2.prototype._commitAddonInstall = function(addon, install)
{
    var insertStatement = this.connection.createStatement("INSERT OR IGNORE INTO addonInstalls VALUES (:guid, :url, :hash, :os)");

    insertStatement.params.guid = addon.guid;
    insertStatement.params.url = install.url;
    insertStatement.params.hash = install.hash;
    insertStatement.params.os = install.os;

    return insertStatement;
}

Bandwagon.Factory.CollectionFactory2.prototype._commitAddonComment = function(addon, comment)
{
    var insertStatement = this.connection.createStatement("INSERT OR IGNORE INTO addonComments VALUES (:guid, :comment, :author)");

    insertStatement.params.guid = addon.guid;
    insertStatement.params.comment = comment.comment;
    insertStatement.params.author = comment.author;

    return insertStatement;
}

Bandwagon.Factory.CollectionFactory2.prototype._commitAddonAuthor = function(addon, author)
{
    var insertStatement = this.connection.createStatement("INSERT OR IGNORE INTO addonAuthors VALUES (:guid, :author)");

    insertStatement.params.guid = addon.guid;
    insertStatement.params.author = author;

    return insertStatement;
}

Bandwagon.Factory.CollectionFactory2.prototype._commitCollectionsAddonsTuple = function(addon, collection)
{
    var insertStatement = this.connection.createStatement("INSERT INTO collectionsAddons VALUES (:resourceURL, :guid, :read)");

    insertStatement.params.resourceURL = collection.resourceURL;
    insertStatement.params.guid = addon.guid;
    insertStatement.params.read = (addon.read?1:0);

    return insertStatement;
}

