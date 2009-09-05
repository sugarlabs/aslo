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

    //this.connection.executeSimpleSQL("PRAGMA locking_mode = EXCLUSIVE");
}

Bandwagon.Factory.CollectionFactory.prototype.handleError = function(aError)
{
    Bandwagon.Logger.warn("Storage Error: " + aError.message);
}

Bandwagon.Factory.CollectionFactory.prototype.openServiceDocument = function(callback)
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

Bandwagon.Factory.CollectionFactory.prototype.commitServiceDocument = function(serviceDocument, callback)
{
    var cf = this;

    if (!this.connection)
        return null;

    var deleteStatement = this.connection.createStatement("DELETE FROM serviceDocument");

    var insertStatement = this.connection.createStatement("INSERT INTO serviceDocument VALUES (?1, ?2)");
    insertStatement.bindUTF8StringParameter(0, serviceDocument.emailResourceURL);
    insertStatement.bindUTF8StringParameter(1, serviceDocument.collectionListResourceURL);
 
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

Bandwagon.Factory.CollectionFactory.prototype.newCollection = function()
{
    return new this.Bandwagon.Model.Collection(this.Bandwagon);
}

Bandwagon.Factory.CollectionFactory.prototype.openCollection = function(collection_id, callback)
{
    // NOT NEEDED FOR NOW
}

Bandwagon.Factory.CollectionFactory.prototype.openCollections = function(callback)
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

Bandwagon.Factory.CollectionFactory.prototype.commitCollection = function(collection)
{
    var cf = this;

    if (!this.connection)
        return;

    var replaceStatement = this.connection.createStatement("REPLACE INTO collections VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7, ?8, ?9, ?10, ?11, ?12, ?13, ?14, ?15, ?16, ?17, ?18, ?19)");

    (collection.storageID==-1?replaceStatement.bindNullParameter(0):replaceStatement.bindInt32Parameter(0, collection.storageID));
cf.Bandwagon.Logger.debug("In commitCollection() with param0 = " + (collection.storageID));
    replaceStatement.bindUTF8StringParameter(1, collection.resourceURL);
    replaceStatement.bindUTF8StringParameter(2, collection.name);
    replaceStatement.bindUTF8StringParameter(3, collection.description);
    replaceStatement.bindInt32Parameter(4, collection.dateAdded.getTime()/1000);
    (collection.dateLastCheck == null?replaceStatement.bindNullParameter(5):replaceStatement.bindInt32Parameter(5, collection.dateLastCheck.getTime()/1000));
    replaceStatement.bindInt32Parameter(6, collection.updateInterval);
    replaceStatement.bindInt32Parameter(7, collection.showNotifications);
    replaceStatement.bindInt32Parameter(8, (collection.autoPublish?1:0));
    replaceStatement.bindInt32Parameter(9, (collection.active?1:0));
    replaceStatement.bindInt32Parameter(10, collection.addonsPerPage);
    replaceStatement.bindUTF8StringParameter(11, collection.creator);
    replaceStatement.bindInt32Parameter(12, collection.listed);
    replaceStatement.bindInt32Parameter(13, collection.writable);
    replaceStatement.bindInt32Parameter(14, collection.subscribed);
    (collection.lastModified == null?replaceStatement.bindNullParameter(15):replaceStatement.bindInt32Parameter(15, collection.lastModified.getTime()/1000));
    replaceStatement.bindUTF8StringParameter(16, collection.addonsResourceURL);
    replaceStatement.bindUTF8StringParameter(17, collection.type);
    replaceStatement.bindUTF8StringParameter(18, collection.iconURL);

    cf.Bandwagon.Logger.debug("In commitCollection() with collection: " + collection.resourceURL)

    return replaceStatement.executeAsync({
        handleResult: function(aResultSet) {},
        handleError: cf.handleError,
        handleCompletion: function(aReason)
        {
            if (aReason == cf.Bandwagon.STMT_OK)
            {
        cf.Bandwagon.Logger.debug("Finished commitCollection() with collection: " + collection.resourceURL)
                if (collection.storageID == -1) 
                {
                    collection.storageID = cf.connection.lastInsertRowID;
                }

                //cf._commitCollectionsAddons(collection);
                //cf._commitCollectionsLinks(collection);
            }
        }
    });
}

Bandwagon.Factory.CollectionFactory.prototype._commitCollectionsLinks = function(collection)
{
    var cf = this;

    if (!this.connection)
        return;

    var statements = [];

    var deleteStatement = this.connection.createStatement("DELETE FROM collectionsLinks WHERE collection = ?1");
    deleteStatement.bindInt32Parameter(0, collection.storageID);
    statements.push(deleteStatement);

    for (var id in collection.links)
    {
        var insertStatement = this.connection.createStatement("INSERT INTO collectionsLinks VALUES (?1, ?2, ?3, ?4)");

        insertStatement.bindNullParameter(0);
        insertStatement.bindInt32Parameter(1, collection.storageID);
        insertStatement.bindUTF8StringParameter(2, id);
        insertStatement.bindUTF8StringParameter(3, collection.links[id]);

        statements.push(insertStatement);
    }

    return this.connection.executeAsync(
        statements,
        statements.length,
        {
            handleResult: function(aResultSet) {},
            handleError: cf.handleError,
            handleCompletion: function(aReason) {},
        });
}

Bandwagon.Factory.CollectionFactory.prototype._commitCollectionsAddons = function(collection)
{
    var cf = this;

    if (!this.connection)
        return;

    var deleteStatement = this.connection.createStatement("DELETE FROM collectionsAddons WHERE collection = ?1");
    deleteStatement.bindInt32Parameter(0, collection.storageID);
    deleteStatement.executeAsync(
    {
        handleResult: function(aResultSet) {},
        handleError: cf.handleError,
        handleCompletion: function(aReason)
        {
            if (aReason == cf.Bandwagon.STMT_OK)
            {
                for (var id in collection.addons)
                {
                    var addon = collection.addons[id];
                    //let addon = collection.addons[id]; // FIXME
                    //let addonGUID = collection.addons[id].guid;

                    var statement = cf.connection.createStatement("SELECT id FROM addons where guid = ?1");

                    statement.executeAsync(
                    {
                        addonStorageID: null,
                        handleResult: function(aResultSet)
                        {
                            this.addonStorageID = aResultSet.getNextRow().getResultByName("id");

                        },
                        handleError: cf.handleError,
                        handleCompletion: function(aReason)
                        {
                            //let addonStorageID = this.addonStorageID;
                            var addonStorageID = this.addonStorageID;

                            if (aReason == cf.Bandwagon.STMT_OK)
                            {
                                var statement2 = cf.connection.createStatement("REPLACE INTO addons VALUES (?1, ?2, ?3, ?4, ?5, ?6, ?7, ?8, ?9, ?10, ?11, ?12, ?13, ?14, ?15, ?16)");

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

                                statement2.executeAsync(
                                {
                                    handleResult: function(aResultSet) {},
                                    handleError: cf.handleError,
                                    handleCompletion: function(aReason)
                                    {
                                        if (aReason == cf.Bandwagon.STMT_OK)
                                        {
                                            for (var id in addon.compatibleApplications)
                                            {
                                                cf._commitAddonCompatibleApplication(addonStorageID, addon.compatibleApplications[id]);
                                            }

                                            for (var id in addon.compatibleOS)
                                            {
                                                cf._commitAddonCompatibleOS(addonStorageID, addon.compatibleOS[id]);
                                            }

                                            for (var id in addon.installs)
                                            {
                                                cf._commitAddonInstall(addonStorageID, addon.installs[id]);
                                            }

                                            for (var i=0; i<addon.comments.length; i++)
                                            {
                                                cf._commitAddonComment(addonStorageID, addon.comments[i]);
                                            }

                                            for (var id in addon.authors)
                                            {
                                                cf._commitAddonAuthor(addonStorageID, addon.authors[id]);
                                            }

                                            cf._commitCollectionsAddonsTuple(addonStorageID, collection, addon);
                                        }
                                    }
                                });
                            }
                        }
                    });
                }
            }
        }
    });
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
    var cf = this;

    if (!this.connection)
        return;

    var statement1 = this.connection.createStatement("DELETE FROM collections where id = ?1");
    statement1.bindInt32Parameter(0, collection.storageID);

    var statement2 = this.connection.createStatement("DELETE FROM collectionsAddons where collection = ?1");
    statement2.bindInt32Parameter(0, collection.storageID);
 
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

Bandwagon.Factory.CollectionFactory.prototype._openCollectionFromRS = function(row)
{
    var collection = new this.Bandwagon.Model.Collection(this.Bandwagon);
    collection.storageID = row.getResultByName("id");
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

Bandwagon.Factory.CollectionFactory.prototype._openAddons = function(collections, lastCallback)
{
    var cf = this;

    if (!this.connection)
        return null;

    var statement = this.connection.createStatement(
        "SELECT addons.*, collectionsAddons.id AS collectionsAddonsStorageID, collectionsAddons.read, collections.url AS collectionResourceURL"
        + " FROM addons LEFT JOIN collectionsAddons ON addons.id = collectionsAddons.addon"
        + " LEFT JOIN collections ON collectionsAddons.collection = collections.id"
        + " ORDER BY collections.id");

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

Bandwagon.Factory.CollectionFactory.prototype._openAddonFromRS = function(row)
{
    var addon = new this.Bandwagon.Model.Addon(Bandwagon);
    addon.Bandwagon = this.Bandwagon;

    addon.storageID = row.getResultByName("id");
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
    addon.collectionsAddonsStorageID = row.getResultByName("collectionsAddonsStorageID");
    addon.read = (row.getResultByName("read")==1?true:false);

    return addon;
}

Bandwagon.Factory.CollectionFactory.prototype._openCollectionsLinks = function(collections, lastCallback)
{
    var cf = this;

    if (!this.connection)
        return null;

    var statement = this.connection.createStatement(
        "SELECT collectionsLinks.*, collections.url AS collectionResourceURL"
        + " FROM collectionsLinks LEFT JOIN collections ON collectionsLinks.collection = collections.id"
        + " ORDER BY collections.id");

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

Bandwagon.Factory.CollectionFactory.prototype._openAddonCompatibleApplications = function(collections, lastCallback)
{
    var cf = this;

    if (!this.connection)
        return null;

    var statement = this.connection.createStatement(
        "SELECT addonCompatibleApplications.*, addons.guid AS addonGUID, collections.url AS collectionResourceURL"
        + " FROM addonCompatibleApplications LEFT JOIN addons ON addonCompatibleApplications.addon = addons.id"
        + " LEFT JOIN collectionsAddons ON addons.id = collectionsAddons.addon"
        + " LEFT JOIN collections ON collectionsAddons.collection = collections.id"
        + " ORDER BY collections.id");

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

Bandwagon.Factory.CollectionFactory.prototype._openAddonCompatibleOS = function(collections, lastCallback)
{
    var cf = this;

    if (!this.connection)
        return null;

    var statement = this.connection.createStatement(
        "SELECT addonCompatibleOS.*, addons.guid AS addonGUID, collections.url AS collectionResourceURL"
        + " FROM addonCompatibleOS LEFT JOIN addons ON addonCompatibleOS.addon = addons.id"
        + " LEFT JOIN collectionsAddons ON addons.id = collectionsAddons.addon"
        + " LEFT JOIN collections ON collectionsAddons.collection = collections.id"
        + " ORDER BY collections.id");

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

Bandwagon.Factory.CollectionFactory.prototype._openAddonInstalls = function(collections, lastCallback)
{
    var cf = this;

    if (!this.connection)
        return null;

    var statement = this.connection.createStatement(
        "SELECT addonInstalls.*, addons.guid AS addonGUID, collections.url AS collectionResourceURL"
        + " FROM addonInstalls LEFT JOIN addons ON addonInstalls.addon = addons.id"
        + " LEFT JOIN collectionsAddons ON addons.id = collectionsAddons.addon"
        + " LEFT JOIN collections ON collectionsAddons.collection = collections.id"
        + " ORDER BY collections.id");

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

Bandwagon.Factory.CollectionFactory.prototype._openAddonComments = function(collections, lastCallback)
{
    var cf = this;

    if (!this.connection)
        return null;

    var statement = this.connection.createStatement(
        "SELECT addonComments.*, addons.guid AS addonGUID, collections.url AS collectionResourceURL"
        + " FROM addonComments LEFT JOIN addons ON addonComments.addon = addons.id"
        + " LEFT JOIN collectionsAddons ON addons.id = collectionsAddons.addon"
        + " LEFT JOIN collections ON collectionsAddons.collection = collections.id"
        + " ORDER BY collections.id");

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

Bandwagon.Factory.CollectionFactory.prototype._openAddonAuthors = function(collections, lastCallback)
{
    var cf = this;

    if (!this.connection)
        return null;

    var statement = this.connection.createStatement(
        "SELECT addonAuthors.*, addons.guid AS addonGUID, collections.url AS collectionResourceURL"
        + " FROM addonAuthors LEFT JOIN addons ON addonAuthors.addon = addons.id"
        + " LEFT JOIN collectionsAddons ON addons.id = collectionsAddons.addon"
        + " LEFT JOIN collections ON collectionsAddons.collection = collections.id"
        + " ORDER BY collections.id");

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

                collections[collectionResourceURL].addons[addonGUID].authors.push(author);
            }
        },
        handleError: cf.handleError,
        handleCompletion: function(aReason)
        {
            lastCallback(aReason, collections);
        }
    });
}

Bandwagon.Factory.CollectionFactory.prototype._commitAddonCompatibleApplication = function(addonStorageID, application)
{
    var deleteStatement = this.connection.createStatement("DELETE FROM addonCompatibleApplications WHERE addon = ?1 AND applicationId = ?2");
    deleteStatement.bindInt32Parameter(0, addonStorageID);
    deleteStatement.bindInt32Parameter(1, application.applicationId);

    var insertStatement = this.connection.createStatement("INSERT INTO addonCompatibleApplications VALUES (?1, ?2, ?3, ?4, ?5, ?6)");
    insertStatement.bindInt32Parameter(0, addonStorageID);
    insertStatement.bindUTF8StringParameter(1, application.name);
    insertStatement.bindInt32Parameter(2, application.applicationId);
    insertStatement.bindUTF8StringParameter(3, application.minVersion);
    insertStatement.bindUTF8StringParameter(4, application.maxVersion);
    insertStatement.bindUTF8StringParameter(5, application.guid);

    this.connection.executeAsync([deleteStatement, insertStatement], 2);
}

Bandwagon.Factory.CollectionFactory.prototype._commitAddonCompatibleOS = function(addonStorageID, os)
{
    var deleteStatement = this.connection.createStatement("DELETE FROM addonCompatibleOS WHERE addon = ?1");
    deleteStatement.bindInt32Parameter(0, addonStorageID);

    var insertStatement = this.connection.createStatement("INSERT INTO addonCompatibleOS VALUES (?1, ?2)");
    insertStatement.bindInt32Parameter(0, addonStorageID);
    insertStatement.bindUTF8StringParameter(1, os);

    this.connection.executeAsync([deleteStatement, insertStatement], 2);
}

Bandwagon.Factory.CollectionFactory.prototype._commitAddonInstall = function(addonStorageID, install)
{
    var deleteStatement = this.connection.createStatement("DELETE FROM addonInstalls WHERE addon = ?1");
    deleteStatement.bindInt32Parameter(0, addonStorageID);

    var insertStatement = this.connection.createStatement("INSERT INTO addonInstalls VALUES (?1, ?2, ?3, ?4)");
    insertStatement.bindInt32Parameter(0, addonStorageID);
    insertStatement.bindUTF8StringParameter(1, install.url);
    insertStatement.bindUTF8StringParameter(2, install.hash);
    insertStatement.bindUTF8StringParameter(3, install.os);

    this.connection.executeAsync([deleteStatement, insertStatement], 2);
}

Bandwagon.Factory.CollectionFactory.prototype._commitAddonComment = function(addonStorageID, comment)
{
    var deleteStatement = this.connection.createStatement("DELETE FROM addonComments WHERE addon = ?1");
    deleteStatement.bindInt32Parameter(0, addonStorageID);

    var insertStatement = this.connection.createStatement("INSERT INTO addonComments VALUES (?1, ?2, ?3)");
    insertStatement.bindInt32Parameter(0, addonStorageID);
    insertStatement.bindUTF8StringParameter(1, comment.comment);
    insertStatement.bindUTF8StringParameter(2, comment.author);

    this.connection.executeAsync([deleteStatement, insertStatement], 2);
}

Bandwagon.Factory.CollectionFactory.prototype._commitAddonAuthor = function(addonStorageID, author)
{
    var deleteStatement = this.connection.createStatement("DELETE FROM addonAuthors WHERE addon = ?1");
    deleteStatement.bindInt32Parameter(0, addonStorageID);

    var insertStatement = this.connection.createStatement("INSERT INTO addonAuthors VALUES (?1, ?2)");
    insertStatement.bindInt32Parameter(0, addonStorageID);
    insertStatement.bindUTF8StringParameter(1, author);

    this.connection.executeAsync([deleteStatement, insertStatement], 2);
}

Bandwagon.Factory.CollectionFactory.prototype._commitCollectionsAddonsTuple = function(addonStorageID, collection, addon)
{
    var cf = this;

    var replaceStatement = this.connection.createStatement("REPLACE INTO collectionsAddons VALUES (?1, ?2, ?3, ?4)");

    if (addon.collectionsAddonsStorageID == -1)
    {
        replaceStatement.bindNullParameter(0);
    }
    else
    {
        replaceStatement.bindInt32Parameter(0, addon.collectionsAddonsStorageID);
    }

    replaceStatement.bindInt32Parameter(1, collection.storageID);
    replaceStatement.bindInt32Parameter(2, addonStorageID);
    replaceStatement.bindInt32Parameter(3, (addon.read?1:0));

    replaceStatement.executeAsync(
    {
        handleResult: function(aResultSet) {},
        handleError: cf.handleError,
        handleCompletion: function(aReason)
        {
            if (aReason == cf.Bandwagon.STMT_OK)
            {
                if (addon.collectionsAddonsStorageID == -1)
                {
                    addon.collectionsAddonsStorageID = cf.connection.lastInsertRowID;
                }
            }
        }
    });
}

