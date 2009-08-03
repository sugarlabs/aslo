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

Bandwagon.RPC.Net = function(Bandwagon, Components)
{
    this.Bandwagon = Bandwagon;
    this.Components = Components;

    // public instance variables
    this.onComplete = function(rpc, result, response, type, request) {};
    this.status = this.Bandwagon.RPC.Constants.BANDWAGON_RPC_NET_CREATED;
    this.id = ((new Date()).getTime() - 1169730000000) + "" + (Math.round(1000*Math.random())+1000);

    // private instance variables
    this._url = '';
    this._method = 'GET';
    this._basicAuthUsername = '';
    this._basicAuthPassword = '';
    this._queryString = '';
    this._postData = '';
    this._type = null;
    this._headers = {};

    this._request = null;
    this._logger = null;

    this.finished = function(result, response, request)
    {
        this._logger.debug('Bandwagon.RPC.Net.finished: ' + this.id + ': finished');
        this.status = this.Bandwagon.RPC.Constants.BANDWAGON_RPC_NET_FINISHED;
        this.onComplete(this, result, response, this._type, request);
    };

    this.failed = function(errorCode, errorMessage, data)
    {
        this._logger.debug('Bandwagon.RPC.Net.failed: ' + this.id + ': failed');
        this.status = this.Bandwagon.RPC.Constants.BANDWAGON_RPC_NET_FINISHED;
        var response = {errorCode: errorCode, errorMessage: errorMessage, data: data};
        this.onComplete(this, this.Bandwagon.RPC.Constants.BANDWAGON_RPC_NET_FAILURE, response, this._type, null);
    };

    this.ready = function (rpcnetrequest)
    {
        var rpcnet = this;

        try
        {
            //rpc._logger.debug('Bandwagon.RPC.Net.send.onreadystatechange: ' + rpc.id + ': readyState = ' + rpcnetrequest.readyState);
            if (rpcnetrequest.readyState != 4) { return; }
        }
        catch (e) 
        {
            rpcnet._logger.error('Bandwagon.RPC.Net.send.onreadystatechange: ' + rpcnet.id + ': error in readyState: ' + e);
            rpcnet.failed(rpcnet.Bandwagon.RPC.Constants.BANDWAGON_RPC_NET_ERROR_XHR_CONNECTION);
            return;
        }

        var result = rpcnet.Bandwagon.RPC.Constants.BANDWAGON_RPC_NET_SUCCESS;
        var status = 0;
        var response = null;
        var lastErr = null;

        try 
        {
            status = rpcnetrequest.status;
        }
        catch (e)
        {
            rpcnet._logger.error('Bandwagon.RPC.Net.send.onreadystatechange: ' + rpcnet.id + ': no http status... a protocol error occured');
            rpcnet.failed(rpcnet.Bandwagon.RPC.Constants.BANDWAGON_RPC_NET_ERROR_HTTP);
            return;
        }

        try
        {
            var xmlStr = rpcnetrequest.responseText;

            // dave@briks: The following hacks are workarounds for E4X
            // bugs/features. If anyone knows the dark secrets of namespace
            // support in e4x please let me know!

            xmlStr = xmlStr.replace(/^<\?xml\s+version\s*=\s*(["'])[^\1]+\1[^?]*\?>/, ""); // bug 336551
            xmlStr = xmlStr.replace(/xmlns="http:\/\/addons.mozilla.org\/"/gi, "");
            xmlStr = xmlStr.replace(/xml:base=/gi, "xmlbase=");

            response = new XML(xmlStr);

            //rpcnet._logger.debug("Bandwagon.RPC.Net.send.onreadystatechange: ' + rpcnet.id + ': XML representation: '" + response.toXMLString() + "'");
        }
        catch (e)
        {
            rpcnet._logger.error('Bandwagon.RPC.Net.send.onreadystatechange: ' + rpcnet.id + ": can't evaluate XML response... '" + e + "'");
            lastErr = e;
        }

        rpcnet._logger.debug('Bandwagon.RPC.Net.send.onreadystatechange: ' + rpcnet.id + ': completed, status = ' + status);
        rpcnet._logger.debug('Bandwagon.RPC.Net.send.onreadystatechange: ' + rpcnet.id + ": completed, response text = '" + rpcnetrequest.responseText + "'");

        if (
            (rpcnet._method == 'DELETE' && (status == 303))
            || (rpcnet._method == 'DELETE' && (status == 410))
            || (status >= 200 && status <= 300)
            )
        {
            if (response != null)
            {
                // everything went successfully
                rpcnet.finished(rpcnet.Bandwagon.RPC.Constants.BANDWAGON_RPC_NET_SUCCESS, response, rpcnetrequest);
                return;
            }
            else
            {
                // application error (bad xml)
                rpcnet.failed(rpcnet.Bandwagon.RPC.Constants.BANDWAGON_RPC_SERVICE_ERROR_BAD_XML, lastErr, response);
                return;
            }
        }

        // try to get an error message in response error -> lastErr

        try 
        {
            lastErr = (response.attribute("reason")?response.attribute("reason"):"?");
            rpcnet._logger.debug('Bandwagon.RPC.Net.send.onreadystatechange: ' + rpcnet.id + ": completed, response error message = '" + lastErr + "'");
        }
        catch (e)
        {
            rpcnet._logger.debug('Bandwagon.RPC.Net.send.onreadystatechange: ' + rpcnet.id + ": have an error status code (" + status + "), but there is no error message in the XML response");
            lastErr = null;
        }

        if (status == 400)
        {
            rpcnet.failed(rpcnet.Bandwagon.RPC.Constants.BANDWAGON_RPC_SERVICE_ERROR_BAD_REQUEST, lastErr, response);
            return;
        }
        else if (status == 401)
        {
            rpcnet.failed(rpcnet.Bandwagon.RPC.Constants.BANDWAGON_RPC_SERVICE_ERROR_UNAUTHORIZED, lastErr, response);
            return;
        }
        else if (status == 403)
        {
            rpcnet.failed(rpcnet.Bandwagon.RPC.Constants.BANDWAGON_RPC_SERVICE_ERROR_FORBIDDEN, lastErr, response);
            return;
        }
         else if (status == 404)
        {
            rpcnet.failed(rpcnet.Bandwagon.RPC.Constants.BANDWAGON_RPC_SERVICE_ERROR_NOT_FOUND, lastErr, response);
            return;
        }
         else if (status == 409)
        {
            rpcnet.failed(rpcnet.Bandwagon.RPC.Constants.BANDWAGON_RPC_SERVICE_ERROR_CONFLICT, lastErr, response);
            return;
        }
         else if (status == 422)
        {
            rpcnet.failed(rpcnet.Bandwagon.RPC.Constants.BANDWAGON_RPC_SERVICE_ERROR_BAD_CONTEXT, lastErr, response);
            return;
        }
         else if (status == 500)
        {
            rpcnet.failed(rpcnet.Bandwagon.RPC.Constants.BANDWAGON_RPC_SERVICE_ERROR_INTERNAL_SERVER_ERROR, lastErr, response);
            return;
        }
        else
        {
            rpcnet.failed(rpcnet.Bandwagon.RPC.Constants.BANDWAGON_RPC_SERVICE_ERROR_CRITICAL_ERROR, lastErr, response);
            return;
        }
    };
}

Bandwagon.RPC.Net.prototype.registerLogger = function(logger)
{
    this._logger = logger;
}

Bandwagon.RPC.Net.prototype.setUrl = function(url)
{
    this._url = url;
}

Bandwagon.RPC.Net.prototype.setType = function(type)
{
    this._type = type;
}

Bandwagon.RPC.Net.prototype.setPostData = function(args)
{
    this._postData = '';

    for (var i in args)
    {
        this._postData += this.Bandwagon.Util.encodeURL(i) + '=' + this.Bandwagon.Util.encodeURL(args[i]) + '&';
    }

    if ('&' == this._postData.charAt(this._postData.length-1))
    {
        this._postData = this._postData.substring(0,this._postData.length-1);
    }
}

Bandwagon.RPC.Net.prototype.setArguments = function(args)
{
    this._queryString = '';

    for (var i in args)
    {
        this._queryString += this.Bandwagon.Util.encodeURL(i) + '=' + this.Bandwagon.Util.encodeURL(args[i]) + '&';
    }

    if ('&' == this._queryString.charAt(this._queryString.length-1))
    {
        this._queryString = this._queryString.substring(0,this._queryString.length-1);
    }
}

Bandwagon.RPC.Net.prototype.setMethod = function(method)
{
    if (method == 'POST')
    {
        this._method = 'POST';
    }
    else if (method == 'DELETE')
    {
        this._method = 'DELETE';
    }
    else if (method == 'PUT')
    {
        this._method = 'PUT';
    }
    else 
    {
        this._method = 'GET';
    }

    this._method = method;
}

Bandwagon.RPC.Net.prototype.setHeader = function(header, value)
{
    this._headers[header] = value;
}

Bandwagon.RPC.Net.prototype.setCredentials = function(username, password)
{
    this._basicAuthUsername = username;
    this._basicAuthPassword = password;
}

Bandwagon.RPC.Net.prototype.send = function()
{
    var rpcnet = this;

    rpcnet.status = rpcnet.Bandwagon.RPC.Constants.BANDWAGON_RPC_NET_INPROGRESS;

    //rpc._logger.debug('Bandwagon.RPC.Net.send: ' + rpc.id + ': creating ' + rpc._method + ' XMLHttpRequest');

    var rpcnetrequest = rpcnet.Components.classes["@mozilla.org/xmlextras/xmlhttprequest;1"].createInstance(rpcnet.Components.interfaces.nsIXMLHttpRequest);

    if (!rpcnetrequest) { rpcnet.failed(rpcnet.Bandwagon.RPC.Constants.BANDWAGON_RPC_NET_ERROR_XHR_CREATE); }

    rpcnetrequest.mozBackgroundRequest = true;

    var postData = null;
    var url = rpcnet._url;

    if (('POST' == rpcnet._method || 'PUT' == rpcnet._method)
        && rpcnet._postData.length > 0)
    {
        postData = rpcnet._postData;
    }
    else if (rpcnet._queryString && (rpcnet._queryString.length > 0))
    {
        url += "?" + rpcnet._queryString;
    }

    rpcnet._logger.debug('Bandwagon.RPC.Net.send: ' + rpcnet.id + ': opening ' + rpcnet._method + ' XMLHttpRequest to ' + url);

    try
    {
        rpcnetrequest.open(rpcnet._method, url, true);
    }
    catch (e)
    {
        rpcnet._logger.error('Bandwagon.RPC.Net.send: ' + rpcnet.id + ': error opening connection: ' + e);
        rpcnet.failed(rpcnet.Bandwagon.RPC.Constants.BANDWAGON_RPC_NET_ERROR_XHR_CREATE);
        return;
    }

    if ('POST' == rpcnet._method)
    {
        rpcnetrequest.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    }

    if (('POST' == rpcnet._method || 'PUT' == rpcnet._method) && postData)
    {
        rpcnetrequest.setRequestHeader('Content-length', postData.length);
    }

    for (var header in rpcnet._headers)
    {
        //rpcnet._logger.debug('Bandwagon.RPC.Net.send: ' + rpcnet.id + ': adding custom header ' + header + ': ' + rpcnet._headers[header]);
        rpcnetrequest.setRequestHeader(header, rpcnet._headers[header]);
    }

    if ('' != rpcnet._basicAuthUsername) {
        rpcnet._logger.debug('Bandwagon.RPC.Net.send: using credentials for ' + rpcnet._basicAuthUsername);
        rpcnetrequest.setRequestHeader('Authorization', 'Basic ' + btoa(rpcnet._basicAuthUsername + ':' + rpcnet._basicAuthPassword));
    }

    rpcnetrequest.setRequestHeader("If-Modified-Since", "Sat, 1 Jan 2005 00:00:00 GMT");

    // Cache-Control: no-cache ?
    //rpcnetrequest.overrideMimeType('text/xml');

    rpcnetrequest.onreadystatechange = function() { rpcnet.ready(rpcnetrequest); };

    rpcnet._logger.debug('Bandwagon.RPC.Net.send: ' + rpcnet.id + ': sending XMLHttpRequest ' + (postData?' with data "' + postData + '"':''));

    rpcnetrequest.send(postData);
}
