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

Bandwagon.Model.Collection = function()
{
    this.Bandwagon = Bandwagon;

    this.storageID = -1;
    this.resourceURL = "";
    this.addonsResourceURL = "";

    this.name = "";
    this.description = "";
    this.creator = "";
    this.listed = false;
    this.writable = false;
    this.subscribed = false;
    this.lastModified = new Date();

    this.password = null;
    this.dateAdded = new Date();
    this.dateLastCheck = null;
    this.active = true;

    this.updateInterval = -1; // default is to use global setting
    this.showNotifications = -1; // default is to use global setting
    this.addonsPerPage = -1; // default is to use global setting

    this.autoPublishExtensions = true;
    this.autoPublishThemes = true;
    this.autoPublishDicts = true;
    this.autoPublishLangPacks = true;
    this.autoPublishDisabled = false;

    this.status = this.STATUS_NEW;
    this.type = this.TYPE_NORMAL;

    this.addons = {};
    this.links = {};
}

Bandwagon.Model.Collection.prototype.STATUS_NEW = 0;
Bandwagon.Model.Collection.prototype.STATUS_LOADING = 1;
Bandwagon.Model.Collection.prototype.STATUS_LOADERROR = 2;
Bandwagon.Model.Collection.prototype.STATUS_LOADED = 3;

Bandwagon.Model.Collection.prototype.TYPE_NORMAL = "normal";
Bandwagon.Model.Collection.prototype.TYPE_AUTOPUBLISHER = "autopublisher";
Bandwagon.Model.Collection.prototype.TYPE_OTHER = "other";

Bandwagon.Model.Collection.prototype.getUnreadAddons = function()
{
    var unreadAddons = [];

    for (var id in this.addons)
    {
        if (!this.addons[id].read)
        {
            unreadAddons.push(this.addons[id]);
        }
    }

    return unreadAddons;
}

Bandwagon.Model.Collection.prototype.setAllRead = function()
{
    for (var id in this.addons)
    {
        this.addons[id].read = true;
    }
}

Bandwagon.Model.Collection.prototype.getUnnotifiedAddons = function()
{
    var unnotifiedAddons = [];

    for (var id in this.addons)
    {
        if (!this.addons[id].notified)
        {
            unnotifiedAddons.push(this.addons[id]);
        }
    }

    return unnotifiedAddons;
}

Bandwagon.Model.Collection.prototype.setAllNotified = function()
{
    for (var id in this.addons)
    {
        this.addons[id].notified = true;
    }
}

Bandwagon.Model.Collection.prototype.getSortedAddons = function()
{
    var sortedAddons = [];

    for (var id in this.addons)
    {
        sortedAddons.push(this.addons[id]);
    }

    sortedAddons.sort(function(a, b)
    {
        // sorting is unread, then dateadded

        if (a.read == false && b.read == true ) return -1;
        if (a.read == true && b.read == false ) return 1;

        return (a.dateAdded.getTime() < b.dateAdded.getTime()?1:-1);
    });

    return sortedAddons;
}

Bandwagon.Model.Collection.prototype.hasAddon = function()
{
    for (var id in this.addons)
    {
        if (this.addons[id] && this.addons[id].guid)
        {
            return true;
        }
    }

    return false;
}

Bandwagon.Model.Collection.prototype.getNicknameFromName = function()
{
    return this.name.replace(/\W/g, "_");
}

Bandwagon.Model.Collection.prototype.isLocalAutoPublisher = function()
{
    if (this.type != this.TYPE_AUTOPUBLISHER)
        return false;

    if (this.name == "")
        return false;

    return (this.Bandwagon.Preferences.getPreference("local.autopublisher") == this.resourceURL);
}

Bandwagon.Model.Collection.prototype.toString = function()
{
    return this.name + " (" + this.resourceURL + ")";
}

Bandwagon.Model.Collection.prototype.equals = function(other)
{
    if (other == null)
        return false;

    return (this.resourceURL == other.resourceURL);
}

Bandwagon.Model.Collection.prototype.unserialize = function(xcollection)
{
    var baseURL = xcollection.@xmlbase.toString();

    //this.resourceURL = baseURL + "/" + xcollection.attribute("href").toString();

    this.name = xcollection.attribute("name").toString();
    this.description = xcollection.attribute("description").toString();
    this.creator = xcollection.attribute("creator").toString();
    this.listed = (xcollection.attribute("listed").toString()=="yes"?true:false);
    this.writable = (xcollection.attribute("writable").toString()=="yes"?true:false);
    this.subscribed = (xcollection.attribute("subscribed").toString()=="yes"?true:false);
    this.lastModified = this.Bandwagon.Util.ISO8601toDate(xcollection.attribute("lastmodified").toString());
    this.type = xcollection.attribute("type").toString();

    //this.addonsResourceURL = baseURL + "/" + xcollection.addons.attribute("href").toString();

    for each (var xaddon in xcollection.addons.addon)
    {
        var addon = new this.Bandwagon.Model.Addon();
        addon.Bandwagon = this.Bandwagon;

        addon.unserialize(xaddon);

        if (addon.guid && addon.guid != "" && addon.name && addon.name != "")
        {
            if (this.addons[addon.guid])
            {
                // "merge" with existing item
                this.addons[addon.guid].unserialize(xaddon);
            }
            else
            {
                this.addons[addon.guid] = addon;
            }

            this.addons[addon.guid].seen = true;
        }
    }

    for (var id in this.addons)
    {
        if (this.addons[id].seen != true)
        {
            delete this.addons[id];
            continue;
        }

        this.addons[id].seen = false;
    }

    var linkBaseURL = xcollection.links.@xmlbase.toString();

    for each (var xlink in xcollection.links.link)
    {
        var rel = xlink.attribute("id").toString();
        var href = xlink.attribute("href").toString();

        this.links[rel] = linkBaseURL + href;
    }
}

