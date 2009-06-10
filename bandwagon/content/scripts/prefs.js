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

Bandwagon.Preferences = new function() {}

Bandwagon.Preferences._prefServiceRoot = "extensions.bandwagon.";
Bandwagon.Preferences._prefServiceListDelimiter = "|";
Bandwagon.Preferences._prefServiceCache = null;

Bandwagon.Preferences.setPreference = function(name, value)
{
    try
    {
        if (typeof value == 'boolean')
        {
            this._getPrefService().setBoolPref(name, value);
        }
        else if (typeof value == 'number')
        {
            this._getPrefService().setIntPref(name, value);
        }
        else if (typeof value == 'string')
        {
            this._getPrefService().setCharPref(name, value);
        }
        else
        {
            this._getPrefService().setCharPref(name, value.toString());
        }
    }
    catch (e)
    {
        Bandwagon.Logger.error(e);
    }
}

Bandwagon.Preferences.getPreference = function(name)
{
    var val = null;

    try
    {
        var type = this._getPrefService().getPrefType(name);

        if (this._getPrefService().PREF_BOOL == type)
        {
            val = this._getPrefService().getBoolPref(name);
        }
        else if (this._getPrefService().PREF_INT == type)
        {
            val = this._getPrefService().getIntPref(name);
        }
        else if (this._getPrefService().PREF_STRING == type)
        {
            val = this._getPrefService().getCharPref(name);
        }
        else
        {
            Bandwagon.Logger.error("Invalid pref: " + name);
        }
    }
    catch (e)
    {
        Bandwagon.Logger.error(e);
    }

    return val;
}

Bandwagon.Preferences.setPreferenceList = function(name, list)
{
    // TODO ensure there's no this._prefServiceListDelimiter in the list

    var joinedList = list.join(this._prefServiceListDelimiter);

    this.setPreference(name, joinedList);
}

Bandwagon.Preferences.getPreferenceList = function(name)
{
    var pref = this.getPreference(name, "char");

    if (!pref) return new Array();

    return pref.split(this._prefServiceListDelimiter);
}

Bandwagon.Preferences.addObserver = function(observer)
{
    var prefService = this._getPrefService();

    prefService.QueryInterface(Components.interfaces.nsIPrefBranch2);
    prefService.addObserver("", observer, false);
}

Bandwagon.Preferences.removeObserver = function(observer)
{
    this._getPrefService().removeObserver("", observer);
}

Bandwagon.Preferences.notifyObservers = function(data)
{
    Components.classes["@mozilla.org/observer-service;1"]
              .getService(Components.interfaces.nsIObserverService)
              .notifyObservers(null, "nsPref:changed", data);
}

Bandwagon.Preferences.getGlobalPreference = function(name, failSilently)
{
    var prefSvc = Components.classes["@mozilla.org/preferences-service;1"].
        getService(Components.interfaces.nsIPrefService);

    var val = null;

    try
    {
        var type = prefSvc.getPrefType(name);

        if (prefSvc.PREF_BOOL == type)
        {
            val = prefSvc.getBoolPref(name);
        }
        else if (prefSvc.PREF_INT == type)
        {
            val = prefSvc.getIntPref(name);
        }
        else if (prefSvc.PREF_STRING == type)
        {
            val = prefSvc.getCharPref(name);
        }
        else if (failSilently == undefined || failSilently == false)
        {
                Bandwagon.Logger.error("Invalid pref: " + name);
        }
    }
    catch (e)
    {
        if (failSilently == undefined || failSilently == false)
            Bandwagon.Logger.error(e);

        return null;
    }

    return val;
}

Bandwagon.Preferences.addGlobalObserver = function(observer, branchName)
{
    var prefService = Components.classes["@mozilla.org/preferences-service;1"]
        .getService(Components.interfaces.nsIPrefService);

    var branch = prefService.getBranch(branchName);
    branch.QueryInterface(Components.interfaces.nsIPrefBranch2);

    branch.addObserver("", observer, false);
}

Bandwagon.Preferences.removeGlobalObserver = function(observer, branchName)
{
    var prefService = Components.classes["@mozilla.org/preferences-service;1"]
        .getService(Components.interfaces.nsIPrefService);

    var branch = prefService.getBranch(branchName);
    branch.QueryInterface(Components.interfaces.nsIPrefBranch2);

    branch.removeObserver("", observer);
}

Bandwagon.Preferences._getPrefService = function()
{
    if (!this._prefServiceCache)
    {
        try
        {
            var prefSvc = Components.classes["@mozilla.org/preferences-service;1"].
                getService(Components.interfaces.nsIPrefService);
            this._prefServiceCache = prefSvc.getBranch(this._prefServiceRoot);
        }
        catch (e)
        {
            Bandwagon.Logger.error("Can't get Prefs Service: " + e);
        }
    }

    return this._prefServiceCache;
}


