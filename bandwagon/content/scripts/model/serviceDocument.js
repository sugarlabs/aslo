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

Bandwagon.Model.ServiceDocument = function()
{
    this.Bandwagon = Bandwagon;

    this.emailResourceURL = "";
    this.collectionListResourceURL = "";

    this.collections = [];
}

Bandwagon.Model.ServiceDocument.prototype.unserialize = function(xsharing)
{
    var baseURL = xsharing.@xmlbase.toString();

    // resource urls
    
    this.emailResourceURL = baseURL + "/" + xsharing.email.attribute("href").toString();
    this.collectionListResourceURL = baseURL + "/" + xsharing.collections.attribute("href").toString();

    // collections

    for each (var xcollection in xsharing.collections.collection)
    {
        var collection = new this.Bandwagon.Model.Collection();
        collection.Bandwagon = this.Bandwagon;

        collection.resourceURL = baseURL + "/" + xcollection.attribute("href").toString();

        collection.name = xcollection.attribute("name").toString();
        collection.description = xcollection.attribute("description").toString();
        collection.creator = xcollection.attribute("creator").toString();
        collection.listed = (xcollection.attribute("listed").toString()=="yes"?true:false);
        collection.writable = (xcollection.attribute("writable").toString()=="yes"?true:false);
        collection.subscribed = (xcollection.attribute("subscribed").toString()=="yes"?true:false);
        collection.lastModified = this.Bandwagon.Util.ISO8601toDate(xcollection.attribute("lastmodified").toString());
        collection.type = xcollection.attribute("type").toString();
        collection.iconURL = xcollection.attribute("icon").toString();

        collection.addonsResourceURL = baseURL + "/" + xcollection.addons.attribute("href").toString();

        this.collections.push(collection);
    }
}
