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

Bandwagon.Util = new function() {}

Bandwagon.Util._extensionManager = null;
Bandwagon.Util._rdfService = null;
Bandwagon.Util._extensionsDataSource = null;
Bandwagon.Util.Cc = Components.classes;
Bandwagon.Util.Ci = Components.interfaces;

Bandwagon.Util.getMainWindow = function()
{
    return window.QueryInterface(this.Ci.nsIInterfaceRequestor)
        .getInterface(this.Ci.nsIWebNavigation)
        .QueryInterface(this.Ci.nsIDocShellTreeItem)
        .rootTreeItem
        .QueryInterface(this.Ci.nsIInterfaceRequestor)
        .getInterface(this.Ci.nsIDOMWindow);
}

Bandwagon.Util.getMostRecentAppWindow = function()
{
    var app = Bandwagon.Util.getAppName();
    var appWinString = "navigator:browser"; // default, Firefox
    if (app == "Thunderbird")
        appWinString = "mail:3pane";
    var appWindow = Components.classes["@mozilla.org/appshell/window-mediator;1"]
                .getService(Components.interfaces.nsIWindowMediator)
                .getMostRecentWindow(appWinString);
    return appWindow;
}

Bandwagon.Util.getExtensionVersion = function(emid)
{
    var bits = {major: 1, minor: 0, revision: 0, increment: 0};

    var em = this.Cc["@mozilla.org/extensions/manager;1"]
        .getService(this.Ci.nsIExtensionManager);

    var addon = em.getItemForID(emid);

    var versionString = addon.version;

    return this.splitVersionString(versionString);
}

Bandwagon.Util.splitVersionString = function(versionString)
{
    var versionBits = versionString.split('.');

    if (versionBits[0])
        bits.major = parseInt(versionBits[0]);

    if (versionBits[1])
        bits.minor = parseInt(versionBits[1]);

    if (versionBits[2])
        bits.revision = parseInt(versionBits[2]);

    if (versionBits[3])
        bits.increment = parseInt(versionBits[3]);

    return versionBits;
}

Bandwagon.Util.getHostEnvironmentInfo = function()
{
    // Returns "WINNT" on Windows Vista, XP, 2000, and NT systems;
    // "Linux" on GNU/Linux; and "Darwin" on Mac OS X.
    var osString = this.Cc["@mozilla.org/xre/app-info;1"]
        .getService(this.Ci.nsIXULRuntime).OS;

    var info = this.Cc["@mozilla.org/xre/app-info;1"]
        .getService(this.Ci.nsIXULAppInfo);
    // Get the name of the application running us
    info.name; // Returns "Firefox" for Firefox
    info.version; // Returns "2.0.0.1" for Firefox version 2.0.0.1

    var hostEnvInfo =
    {
        os: osString,
        appName: info.name,
        appVersion: info.version
    }

    return hostEnvInfo;
}

Bandwagon.Util.getAppName = function()
{
    var envinfo = Bandwagon.Util.getHostEnvironmentInfo();
    return envinfo.appName; // Returns "Firefox" for Firefox, "Thunderbird" for TB
}

Bandwagon.Util.isTB2 = function()
{
    var hostEnvInfo = Bandwagon.Util.getHostEnvironmentInfo();

    return (hostEnvInfo.appName == "Thunderbird" && hostEnvInfo.appVersion.charAt(0) == "2");
}

Bandwagon.Util.getAnAppWindow = function()
{
    var appWinString = (Bandwagon.Util.getAppName() == "Thunderbird" ? "mail:3pane" : "navigator:browser");

    return this.Cc["@mozilla.org/appshell/window-mediator;1"].getService(this.Ci.nsIWindowMediator).getMostRecentWindow(appWinString);
}

Bandwagon.Util.compareVersions = function(versionA, versionB)
{
    var versionChecker = this.Cc["@mozilla.org/xpcom/version-comparator;1"]
        .getService(this.Ci.nsIVersionComparator);

    var result = versionChecker.compare(versionA, versionB);

    //Bandwagon.Logger.debug("compared version '" + versionA + "' with version '" + versionB + "' and got: " + result);

    return result;
}

Bandwagon.Util.dumpObject = function(obj, name, indent, depth)
{
    Bandwagon.Logger.debug(Bandwagon.Util._dumpObject(obj, name, indent, depth));
}
Bandwagon.Util._dumpObject = function(obj, name, indent, depth)
{
    if (!name) name = "object";
    if (!indent) indent = " ";

    if (depth > 10)
    {
        return indent + name + ": <Maximum Depth Reached>\n";
    }

    if (typeof obj == "object")
    {
        var child = null;
        var output = indent + name + "\n";
        indent += "\t";

        for (var item in obj)
        {
            try
            {
                child = obj[item];
            }
            catch (e)
            {
                child = "<Unable to Evaluate>";
            }

            if (typeof child == "object")
            {
                output += Bandwagon.Util._dumpObject(child, item, indent, depth + 1);
            }
            else
            {
                output += indent + item + ": " + child + "\n";
            }
        }
        return output;
    }
    else
    {
        return obj;
    }
}

Bandwagon.Util.lengthOfHash = function(hash)
{
    var length = 0;

    for (var object in hash)
    {
        length++;
    }

    return length;
}

Bandwagon.Util.INTERVAL_MINUTES = 1;
Bandwagon.Util.INTERVAL_HOURS = 2;
Bandwagon.Util.INTERVAL_DAYS = 3;

Bandwagon.Util.intervalMillisecondsToUnits = function(ms)
{
    if (ms % (1000*60*60*24) == 0)
    {
        return {units: this.INTERVAL_DAYS, interval: ms/(1000*60*60*24)};
    }
    else if (ms % (1000*60*60) == 0)
    {
        return {units: this.INTERVAL_HOURS, interval: ms/(1000*60*60)};
    }
    else
    {
        return {units: this.INTERVAL_MINUTES, interval: ms/(1000*60)};
    }
}

Bandwagon.Util.intervalUnitsToMilliseconds = function(interval, units)
{
    var ms = 0;

    switch (units)
    {
        case this.INTERVAL_MINUTES:
            ms = 1000 * 60 * interval;
            break;
        case this.INTERVAL_HOURS:
            ms = 1000 * 60 * 60 * interval;
            break;
        case this.INTERVAL_DAYS:
            ms = 1000 * 60 * 60 * 24 * interval;
            break;
    }

    //Bandwagon.Logger.debug("converted interval '" + interval + " of " + units + "' into " + ms + "ms");

    return ms;
}

Bandwagon.Util.getCookie = function(host, name)
{
    var cookieManager = this.Cc["@mozilla.org/cookiemanager;1"]
        .getService(this.Ci.nsICookieManager);

    var iter = cookieManager.enumerator;

    while (iter.hasMoreElements())
    {
        var cookie = iter.getNext();

        if (cookie instanceof this.Ci.nsICookie)
        {
            if (cookie.host == host && cookie.name == name)
                return cookie.value;
        }
    }

    return null;
}

Bandwagon.Util._initExtensionServices = function()
{
    if (this._extensionManager == null)
    {
        this._extensionManager = this.Cc["@mozilla.org/extensions/manager;1"]
            .getService(this.Ci.nsIExtensionManager);
    }

    if (this._rdfService == null)
    {
        this._rdfService = this.Cc["@mozilla.org/rdf/rdf-service;1"]
            .getService(this.Ci.nsIRDFService);
    }

    var getURLSpecFromFile = function(file)
    {
        var ioServ = Bandwagon.Util.Cc["@mozilla.org/network/io-service;1"]
            .getService(Bandwagon.Util.Ci.nsIIOService);
        var fph = ioServ.getProtocolHandler("file")
            .QueryInterface(Bandwagon.Util.Ci.nsIFileProtocolHandler);
        return fph.getURLSpecFromFile(file);
    }

    var getDir = function(key, pathArray)
    {
        var fileLocator = Bandwagon.Util.Cc["@mozilla.org/file/directory_service;1"]
            .getService(Bandwagon.Util.Ci.nsIProperties);
        var dir = fileLocator.get(key, Bandwagon.Util.Ci.nsILocalFile);
        for (var i=0; i<pathArray.length; ++i)
        {
            dir.append(pathArray[i]);
        }
        dir.followLinks = false;
        return dir;
    }

    var getFile = function(key, pathArray)
    {
        var file = getDir(key, pathArray.slice(0, -1));
        file.append(pathArray[pathArray.length - 1]);
        return file;
    }

    if (this._extensionsDataSource == null)
    {
        this._extensionsDataSource = this._rdfService.GetDataSourceBlocking(getURLSpecFromFile(getFile("ProfD", ["extensions.rdf"])));
    }
}

Bandwagon.Util.getInstalledExtensions = function()
{
    this._initExtensionServices();

    var items = this._extensionManager.getItemList(this.Ci.nsIUpdateItem.TYPE_ANY, { });

    return items;
}

Bandwagon.Util.isExtensionInstalled = function(guid)
{
    var installedExtensions = this.getInstalledExtensions();

    for (var i=0; i<installedExtensions.length; i++)
    {
        if (installedExtensions[i].id == guid)
        {
            return true;
        }
    }

    return false;
}

Bandwagon.Util.getExtensionProperty = function(id, propertyName)
{
    this._initExtensionServices();

    var value;

    try
    {
        var target = this._extensionsDataSource.GetTarget
        (
            this._rdfService.GetResource("urn:mozilla:item:" + id),
            this._rdfService.GetResource("http://www.mozilla.org/2004/em-rdf#" + propertyName),
            true
        );

        var stringData = function(literalOrResource)
        {
            if (literalOrResource instanceof this.Ci.nsIRDFLiteral)
                return literalOrResource.Value;
            if (literalOrResource instanceof this.Ci.nsIRDFResource)
                return literalOrResource.Value;
            return undefined;
        }

        value = stringData(target);
    }
    catch (e)
    {
        //Bandwagon.Logger.error("Error getting extension property: " + e);
        return "";
    }

    //Bandwagon.Logger.debug("Extension '" + id + "' has property '" + propertyName + "=" + value + "'");

    return value === undefined ? "" : value;
}

Bandwagon.Util.ISO8601toDate = function(dString)
{
    var x = new Date();

    var regexp = /(\d\d\d\d)(-)?(\d\d)(-)?(\d\d)(T)?(\d\d)(:)?(\d\d)(:)?(\d\d)(\.\d+)?(Z|([+-])(\d\d)(:)?(\d\d))/;

    if (dString.toString().match(new RegExp(regexp))) {
        var d = dString.match(new RegExp(regexp));
        var offset = 0;

        x.setUTCDate(1);
        x.setUTCFullYear(parseInt(d[1],10));
        x.setUTCMonth(parseInt(d[3],10) - 1);
        x.setUTCDate(parseInt(d[5],10));
        x.setUTCHours(parseInt(d[7],10));
        x.setUTCMinutes(parseInt(d[9],10));
        x.setUTCSeconds(parseInt(d[11],10));
        if (d[12])
            x.setUTCMilliseconds(parseFloat(d[12]) * 1000);
            else
                x.setUTCMilliseconds(0);
                if (d[13] != 'Z') {
                    offset = (d[15] * 60) + parseInt(d[17],10);
                    offset *= ((d[14] == '-') ? -1 : 1);
                    x.setTime(x.getTime() - offset * 60 * 1000);
                }
    }
    else
    {
        x.setTime(Date.parse(dString));
    }

    return x;
};

Bandwagon.Util.getBrowserLocale = function()
{
    var gmyextensionBundle = this.Cc["@mozilla.org/intl/stringbundle;1"].getService(this.Ci.nsIStringBundleService);
    var bundle = gmyextensionBundle.createBundle("chrome://global/locale/intl.properties");

    return bundle.GetStringFromName("general.useragent.locale");
};

Bandwagon.Util.encodeURL = function(str)
{
    str = encodeURIComponent(str);

    //str = str.replace(/'/g, "%27");

    return str;
}

Bandwagon.Util.getUserFacingOSName = function(str)
{
    if (str.match(/.*Darwin.*/i))
    {
        return "Mac OS X";
    }
    else if (str.match(/.*Linux.*/i))
    {
        return "Linux";
    }
    else if (str.match(/.*BSD.*/i))
    {
        return "BSD";
    }
    else if (str.match(/.*sunos.*/i))
    {
        return "Solaris";
    }
    else if (str.match(/.*solaris.*/i))
    {
        return "Solaris";
    }
    else if (str.match(/.*Win.*/i))
    {
        return "Windows";
    }
    else
    {
        return str;
    }
}

