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

Bandwagon.RPC.Event = function(type, result, response)
{
    this.Bandwagon = Bandwagon;

	// public instance variables
    this._type = type;
	this._result = result;
	this._response = response;
    this.error = null;
}

Bandwagon.RPC.Event.prototype.isError = function()
{
	return this._result != this.Bandwagon.RPC.Constants.BANDWAGON_RPC_NET_SUCCESS;
}

Bandwagon.RPC.Event.prototype.setError = function(error)
{
    this.error = error;
    this._result = error.code;
}

Bandwagon.RPC.Event.prototype.getError = function()
{
    if (this._result == this.Bandwagon.RPC.Constants.BANDWAGON_RPC_NET_SUCCESS) return null;
	
    if (this.error == null)
    {
        this.error = new this.Bandwagon.RPC.Error();
        this.error.code = -1;
        this.error.message = "Internal error";
    }

	return this.error;
}

Bandwagon.RPC.Event.prototype.getType = function()
{
	return this._type;
}

Bandwagon.RPC.Event.prototype.getData = function()
{
	return this._response;
}

Bandwagon.RPC.Event.prototype.toString = function()
{
	return "Event (" + (this.isError() ? this.getError().toString() : "Success") + ")";
}

