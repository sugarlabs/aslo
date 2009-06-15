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

Bandwagon.Model.Addon = function()
{
    try
    {
        this.Bandwagon = Bandwagon;
    }
    catch (e) {}

    this.TYPE_EXTENSION = 1;
    this.STATUS_PUBLIC = 4;

    this.storageID = -1;
    this.collectionsAddonsStorageID = -1;

    this.name = "";
    this.type = -1;
    this.guid = "";
    this.version = "";
    this.status = -1;
    this.summary = "";
    this.description = "";
    this.icon = "";
    this.eula = "";
    this.thumbnail = "";
    this.rating = -1;
    this.learnmore = "";

    this.compatibleApplications = {};
    this.compatibleOS = {};
    this.installs = {};

    this.authors = {};
    this.categories = {};
    this.dateAdded = new Date();
    this.comments = [];

    this.read = false; // has the user seen this add-on in the collections pane
    this.notified = false; // have we notified the user that we have a new addon

    // *** temp comment
    //this.comments.push({comment: "Hey guys - you need to check out this cool new add-on! Two days in and I'm addicted!", author: "Bob"});

}

Bandwagon.Model.Addon.INSTALL_YES = 1;
Bandwagon.Model.Addon.INSTALL_NO_ADDON_IS_FOR_OLDER_VERSION = 2;
Bandwagon.Model.Addon.INSTALL_NO_UPGRADE_TO_USE_THIS_VERSION = 3;
Bandwagon.Model.Addon.INSTALL_NO_MUST_DOWNLOAD_BETA = 4;
Bandwagon.Model.Addon.INSTALL_NO_NOT_COMPATIBLE_OS = 5;
Bandwagon.Model.Addon.INSTALL_YES_IS_EXPERIMENTAL = 6;
Bandwagon.Model.Addon.INSTALL_NO_ALREADY_INSTALLED = 7;

Bandwagon.Model.Addon.STATUS_PUBLIC = 4;
Bandwagon.Model.Addon.STATUS_SANDBOX = 1;

Bandwagon.Model.Addon.prototype.canInstall = function(env)
{
    // check is the extension already installed
    if (Bandwagon.Util.isExtensionInstalled(this.guid))
    {
        var details =
        {
            type: Bandwagon.Model.Addon.INSTALL_NO_ALREADY_INSTALLED,
            requiredVersion: ""
        };

        return details;
    }

    // check is the extension compatible with this os

    if (!this.getInstaller(env.os))
    {
        var details =
        {
            type: Bandwagon.Model.Addon.INSTALL_NO_NOT_COMPATIBLE_OS,
            requiredVersion: env.os
        };

        return details;
    }

    // check is this extension compatible with firefox, etc.

    var application = this.compatibleApplications[env.appName.toUpperCase()];

    if (!application)
    {
        // this isn't the right error, but is the closest we have

        var details =
        {
            type: Bandwagon.Model.Addon.INSTALL_NO_NOT_COMPATIBLE_OS,
            requiredVersion: env.appName
        };

        return details;
    }

    // check the version is compatible

    if (this.Bandwagon.Util.compareVersions(env.appVersion, application.minVersion) < 0)
    {
        // this version of firefox is less than the min version of this addon

        var details;

        // FIXME I don't think this the correct way to test for the "upgrade to beta"

        if (application.minVersion.match(/pre/))
        {
            details =
            {
                type: Bandwagon.Model.Addon.INSTALL_NO_MUST_DOWNLOAD_BETA,
                requiredVersion: application.minVersion
            };
        }
        else
        {
            details =
            {
                type: Bandwagon.Model.Addon.INSTALL_NO_UPGRADE_TO_USE_THIS_VERSION,
                requiredVersion: application.minVersion
            };
        }

        return details;
    }

    if (this.Bandwagon.Util.compareVersions(env.appVersion, application.maxVersion) > 0)
    {
        // the version of firefox is higher than the max version of this addon

        var details =
        {
            type: Bandwagon.Model.Addon.INSTALL_NO_ADDON_IS_FOR_OLDER_VERSION,
            requiredVersion: application.maxVersion
        };

        return details;
    }

    // check is the extension experimental (user can still install, but we'll show a warning)

    if (this.status == Bandwagon.Model.Addon.STATUS_SANDBOX)
    {
        var details =
        {
            type: Bandwagon.Model.Addon.INSTALL_YES_IS_EXPERIMENTAL,
            requiredVersion: ""
        };

        return details;
    }
   
    // if we get this far, then the add-on is compatible and non-experimental

    var details = 
    {
        type: Bandwagon.Model.Addon.INSTALL_YES,
        requiredVersion: ""
    };
    
    return details;
}

Bandwagon.Model.Addon.prototype.getInstaller = function(os)
{
    var install;
    var addon = this;

    os = os.toUpperCase();

    if (this.installs['ALL'])
    {
        install = this.installs['ALL']
    }
    else if (this.installs[os])
    {
        install = this.installs[os];
    }

    if (!install)
        return null;

    var installer = 
    {
        URL: install.url,
        Hash: install.hash,
        IconURL: addon.icon,
        toString: function () { return this.URL; }
    };

    return installer;
}

Bandwagon.Model.Addon.prototype.toString = function()
{
    return this.name + " (" + this.guid + ")";
}

Bandwagon.Model.Addon.prototype.equals = function(other)
{
    if (other == null)
        return false;

    return (this.guid == other.guid);
}

Bandwagon.Model.Addon.prototype.unserialize = function(xaddon)
{
    this.name = xaddon.name.text().toString();
    this.type = xaddon.type.attribute("id").toString();
    this.guid = xaddon.guid.text().toString();
    this.status = xaddon.status.attribute("id").toString();
    this.summary = xaddon.summary.text().toString();
    this.description = xaddon.description.text().toString();
    this.icon = xaddon.icon.text().toString();
    this.eula = xaddon.eula.text().toString();
    this.thumbnail = xaddon.thumbnail.text().toString();
    this.rating = xaddon.rating.text().toString();
    this.learnmore = xaddon.learnmore.text().toString();

    this.version = xaddon.version.text().toString(); // TODO parse version here?

    for each (var xinstall in xaddon.install)
    {
        var install =
        {
            url: xinstall.text().toString(),
            hash: xinstall.attribute("hash").toString(),
            os: xinstall.attribute("os").toString().toUpperCase()
        };
        
        this.installs[install.os] = install;
    }

    for each (var xos in xaddon.all_compatible_os.os)
    {
        var os = xos.text().toString().toUpperCase();
        this.compatibleOS[os] = os;
    }

    for each (var xapplication in xaddon.compatible_applications.application)
    {
        var application =
        {
            name: xapplication.name.text().toString(),
            applicationId: xapplication.application_id.text().toString(),
            minVersion: xapplication.min_version.text().toString(),
            maxVersion: xapplication.max_version.text().toString(),
            guid: xapplication.appID.text().toString()
        };

        this.compatibleApplications[application.name.toUpperCase()] = application;
    }

    for each (var xauthor in xaddon.authors.author)
    {
        this.authors[xauthor.text().toString()] = xauthor.text().toString();
    }

    for each (var xcategory in xaddon.categories.category)
    {
        this.categories[xcategory.text().toString()] = xcategory.text().toString();
    }

    this.dateAdded = this.Bandwagon.Util.ISO8601toDate(xaddon.meta.added.text().toString());

    this.comments = [];

    var comment0Comment = xaddon.meta.comments.text().toString();
    var comment0Author = xaddon.meta.addedby.text().toString();

    if (!comment0Author.match(/\w/))
        comment0Author = "Unknown";

    //if (comment0Comment.match(/\w/))
    this.comments.push({comment: comment0Comment, author: comment0Author});

}

