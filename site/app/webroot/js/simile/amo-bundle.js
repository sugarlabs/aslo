

//////////////////////////// AJAX LOADER /////////////////////////////

if (typeof SimileAjax == "undefined") {
    var SimileAjax = {
        loaded:                 false,
        loadingScriptsCount:    0,
        error:                  null,
        params:                 { bundle:"true" }
    };
    
    SimileAjax.Platform = new Object();
        /*
            HACK: We need these 2 things here because we cannot simply append
            a <script> element containing code that accesses SimileAjax.Platform
            to initialize it because IE executes that <script> code first
            before it loads ajax.js and platform.js.
        */
        
    var getHead = function(doc) {
        return doc.getElementsByTagName("head")[0];
    };
    
    SimileAjax.findScript = function(doc, substring) {
        var heads = doc.documentElement.getElementsByTagName("head");
        for (var h = 0; h < heads.length; h++) {
            var node = heads[h].firstChild;
            while (node != null) {
                if (node.nodeType == 1 && node.tagName.toLowerCase() == "script") {
                    var url = node.src;
                    var i = url.indexOf(substring);
                    if (i >= 0) {
                        return url;
                    }
                }
                node = node.nextSibling;
            }
        }
        return null;
    };
    SimileAjax.includeJavascriptFile = function(doc, url, onerror, charset) {
        onerror = onerror || "";
        if (doc.body == null) {
            try {
                var q = "'" + onerror.replace( /'/g, '&apos' ) + "'"; // "
                doc.write("<script src='" + url + "' onerror="+ q +
                          (charset ? " charset='"+ charset +"'" : "") +
                          " type='text/javascript'>"+ onerror + "</script>");
                return false;
            } catch (e) {
                // fall through
            }
        }

        var script = doc.createElement("script");
        if (onerror) {
            try { script.innerHTML = onerror; } catch(e) {}
            script.setAttribute("onerror", onerror);
        }
        if (charset) {
            script.setAttribute("charset", charset);
        }
        script.type = "text/javascript";
        script.language = "JavaScript";
        script.src = url;
        return getHead(doc).appendChild(script);
    };
    SimileAjax.includeJavascriptFiles = function(doc, urlPrefix, filenames) {
        for (var i = 0; i < filenames.length; i++) {
            SimileAjax.includeJavascriptFile(doc, urlPrefix + filenames[i]);
        }
        SimileAjax.loadingScriptsCount += filenames.length;
        SimileAjax.includeJavascriptFile(doc, SimileAjax.urlPrefix + "scripts/signal.js?" + filenames.length);
    };
    SimileAjax.includeCssFile = function(doc, url) {
        if (doc.body == null) {
            try {
                doc.write("<link rel='stylesheet' href='" + url + "' type='text/css'/>");
                return;
            } catch (e) {
                // fall through
            }
        }
        
        var link = doc.createElement("link");
        link.setAttribute("rel", "stylesheet");
        link.setAttribute("type", "text/css");
        link.setAttribute("href", url);
        getHead(doc).appendChild(link);
    };
    SimileAjax.includeCssFiles = function(doc, urlPrefix, filenames) {
        for (var i = 0; i < filenames.length; i++) {
            SimileAjax.includeCssFile(doc, urlPrefix + filenames[i]);
        }
    };
    
    /**
     * Append into urls each string in suffixes after prefixing it with urlPrefix.
     * @param {Array} urls
     * @param {String} urlPrefix
     * @param {Array} suffixes
     */
    SimileAjax.prefixURLs = function(urls, urlPrefix, suffixes) {
        for (var i = 0; i < suffixes.length; i++) {
            urls.push(urlPrefix + suffixes[i]);
        }
    };

    /**
     * Parse out the query parameters from a URL
     * @param {String} url    the url to parse, or location.href if undefined
     * @param {Object} to     optional object to extend with the parameters
     * @param {Object} types  optional object mapping keys to value types
     *        (String, Number, Boolean or Array, String by default)
     * @return a key/value Object whose keys are the query parameter names
     * @type Object
     */
    SimileAjax.parseURLParameters = function(url, to, types) {
        to = to || {};
        types = types || {};
        
        if (typeof url == "undefined") {
            url = location.href;
        }
        var q = url.indexOf("?");
        if (q < 0) {
            return to;
        }
        url = (url+"#").slice(q+1, url.indexOf("#")); // toss the URL fragment
        
        var params = url.split("&"), param, parsed = {};
        var decode = window.decodeURIComponent || unescape;
        for (var i = 0; param = params[i]; i++) {
            var eq = param.indexOf("=");
            var name = decode(param.slice(0,eq));
            var old = parsed[name];
            if (typeof old == "undefined") {
                old = [];
            } else if (!(old instanceof Array)) {
                old = [old];
            }
            parsed[name] = old.concat(decode(param.slice(eq+1)));
        }
        for (var i in parsed) {
            if (!parsed.hasOwnProperty(i)) continue;
            var type = types[i] || String;
            var data = parsed[i];
            if (!(data instanceof Array)) {
                data = [data];
            }
            if (type === Boolean && data[0] == "false") {
                to[i] = false; // because Boolean("false") === true
            } else {
                to[i] = type.apply(this, data);
            }
        }
        return to;
    };

    if (typeof Simile_urlPrefix == "string") {
        SimileAjax.urlPrefix = Simile_urlPrefix + '/ajax/';
    }

    SimileAjax.loaded = true;
}
/*==================================================
 *  Platform Utility Functions and Constants
 *==================================================
 */

SimileAjax.Platform.os = {
    isMac:   false,
    isWin:   false,
    isWin32: false,
    isUnix:  false
};
SimileAjax.Platform.browser = {
    isIE:           false,
    isNetscape:     false,
    isMozilla:      false,
    isFirefox:      false,
    isOpera:        false,
    isSafari:       false,

    majorVersion:   0,
    minorVersion:   0
};

(function() {
    var an = navigator.appName.toLowerCase();
	var ua = navigator.userAgent.toLowerCase(); 
    
    /*
     *  Operating system
     */
	SimileAjax.Platform.os.isMac = (ua.indexOf('mac') != -1);
	SimileAjax.Platform.os.isWin = (ua.indexOf('win') != -1);
	SimileAjax.Platform.os.isWin32 = SimileAjax.Platform.isWin && (   
        ua.indexOf('95') != -1 || 
        ua.indexOf('98') != -1 || 
        ua.indexOf('nt') != -1 || 
        ua.indexOf('win32') != -1 || 
        ua.indexOf('32bit') != -1
    );
	SimileAjax.Platform.os.isUnix = (ua.indexOf('x11') != -1);
    
    /*
     *  Browser
     */
    SimileAjax.Platform.browser.isIE = (an.indexOf("microsoft") != -1);
    SimileAjax.Platform.browser.isNetscape = (an.indexOf("netscape") != -1);
    SimileAjax.Platform.browser.isMozilla = (ua.indexOf("mozilla") != -1);
    SimileAjax.Platform.browser.isFirefox = (ua.indexOf("firefox") != -1);
    SimileAjax.Platform.browser.isOpera = (an.indexOf("opera") != -1);
    SimileAjax.Platform.browser.isSafari = (an.indexOf("safari") != -1);
    
    var parseVersionString = function(s) {
        var a = s.split(".");
        SimileAjax.Platform.browser.majorVersion = parseInt(a[0]);
        SimileAjax.Platform.browser.minorVersion = parseInt(a[1]);
    };
    var indexOf = function(s, sub, start) {
        var i = s.indexOf(sub, start);
        return i >= 0 ? i : s.length;
    };
    
    if (SimileAjax.Platform.browser.isMozilla) {
        var offset = ua.indexOf("mozilla/");
        if (offset >= 0) {
            parseVersionString(ua.substring(offset + 8, indexOf(ua, " ", offset)));
        }
    }
    if (SimileAjax.Platform.browser.isIE) {
        var offset = ua.indexOf("msie ");
        if (offset >= 0) {
            parseVersionString(ua.substring(offset + 5, indexOf(ua, ";", offset)));
        }
    }
    if (SimileAjax.Platform.browser.isNetscape) {
        var offset = ua.indexOf("rv:");
        if (offset >= 0) {
            parseVersionString(ua.substring(offset + 3, indexOf(ua, ")", offset)));
        }
    }
    if (SimileAjax.Platform.browser.isFirefox) {
        var offset = ua.indexOf("firefox/");
        if (offset >= 0) {
            parseVersionString(ua.substring(offset + 8, indexOf(ua, " ", offset)));
        }
    }
    
    if (!("localeCompare" in String.prototype)) {
        String.prototype.localeCompare = function (s) {
            if (this < s) return -1;
            else if (this > s) return 1;
            else return 0;
        };
    }
})();

SimileAjax.Platform.getDefaultLocale = function() {
    return SimileAjax.Platform.clientLocale;
};/*==================================================
 *  Debug Utility Functions
 *==================================================
 */

SimileAjax.Debug = {
    silent: false
};

SimileAjax.Debug.log = function(msg) {
    var f;
    if ("console" in window && "log" in window.console) { // FireBug installed
        f = function(msg2) {
            console.log(msg2);
        }
    } else {
        f = function(msg2) {
            if (!SimileAjax.Debug.silent) {
                alert(msg2);
            }
        }
    }
    SimileAjax.Debug.log = f;
    f(msg);
};

SimileAjax.Debug.warn = function(msg) {
    var f;
    if ("console" in window && "warn" in window.console) { // FireBug installed
        f = function(msg2) {
            console.warn(msg2);
        }
    } else {
        f = function(msg2) {
            if (!SimileAjax.Debug.silent) {
                alert(msg2);
            }
        }
    }
    SimileAjax.Debug.warn = f;
    f(msg);
};

SimileAjax.Debug.exception = function(e, msg) {
    var f, params = SimileAjax.parseURLParameters();
    if (params.errors == "throw" || SimileAjax.params.errors == "throw") {
        f = function(e2, msg2) {
            throw(e2); // do not hide from browser's native debugging features
        };
    } else if ("console" in window && "error" in window.console) { // FireBug installed
        f = function(e2, msg2) {
            if (msg2 != null) {
                console.error(msg2 + " %o", e2);
            } else {
                console.error(e2);
            }
            throw(e2); // do not hide from browser's native debugging features
        };
    } else {
        f = function(e2, msg2) {
            if (!SimileAjax.Debug.silent) {
                alert("Caught exception: " + msg2 + "\n\nDetails: " + ("description" in e2 ? e2.description : e2));
            }
            throw(e2); // do not hide from browser's native debugging features
        };
    }
    SimileAjax.Debug.exception = f;
    f(e, msg);
};

SimileAjax.Debug.objectToString = function(o) {
    return SimileAjax.Debug._objectToString(o, "");
};

SimileAjax.Debug._objectToString = function(o, indent) {
    var indent2 = indent + " ";
    if (typeof o == "object") {
        var s = "{";
        for (n in o) {
            s += indent2 + n + ": " + SimileAjax.Debug._objectToString(o[n], indent2) + "\n";
        }
        s += indent + "}";
        return s;
    } else if (typeof o == "array") {
        var s = "[";
        for (var n = 0; n < o.length; n++) {
            s += SimileAjax.Debug._objectToString(o[n], indent2) + "\n";
        }
        s += indent + "]";
        return s;
    } else {
        return o;
    }
};
/**
 * @fileOverview XmlHttp utility functions
 * @name SimileAjax.XmlHttp
 */

SimileAjax.XmlHttp = new Object();

/**
 *  Callback for XMLHttp onRequestStateChange.
 */
SimileAjax.XmlHttp._onReadyStateChange = function(xmlhttp, fError, fDone) {
    switch (xmlhttp.readyState) {
    // 1: Request not yet made
    // 2: Contact established with server but nothing downloaded yet
    // 3: Called multiple while downloading in progress
    
    // Download complete
    case 4:
        try {
            if (xmlhttp.status == 0     // file:// urls, works on Firefox
             || xmlhttp.status == 200   // http:// urls
            ) {
                if (fDone) {
                    fDone(xmlhttp);
                }
            } else {
                if (fError) {
                    fError(
                        xmlhttp.statusText,
                        xmlhttp.status,
                        xmlhttp
                    );
                }
            }
        } catch (e) {
            SimileAjax.Debug.exception("XmlHttp: Error handling onReadyStateChange", e);
        }
        break;
    }
};

/**
 *  Creates an XMLHttpRequest object. On the first run, this
 *  function creates a platform-specific function for
 *  instantiating an XMLHttpRequest object and then replaces
 *  itself with that function.
 */
SimileAjax.XmlHttp._createRequest = function() {
    if (SimileAjax.Platform.browser.isIE) {
        var programIDs = [
        "Msxml2.XMLHTTP",
        "Microsoft.XMLHTTP",
        "Msxml2.XMLHTTP.4.0"
        ];
        for (var i = 0; i < programIDs.length; i++) {
            try {
                var programID = programIDs[i];
                var f = function() {
                    return new ActiveXObject(programID);
                };
                var o = f();
                
                // We are replacing the SimileAjax._createXmlHttpRequest
                // function with this inner function as we've
                // found out that it works. This is so that we
                // don't have to do all the testing over again
                // on subsequent calls.
                SimileAjax.XmlHttp._createRequest = f;
                
                return o;
            } catch (e) {
                // silent
            }
        }
        // fall through to try new XMLHttpRequest();
    }

    try {
        var f = function() {
            return new XMLHttpRequest();
        };
        var o = f();
        
        // We are replacing the SimileAjax._createXmlHttpRequest
        // function with this inner function as we've
        // found out that it works. This is so that we
        // don't have to do all the testing over again
        // on subsequent calls.
        SimileAjax.XmlHttp._createRequest = f;
        
        return o;
    } catch (e) {
        throw new Error("Failed to create an XMLHttpRequest object");
    }
};

/**
 * Performs an asynchronous HTTP GET.
 *  
 * @param {Function} fError a function of the form 
     function(statusText, statusCode, xmlhttp)
 * @param {Function} fDone a function of the form function(xmlhttp)
 */
SimileAjax.XmlHttp.get = function(url, fError, fDone) {
    var xmlhttp = SimileAjax.XmlHttp._createRequest();
    
    xmlhttp.open("GET", url, true);
    xmlhttp.onreadystatechange = function() {
        SimileAjax.XmlHttp._onReadyStateChange(xmlhttp, fError, fDone);
    };
    xmlhttp.send(null);
};

/**
 * Performs an asynchronous HTTP POST.
 *  
 * @param {Function} fError a function of the form 
     function(statusText, statusCode, xmlhttp)
 * @param {Function} fDone a function of the form function(xmlhttp)
 */
SimileAjax.XmlHttp.post = function(url, body, fError, fDone) {
    var xmlhttp = SimileAjax.XmlHttp._createRequest();
    
    xmlhttp.open("POST", url, true);
    xmlhttp.onreadystatechange = function() {
        SimileAjax.XmlHttp._onReadyStateChange(xmlhttp, fError, fDone);
    };
    xmlhttp.send(body);
};

SimileAjax.XmlHttp._forceXML = function(xmlhttp) {
    try {
        xmlhttp.overrideMimeType("text/xml");
    } catch (e) {
        xmlhttp.setrequestheader("Content-Type", "text/xml");
    }
};/*
 *  Copied directly from http://www.json.org/json.js.
 */

/*
    json.js
    2006-04-28

    This file adds these methods to JavaScript:

        object.toJSONString()

            This method produces a JSON text from an object. The
            object must not contain any cyclical references.

        array.toJSONString()

            This method produces a JSON text from an array. The
            array must not contain any cyclical references.

        string.parseJSON()

            This method parses a JSON text to produce an object or
            array. It will return false if there is an error.
*/

SimileAjax.JSON = new Object();

(function () {
    var m = {
        '\b': '\\b',
        '\t': '\\t',
        '\n': '\\n',
        '\f': '\\f',
        '\r': '\\r',
        '"' : '\\"',
        '\\': '\\\\'
    };
    var s = {
        array: function (x) {
            var a = ['['], b, f, i, l = x.length, v;
            for (i = 0; i < l; i += 1) {
                v = x[i];
                f = s[typeof v];
                if (f) {
                    v = f(v);
                    if (typeof v == 'string') {
                        if (b) {
                            a[a.length] = ',';
                        }
                        a[a.length] = v;
                        b = true;
                    }
                }
            }
            a[a.length] = ']';
            return a.join('');
        },
        'boolean': function (x) {
            return String(x);
        },
        'null': function (x) {
            return "null";
        },
        number: function (x) {
            return isFinite(x) ? String(x) : 'null';
        },
        object: function (x) {
            if (x) {
                if (x instanceof Array) {
                    return s.array(x);
                }
                var a = ['{'], b, f, i, v;
                for (i in x) {
                    v = x[i];
                    f = s[typeof v];
                    if (f) {
                        v = f(v);
                        if (typeof v == 'string') {
                            if (b) {
                                a[a.length] = ',';
                            }
                            a.push(s.string(i), ':', v);
                            b = true;
                        }
                    }
                }
                a[a.length] = '}';
                return a.join('');
            }
            return 'null';
        },
        string: function (x) {
            if (/["\\\x00-\x1f]/.test(x)) {
                x = x.replace(/([\x00-\x1f\\"])/g, function(a, b) {
                    var c = m[b];
                    if (c) {
                        return c;
                    }
                    c = b.charCodeAt();
                    return '\\u00' +
                        Math.floor(c / 16).toString(16) +
                        (c % 16).toString(16);
                });
            }
            return '"' + x + '"';
        }
    };

    SimileAjax.JSON.toJSONString = function(o) {
        if (o instanceof Object) {
            return s.object(o);
        } else if (o instanceof Array) {
            return s.array(o);
        } else {
            return o.toString();
        }
    };
    
    SimileAjax.JSON.parseJSON = function () {
        try {
            return !(/[^,:{}\[\]0-9.\-+Eaeflnr-u \n\r\t]/.test(
                    this.replace(/"(\\.|[^"\\])*"/g, ''))) &&
                eval('(' + this + ')');
        } catch (e) {
            return false;
        }
    };
})();
/*==================================================
 *  DOM Utility Functions
 *==================================================
 */

SimileAjax.DOM = new Object();

SimileAjax.DOM.registerEventWithObject = function(elmt, eventName, obj, handlerName) {
    SimileAjax.DOM.registerEvent(elmt, eventName, function(elmt2, evt, target) {
        return obj[handlerName].call(obj, elmt2, evt, target);
    });
};

SimileAjax.DOM.registerEvent = function(elmt, eventName, handler) {
    var handler2 = function(evt) {
        evt = (evt) ? evt : ((event) ? event : null);
        if (evt) {
            var target = (evt.target) ? 
                evt.target : ((evt.srcElement) ? evt.srcElement : null);
            if (target) {
                target = (target.nodeType == 1 || target.nodeType == 9) ? 
                    target : target.parentNode;
            }
            
            return handler(elmt, evt, target);
        }
        return true;
    }
    
    if (SimileAjax.Platform.browser.isIE) {
        elmt.attachEvent("on" + eventName, handler2);
    } else {
        elmt.addEventListener(eventName, handler2, false);
    }
};

SimileAjax.DOM.getPageCoordinates = function(elmt) {
    var left = 0;
    var top = 0;
    
    if (elmt.nodeType != 1) {
        elmt = elmt.parentNode;
    }
    
    var elmt2 = elmt;
    while (elmt2 != null) {
        left += elmt2.offsetLeft;
        top += elmt2.offsetTop;
        elmt2 = elmt2.offsetParent;
    }
    
    var body = document.body;
    while (elmt != null && elmt != body) {
        if ("scrollLeft" in elmt) {
            left -= elmt.scrollLeft;
            top -= elmt.scrollTop;
        }
        elmt = elmt.parentNode;
    }
    
    return { left: left, top: top };
};

SimileAjax.DOM.getSize = function(elmt) {
	var w = this.getStyle(elmt,"width");
	var h = this.getStyle(elmt,"height");
	if (w.indexOf("px") > -1) w = w.replace("px","");
	if (h.indexOf("px") > -1) h = h.replace("px","");
	return {
		w: w,
		h: h
	}
}

SimileAjax.DOM.getStyle = function(elmt, styleProp) {
    if (elmt.currentStyle) { // IE
        var style = elmt.currentStyle[styleProp];
    } else if (window.getComputedStyle) { // standard DOM
        var style = document.defaultView.getComputedStyle(elmt, null).getPropertyValue(styleProp);
    } else {
    	var style = "";
    }
    return style;
}

SimileAjax.DOM.getEventRelativeCoordinates = function(evt, elmt) {
    if (SimileAjax.Platform.browser.isIE) {
        return {
            x: evt.offsetX,
            y: evt.offsetY
        };
    } else {
        var coords = SimileAjax.DOM.getPageCoordinates(elmt);
        return {
            x: evt.pageX - coords.left,
            y: evt.pageY - coords.top
        };
    }
};

SimileAjax.DOM.getEventPageCoordinates = function(evt) {
    if (SimileAjax.Platform.browser.isIE) {
        return {
            x: evt.clientX + document.body.scrollLeft,
            y: evt.clientY + document.body.scrollTop
        };
    } else {
        return {
            x: evt.pageX,
            y: evt.pageY
        };
    }
};

SimileAjax.DOM.hittest = function(x, y, except) {
    return SimileAjax.DOM._hittest(document.body, x, y, except);
};

SimileAjax.DOM._hittest = function(elmt, x, y, except) {
    var childNodes = elmt.childNodes;
    outer: for (var i = 0; i < childNodes.length; i++) {
        var childNode = childNodes[i];
        for (var j = 0; j < except.length; j++) {
            if (childNode == except[j]) {
                continue outer;
            }
        }
        
        if (childNode.offsetWidth == 0 && childNode.offsetHeight == 0) {
            /*
             *  Sometimes SPAN elements have zero width and height but
             *  they have children like DIVs that cover non-zero areas.
             */
            var hitNode = SimileAjax.DOM._hittest(childNode, x, y, except);
            if (hitNode != childNode) {
                return hitNode;
            }
        } else {
            var top = 0;
            var left = 0;
            
            var node = childNode;
            while (node) {
                top += node.offsetTop;
                left += node.offsetLeft;
                node = node.offsetParent;
            }
            
            if (left <= x && top <= y && (x - left) < childNode.offsetWidth && (y - top) < childNode.offsetHeight) {
                return SimileAjax.DOM._hittest(childNode, x, y, except);
            } else if (childNode.nodeType == 1 && childNode.tagName == "TR") {
                /*
                 *  Table row might have cells that span several rows.
                 */
                var childNode2 = SimileAjax.DOM._hittest(childNode, x, y, except);
                if (childNode2 != childNode) {
                    return childNode2;
                }
            }
        }
    }
    return elmt;
};

SimileAjax.DOM.cancelEvent = function(evt) {
    evt.returnValue = false;
    evt.cancelBubble = true;
    if ("preventDefault" in evt) {
        evt.preventDefault();
    }
};

SimileAjax.DOM.appendClassName = function(elmt, className) {
    var classes = elmt.className.split(" ");
    for (var i = 0; i < classes.length; i++) {
        if (classes[i] == className) {
            return;
        }
    }
    classes.push(className);
    elmt.className = classes.join(" ");
};

SimileAjax.DOM.createInputElement = function(type) {
    var div = document.createElement("div");
    div.innerHTML = "<input type='" + type + "' />";
    
    return div.firstChild;
};

SimileAjax.DOM.createDOMFromTemplate = function(template) {
    var result = {};
    result.elmt = SimileAjax.DOM._createDOMFromTemplate(template, result, null);
    
    return result;
};

SimileAjax.DOM._createDOMFromTemplate = function(templateNode, result, parentElmt) {
    if (templateNode == null) {
        /*
        var node = doc.createTextNode("--null--");
        if (parentElmt != null) {
            parentElmt.appendChild(node);
        }
        return node;
        */
        return null;
    } else if (typeof templateNode != "object") {
        var node = document.createTextNode(templateNode);
        if (parentElmt != null) {
            parentElmt.appendChild(node);
        }
        return node;
    } else {
        var elmt = null;
        if ("tag" in templateNode) {
            var tag = templateNode.tag;
            if (parentElmt != null) {
                if (tag == "tr") {
                    elmt = parentElmt.insertRow(parentElmt.rows.length);
                } else if (tag == "td") {
                    elmt = parentElmt.insertCell(parentElmt.cells.length);
                }
            }
            if (elmt == null) {
                elmt = tag == "input" ?
                    SimileAjax.DOM.createInputElement(templateNode.type) :
                    document.createElement(tag);
                    
                if (parentElmt != null) {
                    parentElmt.appendChild(elmt);
                }
            }
        } else {
            elmt = templateNode.elmt;
            if (parentElmt != null) {
                parentElmt.appendChild(elmt);
            }
        }
        
        for (var attribute in templateNode) {
            var value = templateNode[attribute];
            
            if (attribute == "field") {
                result[value] = elmt;
                
            } else if (attribute == "className") {
                elmt.className = value;
            } else if (attribute == "id") {
                elmt.id = value;
            } else if (attribute == "title") {
                elmt.title = value;
            } else if (attribute == "type" && elmt.tagName == "input") {
                // do nothing
            } else if (attribute == "style") {
                for (n in value) {
                    var v = value[n];
                    if (n == "float") {
                        n = SimileAjax.Platform.browser.isIE ? "styleFloat" : "cssFloat";
                    }
                    elmt.style[n] = v;
                }
            } else if (attribute == "children") {
                for (var i = 0; i < value.length; i++) {
                    SimileAjax.DOM._createDOMFromTemplate(value[i], result, elmt);
                }
            } else if (attribute != "tag" && attribute != "elmt") {
                elmt.setAttribute(attribute, value);
            }
        }
        return elmt;
    }
}

SimileAjax.DOM._cachedParent = null;
SimileAjax.DOM.createElementFromString = function(s) {
    if (SimileAjax.DOM._cachedParent == null) {
        SimileAjax.DOM._cachedParent = document.createElement("div");
    }
    SimileAjax.DOM._cachedParent.innerHTML = s;
    return SimileAjax.DOM._cachedParent.firstChild;
};

SimileAjax.DOM.createDOMFromString = function(root, s, fieldElmts) {
    var elmt = typeof root == "string" ? document.createElement(root) : root;
    elmt.innerHTML = s;
    
    var dom = { elmt: elmt };
    SimileAjax.DOM._processDOMChildrenConstructedFromString(dom, elmt, fieldElmts != null ? fieldElmts : {} );
    
    return dom;
};

SimileAjax.DOM._processDOMConstructedFromString = function(dom, elmt, fieldElmts) {
    var id = elmt.id;
    if (id != null && id.length > 0) {
        elmt.removeAttribute("id");
        if (id in fieldElmts) {
            var parentElmt = elmt.parentNode;
            parentElmt.insertBefore(fieldElmts[id], elmt);
            parentElmt.removeChild(elmt);
            
            dom[id] = fieldElmts[id];
            return;
        } else {
            dom[id] = elmt;
        }
    }
    
    if (elmt.hasChildNodes()) {
        SimileAjax.DOM._processDOMChildrenConstructedFromString(dom, elmt, fieldElmts);
    }
};

SimileAjax.DOM._processDOMChildrenConstructedFromString = function(dom, elmt, fieldElmts) {
    var node = elmt.firstChild;
    while (node != null) {
        var node2 = node.nextSibling;
        if (node.nodeType == 1) {
            SimileAjax.DOM._processDOMConstructedFromString(dom, node, fieldElmts);
        }
        node = node2;
    }
};
/**
 * @fileOverview Graphics utility functions and constants
 * @name SimileAjax.Graphics
 */

SimileAjax.Graphics = new Object();

/**
 * A boolean value indicating whether PNG translucency is supported on the
 * user's browser or not.
 *
 * @type Boolean
 */
SimileAjax.Graphics.pngIsTranslucent = (!SimileAjax.Platform.browser.isIE) || (SimileAjax.Platform.browser.majorVersion > 6);

/*==================================================
 *  Opacity, translucency
 *==================================================
 */
SimileAjax.Graphics._createTranslucentImage1 = function(url, verticalAlign) {
    var elmt = document.createElement("img");
    elmt.setAttribute("src", url);
    if (verticalAlign != null) {
        elmt.style.verticalAlign = verticalAlign;
    }
    return elmt;
};
SimileAjax.Graphics._createTranslucentImage2 = function(url, verticalAlign) {
    var elmt = document.createElement("img");
    elmt.style.width = "1px";  // just so that IE will calculate the size property
    elmt.style.height = "1px";
    elmt.style.filter = "progid:DXImageTransform.Microsoft.AlphaImageLoader(src='" + url +"', sizingMethod='image')";
    elmt.style.verticalAlign = (verticalAlign != null) ? verticalAlign : "middle";
    return elmt;
};

/**
 * Creates a DOM element for an <code>img</code> tag using the URL given. This
 * is a convenience method that automatically includes the necessary CSS to
 * allow for translucency, even on IE.
 * 
 * @function
 * @param {String} url the URL to the image
 * @param {String} verticalAlign the CSS value for the image's vertical-align
 * @return {Element} a DOM element containing the <code>img</code> tag
 */
SimileAjax.Graphics.createTranslucentImage = SimileAjax.Graphics.pngIsTranslucent ?
    SimileAjax.Graphics._createTranslucentImage1 :
    SimileAjax.Graphics._createTranslucentImage2;

SimileAjax.Graphics._createTranslucentImageHTML1 = function(url, verticalAlign) {
    return "<img src=\"" + url + "\"" +
        (verticalAlign != null ? " style=\"vertical-align: " + verticalAlign + ";\"" : "") +
        " />";
};
SimileAjax.Graphics._createTranslucentImageHTML2 = function(url, verticalAlign) {
    var style = 
        "width: 1px; height: 1px; " +
        "filter:progid:DXImageTransform.Microsoft.AlphaImageLoader(src='" + url +"', sizingMethod='image');" +
        (verticalAlign != null ? " vertical-align: " + verticalAlign + ";" : "");
        
    return "<img src='" + url + "' style=\"" + style + "\" />";
};

/**
 * Creates an HTML string for an <code>img</code> tag using the URL given.
 * This is a convenience method that automatically includes the necessary CSS
 * to allow for translucency, even on IE.
 * 
 * @function
 * @param {String} url the URL to the image
 * @param {String} verticalAlign the CSS value for the image's vertical-align
 * @return {String} a string containing the <code>img</code> tag
 */
SimileAjax.Graphics.createTranslucentImageHTML = SimileAjax.Graphics.pngIsTranslucent ?
    SimileAjax.Graphics._createTranslucentImageHTML1 :
    SimileAjax.Graphics._createTranslucentImageHTML2;

/**
 * Sets the opacity on the given DOM element.
 *
 * @param {Element} elmt the DOM element to set the opacity on
 * @param {Number} opacity an integer from 0 to 100 specifying the opacity
 */
SimileAjax.Graphics.setOpacity = function(elmt, opacity) {
    if (SimileAjax.Platform.browser.isIE) {
        elmt.style.filter = "progid:DXImageTransform.Microsoft.Alpha(Style=0,Opacity=" + opacity + ")";
    } else {
        var o = (opacity / 100).toString();
        elmt.style.opacity = o;
        elmt.style.MozOpacity = o;
    }
};

/*==================================================
 *  Bubble
 *==================================================
 */
SimileAjax.Graphics._bubbleMargins = {
    top:      33,
    bottom:   42,
    left:     33,
    right:    40
}

// pixels from boundary of the whole bubble div to the tip of the arrow
SimileAjax.Graphics._arrowOffsets = { 
    top:      0,
    bottom:   9,
    left:     1,
    right:    8
}

SimileAjax.Graphics._bubblePadding = 15;
SimileAjax.Graphics._bubblePointOffset = 6;
SimileAjax.Graphics._halfArrowWidth = 18;

/**
 * Creates a nice, rounded bubble popup with the given content in a div,
 * page coordinates and a suggested width. The bubble will point to the 
 * location on the page as described by pageX and pageY.  All measurements 
 * should be given in pixels.
 *
 * @param {Element} the content div
 * @param {Number} pageX the x coordinate of the point to point to
 * @param {Number} pageY the y coordinate of the point to point to
 * @param {Number} contentWidth a suggested width of the content
 * @param {String} orientation a string ("top", "bottom", "left", or "right")
 *   that describes the orientation of the arrow on the bubble
 */
SimileAjax.Graphics.createBubbleForContentAndPoint = function(div, pageX, pageY, contentWidth, orientation) {
    if (typeof contentWidth != "number") {
        contentWidth = 300;
    }
    
    div.style.position = "absolute";
    div.style.left = "-5000px";
    div.style.top = "0px";
    div.style.width = contentWidth + "px";
    document.body.appendChild(div);
    
    window.setTimeout(function() {
        var width = div.scrollWidth + 10;
        var height = div.scrollHeight + 10;
        
        var bubble = SimileAjax.Graphics.createBubbleForPoint(pageX, pageY, width, height, orientation);
        
        document.body.removeChild(div);
        div.style.position = "static";
        div.style.left = "";
        div.style.top = "";
        div.style.width = width + "px";
        bubble.content.appendChild(div);
    }, 200);
};

/**
 * Creates a nice, rounded bubble popup with the given page coordinates and
 * content dimensions.  The bubble will point to the location on the page
 * as described by pageX and pageY.  All measurements should be given in
 * pixels.
 *
 * @param {Number} pageX the x coordinate of the point to point to
 * @param {Number} pageY the y coordinate of the point to point to
 * @param {Number} contentWidth the width of the content box in the bubble
 * @param {Number} contentHeight the height of the content box in the bubble
 * @param {String} orientation a string ("top", "bottom", "left", or "right")
 *   that describes the orientation of the arrow on the bubble
 * @return {Element} a DOM element for the newly created bubble
 */
SimileAjax.Graphics.createBubbleForPoint = function(pageX, pageY, contentWidth, contentHeight, orientation) {
    function getWindowDims() {
        if (typeof window.innerHeight == 'number') {
            return { w:window.innerWidth, h:window.innerHeight }; // Non-IE
        } else if (document.documentElement && document.documentElement.clientHeight) {
            return { // IE6+, in "standards compliant mode"
                w:document.documentElement.clientWidth,
                h:document.documentElement.clientHeight
            };
        } else if (document.body && document.body.clientHeight) {
            return { // IE 4 compatible
                w:document.body.clientWidth,
                h:document.body.clientHeight
            };
        }
    }

    var close = function() { 
        if (!bubble._closed) {
            document.body.removeChild(bubble._div);
            bubble._doc = null;
            bubble._div = null;
            bubble._content = null;
            bubble._closed = true;
        }
    }
    var bubble = {
        _closed:   false
    };
    
    var dims = getWindowDims();
    var docWidth = dims.w;
    var docHeight = dims.h;

    var margins = SimileAjax.Graphics._bubbleMargins;
    contentWidth = parseInt(contentWidth, 10); // harden against bad input bugs
    contentHeight = parseInt(contentHeight, 10); // getting numbers-as-strings
    var bubbleWidth = margins.left + contentWidth + margins.right;
    var bubbleHeight = margins.top + contentHeight + margins.bottom;
    
    var pngIsTranslucent = SimileAjax.Graphics.pngIsTranslucent;
    var urlPrefix = SimileAjax.urlPrefix;
    
    var setImg = function(elmt, url, width, height) {
        elmt.style.position = "absolute";
        elmt.style.width = width + "px";
        elmt.style.height = height + "px";
        if (pngIsTranslucent) {
            elmt.style.background = "url(" + url + ")";
        } else {
            elmt.style.filter = "progid:DXImageTransform.Microsoft.AlphaImageLoader(src='" + url +"', sizingMethod='crop')";
        }
    }
    var div = document.createElement("div");
    div.style.width = bubbleWidth + "px";
    div.style.height = bubbleHeight + "px";
    div.style.position = "absolute";
    div.style.zIndex = 1000;
    
    var layer = SimileAjax.WindowManager.pushLayer(close, true, div);
    bubble._div = div;
    bubble.close = function() { SimileAjax.WindowManager.popLayer(layer); }
    
    var divInner = document.createElement("div");
    divInner.style.width = "100%";
    divInner.style.height = "100%";
    divInner.style.position = "relative";
    div.appendChild(divInner);
    
    var createImg = function(url, left, top, width, height) {
        var divImg = document.createElement("div");
        divImg.style.left = left + "px";
        divImg.style.top = top + "px";
        setImg(divImg, url, width, height);
        divInner.appendChild(divImg);
    }
    
    createImg(urlPrefix + "images/bubble-top-left.png", 0, 0, margins.left, margins.top);
    createImg(urlPrefix + "images/bubble-top.png", margins.left, 0, contentWidth, margins.top);
    createImg(urlPrefix + "images/bubble-top-right.png", margins.left + contentWidth, 0, margins.right, margins.top);
    
    createImg(urlPrefix + "images/bubble-left.png", 0, margins.top, margins.left, contentHeight);
    createImg(urlPrefix + "images/bubble-right.png", margins.left + contentWidth, margins.top, margins.right, contentHeight);
    
    createImg(urlPrefix + "images/bubble-bottom-left.png", 0, margins.top + contentHeight, margins.left, margins.bottom);
    createImg(urlPrefix + "images/bubble-bottom.png", margins.left, margins.top + contentHeight, contentWidth, margins.bottom);
    createImg(urlPrefix + "images/bubble-bottom-right.png", margins.left + contentWidth, margins.top + contentHeight, margins.right, margins.bottom);
    
    var divClose = document.createElement("div");
    divClose.style.left = (bubbleWidth - margins.right + SimileAjax.Graphics._bubblePadding - 16 - 2) + "px";
    divClose.style.top = (margins.top - SimileAjax.Graphics._bubblePadding + 1) + "px";
    divClose.style.cursor = "pointer";
    setImg(divClose, urlPrefix + "images/close-button.png", 16, 16);
    SimileAjax.WindowManager.registerEventWithObject(divClose, "click", bubble, "close");
    divInner.appendChild(divClose);
        
    var divContent = document.createElement("div");
    divContent.style.position = "absolute";
    divContent.style.left = margins.left + "px";
    divContent.style.top = margins.top + "px";
    divContent.style.width = contentWidth + "px";
    divContent.style.height = contentHeight + "px";
    divContent.style.overflow = "auto";
    divContent.style.background = "white";
    divInner.appendChild(divContent);
    bubble.content = divContent;
    
    (function() {
        if (pageX - SimileAjax.Graphics._halfArrowWidth - SimileAjax.Graphics._bubblePadding > 0 &&
            pageX + SimileAjax.Graphics._halfArrowWidth + SimileAjax.Graphics._bubblePadding < docWidth) {
            
            var left = pageX - Math.round(contentWidth / 2) - margins.left;
            left = pageX < (docWidth / 2) ?
                Math.max(left, -(margins.left - SimileAjax.Graphics._bubblePadding)) : 
                Math.min(left, docWidth + (margins.right - SimileAjax.Graphics._bubblePadding) - bubbleWidth);
                
            if ((orientation && orientation == "top") || (!orientation && (pageY - SimileAjax.Graphics._bubblePointOffset - bubbleHeight > 0))) { // top
                var divImg = document.createElement("div");
                
                divImg.style.left = (pageX - SimileAjax.Graphics._halfArrowWidth - left) + "px";
                divImg.style.top = (margins.top + contentHeight) + "px";
                setImg(divImg, urlPrefix + "images/bubble-bottom-arrow.png", 37, margins.bottom);
                divInner.appendChild(divImg);
                
                div.style.left = left + "px";
                div.style.top = (pageY - SimileAjax.Graphics._bubblePointOffset - bubbleHeight + 
                    SimileAjax.Graphics._arrowOffsets.bottom) + "px";
                
                return;
            } else if ((orientation && orientation == "bottom") || (!orientation && (pageY + SimileAjax.Graphics._bubblePointOffset + bubbleHeight < docHeight))) { // bottom
                var divImg = document.createElement("div");
                
                divImg.style.left = (pageX - SimileAjax.Graphics._halfArrowWidth - left) + "px";
                divImg.style.top = "0px";
                setImg(divImg, urlPrefix + "images/bubble-top-arrow.png", 37, margins.top);
                divInner.appendChild(divImg);
                
                div.style.left = left + "px";
                div.style.top = (pageY + SimileAjax.Graphics._bubblePointOffset - 
                    SimileAjax.Graphics._arrowOffsets.top) + "px";
                
                return;
            }
        }
        
        var top = pageY - Math.round(contentHeight / 2) - margins.top;
        top = pageY < (docHeight / 2) ?
            Math.max(top, -(margins.top - SimileAjax.Graphics._bubblePadding)) : 
            Math.min(top, docHeight + (margins.bottom - SimileAjax.Graphics._bubblePadding) - bubbleHeight);
                
        if ((orientation && orientation == "left") || (!orientation && (pageX - SimileAjax.Graphics._bubblePointOffset - bubbleWidth > 0))) { // left
            var divImg = document.createElement("div");
            
            divImg.style.left = (margins.left + contentWidth) + "px";
            divImg.style.top = (pageY - SimileAjax.Graphics._halfArrowWidth - top) + "px";
            setImg(divImg, urlPrefix + "images/bubble-right-arrow.png", margins.right, 37);
            divInner.appendChild(divImg);
            
            div.style.left = (pageX - SimileAjax.Graphics._bubblePointOffset - bubbleWidth +
                SimileAjax.Graphics._arrowOffsets.right) + "px";
            div.style.top = top + "px";
        } else if ((orientation && orientation == "right") || (!orientation && (pageX - SimileAjax.Graphics._bubblePointOffset - bubbleWidth < docWidth))) { // right
            var divImg = document.createElement("div");
            
            divImg.style.left = "0px";
            divImg.style.top = (pageY - SimileAjax.Graphics._halfArrowWidth - top) + "px";
            setImg(divImg, urlPrefix + "images/bubble-left-arrow.png", margins.left, 37);
            divInner.appendChild(divImg);
            
            div.style.left = (pageX + SimileAjax.Graphics._bubblePointOffset - 
                SimileAjax.Graphics._arrowOffsets.left) + "px";
            div.style.top = top + "px";
        }
    })();
    
    document.body.appendChild(div);
    
    return bubble;
};

/**
 * Creates a floating, rounded message bubble in the center of the window for
 * displaying modal information, e.g. "Loading..."
 *
 * @param {Document} doc the root document for the page to render on
 * @param {Object} an object with two properties, contentDiv and containerDiv,
 *   consisting of the newly created DOM elements
 */
SimileAjax.Graphics.createMessageBubble = function(doc) {
    var containerDiv = doc.createElement("div");
    if (SimileAjax.Graphics.pngIsTranslucent) {
        var topDiv = doc.createElement("div");
        topDiv.style.height = "33px";
        topDiv.style.background = "url(" + SimileAjax.urlPrefix + "images/message-top-left.png) top left no-repeat";
        topDiv.style.paddingLeft = "44px";
        containerDiv.appendChild(topDiv);
        
        var topRightDiv = doc.createElement("div");
        topRightDiv.style.height = "33px";
        topRightDiv.style.background = "url(" + SimileAjax.urlPrefix + "images/message-top-right.png) top right no-repeat";
        topDiv.appendChild(topRightDiv);
        
        var middleDiv = doc.createElement("div");
        middleDiv.style.background = "url(" + SimileAjax.urlPrefix + "images/message-left.png) top left repeat-y";
        middleDiv.style.paddingLeft = "44px";
        containerDiv.appendChild(middleDiv);
        
        var middleRightDiv = doc.createElement("div");
        middleRightDiv.style.background = "url(" + SimileAjax.urlPrefix + "images/message-right.png) top right repeat-y";
        middleRightDiv.style.paddingRight = "44px";
        middleDiv.appendChild(middleRightDiv);
        
        var contentDiv = doc.createElement("div");
        middleRightDiv.appendChild(contentDiv);
        
        var bottomDiv = doc.createElement("div");
        bottomDiv.style.height = "55px";
        bottomDiv.style.background = "url(" + SimileAjax.urlPrefix + "images/message-bottom-left.png) bottom left no-repeat";
        bottomDiv.style.paddingLeft = "44px";
        containerDiv.appendChild(bottomDiv);
        
        var bottomRightDiv = doc.createElement("div");
        bottomRightDiv.style.height = "55px";
        bottomRightDiv.style.background = "url(" + SimileAjax.urlPrefix + "images/message-bottom-right.png) bottom right no-repeat";
        bottomDiv.appendChild(bottomRightDiv);
    } else {
        containerDiv.style.border = "2px solid #7777AA";
        containerDiv.style.padding = "20px";
        containerDiv.style.background = "white";
        SimileAjax.Graphics.setOpacity(containerDiv, 90);
        
        var contentDiv = doc.createElement("div");
        containerDiv.appendChild(contentDiv);
    }
    
    return {
        containerDiv:   containerDiv,
        contentDiv:     contentDiv
    };
};

/*==================================================
 *  Animation
 *==================================================
 */

/**
 * Creates an animation for a function, and an interval of values.  The word
 * "animation" here is used in the sense of repeatedly calling a function with
 * a current value from within an interval, and a delta value.
 *
 * @param {Function} f a function to be called every 50 milliseconds throughout
 *   the animation duration, of the form f(current, delta), where current is
 *   the current value within the range and delta is the current change.
 * @param {Number} from a starting value
 * @param {Number} to an ending value
 * @param {Number} duration the duration of the animation in milliseconds
 * @param {Function} [cont] an optional function that is called at the end of
 *   the animation, i.e. a continuation.
 * @return {SimileAjax.Graphics._Animation} a new animation object
 */
SimileAjax.Graphics.createAnimation = function(f, from, to, duration, cont) {
    return new SimileAjax.Graphics._Animation(f, from, to, duration, cont);
};

SimileAjax.Graphics._Animation = function(f, from, to, duration, cont) {
    this.f = f;
    this.cont = (typeof cont == "function") ? cont : function() {};
    
    this.from = from;
    this.to = to;
    this.current = from;
    
    this.duration = duration;
    this.start = new Date().getTime();
    this.timePassed = 0;
};

/**
 * Runs this animation.
 */
SimileAjax.Graphics._Animation.prototype.run = function() {
    var a = this;
    window.setTimeout(function() { a.step(); }, 50);
};

/**
 * Increments this animation by one step, and then continues the animation with
 * <code>run()</code>.
 */
SimileAjax.Graphics._Animation.prototype.step = function() {
    this.timePassed += 50;
    
    var timePassedFraction = this.timePassed / this.duration;
    var parameterFraction = -Math.cos(timePassedFraction * Math.PI) / 2 + 0.5;
    var current = parameterFraction * (this.to - this.from) + this.from;
    
    try {
        this.f(current, current - this.current);
    } catch (e) {
    }
    this.current = current;
    
    if (this.timePassed < this.duration) {
        this.run();
    } else {
        this.f(this.to, 0);
        this["cont"]();
    }
};

/*==================================================
 *  CopyPasteButton
 *
 *  Adapted from http://spaces.live.com/editorial/rayozzie/demo/liveclip/liveclipsample/techPreview.html.
 *==================================================
 */

/**
 * Creates a button and textarea for displaying structured data and copying it
 * to the clipboard.  The data is dynamically generated by the given 
 * createDataFunction parameter.
 *
 * @param {String} image an image URL to use as the background for the 
 *   generated box
 * @param {Number} width the width in pixels of the generated box
 * @param {Number} height the height in pixels of the generated box
 * @param {Function} createDataFunction a function that is called with no
 *   arguments to generate the structured data
 * @return a new DOM element
 */
SimileAjax.Graphics.createStructuredDataCopyButton = function(image, width, height, createDataFunction) {
    var div = document.createElement("div");
    div.style.position = "relative";
    div.style.display = "inline";
    div.style.width = width + "px";
    div.style.height = height + "px";
    div.style.overflow = "hidden";
    div.style.margin = "2px";
    
    if (SimileAjax.Graphics.pngIsTranslucent) {
        div.style.background = "url(" + image + ") no-repeat";
    } else {
        div.style.filter = "progid:DXImageTransform.Microsoft.AlphaImageLoader(src='" + image +"', sizingMethod='image')";
    }
    
    var style;
    if (SimileAjax.Platform.browser.isIE) {
        style = "filter:alpha(opacity=0)";
    } else {
        style = "opacity: 0";
    }
    div.innerHTML = "<textarea rows='1' autocomplete='off' value='none' style='" + style + "' />";
    
    var textarea = div.firstChild;
    textarea.style.width = width + "px";
    textarea.style.height = height + "px";
    textarea.onmousedown = function(evt) {
        evt = (evt) ? evt : ((event) ? event : null);
        if (evt.button == 2) {
            textarea.value = createDataFunction();
            textarea.select();
        }
    };
    
    return div;
};

SimileAjax.Graphics.getFontRenderingContext = function(elmt, width) {
    return new SimileAjax.Graphics._FontRenderingContext(elmt, width);
};

SimileAjax.Graphics._FontRenderingContext = function(elmt, width) {
    this._elmt = elmt;
    this._elmt.style.visibility = "hidden";
    if (typeof width == "string") {
        this._elmt.style.width = width;
    } else if (typeof width == "number") {
        this._elmt.style.width = width + "px";
    }
};

SimileAjax.Graphics._FontRenderingContext.prototype.dispose = function() {
    this._elmt = null;
};

SimileAjax.Graphics._FontRenderingContext.prototype.update = function() {
    this._elmt.innerHTML = "A";
    this._lineHeight = this._elmt.offsetHeight;
};

SimileAjax.Graphics._FontRenderingContext.prototype.computeSize = function(text) {
    this._elmt.innerHTML = text;
    return {
        width:  this._elmt.offsetWidth,
        height: this._elmt.offsetHeight
    };
};

SimileAjax.Graphics._FontRenderingContext.prototype.getLineHeight = function() {
    return this._lineHeight;
};
/**
 * @fileOverview A collection of date/time utility functions
 * @name SimileAjax.DateTime
 */

SimileAjax.DateTime = new Object();

SimileAjax.DateTime.MILLISECOND    = 0;
SimileAjax.DateTime.SECOND         = 1;
SimileAjax.DateTime.MINUTE         = 2;
SimileAjax.DateTime.HOUR           = 3;
SimileAjax.DateTime.DAY            = 4;
SimileAjax.DateTime.WEEK           = 5;
SimileAjax.DateTime.MONTH          = 6;
SimileAjax.DateTime.YEAR           = 7;
SimileAjax.DateTime.DECADE         = 8;
SimileAjax.DateTime.CENTURY        = 9;
SimileAjax.DateTime.MILLENNIUM     = 10;

SimileAjax.DateTime.EPOCH          = -1;
SimileAjax.DateTime.ERA            = -2;

/**
 * An array of unit lengths, expressed in milliseconds, of various lengths of
 * time.  The array indices are predefined and stored as properties of the
 * SimileAjax.DateTime object, e.g. SimileAjax.DateTime.YEAR.
 * @type Array
 */
SimileAjax.DateTime.gregorianUnitLengths = [];
    (function() {
        var d = SimileAjax.DateTime;
        var a = d.gregorianUnitLengths;
        
        a[d.MILLISECOND] = 1;
        a[d.SECOND]      = 1000;
        a[d.MINUTE]      = a[d.SECOND] * 60;
        a[d.HOUR]        = a[d.MINUTE] * 60;
        a[d.DAY]         = a[d.HOUR] * 24;
        a[d.WEEK]        = a[d.DAY] * 7;
        a[d.MONTH]       = a[d.DAY] * 31;
        a[d.YEAR]        = a[d.DAY] * 365;
        a[d.DECADE]      = a[d.YEAR] * 10;
        a[d.CENTURY]     = a[d.YEAR] * 100;
        a[d.MILLENNIUM]  = a[d.YEAR] * 1000;
    })();
    
SimileAjax.DateTime._dateRegexp = new RegExp(
    "^(-?)([0-9]{4})(" + [
        "(-?([0-9]{2})(-?([0-9]{2}))?)", // -month-dayOfMonth
        "(-?([0-9]{3}))",                // -dayOfYear
        "(-?W([0-9]{2})(-?([1-7]))?)"    // -Wweek-dayOfWeek
    ].join("|") + ")?$"
);
SimileAjax.DateTime._timezoneRegexp = new RegExp(
    "Z|(([-+])([0-9]{2})(:?([0-9]{2}))?)$"
);
SimileAjax.DateTime._timeRegexp = new RegExp(
    "^([0-9]{2})(:?([0-9]{2})(:?([0-9]{2})(\.([0-9]+))?)?)?$"
);

/**
 * Takes a date object and a string containing an ISO 8601 date and sets the
 * the date using information parsed from the string.  Note that this method
 * does not parse any time information.
 *
 * @param {Date} dateObject the date object to modify
 * @param {String} string an ISO 8601 string to parse
 * @return {Date} the modified date object
 */
SimileAjax.DateTime.setIso8601Date = function(dateObject, string) {
    /*
     *  This function has been adapted from dojo.date, v.0.3.0
     *  http://dojotoolkit.org/.
     */
     
    var d = string.match(SimileAjax.DateTime._dateRegexp);
    if(!d) {
        throw new Error("Invalid date string: " + string);
    }
    
    var sign = (d[1] == "-") ? -1 : 1; // BC or AD
    var year = sign * d[2];
    var month = d[5];
    var date = d[7];
    var dayofyear = d[9];
    var week = d[11];
    var dayofweek = (d[13]) ? d[13] : 1;

    dateObject.setUTCFullYear(year);
    if (dayofyear) { 
        dateObject.setUTCMonth(0);
        dateObject.setUTCDate(Number(dayofyear));
    } else if (week) {
        dateObject.setUTCMonth(0);
        dateObject.setUTCDate(1);
        var gd = dateObject.getUTCDay();
        var day =  (gd) ? gd : 7;
        var offset = Number(dayofweek) + (7 * Number(week));
        
        if (day <= 4) { 
            dateObject.setUTCDate(offset + 1 - day); 
        } else { 
            dateObject.setUTCDate(offset + 8 - day); 
        }
    } else {
        if (month) { 
            dateObject.setUTCDate(1);
            dateObject.setUTCMonth(month - 1); 
        }
        if (date) { 
            dateObject.setUTCDate(date); 
        }
    }
    
    return dateObject;
};

/**
 * Takes a date object and a string containing an ISO 8601 time and sets the
 * the time using information parsed from the string.  Note that this method
 * does not parse any date information.
 *
 * @param {Date} dateObject the date object to modify
 * @param {String} string an ISO 8601 string to parse
 * @return {Date} the modified date object
 */
SimileAjax.DateTime.setIso8601Time = function (dateObject, string) {
    /*
     *  This function has been adapted from dojo.date, v.0.3.0
     *  http://dojotoolkit.org/.
     */
    
    var d = string.match(SimileAjax.DateTime._timeRegexp);
    if(!d) {
        SimileAjax.Debug.warn("Invalid time string: " + string);
        return false;
    }
    var hours = d[1];
    var mins = Number((d[3]) ? d[3] : 0);
    var secs = (d[5]) ? d[5] : 0;
    var ms = d[7] ? (Number("0." + d[7]) * 1000) : 0;

    dateObject.setUTCHours(hours);
    dateObject.setUTCMinutes(mins);
    dateObject.setUTCSeconds(secs);
    dateObject.setUTCMilliseconds(ms);
    
    return dateObject;
};

/**
 * The timezone offset in minutes in the user's browser.
 * @type Number
 */
SimileAjax.DateTime.timezoneOffset = new Date().getTimezoneOffset();

/**
 * Takes a date object and a string containing an ISO 8601 date and time and 
 * sets the date object using information parsed from the string.
 *
 * @param {Date} dateObject the date object to modify
 * @param {String} string an ISO 8601 string to parse
 * @return {Date} the modified date object
 */
SimileAjax.DateTime.setIso8601 = function (dateObject, string){
    /*
     *  This function has been adapted from dojo.date, v.0.3.0
     *  http://dojotoolkit.org/.
     */
     
    var offset = null;
    var comps = (string.indexOf("T") == -1) ? string.split(" ") : string.split("T");
    
    SimileAjax.DateTime.setIso8601Date(dateObject, comps[0]);
    if (comps.length == 2) { 
        // first strip timezone info from the end
        var d = comps[1].match(SimileAjax.DateTime._timezoneRegexp);
        if (d) {
            if (d[0] == 'Z') {
                offset = 0;
            } else {
                offset = (Number(d[3]) * 60) + Number(d[5]);
                offset *= ((d[2] == '-') ? 1 : -1);
            }
            comps[1] = comps[1].substr(0, comps[1].length - d[0].length);
        }

        SimileAjax.DateTime.setIso8601Time(dateObject, comps[1]); 
    }
    if (offset == null) {
        offset = dateObject.getTimezoneOffset(); // local time zone if no tz info
    }
    dateObject.setTime(dateObject.getTime() + offset * 60000);
    
    return dateObject;
};

/**
 * Takes a string containing an ISO 8601 date and returns a newly instantiated
 * date object with the parsed date and time information from the string.
 *
 * @param {String} string an ISO 8601 string to parse
 * @return {Date} a new date object created from the string
 */
SimileAjax.DateTime.parseIso8601DateTime = function (string) {
    try {
        return SimileAjax.DateTime.setIso8601(new Date(0), string);
    } catch (e) {
        return null;
    }
};

/**
 * Takes a string containing a Gregorian date and time and returns a newly
 * instantiated date object with the parsed date and time information from the
 * string.  If the param is actually an instance of Date instead of a string, 
 * simply returns the given date instead.
 *
 * @param {Object} o an object, to either return or parse as a string
 * @return {Date} the date object
 */
SimileAjax.DateTime.parseGregorianDateTime = function(o) {
    if (o == null) {
        return null;
    } else if (o instanceof Date) {
        return o;
    }
    
    var s = o.toString();
    if (s.length > 0 && s.length < 8) {
        var space = s.indexOf(" ");
        if (space > 0) {
            var year = parseInt(s.substr(0, space));
            var suffix = s.substr(space + 1);
            if (suffix.toLowerCase() == "bc") {
                year = 1 - year;
            }
        } else {
            var year = parseInt(s);
        }
            
        var d = new Date(0);
        d.setUTCFullYear(year);
        
        return d;
    }
    
    try {
        return new Date(Date.parse(s));
    } catch (e) {
        return null;
    }
};

/**
 * Rounds date objects down to the nearest interval or multiple of an interval.
 * This method modifies the given date object, converting it to the given
 * timezone if specified.
 * 
 * @param {Date} date the date object to round
 * @param {Number} intervalUnit a constant, integer index specifying an 
 *   interval, e.g. SimileAjax.DateTime.HOUR
 * @param {Number} timeZone a timezone shift, given in hours
 * @param {Number} multiple a multiple of the interval to round by
 * @param {Number} firstDayOfWeek an integer specifying the first day of the
 *   week, 0 corresponds to Sunday, 1 to Monday, etc.
 */
SimileAjax.DateTime.roundDownToInterval = function(date, intervalUnit, timeZone, multiple, firstDayOfWeek) {
    var timeShift = timeZone * 
        SimileAjax.DateTime.gregorianUnitLengths[SimileAjax.DateTime.HOUR];
        
    var date2 = new Date(date.getTime() + timeShift);
    var clearInDay = function(d) {
        d.setUTCMilliseconds(0);
        d.setUTCSeconds(0);
        d.setUTCMinutes(0);
        d.setUTCHours(0);
    };
    var clearInYear = function(d) {
        clearInDay(d);
        d.setUTCDate(1);
        d.setUTCMonth(0);
    };
    
    switch(intervalUnit) {
    case SimileAjax.DateTime.MILLISECOND:
        var x = date2.getUTCMilliseconds();
        date2.setUTCMilliseconds(x - (x % multiple));
        break;
    case SimileAjax.DateTime.SECOND:
        date2.setUTCMilliseconds(0);
        
        var x = date2.getUTCSeconds();
        date2.setUTCSeconds(x - (x % multiple));
        break;
    case SimileAjax.DateTime.MINUTE:
        date2.setUTCMilliseconds(0);
        date2.setUTCSeconds(0);
        
        var x = date2.getUTCMinutes();
        date2.setTime(date2.getTime() - 
            (x % multiple) * SimileAjax.DateTime.gregorianUnitLengths[SimileAjax.DateTime.MINUTE]);
        break;
    case SimileAjax.DateTime.HOUR:
        date2.setUTCMilliseconds(0);
        date2.setUTCSeconds(0);
        date2.setUTCMinutes(0);
        
        var x = date2.getUTCHours();
        date2.setUTCHours(x - (x % multiple));
        break;
    case SimileAjax.DateTime.DAY:
        clearInDay(date2);
        break;
    case SimileAjax.DateTime.WEEK:
        clearInDay(date2);
        var d = (date2.getUTCDay() + 7 - firstDayOfWeek) % 7;
        date2.setTime(date2.getTime() - 
            d * SimileAjax.DateTime.gregorianUnitLengths[SimileAjax.DateTime.DAY]);
        break;
    case SimileAjax.DateTime.MONTH:
        clearInDay(date2);
        date2.setUTCDate(1);
        
        var x = date2.getUTCMonth();
        date2.setUTCMonth(x - (x % multiple));
        break;
    case SimileAjax.DateTime.YEAR:
        clearInYear(date2);
        
        var x = date2.getUTCFullYear();
        date2.setUTCFullYear(x - (x % multiple));
        break;
    case SimileAjax.DateTime.DECADE:
        clearInYear(date2);
        date2.setUTCFullYear(Math.floor(date2.getUTCFullYear() / 10) * 10);
        break;
    case SimileAjax.DateTime.CENTURY:
        clearInYear(date2);
        date2.setUTCFullYear(Math.floor(date2.getUTCFullYear() / 100) * 100);
        break;
    case SimileAjax.DateTime.MILLENNIUM:
        clearInYear(date2);
        date2.setUTCFullYear(Math.floor(date2.getUTCFullYear() / 1000) * 1000);
        break;
    }
    
    date.setTime(date2.getTime() - timeShift);
};

/**
 * Rounds date objects up to the nearest interval or multiple of an interval.
 * This method modifies the given date object, converting it to the given
 * timezone if specified.
 * 
 * @param {Date} date the date object to round
 * @param {Number} intervalUnit a constant, integer index specifying an 
 *   interval, e.g. SimileAjax.DateTime.HOUR
 * @param {Number} timeZone a timezone shift, given in hours
 * @param {Number} multiple a multiple of the interval to round by
 * @param {Number} firstDayOfWeek an integer specifying the first day of the
 *   week, 0 corresponds to Sunday, 1 to Monday, etc.
 * @see SimileAjax.DateTime.roundDownToInterval
 */
SimileAjax.DateTime.roundUpToInterval = function(date, intervalUnit, timeZone, multiple, firstDayOfWeek) {
    var originalTime = date.getTime();
    SimileAjax.DateTime.roundDownToInterval(date, intervalUnit, timeZone, multiple, firstDayOfWeek);
    if (date.getTime() < originalTime) {
        date.setTime(date.getTime() + 
            SimileAjax.DateTime.gregorianUnitLengths[intervalUnit] * multiple);
    }
};

/**
 * Increments a date object by a specified interval, taking into
 * consideration the timezone.
 *
 * @param {Date} date the date object to increment
 * @param {Number} intervalUnit a constant, integer index specifying an 
 *   interval, e.g. SimileAjax.DateTime.HOUR
 * @param {Number} timeZone the timezone offset in hours
 */
SimileAjax.DateTime.incrementByInterval = function(date, intervalUnit, timeZone) {
    timeZone = (typeof timeZone == 'undefined') ? 0 : timeZone;

    var timeShift = timeZone * 
        SimileAjax.DateTime.gregorianUnitLengths[SimileAjax.DateTime.HOUR];
        
    var date2 = new Date(date.getTime() + timeShift);

    switch(intervalUnit) {
    case SimileAjax.DateTime.MILLISECOND:
        date2.setTime(date2.getTime() + 1)
        break;
    case SimileAjax.DateTime.SECOND:
        date2.setTime(date2.getTime() + 1000);
        break;
    case SimileAjax.DateTime.MINUTE:
        date2.setTime(date2.getTime() + 
            SimileAjax.DateTime.gregorianUnitLengths[SimileAjax.DateTime.MINUTE]);
        break;
    case SimileAjax.DateTime.HOUR:
        date2.setTime(date2.getTime() + 
            SimileAjax.DateTime.gregorianUnitLengths[SimileAjax.DateTime.HOUR]);
        break;
    case SimileAjax.DateTime.DAY:
        date2.setUTCDate(date2.getUTCDate() + 1);
        break;
    case SimileAjax.DateTime.WEEK:
        date2.setUTCDate(date2.getUTCDate() + 7);
        break;
    case SimileAjax.DateTime.MONTH:
        date2.setUTCMonth(date2.getUTCMonth() + 1);
        break;
    case SimileAjax.DateTime.YEAR:
        date2.setUTCFullYear(date2.getUTCFullYear() + 1);
        break;
    case SimileAjax.DateTime.DECADE:
        date2.setUTCFullYear(date2.getUTCFullYear() + 10);
        break;
    case SimileAjax.DateTime.CENTURY:
        date2.setUTCFullYear(date2.getUTCFullYear() + 100);
        break;
    case SimileAjax.DateTime.MILLENNIUM:
        date2.setUTCFullYear(date2.getUTCFullYear() + 1000);
        break;
    }

    date.setTime(date2.getTime() - timeShift);
};

/**
 * Returns a new date object with the given time offset removed.
 *
 * @param {Date} date the starting date
 * @param {Number} timeZone a timezone specified in an hour offset to remove
 * @return {Date} a new date object with the offset removed
 */
SimileAjax.DateTime.removeTimeZoneOffset = function(date, timeZone) {
    return new Date(date.getTime() + 
        timeZone * SimileAjax.DateTime.gregorianUnitLengths[SimileAjax.DateTime.HOUR]);
};

/**
 * Returns the timezone of the user's browser.
 *
 * @return {Number} the timezone in the user's locale in hours
 */
SimileAjax.DateTime.getTimezone = function() {
    var d = new Date().getTimezoneOffset();
    return d / -60;
};
/*==================================================
 *  String Utility Functions and Constants
 *==================================================
 */

String.prototype.trim = function() {
    return this.replace(/^\s+|\s+$/g, '');
};

String.prototype.startsWith = function(prefix) {
    return this.length >= prefix.length && this.substr(0, prefix.length) == prefix;
};

String.prototype.endsWith = function(suffix) {
    return this.length >= suffix.length && this.substr(this.length - suffix.length) == suffix;
};

String.substitute = function(s, objects) {
    var result = "";
    var start = 0;
    while (start < s.length - 1) {
        var percent = s.indexOf("%", start);
        if (percent < 0 || percent == s.length - 1) {
            break;
        } else if (percent > start && s.charAt(percent - 1) == "\\") {
            result += s.substring(start, percent - 1) + "%";
            start = percent + 1;
        } else {
            var n = parseInt(s.charAt(percent + 1));
            if (isNaN(n) || n >= objects.length) {
                result += s.substring(start, percent + 2);
            } else {
                result += s.substring(start, percent) + objects[n].toString();
            }
            start = percent + 2;
        }
    }
    
    if (start < s.length) {
        result += s.substring(start);
    }
    return result;
};
/*==================================================
 *  HTML Utility Functions
 *==================================================
 */

SimileAjax.HTML = new Object();

SimileAjax.HTML._e2uHash = {};
(function() {
    var e2uHash = SimileAjax.HTML._e2uHash;
    e2uHash['nbsp']= '\u00A0[space]';
    e2uHash['iexcl']= '\u00A1';
    e2uHash['cent']= '\u00A2';
    e2uHash['pound']= '\u00A3';
    e2uHash['curren']= '\u00A4';
    e2uHash['yen']= '\u00A5';
    e2uHash['brvbar']= '\u00A6';
    e2uHash['sect']= '\u00A7';
    e2uHash['uml']= '\u00A8';
    e2uHash['copy']= '\u00A9';
    e2uHash['ordf']= '\u00AA';
    e2uHash['laquo']= '\u00AB';
    e2uHash['not']= '\u00AC';
    e2uHash['shy']= '\u00AD';
    e2uHash['reg']= '\u00AE';
    e2uHash['macr']= '\u00AF';
    e2uHash['deg']= '\u00B0';
    e2uHash['plusmn']= '\u00B1';
    e2uHash['sup2']= '\u00B2';
    e2uHash['sup3']= '\u00B3';
    e2uHash['acute']= '\u00B4';
    e2uHash['micro']= '\u00B5';
    e2uHash['para']= '\u00B6';
    e2uHash['middot']= '\u00B7';
    e2uHash['cedil']= '\u00B8';
    e2uHash['sup1']= '\u00B9';
    e2uHash['ordm']= '\u00BA';
    e2uHash['raquo']= '\u00BB';
    e2uHash['frac14']= '\u00BC';
    e2uHash['frac12']= '\u00BD';
    e2uHash['frac34']= '\u00BE';
    e2uHash['iquest']= '\u00BF';
    e2uHash['Agrave']= '\u00C0';
    e2uHash['Aacute']= '\u00C1';
    e2uHash['Acirc']= '\u00C2';
    e2uHash['Atilde']= '\u00C3';
    e2uHash['Auml']= '\u00C4';
    e2uHash['Aring']= '\u00C5';
    e2uHash['AElig']= '\u00C6';
    e2uHash['Ccedil']= '\u00C7';
    e2uHash['Egrave']= '\u00C8';
    e2uHash['Eacute']= '\u00C9';
    e2uHash['Ecirc']= '\u00CA';
    e2uHash['Euml']= '\u00CB';
    e2uHash['Igrave']= '\u00CC';
    e2uHash['Iacute']= '\u00CD';
    e2uHash['Icirc']= '\u00CE';
    e2uHash['Iuml']= '\u00CF';
    e2uHash['ETH']= '\u00D0';
    e2uHash['Ntilde']= '\u00D1';
    e2uHash['Ograve']= '\u00D2';
    e2uHash['Oacute']= '\u00D3';
    e2uHash['Ocirc']= '\u00D4';
    e2uHash['Otilde']= '\u00D5';
    e2uHash['Ouml']= '\u00D6';
    e2uHash['times']= '\u00D7';
    e2uHash['Oslash']= '\u00D8';
    e2uHash['Ugrave']= '\u00D9';
    e2uHash['Uacute']= '\u00DA';
    e2uHash['Ucirc']= '\u00DB';
    e2uHash['Uuml']= '\u00DC';
    e2uHash['Yacute']= '\u00DD';
    e2uHash['THORN']= '\u00DE';
    e2uHash['szlig']= '\u00DF';
    e2uHash['agrave']= '\u00E0';
    e2uHash['aacute']= '\u00E1';
    e2uHash['acirc']= '\u00E2';
    e2uHash['atilde']= '\u00E3';
    e2uHash['auml']= '\u00E4';
    e2uHash['aring']= '\u00E5';
    e2uHash['aelig']= '\u00E6';
    e2uHash['ccedil']= '\u00E7';
    e2uHash['egrave']= '\u00E8';
    e2uHash['eacute']= '\u00E9';
    e2uHash['ecirc']= '\u00EA';
    e2uHash['euml']= '\u00EB';
    e2uHash['igrave']= '\u00EC';
    e2uHash['iacute']= '\u00ED';
    e2uHash['icirc']= '\u00EE';
    e2uHash['iuml']= '\u00EF';
    e2uHash['eth']= '\u00F0';
    e2uHash['ntilde']= '\u00F1';
    e2uHash['ograve']= '\u00F2';
    e2uHash['oacute']= '\u00F3';
    e2uHash['ocirc']= '\u00F4';
    e2uHash['otilde']= '\u00F5';
    e2uHash['ouml']= '\u00F6';
    e2uHash['divide']= '\u00F7';
    e2uHash['oslash']= '\u00F8';
    e2uHash['ugrave']= '\u00F9';
    e2uHash['uacute']= '\u00FA';
    e2uHash['ucirc']= '\u00FB';
    e2uHash['uuml']= '\u00FC';
    e2uHash['yacute']= '\u00FD';
    e2uHash['thorn']= '\u00FE';
    e2uHash['yuml']= '\u00FF';
    e2uHash['quot']= '\u0022';
    e2uHash['amp']= '\u0026';
    e2uHash['lt']= '\u003C';
    e2uHash['gt']= '\u003E';
    e2uHash['OElig']= '';
    e2uHash['oelig']= '\u0153';
    e2uHash['Scaron']= '\u0160';
    e2uHash['scaron']= '\u0161';
    e2uHash['Yuml']= '\u0178';
    e2uHash['circ']= '\u02C6';
    e2uHash['tilde']= '\u02DC';
    e2uHash['ensp']= '\u2002';
    e2uHash['emsp']= '\u2003';
    e2uHash['thinsp']= '\u2009';
    e2uHash['zwnj']= '\u200C';
    e2uHash['zwj']= '\u200D';
    e2uHash['lrm']= '\u200E';
    e2uHash['rlm']= '\u200F';
    e2uHash['ndash']= '\u2013';
    e2uHash['mdash']= '\u2014';
    e2uHash['lsquo']= '\u2018';
    e2uHash['rsquo']= '\u2019';
    e2uHash['sbquo']= '\u201A';
    e2uHash['ldquo']= '\u201C';
    e2uHash['rdquo']= '\u201D';
    e2uHash['bdquo']= '\u201E';
    e2uHash['dagger']= '\u2020';
    e2uHash['Dagger']= '\u2021';
    e2uHash['permil']= '\u2030';
    e2uHash['lsaquo']= '\u2039';
    e2uHash['rsaquo']= '\u203A';
    e2uHash['euro']= '\u20AC';
    e2uHash['fnof']= '\u0192';
    e2uHash['Alpha']= '\u0391';
    e2uHash['Beta']= '\u0392';
    e2uHash['Gamma']= '\u0393';
    e2uHash['Delta']= '\u0394';
    e2uHash['Epsilon']= '\u0395';
    e2uHash['Zeta']= '\u0396';
    e2uHash['Eta']= '\u0397';
    e2uHash['Theta']= '\u0398';
    e2uHash['Iota']= '\u0399';
    e2uHash['Kappa']= '\u039A';
    e2uHash['Lambda']= '\u039B';
    e2uHash['Mu']= '\u039C';
    e2uHash['Nu']= '\u039D';
    e2uHash['Xi']= '\u039E';
    e2uHash['Omicron']= '\u039F';
    e2uHash['Pi']= '\u03A0';
    e2uHash['Rho']= '\u03A1';
    e2uHash['Sigma']= '\u03A3';
    e2uHash['Tau']= '\u03A4';
    e2uHash['Upsilon']= '\u03A5';
    e2uHash['Phi']= '\u03A6';
    e2uHash['Chi']= '\u03A7';
    e2uHash['Psi']= '\u03A8';
    e2uHash['Omega']= '\u03A9';
    e2uHash['alpha']= '\u03B1';
    e2uHash['beta']= '\u03B2';
    e2uHash['gamma']= '\u03B3';
    e2uHash['delta']= '\u03B4';
    e2uHash['epsilon']= '\u03B5';
    e2uHash['zeta']= '\u03B6';
    e2uHash['eta']= '\u03B7';
    e2uHash['theta']= '\u03B8';
    e2uHash['iota']= '\u03B9';
    e2uHash['kappa']= '\u03BA';
    e2uHash['lambda']= '\u03BB';
    e2uHash['mu']= '\u03BC';
    e2uHash['nu']= '\u03BD';
    e2uHash['xi']= '\u03BE';
    e2uHash['omicron']= '\u03BF';
    e2uHash['pi']= '\u03C0';
    e2uHash['rho']= '\u03C1';
    e2uHash['sigmaf']= '\u03C2';
    e2uHash['sigma']= '\u03C3';
    e2uHash['tau']= '\u03C4';
    e2uHash['upsilon']= '\u03C5';
    e2uHash['phi']= '\u03C6';
    e2uHash['chi']= '\u03C7';
    e2uHash['psi']= '\u03C8';
    e2uHash['omega']= '\u03C9';
    e2uHash['thetasym']= '\u03D1';
    e2uHash['upsih']= '\u03D2';
    e2uHash['piv']= '\u03D6';
    e2uHash['bull']= '\u2022';
    e2uHash['hellip']= '\u2026';
    e2uHash['prime']= '\u2032';
    e2uHash['Prime']= '\u2033';
    e2uHash['oline']= '\u203E';
    e2uHash['frasl']= '\u2044';
    e2uHash['weierp']= '\u2118';
    e2uHash['image']= '\u2111';
    e2uHash['real']= '\u211C';
    e2uHash['trade']= '\u2122';
    e2uHash['alefsym']= '\u2135';
    e2uHash['larr']= '\u2190';
    e2uHash['uarr']= '\u2191';
    e2uHash['rarr']= '\u2192';
    e2uHash['darr']= '\u2193';
    e2uHash['harr']= '\u2194';
    e2uHash['crarr']= '\u21B5';
    e2uHash['lArr']= '\u21D0';
    e2uHash['uArr']= '\u21D1';
    e2uHash['rArr']= '\u21D2';
    e2uHash['dArr']= '\u21D3';
    e2uHash['hArr']= '\u21D4';
    e2uHash['forall']= '\u2200';
    e2uHash['part']= '\u2202';
    e2uHash['exist']= '\u2203';
    e2uHash['empty']= '\u2205';
    e2uHash['nabla']= '\u2207';
    e2uHash['isin']= '\u2208';
    e2uHash['notin']= '\u2209';
    e2uHash['ni']= '\u220B';
    e2uHash['prod']= '\u220F';
    e2uHash['sum']= '\u2211';
    e2uHash['minus']= '\u2212';
    e2uHash['lowast']= '\u2217';
    e2uHash['radic']= '\u221A';
    e2uHash['prop']= '\u221D';
    e2uHash['infin']= '\u221E';
    e2uHash['ang']= '\u2220';
    e2uHash['and']= '\u2227';
    e2uHash['or']= '\u2228';
    e2uHash['cap']= '\u2229';
    e2uHash['cup']= '\u222A';
    e2uHash['int']= '\u222B';
    e2uHash['there4']= '\u2234';
    e2uHash['sim']= '\u223C';
    e2uHash['cong']= '\u2245';
    e2uHash['asymp']= '\u2248';
    e2uHash['ne']= '\u2260';
    e2uHash['equiv']= '\u2261';
    e2uHash['le']= '\u2264';
    e2uHash['ge']= '\u2265';
    e2uHash['sub']= '\u2282';
    e2uHash['sup']= '\u2283';
    e2uHash['nsub']= '\u2284';
    e2uHash['sube']= '\u2286';
    e2uHash['supe']= '\u2287';
    e2uHash['oplus']= '\u2295';
    e2uHash['otimes']= '\u2297';
    e2uHash['perp']= '\u22A5';
    e2uHash['sdot']= '\u22C5';
    e2uHash['lceil']= '\u2308';
    e2uHash['rceil']= '\u2309';
    e2uHash['lfloor']= '\u230A';
    e2uHash['rfloor']= '\u230B';
    e2uHash['lang']= '\u2329';
    e2uHash['rang']= '\u232A';
    e2uHash['loz']= '\u25CA';
    e2uHash['spades']= '\u2660';
    e2uHash['clubs']= '\u2663';
    e2uHash['hearts']= '\u2665';
    e2uHash['diams']= '\u2666'; 
})();

SimileAjax.HTML.deEntify = function(s) {
    var e2uHash = SimileAjax.HTML._e2uHash;
    
    var re = /&(\w+?);/;
    while (re.test(s)) {
        var m = s.match(re);
        s = s.replace(re, e2uHash[m[1]]);
    }
    return s;
};/**
 * A basic set (in the mathematical sense) data structure
 *
 * @constructor
 * @param {Array or SimileAjax.Set} [a] an initial collection
 */
SimileAjax.Set = function(a) {
    this._hash = {};
    this._count = 0;
    
    if (a instanceof Array) {
        for (var i = 0; i < a.length; i++) {
            this.add(a[i]);
        }
    } else if (a instanceof SimileAjax.Set) {
        this.addSet(a);
    }
}

/**
 * Adds the given object to this set, assuming there it does not already exist
 *
 * @param {Object} o the object to add
 * @return {Boolean} true if the object was added, false if not
 */
SimileAjax.Set.prototype.add = function(o) {
    if (!(o in this._hash)) {
        this._hash[o] = true;
        this._count++;
        return true;
    }
    return false;
}

/**
 * Adds each element in the given set to this set
 *
 * @param {SimileAjax.Set} set the set of elements to add
 */
SimileAjax.Set.prototype.addSet = function(set) {
    for (var o in set._hash) {
        this.add(o);
    }
}

/**
 * Removes the given element from this set
 *
 * @param {Object} o the object to remove
 * @return {Boolean} true if the object was successfully removed,
 *   false otherwise
 */
SimileAjax.Set.prototype.remove = function(o) {
    if (o in this._hash) {
        delete this._hash[o];
        this._count--;
        return true;
    }
    return false;
}

/**
 * Removes the elements in this set that correspond to the elements in the
 * given set
 *
 * @param {SimileAjax.Set} set the set of elements to remove
 */
SimileAjax.Set.prototype.removeSet = function(set) {
    for (var o in set._hash) {
        this.remove(o);
    }
}

/**
 * Removes all elements in this set that are not present in the given set, i.e.
 * modifies this set to the intersection of the two sets
 *
 * @param {SimileAjax.Set} set the set to intersect
 */
SimileAjax.Set.prototype.retainSet = function(set) {
    for (var o in this._hash) {
        if (!set.contains(o)) {
            delete this._hash[o];
            this._count--;
        }
    }
}

/**
 * Returns whether or not the given element exists in this set
 *
 * @param {SimileAjax.Set} o the object to test for
 * @return {Boolean} true if the object is present, false otherwise
 */
SimileAjax.Set.prototype.contains = function(o) {
    return (o in this._hash);
}

/**
 * Returns the number of elements in this set
 *
 * @return {Number} the number of elements in this set
 */
SimileAjax.Set.prototype.size = function() {
    return this._count;
}

/**
 * Returns the elements of this set as an array
 *
 * @return {Array} a new array containing the elements of this set
 */
SimileAjax.Set.prototype.toArray = function() {
    var a = [];
    for (var o in this._hash) {
        a.push(o);
    }
    return a;
}

/**
 * Iterates through the elements of this set, order unspecified, executing the
 * given function on each element until the function returns true
 *
 * @param {Function} f a function of form f(element)
 */
SimileAjax.Set.prototype.visit = function(f) {
    for (var o in this._hash) {
        if (f(o) == true) {
            break;
        }
    }
}

/**
 * A sorted array data structure
 *
 * @constructor
 */
SimileAjax.SortedArray = function(compare, initialArray) {
    this._a = (initialArray instanceof Array) ? initialArray : [];
    this._compare = compare;
};

SimileAjax.SortedArray.prototype.add = function(elmt) {
    var sa = this;
    var index = this.find(function(elmt2) {
        return sa._compare(elmt2, elmt);
    });
    
    if (index < this._a.length) {
        this._a.splice(index, 0, elmt);
    } else {
        this._a.push(elmt);
    }
};

SimileAjax.SortedArray.prototype.remove = function(elmt) {
    var sa = this;
    var index = this.find(function(elmt2) {
        return sa._compare(elmt2, elmt);
    });
    
    while (index < this._a.length && this._compare(this._a[index], elmt) == 0) {
        if (this._a[index] == elmt) {
            this._a.splice(index, 1);
            return true;
        } else {
            index++;
        }
    }
    return false;
};

SimileAjax.SortedArray.prototype.removeAll = function() {
    this._a = [];
};

SimileAjax.SortedArray.prototype.elementAt = function(index) {
    return this._a[index];
};

SimileAjax.SortedArray.prototype.length = function() {
    return this._a.length;
};

SimileAjax.SortedArray.prototype.find = function(compare) {
    var a = 0;
    var b = this._a.length;
    
    while (a < b) {
        var mid = Math.floor((a + b) / 2);
        var c = compare(this._a[mid]);
        if (mid == a) {
            return c < 0 ? a+1 : a;
        } else if (c < 0) {
            a = mid;
        } else {
            b = mid;
        }
    }
    return a;
};

SimileAjax.SortedArray.prototype.getFirst = function() {
    return (this._a.length > 0) ? this._a[0] : null;
};

SimileAjax.SortedArray.prototype.getLast = function() {
    return (this._a.length > 0) ? this._a[this._a.length - 1] : null;
};

/*==================================================
 *  Event Index
 *==================================================
 */

SimileAjax.EventIndex = function(unit) {
    var eventIndex = this;
    
    this._unit = (unit != null) ? unit : SimileAjax.NativeDateUnit;
    this._events = new SimileAjax.SortedArray(
        function(event1, event2) {
            return eventIndex._unit.compare(event1.getStart(), event2.getStart());
        }
    );
    this._idToEvent = {};
    this._indexed = true;
};

SimileAjax.EventIndex.prototype.getUnit = function() {
    return this._unit;
};

SimileAjax.EventIndex.prototype.getEvent = function(id) {
    return this._idToEvent[id];
};

SimileAjax.EventIndex.prototype.add = function(evt) {
    this._events.add(evt);
    this._idToEvent[evt.getID()] = evt;
    this._indexed = false;
};

SimileAjax.EventIndex.prototype.removeAll = function() {
    this._events.removeAll();
    this._idToEvent = {};
    this._indexed = false;
};

SimileAjax.EventIndex.prototype.getCount = function() {
    return this._events.length();
};

SimileAjax.EventIndex.prototype.getIterator = function(startDate, endDate) {
    if (!this._indexed) {
        this._index();
    }
    return new SimileAjax.EventIndex._Iterator(this._events, startDate, endDate, this._unit);
};

SimileAjax.EventIndex.prototype.getReverseIterator = function(startDate, endDate) {
    if (!this._indexed) {
        this._index();
    }
    return new SimileAjax.EventIndex._ReverseIterator(this._events, startDate, endDate, this._unit);
};

SimileAjax.EventIndex.prototype.getAllIterator = function() {
    return new SimileAjax.EventIndex._AllIterator(this._events);
};

SimileAjax.EventIndex.prototype.getEarliestDate = function() {
    var evt = this._events.getFirst();
    return (evt == null) ? null : evt.getStart();
};

SimileAjax.EventIndex.prototype.getLatestDate = function() {
    var evt = this._events.getLast();
    if (evt == null) {
        return null;
    }
    
    if (!this._indexed) {
        this._index();
    }
    
    var index = evt._earliestOverlapIndex;
    var date = this._events.elementAt(index).getEnd();
    for (var i = index + 1; i < this._events.length(); i++) {
        date = this._unit.later(date, this._events.elementAt(i).getEnd());
    }
    
    return date;
};

SimileAjax.EventIndex.prototype._index = function() {
    /*
     *  For each event, we want to find the earliest preceding
     *  event that overlaps with it, if any.
     */
    
    var l = this._events.length();
    for (var i = 0; i < l; i++) {
        var evt = this._events.elementAt(i);
        evt._earliestOverlapIndex = i;
    }
    
    var toIndex = 1;
    for (var i = 0; i < l; i++) {
        var evt = this._events.elementAt(i);
        var end = evt.getEnd();
        
        toIndex = Math.max(toIndex, i + 1);
        while (toIndex < l) {
            var evt2 = this._events.elementAt(toIndex);
            var start2 = evt2.getStart();
            
            if (this._unit.compare(start2, end) < 0) {
                evt2._earliestOverlapIndex = i;
                toIndex++;
            } else {
                break;
            }
        }
    }
    this._indexed = true;
};

SimileAjax.EventIndex._Iterator = function(events, startDate, endDate, unit) {
    this._events = events;
    this._startDate = startDate;
    this._endDate = endDate;
    this._unit = unit;
    
    this._currentIndex = events.find(function(evt) {
        return unit.compare(evt.getStart(), startDate);
    });
    if (this._currentIndex - 1 >= 0) {
        this._currentIndex = this._events.elementAt(this._currentIndex - 1)._earliestOverlapIndex;
    }
    this._currentIndex--;
    
    this._maxIndex = events.find(function(evt) {
        return unit.compare(evt.getStart(), endDate);
    });
    
    this._hasNext = false;
    this._next = null;
    this._findNext();
};

SimileAjax.EventIndex._Iterator.prototype = {
    hasNext: function() { return this._hasNext; },
    next: function() {
        if (this._hasNext) {
            var next = this._next;
            this._findNext();
            
            return next;
        } else {
            return null;
        }
    },
    _findNext: function() {
        var unit = this._unit;
        while ((++this._currentIndex) < this._maxIndex) {
            var evt = this._events.elementAt(this._currentIndex);
            if (unit.compare(evt.getStart(), this._endDate) < 0 &&
                unit.compare(evt.getEnd(), this._startDate) > 0) {
                
                this._next = evt;
                this._hasNext = true;
                return;
            }
        }
        this._next = null;
        this._hasNext = false;
    }
};

SimileAjax.EventIndex._ReverseIterator = function(events, startDate, endDate, unit) {
    this._events = events;
    this._startDate = startDate;
    this._endDate = endDate;
    this._unit = unit;
    
    this._minIndex = events.find(function(evt) {
        return unit.compare(evt.getStart(), startDate);
    });
    if (this._minIndex - 1 >= 0) {
        this._minIndex = this._events.elementAt(this._minIndex - 1)._earliestOverlapIndex;
    }
    
    this._maxIndex = events.find(function(evt) {
        return unit.compare(evt.getStart(), endDate);
    });
    
    this._currentIndex = this._maxIndex;
    this._hasNext = false;
    this._next = null;
    this._findNext();
};

SimileAjax.EventIndex._ReverseIterator.prototype = {
    hasNext: function() { return this._hasNext; },
    next: function() {
        if (this._hasNext) {
            var next = this._next;
            this._findNext();
            
            return next;
        } else {
            return null;
        }
    },
    _findNext: function() {
        var unit = this._unit;
        while ((--this._currentIndex) >= this._minIndex) {
            var evt = this._events.elementAt(this._currentIndex);
            if (unit.compare(evt.getStart(), this._endDate) < 0 &&
                unit.compare(evt.getEnd(), this._startDate) > 0) {
                
                this._next = evt;
                this._hasNext = true;
                return;
            }
        }
        this._next = null;
        this._hasNext = false;
    }
};

SimileAjax.EventIndex._AllIterator = function(events) {
    this._events = events;
    this._index = 0;
};

SimileAjax.EventIndex._AllIterator.prototype = {
    hasNext: function() {
        return this._index < this._events.length();
    },
    next: function() {
        return this._index < this._events.length() ?
            this._events.elementAt(this._index++) : null;
    }
};/*==================================================
 *  Default Unit
 *==================================================
 */

SimileAjax.NativeDateUnit = new Object();

SimileAjax.NativeDateUnit.makeDefaultValue = function() {
    return new Date();
};

SimileAjax.NativeDateUnit.cloneValue = function(v) {
    return new Date(v.getTime());
};

SimileAjax.NativeDateUnit.getParser = function(format) {
    if (typeof format == "string") {
        format = format.toLowerCase();
    }
    return (format == "iso8601" || format == "iso 8601") ?
        SimileAjax.DateTime.parseIso8601DateTime : 
        SimileAjax.DateTime.parseGregorianDateTime;
};

SimileAjax.NativeDateUnit.parseFromObject = function(o) {
    return SimileAjax.DateTime.parseGregorianDateTime(o);
};

SimileAjax.NativeDateUnit.toNumber = function(v) {
    return v.getTime();
};

SimileAjax.NativeDateUnit.fromNumber = function(n) {
    return new Date(n);
};

SimileAjax.NativeDateUnit.compare = function(v1, v2) {
    var n1, n2;
    if (typeof v1 == "object") {
        n1 = v1.getTime();
    } else {
        n1 = Number(v1);
    }
    if (typeof v2 == "object") {
        n2 = v2.getTime();
    } else {
        n2 = Number(v2);
    }
    
    return n1 - n2;
};

SimileAjax.NativeDateUnit.earlier = function(v1, v2) {
    return SimileAjax.NativeDateUnit.compare(v1, v2) < 0 ? v1 : v2;
};

SimileAjax.NativeDateUnit.later = function(v1, v2) {
    return SimileAjax.NativeDateUnit.compare(v1, v2) > 0 ? v1 : v2;
};

SimileAjax.NativeDateUnit.change = function(v, n) {
    return new Date(v.getTime() + n);
};

/*==================================================
 *  General, miscellaneous SimileAjax stuff
 *==================================================
 */

SimileAjax.ListenerQueue = function(wildcardHandlerName) {
    this._listeners = [];
    this._wildcardHandlerName = wildcardHandlerName;
};

SimileAjax.ListenerQueue.prototype.add = function(listener) {
    this._listeners.push(listener);
};

SimileAjax.ListenerQueue.prototype.remove = function(listener) {
    var listeners = this._listeners;
    for (var i = 0; i < listeners.length; i++) {
        if (listeners[i] == listener) {
            listeners.splice(i, 1);
            break;
        }
    }
};

SimileAjax.ListenerQueue.prototype.fire = function(handlerName, args) {
    var listeners = [].concat(this._listeners);
    for (var i = 0; i < listeners.length; i++) {
        var listener = listeners[i];
        if (handlerName in listener) {
            try {
                listener[handlerName].apply(listener, args);
            } catch (e) {
                SimileAjax.Debug.exception("Error firing event of name " + handlerName, e);
            }
        } else if (this._wildcardHandlerName != null &&
            this._wildcardHandlerName in listener) {
            try {
                listener[this._wildcardHandlerName].apply(listener, [ handlerName ]);
            } catch (e) {
                SimileAjax.Debug.exception("Error firing event of name " + handlerName + " to wildcard handler", e);
            }
        }
    }
};

/**
 * @fileOverview UI layers and window-wide dragging
 * @name SimileAjax.WindowManager
 */

/**
 *  This is a singleton that keeps track of UI layers (modal and 
 *  modeless) and enables/disables UI elements based on which layers
 *  they belong to. It also provides window-wide dragging 
 *  implementation.
 */ 
SimileAjax.WindowManager = {
    _initialized:       false,
    _listeners:         [],
    
    _draggedElement:                null,
    _draggedElementCallback:        null,
    _dropTargetHighlightElement:    null,
    _lastCoords:                    null,
    _ghostCoords:                   null,
    _draggingMode:                  "",
    _dragging:                      false,
    
    _layers:            []
};

SimileAjax.WindowManager.initialize = function() {
    if (SimileAjax.WindowManager._initialized) {
        return;
    }
    
    SimileAjax.DOM.registerEvent(document.body, "mousedown", SimileAjax.WindowManager._onBodyMouseDown);
    SimileAjax.DOM.registerEvent(document.body, "mousemove", SimileAjax.WindowManager._onBodyMouseMove);
    SimileAjax.DOM.registerEvent(document.body, "mouseup",   SimileAjax.WindowManager._onBodyMouseUp);
    SimileAjax.DOM.registerEvent(document, "keydown",       SimileAjax.WindowManager._onBodyKeyDown);
    SimileAjax.DOM.registerEvent(document, "keyup",         SimileAjax.WindowManager._onBodyKeyUp);
    
    SimileAjax.WindowManager._layers.push({index: 0});
    
    SimileAjax.WindowManager._historyListener = {
        onBeforeUndoSeveral:    function() {},
        onAfterUndoSeveral:     function() {},
        onBeforeUndo:           function() {},
        onAfterUndo:            function() {},
        
        onBeforeRedoSeveral:    function() {},
        onAfterRedoSeveral:     function() {},
        onBeforeRedo:           function() {},
        onAfterRedo:            function() {}
    };
    //SimileAjax.History.addListener(SimileAjax.WindowManager._historyListener);
    
    SimileAjax.WindowManager._initialized = true;
};

SimileAjax.WindowManager.getBaseLayer = function() {
    SimileAjax.WindowManager.initialize();
    return SimileAjax.WindowManager._layers[0];
};

SimileAjax.WindowManager.getHighestLayer = function() {
    SimileAjax.WindowManager.initialize();
    return SimileAjax.WindowManager._layers[SimileAjax.WindowManager._layers.length - 1];
};

SimileAjax.WindowManager.registerEventWithObject = function(elmt, eventName, obj, handlerName, layer) {
    SimileAjax.WindowManager.registerEvent(
        elmt, 
        eventName, 
        function(elmt2, evt, target) {
            return obj[handlerName].call(obj, elmt2, evt, target);
        },
        layer
    );
};

SimileAjax.WindowManager.registerEvent = function(elmt, eventName, handler, layer) {
    if (layer == null) {
        layer = SimileAjax.WindowManager.getHighestLayer();
    }
    
    var handler2 = function(elmt, evt, target) {
        if (SimileAjax.WindowManager._canProcessEventAtLayer(layer)) {
            SimileAjax.WindowManager._popToLayer(layer.index);
            try {
                handler(elmt, evt, target);
            } catch (e) {
                SimileAjax.Debug.exception(e);
            }
        }
        SimileAjax.DOM.cancelEvent(evt);
        return false;
    }
    
    SimileAjax.DOM.registerEvent(elmt, eventName, handler2);
};

SimileAjax.WindowManager.pushLayer = function(f, ephemeral, elmt) {
    var layer = { onPop: f, index: SimileAjax.WindowManager._layers.length, ephemeral: (ephemeral), elmt: elmt };
    SimileAjax.WindowManager._layers.push(layer);
    
    return layer;
};

SimileAjax.WindowManager.popLayer = function(layer) {
    for (var i = 1; i < SimileAjax.WindowManager._layers.length; i++) {
        if (SimileAjax.WindowManager._layers[i] == layer) {
            SimileAjax.WindowManager._popToLayer(i - 1);
            break;
        }
    }
};

SimileAjax.WindowManager.popAllLayers = function() {
    SimileAjax.WindowManager._popToLayer(0);
};

SimileAjax.WindowManager.registerForDragging = function(elmt, callback, layer) {
    SimileAjax.WindowManager.registerEvent(
        elmt, 
        "mousedown", 
        function(elmt, evt, target) {
            SimileAjax.WindowManager._handleMouseDown(elmt, evt, callback);
        }, 
        layer
    );
};

SimileAjax.WindowManager._popToLayer = function(level) {
    while (level+1 < SimileAjax.WindowManager._layers.length) {
        try {
            var layer = SimileAjax.WindowManager._layers.pop();
            if (layer.onPop != null) {
                layer.onPop();
            }
        } catch (e) {
        }
    }
};

SimileAjax.WindowManager._canProcessEventAtLayer = function(layer) {
    if (layer.index == (SimileAjax.WindowManager._layers.length - 1)) {
        return true;
    }
    for (var i = layer.index + 1; i < SimileAjax.WindowManager._layers.length; i++) {
        if (!SimileAjax.WindowManager._layers[i].ephemeral) {
            return false;
        }
    }
    return true;
};

SimileAjax.WindowManager.cancelPopups = function(evt) {
    var evtCoords = (evt) ? SimileAjax.DOM.getEventPageCoordinates(evt) : { x: -1, y: -1 };
    
    var i = SimileAjax.WindowManager._layers.length - 1;
    while (i > 0 && SimileAjax.WindowManager._layers[i].ephemeral) {
        var layer = SimileAjax.WindowManager._layers[i];
        if (layer.elmt != null) { // if event falls within main element of layer then don't cancel
            var elmt = layer.elmt;
            var elmtCoords = SimileAjax.DOM.getPageCoordinates(elmt);
            if (evtCoords.x >= elmtCoords.left && evtCoords.x < (elmtCoords.left + elmt.offsetWidth) &&
                evtCoords.y >= elmtCoords.top && evtCoords.y < (elmtCoords.top + elmt.offsetHeight)) {
                break;
            }
        }
        i--;
    }
    SimileAjax.WindowManager._popToLayer(i);
};

SimileAjax.WindowManager._onBodyMouseDown = function(elmt, evt, target) {
    if (!("eventPhase" in evt) || evt.eventPhase == evt.BUBBLING_PHASE) {
        SimileAjax.WindowManager.cancelPopups(evt);
    }
};

SimileAjax.WindowManager._handleMouseDown = function(elmt, evt, callback) {
    SimileAjax.WindowManager._draggedElement = elmt;
    SimileAjax.WindowManager._draggedElementCallback = callback;
    SimileAjax.WindowManager._lastCoords = { x: evt.clientX, y: evt.clientY };
        
    SimileAjax.DOM.cancelEvent(evt);
    return false;
};

SimileAjax.WindowManager._onBodyKeyDown = function(elmt, evt, target) {
    if (SimileAjax.WindowManager._dragging) {
        if (evt.keyCode == 27) { // esc
            SimileAjax.WindowManager._cancelDragging();
        } else if ((evt.keyCode == 17 || evt.keyCode == 16) && SimileAjax.WindowManager._draggingMode != "copy") {
            SimileAjax.WindowManager._draggingMode = "copy";
            
            var img = SimileAjax.Graphics.createTranslucentImage(SimileAjax.urlPrefix + "images/copy.png");
            img.style.position = "absolute";
            img.style.left = (SimileAjax.WindowManager._ghostCoords.left - 16) + "px";
            img.style.top = (SimileAjax.WindowManager._ghostCoords.top) + "px";
            document.body.appendChild(img);
            
            SimileAjax.WindowManager._draggingModeIndicatorElmt = img;
        }
    }
};

SimileAjax.WindowManager._onBodyKeyUp = function(elmt, evt, target) {
    if (SimileAjax.WindowManager._dragging) {
        if (evt.keyCode == 17 || evt.keyCode == 16) {
            SimileAjax.WindowManager._draggingMode = "";
            if (SimileAjax.WindowManager._draggingModeIndicatorElmt != null) {
                document.body.removeChild(SimileAjax.WindowManager._draggingModeIndicatorElmt);
                SimileAjax.WindowManager._draggingModeIndicatorElmt = null;
            }
        }
    }
};

SimileAjax.WindowManager._onBodyMouseMove = function(elmt, evt, target) {
    if (SimileAjax.WindowManager._draggedElement != null) {
        var callback = SimileAjax.WindowManager._draggedElementCallback;
        
        var lastCoords = SimileAjax.WindowManager._lastCoords;
        var diffX = evt.clientX - lastCoords.x;
        var diffY = evt.clientY - lastCoords.y;
        
        if (!SimileAjax.WindowManager._dragging) {
            if (Math.abs(diffX) > 5 || Math.abs(diffY) > 5) {
                try {
                    if ("onDragStart" in callback) {
                        callback.onDragStart();
                    }
                    
                    if ("ghost" in callback && callback.ghost) {
                        var draggedElmt = SimileAjax.WindowManager._draggedElement;
                        
                        SimileAjax.WindowManager._ghostCoords = SimileAjax.DOM.getPageCoordinates(draggedElmt);
                        SimileAjax.WindowManager._ghostCoords.left += diffX;
                        SimileAjax.WindowManager._ghostCoords.top += diffY;
                        
                        var ghostElmt = draggedElmt.cloneNode(true);
                        ghostElmt.style.position = "absolute";
                        ghostElmt.style.left = SimileAjax.WindowManager._ghostCoords.left + "px";
                        ghostElmt.style.top = SimileAjax.WindowManager._ghostCoords.top + "px";
                        ghostElmt.style.zIndex = 1000;
                        SimileAjax.Graphics.setOpacity(ghostElmt, 50);
                        
                        document.body.appendChild(ghostElmt);
                        callback._ghostElmt = ghostElmt;
                    }
                    
                    SimileAjax.WindowManager._dragging = true;
                    SimileAjax.WindowManager._lastCoords = { x: evt.clientX, y: evt.clientY };
                    
                    document.body.focus();
                } catch (e) {
                    SimileAjax.Debug.exception("WindowManager: Error handling mouse down", e);
                    SimileAjax.WindowManager._cancelDragging();
                }
            }
        } else {
            try {
                SimileAjax.WindowManager._lastCoords = { x: evt.clientX, y: evt.clientY };
                
                if ("onDragBy" in callback) {
                    callback.onDragBy(diffX, diffY);
                }
                
                if ("_ghostElmt" in callback) {
                    var ghostElmt = callback._ghostElmt;
                    
                    SimileAjax.WindowManager._ghostCoords.left += diffX;
                    SimileAjax.WindowManager._ghostCoords.top += diffY;
                    
                    ghostElmt.style.left = SimileAjax.WindowManager._ghostCoords.left + "px";
                    ghostElmt.style.top = SimileAjax.WindowManager._ghostCoords.top + "px";
                    if (SimileAjax.WindowManager._draggingModeIndicatorElmt != null) {
                        var indicatorElmt = SimileAjax.WindowManager._draggingModeIndicatorElmt;
                        
                        indicatorElmt.style.left = (SimileAjax.WindowManager._ghostCoords.left - 16) + "px";
                        indicatorElmt.style.top = SimileAjax.WindowManager._ghostCoords.top + "px";
                    }
                    
                    if ("droppable" in callback && callback.droppable) {
                        var coords = SimileAjax.DOM.getEventPageCoordinates(evt);
                        var target = SimileAjax.DOM.hittest(
                            coords.x, coords.y, 
                            [   SimileAjax.WindowManager._ghostElmt, 
                                SimileAjax.WindowManager._dropTargetHighlightElement 
                            ]
                        );
                        target = SimileAjax.WindowManager._findDropTarget(target);
                        
                        if (target != SimileAjax.WindowManager._potentialDropTarget) {
                            if (SimileAjax.WindowManager._dropTargetHighlightElement != null) {
                                document.body.removeChild(SimileAjax.WindowManager._dropTargetHighlightElement);
                                
                                SimileAjax.WindowManager._dropTargetHighlightElement = null;
                                SimileAjax.WindowManager._potentialDropTarget = null;
                            }

                            var droppable = false;
                            if (target != null) {
                                if ((!("canDropOn" in callback) || callback.canDropOn(target)) &&
                                    (!("canDrop" in target) || target.canDrop(SimileAjax.WindowManager._draggedElement))) {
                                    
                                    droppable = true;
                                }
                            }
                            
                            if (droppable) {
                                var border = 4;
                                var targetCoords = SimileAjax.DOM.getPageCoordinates(target);
                                var highlight = document.createElement("div");
                                highlight.style.border = border + "px solid yellow";
                                highlight.style.backgroundColor = "yellow";
                                highlight.style.position = "absolute";
                                highlight.style.left = targetCoords.left + "px";
                                highlight.style.top = targetCoords.top + "px";
                                highlight.style.width = (target.offsetWidth - border * 2) + "px";
                                highlight.style.height = (target.offsetHeight - border * 2) + "px";
                                SimileAjax.Graphics.setOpacity(highlight, 30);
                                document.body.appendChild(highlight);
                                
                                SimileAjax.WindowManager._potentialDropTarget = target;
                                SimileAjax.WindowManager._dropTargetHighlightElement = highlight;
                            }
                        }
                    }
                }
            } catch (e) {
                SimileAjax.Debug.exception("WindowManager: Error handling mouse move", e);
                SimileAjax.WindowManager._cancelDragging();
            }
        }
        
        SimileAjax.DOM.cancelEvent(evt);
        return false;
    }
};

SimileAjax.WindowManager._onBodyMouseUp = function(elmt, evt, target) {
    if (SimileAjax.WindowManager._draggedElement != null) {
        try {
            if (SimileAjax.WindowManager._dragging) {
                var callback = SimileAjax.WindowManager._draggedElementCallback;
                if ("onDragEnd" in callback) {
                    callback.onDragEnd();
                }
                if ("droppable" in callback && callback.droppable) {
                    var dropped = false;
                    
                    var target = SimileAjax.WindowManager._potentialDropTarget;
                    if (target != null) {
                        if ((!("canDropOn" in callback) || callback.canDropOn(target)) &&
                            (!("canDrop" in target) || target.canDrop(SimileAjax.WindowManager._draggedElement))) {
                            
                            if ("onDropOn" in callback) {
                                callback.onDropOn(target);
                            }
                            target.ondrop(SimileAjax.WindowManager._draggedElement, SimileAjax.WindowManager._draggingMode);
                            
                            dropped = true;
                        }
                    }
                    
                    if (!dropped) {
                        // TODO: do holywood explosion here
                    }
                }
            }
        } finally {
            SimileAjax.WindowManager._cancelDragging();
        }
        
        SimileAjax.DOM.cancelEvent(evt);
        return false;
    }
};

SimileAjax.WindowManager._cancelDragging = function() {
    var callback = SimileAjax.WindowManager._draggedElementCallback;
    if ("_ghostElmt" in callback) {
        var ghostElmt = callback._ghostElmt;
        document.body.removeChild(ghostElmt);
        
        delete callback._ghostElmt;
    }
    if (SimileAjax.WindowManager._dropTargetHighlightElement != null) {
        document.body.removeChild(SimileAjax.WindowManager._dropTargetHighlightElement);
        SimileAjax.WindowManager._dropTargetHighlightElement = null;
    }
    if (SimileAjax.WindowManager._draggingModeIndicatorElmt != null) {
        document.body.removeChild(SimileAjax.WindowManager._draggingModeIndicatorElmt);
        SimileAjax.WindowManager._draggingModeIndicatorElmt = null;
    }
    
    SimileAjax.WindowManager._draggedElement = null;
    SimileAjax.WindowManager._draggedElementCallback = null;
    SimileAjax.WindowManager._potentialDropTarget = null;
    SimileAjax.WindowManager._dropTargetHighlightElement = null;
    SimileAjax.WindowManager._lastCoords = null;
    SimileAjax.WindowManager._ghostCoords = null;
    SimileAjax.WindowManager._draggingMode = "";
    SimileAjax.WindowManager._dragging = false;
};

SimileAjax.WindowManager._findDropTarget = function(elmt) {
    while (elmt != null) {
        if ("ondrop" in elmt && (typeof elmt.ondrop) == "function") {
            break;
        }
        elmt = elmt.parentNode;
    }
    return elmt;
};
////////////////////////////// TIMELINE LOADER /////////////////////////////////

(function() {
    var useLocalResources = false;
    if (document.location.search.length > 0) {
        var params = document.location.search.substr(1).split("&");
        for (var i = 0; i < params.length; i++) {
            if (params[i] == "timeline-use-local-resources") {
                useLocalResources = true;
            }
        }
    };
    
    var loadMe = function() {
        if ("Timeline" in window) {
            return;
        }
        
        window.Timeline = new Object();
        window.Timeline.DateTime = window.SimileAjax.DateTime; // for backward compatibility
        
    };
    
        loadMe();
})();/*==================================================
 *  Timeline
 *==================================================
 */

Timeline.strings = {}; // localization string tables

Timeline.getDefaultLocale = function() {
    return Timeline.clientLocale;
};

Timeline.create = function(elmt, bandInfos, orientation, unit) {
    return new Timeline._Impl(elmt, bandInfos, orientation, unit);
};

Timeline.HORIZONTAL = 0;
Timeline.VERTICAL = 1;

Timeline._defaultTheme = null;

Timeline.createBandInfo = function(params) {
    var theme = ("theme" in params) ? params.theme : Timeline.getDefaultTheme();
    
    var eventSource = ("eventSource" in params) ? params.eventSource : null;
    
    var ether = new Timeline.LinearEther({ 
        centersOn:          ("date" in params) ? params.date : new Date(),
        interval:           SimileAjax.DateTime.gregorianUnitLengths[params.intervalUnit],
        pixelsPerInterval:  params.intervalPixels
    });
    
    var etherPainter = new Timeline.GregorianEtherPainter({
        unit:       params.intervalUnit, 
        multiple:   ("multiple" in params) ? params.multiple : 1,
        theme:      theme,
        align:      ("align" in params) ? params.align : undefined
    });
    
    var eventPainterParams = {
        showText:   ("showEventText" in params) ? params.showEventText : true,
        theme:      theme
    };
    if ("trackHeight" in params) {
        eventPainterParams.trackHeight = params.trackHeight;
    }
    if ("trackGap" in params) {
        eventPainterParams.trackGap = params.trackGap;
    }
    
    var layout = ("overview" in params && params.overview) ? "overview" : ("layout" in params ? params.layout : "original");
    var eventPainter;
    switch (layout) {
        case "overview" :
            eventPainter = new Timeline.OverviewEventPainter(eventPainterParams);
            break;
        case "detailed" :
            eventPainter = new Timeline.DetailedEventPainter(eventPainterParams);
            break;
        default:
            eventPainter = new Timeline.OriginalEventPainter(eventPainterParams);
    }
    
    return {   
        width:          params.width,
        eventSource:    eventSource,
        timeZone:       ("timeZone" in params) ? params.timeZone : 0,
        ether:          ether,
        etherPainter:   etherPainter,
        eventPainter:   eventPainter
    };
};

Timeline.createHotZoneBandInfo = function(params) {
    var theme = ("theme" in params) ? params.theme : Timeline.getDefaultTheme();
    
    var eventSource = ("eventSource" in params) ? params.eventSource : null;
    
    var ether = new Timeline.HotZoneEther({ 
        centersOn:          ("date" in params) ? params.date : new Date(),
        interval:           SimileAjax.DateTime.gregorianUnitLengths[params.intervalUnit],
        pixelsPerInterval:  params.intervalPixels,
        zones:              params.zones
    });
    
    var etherPainter = new Timeline.HotZoneGregorianEtherPainter({
        unit:       params.intervalUnit, 
        zones:      params.zones,
        theme:      theme,
        align:      ("align" in params) ? params.align : undefined
    });
    
    var eventPainterParams = {
        showText:   ("showEventText" in params) ? params.showEventText : true,
        theme:      theme
    };
    if ("trackHeight" in params) {
        eventPainterParams.trackHeight = params.trackHeight;
    }
    if ("trackGap" in params) {
        eventPainterParams.trackGap = params.trackGap;
    }
    
    var layout = ("overview" in params && params.overview) ? "overview" : ("layout" in params ? params.layout : "original");
    var eventPainter;
    switch (layout) {
        case "overview" :
            eventPainter = new Timeline.OverviewEventPainter(eventPainterParams);
            break;
        case "detailed" :
            eventPainter = new Timeline.DetailedEventPainter(eventPainterParams);
            break;
        default:
            eventPainter = new Timeline.OriginalEventPainter(eventPainterParams);
    }
   
    return {   
        width:          params.width,
        eventSource:    eventSource,
        timeZone:       ("timeZone" in params) ? params.timeZone : 0,
        ether:          ether,
        etherPainter:   etherPainter,
        eventPainter:   eventPainter
    };
};

Timeline.getDefaultTheme = function() {
    if (Timeline._defaultTheme == null) {
        Timeline._defaultTheme = Timeline.ClassicTheme.create(Timeline.getDefaultLocale());
    }
    return Timeline._defaultTheme;
};

Timeline.setDefaultTheme = function(theme) {
    Timeline._defaultTheme = theme;
};

Timeline.loadXML = function(url, f) {
    var fError = function(statusText, status, xmlhttp) {
        alert("Failed to load data xml from " + url + "\n" + statusText);
    };
    var fDone = function(xmlhttp) {
        var xml = xmlhttp.responseXML;
        if (!xml.documentElement && xmlhttp.responseStream) {
            xml.load(xmlhttp.responseStream);
        } 
        f(xml, url);
    };
    SimileAjax.XmlHttp.get(url, fError, fDone);
};


Timeline.loadJSON = function(url, f) {
    var fError = function(statusText, status, xmlhttp) {
        alert("Failed to load json data from " + url + "\n" + statusText);
    };
    var fDone = function(xmlhttp) {
        f(eval('(' + xmlhttp.responseText + ')'), url);
    };
    SimileAjax.XmlHttp.get(url, fError, fDone);
};


Timeline._Impl = function(elmt, bandInfos, orientation, unit) {
    SimileAjax.WindowManager.initialize();
    
    this._containerDiv = elmt;
    
    this._bandInfos = bandInfos;
    this._orientation = orientation == null ? Timeline.HORIZONTAL : orientation;
    this._unit = (unit != null) ? unit : SimileAjax.NativeDateUnit;
    
    this._initialize();
};

Timeline._Impl.prototype.dispose = function() {
    for (var i = 0; i < this._bands.length; i++) {
        this._bands[i].dispose();
    }
    this._bands = null;
    this._bandInfos = null;
    this._containerDiv.innerHTML = "";
};

Timeline._Impl.prototype.getBandCount = function() {
    return this._bands.length;
};

Timeline._Impl.prototype.getBand = function(index) {
    return this._bands[index];
};

Timeline._Impl.prototype.layout = function() {
    this._distributeWidths();
};

Timeline._Impl.prototype.paint = function() {
    for (var i = 0; i < this._bands.length; i++) {
        this._bands[i].paint();
    }
};

Timeline._Impl.prototype.getDocument = function() {
    return this._containerDiv.ownerDocument;
};

Timeline._Impl.prototype.addDiv = function(div) {
    this._containerDiv.appendChild(div);
};

Timeline._Impl.prototype.removeDiv = function(div) {
    this._containerDiv.removeChild(div);
};

Timeline._Impl.prototype.isHorizontal = function() {
    return this._orientation == Timeline.HORIZONTAL;
};

Timeline._Impl.prototype.isVertical = function() {
    return this._orientation == Timeline.VERTICAL;
};

Timeline._Impl.prototype.getPixelLength = function() {
    return this._orientation == Timeline.HORIZONTAL ? 
        this._containerDiv.offsetWidth : this._containerDiv.offsetHeight;
};

Timeline._Impl.prototype.getPixelWidth = function() {
    return this._orientation == Timeline.VERTICAL ? 
        this._containerDiv.offsetWidth : this._containerDiv.offsetHeight;
};

Timeline._Impl.prototype.getUnit = function() {
    return this._unit;
};

Timeline._Impl.prototype.loadXML = function(url, f) {
    var tl = this;
    
    
    var fError = function(statusText, status, xmlhttp) {
        alert("Failed to load data xml from " + url + "\n" + statusText);
        tl.hideLoadingMessage();
    };
    var fDone = function(xmlhttp) {
        try {
            var xml = xmlhttp.responseXML;
            if (!xml.documentElement && xmlhttp.responseStream) {
                xml.load(xmlhttp.responseStream);
            } 
            f(xml, url);
        } finally {
            tl.hideLoadingMessage();
        }
    };
    
    this.showLoadingMessage();
    window.setTimeout(function() { SimileAjax.XmlHttp.get(url, fError, fDone); }, 0);
};

Timeline._Impl.prototype.loadJSON = function(url, f) {
    var tl = this;
    
    
    var fError = function(statusText, status, xmlhttp) {
        alert("Failed to load json data from " + url + "\n" + statusText);
        tl.hideLoadingMessage();
    };
    var fDone = function(xmlhttp) {
        try {
            f(eval('(' + xmlhttp.responseText + ')'), url);
        } finally {
            tl.hideLoadingMessage();
        }
    };
    
    this.showLoadingMessage();
    window.setTimeout(function() { SimileAjax.XmlHttp.get(url, fError, fDone); }, 0);
};

Timeline._Impl.prototype._initialize = function() {
    var containerDiv = this._containerDiv;
    var doc = containerDiv.ownerDocument;
    
    containerDiv.className = 
        containerDiv.className.split(" ").concat("timeline-container").join(" ");
        
    while (containerDiv.firstChild) {
        containerDiv.removeChild(containerDiv.firstChild);
    }
    
    /*
     *  inserting copyright and link to simile
     */
    var elmtCopyright = SimileAjax.Graphics.createTranslucentImage(Timeline.urlPrefix + (this.isHorizontal() ? "images/copyright-vertical.png" : "images/copyright.png"));
    elmtCopyright.className = "timeline-copyright";
    elmtCopyright.title = "Timeline (c) SIMILE - http://simile.mit.edu/timeline/";
    SimileAjax.DOM.registerEvent(elmtCopyright, "click", function() { window.location = "http://simile.mit.edu/timeline/"; });
    containerDiv.appendChild(elmtCopyright);
    
    /*
     *  creating bands
     */
    this._bands = [];
    for (var i = 0; i < this._bandInfos.length; i++) {
        var band = new Timeline._Band(this, this._bandInfos[i], i);
        this._bands.push(band);
    }
    this._distributeWidths();
    
    /*
     *  sync'ing bands
     */
    for (var i = 0; i < this._bandInfos.length; i++) {
        var bandInfo = this._bandInfos[i];
        if ("syncWith" in bandInfo) {
            this._bands[i].setSyncWithBand(
                this._bands[bandInfo.syncWith], 
                ("highlight" in bandInfo) ? bandInfo.highlight : false
            );
        }
    }
    
    /*
     *  creating loading UI
     */
    var message = SimileAjax.Graphics.createMessageBubble(doc);
    message.containerDiv.className = "timeline-message-container";
    containerDiv.appendChild(message.containerDiv);
    
    message.contentDiv.className = "timeline-message";
    message.contentDiv.innerHTML = "<img src='" + Timeline.urlPrefix + "images/progress-running.gif' /> Loading...";
    
    this.showLoadingMessage = function() { message.containerDiv.style.display = "block"; };
    this.hideLoadingMessage = function() { message.containerDiv.style.display = "none"; };
};

Timeline._Impl.prototype._distributeWidths = function() {
    var length = this.getPixelLength();
    var width = this.getPixelWidth();
    var cumulativeWidth = 0;
    
    for (var i = 0; i < this._bands.length; i++) {
        var band = this._bands[i];
        var bandInfos = this._bandInfos[i];
        var widthString = bandInfos.width;
        
        var x = widthString.indexOf("%");
        if (x > 0) {
            var percent = parseInt(widthString.substr(0, x));
            var bandWidth = percent * width / 100;
        } else {
            var bandWidth = parseInt(widthString);
        }
        
        band.setBandShiftAndWidth(cumulativeWidth, bandWidth);
        band.setViewLength(length);
        
        cumulativeWidth += bandWidth;
    }
};

/*==================================================
 *  Band
 *==================================================
 */
Timeline._Band = function(timeline, bandInfo, index) {
    this._timeline = timeline;
    this._bandInfo = bandInfo;
    this._index = index;
    
    this._locale = ("locale" in bandInfo) ? bandInfo.locale : Timeline.getDefaultLocale();
    this._timeZone = ("timeZone" in bandInfo) ? bandInfo.timeZone : 0;
    this._labeller = ("labeller" in bandInfo) ? bandInfo.labeller : 
        (("createLabeller" in timeline.getUnit()) ?
            timeline.getUnit().createLabeller(this._locale, this._timeZone) :
            new Timeline.GregorianDateLabeller(this._locale, this._timeZone));

    this._dragging = false;
    this._changing = false;
    this._originalScrollSpeed = 5; // pixels
    this._scrollSpeed = this._originalScrollSpeed;
    this._onScrollListeners = [];
    
    var b = this;
    this._syncWithBand = null;
    this._syncWithBandHandler = function(band) {
        b._onHighlightBandScroll();
    };
    this._selectorListener = function(band) {
        b._onHighlightBandScroll();
    };
    
    /*
     *  Install a textbox to capture keyboard events
     */
    var inputDiv = this._timeline.getDocument().createElement("div");
    inputDiv.className = "timeline-band-input";
    this._timeline.addDiv(inputDiv);
    
    this._keyboardInput = document.createElement("input");
    this._keyboardInput.type = "text";
    inputDiv.appendChild(this._keyboardInput);
    SimileAjax.DOM.registerEventWithObject(this._keyboardInput, "keydown", this, "_onKeyDown");
    SimileAjax.DOM.registerEventWithObject(this._keyboardInput, "keyup", this, "_onKeyUp");
    
    /*
     *  The band's outer most div that slides with respect to the timeline's div
     */
    this._div = this._timeline.getDocument().createElement("div");
    this._div.className = "timeline-band timeline-band-" + index;
    this._timeline.addDiv(this._div);
    
    SimileAjax.DOM.registerEventWithObject(this._div, "mousedown", this, "_onMouseDown");
    SimileAjax.DOM.registerEventWithObject(this._div, "mousemove", this, "_onMouseMove");
    SimileAjax.DOM.registerEventWithObject(this._div, "mouseup", this, "_onMouseUp");
    SimileAjax.DOM.registerEventWithObject(this._div, "mouseout", this, "_onMouseOut");
    SimileAjax.DOM.registerEventWithObject(this._div, "dblclick", this, "_onDblClick");
    
    /*
     *  The inner div that contains layers
     */
    this._innerDiv = this._timeline.getDocument().createElement("div");
    this._innerDiv.className = "timeline-band-inner";
    this._div.appendChild(this._innerDiv);
    
    /*
     *  Initialize parts of the band
     */
    this._ether = bandInfo.ether;
    bandInfo.ether.initialize(timeline);
        
    this._etherPainter = bandInfo.etherPainter;
    bandInfo.etherPainter.initialize(this, timeline);
    
    this._eventSource = bandInfo.eventSource;
    if (this._eventSource) {
        this._eventListener = {
            onAddMany: function() { b._onAddMany(); },
            onClear:   function() { b._onClear(); }
        }
        this._eventSource.addListener(this._eventListener);
    }
        
    this._eventPainter = bandInfo.eventPainter;
    bandInfo.eventPainter.initialize(this, timeline);
    
    this._decorators = ("decorators" in bandInfo) ? bandInfo.decorators : [];
    for (var i = 0; i < this._decorators.length; i++) {
        this._decorators[i].initialize(this, timeline);
    }
};

Timeline._Band.SCROLL_MULTIPLES = 5;

Timeline._Band.prototype.dispose = function() {
    this.closeBubble();
    
    if (this._eventSource) {
        this._eventSource.removeListener(this._eventListener);
        this._eventListener = null;
        this._eventSource = null;
    }
    
    this._timeline = null;
    this._bandInfo = null;
    
    this._labeller = null;
    this._ether = null;
    this._etherPainter = null;
    this._eventPainter = null;
    this._decorators = null;
    
    this._onScrollListeners = null;
    this._syncWithBandHandler = null;
    this._selectorListener = null;
    
    this._div = null;
    this._innerDiv = null;
    this._keyboardInput = null;
};

Timeline._Band.prototype.addOnScrollListener = function(listener) {
    this._onScrollListeners.push(listener);
};

Timeline._Band.prototype.removeOnScrollListener = function(listener) {
    for (var i = 0; i < this._onScrollListeners.length; i++) {
        if (this._onScrollListeners[i] == listener) {
            this._onScrollListeners.splice(i, 1);
            break;
        }
    }
};

Timeline._Band.prototype.setSyncWithBand = function(band, highlight) {
    if (this._syncWithBand) {
        this._syncWithBand.removeOnScrollListener(this._syncWithBandHandler);
    }
    
    this._syncWithBand = band;
    this._syncWithBand.addOnScrollListener(this._syncWithBandHandler);
    this._highlight = highlight;
    this._positionHighlight();
};

Timeline._Band.prototype.getLocale = function() {
    return this._locale;
};

Timeline._Band.prototype.getTimeZone = function() {
    return this._timeZone;
};

Timeline._Band.prototype.getLabeller = function() {
    return this._labeller;
};

Timeline._Band.prototype.getIndex = function() {
    return this._index;
};

Timeline._Band.prototype.getEther = function() {
    return this._ether;
};

Timeline._Band.prototype.getEtherPainter = function() {
    return this._etherPainter;
};

Timeline._Band.prototype.getEventSource = function() {
    return this._eventSource;
};

Timeline._Band.prototype.getEventPainter = function() {
    return this._eventPainter;
};

Timeline._Band.prototype.layout = function() {
    this.paint();
};

Timeline._Band.prototype.paint = function() {
    this._etherPainter.paint();
    this._paintDecorators();
    this._paintEvents();
};

Timeline._Band.prototype.softLayout = function() {
    this.softPaint();
};

Timeline._Band.prototype.softPaint = function() {
    this._etherPainter.softPaint();
    this._softPaintDecorators();
    this._softPaintEvents();
};

Timeline._Band.prototype.setBandShiftAndWidth = function(shift, width) {
    var inputDiv = this._keyboardInput.parentNode;
    var middle = shift + Math.floor(width / 2);
    if (this._timeline.isHorizontal()) {
        this._div.style.top = shift + "px";
        this._div.style.height = width + "px";
        
        inputDiv.style.top = middle + "px";
        inputDiv.style.left = "-1em";
    } else {
        this._div.style.left = shift + "px";
        this._div.style.width = width + "px";
        
        inputDiv.style.left = middle + "px";
        inputDiv.style.top = "-1em";
    }
};

Timeline._Band.prototype.getViewWidth = function() {
    if (this._timeline.isHorizontal()) {
        return this._div.offsetHeight;
    } else {
        return this._div.offsetWidth;
    }
};

Timeline._Band.prototype.setViewLength = function(length) {
    this._viewLength = length;
    this._recenterDiv();
    this._onChanging();
};

Timeline._Band.prototype.getViewLength = function() {
    return this._viewLength;
};

Timeline._Band.prototype.getTotalViewLength = function() {
    return Timeline._Band.SCROLL_MULTIPLES * this._viewLength;
};

Timeline._Band.prototype.getViewOffset = function() {
    return this._viewOffset;
};

Timeline._Band.prototype.getMinDate = function() {
    return this._ether.pixelOffsetToDate(this._viewOffset);
};

Timeline._Band.prototype.getMaxDate = function() {
    return this._ether.pixelOffsetToDate(this._viewOffset + Timeline._Band.SCROLL_MULTIPLES * this._viewLength);
};

Timeline._Band.prototype.getMinVisibleDate = function() {
    return this._ether.pixelOffsetToDate(0);
};

Timeline._Band.prototype.getMaxVisibleDate = function() {
    return this._ether.pixelOffsetToDate(this._viewLength);
};

Timeline._Band.prototype.getCenterVisibleDate = function() {
    return this._ether.pixelOffsetToDate(this._viewLength / 2);
};

Timeline._Band.prototype.setMinVisibleDate = function(date) {
    if (!this._changing) {
        this._moveEther(Math.round(-this._ether.dateToPixelOffset(date)));
    }
};

Timeline._Band.prototype.setMaxVisibleDate = function(date) {
    if (!this._changing) {
        this._moveEther(Math.round(this._viewLength - this._ether.dateToPixelOffset(date)));
    }
};

Timeline._Band.prototype.setCenterVisibleDate = function(date) {
    if (!this._changing) {
        this._moveEther(Math.round(this._viewLength / 2 - this._ether.dateToPixelOffset(date)));
    }
};

Timeline._Band.prototype.dateToPixelOffset = function(date) {
    return this._ether.dateToPixelOffset(date) - this._viewOffset;
};

Timeline._Band.prototype.pixelOffsetToDate = function(pixels) {
    return this._ether.pixelOffsetToDate(pixels + this._viewOffset);
};

Timeline._Band.prototype.createLayerDiv = function(zIndex, className) {
    var div = this._timeline.getDocument().createElement("div");
    div.className = "timeline-band-layer" + (typeof className == "string" ? (" " + className) : "");
    div.style.zIndex = zIndex;
    this._innerDiv.appendChild(div);
    
    var innerDiv = this._timeline.getDocument().createElement("div");
    innerDiv.className = "timeline-band-layer-inner";
    if (SimileAjax.Platform.browser.isIE) {
        innerDiv.style.cursor = "move";
    } else {
        innerDiv.style.cursor = "-moz-grab";
    }
    div.appendChild(innerDiv);
    
    return innerDiv;
};

Timeline._Band.prototype.removeLayerDiv = function(div) {
    this._innerDiv.removeChild(div.parentNode);
};

Timeline._Band.prototype.scrollToCenter = function(date, f) {
    var pixelOffset = this._ether.dateToPixelOffset(date);
    if (pixelOffset < -this._viewLength / 2) {
        this.setCenterVisibleDate(this.pixelOffsetToDate(pixelOffset + this._viewLength));
    } else if (pixelOffset > 3 * this._viewLength / 2) {
        this.setCenterVisibleDate(this.pixelOffsetToDate(pixelOffset - this._viewLength));
    }
    this._autoScroll(Math.round(this._viewLength / 2 - this._ether.dateToPixelOffset(date)), f);
};

Timeline._Band.prototype.showBubbleForEvent = function(eventID) {
    var evt = this.getEventSource().getEvent(eventID);
    if (evt) {
        var self = this;
        this.scrollToCenter(evt.getStart(), function() {
            self._eventPainter.showBubble(evt);
        });
    }
};

Timeline._Band.prototype._onMouseDown = function(innerFrame, evt, target) {
    this.closeBubble();
    
    this._dragging = true;
    this._dragX = evt.clientX;
    this._dragY = evt.clientY;
};

Timeline._Band.prototype._onMouseMove = function(innerFrame, evt, target) {
    if (this._dragging) {
        var diffX = evt.clientX - this._dragX;
        var diffY = evt.clientY - this._dragY;
        
        this._dragX = evt.clientX;
        this._dragY = evt.clientY;
        
        this._moveEther(this._timeline.isHorizontal() ? diffX : diffY);
        this._positionHighlight();
    }
};

Timeline._Band.prototype._onMouseUp = function(innerFrame, evt, target) {
    this._dragging = false;
    this._keyboardInput.focus();
};

Timeline._Band.prototype._onMouseOut = function(innerFrame, evt, target) {
    var coords = SimileAjax.DOM.getEventRelativeCoordinates(evt, innerFrame);
    coords.x += this._viewOffset;
    if (coords.x < 0 || coords.x > innerFrame.offsetWidth ||
        coords.y < 0 || coords.y > innerFrame.offsetHeight) {
        this._dragging = false;
    }
};

Timeline._Band.prototype._onDblClick = function(innerFrame, evt, target) {
    var coords = SimileAjax.DOM.getEventRelativeCoordinates(evt, innerFrame);
    var distance = coords.x - (this._viewLength / 2 - this._viewOffset);
    
    this._autoScroll(-distance);
};

Timeline._Band.prototype._onKeyDown = function(keyboardInput, evt, target) {
    if (!this._dragging) {
        switch (evt.keyCode) {
        case 27: // ESC
            break;
        case 37: // left arrow
        case 38: // up arrow
            this._scrollSpeed = Math.min(50, Math.abs(this._scrollSpeed * 1.05));
            this._moveEther(this._scrollSpeed);
            break;
        case 39: // right arrow
        case 40: // down arrow
            this._scrollSpeed = -Math.min(50, Math.abs(this._scrollSpeed * 1.05));
            this._moveEther(this._scrollSpeed);
            break;
        default:
            return true;
        }
        this.closeBubble();
        
        SimileAjax.DOM.cancelEvent(evt);
        return false;
    }
    return true;
};

Timeline._Band.prototype._onKeyUp = function(keyboardInput, evt, target) {
    if (!this._dragging) {
        this._scrollSpeed = this._originalScrollSpeed;
        
        switch (evt.keyCode) {
        case 35: // end
            this.setCenterVisibleDate(this._eventSource.getLatestDate());
            break;
        case 36: // home
            this.setCenterVisibleDate(this._eventSource.getEarliestDate());
            break;
        case 33: // page up
            this._autoScroll(this._timeline.getPixelLength());
            break;
        case 34: // page down
            this._autoScroll(-this._timeline.getPixelLength());
            break;
        default:
            return true;
        }
        
        this.closeBubble();
        
        SimileAjax.DOM.cancelEvent(evt);
        return false;
    }
    return true;
};

Timeline._Band.prototype._autoScroll = function(distance, f) {
    var b = this;
    var a = SimileAjax.Graphics.createAnimation(
        function(abs, diff) {
            b._moveEther(diff);
        }, 
        0, 
        distance, 
        1000, 
        f
    );
    a.run();
};

Timeline._Band.prototype._moveEther = function(shift) {
    this.closeBubble();
    
    this._viewOffset += shift;
    this._ether.shiftPixels(-shift);
    if (this._timeline.isHorizontal()) {
        this._div.style.left = this._viewOffset + "px";
    } else {
        this._div.style.top = this._viewOffset + "px";
    }
    
    if (this._viewOffset > -this._viewLength * 0.5 ||
        this._viewOffset < -this._viewLength * (Timeline._Band.SCROLL_MULTIPLES - 1.5)) {
        
        this._recenterDiv();
    } else {
        this.softLayout();
    }
    
    this._onChanging();
}

Timeline._Band.prototype._onChanging = function() {
    this._changing = true;

    this._fireOnScroll();
    this._setSyncWithBandDate();
    
    this._changing = false;
};

Timeline._Band.prototype._fireOnScroll = function() {
    for (var i = 0; i < this._onScrollListeners.length; i++) {
        this._onScrollListeners[i](this);
    }
};

Timeline._Band.prototype._setSyncWithBandDate = function() {
    if (this._syncWithBand) {
        var centerDate = this._ether.pixelOffsetToDate(this.getViewLength() / 2);
        this._syncWithBand.setCenterVisibleDate(centerDate);
    }
};

Timeline._Band.prototype._onHighlightBandScroll = function() {
    if (this._syncWithBand) {
        var centerDate = this._syncWithBand.getCenterVisibleDate();
        var centerPixelOffset = this._ether.dateToPixelOffset(centerDate);
        
        this._moveEther(Math.round(this._viewLength / 2 - centerPixelOffset));
        
        if (this._highlight) {
            this._etherPainter.setHighlight(
                this._syncWithBand.getMinVisibleDate(), 
                this._syncWithBand.getMaxVisibleDate());
        }
    }
};

Timeline._Band.prototype._onAddMany = function() {
    this._paintEvents();
};

Timeline._Band.prototype._onClear = function() {
    this._paintEvents();
};

Timeline._Band.prototype._positionHighlight = function() {
    if (this._syncWithBand) {
        var startDate = this._syncWithBand.getMinVisibleDate();
        var endDate = this._syncWithBand.getMaxVisibleDate();
        
        if (this._highlight) {
            this._etherPainter.setHighlight(startDate, endDate);
        }
    }
};

Timeline._Band.prototype._recenterDiv = function() {
    this._viewOffset = -this._viewLength * (Timeline._Band.SCROLL_MULTIPLES - 1) / 2;
    if (this._timeline.isHorizontal()) {
        this._div.style.left = this._viewOffset + "px";
        this._div.style.width = (Timeline._Band.SCROLL_MULTIPLES * this._viewLength) + "px";
    } else {
        this._div.style.top = this._viewOffset + "px";
        this._div.style.height = (Timeline._Band.SCROLL_MULTIPLES * this._viewLength) + "px";
    }
    this.layout();
};

Timeline._Band.prototype._paintEvents = function() {
    this._eventPainter.paint();
};

Timeline._Band.prototype._softPaintEvents = function() {
    this._eventPainter.softPaint();
};

Timeline._Band.prototype._paintDecorators = function() {
    for (var i = 0; i < this._decorators.length; i++) {
        this._decorators[i].paint();
    }
};

Timeline._Band.prototype._softPaintDecorators = function() {
    for (var i = 0; i < this._decorators.length; i++) {
        this._decorators[i].softPaint();
    }
};

Timeline._Band.prototype.closeBubble = function() {
    SimileAjax.WindowManager.cancelPopups();
};
/*==================================================
 *  Classic Theme
 *==================================================
 */


Timeline.ClassicTheme = new Object();

Timeline.ClassicTheme.implementations = [];

Timeline.ClassicTheme.create = function(locale) {
    if (locale == null) {
        locale = Timeline.getDefaultLocale();
    }
    
    var f = Timeline.ClassicTheme.implementations[locale];
    if (f == null) {
        f = Timeline.ClassicTheme._Impl;
    }
    return new f();
};

Timeline.ClassicTheme._Impl = function() {
    this.firstDayOfWeek = 0; // Sunday
    
    this.ether = {
        backgroundColors: [
            "#EEE",
            "#DDD",
            "#CCC",
            "#AAA"
        ],
        highlightColor:     "white",
        highlightOpacity:   50,
        interval: {
            line: {
                show:       true,
                color:      "#aaa",
                opacity:    25
            },
            weekend: {
                color:      "#FFFFE0",
                opacity:    30
            },
            marker: {
                hAlign:     "Bottom",
                hBottomStyler: function(elmt) {
                    elmt.className = "timeline-ether-marker-bottom";
                },
                hBottomEmphasizedStyler: function(elmt) {
                    elmt.className = "timeline-ether-marker-bottom-emphasized";
                },
                hTopStyler: function(elmt) {
                    elmt.className = "timeline-ether-marker-top";
                },
                hTopEmphasizedStyler: function(elmt) {
                    elmt.className = "timeline-ether-marker-top-emphasized";
                },
                    
                vAlign:     "Right",
                vRightStyler: function(elmt) {
                    elmt.className = "timeline-ether-marker-right";
                },
                vRightEmphasizedStyler: function(elmt) {
                    elmt.className = "timeline-ether-marker-right-emphasized";
                },
                vLeftStyler: function(elmt) {
                    elmt.className = "timeline-ether-marker-left";
                },
                vLeftEmphasizedStyler:function(elmt) {
                    elmt.className = "timeline-ether-marker-left-emphasized";
                }
            }
        }
    };
    
    this.event = {
        track: {
            height:         10, // px
            gap:            2   // px
        },
        overviewTrack: {
            offset:     20,     // px
            tickHeight: 6,      // px
            height:     2,      // px
            gap:        1       // px
        },
        tape: {
            height:         4 // px
        },
        instant: {
            icon:              Timeline.urlPrefix + "images/dull-blue-circle.png",
            iconWidth:         10,
            iconHeight:        10,
            color:             "#58A0DC",
            impreciseColor:    "#58A0DC",
            impreciseOpacity:  20
        },
        duration: {
            color:            "#58A0DC",
            impreciseColor:   "#58A0DC",
            impreciseOpacity: 20
        },
        label: {
            backgroundColor:   "white",
            backgroundOpacity: 50,
            lineColor:         "#58A0DC",
            offsetFromLine:    3 // px
        },
        highlightColors: [
            "#FFFF00",
            "#FFC000",
            "#FF0000",
            "#0000FF"
        ],
        bubble: {
            width:          250, // px
            height:         125, // px
            titleStyler: function(elmt) {
                elmt.className = "timeline-event-bubble-title";
            },
            bodyStyler: function(elmt) {
                elmt.className = "timeline-event-bubble-body";
            },
            imageStyler: function(elmt) {
                elmt.className = "timeline-event-bubble-image";
            },
            wikiStyler: function(elmt) {
                elmt.className = "timeline-event-bubble-wiki";
            },
            timeStyler: function(elmt) {
                elmt.className = "timeline-event-bubble-time";
            }
        }
    };
};/*==================================================
 *  Linear Ether
 *==================================================
 */
 
Timeline.LinearEther = function(params) {
    this._params = params;
    this._interval = params.interval;
    this._pixelsPerInterval = params.pixelsPerInterval;
};

Timeline.LinearEther.prototype.initialize = function(timeline) {
    this._timeline = timeline;
    this._unit = timeline.getUnit();
    
    if ("startsOn" in this._params) {
        this._start = this._unit.parseFromObject(this._params.startsOn);
    } else if ("endsOn" in this._params) {
        this._start = this._unit.parseFromObject(this._params.endsOn);
        this.shiftPixels(-this._timeline.getPixelLength());
    } else if ("centersOn" in this._params) {
        this._start = this._unit.parseFromObject(this._params.centersOn);
        this.shiftPixels(-this._timeline.getPixelLength() / 2);
    } else {
        this._start = this._unit.makeDefaultValue();
        this.shiftPixels(-this._timeline.getPixelLength() / 2);
    }
};

Timeline.LinearEther.prototype.setDate = function(date) {
    this._start = this._unit.cloneValue(date);
};

Timeline.LinearEther.prototype.shiftPixels = function(pixels) {
    var numeric = this._interval * pixels / this._pixelsPerInterval;
    this._start = this._unit.change(this._start, numeric);
};

Timeline.LinearEther.prototype.dateToPixelOffset = function(date) {
    var numeric = this._unit.compare(date, this._start);
    return this._pixelsPerInterval * numeric / this._interval;
};

Timeline.LinearEther.prototype.pixelOffsetToDate = function(pixels) {
    var numeric = pixels * this._interval / this._pixelsPerInterval;
    return this._unit.change(this._start, numeric);
};

/*==================================================
 *  Hot Zone Ether
 *==================================================
 */
 
Timeline.HotZoneEther = function(params) {
    this._params = params;
    this._interval = params.interval;
    this._pixelsPerInterval = params.pixelsPerInterval;
};

Timeline.HotZoneEther.prototype.initialize = function(timeline) {
    this._timeline = timeline;
    this._unit = timeline.getUnit();
    
    this._zones = [{
        startTime:  Number.NEGATIVE_INFINITY,
        endTime:    Number.POSITIVE_INFINITY,
        magnify:    1
    }];
    var params = this._params;
    for (var i = 0; i < params.zones.length; i++) {
        var zone = params.zones[i];
        var zoneStart = this._unit.parseFromObject(zone.start);
        var zoneEnd =   this._unit.parseFromObject(zone.end);
        
        for (var j = 0; j < this._zones.length && this._unit.compare(zoneEnd, zoneStart) > 0; j++) {
            var zone2 = this._zones[j];
            
            if (this._unit.compare(zoneStart, zone2.endTime) < 0) {
                if (this._unit.compare(zoneStart, zone2.startTime) > 0) {
                    this._zones.splice(j, 0, {
                        startTime:   zone2.startTime,
                        endTime:     zoneStart,
                        magnify:     zone2.magnify
                    });
                    j++;
                    
                    zone2.startTime = zoneStart;
                }
                
                if (this._unit.compare(zoneEnd, zone2.endTime) < 0) {
                    this._zones.splice(j, 0, {
                        startTime:  zoneStart,
                        endTime:    zoneEnd,
                        magnify:    zone.magnify * zone2.magnify
                    });
                    j++;
                    
                    zone2.startTime = zoneEnd;
                    zoneStart = zoneEnd;
                } else {
                    zone2.magnify *= zone.magnify;
                    zoneStart = zone2.endTime;
                }
            } // else, try the next existing zone
        }
    }

    if ("startsOn" in this._params) {
        this._start = this._unit.parseFromObject(this._params.startsOn);
    } else if ("endsOn" in this._params) {
        this._start = this._unit.parseFromObject(this._params.endsOn);
        this.shiftPixels(-this._timeline.getPixelLength());
    } else if ("centersOn" in this._params) {
        this._start = this._unit.parseFromObject(this._params.centersOn);
        this.shiftPixels(-this._timeline.getPixelLength() / 2);
    } else {
        this._start = this._unit.makeDefaultValue();
        this.shiftPixels(-this._timeline.getPixelLength() / 2);
    }
};

Timeline.HotZoneEther.prototype.setDate = function(date) {
    this._start = this._unit.cloneValue(date);
};

Timeline.HotZoneEther.prototype.shiftPixels = function(pixels) {
    this._start = this.pixelOffsetToDate(pixels);
};

Timeline.HotZoneEther.prototype.dateToPixelOffset = function(date) {
    return this._dateDiffToPixelOffset(this._start, date);
};

Timeline.HotZoneEther.prototype.pixelOffsetToDate = function(pixels) {
    return this._pixelOffsetToDate(pixels, this._start);
};

Timeline.HotZoneEther.prototype._dateDiffToPixelOffset = function(fromDate, toDate) {
    var scale = this._getScale();
    var fromTime = fromDate;
    var toTime = toDate;
    
    var pixels = 0;
    if (this._unit.compare(fromTime, toTime) < 0) {
        var z = 0;
        while (z < this._zones.length) {
            if (this._unit.compare(fromTime, this._zones[z].endTime) < 0) {
                break;
            }
            z++;
        }
        
        while (this._unit.compare(fromTime, toTime) < 0) {
            var zone = this._zones[z];
            var toTime2 = this._unit.earlier(toTime, zone.endTime);
            
            pixels += (this._unit.compare(toTime2, fromTime) / (scale / zone.magnify));
            
            fromTime = toTime2;
            z++;
        }
    } else {
        var z = this._zones.length - 1;
        while (z >= 0) {
            if (this._unit.compare(fromTime, this._zones[z].startTime) > 0) {
                break;
            }
            z--;
        }
        
        while (this._unit.compare(fromTime, toTime) > 0) {
            var zone = this._zones[z];
            var toTime2 = this._unit.later(toTime, zone.startTime);
            
            pixels += (this._unit.compare(toTime2, fromTime) / (scale / zone.magnify));
            
            fromTime = toTime2;
            z--;
        }
    }
    return pixels;
};

Timeline.HotZoneEther.prototype._pixelOffsetToDate = function(pixels, fromDate) {
    var scale = this._getScale();
    var time = fromDate;
    if (pixels > 0) {
        var z = 0;
        while (z < this._zones.length) {
            if (this._unit.compare(time, this._zones[z].endTime) < 0) {
                break;
            }
            z++;
        }
        
        while (pixels > 0) {
            var zone = this._zones[z];
            var scale2 = scale / zone.magnify;
            
            if (zone.endTime == Number.POSITIVE_INFINITY) {
                time = this._unit.change(time, pixels * scale2);
                pixels = 0;
            } else {
                var pixels2 = this._unit.compare(zone.endTime, time) / scale2;
                if (pixels2 > pixels) {
                    time = this._unit.change(time, pixels * scale2);
                    pixels = 0;
                } else {
                    time = zone.endTime;
                    pixels -= pixels2;
                }
            }
            z++;
        }
    } else {
        var z = this._zones.length - 1;
        while (z >= 0) {
            if (this._unit.compare(time, this._zones[z].startTime) > 0) {
                break;
            }
            z--;
        }
        
        pixels = -pixels;
        while (pixels > 0) {
            var zone = this._zones[z];
            var scale2 = scale / zone.magnify;
            
            if (zone.startTime == Number.NEGATIVE_INFINITY) {
                time = this._unit.change(time, -pixels * scale2);
                pixels = 0;
            } else {
                var pixels2 = this._unit.compare(time, zone.startTime) / scale2;
                if (pixels2 > pixels) {
                    time = this._unit.change(time, -pixels * scale2);
                    pixels = 0;
                } else {
                    time = zone.startTime;
                    pixels -= pixels2;
                }
            }
            z--;
        }
    }
    return time;
};

Timeline.HotZoneEther.prototype._getScale = function() {
    return this._interval / this._pixelsPerInterval;
};
/*==================================================
 *  Gregorian Ether Painter
 *==================================================
 */
 
Timeline.GregorianEtherPainter = function(params) {
    this._params = params;
    this._theme = params.theme;
    this._unit = params.unit;
    this._multiple = ("multiple" in params) ? params.multiple : 1;
};

Timeline.GregorianEtherPainter.prototype.initialize = function(band, timeline) {
    this._band = band;
    this._timeline = timeline;
    
    this._backgroundLayer = band.createLayerDiv(0);
    this._backgroundLayer.setAttribute("name", "ether-background"); // for debugging
    this._backgroundLayer.style.background = this._theme.ether.backgroundColors[band.getIndex()];
    
    this._markerLayer = null;
    this._lineLayer = null;
    
    var align = ("align" in this._params && this._params.align != undefined) ? this._params.align : 
        this._theme.ether.interval.marker[timeline.isHorizontal() ? "hAlign" : "vAlign"];
    var showLine = ("showLine" in this._params) ? this._params.showLine : 
        this._theme.ether.interval.line.show;
        
    this._intervalMarkerLayout = new Timeline.EtherIntervalMarkerLayout(
        this._timeline, this._band, this._theme, align, showLine);
        
    this._highlight = new Timeline.EtherHighlight(
        this._timeline, this._band, this._theme, this._backgroundLayer);
}

Timeline.GregorianEtherPainter.prototype.setHighlight = function(startDate, endDate) {
    this._highlight.position(startDate, endDate);
}

Timeline.GregorianEtherPainter.prototype.paint = function() {
    if (this._markerLayer) {
        this._band.removeLayerDiv(this._markerLayer);
    }
    this._markerLayer = this._band.createLayerDiv(100);
    this._markerLayer.setAttribute("name", "ether-markers"); // for debugging
    this._markerLayer.style.display = "none";
    
    if (this._lineLayer) {
        this._band.removeLayerDiv(this._lineLayer);
    }
    this._lineLayer = this._band.createLayerDiv(1);
    this._lineLayer.setAttribute("name", "ether-lines"); // for debugging
    this._lineLayer.style.display = "none";
    
    var minDate = this._band.getMinDate();
    var maxDate = this._band.getMaxDate();
    
    var timeZone = this._band.getTimeZone();
    var labeller = this._band.getLabeller();
    
    SimileAjax.DateTime.roundDownToInterval(minDate, this._unit, timeZone, this._multiple, this._theme.firstDayOfWeek);
    
    var p = this;
    var incrementDate = function(date) {
        for (var i = 0; i < p._multiple; i++) {
            SimileAjax.DateTime.incrementByInterval(date, p._unit);
        }
    };
    
    while (minDate.getTime() < maxDate.getTime()) {
        this._intervalMarkerLayout.createIntervalMarker(
            minDate, labeller, this._unit, this._markerLayer, this._lineLayer);
            
        incrementDate(minDate);
    }
    this._markerLayer.style.display = "block";
    this._lineLayer.style.display = "block";
};

Timeline.GregorianEtherPainter.prototype.softPaint = function() {
};

/*==================================================
 *  Hot Zone Gregorian Ether Painter
 *==================================================
 */
 
Timeline.HotZoneGregorianEtherPainter = function(params) {
    this._params = params;
    this._theme = params.theme;
    
    this._zones = [{
        startTime:  Number.NEGATIVE_INFINITY,
        endTime:    Number.POSITIVE_INFINITY,
        unit:       params.unit,
        multiple:   1
    }];
    for (var i = 0; i < params.zones.length; i++) {
        var zone = params.zones[i];
        var zoneStart = SimileAjax.DateTime.parseGregorianDateTime(zone.start).getTime();
        var zoneEnd = SimileAjax.DateTime.parseGregorianDateTime(zone.end).getTime();
        
        for (var j = 0; j < this._zones.length && zoneEnd > zoneStart; j++) {
            var zone2 = this._zones[j];
            
            if (zoneStart < zone2.endTime) {
                if (zoneStart > zone2.startTime) {
                    this._zones.splice(j, 0, {
                        startTime:   zone2.startTime,
                        endTime:     zoneStart,
                        unit:        zone2.unit,
                        multiple:    zone2.multiple
                    });
                    j++;
                    
                    zone2.startTime = zoneStart;
                }
                
                if (zoneEnd < zone2.endTime) {
                    this._zones.splice(j, 0, {
                        startTime:  zoneStart,
                        endTime:    zoneEnd,
                        unit:       zone.unit,
                        multiple:   (zone.multiple) ? zone.multiple : 1
                    });
                    j++;
                    
                    zone2.startTime = zoneEnd;
                    zoneStart = zoneEnd;
                } else {
                    zone2.multiple = zone.multiple;
                    zone2.unit = zone.unit;
                    zoneStart = zone2.endTime;
                }
            } // else, try the next existing zone
        }
    }
};

Timeline.HotZoneGregorianEtherPainter.prototype.initialize = function(band, timeline) {
    this._band = band;
    this._timeline = timeline;
    
    this._backgroundLayer = band.createLayerDiv(0);
    this._backgroundLayer.setAttribute("name", "ether-background"); // for debugging
    this._backgroundLayer.style.background = this._theme.ether.backgroundColors[band.getIndex()];
    
    this._markerLayer = null;
    this._lineLayer = null;
    
    var align = ("align" in this._params && this._params.align != undefined) ? this._params.align : 
        this._theme.ether.interval.marker[timeline.isHorizontal() ? "hAlign" : "vAlign"];
    var showLine = ("showLine" in this._params) ? this._params.showLine : 
        this._theme.ether.interval.line.show;
        
    this._intervalMarkerLayout = new Timeline.EtherIntervalMarkerLayout(
        this._timeline, this._band, this._theme, align, showLine);
        
    this._highlight = new Timeline.EtherHighlight(
        this._timeline, this._band, this._theme, this._backgroundLayer);
}

Timeline.HotZoneGregorianEtherPainter.prototype.setHighlight = function(startDate, endDate) {
    this._highlight.position(startDate, endDate);
}

Timeline.HotZoneGregorianEtherPainter.prototype.paint = function() {
    if (this._markerLayer) {
        this._band.removeLayerDiv(this._markerLayer);
    }
    this._markerLayer = this._band.createLayerDiv(100);
    this._markerLayer.setAttribute("name", "ether-markers"); // for debugging
    this._markerLayer.style.display = "none";
    
    if (this._lineLayer) {
        this._band.removeLayerDiv(this._lineLayer);
    }
    this._lineLayer = this._band.createLayerDiv(1);
    this._lineLayer.setAttribute("name", "ether-lines"); // for debugging
    this._lineLayer.style.display = "none";
    
    var minDate = this._band.getMinDate();
    var maxDate = this._band.getMaxDate();
    
    var timeZone = this._band.getTimeZone();
    var labeller = this._band.getLabeller();
    
    var p = this;
    var incrementDate = function(date, zone) {
        for (var i = 0; i < zone.multiple; i++) {
            SimileAjax.DateTime.incrementByInterval(date, zone.unit);
        }
    };
    
    var zStart = 0;
    while (zStart < this._zones.length) {
        if (minDate.getTime() < this._zones[zStart].endTime) {
            break;
        }
        zStart++;
    }
    var zEnd = this._zones.length - 1;
    while (zEnd >= 0) {
        if (maxDate.getTime() > this._zones[zEnd].startTime) {
            break;
        }
        zEnd--;
    }
    
    for (var z = zStart; z <= zEnd; z++) {
        var zone = this._zones[z];
        
        var minDate2 = new Date(Math.max(minDate.getTime(), zone.startTime));
        var maxDate2 = new Date(Math.min(maxDate.getTime(), zone.endTime));
        
        SimileAjax.DateTime.roundDownToInterval(minDate2, zone.unit, timeZone, zone.multiple, this._theme.firstDayOfWeek);
        SimileAjax.DateTime.roundUpToInterval(maxDate2, zone.unit, timeZone, zone.multiple, this._theme.firstDayOfWeek);
        
        while (minDate2.getTime() < maxDate2.getTime()) {
            this._intervalMarkerLayout.createIntervalMarker(
                minDate2, labeller, zone.unit, this._markerLayer, this._lineLayer);
                
            incrementDate(minDate2, zone);
        }
    }
    this._markerLayer.style.display = "block";
    this._lineLayer.style.display = "block";
};

Timeline.HotZoneGregorianEtherPainter.prototype.softPaint = function() {
};

/*==================================================
 *  Year Count Ether Painter
 *==================================================
 */
 
Timeline.YearCountEtherPainter = function(params) {
    this._params = params;
    this._theme = params.theme;
    this._startDate = SimileAjax.DateTime.parseGregorianDateTime(params.startDate);
    this._multiple = ("multiple" in params) ? params.multiple : 1;
};

Timeline.YearCountEtherPainter.prototype.initialize = function(band, timeline) {
    this._band = band;
    this._timeline = timeline;
    
    this._backgroundLayer = band.createLayerDiv(0);
    this._backgroundLayer.setAttribute("name", "ether-background"); // for debugging
    this._backgroundLayer.style.background = this._theme.ether.backgroundColors[band.getIndex()];
    
    this._markerLayer = null;
    this._lineLayer = null;
    
    var align = ("align" in this._params) ? this._params.align : 
        this._theme.ether.interval.marker[timeline.isHorizontal() ? "hAlign" : "vAlign"];
    var showLine = ("showLine" in this._params) ? this._params.showLine : 
        this._theme.ether.interval.line.show;
        
    this._intervalMarkerLayout = new Timeline.EtherIntervalMarkerLayout(
        this._timeline, this._band, this._theme, align, showLine);
        
    this._highlight = new Timeline.EtherHighlight(
        this._timeline, this._band, this._theme, this._backgroundLayer);
};

Timeline.YearCountEtherPainter.prototype.setHighlight = function(startDate, endDate) {
    this._highlight.position(startDate, endDate);
};

Timeline.YearCountEtherPainter.prototype.paint = function() {
    if (this._markerLayer) {
        this._band.removeLayerDiv(this._markerLayer);
    }
    this._markerLayer = this._band.createLayerDiv(100);
    this._markerLayer.setAttribute("name", "ether-markers"); // for debugging
    this._markerLayer.style.display = "none";
    
    if (this._lineLayer) {
        this._band.removeLayerDiv(this._lineLayer);
    }
    this._lineLayer = this._band.createLayerDiv(1);
    this._lineLayer.setAttribute("name", "ether-lines"); // for debugging
    this._lineLayer.style.display = "none";
    
    var minDate = new Date(this._startDate.getTime());
    var maxDate = this._band.getMaxDate();
    var yearDiff = this._band.getMinDate().getUTCFullYear() - this._startDate.getUTCFullYear();
    minDate.setUTCFullYear(this._band.getMinDate().getUTCFullYear() - yearDiff % this._multiple);
    
    var p = this;
    var incrementDate = function(date) {
        for (var i = 0; i < p._multiple; i++) {
            SimileAjax.DateTime.incrementByInterval(date, SimileAjax.DateTime.YEAR);
        }
    };
    var labeller = {
        labelInterval: function(date, intervalUnit) {
            var diff = date.getUTCFullYear() - p._startDate.getUTCFullYear();
            return {
                text: diff,
                emphasized: diff == 0
            };
        }
    };
    
    while (minDate.getTime() < maxDate.getTime()) {
        this._intervalMarkerLayout.createIntervalMarker(
            minDate, labeller, SimileAjax.DateTime.YEAR, this._markerLayer, this._lineLayer);
            
        incrementDate(minDate);
    }
    this._markerLayer.style.display = "block";
    this._lineLayer.style.display = "block";
};

Timeline.YearCountEtherPainter.prototype.softPaint = function() {
};

/*==================================================
 *  Quarterly Ether Painter
 *==================================================
 */
 
Timeline.QuarterlyEtherPainter = function(params) {
    this._params = params;
    this._theme = params.theme;
    this._startDate = SimileAjax.DateTime.parseGregorianDateTime(params.startDate);
};

Timeline.QuarterlyEtherPainter.prototype.initialize = function(band, timeline) {
    this._band = band;
    this._timeline = timeline;
    
    this._backgroundLayer = band.createLayerDiv(0);
    this._backgroundLayer.setAttribute("name", "ether-background"); // for debugging
    this._backgroundLayer.style.background = this._theme.ether.backgroundColors[band.getIndex()];
    
    this._markerLayer = null;
    this._lineLayer = null;
    
    var align = ("align" in this._params) ? this._params.align : 
        this._theme.ether.interval.marker[timeline.isHorizontal() ? "hAlign" : "vAlign"];
    var showLine = ("showLine" in this._params) ? this._params.showLine : 
        this._theme.ether.interval.line.show;
        
    this._intervalMarkerLayout = new Timeline.EtherIntervalMarkerLayout(
        this._timeline, this._band, this._theme, align, showLine);
        
    this._highlight = new Timeline.EtherHighlight(
        this._timeline, this._band, this._theme, this._backgroundLayer);
};

Timeline.QuarterlyEtherPainter.prototype.setHighlight = function(startDate, endDate) {
    this._highlight.position(startDate, endDate);
};

Timeline.QuarterlyEtherPainter.prototype.paint = function() {
    if (this._markerLayer) {
        this._band.removeLayerDiv(this._markerLayer);
    }
    this._markerLayer = this._band.createLayerDiv(100);
    this._markerLayer.setAttribute("name", "ether-markers"); // for debugging
    this._markerLayer.style.display = "none";
    
    if (this._lineLayer) {
        this._band.removeLayerDiv(this._lineLayer);
    }
    this._lineLayer = this._band.createLayerDiv(1);
    this._lineLayer.setAttribute("name", "ether-lines"); // for debugging
    this._lineLayer.style.display = "none";
    
    var minDate = new Date(0);
    var maxDate = this._band.getMaxDate();
    
    minDate.setUTCFullYear(Math.max(this._startDate.getUTCFullYear(), this._band.getMinDate().getUTCFullYear()));
    minDate.setUTCMonth(this._startDate.getUTCMonth());
    
    var p = this;
    var incrementDate = function(date) {
        date.setUTCMonth(date.getUTCMonth() + 3);
    };
    var labeller = {
        labelInterval: function(date, intervalUnit) {
            var quarters = (4 + (date.getUTCMonth() - p._startDate.getUTCMonth()) / 3) % 4;
            if (quarters != 0) {
                return { text: "Q" + (quarters + 1), emphasized: false };
            } else {
                return { text: "Y" + (date.getUTCFullYear() - p._startDate.getUTCFullYear() + 1), emphasized: true };
            }
        }
    };
    
    while (minDate.getTime() < maxDate.getTime()) {
        this._intervalMarkerLayout.createIntervalMarker(
            minDate, labeller, SimileAjax.DateTime.YEAR, this._markerLayer, this._lineLayer);
            
        incrementDate(minDate);
    }
    this._markerLayer.style.display = "block";
    this._lineLayer.style.display = "block";
};

Timeline.QuarterlyEtherPainter.prototype.softPaint = function() {
};

/*==================================================
 *  Ether Interval Marker Layout
 *==================================================
 */
 
Timeline.EtherIntervalMarkerLayout = function(timeline, band, theme, align, showLine) {
    var horizontal = timeline.isHorizontal();
    if (horizontal) {
        if (align == "Top") {
            this.positionDiv = function(div, offset) {
                div.style.left = offset + "px";
                div.style.top = "0px";
            };
        } else {
            this.positionDiv = function(div, offset) {
                div.style.left = offset + "px";
                div.style.bottom = "0px";
            };
        }
    } else {
        if (align == "Left") {
            this.positionDiv = function(div, offset) {
                div.style.top = offset + "px";
                div.style.left = "0px";
            };
        } else {
            this.positionDiv = function(div, offset) {
                div.style.top = offset + "px";
                div.style.right = "0px";
            };
        }
    }
    
    var markerTheme = theme.ether.interval.marker;
    var lineTheme = theme.ether.interval.line;
    var weekendTheme = theme.ether.interval.weekend;
    
    var stylePrefix = (horizontal ? "h" : "v") + align;
    var labelStyler = markerTheme[stylePrefix + "Styler"];
    var emphasizedLabelStyler = markerTheme[stylePrefix + "EmphasizedStyler"];
    var day = SimileAjax.DateTime.gregorianUnitLengths[SimileAjax.DateTime.DAY];
    
    this.createIntervalMarker = function(date, labeller, unit, markerDiv, lineDiv) {
        var offset = Math.round(band.dateToPixelOffset(date));

        if (showLine && unit != SimileAjax.DateTime.WEEK) {
            var divLine = timeline.getDocument().createElement("div");
            divLine.style.position = "absolute";
            
            if (lineTheme.opacity < 100) {
                SimileAjax.Graphics.setOpacity(divLine, lineTheme.opacity);
            }
            
            if (horizontal) {
                divLine.style.borderLeft = "1px solid " + lineTheme.color;
                divLine.style.left = offset + "px";
                divLine.style.width = "1px";
                divLine.style.top = "0px";
                divLine.style.height = "100%";
            } else {
                divLine.style.borderTop = "1px solid " + lineTheme.color;
                divLine.style.top = offset + "px";
                divLine.style.height = "1px";
                divLine.style.left = "0px";
                divLine.style.width = "100%";
            }
            lineDiv.appendChild(divLine);
        }
        if (unit == SimileAjax.DateTime.WEEK) {
            var firstDayOfWeek = theme.firstDayOfWeek;
            
            var saturday = new Date(date.getTime() + (6 - firstDayOfWeek - 7) * day);
            var monday = new Date(saturday.getTime() + 2 * day);
            
            var saturdayPixel = Math.round(band.dateToPixelOffset(saturday));
            var mondayPixel = Math.round(band.dateToPixelOffset(monday));
            var length = Math.max(1, mondayPixel - saturdayPixel);
            
            var divWeekend = timeline.getDocument().createElement("div");
            divWeekend.style.position = "absolute";
            
            divWeekend.style.background = weekendTheme.color;
            if (weekendTheme.opacity < 100) {
                SimileAjax.Graphics.setOpacity(divWeekend, weekendTheme.opacity);
            }
            
            if (horizontal) {
                divWeekend.style.left = saturdayPixel + "px";
                divWeekend.style.width = length + "px";
                divWeekend.style.top = "0px";
                divWeekend.style.height = "100%";
            } else {
                divWeekend.style.top = saturdayPixel + "px";
                divWeekend.style.height = length + "px";
                divWeekend.style.left = "0px";
                divWeekend.style.width = "100%";
            }
            lineDiv.appendChild(divWeekend);
        }
        
        var label = labeller.labelInterval(date, unit);
        
        var div = timeline.getDocument().createElement("div");
        div.innerHTML = label.text;
        div.style.position = "absolute";
        (label.emphasized ? emphasizedLabelStyler : labelStyler)(div);
        
        this.positionDiv(div, offset);
        markerDiv.appendChild(div);
        
        return div;
    };
};

/*==================================================
 *  Ether Highlight Layout
 *==================================================
 */
 
Timeline.EtherHighlight = function(timeline, band, theme, backgroundLayer) {
    var horizontal = timeline.isHorizontal();
    
    this._highlightDiv = null;
    this._createHighlightDiv = function() {
        if (this._highlightDiv == null) {
            this._highlightDiv = timeline.getDocument().createElement("div");
            this._highlightDiv.setAttribute("name", "ether-highlight"); // for debugging
            this._highlightDiv.style.position = "absolute";
            this._highlightDiv.style.background = theme.ether.highlightColor;
            
            var opacity = theme.ether.highlightOpacity;
            if (opacity < 100) {
                SimileAjax.Graphics.setOpacity(this._highlightDiv, opacity);
            }
            
            backgroundLayer.appendChild(this._highlightDiv);
        }
    }
    
    this.position = function(startDate, endDate) {
        this._createHighlightDiv();
        
        var startPixel = Math.round(band.dateToPixelOffset(startDate));
        var endPixel = Math.round(band.dateToPixelOffset(endDate));
        var length = Math.max(endPixel - startPixel, 3);
        if (horizontal) {
            this._highlightDiv.style.left = startPixel + "px";
            this._highlightDiv.style.width = length + "px";
            this._highlightDiv.style.top = "2px";
            this._highlightDiv.style.height = (band.getViewWidth() - 4) + "px";
        } else {
            this._highlightDiv.style.top = startPixel + "px";
            this._highlightDiv.style.height = length + "px";
            this._highlightDiv.style.left = "2px";
            this._highlightDiv.style.width = (band.getViewWidth() - 4) + "px";
        }
    }
};

/*==================================================
 *  Gregorian Date Labeller
 *==================================================
 */

Timeline.GregorianDateLabeller = function(locale, timeZone) {
    this._locale = locale;
    this._timeZone = timeZone;
};

Timeline.GregorianDateLabeller.monthNames = [];
Timeline.GregorianDateLabeller.dayNames = [];
Timeline.GregorianDateLabeller.labelIntervalFunctions = [];

Timeline.GregorianDateLabeller.getMonthName = function(month, locale) {
    return Timeline.GregorianDateLabeller.monthNames[locale][month];
};

Timeline.GregorianDateLabeller.prototype.labelInterval = function(date, intervalUnit) {
    var f = Timeline.GregorianDateLabeller.labelIntervalFunctions[this._locale];
    if (f == null) {
        f = Timeline.GregorianDateLabeller.prototype.defaultLabelInterval;
    }
    return f.call(this, date, intervalUnit);
};

Timeline.GregorianDateLabeller.prototype.labelPrecise = function(date) {
    return SimileAjax.DateTime.removeTimeZoneOffset(
        date, 
        this._timeZone //+ (new Date().getTimezoneOffset() / 60)
    ).toUTCString();
};

Timeline.GregorianDateLabeller.prototype.defaultLabelInterval = function(date, intervalUnit) {
    var text;
    var emphasized = false;
    
    date = SimileAjax.DateTime.removeTimeZoneOffset(date, this._timeZone);
    
    switch(intervalUnit) {
    case SimileAjax.DateTime.MILLISECOND:
        text = date.getUTCMilliseconds();
        break;
    case SimileAjax.DateTime.SECOND:
        text = date.getUTCSeconds();
        break;
    case SimileAjax.DateTime.MINUTE:
        var m = date.getUTCMinutes();
        if (m == 0) {
            text = date.getUTCHours() + ":00";
            emphasized = true;
        } else {
            text = m;
        }
        break;
    case SimileAjax.DateTime.HOUR:
        text = date.getUTCHours() + "hr";
        break;
    case SimileAjax.DateTime.DAY:
        text = Timeline.GregorianDateLabeller.getMonthName(date.getUTCMonth(), this._locale) + " " + date.getUTCDate();
        break;
    case SimileAjax.DateTime.WEEK:
        text = Timeline.GregorianDateLabeller.getMonthName(date.getUTCMonth(), this._locale) + " " + date.getUTCDate();
        break;
    case SimileAjax.DateTime.MONTH:
        var m = date.getUTCMonth();
        if (m != 0) {
            text = Timeline.GregorianDateLabeller.getMonthName(m, this._locale);
            break;
        } // else, fall through
    case SimileAjax.DateTime.YEAR:
    case SimileAjax.DateTime.DECADE:
    case SimileAjax.DateTime.CENTURY:
    case SimileAjax.DateTime.MILLENNIUM:
        var y = date.getUTCFullYear();
        if (y > 0) {
            text = date.getUTCFullYear();
        } else {
            text = (1 - y) + "BC";
        }
        emphasized = 
            (intervalUnit == SimileAjax.DateTime.MONTH) ||
            (intervalUnit == SimileAjax.DateTime.DECADE && y % 100 == 0) || 
            (intervalUnit == SimileAjax.DateTime.CENTURY && y % 1000 == 0);
        break;
    default:
        text = date.toUTCString();
    }
    return { text: text, emphasized: emphasized };
}

/*==================================================
 *  Default Event Source
 *==================================================
 */


Timeline.DefaultEventSource = function(eventIndex) {
    this._events = (eventIndex instanceof Object) ? eventIndex : new SimileAjax.EventIndex();
    this._listeners = [];
};

Timeline.DefaultEventSource.prototype.addListener = function(listener) {
    this._listeners.push(listener);
};

Timeline.DefaultEventSource.prototype.removeListener = function(listener) {
    for (var i = 0; i < this._listeners.length; i++) {
        if (this._listeners[i] == listener) {
            this._listeners.splice(i, 1);
            break;
        }
    }
};

Timeline.DefaultEventSource.prototype.loadXML = function(xml, url) {
    var base = this._getBaseURL(url);
    
    var wikiURL = xml.documentElement.getAttribute("wiki-url");
    var wikiSection = xml.documentElement.getAttribute("wiki-section");

    var dateTimeFormat = xml.documentElement.getAttribute("date-time-format");
    var parseDateTimeFunction = this._events.getUnit().getParser(dateTimeFormat);

    var node = xml.documentElement.firstChild;
    var added = false;
    while (node != null) {
        if (node.nodeType == 1) {
            var description = "";
            if (node.firstChild != null && node.firstChild.nodeType == 3) {
                description = node.firstChild.nodeValue;
            }
            var evt = new Timeline.DefaultEventSource.Event(
                node.getAttribute("id"),
                parseDateTimeFunction(node.getAttribute("start")),
                parseDateTimeFunction(node.getAttribute("end")),
                parseDateTimeFunction(node.getAttribute("latestStart")),
                parseDateTimeFunction(node.getAttribute("earliestEnd")),
                node.getAttribute("isDuration") != "true",
                node.getAttribute("title"),
                description,
                this._resolveRelativeURL(node.getAttribute("image"), base),
                this._resolveRelativeURL(node.getAttribute("link"), base),
                this._resolveRelativeURL(node.getAttribute("icon"), base),
                node.getAttribute("color"),
                node.getAttribute("textColor")
            );
            evt._node = node;
            evt.getProperty = function(name) {
                return this._node.getAttribute(name);
            };
            evt.setWikiInfo(wikiURL, wikiSection);
            
            this._events.add(evt);
            
            added = true;
        }
        node = node.nextSibling;
    }

    if (added) {
        this._fire("onAddMany", []);
    }
};


Timeline.DefaultEventSource.prototype.loadJSON = function(data, url) {
    var base = this._getBaseURL(url);
    var added = false;  
    if (data && data.events){
        var wikiURL = ("wikiURL" in data) ? data.wikiURL : null;
        var wikiSection = ("wikiSection" in data) ? data.wikiSection : null;
    
        var dateTimeFormat = ("dateTimeFormat" in data) ? data.dateTimeFormat : null;
        var parseDateTimeFunction = this._events.getUnit().getParser(dateTimeFormat);
       
        for (var i=0; i < data.events.length; i++){
            var event = data.events[i];
            var evt = new Timeline.DefaultEventSource.Event(
                ("id" in event) ? event.id : undefined,
                parseDateTimeFunction(event.start),
                parseDateTimeFunction(event.end),
                parseDateTimeFunction(event.latestStart),
                parseDateTimeFunction(event.earliestEnd),
                event.isDuration || false,
                event.title,
                event.description,
                this._resolveRelativeURL(event.image, base),
                this._resolveRelativeURL(event.link, base),
                this._resolveRelativeURL(event.icon, base),
                event.color,
                event.textColor
            );
            evt._obj = event;
            evt.getProperty = function(name) {
                return this._obj[name];
            };
            evt.setWikiInfo(wikiURL, wikiSection);

            this._events.add(evt);
            added = true;
        }
    }
   
    if (added) {
        this._fire("onAddMany", []);
    }
};

/*
 *  Contributed by Morten Frederiksen, http://www.wasab.dk/morten/
 */
Timeline.DefaultEventSource.prototype.loadSPARQL = function(xml, url) {
    var base = this._getBaseURL(url);
    
    var dateTimeFormat = 'iso8601';
    var parseDateTimeFunction = this._events.getUnit().getParser(dateTimeFormat);

    if (xml == null) {
        return;
    }
    
    /*
     *  Find <results> tag
     */
    var node = xml.documentElement.firstChild;
    while (node != null && (node.nodeType != 1 || node.nodeName != 'results')) {
        node = node.nextSibling;
    }
    
    var wikiURL = null;
    var wikiSection = null;
    if (node != null) {
        wikiURL = node.getAttribute("wiki-url");
        wikiSection = node.getAttribute("wiki-section");
        
        node = node.firstChild;
    }
    
    var added = false;
    while (node != null) {
        if (node.nodeType == 1) {
            var bindings = { };
            var binding = node.firstChild;
            while (binding != null) {
                if (binding.nodeType == 1 && 
                    binding.firstChild != null && 
                    binding.firstChild.nodeType == 1 && 
                    binding.firstChild.firstChild != null && 
                    binding.firstChild.firstChild.nodeType == 3) {
                    bindings[binding.getAttribute('name')] = binding.firstChild.firstChild.nodeValue;
                }
                binding = binding.nextSibling;
            }
            
            if (bindings["start"] == null && bindings["date"] != null) {
                bindings["start"] = bindings["date"];
            }
            
            var evt = new Timeline.DefaultEventSource.Event(
                bindings["id"],
                parseDateTimeFunction(bindings["start"]),
                parseDateTimeFunction(bindings["end"]),
                parseDateTimeFunction(bindings["latestStart"]),
                parseDateTimeFunction(bindings["earliestEnd"]),
                bindings["isDuration"] != "true",
                bindings["title"],
                bindings["description"],
                this._resolveRelativeURL(bindings["image"], base),
                this._resolveRelativeURL(bindings["link"], base),
                this._resolveRelativeURL(bindings["icon"], base),
                bindings["color"],
                bindings["textColor"]
            );
            evt._bindings = bindings;
            evt.getProperty = function(name) {
                return this._bindings[name];
            };
            evt.setWikiInfo(wikiURL, wikiSection);
            
            this._events.add(evt);
            added = true;
        }
        node = node.nextSibling;
    }

    if (added) {
        this._fire("onAddMany", []);
    }
};

Timeline.DefaultEventSource.prototype.add = function(evt) {
    this._events.add(evt);
    this._fire("onAddOne", [evt]);
};

Timeline.DefaultEventSource.prototype.addMany = function(events) {
    for (var i = 0; i < events.length; i++) {
        this._events.add(events[i]);
    }
    this._fire("onAddMany", []);
};

Timeline.DefaultEventSource.prototype.clear = function() {
    this._events.removeAll();
    this._fire("onClear", []);
};

Timeline.DefaultEventSource.prototype.getEvent = function(id) {
    return this._events.getEvent(id);
};

Timeline.DefaultEventSource.prototype.getEventIterator = function(startDate, endDate) {
    return this._events.getIterator(startDate, endDate);
};

Timeline.DefaultEventSource.prototype.getEventReverseIterator = function(startDate, endDate) {
    return this._events.getReverseIterator(startDate, endDate);
};

Timeline.DefaultEventSource.prototype.getAllEventIterator = function() {
    return this._events.getAllIterator();
};

Timeline.DefaultEventSource.prototype.getCount = function() {
    return this._events.getCount();
};

Timeline.DefaultEventSource.prototype.getEarliestDate = function() {
    return this._events.getEarliestDate();
};

Timeline.DefaultEventSource.prototype.getLatestDate = function() {
    return this._events.getLatestDate();
};

Timeline.DefaultEventSource.prototype._fire = function(handlerName, args) {
    for (var i = 0; i < this._listeners.length; i++) {
        var listener = this._listeners[i];
        if (handlerName in listener) {
            try {
                listener[handlerName].apply(listener, args);
            } catch (e) {
                SimileAjax.Debug.exception(e);
            }
        }
    }
};

Timeline.DefaultEventSource.prototype._getBaseURL = function(url) {
    if (url.indexOf("://") < 0) {
        var url2 = this._getBaseURL(document.location.href);
        if (url.substr(0,1) == "/") {
            url = url2.substr(0, url2.indexOf("/", url2.indexOf("://") + 3)) + url;
        } else {
            url = url2 + url;
        }
    }
    
    var i = url.lastIndexOf("/");
    if (i < 0) {
        return "";
    } else {
        return url.substr(0, i+1);
    }
};

Timeline.DefaultEventSource.prototype._resolveRelativeURL = function(url, base) {
    if (url == null || url == "") {
        return url;
    } else if (url.indexOf("://") > 0) {
        return url;
    } else if (url.substr(0,1) == "/") {
        return base.substr(0, base.indexOf("/", base.indexOf("://") + 3)) + url;
    } else {
        return base + url;
    }
};


Timeline.DefaultEventSource.Event = function(
        id,
        start, end, latestStart, earliestEnd, instant, 
        text, description, image, link,
        icon, color, textColor) {
        
    id = (id) ? id.trim() : "";
    this._id = id.length > 0 ? id : ("e" + Math.floor(Math.random() * 1000000));
    
    this._instant = instant || (end == null);
    
    this._start = start;
    this._end = (end != null) ? end : start;
    
    this._latestStart = (latestStart != null) ? latestStart : (instant ? this._end : this._start);
    this._earliestEnd = (earliestEnd != null) ? earliestEnd : (instant ? this._start : this._end);
    
    this._text = SimileAjax.HTML.deEntify(text);
    this._description = SimileAjax.HTML.deEntify(description);
    this._image = (image != null && image != "") ? image : null;
    this._link = (link != null && link != "") ? link : null;
    
    this._icon = (icon != null && icon != "") ? icon : null;
    this._color = (color != null && color != "") ? color : null;
    this._textColor = (textColor != null && textColor != "") ? textColor : null;
    
    this._wikiURL = null;
    this._wikiSection = null;
};

Timeline.DefaultEventSource.Event.prototype = {
    getID:          function() { return this._id; },
    
    isInstant:      function() { return this._instant; },
    isImprecise:    function() { return this._start != this._latestStart || this._end != this._earliestEnd; },
    
    getStart:       function() { return this._start; },
    getEnd:         function() { return this._end; },
    getLatestStart: function() { return this._latestStart; },
    getEarliestEnd: function() { return this._earliestEnd; },
    
    getText:        function() { return this._text; },
    getDescription: function() { return this._description; },
    getImage:       function() { return this._image; },
    getLink:        function() { return this._link; },
    
    getIcon:        function() { return this._icon; },
    getColor:       function() { return this._color; },
    getTextColor:   function() { return this._textColor; },
    
    getProperty:    function(name) { return null; },
    
    getWikiURL:     function() { return this._wikiURL; },
    getWikiSection: function() { return this._wikiSection; },
    setWikiInfo: function(wikiURL, wikiSection) {
        this._wikiURL = wikiURL;
        this._wikiSection = wikiSection;
    },
    
    fillDescription: function(elmt) {
        elmt.innerHTML = this._description;
    },
    fillWikiInfo: function(elmt) {
        if (this._wikiURL != null && this._wikiSection != null) {
            var wikiID = this.getProperty("wikiID");
            if (wikiID == null || wikiID.length == 0) {
                wikiID = this.getText();
            }
            wikiID = wikiID.replace(/\s/g, "_");
            
            var url = this._wikiURL + this._wikiSection.replace(/\s/g, "_") + "/" + wikiID;
            var a = document.createElement("a");
            a.href = url;
            a.target = "new";
            a.innerHTML = Timeline.strings[Timeline.clientLocale].wikiLinkLabel;
            
            elmt.appendChild(document.createTextNode("["));
            elmt.appendChild(a);
            elmt.appendChild(document.createTextNode("]"));
        } else {
            elmt.style.display = "none";
        }
    },
    fillTime: function(elmt, labeller) {
        if (this._instant) {
            if (this.isImprecise()) {
                elmt.appendChild(elmt.ownerDocument.createTextNode(labeller.labelPrecise(this._start)));
                elmt.appendChild(elmt.ownerDocument.createElement("br"));
                elmt.appendChild(elmt.ownerDocument.createTextNode(labeller.labelPrecise(this._end)));
            } else {
                elmt.appendChild(elmt.ownerDocument.createTextNode(labeller.labelPrecise(this._start)));
            }
        } else {
            if (this.isImprecise()) {
                elmt.appendChild(elmt.ownerDocument.createTextNode(
                    labeller.labelPrecise(this._start) + " ~ " + labeller.labelPrecise(this._latestStart)));
                elmt.appendChild(elmt.ownerDocument.createElement("br"));
                elmt.appendChild(elmt.ownerDocument.createTextNode(
                    labeller.labelPrecise(this._earliestEnd) + " ~ " + labeller.labelPrecise(this._end)));
            } else {
                elmt.appendChild(elmt.ownerDocument.createTextNode(labeller.labelPrecise(this._start)));
                elmt.appendChild(elmt.ownerDocument.createElement("br"));
                elmt.appendChild(elmt.ownerDocument.createTextNode(labeller.labelPrecise(this._end)));
            }
        }
    },
    fillInfoBubble: function(elmt, theme, labeller) {
        var doc = elmt.ownerDocument;
        
        var title = this.getText();
        var link = this.getLink();
        var image = this.getImage();
        
        if (image != null) {
            var img = doc.createElement("img");
            img.src = image;
            
            theme.event.bubble.imageStyler(img);
            elmt.appendChild(img);
        }
        
        var divTitle = doc.createElement("div");
        var textTitle = doc.createTextNode(title);
        if (link != null) {
            var a = doc.createElement("a");
            a.href = link;
            a.appendChild(textTitle);
            divTitle.appendChild(a);
        } else {
            divTitle.appendChild(textTitle);
        }
        theme.event.bubble.titleStyler(divTitle);
        elmt.appendChild(divTitle);
        
        var divBody = doc.createElement("div");
        this.fillDescription(divBody);
        theme.event.bubble.bodyStyler(divBody);
        elmt.appendChild(divBody);
        
        var divTime = doc.createElement("div");
        this.fillTime(divTime, labeller);
        theme.event.bubble.timeStyler(divTime);
        elmt.appendChild(divTime);
        
        var divWiki = doc.createElement("div");
        this.fillWikiInfo(divWiki);
        theme.event.bubble.wikiStyler(divWiki);
        elmt.appendChild(divWiki);
    }
};/*==================================================
 *  Original Event Painter
 *==================================================
 */

Timeline.OriginalEventPainter = function(params) {
    this._params = params;
    this._onSelectListeners = [];
    
    this._filterMatcher = null;
    this._highlightMatcher = null;
    this._frc = null;
    
    this._eventIdToElmt = {};
};

Timeline.OriginalEventPainter.prototype.initialize = function(band, timeline) {
    this._band = band;
    this._timeline = timeline;
    
    this._backLayer = null;
    this._eventLayer = null;
    this._lineLayer = null;
    this._highlightLayer = null;
    
    this._eventIdToElmt = null;
};

Timeline.OriginalEventPainter.prototype.addOnSelectListener = function(listener) {
    this._onSelectListeners.push(listener);
};

Timeline.OriginalEventPainter.prototype.removeOnSelectListener = function(listener) {
    for (var i = 0; i < this._onSelectListeners.length; i++) {
        if (this._onSelectListeners[i] == listener) {
            this._onSelectListeners.splice(i, 1);
            break;
        }
    }
};

Timeline.OriginalEventPainter.prototype.getFilterMatcher = function() {
    return this._filterMatcher;
};

Timeline.OriginalEventPainter.prototype.setFilterMatcher = function(filterMatcher) {
    this._filterMatcher = filterMatcher;
};

Timeline.OriginalEventPainter.prototype.getHighlightMatcher = function() {
    return this._highlightMatcher;
};

Timeline.OriginalEventPainter.prototype.setHighlightMatcher = function(highlightMatcher) {
    this._highlightMatcher = highlightMatcher;
};

Timeline.OriginalEventPainter.prototype.paint = function() {
    var eventSource = this._band.getEventSource();
    if (eventSource == null) {
        return;
    }
    
    this._eventIdToElmt = {};
    this._prepareForPainting();
    
    var eventTheme = this._params.theme.event;
    var trackHeight = Math.max(eventTheme.track.height, eventTheme.tape.height + this._frc.getLineHeight());
    var metrics = {
        trackOffset:    eventTheme.track.gap,
        trackHeight:    trackHeight,
        trackGap:       eventTheme.track.gap,
        trackIncrement: trackHeight + eventTheme.track.gap,
        icon:           eventTheme.instant.icon,
        iconWidth:      eventTheme.instant.iconWidth,
        iconHeight:     eventTheme.instant.iconHeight,
        labelWidth:     eventTheme.label.width
    }
    
    var minDate = this._band.getMinDate();
    var maxDate = this._band.getMaxDate();
    
    var filterMatcher = (this._filterMatcher != null) ? 
        this._filterMatcher :
        function(evt) { return true; };
    var highlightMatcher = (this._highlightMatcher != null) ? 
        this._highlightMatcher :
        function(evt) { return -1; };
    
    var iterator = eventSource.getEventReverseIterator(minDate, maxDate);
    while (iterator.hasNext()) {
        var evt = iterator.next();
        if (filterMatcher(evt)) {
            this.paintEvent(evt, metrics, this._params.theme, highlightMatcher(evt));
        }
    }
    
    this._highlightLayer.style.display = "block";
    this._lineLayer.style.display = "block";
    this._eventLayer.style.display = "block";
};

Timeline.OriginalEventPainter.prototype.softPaint = function() {
};

Timeline.OriginalEventPainter.prototype._prepareForPainting = function() {
    var band = this._band;
        
    if (this._backLayer == null) {
        this._backLayer = this._band.createLayerDiv(0, "timeline-band-events");
        this._backLayer.style.visibility = "hidden";
        
        var eventLabelPrototype = document.createElement("span");
        eventLabelPrototype.className = "timeline-event-label";
        this._backLayer.appendChild(eventLabelPrototype);
        this._frc = SimileAjax.Graphics.getFontRenderingContext(eventLabelPrototype);
    }
    this._frc.update();
    this._tracks = [];
    
    if (this._highlightLayer != null) {
        band.removeLayerDiv(this._highlightLayer);
    }
    this._highlightLayer = band.createLayerDiv(105, "timeline-band-highlights");
    this._highlightLayer.style.display = "none";
    
    if (this._lineLayer != null) {
        band.removeLayerDiv(this._lineLayer);
    }
    this._lineLayer = band.createLayerDiv(110, "timeline-band-lines");
    this._lineLayer.style.display = "none";
    
    if (this._eventLayer != null) {
        band.removeLayerDiv(this._eventLayer);
    }
    this._eventLayer = band.createLayerDiv(115, "timeline-band-events");
    this._eventLayer.style.display = "none";
};

Timeline.OriginalEventPainter.prototype.paintEvent = function(evt, metrics, theme, highlightIndex) {
    if (evt.isInstant()) {
        this.paintInstantEvent(evt, metrics, theme, highlightIndex);
    } else {
        this.paintDurationEvent(evt, metrics, theme, highlightIndex);
    }
};
    
Timeline.OriginalEventPainter.prototype.paintInstantEvent = function(evt, metrics, theme, highlightIndex) {
    if (evt.isImprecise()) {
        this.paintImpreciseInstantEvent(evt, metrics, theme, highlightIndex);
    } else {
        this.paintPreciseInstantEvent(evt, metrics, theme, highlightIndex);
    }
}

Timeline.OriginalEventPainter.prototype.paintDurationEvent = function(evt, metrics, theme, highlightIndex) {
    if (evt.isImprecise()) {
        this.paintImpreciseDurationEvent(evt, metrics, theme, highlightIndex);
    } else {
        this.paintPreciseDurationEvent(evt, metrics, theme, highlightIndex);
    }
}
    
Timeline.OriginalEventPainter.prototype.paintPreciseInstantEvent = function(evt, metrics, theme, highlightIndex) {
    var doc = this._timeline.getDocument();
    var text = evt.getText();
    
    var startDate = evt.getStart();
    var startPixel = Math.round(this._band.dateToPixelOffset(startDate));
    var iconRightEdge = Math.round(startPixel + metrics.iconWidth / 2);
    var iconLeftEdge = Math.round(startPixel - metrics.iconWidth / 2);
    
    var labelSize = this._frc.computeSize(text);
    var labelLeft = iconRightEdge + theme.event.label.offsetFromLine;
    var labelRight = labelLeft + labelSize.width;
    
    var rightEdge = labelRight;
    var track = this._findFreeTrack(rightEdge);
    
    var labelTop = Math.round(
        metrics.trackOffset + track * metrics.trackIncrement + 
        metrics.trackHeight / 2 - labelSize.height / 2);
        
    var iconElmtData = this._paintEventIcon(evt, track, iconLeftEdge, metrics, theme);
    var labelElmtData = this._paintEventLabel(evt, text, labelLeft, labelTop, labelSize.width, labelSize.height, theme);

    var self = this;
    var clickHandler = function(elmt, domEvt, target) {
        return self._onClickInstantEvent(iconElmtData.elmt, domEvt, evt);
    };
    SimileAjax.DOM.registerEvent(iconElmtData.elmt, "mousedown", clickHandler);
    SimileAjax.DOM.registerEvent(labelElmtData.elmt, "mousedown", clickHandler);
    
    this._createHighlightDiv(highlightIndex, iconElmtData, theme);
    
    this._eventIdToElmt[evt.getID()] = iconElmtData.elmt;
    this._tracks[track] = iconLeftEdge;
};

Timeline.OriginalEventPainter.prototype.paintImpreciseInstantEvent = function(evt, metrics, theme, highlightIndex) {
    var doc = this._timeline.getDocument();
    var text = evt.getText();
    
    var startDate = evt.getStart();
    var endDate = evt.getEnd();
    var startPixel = Math.round(this._band.dateToPixelOffset(startDate));
    var endPixel = Math.round(this._band.dateToPixelOffset(endDate));
    
    var iconRightEdge = Math.round(startPixel + metrics.iconWidth / 2);
    var iconLeftEdge = Math.round(startPixel - metrics.iconWidth / 2);
    
    var labelSize = this._frc.computeSize(text);
    var labelLeft = iconRightEdge + theme.event.label.offsetFromLine;
    var labelRight = labelLeft + labelSize.width;
    
    var rightEdge = Math.max(labelRight, endPixel);
    var track = this._findFreeTrack(rightEdge);
    var labelTop = Math.round(
        metrics.trackOffset + track * metrics.trackIncrement + 
        metrics.trackHeight / 2 - labelSize.height / 2);
    
    var iconElmtData = this._paintEventIcon(evt, track, iconLeftEdge, metrics, theme);
    var labelElmtData = this._paintEventLabel(evt, text, labelLeft, labelTop, labelSize.width, labelSize.height, theme);
    var tapeElmtData = this._paintEventTape(evt, track, startPixel, endPixel, 
        theme.event.instant.impreciseColor, theme.event.instant.impreciseOpacity, metrics, theme);
    
    var self = this;
    var clickHandler = function(elmt, domEvt, target) {
        return self._onClickInstantEvent(iconElmtData.elmt, domEvt, evt);
    };
    SimileAjax.DOM.registerEvent(iconElmtData.elmt, "mousedown", clickHandler);
    SimileAjax.DOM.registerEvent(tapeElmtData.elmt, "mousedown", clickHandler);
    SimileAjax.DOM.registerEvent(labelElmtData.elmt, "mousedown", clickHandler);
    
    this._createHighlightDiv(highlightIndex, iconElmtData, theme);
    
    this._eventIdToElmt[evt.getID()] = iconElmtData.elmt;
    this._tracks[track] = iconLeftEdge;
};

Timeline.OriginalEventPainter.prototype.paintPreciseDurationEvent = function(evt, metrics, theme, highlightIndex) {
    var doc = this._timeline.getDocument();
    var text = evt.getText();
    
    var startDate = evt.getStart();
    var endDate = evt.getEnd();
    var startPixel = Math.round(this._band.dateToPixelOffset(startDate));
    var endPixel = Math.round(this._band.dateToPixelOffset(endDate));
    
    var labelSize = this._frc.computeSize(text);
    var labelLeft = startPixel;
    var labelRight = labelLeft + labelSize.width;
    
    var rightEdge = Math.max(labelRight, endPixel);
    var track = this._findFreeTrack(rightEdge);
    var labelTop = Math.round(
        metrics.trackOffset + track * metrics.trackIncrement + theme.event.tape.height);
    
    var color = evt.getColor();
    color = color != null ? color : theme.event.duration.color;
    
    var tapeElmtData = this._paintEventTape(evt, track, startPixel, endPixel, color, 100, metrics, theme);
    var labelElmtData = this._paintEventLabel(evt, text, labelLeft, labelTop, labelSize.width, labelSize.height, theme);
    
    var self = this;
    var clickHandler = function(elmt, domEvt, target) {
        return self._onClickInstantEvent(tapeElmtData.elmt, domEvt, evt);
    };
    SimileAjax.DOM.registerEvent(tapeElmtData.elmt, "mousedown", clickHandler);
    SimileAjax.DOM.registerEvent(labelElmtData.elmt, "mousedown", clickHandler);
    
    this._createHighlightDiv(highlightIndex, tapeElmtData, theme);
    
    this._eventIdToElmt[evt.getID()] = tapeElmtData.elmt;
    this._tracks[track] = startPixel;
};

Timeline.OriginalEventPainter.prototype.paintImpreciseDurationEvent = function(evt, metrics, theme, highlightIndex) {
    var doc = this._timeline.getDocument();
    var text = evt.getText();
    
    var startDate = evt.getStart();
    var latestStartDate = evt.getLatestStart();
    var endDate = evt.getEnd();
    var earliestEndDate = evt.getEarliestEnd();
    
    var startPixel = Math.round(this._band.dateToPixelOffset(startDate));
    var latestStartPixel = Math.round(this._band.dateToPixelOffset(latestStartDate));
    var endPixel = Math.round(this._band.dateToPixelOffset(endDate));
    var earliestEndPixel = Math.round(this._band.dateToPixelOffset(earliestEndDate));
    
    var labelSize = this._frc.computeSize(text);
    var labelLeft = latestStartPixel;
    var labelRight = labelLeft + labelSize.width;
    
    var rightEdge = Math.max(labelRight, endPixel);
    var track = this._findFreeTrack(rightEdge);
    var labelTop = Math.round(
        metrics.trackOffset + track * metrics.trackIncrement + theme.event.tape.height);
    
    var color = evt.getColor();
    color = color != null ? color : theme.event.duration.color;
    
    var impreciseTapeElmtData = this._paintEventTape(evt, track, startPixel, endPixel, 
        theme.event.duration.impreciseColor, theme.event.duration.impreciseOpacity, metrics, theme);
    var tapeElmtData = this._paintEventTape(evt, track, latestStartPixel, earliestEndPixel, color, 100, metrics, theme);
    
    var labelElmtData = this._paintEventLabel(evt, text, labelLeft, labelTop, labelSize.width, labelSize.height, theme);
    
    var self = this;
    var clickHandler = function(elmt, domEvt, target) {
        return self._onClickInstantEvent(tapeElmtData.elmt, domEvt, evt);
    };
    SimileAjax.DOM.registerEvent(tapeElmtData.elmt, "mousedown", clickHandler);
    SimileAjax.DOM.registerEvent(labelElmtData.elmt, "mousedown", clickHandler);
    
    this._createHighlightDiv(highlightIndex, tapeElmtData, theme);
    
    this._eventIdToElmt[evt.getID()] = tapeElmtData.elmt;
    this._tracks[track] = startPixel;
};

Timeline.OriginalEventPainter.prototype._findFreeTrack = function(rightEdge) {
    for (var i = 0; i < this._tracks.length; i++) {
        var t = this._tracks[i];
        if (t > rightEdge) {
            break;
        }
    }
    return i;
};

Timeline.OriginalEventPainter.prototype._paintEventIcon = function(evt, iconTrack, left, metrics, theme) {
    var icon = evt.getIcon();
    icon = icon != null ? icon : metrics.icon;
    
    var middle = metrics.trackOffset + iconTrack * metrics.trackIncrement + metrics.trackHeight / 2;
    var top = Math.round(middle - metrics.iconHeight / 2);

    var img = SimileAjax.Graphics.createTranslucentImage(icon);
    var iconDiv = this._timeline.getDocument().createElement("div");
    iconDiv.style.position = "absolute";
    iconDiv.style.left = left + "px";
    iconDiv.style.top = top + "px";
    iconDiv.appendChild(img);
    iconDiv.style.cursor = "pointer";
    this._eventLayer.appendChild(iconDiv);
    
    return {
        left:   left,
        top:    top,
        width:  metrics.iconWidth,
        height: metrics.iconHeight,
        elmt:   iconDiv
    };
};

Timeline.OriginalEventPainter.prototype._paintEventLabel = function(evt, text, left, top, width, height, theme) {
    var doc = this._timeline.getDocument();
    
    var labelDiv = doc.createElement("div");
    labelDiv.style.position = "absolute";
    labelDiv.style.left = left + "px";
    labelDiv.style.width = width + "px";
    labelDiv.style.top = top + "px";
    labelDiv.innerHTML = text;
    labelDiv.style.cursor = "pointer";
    
    var color = evt.getTextColor();
    if (color == null) {
        color = evt.getColor();
    }
    if (color != null) {
        labelDiv.style.color = color;
    }
    
    this._eventLayer.appendChild(labelDiv);
    
    return {
        left:   left,
        top:    top,
        width:  width,
        height: height,
        elmt:   labelDiv
    };
};

Timeline.OriginalEventPainter.prototype._paintEventTape = function(
    evt, iconTrack, startPixel, endPixel, color, opacity, metrics, theme) {
    
    var tapeWidth = endPixel - startPixel;
    var tapeHeight = theme.event.tape.height;
    var top = metrics.trackOffset + iconTrack * metrics.trackIncrement;
    
    var tapeDiv = this._timeline.getDocument().createElement("div");
    tapeDiv.style.position = "absolute";
    tapeDiv.style.left = startPixel + "px";
    tapeDiv.style.width = tapeWidth + "px";
    tapeDiv.style.top = top + "px";
    tapeDiv.style.height = tapeHeight + "px";
    tapeDiv.style.backgroundColor = color;
    tapeDiv.style.overflow = "hidden";
    tapeDiv.style.cursor = "pointer";
    SimileAjax.Graphics.setOpacity(tapeDiv, opacity);
    
    this._eventLayer.appendChild(tapeDiv);
    
    return {
        left:   startPixel,
        top:    top,
        width:  tapeWidth,
        height: tapeHeight,
        elmt:   tapeDiv
    };
}

Timeline.OriginalEventPainter.prototype._createHighlightDiv = function(highlightIndex, dimensions, theme) {
    if (highlightIndex >= 0) {
        var doc = this._timeline.getDocument();
        var eventTheme = theme.event;
        
        var color = eventTheme.highlightColors[Math.min(highlightIndex, eventTheme.highlightColors.length - 1)];
        
        var div = doc.createElement("div");
        div.style.position = "absolute";
        div.style.overflow = "hidden";
        div.style.left =    (dimensions.left - 2) + "px";
        div.style.width =   (dimensions.width + 4) + "px";
        div.style.top =     (dimensions.top - 2) + "px";
        div.style.height =  (dimensions.height + 4) + "px";
        div.style.background = color;
        
        this._highlightLayer.appendChild(div);
    }
};

Timeline.OriginalEventPainter.prototype._onClickInstantEvent = function(icon, domEvt, evt) {
    var c = SimileAjax.DOM.getPageCoordinates(icon);
    this._showBubble(
        c.left + Math.ceil(icon.offsetWidth / 2), 
        c.top + Math.ceil(icon.offsetHeight / 2),
        evt
    );
    this._fireOnSelect(evt.getID());
    
    domEvt.cancelBubble = true;
    SimileAjax.DOM.cancelEvent(domEvt);
    return false;
};

Timeline.OriginalEventPainter.prototype._onClickDurationEvent = function(target, domEvt, evt) {
    if ("pageX" in domEvt) {
        var x = domEvt.pageX;
        var y = domEvt.pageY;
    } else {
        var c = SimileAjax.DOM.getPageCoordinates(target);
        var x = domEvt.offsetX + c.left;
        var y = domEvt.offsetY + c.top;
    }
    this._showBubble(x, y, evt);
    this._fireOnSelect(evt.getID());
    
    domEvt.cancelBubble = true;
    SimileAjax.DOM.cancelEvent(domEvt);
    return false;
};

Timeline.OriginalEventPainter.prototype.showBubble = function(evt) {
    var elmt = this._eventIdToElmt[evt.getID()];
    if (elmt) {
        var c = SimileAjax.DOM.getPageCoordinates(elmt);
        this._showBubble(c.left + elmt.offsetWidth / 2, c.top + elmt.offsetHeight / 2, evt);
    }
};

Timeline.OriginalEventPainter.prototype._showBubble = function(x, y, evt) {
    var div = document.createElement("div");
    evt.fillInfoBubble(div, this._params.theme, this._band.getLabeller());
    
    SimileAjax.WindowManager.cancelPopups();
    SimileAjax.Graphics.createBubbleForContentAndPoint(div, x, y, this._params.theme.event.bubble.width);
};

Timeline.OriginalEventPainter.prototype._fireOnSelect = function(eventID) {
    for (var i = 0; i < this._onSelectListeners.length; i++) {
        this._onSelectListeners[i](eventID);
    }
};
/*==================================================
 *  Detailed Event Painter
 *==================================================
 */

Timeline.DetailedEventPainter = function(params) {
    this._params = params;
    this._onSelectListeners = [];
    
    this._filterMatcher = null;
    this._highlightMatcher = null;
    this._frc = null;
    
    this._eventIdToElmt = {};
};

Timeline.DetailedEventPainter.prototype.initialize = function(band, timeline) {
    this._band = band;
    this._timeline = timeline;
    
    this._backLayer = null;
    this._eventLayer = null;
    this._lineLayer = null;
    this._highlightLayer = null;
    
    this._eventIdToElmt = null;
};

Timeline.DetailedEventPainter.prototype.addOnSelectListener = function(listener) {
    this._onSelectListeners.push(listener);
};

Timeline.DetailedEventPainter.prototype.removeOnSelectListener = function(listener) {
    for (var i = 0; i < this._onSelectListeners.length; i++) {
        if (this._onSelectListeners[i] == listener) {
            this._onSelectListeners.splice(i, 1);
            break;
        }
    }
};

Timeline.DetailedEventPainter.prototype.getFilterMatcher = function() {
    return this._filterMatcher;
};

Timeline.DetailedEventPainter.prototype.setFilterMatcher = function(filterMatcher) {
    this._filterMatcher = filterMatcher;
};

Timeline.DetailedEventPainter.prototype.getHighlightMatcher = function() {
    return this._highlightMatcher;
};

Timeline.DetailedEventPainter.prototype.setHighlightMatcher = function(highlightMatcher) {
    this._highlightMatcher = highlightMatcher;
};

Timeline.DetailedEventPainter.prototype.paint = function() {
    var eventSource = this._band.getEventSource();
    if (eventSource == null) {
        return;
    }
    
    this._eventIdToElmt = {};
    this._prepareForPainting();
    
    var eventTheme = this._params.theme.event;
    var trackHeight = Math.max(eventTheme.track.height, this._frc.getLineHeight());
    var metrics = {
        trackOffset:    Math.round(this._band.getViewWidth() / 2 - trackHeight / 2),
        trackHeight:    trackHeight,
        trackGap:       eventTheme.track.gap,
        trackIncrement: trackHeight + eventTheme.track.gap,
        icon:           eventTheme.instant.icon,
        iconWidth:      eventTheme.instant.iconWidth,
        iconHeight:     eventTheme.instant.iconHeight,
        labelWidth:     eventTheme.label.width
    }
    
    var minDate = this._band.getMinDate();
    var maxDate = this._band.getMaxDate();
    
    var filterMatcher = (this._filterMatcher != null) ? 
        this._filterMatcher :
        function(evt) { return true; };
    var highlightMatcher = (this._highlightMatcher != null) ? 
        this._highlightMatcher :
        function(evt) { return -1; };
    
    var iterator = eventSource.getEventReverseIterator(minDate, maxDate);
    while (iterator.hasNext()) {
        var evt = iterator.next();
        if (filterMatcher(evt)) {
            this.paintEvent(evt, metrics, this._params.theme, highlightMatcher(evt));
        }
    }
    
    this._highlightLayer.style.display = "block";
    this._lineLayer.style.display = "block";
    this._eventLayer.style.display = "block";
};

Timeline.DetailedEventPainter.prototype.softPaint = function() {
};

Timeline.DetailedEventPainter.prototype._prepareForPainting = function() {
    var band = this._band;
        
    if (this._backLayer == null) {
        this._backLayer = this._band.createLayerDiv(0, "timeline-band-events");
        this._backLayer.style.visibility = "hidden";
        
        var eventLabelPrototype = document.createElement("span");
        eventLabelPrototype.className = "timeline-event-label";
        this._backLayer.appendChild(eventLabelPrototype);
        this._frc = SimileAjax.Graphics.getFontRenderingContext(eventLabelPrototype);
    }
    this._frc.update();
    this._lowerTracks = [];
    this._upperTracks = [];
    
    if (this._highlightLayer != null) {
        band.removeLayerDiv(this._highlightLayer);
    }
    this._highlightLayer = band.createLayerDiv(105, "timeline-band-highlights");
    this._highlightLayer.style.display = "none";
    
    if (this._lineLayer != null) {
        band.removeLayerDiv(this._lineLayer);
    }
    this._lineLayer = band.createLayerDiv(110, "timeline-band-lines");
    this._lineLayer.style.display = "none";
    
    if (this._eventLayer != null) {
        band.removeLayerDiv(this._eventLayer);
    }
    this._eventLayer = band.createLayerDiv(110, "timeline-band-events");
    this._eventLayer.style.display = "none";
};

Timeline.DetailedEventPainter.prototype.paintEvent = function(evt, metrics, theme, highlightIndex) {
    if (evt.isInstant()) {
        this.paintInstantEvent(evt, metrics, theme, highlightIndex);
    } else {
        this.paintDurationEvent(evt, metrics, theme, highlightIndex);
    }
};
    
Timeline.DetailedEventPainter.prototype.paintInstantEvent = function(evt, metrics, theme, highlightIndex) {
    if (evt.isImprecise()) {
        this.paintImpreciseInstantEvent(evt, metrics, theme, highlightIndex);
    } else {
        this.paintPreciseInstantEvent(evt, metrics, theme, highlightIndex);
    }
}

Timeline.DetailedEventPainter.prototype.paintDurationEvent = function(evt, metrics, theme, highlightIndex) {
    if (evt.isImprecise()) {
        this.paintImpreciseDurationEvent(evt, metrics, theme, highlightIndex);
    } else {
        this.paintPreciseDurationEvent(evt, metrics, theme, highlightIndex);
    }
}
    
Timeline.DetailedEventPainter.prototype.paintPreciseInstantEvent = function(evt, metrics, theme, highlightIndex) {
    var doc = this._timeline.getDocument();
    var text = evt.getText();
    
    var startDate = evt.getStart();
    var startPixel = Math.round(this._band.dateToPixelOffset(startDate));
    var iconRightEdge = Math.round(startPixel + metrics.iconWidth / 2);
    var iconLeftEdge = Math.round(startPixel - metrics.iconWidth / 2);
    
    var labelSize = this._frc.computeSize(text);
    var iconTrack = this._findFreeTrackForSolid(iconRightEdge, startPixel);
    var iconElmtData = this._paintEventIcon(evt, iconTrack, iconLeftEdge, metrics, theme);
    
    var labelLeft = iconRightEdge + theme.event.label.offsetFromLine;
    var labelTrack = iconTrack;
    
    var iconTrackData = this._getTrackData(iconTrack);
    if (Math.min(iconTrackData.solid, iconTrackData.text) >= labelLeft + labelSize.width) { // label on the same track, to the right of icon
        iconTrackData.solid = iconLeftEdge;
        iconTrackData.text = labelLeft;
    } else { // label on a different track, below icon
        iconTrackData.solid = iconLeftEdge;
        
        labelLeft = startPixel + theme.event.label.offsetFromLine;
        labelTrack = this._findFreeTrackForText(iconTrack, labelLeft + labelSize.width, function(t) { t.line = startPixel - 2; });
        this._getTrackData(labelTrack).text = iconLeftEdge;
        
        this._paintEventLine(evt, startPixel, iconTrack, labelTrack, metrics, theme);
    }
    
    var labelTop = Math.round(
        metrics.trackOffset + labelTrack * metrics.trackIncrement + 
        metrics.trackHeight / 2 - labelSize.height / 2);
        
    var labelElmtData = this._paintEventLabel(evt, text, labelLeft, labelTop, labelSize.width, labelSize.height, theme);

    var self = this;
    var clickHandler = function(elmt, domEvt, target) {
        return self._onClickInstantEvent(iconElmtData.elmt, domEvt, evt);
    };
    SimileAjax.DOM.registerEvent(iconElmtData.elmt, "mousedown", clickHandler);
    SimileAjax.DOM.registerEvent(labelElmtData.elmt, "mousedown", clickHandler);
    
    this._createHighlightDiv(highlightIndex, iconElmtData, theme);
    
    this._eventIdToElmt[evt.getID()] = iconElmtData.elmt;
};

Timeline.DetailedEventPainter.prototype.paintImpreciseInstantEvent = function(evt, metrics, theme, highlightIndex) {
    var doc = this._timeline.getDocument();
    var text = evt.getText();
    
    var startDate = evt.getStart();
    var endDate = evt.getEnd();
    var startPixel = Math.round(this._band.dateToPixelOffset(startDate));
    var endPixel = Math.round(this._band.dateToPixelOffset(endDate));
    
    var iconRightEdge = Math.round(startPixel + metrics.iconWidth / 2);
    var iconLeftEdge = Math.round(startPixel - metrics.iconWidth / 2);
    
    var labelSize = this._frc.computeSize(text);
    var iconTrack = this._findFreeTrackForSolid(endPixel, startPixel);
    
    var tapeElmtData = this._paintEventTape(evt, iconTrack, startPixel, endPixel, 
        theme.event.instant.impreciseColor, theme.event.instant.impreciseOpacity, metrics, theme);
    var iconElmtData = this._paintEventIcon(evt, iconTrack, iconLeftEdge, metrics, theme);
    
    var iconTrackData = this._getTrackData(iconTrack);
    iconTrackData.solid = iconLeftEdge;
    
    var labelLeft = iconRightEdge + theme.event.label.offsetFromLine;
    var labelRight = labelLeft + labelSize.width;
    var labelTrack;
    if (labelRight < endPixel) {
        labelTrack = iconTrack;
    } else {
        labelLeft = startPixel + theme.event.label.offsetFromLine;
        labelRight = labelLeft + labelSize.width;
    
        labelTrack = this._findFreeTrackForText(iconTrack, labelRight, function(t) { t.line = startPixel - 2; });
        this._getTrackData(labelTrack).text = iconLeftEdge;
        
        this._paintEventLine(evt, startPixel, iconTrack, labelTrack, metrics, theme);
    }
    var labelTop = Math.round(
        metrics.trackOffset + labelTrack * metrics.trackIncrement + 
        metrics.trackHeight / 2 - labelSize.height / 2);
        
    var labelElmtData = this._paintEventLabel(evt, text, labelLeft, labelTop, labelSize.width, labelSize.height, theme);
    
    var self = this;
    var clickHandler = function(elmt, domEvt, target) {
        return self._onClickInstantEvent(iconElmtData.elmt, domEvt, evt);
    };
    SimileAjax.DOM.registerEvent(iconElmtData.elmt, "mousedown", clickHandler);
    SimileAjax.DOM.registerEvent(tapeElmtData.elmt, "mousedown", clickHandler);
    SimileAjax.DOM.registerEvent(labelElmtData.elmt, "mousedown", clickHandler);
    
    this._createHighlightDiv(highlightIndex, iconElmtData, theme);
    
    this._eventIdToElmt[evt.getID()] = iconElmtData.elmt;
};

Timeline.DetailedEventPainter.prototype.paintPreciseDurationEvent = function(evt, metrics, theme, highlightIndex) {
    var doc = this._timeline.getDocument();
    var text = evt.getText();
    
    var startDate = evt.getStart();
    var endDate = evt.getEnd();
    var startPixel = Math.round(this._band.dateToPixelOffset(startDate));
    var endPixel = Math.round(this._band.dateToPixelOffset(endDate));
    
    var labelSize = this._frc.computeSize(text);
    var tapeTrack = this._findFreeTrackForSolid(endPixel);
    var color = evt.getColor();
    color = color != null ? color : theme.event.duration.color;
    
    var tapeElmtData = this._paintEventTape(evt, tapeTrack, startPixel, endPixel, color, 100, metrics, theme);
    
    var tapeTrackData = this._getTrackData(tapeTrack);
    tapeTrackData.solid = startPixel;
    
    var labelLeft = startPixel + theme.event.label.offsetFromLine;
    var labelTrack = this._findFreeTrackForText(tapeTrack, labelLeft + labelSize.width, function(t) { t.line = startPixel - 2; });
    this._getTrackData(labelTrack).text = startPixel - 2;
    
    this._paintEventLine(evt, startPixel, tapeTrack, labelTrack, metrics, theme);
    
    var labelTop = Math.round(
        metrics.trackOffset + labelTrack * metrics.trackIncrement + 
        metrics.trackHeight / 2 - labelSize.height / 2);
        
    var labelElmtData = this._paintEventLabel(evt, text, labelLeft, labelTop, labelSize.width, labelSize.height, theme);

    var self = this;
    var clickHandler = function(elmt, domEvt, target) {
        return self._onClickDurationEvent(tapeElmtData.elmt, domEvt, evt);
    };
    SimileAjax.DOM.registerEvent(tapeElmtData.elmt, "mousedown", clickHandler);
    SimileAjax.DOM.registerEvent(labelElmtData.elmt, "mousedown", clickHandler);
    
    this._createHighlightDiv(highlightIndex, tapeElmtData, theme);
    
    this._eventIdToElmt[evt.getID()] = tapeElmtData.elmt;
};

Timeline.DetailedEventPainter.prototype.paintImpreciseDurationEvent = function(evt, metrics, theme, highlightIndex) {
    var doc = this._timeline.getDocument();
    var text = evt.getText();
    
    var startDate = evt.getStart();
    var latestStartDate = evt.getLatestStart();
    var endDate = evt.getEnd();
    var earliestEndDate = evt.getEarliestEnd();
    
    var startPixel = Math.round(this._band.dateToPixelOffset(startDate));
    var latestStartPixel = Math.round(this._band.dateToPixelOffset(latestStartDate));
    var endPixel = Math.round(this._band.dateToPixelOffset(endDate));
    var earliestEndPixel = Math.round(this._band.dateToPixelOffset(earliestEndDate));
    
    var labelSize = this._frc.computeSize(text);
    var tapeTrack = this._findFreeTrackForSolid(endPixel);
    var color = evt.getColor();
    color = color != null ? color : theme.event.duration.color;
    
    var impreciseTapeElmtData = this._paintEventTape(evt, tapeTrack, startPixel, endPixel, 
        theme.event.duration.impreciseColor, theme.event.duration.impreciseOpacity, metrics, theme);
    var tapeElmtData = this._paintEventTape(evt, tapeTrack, latestStartPixel, earliestEndPixel, color, 100, metrics, theme);
    
    var tapeTrackData = this._getTrackData(tapeTrack);
    tapeTrackData.solid = startPixel;
    
    var labelLeft = latestStartPixel + theme.event.label.offsetFromLine;
    var labelTrack = this._findFreeTrackForText(tapeTrack, labelLeft + labelSize.width, function(t) { t.line = latestStartPixel - 2; });
    this._getTrackData(labelTrack).text = latestStartPixel - 2;
    
    this._paintEventLine(evt, latestStartPixel, tapeTrack, labelTrack, metrics, theme);
    
    var labelTop = Math.round(
        metrics.trackOffset + labelTrack * metrics.trackIncrement + 
        metrics.trackHeight / 2 - labelSize.height / 2);
        
    var labelElmtData = this._paintEventLabel(evt, text, labelLeft, labelTop, labelSize.width, labelSize.height, theme);
    
    var self = this;
    var clickHandler = function(elmt, domEvt, target) {
        return self._onClickDurationEvent(tapeElmtData.elmt, domEvt, evt);
    };
    SimileAjax.DOM.registerEvent(tapeElmtData.elmt, "mousedown", clickHandler);
    SimileAjax.DOM.registerEvent(labelElmtData.elmt, "mousedown", clickHandler);
    
    this._createHighlightDiv(highlightIndex, tapeElmtData, theme);
    
    this._eventIdToElmt[evt.getID()] = tapeElmtData.elmt;
};

Timeline.DetailedEventPainter.prototype._findFreeTrackForSolid = function(solidEdge, softEdge) {
    for (var i = 0; true; i++) {
        if (i < this._lowerTracks.length) {
            var t = this._lowerTracks[i];
            if (Math.min(t.solid, t.text) > solidEdge && (!(softEdge) || t.line > softEdge)) {
                return i;
            }
        } else {
            this._lowerTracks.push({
                solid:  Number.POSITIVE_INFINITY,
                text:   Number.POSITIVE_INFINITY,
                line:   Number.POSITIVE_INFINITY
            });
            
            return i;
        }
        
        if (i < this._upperTracks.length) {
            var t = this._upperTracks[i];
            if (Math.min(t.solid, t.text) > solidEdge && (!(softEdge) || t.line > softEdge)) {
                return -1 - i;
            }
        } else {
            this._upperTracks.push({
                solid:  Number.POSITIVE_INFINITY,
                text:   Number.POSITIVE_INFINITY,
                line:   Number.POSITIVE_INFINITY
            });
            
            return -1 - i;
        }
    }
};

Timeline.DetailedEventPainter.prototype._findFreeTrackForText = function(fromTrack, edge, occupiedTrackVisitor) {
    var extendUp;
    var index;
    var firstIndex;
    var result;
    
    if (fromTrack < 0) {
        extendUp = true;
        firstIndex = -fromTrack;
        
        index = this._findFreeUpperTrackForText(firstIndex, edge);
        result = -1 - index;
    } else if (fromTrack > 0) {
        extendUp = false;
        firstIndex = fromTrack + 1;
        
        index = this._findFreeLowerTrackForText(firstIndex, edge);
        result = index;
    } else {
        var upIndex = this._findFreeUpperTrackForText(0, edge);
        var downIndex = this._findFreeLowerTrackForText(1, edge);
        
        if (downIndex - 1 <= upIndex) {
            extendUp = false;
            firstIndex = 1;
            index = downIndex;
            result = index;
        } else {
            extendUp = true;
            firstIndex = 0;
            index = upIndex;
            result = -1 - index;
        }
    }
    
    if (extendUp) {
        if (index == this._upperTracks.length) {
            this._upperTracks.push({
                solid:  Number.POSITIVE_INFINITY,
                text:   Number.POSITIVE_INFINITY,
                line:   Number.POSITIVE_INFINITY
            });
        }
        for (var i = firstIndex; i < index; i++) {
            occupiedTrackVisitor(this._upperTracks[i]);
        }
    } else {
        if (index == this._lowerTracks.length) {
            this._lowerTracks.push({
                solid:  Number.POSITIVE_INFINITY,
                text:   Number.POSITIVE_INFINITY,
                line:   Number.POSITIVE_INFINITY
            });
        }
        for (var i = firstIndex; i < index; i++) {
            occupiedTrackVisitor(this._lowerTracks[i]);
        }
    }
    return result;
};

Timeline.DetailedEventPainter.prototype._findFreeLowerTrackForText = function(index, edge) {
    for (; index < this._lowerTracks.length; index++) {
        var t = this._lowerTracks[index];
        if (Math.min(t.solid, t.text) >= edge) {
            break;
        }
    }
    return index;
};

Timeline.DetailedEventPainter.prototype._findFreeUpperTrackForText = function(index, edge) {
    for (; index < this._upperTracks.length; index++) {
        var t = this._upperTracks[index];
        if (Math.min(t.solid, t.text) >= edge) {
            break;
        }
    }
    return index;
};

Timeline.DetailedEventPainter.prototype._getTrackData = function(index) {
    return (index < 0) ? this._upperTracks[-index - 1] : this._lowerTracks[index];
};

Timeline.DetailedEventPainter.prototype._paintEventLine = function(evt, left, startTrack, endTrack, metrics, theme) {
    var top = Math.round(metrics.trackOffset + startTrack * metrics.trackIncrement + metrics.trackHeight / 2);
    var height = Math.round(Math.abs(endTrack - startTrack) * metrics.trackIncrement);
    
    var lineStyle = "1px solid " + theme.event.label.lineColor;
    var lineDiv = this._timeline.getDocument().createElement("div");
    lineDiv.style.position = "absolute";
    lineDiv.style.left = left + "px";
    lineDiv.style.width = theme.event.label.offsetFromLine + "px";
    lineDiv.style.height = height + "px";
    if (startTrack > endTrack) {
        lineDiv.style.top = (top - height) + "px";
        lineDiv.style.borderTop = lineStyle;
    } else {
        lineDiv.style.top = top + "px";
        lineDiv.style.borderBottom = lineStyle;
    }
    lineDiv.style.borderLeft = lineStyle;
    this._lineLayer.appendChild(lineDiv);
};

Timeline.DetailedEventPainter.prototype._paintEventIcon = function(evt, iconTrack, left, metrics, theme) {
    var icon = evt.getIcon();
    icon = icon != null ? icon : metrics.icon;
    
    var middle = metrics.trackOffset + iconTrack * metrics.trackIncrement + metrics.trackHeight / 2;
    var top = Math.round(middle - metrics.iconHeight / 2);

    var img = SimileAjax.Graphics.createTranslucentImage(icon);
    var iconDiv = this._timeline.getDocument().createElement("div");
    iconDiv.style.position = "absolute";
    iconDiv.style.left = left + "px";
    iconDiv.style.top = top + "px";
    iconDiv.appendChild(img);
    iconDiv.style.cursor = "pointer";
    this._eventLayer.appendChild(iconDiv);
    
    return {
        left:   left,
        top:    top,
        width:  metrics.iconWidth,
        height: metrics.iconHeight,
        elmt:   iconDiv
    };
};

Timeline.DetailedEventPainter.prototype._paintEventLabel = function(evt, text, left, top, width, height, theme) {
    var doc = this._timeline.getDocument();
    
    var labelBackgroundDiv = doc.createElement("div");
    labelBackgroundDiv.style.position = "absolute";
    labelBackgroundDiv.style.left = left + "px";
    labelBackgroundDiv.style.width = width + "px";
    labelBackgroundDiv.style.top = top + "px";
    labelBackgroundDiv.style.height = height + "px";
    labelBackgroundDiv.style.backgroundColor = theme.event.label.backgroundColor;
    SimileAjax.Graphics.setOpacity(labelBackgroundDiv, theme.event.label.backgroundOpacity);
    this._eventLayer.appendChild(labelBackgroundDiv);
    
    var labelDiv = doc.createElement("div");
    labelDiv.style.position = "absolute";
    labelDiv.style.left = left + "px";
    labelDiv.style.width = width + "px";
    labelDiv.style.top = top + "px";
    labelDiv.innerHTML = text;
    labelDiv.style.cursor = "pointer";
    
    var color = evt.getTextColor();
    if (color == null) {
        color = evt.getColor();
    }
    if (color != null) {
        labelDiv.style.color = color;
    }
    
    this._eventLayer.appendChild(labelDiv);
    
    return {
        left:   left,
        top:    top,
        width:  width,
        height: height,
        elmt:   labelDiv
    };
};

Timeline.DetailedEventPainter.prototype._paintEventTape = function(
    evt, iconTrack, startPixel, endPixel, color, opacity, metrics, theme) {
    
    var tapeWidth = endPixel - startPixel;
    var tapeHeight = theme.event.tape.height;
    var middle = metrics.trackOffset + iconTrack * metrics.trackIncrement + metrics.trackHeight / 2;
    var top = Math.round(middle - tapeHeight / 2);
    
    var tapeDiv = this._timeline.getDocument().createElement("div");
    tapeDiv.style.position = "absolute";
    tapeDiv.style.left = startPixel + "px";
    tapeDiv.style.width = tapeWidth + "px";
    tapeDiv.style.top = top + "px";
    tapeDiv.style.height = tapeHeight + "px";
    tapeDiv.style.backgroundColor = color;
    tapeDiv.style.overflow = "hidden";
    tapeDiv.style.cursor = "pointer";
    SimileAjax.Graphics.setOpacity(tapeDiv, opacity);
    
    this._eventLayer.appendChild(tapeDiv);
    
    return {
        left:   startPixel,
        top:    top,
        width:  tapeWidth,
        height: tapeHeight,
        elmt:   tapeDiv
    };
}

Timeline.DetailedEventPainter.prototype._createHighlightDiv = function(highlightIndex, dimensions, theme) {
    if (highlightIndex >= 0) {
        var doc = this._timeline.getDocument();
        var eventTheme = theme.event;
        
        var color = eventTheme.highlightColors[Math.min(highlightIndex, eventTheme.highlightColors.length - 1)];
        
        var div = doc.createElement("div");
        div.style.position = "absolute";
        div.style.overflow = "hidden";
        div.style.left =    (dimensions.left - 2) + "px";
        div.style.width =   (dimensions.width + 4) + "px";
        div.style.top =     (dimensions.top - 2) + "px";
        div.style.height =  (dimensions.height + 4) + "px";
        div.style.background = color;
        
        this._highlightLayer.appendChild(div);
    }
};

Timeline.DetailedEventPainter.prototype._onClickInstantEvent = function(icon, domEvt, evt) {
    var c = SimileAjax.DOM.getPageCoordinates(icon);
    this._showBubble(
        c.left + Math.ceil(icon.offsetWidth / 2), 
        c.top + Math.ceil(icon.offsetHeight / 2),
        evt
    );
    this._fireOnSelect(evt.getID());
    
    domEvt.cancelBubble = true;
    SimileAjax.DOM.cancelEvent(domEvt);
    return false;
};

Timeline.DetailedEventPainter.prototype._onClickDurationEvent = function(target, domEvt, evt) {
    if ("pageX" in domEvt) {
        var x = domEvt.pageX;
        var y = domEvt.pageY;
    } else {
        var c = SimileAjax.DOM.getPageCoordinates(target);
        var x = domEvt.offsetX + c.left;
        var y = domEvt.offsetY + c.top;
    }
    this._showBubble(x, y, evt);
    this._fireOnSelect(evt.getID());
    
    domEvt.cancelBubble = true;
    SimileAjax.DOM.cancelEvent(domEvt);
    return false;
};

Timeline.DetailedEventPainter.prototype.showBubble = function(evt) {
    var elmt = this._eventIdToElmt[evt.getID()];
    if (elmt) {
        var c = SimileAjax.DOM.getPageCoordinates(elmt);
        this._showBubble(c.left + elmt.offsetWidth / 2, c.top + elmt.offsetHeight / 2, evt);
    }
};

Timeline.DetailedEventPainter.prototype._showBubble = function(x, y, evt) {
    var div = document.createElement("div");
    evt.fillInfoBubble(div, this._params.theme, this._band.getLabeller());
    
    SimileAjax.WindowManager.cancelPopups();
    SimileAjax.Graphics.createBubbleForContentAndPoint(div, x, y, this._params.theme.event.bubble.width);
};

Timeline.DetailedEventPainter.prototype._fireOnSelect = function(eventID) {
    for (var i = 0; i < this._onSelectListeners.length; i++) {
        this._onSelectListeners[i](eventID);
    }
};
/*==================================================
 *  Overview Event Painter
 *==================================================
 */

Timeline.OverviewEventPainter = function(params) {
    this._params = params;
    this._onSelectListeners = [];
    
    this._filterMatcher = null;
    this._highlightMatcher = null;
};

Timeline.OverviewEventPainter.prototype.initialize = function(band, timeline) {
    this._band = band;
    this._timeline = timeline;
    
    this._eventLayer = null;
    this._highlightLayer = null;
};

Timeline.OverviewEventPainter.prototype.addOnSelectListener = function(listener) {
    this._onSelectListeners.push(listener);
};

Timeline.OverviewEventPainter.prototype.removeOnSelectListener = function(listener) {
    for (var i = 0; i < this._onSelectListeners.length; i++) {
        if (this._onSelectListeners[i] == listener) {
            this._onSelectListeners.splice(i, 1);
            break;
        }
    }
};

Timeline.OverviewEventPainter.prototype.getFilterMatcher = function() {
    return this._filterMatcher;
};

Timeline.OverviewEventPainter.prototype.setFilterMatcher = function(filterMatcher) {
    this._filterMatcher = filterMatcher;
};

Timeline.OverviewEventPainter.prototype.getHighlightMatcher = function() {
    return this._highlightMatcher;
};

Timeline.OverviewEventPainter.prototype.setHighlightMatcher = function(highlightMatcher) {
    this._highlightMatcher = highlightMatcher;
};

Timeline.OverviewEventPainter.prototype.paint = function() {
    var eventSource = this._band.getEventSource();
    if (eventSource == null) {
        return;
    }
    
    this._prepareForPainting();
    
    var eventTheme = this._params.theme.event;
    var metrics = {
        trackOffset:    eventTheme.overviewTrack.offset,
        trackHeight:    eventTheme.overviewTrack.height,
        trackGap:       eventTheme.overviewTrack.gap,
        trackIncrement: eventTheme.overviewTrack.height + eventTheme.overviewTrack.gap
    }
    
    var minDate = this._band.getMinDate();
    var maxDate = this._band.getMaxDate();
    
    var filterMatcher = (this._filterMatcher != null) ? 
        this._filterMatcher :
        function(evt) { return true; };
    var highlightMatcher = (this._highlightMatcher != null) ? 
        this._highlightMatcher :
        function(evt) { return -1; };
    
    var iterator = eventSource.getEventReverseIterator(minDate, maxDate);
    while (iterator.hasNext()) {
        var evt = iterator.next();
        if (filterMatcher(evt)) {
            this.paintEvent(evt, metrics, this._params.theme, highlightMatcher(evt));
        }
    }
    
    this._highlightLayer.style.display = "block";
    this._eventLayer.style.display = "block";
};

Timeline.OverviewEventPainter.prototype.softPaint = function() {
};

Timeline.OverviewEventPainter.prototype._prepareForPainting = function() {
    var band = this._band;
        
    this._tracks = [];
    
    if (this._highlightLayer != null) {
        band.removeLayerDiv(this._highlightLayer);
    }
    this._highlightLayer = band.createLayerDiv(105, "timeline-band-highlights");
    this._highlightLayer.style.display = "none";
    
    if (this._eventLayer != null) {
        band.removeLayerDiv(this._eventLayer);
    }
    this._eventLayer = band.createLayerDiv(110, "timeline-band-events");
    this._eventLayer.style.display = "none";
};

Timeline.OverviewEventPainter.prototype.paintEvent = function(evt, metrics, theme, highlightIndex) {
    if (evt.isInstant()) {
        this.paintInstantEvent(evt, metrics, theme, highlightIndex);
    } else {
        this.paintDurationEvent(evt, metrics, theme, highlightIndex);
    }
};

Timeline.OverviewEventPainter.prototype.paintInstantEvent = function(evt, metrics, theme, highlightIndex) {
    var startDate = evt.getStart();
    var startPixel = Math.round(this._band.dateToPixelOffset(startDate));
    
    var color = evt.getColor();
    color = color != null ? color : theme.event.duration.color;
    
    var tickElmtData = this._paintEventTick(evt, startPixel, color, 100, metrics, theme);
    
    this._createHighlightDiv(highlightIndex, tickElmtData, theme);
};

Timeline.OverviewEventPainter.prototype.paintDurationEvent = function(evt, metrics, theme, highlightIndex) {
    var latestStartDate = evt.getLatestStart();
    var earliestEndDate = evt.getEarliestEnd();
    
    var latestStartPixel = Math.round(this._band.dateToPixelOffset(latestStartDate));
    var earliestEndPixel = Math.round(this._band.dateToPixelOffset(earliestEndDate));
    
    var tapeTrack = 0;
    for (; tapeTrack < this._tracks.length; tapeTrack++) {
        if (earliestEndPixel < this._tracks[tapeTrack]) {
            break;
        }
    }
    this._tracks[tapeTrack] = earliestEndPixel;
    
    var color = evt.getColor();
    color = color != null ? color : theme.event.duration.color;
    
    var tapeElmtData = this._paintEventTape(evt, tapeTrack, latestStartPixel, earliestEndPixel, color, 100, metrics, theme);
    
    this._createHighlightDiv(highlightIndex, tapeElmtData, theme);
};

Timeline.OverviewEventPainter.prototype._paintEventTape = function(
    evt, track, left, right, color, opacity, metrics, theme) {
    
    var top = metrics.trackOffset + track * metrics.trackIncrement;
    var width = right - left;
    var height = metrics.trackHeight;
    
    var tapeDiv = this._timeline.getDocument().createElement("div");
    tapeDiv.style.position = "absolute";
    tapeDiv.style.left = left + "px";
    tapeDiv.style.width = width + "px";
    tapeDiv.style.top = top + "px";
    tapeDiv.style.height = height + "px";
    tapeDiv.style.backgroundColor = color;
    tapeDiv.style.overflow = "hidden";
    SimileAjax.Graphics.setOpacity(tapeDiv, opacity);
    
    this._eventLayer.appendChild(tapeDiv);
    
    return {
        left:   left,
        top:    top,
        width:  width,
        height: height,
        elmt:   tapeDiv
    };
}

Timeline.OverviewEventPainter.prototype._paintEventTick = function(
    evt, left, color, opacity, metrics, theme) {
    
    var height = theme.event.overviewTrack.tickHeight;
    var top = metrics.trackOffset - height;
    var width = 1;
    
    var tickDiv = this._timeline.getDocument().createElement("div");
    tickDiv.style.position = "absolute";
    tickDiv.style.left = left + "px";
    tickDiv.style.width = width + "px";
    tickDiv.style.top = top + "px";
    tickDiv.style.height = height + "px";
    tickDiv.style.backgroundColor = color;
    tickDiv.style.overflow = "hidden";
    SimileAjax.Graphics.setOpacity(tickDiv, opacity);
    
    this._eventLayer.appendChild(tickDiv);
    
    return {
        left:   left,
        top:    top,
        width:  width,
        height: height,
        elmt:   tickDiv
    };
}

Timeline.OverviewEventPainter.prototype._createHighlightDiv = function(highlightIndex, dimensions, theme) {
    if (highlightIndex >= 0) {
        var doc = this._timeline.getDocument();
        var eventTheme = theme.event;
        
        var color = eventTheme.highlightColors[Math.min(highlightIndex, eventTheme.highlightColors.length - 1)];
        
        var div = doc.createElement("div");
        div.style.position = "absolute";
        div.style.overflow = "hidden";
        div.style.left =    (dimensions.left - 1) + "px";
        div.style.width =   (dimensions.width + 2) + "px";
        div.style.top =     (dimensions.top - 1) + "px";
        div.style.height =  (dimensions.height + 2) + "px";
        div.style.background = color;
        
        this._highlightLayer.appendChild(div);
    }
};

Timeline.OverviewEventPainter.prototype.showBubble = function(evt) {
    // not implemented
};
/*==================================================
 *  Span Highlight Decorator
 *==================================================
 */

Timeline.SpanHighlightDecorator = function(params) {
    this._unit = ("unit" in params) ? params.unit : SimileAjax.NativeDateUnit;
    this._startDate = (typeof params.startDate == "string") ? 
        this._unit.parseFromObject(params.startDate) : params.startDate;
    this._endDate = (typeof params.endDate == "string") ?
        this._unit.parseFromObject(params.endDate) : params.endDate;
    this._startLabel = params.startLabel;
    this._endLabel = params.endLabel;
    this._color = params.color;
    this._opacity = ("opacity" in params) ? params.opacity : 100;
};

Timeline.SpanHighlightDecorator.prototype.initialize = function(band, timeline) {
    this._band = band;
    this._timeline = timeline;
    
    this._layerDiv = null;
};

Timeline.SpanHighlightDecorator.prototype.paint = function() {
    if (this._layerDiv != null) {
        this._band.removeLayerDiv(this._layerDiv);
    }
    this._layerDiv = this._band.createLayerDiv(10);
    this._layerDiv.setAttribute("name", "span-highlight-decorator"); // for debugging
    this._layerDiv.style.display = "none";
    
    var minDate = this._band.getMinDate();
    var maxDate = this._band.getMaxDate();
    
    if (this._unit.compare(this._startDate, maxDate) < 0 &&
        this._unit.compare(this._endDate, minDate) > 0) {
        
        minDate = this._unit.later(minDate, this._startDate);
        maxDate = this._unit.earlier(maxDate, this._endDate);
        
        var minPixel = this._band.dateToPixelOffset(minDate);
        var maxPixel = this._band.dateToPixelOffset(maxDate);
        
        var doc = this._timeline.getDocument();
        
        var createTable = function() {
            var table = doc.createElement("table");
            table.insertRow(0).insertCell(0);
            return table;
        };
    
        var div = doc.createElement("div");
        div.style.position = "absolute";
        div.style.overflow = "hidden";
        div.style.background = this._color;
        if (this._opacity < 100) {
            SimileAjax.Graphics.setOpacity(div, this._opacity);
        }
        this._layerDiv.appendChild(div);
            
        var tableStartLabel = createTable();
        tableStartLabel.style.position = "absolute";
        tableStartLabel.style.overflow = "hidden";
        tableStartLabel.style.fontSize = "200%";
        tableStartLabel.style.fontWeight = "bold";
        tableStartLabel.style.color = this._color;
        tableStartLabel.rows[0].cells[0].innerHTML = this._startLabel;
        this._layerDiv.appendChild(tableStartLabel);
        
        var tableEndLabel = createTable();
        tableEndLabel.style.position = "absolute";
        tableEndLabel.style.overflow = "hidden";
        tableEndLabel.style.fontSize = "200%";
        tableEndLabel.style.fontWeight = "bold";
        tableEndLabel.style.color = this._color;
        tableEndLabel.rows[0].cells[0].innerHTML = this._endLabel;
        this._layerDiv.appendChild(tableEndLabel);
        
        if (this._timeline.isHorizontal()) {
            div.style.left = minPixel + "px";
            div.style.width = (maxPixel - minPixel) + "px";
            div.style.top = "0px";
            div.style.height = "100%";
            
            tableStartLabel.style.right = (this._band.getTotalViewLength() - minPixel) + "px";
            tableStartLabel.style.width = (this._startLabel.length) + "em";
            tableStartLabel.style.top = "0px";
            tableStartLabel.style.height = "100%";
            tableStartLabel.style.textAlign = "right";
            tableStartLabel.rows[0].style.verticalAlign = "top";
            
            tableEndLabel.style.left = maxPixel + "px";
            tableEndLabel.style.width = (this._endLabel.length) + "em";
            tableEndLabel.style.top = "0px";
            tableEndLabel.style.height = "100%";
            tableEndLabel.rows[0].style.verticalAlign = "top";
        } else {
            div.style.top = minPixel + "px";
            div.style.height = (maxPixel - minPixel) + "px";
            div.style.left = "0px";
            div.style.width = "100%";
            
            tableStartLabel.style.bottom = minPixel + "px";
            tableStartLabel.style.height = "1.5px";
            tableStartLabel.style.left = "0px";
            tableStartLabel.style.width = "100%";
            
            tableEndLabel.style.top = maxPixel + "px";
            tableEndLabel.style.height = "1.5px";
            tableEndLabel.style.left = "0px";
            tableEndLabel.style.width = "100%";
        }
    }
    this._layerDiv.style.display = "block";
};

Timeline.SpanHighlightDecorator.prototype.softPaint = function() {
};

/*==================================================
 *  Point Highlight Decorator
 *==================================================
 */

Timeline.PointHighlightDecorator = function(params) {
    this._unit = ("unit" in params) ? params.unit : SimileAjax.NativeDateUnit;
    this._date = (typeof params.date == "string") ? 
        this._unit.parseFromObject(params.date) : params.date;
    this._width = ("width" in params) ? params.width : 10;
    this._color = params.color;
    this._opacity = ("opacity" in params) ? params.opacity : 100;
};

Timeline.PointHighlightDecorator.prototype.initialize = function(band, timeline) {
    this._band = band;
    this._timeline = timeline;
    
    this._layerDiv = null;
};

Timeline.PointHighlightDecorator.prototype.paint = function() {
    if (this._layerDiv != null) {
        this._band.removeLayerDiv(this._layerDiv);
    }
    this._layerDiv = this._band.createLayerDiv(10);
    this._layerDiv.setAttribute("name", "span-highlight-decorator"); // for debugging
    this._layerDiv.style.display = "none";
    
    var minDate = this._band.getMinDate();
    var maxDate = this._band.getMaxDate();
    
    if (this._unit.compare(this._date, maxDate) < 0 &&
        this._unit.compare(this._date, minDate) > 0) {
        
        var pixel = this._band.dateToPixelOffset(this._date);
        var minPixel = pixel - Math.round(this._width / 2);
        
        var doc = this._timeline.getDocument();
    
        var div = doc.createElement("div");
        div.style.position = "absolute";
        div.style.overflow = "hidden";
        div.style.background = this._color;
        if (this._opacity < 100) {
            SimileAjax.Graphics.setOpacity(div, this._opacity);
        }
        this._layerDiv.appendChild(div);
            
        if (this._timeline.isHorizontal()) {
            div.style.left = minPixel + "px";
            div.style.width = this._width + "px";
            div.style.top = "0px";
            div.style.height = "100%";
        } else {
            div.style.top = minPixel + "px";
            div.style.height = this._width + "px";
            div.style.left = "0px";
            div.style.width = "100%";
        }
    }
    this._layerDiv.style.display = "block";
};

Timeline.PointHighlightDecorator.prototype.softPaint = function() {
};
/*==================================================
 *  Default Unit
 *==================================================
 */

Timeline.NativeDateUnit = new Object();

Timeline.NativeDateUnit.createLabeller = function(locale, timeZone) {
    return new Timeline.GregorianDateLabeller(locale, timeZone);
};

Timeline.NativeDateUnit.makeDefaultValue = function() {
    return new Date();
};

Timeline.NativeDateUnit.cloneValue = function(v) {
    return new Date(v.getTime());
};

Timeline.NativeDateUnit.getParser = function(format) {
    if (typeof format == "string") {
        format = format.toLowerCase();
    }
    return (format == "iso8601" || format == "iso 8601") ?
        Timeline.DateTime.parseIso8601DateTime : 
        Timeline.DateTime.parseGregorianDateTime;
};

Timeline.NativeDateUnit.parseFromObject = function(o) {
    return Timeline.DateTime.parseGregorianDateTime(o);
};

Timeline.NativeDateUnit.toNumber = function(v) {
    return v.getTime();
};

Timeline.NativeDateUnit.fromNumber = function(n) {
    return new Date(n);
};

Timeline.NativeDateUnit.compare = function(v1, v2) {
    var n1, n2;
    if (typeof v1 == "object") {
        n1 = v1.getTime();
    } else {
        n1 = Number(v1);
    }
    if (typeof v2 == "object") {
        n2 = v2.getTime();
    } else {
        n2 = Number(v2);
    }
    
    return n1 - n2;
};

Timeline.NativeDateUnit.earlier = function(v1, v2) {
    return Timeline.NativeDateUnit.compare(v1, v2) < 0 ? v1 : v2;
};

Timeline.NativeDateUnit.later = function(v1, v2) {
    return Timeline.NativeDateUnit.compare(v1, v2) > 0 ? v1 : v2;
};

Timeline.NativeDateUnit.change = function(v, n) {
    return new Date(v.getTime() + n);
};

////////////////////////////// TIMEPLOT LOADER ////////////////////////////////
(function() {

    var local = true;

    // Load Timeplot if it's not already loaded (after SimileAjax and Timeline)
    var loadTimeplot = function() {

        if (typeof window.Timeplot != "undefined") {
            return;
        }
        
        window.Timeplot = {
            loaded:     false,
            params:     { bundle: true, autoCreate: true },
            namespace:  "http://simile.mit.edu/2007/06/timeplot#",
            importers:  {}
        };
        
        var locales = [ "en" ];

        var defaultClientLocales = ("language" in navigator ? navigator.language : navigator.browserLanguage).split(";");
        for (var l = 0; l < defaultClientLocales.length; l++) {
            var locale = defaultClientLocales[l];
            if (locale != "en") {
                var segments = locale.split("-");
                if (segments.length > 1 && segments[0] != "en") {
                    locales.push(segments[0]);
                }
                locales.push(locale);
            }
        }

        var paramTypes = { bundle:Boolean, autoCreate:Boolean };

        if (Timeplot.params.locale) { // ISO-639 language codes,
            // optional ISO-3166 country codes (2 characters)
            if (Timeplot.params.locale != "en") {
                var segments = Timeplot.params.locale.split("-");
                if (segments.length > 1 && segments[0] != "en") {
                    locales.push(segments[0]);
                }
                locales.push(Timeplot.params.locale);
            }
        }
        
        var canvas = document.createElement("canvas");

        window.SimileAjax_onLoad = function() {
            //if (local && window.console.open) window.console.open();
            if (Timeplot.params.callback) {
                eval(Timeplot.params.callback + "()");
            }
        }
        
        if (typeof Simile_urlPrefix == "string") {
            Timeplot.urlPrefix = Simile_urlPrefix + '/timeplot/';
        }
        
        Timeplot.loaded = true;
    };

    // Load Timeline if it's not already loaded (after SimileAjax and before Timeplot)
    var loadTimeline = function() {
        if (typeof Timeline != "undefined") {
            loadTimeplot();
        } else {
            window.SimileAjax_onLoad = loadTimeplot;
        }
    };
    
    // Load SimileAjax if it's not already loaded
    if (typeof SimileAjax == "undefined") {
        window.SimileAjax_onLoad = loadTimeline;
    } else {
        loadTimeline();
    }
})();
/**
 * Timeplot
 * 
 * @fileOverview Timeplot
 * @name Timeplot
 */

Timeline.Debug = SimileAjax.Debug; // timeline uses it's own debug system which is not as advanced
var log = SimileAjax.Debug.log; // shorter name is easier to use

/*
 * This function is used to implement a raw but effective OOP-like inheritance
 * in various Timeplot classes.
 */
Object.extend = function(destination, source) {
    for (var property in source) {
        destination[property] = source[property];
    }
    return destination;
}

// ---------------------------------------------

/**
 * Create a timeplot attached to the given element and using the configuration from the given array of PlotInfos
 */
Timeplot.create = function(elmt, plotInfos) {
    return new Timeplot._Impl(elmt, plotInfos);
};

/**
 * Create a PlotInfo configuration from the given map of params
 */
Timeplot.createPlotInfo = function(params) {
    return {   
        id:                ("id" in params) ? params.id : "p" + Math.round(Math.random() * 1000000),
        dataSource:        ("dataSource" in params) ? params.dataSource : null,
        eventSource:       ("eventSource" in params) ? params.eventSource : null,
        timeGeometry:      ("timeGeometry" in params) ? params.timeGeometry : new Timeplot.DefaultTimeGeometry(),
        valueGeometry:     ("valueGeometry" in params) ? params.valueGeometry : new Timeplot.DefaultValueGeometry(),
        timeZone:          ("timeZone" in params) ? params.timeZone : 0,
        fillColor:         ("fillColor" in params) ? ((params.fillColor == "string") ? new Timeplot.Color(params.fillColor) : params.fillColor) : null,
        fillGradient:      ("fillGradient" in params) ? params.fillGradient : true,
        fillFrom:          ("fillFrom" in params) ? params.fillFrom : Number.NEGATIVE_INFINITY,
        lineColor:         ("lineColor" in params) ? ((params.lineColor == "string") ? new Timeplot.Color(params.lineColor) : params.lineColor) : new Timeplot.Color("#606060"),
        lineWidth:         ("lineWidth" in params) ? params.lineWidth : 1.0,
        dotRadius:         ("dotRadius" in params) ? params.dotRadius : 2.0,
        dotColor:          ("dotColor" in params) ? params.dotColor : null,
        eventLineWidth:    ("eventLineWidth" in params) ? params.eventLineWidth : 1.0,
        showValues:        ("showValues" in params) ? params.showValues : false,
        roundValues:       ("roundValues" in params) ? params.roundValues : true,
        valuesOpacity:     ("valuesOpacity" in params) ? params.valuesOpacity : 75,
        bubbleWidth:       ("bubbleWidth" in params) ? params.bubbleWidth : 300,
        bubbleHeight:      ("bubbleHeight" in params) ? params.bubbleHeight : 200
    };
};

// -------------------------------------------------------

/**
 * This is the implementation of the Timeplot object.
 *  
 * @constructor 
 */
Timeplot._Impl = function(elmt, plotInfos) {
	this._id = "t" + Math.round(Math.random() * 1000000);
    this._containerDiv = elmt;
    this._plotInfos = plotInfos;
    this._painters = {
        background: [],
        foreground: []
    };
    this._painter = null;
    this._active = false;
    this._upright = false;
    this._initialize();
};

Timeplot._Impl.prototype = {

    dispose: function() {
        for (var i = 0; i < this._plots.length; i++) {
            this._plots[i].dispose();
        }
        this._plots = null;
        this._plotsInfos = null;
        this._containerDiv.innerHTML = "";
    },
    
    /**
     * Returns the main container div this timeplot is operating on.
     */
    getElement: function() {
    	return this._containerDiv;
    },
    
    /**
     * Returns document this timeplot belongs to.
     */
    getDocument: function() {
        return this._containerDiv.ownerDocument;
    },

    /**
     * Append the given element to the timeplot DOM
     */
    add: function(div) {
        this._containerDiv.appendChild(div);
    },

    /**
     * Remove the given element from the timeplot DOM
     */
    remove: function(div) {
        this._containerDiv.removeChild(div);
    },

    /**
     * Add a painter to the timeplot
     */
    addPainter: function(layerName, painter) {
        var layer = this._painters[layerName];
        if (layer) {
            for (var i = 0; i < layer.length; i++) {
                if (layer[i].context._id == painter.context._id) {
                    return;
                }
            }
            layer.push(painter);
        }
    },
    
    /**
     * Remove a painter from the timeplot
     */
    removePainter: function(layerName, painter) {
        var layer = this._painters[layerName];
        if (layer) {
            for (var i = 0; i < layer.length; i++) {
                if (layer[i].context._id == painter.context._id) {
                    layer.splice(i, 1);
                    break;
                }
            }
        }
    },
    
    /**
     * Get the width in pixels of the area occupied by the entire timeplot in the page
     */
    getWidth: function() {
    	return this._containerDiv.clientWidth;
    },

    /**
     * Get the height in pixels of the area occupied by the entire timeplot in the page
     */
    getHeight: function() {
        return this._containerDiv.clientHeight;
    },
    
    /**
     * Get the drawing canvas associated with this timeplot
     */
    getCanvas: function() {
        return this._canvas;
    },
    
    /**
     * <p>Load the data from the given url into the given eventSource, using
     * the given separator to parse the columns and preprocess it before parsing
     * thru the optional filter function. The filter is useful for when 
     * the data is row-oriented but the format is not compatible with the
     * one that Timeplot expects.</p> 
     * 
     * <p>Here is an example of a filter that changes dates in the form 'yyyy/mm/dd'
     * in the required 'yyyy-mm-dd' format:
     * <pre>var dataFilter = function(data) {
     *     for (var i = 0; i < data.length; i++) {
     *         var row = data[i];
     *         row[0] = row[0].replace(/\//g,"-");
     *     }
     *     return data;
     * };</pre></p>
     */
    loadText: function(url, separator, eventSource, filter) {
    	if (this._active) {
	        var tp = this;
	        
	        var fError = function(statusText, status, xmlhttp) {
	            alert("Failed to load data xml from " + url + "\n" + statusText);
	            tp.hideLoadingMessage();
	        };
	        
	        var fDone = function(xmlhttp) {
	            try {
	                eventSource.loadText(xmlhttp.responseText, separator, url, filter);
	            } catch (e) {
	                SimileAjax.Debug.exception(e);
	            } finally {
	                tp.hideLoadingMessage();
	            }
	        };
	        
	        this.showLoadingMessage();
	        window.setTimeout(function() { SimileAjax.XmlHttp.get(url, fError, fDone); }, 0);
    	}
    },

    /**
     * Load event data from the given url into the given eventSource, using
     * the Timeline XML event format.
     */
    loadXML: function(url, eventSource) {
    	if (this._active) {
	        var tl = this;
	        
	        var fError = function(statusText, status, xmlhttp) {
	            alert("Failed to load data xml from " + url + "\n" + statusText);
	            tl.hideLoadingMessage();
	        };
	        
	        var fDone = function(xmlhttp) {
	            try {
	                var xml = xmlhttp.responseXML;
	                if (!xml.documentElement && xmlhttp.responseStream) {
	                    xml.load(xmlhttp.responseStream);
	                } 
	                eventSource.loadXML(xml, url);
	            } finally {
	                tl.hideLoadingMessage();
	            }
	        };
	        
	        this.showLoadingMessage();
	        window.setTimeout(function() { SimileAjax.XmlHttp.get(url, fError, fDone); }, 0);
    	}
    },
    
    /**
     * Overlay a 'div' element filled with the given text and styles to this timeplot
     * This is used to implement labels since canvas does not support drawing text.
     */
    putText: function(id, text, clazz, styles) {
        var div = this.putDiv(id, "timeplot-div " + clazz, styles);
        div.innerHTML = text;
        return div;
    },

    /**
     * Overlay a 'div' element, with the given class and the given styles to this timeplot.
     * This is used for labels and horizontal and vertical grids. 
     */
    putDiv: function(id, clazz, styles) {
    	var tid = this._id + "-" + id;
    	var div = document.getElementById(tid);
    	if (!div) {
	        var container = this._containerDiv.firstChild; // get the divs container
	        div = document.createElement("div");
	        div.setAttribute("id",tid);
	        container.appendChild(div);
    	}
        div.setAttribute("class","timeplot-div " + clazz);
        div.setAttribute("className","timeplot-div " + clazz);
        this.placeDiv(div,styles);
        return div;
    },
    
    /**
     * AMO change
     * Removes an overlayed 'div' element if it exists
     */
    removeDiv: function(id) {
        var div = document.getElementById(this._id + "-" + id);
        
        if (div) div.parentNode.removeChild(div);
    },
    
    /**
     * Associate the given map of styles to the given element. 
     * In case such styles indicate position (left,right,top,bottom) correct them
     * with the padding information so that they align to the 'internal' area
     * of the timeplot.
     */
    placeDiv: function(div, styles) {
        if (styles) {
            for (style in styles) {
                if (style == "left") {
                    styles[style] += this._paddingX;
                    styles[style] += "px";
                } else if (style == "right") {
                    styles[style] += this._paddingX;
                    styles[style] += "px";
                } else if (style == "top") {
                    styles[style] += this._paddingY;
                    styles[style] += "px";
                } else if (style == "bottom") {
                    styles[style] += this._paddingY;
                    styles[style] += "px";
                } else if (style == "width") {
                    if (styles[style] < 0) styles[style] = 0;
                    styles[style] += "px";
                } else if (style == "height") {
                    if (styles[style] < 0) styles[style] = 0;
                    styles[style] += "px";
                }
                div.style[style] = styles[style];
            }
        }
    },
    
    /**
     * AMO change
     * Adds a new plot
     * If update = true, adds a new plot to an already processed dataset
     */
    addPlot: function(plotInfo, update) {
        if (update) this._plotInfos.push(plotInfo);
        
        var timeplot = this;
        var painter = {
            onAddMany: function() { timeplot.update(); },
            onClear:   function() { timeplot.update(); }
        }
        
        var plot = new Timeplot.Plot(this, plotInfo);
        var dataSource = plot.getDataSource();
        if (dataSource) {
            if (update) dataSource._process();
            dataSource.addListener(painter);
        }
        this.addPainter("background", {
            context: plot.getTimeGeometry(),
            action: plot.getTimeGeometry().paint
        });
        this.addPainter("background", {
            context: plot.getValueGeometry(),
            action: plot.getValueGeometry().paint
        });
        this.addPainter("foreground", {
            context: plot,
            action: plot.paint
        });
        plot.initialize();
        this._plots.push(plot);
        
        if (update) this.update();
        
        return true;
    },
    
    /**
     * AMO change
     * Removes a plot and repaints
     */
    removePlot: function(plotID) {
        var removed = false;
        
        if (this._plots) {
            for (var i = 0; i < this._plots.length; i++) {
                if (this._plots[i]._id == plotID) {
                    /* Removing only this painter preserves the value and time
                      axis. plot.dispose() isn't used to preserve dataSource in
                      case the plot is added back. */
                    this.removePainter("foreground", {context: this._plots[i]});
                    
                    this.removeDiv(this._plots[i]._id + 'valueflag');
                    this.removeDiv(this._plots[i]._id + 'valueflagLineLeft');
                    this.removeDiv(this._plots[i]._id + 'valueflagLineRight');
                    this.removeDiv(this._plots[i]._id + 'valuepole');
                    
                    this._plots.splice(i, 1);
                    removed = true;
                }
            }
        }
        
        if (this._plotInfos) {
            for (i = 0; i < this._plotInfos.length; i++) {
                if (this._plotInfos[i].id == plotID) {
                    this._plotInfos.splice(i, 1);
                    removed = true;
                }
            }
        }
        
        if (removed) this.update();
        
        return removed;
    },
    
    /**
     * return a {x,y} map with the location of the given element relative to the 'internal' area of the timeplot
     * (that is, without the container padding)
     */
    locate: function(div) {
    	return {
    		x: div.offsetLeft - this._paddingX,
    		y: div.offsetTop - this._paddingY
    	}
    },
    
    /**
     * Forces timeplot to re-evaluate the various value and time geometries
     * associated with its plot layers and repaint accordingly. This should
     * be invoked after the data in any of the data sources has been
     * modified.
     */
    update: function() {
    	if (this._active) {
	        for (var i = 0; i < this._plots.length; i++) {
	            var plot = this._plots[i];
	            var dataSource = plot.getDataSource();
	            if (dataSource) {
	                var range = dataSource.getRange();
	                if (range) {
	                	plot._valueGeometry.setRange(range);
	                	plot._timeGeometry.setRange(range);
	                }
	            }
	        }
	        this.paint();
    	}
    },
    
    /**
     * Forces timeplot to re-evaluate its own geometry, clear itself and paint.
     * This should be used instead of paint() when you're not sure if the 
     * geometry of the page has changed or not. 
     */
    repaint: function() {
    	if (this._active) {
	        this._prepareCanvas();
	        for (var i = 0; i < this._plots.length; i++) {
	            var plot = this._plots[i];
	            if (plot._timeGeometry) plot._timeGeometry.reset();
	            if (plot._valueGeometry) plot._valueGeometry.reset();
	        }
	        this.paint();
    	}
    },
    
    /**
     * Calls all the painters that were registered to this timeplot and makes them
     * paint the timeplot. This should be used only when you're sure that the geometry
     * of the page hasn't changed.
     * NOTE: painting is performed by a different thread and it's safe to call this
     * function in bursts (as in mousemove or during window resizing
     */
    paint: function() {
        if (this._active && this._painter == null) {
            var timeplot = this;
            this._painter = window.setTimeout(function() {
                timeplot._clearCanvas();
                
                var run = function(action,context) {
                    try {
                        if (context.setTimeplot) context.setTimeplot(timeplot);
                        action.apply(context,[]);
                    } catch (e) {
                        SimileAjax.Debug.exception(e);
                    }
                }
                
                var background = timeplot._painters.background;
                for (var i = 0; i < background.length; i++) {
                    run(background[i].action, background[i].context); 
                }
                var foreground = timeplot._painters.foreground;
                for (var i = 0; i < foreground.length; i++) {
                    run(foreground[i].action, foreground[i].context); 
                }
                
                timeplot._painter = null;
            }, 20);
        }
    },

    _clearCanvas: function() {
    	var canvas = this.getCanvas();
    	var ctx = canvas.getContext('2d');
        ctx.clearRect(0,0,canvas.width,canvas.height);
    },
    
    _prepareCanvas: function() {
        var canvas = this.getCanvas();

        // using jQuery.  note we calculate the average padding; if your
        // padding settings are not symmetrical, the labels will be off
        // since they expect to be centered on the canvas.
        var con = $('#' + this._containerDiv.id);
        this._paddingX = (parseInt(con.css('paddingLeft')) +
                          parseInt(con.css('paddingRight'))) / 2;
        this._paddingY = (parseInt(con.css('paddingTop')) +
                          parseInt(con.css('paddingBottom'))) / 2;

        canvas.width = this.getWidth() - (this._paddingX * 2);
        canvas.height = this.getHeight() - (this._paddingY * 2);

        var ctx = canvas.getContext('2d');
        this._setUpright(ctx, canvas);
        ctx.globalCompositeOperation = 'source-over';
    },

    _setUpright: function(ctx, canvas) {
        // excanvas+IE requires this to be done only once, ever; actual canvas
        // implementations reset and require this for each call to re-layout
        if (!SimileAjax.Platform.browser.isIE) this._upright = false;
        if (!this._upright) {
            this._upright = true;
            ctx.translate(0, canvas.height);
            ctx.scale(1,-1);
        }
    },
    
    _isBrowserSupported: function(canvas) {
    	var browser = SimileAjax.Platform.browser;
    	if ((canvas.getContext && window.getComputedStyle) ||
            (browser.isIE && browser.majorVersion >= 6)) {
        	return true;
    	} else {
    		return false;
    	}
    },
    
    _initialize: function() {
    	
    	// initialize the window manager (used to handle the popups)
    	// NOTE: this is a singleton and it's safe to call multiple times
    	SimileAjax.WindowManager.initialize(); 
    	
        var containerDiv = this._containerDiv;
        var doc = containerDiv.ownerDocument;
    
        // make sure the timeplot div has the right class    
        containerDiv.className = "timeplot-container " + containerDiv.className;
            
        // clean it up if it contains some content
        while (containerDiv.firstChild) {
            containerDiv.removeChild(containerDiv.firstChild);
        }
        
        var canvas = doc.createElement("canvas");
        
        if (this._isBrowserSupported(canvas)) {
            // this is where we'll place the labels
            var labels = doc.createElement("div");
            containerDiv.appendChild(labels);

            this._canvas = canvas;
            canvas.className = "timeplot-canvas";
            containerDiv.appendChild(canvas);
            if(!canvas.getContext && G_vmlCanvasManager) {
                canvas = G_vmlCanvasManager.initElement(this._canvas);
                this._canvas = canvas;
            }
            this._prepareCanvas();
    
            // inserting copyright and link to simile
            var elmtCopyright = SimileAjax.Graphics.createTranslucentImage(Timeplot.urlPrefix + "images/copyright.png");
            elmtCopyright.className = "timeplot-copyright";
            elmtCopyright.title = "Timeplot (c) SIMILE - http://simile.mit.edu/timeplot/";
            SimileAjax.DOM.registerEvent(elmtCopyright, "click", function() { window.location = "http://simile.mit.edu/timeplot/"; });
            containerDiv.appendChild(elmtCopyright);

            // creating painters
            this._plots = [];
            if (this._plotInfos) {
                for (var i = 0; i < this._plotInfos.length; i++) {
                    this.addPlot(this._plotInfos[i]);
                }
            }
                
            // creating loading UI
            var message = SimileAjax.Graphics.createMessageBubble(doc);
            message.containerDiv.className = "timeplot-message-container";
            containerDiv.appendChild(message.containerDiv);
            
            message.contentDiv.className = "timeplot-message";
            message.contentDiv.innerHTML = "<img src='" + Timeplot.urlPrefix + "images/progress-running.gif' /> Loading...";
            
            this.showLoadingMessage = function() { message.containerDiv.style.display = "block"; };
            this.hideLoadingMessage = function() { message.containerDiv.style.display = "none"; };
    
            this._active = true;
            
        } else {
    
            this._message = SimileAjax.Graphics.createMessageBubble(doc);
            this._message.containerDiv.className = "timeplot-message-container";
            this._message.containerDiv.style.top = "15%";
            this._message.containerDiv.style.left = "20%";
            this._message.containerDiv.style.right = "20%";
            this._message.containerDiv.style.minWidth = "20em";
            this._message.contentDiv.className = "timeplot-message";
            this._message.contentDiv.innerHTML = "We're terribly sorry, but your browser is not currently supported by <a href='http://simile.mit.edu/timeplot/'>Timeplot</a>.<br><br> We are working on supporting it in the near future but, for now, see the <a href='http://simile.mit.edu/wiki/Timeplot_Limitations'>list of currently supported browsers</a>.";
            this._message.containerDiv.style.display = "block";

            containerDiv.appendChild(this._message.containerDiv);
    
        }
    }
};
/**
 * Plot Layer
 * 
 * @fileOverview Plot Layer
 * @name Plot
 */
 
/**
 * A plot layer is the main building block for timeplots and it's the object
 * that is responsible for painting the plot itself. Each plot needs to have
 * a time geometry, either a DataSource (for time series
 * plots) or an EventSource (for event plots) and a value geometry in case 
 * of time series plots. Such parameters are passed along
 * in the 'plotInfo' map.
 * 
 * @constructor
 */
Timeplot.Plot = function(timeplot, plotInfo) {
	this._timeplot = timeplot;
    this._canvas = timeplot.getCanvas();
    this._plotInfo = plotInfo;
    this._id = plotInfo.id;
    this._timeGeometry = plotInfo.timeGeometry;
    this._valueGeometry = plotInfo.valueGeometry;
    this._showValues = plotInfo.showValues;
    this._theme = new Timeline.getDefaultTheme();
    this._dataSource = plotInfo.dataSource;
    this._eventSource = plotInfo.eventSource;
    this._bubble = null;
};

Timeplot.Plot.prototype = {
    
    /**
     * Initialize the plot layer
     */
    initialize: function() {
	    if (this._showValues && this._dataSource && this._dataSource.getValue) {
            this._timeFlag = this._timeplot.putDiv("timeflag","timeplot-timeflag");
	        this._valueFlag = this._timeplot.putDiv(this._id + "valueflag","timeplot-valueflag");
	        this._valueFlagLineLeft = this._timeplot.putDiv(this._id + "valueflagLineLeft","timeplot-valueflag-line");
            this._valueFlagLineRight = this._timeplot.putDiv(this._id + "valueflagLineRight","timeplot-valueflag-line");
            if (!this._valueFlagLineLeft.firstChild) {
            	this._valueFlagLineLeft.appendChild(SimileAjax.Graphics.createTranslucentImage(Timeplot.urlPrefix + "images/line_left.png"));
                this._valueFlagLineRight.appendChild(SimileAjax.Graphics.createTranslucentImage(Timeplot.urlPrefix + "images/line_right.png"));
            }
	        this._valueFlagPole = this._timeplot.putDiv(this._id + "valuepole","timeplot-valueflag-pole");

            var opacity = this._plotInfo.valuesOpacity;
            
            SimileAjax.Graphics.setOpacity(this._timeFlag, opacity);
            SimileAjax.Graphics.setOpacity(this._valueFlag, opacity);
            SimileAjax.Graphics.setOpacity(this._valueFlagLineLeft, opacity);
            SimileAjax.Graphics.setOpacity(this._valueFlagLineRight, opacity);
            SimileAjax.Graphics.setOpacity(this._valueFlagPole, opacity);

            var plot = this;
            
		    var mouseOverHandler = function(elmt, evt, target) {
		        plot._valueFlag.style.display = "block";
		        mouseMoveHandler(elmt, evt, target);
		    }
		
		    var day = 24 * 60 * 60 * 1000;
		    var month = 30 * day;
		    
		    var mouseMoveHandler = function(elmt, evt, target) {
		    	if (typeof SimileAjax != "undefined") {
                    var c = plot._canvas;
			        var x = Math.round(SimileAjax.DOM.getEventRelativeCoordinates(evt,plot._canvas).x);
			        if (x > c.width) x = c.width;
			        if (isNaN(x) || x < 0) x = 0;
			        var t = plot._timeGeometry.fromScreen(x);
			        if (t == 0) { // something is wrong
                        plot._valueFlag.style.display = "none";
			        	return;
			        }
			        
			        var v = plot._dataSource.getValue(t);
			        if (plot._plotInfo.roundValues) v = Math.round(v);
			        plot._valueFlag.innerHTML = new String(v);
			        var d = new Date(t);
			        var p = plot._timeGeometry.getPeriod(); 
			        if (p < day) {
			            plot._timeFlag.innerHTML = d.toLocaleTimeString();
			        } else if (p > month) {
                        plot._timeFlag.innerHTML = d.toLocaleDateString();
			        } else {
                        plot._timeFlag.innerHTML = d.toLocaleString();
			        }
			        
			        var tw = plot._timeFlag.clientWidth;
                    var th = plot._timeFlag.clientHeight;
                    var tdw = Math.round(tw / 2);
                    var vw = plot._valueFlag.clientWidth;
                    var vh = plot._valueFlag.clientHeight;
			        var y = plot._valueGeometry.toScreen(v);

                    if (x + tdw > c.width) {
                        var tx = c.width - tdw;
                    } else if (x - tdw < 0) {
                        var tx = tdw;
                    } else {
                    	var tx = x;
                    }

			        if (plot._timeGeometry._timeValuePosition == "top") {
                        plot._timeplot.placeDiv(plot._valueFlagPole, {
                            left: x,
                            top: th - 5,
                            height: c.height - y - th + 6,
                            display: "block"
                        });
				        plot._timeplot.placeDiv(plot._timeFlag,{
				            left: tx - tdw,
				            top: -6,
				            display: "block"
				        });
			        } else {
                        plot._timeplot.placeDiv(plot._valueFlagPole, {
                            left: x,
                            bottom: th - 5,
                            height: y - th + 6,
                            display: "block"
                        });
                        plot._timeplot.placeDiv(plot._timeFlag,{
                            left: tx - tdw,
                            bottom: -6,
                            display: "block"
                        });
			        }
			        
			        if (x + vw + 14 > c.width && y + vh + 4 > c.height) {
                        plot._valueFlagLineLeft.style.display = "none";
	                    plot._timeplot.placeDiv(plot._valueFlagLineRight,{
	                        left: x - 14,
	                        bottom: y - 14,
	                        display: "block"
	                    });
	                    plot._timeplot.placeDiv(plot._valueFlag,{
	                        left: x - vw - 13,
	                        bottom: y - vh - 13,
	                        display: "block"
	                    });
			        } else if (x + vw + 14 > c.width && y + vh + 4 < c.height) {
                        plot._valueFlagLineRight.style.display = "none";
                        plot._timeplot.placeDiv(plot._valueFlagLineLeft,{
                            left: x - 14,
                            bottom: y,
                            display: "block"
                        });
                        plot._timeplot.placeDiv(plot._valueFlag,{
                            left: x - vw - 13,
                            bottom: y + 13,
                            display: "block"
                        });
                    } else if (x + vw + 14 < c.width && y + vh + 4 > c.height) {
                        plot._valueFlagLineRight.style.display = "none";
                        plot._timeplot.placeDiv(plot._valueFlagLineLeft,{
                            left: x,
                            bottom: y - 13,
                            display: "block"
                        });
                        plot._timeplot.placeDiv(plot._valueFlag,{
                            left: x + 13,
                            bottom: y - 13,
                            display: "block"
                        });
			        } else {
                        plot._valueFlagLineLeft.style.display = "none";
                        plot._timeplot.placeDiv(plot._valueFlagLineRight,{
                            left: x,
                            bottom: y,
                            display: "block"
                        });
                        plot._timeplot.placeDiv(plot._valueFlag,{
                            left: x + 13,
                            bottom: y + 13,
                            display: "block"
                        });
			        }
		    	}
		    }

            var timeplotElement = this._timeplot.getElement();
            SimileAjax.DOM.registerEvent(timeplotElement, "mouseover", mouseOverHandler);
            SimileAjax.DOM.registerEvent(timeplotElement, "mousemove", mouseMoveHandler);
	    }
    },

    /**
     * Dispose the plot layer and all the data sources and listeners associated to it
     */
    dispose: function() {
        if (this._dataSource) {
            this._dataSource.removeListener(this._paintingListener);
            this._paintingListener = null;
            this._dataSource.dispose();
            this._dataSource = null;
        }
    },

    /**
     * Return the data source of this plot layer (it could be either a DataSource or an EventSource)
     */
    getDataSource: function() {
        return (this._dataSource) ? this._dataSource : this._eventSource;
    },

    /**
     * Return the time geometry associated with this plot layer
     */
    getTimeGeometry: function() {
        return this._timeGeometry;
    },

    /**
     * Return the value geometry associated with this plot layer
     */
    getValueGeometry: function() {
        return this._valueGeometry;
    },

    /**
     * Paint this plot layer
     */
    paint: function() {
        var ctx = this._canvas.getContext('2d');

        ctx.lineWidth = this._plotInfo.lineWidth;
        ctx.lineJoin = 'miter';

        if (this._dataSource) {     
            if (this._plotInfo.fillColor) {
                if (this._plotInfo.fillGradient) {
                    var gradient = ctx.createLinearGradient(0,this._canvas.height,0,0);
                    gradient.addColorStop(0,this._plotInfo.fillColor.toString());
                    gradient.addColorStop(0.5,this._plotInfo.fillColor.toString());
                    gradient.addColorStop(1, 'rgba(255,255,255,0)');

                    ctx.fillStyle = gradient;
                } else {
                    ctx.fillStyle = this._plotInfo.fillColor.toString();
                }

                ctx.beginPath();
                ctx.moveTo(0,0);
	            this._plot(function(x,y) {
                    ctx.lineTo(x,y);
	            });
                if (this._plotInfo.fillFrom == Number.NEGATIVE_INFINITY) {
                    ctx.lineTo(this._canvas.width, 0);
                } else if (this._plotInfo.fillFrom == Number.POSITIVE_INFINITY) {
                    ctx.lineTo(this._canvas.width, this._canvas.height);
                    ctx.lineTo(0, this._canvas.height);
                } else {
                    ctx.lineTo(this._canvas.width, this._valueGeometry.toScreen(this._plotInfo.fillFrom));
                    ctx.lineTo(0, this._valueGeometry.toScreen(this._plotInfo.fillFrom));
                }
                ctx.fill();
            }
                    
            if (this._plotInfo.lineColor) {
                ctx.strokeStyle = this._plotInfo.lineColor.toString();
	            ctx.beginPath();
                    var first = true;
	            this._plot(function(x,y) {
                        if (first) {
                             first = false;
                             ctx.moveTo(x,y);
                        }
	                ctx.lineTo(x,y);
	            });
	            ctx.stroke();
            }

            if (this._plotInfo.dotColor) {
                ctx.fillStyle = this._plotInfo.dotColor.toString();
                var r = this._plotInfo.dotRadius;
                this._plot(function(x,y) {
                    ctx.beginPath();
                    ctx.arc(x,y,r,0,2*Math.PI,true);
                    ctx.fill();
                });
            }
        }

        if (this._eventSource) {
            var gradient = ctx.createLinearGradient(0,0,0,this._canvas.height);
            gradient.addColorStop(1, 'rgba(255,255,255,0)');

            ctx.strokeStyle = gradient;
            ctx.fillStyle = gradient; 
            ctx.lineWidth = this._plotInfo.eventLineWidth;
            ctx.lineJoin = 'miter';
            
            var i = this._eventSource.getAllEventIterator();
            while (i.hasNext()) {
                var event = i.next();
                var color = event.getColor();
                color = (color) ? new Timeplot.Color(color) : this._plotInfo.lineColor;
                var eventStart = event.getStart().getTime();
                var eventEnd = event.getEnd().getTime();
                if (eventStart == eventEnd) {
                    var c = color.toString();
                    gradient.addColorStop(0, c);
                    var start = this._timeGeometry.toScreen(eventStart);
                    start = Math.floor(start) + 0.5; // center it between two pixels (makes the rendering nicer)
                    var end = start;
                    ctx.beginPath();
                    ctx.moveTo(start,0);
                    ctx.lineTo(start,this._canvas.height);
                    ctx.stroke();
                    var x = start - 4;
                    var w = 7;
                } else {
                	var c = color.toString(0.5);
                    gradient.addColorStop(0, c);
                    var start = this._timeGeometry.toScreen(eventStart);
                    start = Math.floor(start) + 0.5; // center it between two pixels (makes the rendering nicer)
                    var end = this._timeGeometry.toScreen(eventEnd);
                    end = Math.floor(end) + 0.5; // center it between two pixels (makes the rendering nicer)
                    ctx.fillRect(start,0,end - start, this._canvas.height);
                    var x = start;
                    var w = end - start - 1;
                }

                var div = this._timeplot.putDiv(event.getID(),"timeplot-event-box",{
                    left: Math.round(x),
                    width: Math.round(w),
                    top: 0,
                    height: this._canvas.height - 1
                });

                var plot = this;
                var clickHandler = function(event) { 
                    return function(elmt, evt, target) { 
                        var doc = plot._timeplot.getDocument();
                    	plot._closeBubble();
                    	var coords = SimileAjax.DOM.getEventPageCoordinates(evt);
                    	var elmtCoords = SimileAjax.DOM.getPageCoordinates(elmt);
                        plot._bubble = SimileAjax.Graphics.createBubbleForPoint(coords.x, elmtCoords.top + plot._canvas.height, plot._plotInfo.bubbleWidth, plot._plotInfo.bubbleHeight, "bottom");
                        event.fillInfoBubble(plot._bubble.content, plot._theme, plot._timeGeometry.getLabeler());
                    }
                };
                var mouseOverHandler = function(elmt, evt, target) {
                	elmt.oldClass = elmt.className;
                    elmt.className = elmt.className + " timeplot-event-box-highlight";
                };
                var mouseOutHandler = function(elmt, evt, target) {
                    elmt.className = elmt.oldClass;
                    elmt.oldClass = null;
                }
                
                if (!div.instrumented) {
	                SimileAjax.DOM.registerEvent(div, "click"    , clickHandler(event));
	                SimileAjax.DOM.registerEvent(div, "mouseover", mouseOverHandler);
	                SimileAjax.DOM.registerEvent(div, "mouseout" , mouseOutHandler);
		            div.instrumented = true;
                }
            }
        }
    },

    _plot: function(f) {
        var data = this._dataSource.getData();
        if (data) {
	        var times = data.times;
	        var values = data.values;
	        var T = times.length;
	        for (var t = 0; t < T; t++) {
	        	var x = this._timeGeometry.toScreen(times[t]);
	        	var y = this._valueGeometry.toScreen(values[t]);
	            f(x, y);
	        }
        }
    },
    
    _closeBubble: function() {
        if (this._bubble != null) {
            this._bubble.close();
            this._bubble = null;
        }
    }

}/**
 * Sources
 * 
 * @fileOverview Sources
 * @name Sources
 */

/**
 * Timeplot.DefaultEventSource is an extension of Timeline.DefaultEventSource
 * and therefore reuses the exact same event loading subsystem that
 * Timeline uses.
 * 
 * @constructor
 */
Timeplot.DefaultEventSource = function(eventIndex) {
	Timeline.DefaultEventSource.apply(this, arguments);
};

Object.extend(Timeplot.DefaultEventSource.prototype, Timeline.DefaultEventSource.prototype);

/**
 * Function used by Timeplot to load time series data from a text file.
 */
Timeplot.DefaultEventSource.prototype.loadText = function(text, separator, url, filter) {

    if (text == null) {
        return;
    }

    this._events.maxValues = new Array();
    var base = this._getBaseURL(url);

    var dateTimeFormat = 'iso8601';
    var parseDateTimeFunction = this._events.getUnit().getParser(dateTimeFormat);

// AMO change - moved to after filter so we can parse comments
    //var data = this._parseText(text, separator);
var data = text;

    var added = false;

    if (filter) {
        data = filter(data);
    }

    data = this._parseText(data, separator);

    if (data) {
        for (var i = 0; i < data.length; i++){
            var row = data[i];
            if (row.length > 1) {
		        var evt = new Timeplot.DefaultEventSource.NumericEvent(
		            parseDateTimeFunction(row[0]),
		            row.slice(1)
		        );
		        this._events.add(evt);
		        added = true;
            }
        }
    }

    if (added) {
        this._fire("onAddMany", []);
    }
}

/*
 * Parse the data file.
 * 
 * Adapted from http://www.kawa.net/works/js/jkl/js/jkl-parsexml.js by Yusuke Kawasaki
 */
Timeplot.DefaultEventSource.prototype._parseText = function (text, separator) {
    text = text.replace( /\r\n?/g, "\n" ); // normalize newlines
    var pos = 0;
    var len = text.length;
    var table = [];
    while (pos < len) {
        var line = [];
        if (text.charAt(pos) != '#') { // if it's not a comment, process
            while (pos < len) {
                if (text.charAt(pos) == '"') {            // "..." quoted column
                    var nextquote = text.indexOf('"', pos+1 );
                    while (nextquote<len && nextquote > -1) {
                        if (text.charAt(nextquote+1) != '"') {
                            break;                          // end of column
                        }
                        nextquote = text.indexOf('"', nextquote + 2);
                    }
                    if ( nextquote < 0 ) {
                        // unclosed quote
                    } else if (text.charAt(nextquote + 1) == separator) { // end of column
                        var quoted = text.substr(pos + 1, nextquote-pos - 1);
                        quoted = quoted.replace(/""/g,'"');
                        line[line.length] = quoted;
                        pos = nextquote + 2;
                        continue;
                    } else if (text.charAt(nextquote + 1) == "\n" || // end of line
                               len == nextquote + 1 ) {              // end of file
                        var quoted = text.substr(pos + 1, nextquote-pos - 1);
                        quoted = quoted.replace(/""/g,'"');
                        line[line.length] = quoted;
                        pos = nextquote + 2;
                        break;
                    } else {
                        // invalid column
                    }
                }
                var nextseparator = text.indexOf(separator, pos);
                var nextnline = text.indexOf("\n", pos);
                if (nextnline < 0) nextnline = len;
                if (nextseparator > -1 && nextseparator < nextnline) {
                    line[line.length] = text.substr(pos, nextseparator-pos);
                    pos = nextseparator + 1;
                } else {                                    // end of line
                    line[line.length] = text.substr(pos, nextnline-pos);
                    pos = nextnline + 1;
                    break;
                }
            }
        } else { // if it's a comment, ignore
            var nextnline = text.indexOf("\n", pos);
            pos = (nextnline > -1) ? nextnline + 1 : cur;
        }
        if (line.length > 0) {
            table[table.length] = line;                 // push line
        }
    }
    if (table.length < 0) return;                     // null data
    return table;
}

/**
 * Return the range of the loaded data
 */
Timeplot.DefaultEventSource.prototype.getRange = function() {
	var earliestDate = this.getEarliestDate();
	var latestDate = this.getLatestDate();
    return {
        earliestDate: (earliestDate) ? earliestDate : null,
        latestDate: (latestDate) ? latestDate : null,
        min: 0,
        max: 0
    };
}

// -----------------------------------------------------------------------

/**
 * A NumericEvent is an Event that also contains an array of values, 
 * one for each columns in the loaded data file.
 * 
 * @constructor
 */
Timeplot.DefaultEventSource.NumericEvent = function(time, values) {
    this._id = "e" + Math.round(Math.random() * 1000000);
    this._time = time;
    this._values = values;
};

Timeplot.DefaultEventSource.NumericEvent.prototype = {
    getID:          function() { return this._id; },
    getTime:        function() { return this._time; },
    getValues:      function() { return this._values; },

    // these are required by the EventSource
    getStart:       function() { return this._time; },
    getEnd:         function() { return this._time; }
};

// -----------------------------------------------------------------------

/**
 * A DataSource represent an abstract class that represents a monodimensional time series.
 * 
 * @constructor
 */
Timeplot.DataSource = function(eventSource) {
    this._eventSource = eventSource;
    var source = this;
    this._processingListener = {
        onAddMany: function() { source._process(); },
        onClear:   function() { source._clear(); }
    }
    this.addListener(this._processingListener);
    this._listeners = [];
    this._data = null;
    this._range = null;
};

Timeplot.DataSource.prototype = {
  
    _clear: function() {
        this._data = null;
        this._range = null;
    },

    _process: function() {
        this._data = {
            times: new Array(),
            values: new Array()
        };
        this._range = {
            earliestDate: null,
            latestDate: null,
            min: 0,
            max: 0
        };
    },

    /**
     * Return the range of this data source
     */
    getRange: function() {
        return this._range;
    },

    /**
     * Return the actual data that this data source represents.
     * NOTE: _data = { times: [], values: [] }
     */
    getData: function() {
        return this._data;
    },
    
    /**
     * Return the value associated with the given time in this time series
     */
    getValue: function(t) {
    	if (this._data) {
	    	for (var i = 0; i < this._data.times.length; i++) {
	    		var l = this._data.times[i];
	    		if (l >= t) {
	    			return this._data.values[i];
	    		}
	    	}
    	}
    	return 0;
    },

    /**
     * Add a listener to the underlying event source
     */
    addListener: function(listener) {
        this._eventSource.addListener(listener);
    },

    /**
     * Remove a listener from the underlying event source
     */
    removeListener: function(listener) {
        this._eventSource.removeListener(listener);
    },

    /**
     * Replace a listener from the underlying event source
     */
    replaceListener: function(oldListener, newListener) {
        this.removeListener(oldListener);
        this.addListener(newListener);
    }

}

// -----------------------------------------------------------------------

/**
 * Implementation of a DataSource that extracts the time series out of a 
 * single column from the events
 * 
 * @constructor
 */
Timeplot.ColumnSource = function(eventSource, column) {
    Timeplot.DataSource.apply(this, arguments);
    this._column = column - 1;
};

Object.extend(Timeplot.ColumnSource.prototype,Timeplot.DataSource.prototype);

Timeplot.ColumnSource.prototype.dispose = function() {
    this.removeListener(this._processingListener);
    this._clear();
}

Timeplot.ColumnSource.prototype._process = function() {
    var count = this._eventSource.getCount();
    var times = new Array(count);
    var values = new Array(count);
    var min = Number.MAX_VALUE;
    var max = Number.MIN_VALUE;
    var i = 0;

    var iterator = this._eventSource.getAllEventIterator();
    while (iterator.hasNext()) {
        var event = iterator.next();
        var time = event.getTime();
        times[i] = time;
        var value = this._getValue(event);
        if (!isNaN(value)) {
           if (value < min) {
               min = value;
           }
           if (value > max) {
               max = value;
           }    
            values[i] = value;
        }
        i++;
    }

    this._data = {
        times: times,
        values: values
    };

    if (max == Number.MIN_VALUE) max = 1;
    
    this._range = {
        earliestDate: this._eventSource.getEarliestDate(),
        latestDate: this._eventSource.getLatestDate(),
        min: min,
        max: max
    };
}

Timeplot.ColumnSource.prototype._getValue = function(event) {
    return parseFloat(event.getValues()[this._column]);
}

// ---------------------------------------------------------------

/**
 * Data Source that generates the time series out of the difference
 * between the first and the second column
 * 
 * @constructor
 */
Timeplot.ColumnDiffSource = function(eventSource, column1, column2) {
    Timeplot.ColumnSource.apply(this, arguments);
    this._column2 = column2 - 1;
};

Object.extend(Timeplot.ColumnDiffSource.prototype,Timeplot.ColumnSource.prototype);

Timeplot.ColumnDiffSource.prototype._getValue = function(event) {
    var a = parseFloat(event.getValues()[this._column]);
    var b = parseFloat(event.getValues()[this._column2]);
    return a - b;
}
/**
 * Geometries
 * 
 * @fileOverview Geometries
 * @name Geometries
 */

/**
 * This is the constructor for the default value geometry.
 * A value geometry is what regulates mapping of the plot values to the screen y coordinate.
 * If two plots share the same value geometry, they will be drawn using the same scale.
 * If "min" and "max" parameters are not set, the geometry will stretch itself automatically
 * so that the entire plot will be drawn without overflowing. The stretching happens also
 * when a geometry is shared between multiple plots, the one with the biggest range will
 * win over the others.
 * 
 * @constructor
 */
Timeplot.DefaultValueGeometry = function(params) {
    if (!params) params = {};
    this._id = ("id" in params) ? params.id : "g" + Math.round(Math.random() * 1000000);
    this._axisColor = ("axisColor" in params) ? ((typeof params.axisColor == "string") ? new Timeplot.Color(params.axisColor) : params.axisColor) : new Timeplot.Color("#606060"),
    this._gridColor = ("gridColor" in params) ? ((typeof params.gridColor == "string") ? new Timeplot.Color(params.gridColor) : params.gridColor) : null,
    this._gridLineWidth = ("gridLineWidth" in params) ? params.gridLineWidth : 0.5;
    this._axisLabelsPlacement = ("axisLabelsPlacement" in params) ? params.axisLabelsPlacement : "right";
    this._gridSpacing = ("gridSpacing" in params) ? params.gridStep : 50;
    this._gridType = ("gridType" in params) ? params.gridType : "short";
    this._gridShortSize = ("gridShortSize" in params) ? params.gridShortSize : 10;
    this._minValue = ("min" in params) ? params.min : null;
    this._maxValue = ("max" in params) ? params.max : null;
    this._linMap = {
        direct: function(v) {
            return v;
        },
        inverse: function(y) {
            return y;
        }
    }
    this._map = this._linMap;
    this._labels = [];
    this._grid = [];
}

Timeplot.DefaultValueGeometry.prototype = {

    /**
     * Since geometries can be reused across timeplots, we need to call this function
     * before we can paint using this geometry.
     */
    setTimeplot: function(timeplot) {
        this._timeplot = timeplot;
        this._canvas = timeplot.getCanvas();
        this.reset();
    },

    /**
     * Called by all the plot layers this geometry is associated with
     * to update the value range. Unless min/max values are specified
     * in the parameters, the biggest value range will be used.
     */
    setRange: function(range) {
        if ((this._minValue == null) || ((this._minValue != null) && (range.min < this._minValue))) {
            this._minValue = range.min;
        }
        if ((this._maxValue == null) || ((this._maxValue != null) && (range.max * 1.05 > this._maxValue))) {
            this._maxValue = range.max * 1.05; // get a little more head room to avoid hitting the ceiling
        }

        this._updateMappedValues();

        if (!(this._minValue == 0 && this._maxValue == 0)) {
            this._grid = this._calculateGrid();
        }
    },

    /**
     * Called after changing ranges or canvas size to reset the grid values
     */
    reset: function() {
    	this._clearLabels();
        this._updateMappedValues();
        this._grid = this._calculateGrid();
    },

    /**
     * Map the given value to a y screen coordinate.
     */
    toScreen: function(value) {
    	if (this._canvas && this._maxValue) {
	        var v = value - this._minValue;
	        return this._canvas.height * (this._map.direct(v)) / this._mappedRange;
    	} else {
    		return -50;
    	}
    },

    /**
     * Map the given y screen coordinate to a value
     */
    fromScreen: function(y) {
    	if (this._canvas) {
            return this._map.inverse(this._mappedRange * y / this._canvas.height) + this._minValue;
    	} else {
    		return 0;
    	}
    },

    /**
     * Each geometry is also a painter and paints the value grid and grid labels.
     */
    paint: function() {
    	if (this._timeplot) {
	        var ctx = this._canvas.getContext('2d');
	
	        ctx.lineJoin = 'miter';
	
            // paint grid
            if (this._gridColor) {        
                var gridGradient = ctx.createLinearGradient(0,0,0,this._canvas.height);
                gridGradient.addColorStop(0, this._gridColor.toHexString());
		        gridGradient.addColorStop(0.3, this._gridColor.toHexString());
		        gridGradient.addColorStop(1, "rgba(255,255,255,0.5)");

                ctx.lineWidth = this._gridLineWidth;
                ctx.strokeStyle = gridGradient;
    
                for (var i = 0; i < this._grid.length; i++) {
                    var tick = this._grid[i];
                    var y = Math.floor(tick.y) + 0.5;
                    if (typeof tick.label != "undefined") {
	                    if (this._axisLabelsPlacement == "left") {
	                        var div = this._timeplot.putText(this._id + "-" + i, tick.label,"timeplot-grid-label",{
	                            left: 4,
	                            bottom: y + 2,
	                            color: this._gridColor.toHexString(),
	                            visibility: "hidden"
	                        });
	                    } else if (this._axisLabelsPlacement == "right") {
	                        var div = this._timeplot.putText(this._id + "-" + i, tick.label, "timeplot-grid-label",{
	                            right: 4,
	                            bottom: y + 2,
	                            color: this._gridColor.toHexString(),
	                            visibility: "hidden"
	                        });
	                    }
	                    if (y + div.clientHeight < this._canvas.height + 10) {
	                        div.style.visibility = "visible"; // avoid the labels that would overflow
	                    }
                    }

                    // draw grid
                    ctx.beginPath();
                    if (this._gridType == "long" || tick.label == 0) {
	                    ctx.moveTo(0, y);
	                    ctx.lineTo(this._canvas.width, y);
                    } else if (this._gridType == "short") {
                        if (this._axisLabelsPlacement == "left") {
	                        ctx.moveTo(0, y);
	                        ctx.lineTo(this._gridShortSize, y);
                        } else if (this._axisLabelsPlacement == "right") {
	                        ctx.moveTo(this._canvas.width, y);
	                        ctx.lineTo(this._canvas.width - this._gridShortSize, y);
                        }                    	
                    }
                    ctx.stroke();
                }
            }
		
	        // paint axis
            var axisGradient = ctx.createLinearGradient(0,0,0,this._canvas.height);
            axisGradient.addColorStop(0, this._axisColor.toString());
            axisGradient.addColorStop(0.5, this._axisColor.toString());
            axisGradient.addColorStop(1, "rgba(255,255,255,0.5)");
	        
	        ctx.lineWidth = 1;
            ctx.strokeStyle = axisGradient;
	
	        // left axis
	        ctx.beginPath();
	        ctx.moveTo(0,this._canvas.height);
	        ctx.lineTo(0,0);
	        ctx.stroke();
	        
	        // right axis
	        ctx.beginPath();
	        ctx.moveTo(this._canvas.width,0);
	        ctx.lineTo(this._canvas.width,this._canvas.height);
	        ctx.stroke();
    	}
    },
    
    /**
     * Removes all the labels that were added by this geometry
     */
    _clearLabels: function() {
    	for (var i = 0; i < this._labels.length; i++) {
    		var l = this._labels[i];
    		var parent = l.parentNode;
    		if (parent) parent.removeChild(l);
    	}
    },
    
    /*
     * This function calculates the grid spacing that it will be used 
     * by this geometry to draw the grid in order to reduce clutter. 
     */
    _calculateGrid: function() {
        var grid = [];
        
        if (!this._canvas || this._valueRange == 0) return grid;
                
        var power = 0;
    	if (this._valueRange > 1) {
    		while (Math.pow(10,power) < this._valueRange) {
    			power++;
    		}
    		power--;
    	} else {
            while (Math.pow(10,power) > this._valueRange) {
                power--;
            }
    	}

        var unit = Math.pow(10,power);
        var inc = unit;
        while (true) {
            var dy = this.toScreen(this._minValue + inc);

	        while (dy < this._gridSpacing) {
	        	inc += unit;
                dy = this.toScreen(this._minValue + inc);
	        }

	        if (dy > 2 * this._gridSpacing) { // grids are too spaced out
	        	unit /= 10;
	        	inc = unit;
	        } else {
	        	break;
	        }
        }
        
        var v = 0;
        var y = this.toScreen(v);
        if (this._minValue >= 0) {
        	while (y < this._canvas.height) {
        		if (y > 0) {
        			grid.push({ y: y, label: v });
        		}
        		v += inc;
        		y = this.toScreen(v);
        	}
        } else if (this._maxValue <= 0) {
            while (y > 0) {
                if (y < this._canvas.height) {
                    grid.push({ y: y, label: v });
                }
                v -= inc;
                y = this.toScreen(v);
            }
        } else {
            while (y < this._canvas.height) {
                if (y > 0) {
                    grid.push({ y: y, label: v });
                }
                v += inc;
                y = this.toScreen(v);
            }
            v = -inc;
            y = this.toScreen(v);
            while (y > 0) {
                if (y < this._canvas.height) {
                    grid.push({ y: y, label: v });
                }
                v -= inc;
                y = this.toScreen(v);
            }
        }
        
        return grid;
    },

    /*
     * Update the values that are used by the paint function so that
     * we don't have to calculate them at every repaint.
     */
    _updateMappedValues: function() {
        this._valueRange = Math.abs(this._maxValue - this._minValue);
        this._mappedRange = this._map.direct(this._valueRange);
    }
    
}

// --------------------------------------------------

/**
 * This is the constructor for a Logarithmic value geometry, which
 * is useful when plots have values in different magnitudes but 
 * exhibit similar trends and such trends want to be shown on the same
 * plot (here a cartesian geometry would make the small magnitudes 
 * disappear).
 * 
 * NOTE: this class extends Timeplot.DefaultValueGeometry and inherits
 * all of the methods of that class. So refer to that class. 
 * 
 * @constructor
 */
Timeplot.LogarithmicValueGeometry = function(params) {
    Timeplot.DefaultValueGeometry.apply(this, arguments);
    this._logMap = {
    	direct: function(v) {
			return Math.log(v + 1) / Math.log(10);
    	},
    	inverse: function(y) {
			return Math.exp(Math.log(10) * y) - 1;
    	}
    }
    this._mode = "log";
    this._map = this._logMap;
    this._calculateGrid = this._logarithmicCalculateGrid;
};

Timeplot.LogarithmicValueGeometry.prototype._linearCalculateGrid = Timeplot.DefaultValueGeometry.prototype._calculateGrid;

Object.extend(Timeplot.LogarithmicValueGeometry.prototype,Timeplot.DefaultValueGeometry.prototype);

/*
 * This function calculates the grid spacing that it will be used 
 * by this geometry to draw the grid in order to reduce clutter. 
 */
Timeplot.LogarithmicValueGeometry.prototype._logarithmicCalculateGrid = function() {
    var grid = [];
    
    if (!this._canvas || this._valueRange == 0) return grid;

    var v = 1;
    var y = this.toScreen(v);
    while (y < this._canvas.height || isNaN(y)) {
        if (y > 0) {
            grid.push({ y: y, label: v });
        }
        v *= 10;
        y = this.toScreen(v);
    }
    
    return grid;
};

/**
 * Turn the logarithmic scaling off. 
 */
Timeplot.LogarithmicValueGeometry.prototype.actLinear = function() {
    this._mode = "lin";
    this._map = this._linMap;
    this._calculateGrid = this._linearCalculateGrid;
	this.reset();
}

/**
 * Turn the logarithmic scaling on. 
 */
Timeplot.LogarithmicValueGeometry.prototype.actLogarithmic = function() {
    this._mode = "log";
    this._map = this._logMap;
    this._calculateGrid = this._logarithmicCalculateGrid;
    this.reset();
}

/**
 * Toggle logarithmic scaling seeting it to on if off and viceversa. 
 */
Timeplot.LogarithmicValueGeometry.prototype.toggle = function() {
	if (this._mode == "log") {
		this.actLinear();
	} else {
        this.actLogarithmic();
	}
}

// -----------------------------------------------------

/**
 * This is the constructor for the default time geometry.
 * 
 * @constructor
 */
Timeplot.DefaultTimeGeometry = function(params) {
    if (!params) params = {};
    this._id = ("id" in params) ? params.id : "g" + Math.round(Math.random() * 1000000);
    this._locale = ("locale" in params) ? params.locale : "en";
    this._timeZone = ("timeZone" in params) ? params.timeZone : SimileAjax.DateTime.getTimezone();
    this._labeller = ("labeller" in params) ? params.labeller : null;
    /* AMO change: optional control over date format */
    this._dayIntervalFormat = ("dayIntervalFormat" in params) ? params.dayIntervalFormat : null;
    this._axisColor = ("axisColor" in params) ? ((params.axisColor == "string") ? new Timeplot.Color(params.axisColor) : params.axisColor) : new Timeplot.Color("#606060"),
    this._gridColor = ("gridColor" in params) ? ((params.gridColor == "string") ? new Timeplot.Color(params.gridColor) : params.gridColor) : null,
    this._gridLineWidth = ("gridLineWidth" in params) ? params.gridLineWidth : 0.5;
    this._axisLabelsPlacement = ("axisLabelsPlacement" in params) ? params.axisLabelsPlacement : "bottom";
    this._gridStep = ("gridStep" in params) ? params.gridStep : 100;
    this._gridStepRange = ("gridStepRange" in params) ? params.gridStepRange : 20;
    this._min = ("min" in params) ? params.min : null;
    this._max = ("max" in params) ? params.max : null;
    this._timeValuePosition =("timeValuePosition" in params) ? params.timeValuePosition : "bottom";
    this._unit = ("unit" in params) ? params.unit : Timeline.NativeDateUnit;
    this._linMap = {
        direct: function(t) {
            return t;
        },
        inverse: function(x) {
            return x;
        }
    }
    this._map = this._linMap;
    this._labeler = this._unit.createLabeller(this._locale, this._timeZone);
    var dateParser = this._unit.getParser("iso8601");
    if (this._min && !this._min.getTime) {
        this._min = dateParser(this._min);
    }
    if (this._max && !this._max.getTime) {
        this._max = dateParser(this._max);
    }
    this._grid = [];
}

Timeplot.DefaultTimeGeometry.prototype = {

    /**
     * Since geometries can be reused across timeplots, we need to call this function
     * before we can paint using this geometry.
     */
    setTimeplot: function(timeplot) {
    	this._timeplot = timeplot;
    	this._canvas = timeplot.getCanvas();
        this.reset();
    },

    /**
     * Called by all the plot layers this geometry is associated with
     * to update the time range. Unless min/max values are specified
     * in the parameters, the biggest range will be used.
     */
    setRange: function(range) {
    	if (this._min) {
    		this._earliestDate = this._min;
    	} else if (range.earliestDate && ((this._earliestDate == null) || ((this._earliestDate != null) && (range.earliestDate.getTime() < this._earliestDate.getTime())))) {
            this._earliestDate = range.earliestDate;
        }
        
        if (this._max) {
        	this._latestDate = this._max;
        } else if (range.latestDate && ((this._latestDate == null) || ((this._latestDate != null) && (range.latestDate.getTime() > this._latestDate.getTime())))) {
            this._latestDate = range.latestDate;
        }

        if (!this._earliestDate && !this._latestDate) {
            this._grid = [];
        } else {
        	this.reset(); 
        }
    },
    
    /**
     * Called after changing ranges or canvas size to reset the grid values
     */
    reset: function() {
        this._updateMappedValues();
        if (this._canvas) this._grid = this._calculateGrid();
    },
    
    /**
     * Map the given date to a x screen coordinate.
     */
    toScreen: function(time) {
    	if (this._canvas && this._latestDate) {
            var t = time - this._earliestDate.getTime();
            var fraction = (this._mappedPeriod > 0) ? this._map.direct(t) / this._mappedPeriod : 0;
            return this._canvas.width * fraction;
        } else {
            return -50;
        } 
    },

    /**
     * Map the given x screen coordinate to a date.
     */
    fromScreen: function(x) {
    	if (this._canvas) {
            return this._map.inverse(this._mappedPeriod * x / this._canvas.width) + this._earliestDate.getTime();
    	} else {
    		return 0;
    	} 
    },
    
    /**
     * Get a period (in milliseconds) this time geometry spans.
     */
    getPeriod: function() {
    	return this._period;
    },
    
    /**
     * Return the labeler that has been associated with this time geometry
     */
    getLabeler: function() {
    	return this._labeler;
    },

    /**
     * Return the time unit associated with this time geometry
     */
    getUnit: function() {
        return this._unit;
    },

   /**
    * Each geometry is also a painter and paints the value grid and grid labels.
    */
    paint: function() {
    	if (this._canvas) {
	    	var unit = this._unit;
	        var ctx = this._canvas.getContext('2d');
	
	        var gradient = ctx.createLinearGradient(0,0,0,this._canvas.height);
	
	        ctx.strokeStyle = gradient;
	        ctx.lineWidth = this._gridLineWidth;
	        ctx.lineJoin = 'miter';
	
	        // paint grid
	        if (this._gridColor) {        
	            gradient.addColorStop(0, this._gridColor.toString());
	            gradient.addColorStop(1, "rgba(255,255,255,0.9)");
	
	            for (var i = 0; i < this._grid.length; i++) {
	            	var tick = this._grid[i];
	            	var x = Math.floor(tick.x) + 0.5;
                    if (this._axisLabelsPlacement == "top") {
                        var div = this._timeplot.putText(this._id + "-" + i, tick.label,"timeplot-grid-label",{
                            left: x + 4,
                            top: 2,
                            visibility: "hidden"
                        });
                    } else if (this._axisLabelsPlacement == "bottom") {
                        var div = this._timeplot.putText(this._id + "-" + i, tick.label, "timeplot-grid-label",{
                            left: x + 4,
                            bottom: 2,
                            visibility: "hidden"
                        });
                    }
                    if (x + div.clientWidth < this._canvas.width + 10) {
                        div.style.visibility = "visible"; // avoid the labels that would overflow
                    }

                    // draw separator
                    ctx.beginPath();
                    ctx.moveTo(x,0);
                    ctx.lineTo(x,this._canvas.height);
                    ctx.stroke();
	            }
	        }
	
	        // paint axis
	        gradient.addColorStop(0, this._axisColor.toString());
	        gradient.addColorStop(1, "rgba(255,255,255,0.5)");
	        
	        ctx.lineWidth = 1;
	        gradient.addColorStop(0, this._axisColor.toString());
	
	        ctx.beginPath();
	        ctx.moveTo(0,0);
	        ctx.lineTo(this._canvas.width,0);
	        ctx.stroke();
    	}
    },
    
    /*
     * This function calculates the grid spacing that it will be used 
     * by this geometry to draw the grid in order to reduce clutter. 
     */
    _calculateGrid: function() {
    	var grid = [];
    	
    	var time = SimileAjax.DateTime;
    	var u = this._unit;
    	var p = this._period;
        
        if (p == 0) return grid;
        
        // find the time units nearest to the time period
        if (p > time.gregorianUnitLengths[time.MILLENNIUM]) {
            unit = time.MILLENNIUM;	
        } else {
	        for (var unit = time.MILLENNIUM; unit > 0; unit--) {
	            if (time.gregorianUnitLengths[unit-1] <= p && p < time.gregorianUnitLengths[unit]) {
	                unit--;
	                break;
	            }
	        }
        }

        var t = u.cloneValue(this._earliestDate);

        do {
	        time.roundDownToInterval(t, unit, this._timeZone, 1, 0);
	        var x = this.toScreen(u.toNumber(t));
	        switch (unit) {
	        	case time.SECOND:
                  var l = t.toLocaleTimeString();
	        	  break;
	        	case time.MINUTE:
	        	  var m = t.getMinutes();
                  var l = t.getHours() + ":" + ((m < 10) ? "0" : "") + m;
                  break;
                case time.HOUR:
                  var l = t.getHours() + ":00";
                  break;
	        	case time.DAY:
	        	case time.WEEK:
                case time.MONTH:
                  var l = t.toLocaleDateString();
                  /* AMO change: optional control over date format */
                  if (this._dayIntervalFormat && typeof t.strftime == 'function') {
                    l = t.strftime(this._dayIntervalFormat);
                  }
                  break;  
                case time.YEAR:
                case time.DECADE:
                case time.CENTURY:
                case time.MILLENNIUM:
	        	  var l = t.getUTCFullYear();
	        	  break;
	        }
	        if (x > 0) { 
		        grid.push({ x: x, label: l });
	        }
	        time.incrementByInterval(t, unit, this._timeZone);
        } while (t.getTime() < this._latestDate.getTime());
        
        return grid;
    },
        
    /*
     * Update the values that are used by the paint function so that
     * we don't have to calculate them at every repaint.
     */
    _updateMappedValues: function() {
    	if (this._latestDate && this._earliestDate) {
	        this._period = this._latestDate.getTime() - this._earliestDate.getTime();
	        this._mappedPeriod = this._map.direct(this._period);
    	} else {
    		this._period = 0;
    		this._mappedPeriod = 0;
    	}
    }
    
}

// --------------------------------------------------------------

/**
 * This is the constructor for the magnifying time geometry.
 * Users can interact with this geometry and 'magnify' certain areas of the
 * plot to see the plot enlarged and resolve details that would otherwise
 * get lost or cluttered with a linear time geometry.
 * 
 * @constructor
 */
Timeplot.MagnifyingTimeGeometry = function(params) {
    Timeplot.DefaultTimeGeometry.apply(this, arguments);
        
    var g = this;
    this._MagnifyingMap = {
        direct: function(t) {
        	if (t < g._leftTimeMargin) {
        		var x = t * g._leftRate;
        	} else if ( g._leftTimeMargin < t && t < g._rightTimeMargin ) {
        		var x = t * g._expandedRate + g._expandedTimeTranslation;
        	} else {
        		var x = t * g._rightRate + g._rightTimeTranslation;
        	}
        	return x;
        },
        inverse: function(x) {
            if (x < g._leftScreenMargin) {
                var t = x / g._leftRate;
            } else if ( g._leftScreenMargin < x && x < g._rightScreenMargin ) {
                var t = x / g._expandedRate + g._expandedScreenTranslation;
            } else {
                var t = x / g._rightRate + g._rightScreenTranslation;
            }
            return t;
        }
    }

    this._mode = "lin";
    this._map = this._linMap;
};

Object.extend(Timeplot.MagnifyingTimeGeometry.prototype,Timeplot.DefaultTimeGeometry.prototype);

/**
 * Initialize this geometry associating it with the given timeplot and 
 * register the geometry event handlers to the timeplot so that it can
 * interact with the user.
 */
Timeplot.MagnifyingTimeGeometry.prototype.initialize = function(timeplot) {
    Timeplot.DefaultTimeGeometry.prototype.initialize.apply(this, arguments);

    if (!this._lens) {
        this._lens = this._timeplot.putDiv("lens","timeplot-lens");
    }

    var period = 1000 * 60 * 60 * 24 * 30; // a month in the magnifying lens

    var geometry = this;
    
    var magnifyWith = function(lens) {
        var aperture = lens.clientWidth;
        var loc = geometry._timeplot.locate(lens);
        geometry.setMagnifyingParams(loc.x + aperture / 2, aperture, period);
        geometry.actMagnifying();
        geometry._timeplot.paint();
    }
    
    var canvasMouseDown = function(elmt, evt, target) {
        geometry._canvas.startCoords = SimileAjax.DOM.getEventRelativeCoordinates(evt,elmt);
        geometry._canvas.pressed = true;
    }
    
    var canvasMouseUp = function(elmt, evt, target) {
        geometry._canvas.pressed = false;
        var coords = SimileAjax.DOM.getEventRelativeCoordinates(evt,elmt);
        if (Timeplot.Math.isClose(coords,geometry._canvas.startCoords,5)) {
            geometry._lens.style.display = "none";
            geometry.actLinear();
            geometry._timeplot.paint();
        } else {
	        geometry._lens.style.cursor = "move";
	        magnifyWith(geometry._lens);
        }
    }

    var canvasMouseMove = function(elmt, evt, target) {
        if (geometry._canvas.pressed) {
            var coords = SimileAjax.DOM.getEventRelativeCoordinates(evt,elmt);
            if (coords.x < 0) coords.x = 0;
            if (coords.x > geometry._canvas.width) coords.x = geometry._canvas.width;
            geometry._timeplot.placeDiv(geometry._lens, {
                left: geometry._canvas.startCoords.x,
                width: coords.x - geometry._canvas.startCoords.x,
                bottom: 0,
                height: geometry._canvas.height,
                display: "block"
            });
        }
    }

    var lensMouseDown = function(elmt, evt, target) {
        geometry._lens.startCoords = SimileAjax.DOM.getEventRelativeCoordinates(evt,elmt);;
        geometry._lens.pressed = true; 
    }
    
    var lensMouseUp = function(elmt, evt, target) {
        geometry._lens.pressed = false;
    }
    
    var lensMouseMove = function(elmt, evt, target) {
        if (geometry._lens.pressed) {
            var coords = SimileAjax.DOM.getEventRelativeCoordinates(evt,elmt);
            var lens = geometry._lens;
            var left = lens.offsetLeft + coords.x - lens.startCoords.x;
            if (left < geometry._timeplot._paddingX) left = geometry._timeplot._paddingX;
            if (left + lens.clientWidth > geometry._canvas.width - geometry._timeplot._paddingX) left = geometry._canvas.width - lens.clientWidth + geometry._timeplot._paddingX;
            lens.style.left = left;
            magnifyWith(lens);
        }
    }
    
    if (!this._canvas.instrumented) {
        SimileAjax.DOM.registerEvent(this._canvas, "mousedown", canvasMouseDown);
        SimileAjax.DOM.registerEvent(this._canvas, "mousemove", canvasMouseMove);
        SimileAjax.DOM.registerEvent(this._canvas, "mouseup"  , canvasMouseUp);
        SimileAjax.DOM.registerEvent(this._canvas, "mouseup"  , lensMouseUp);
        this._canvas.instrumented = true;
    }
    
    if (!this._lens.instrumented) {
	    SimileAjax.DOM.registerEvent(this._lens, "mousedown", lensMouseDown);
	    SimileAjax.DOM.registerEvent(this._lens, "mousemove", lensMouseMove);
        SimileAjax.DOM.registerEvent(this._lens, "mouseup"  , lensMouseUp);
    	SimileAjax.DOM.registerEvent(this._lens, "mouseup"  , canvasMouseUp);
    	this._lens.instrumented = true;
    }
}

/**
 * Set the Magnifying parameters. c is the location in pixels where the Magnifying
 * center should be located in the timeplot, a is the aperture in pixel of
 * the Magnifying and b is the time period in milliseconds that the Magnifying 
 * should span.
 */
Timeplot.MagnifyingTimeGeometry.prototype.setMagnifyingParams = function(c,a,b) {
    a = a / 2;
    b = b / 2;

    var w = this._canvas.width;
    var d = this._period;

    if (c < 0) c = 0;
    if (c > w) c = w;
    
    if (c - a < 0) a = c;
    if (c + a > w) a = w - c;
    
    var ct = this.fromScreen(c) - this._earliestDate.getTime();
    if (ct - b < 0) b = ct;
    if (ct + b > d) b = d - ct;

    this._centerX = c;
    this._centerTime = ct;
    this._aperture = a;
    this._aperturePeriod = b;
    
    this._leftScreenMargin = this._centerX - this._aperture;
    this._rightScreenMargin = this._centerX + this._aperture;
    this._leftTimeMargin = this._centerTime - this._aperturePeriod;
    this._rightTimeMargin = this._centerTime + this._aperturePeriod;
        
    this._leftRate = (c - a) / (ct - b);
    this._expandedRate = a / b;
    this._rightRate = (w - c - a) / (d - ct - b);

    this._expandedTimeTranslation = this._centerX - this._centerTime * this._expandedRate; 
    this._expandedScreenTranslation = this._centerTime - this._centerX / this._expandedRate;
    this._rightTimeTranslation = (c + a) - (ct + b) * this._rightRate;
    this._rightScreenTranslation = (ct + b) - (c + a) / this._rightRate;

    this._updateMappedValues();
}

/*
 * Turn magnification off.
 */
Timeplot.MagnifyingTimeGeometry.prototype.actLinear = function() {
    this._mode = "lin";
    this._map = this._linMap;
    this.reset();
}

/*
 * Turn magnification on.
 */
Timeplot.MagnifyingTimeGeometry.prototype.actMagnifying = function() {
    this._mode = "Magnifying";
    this._map = this._MagnifyingMap;
    this.reset();
}

/*
 * Toggle magnification.
 */
Timeplot.MagnifyingTimeGeometry.prototype.toggle = function() {
    if (this._mode == "Magnifying") {
        this.actLinear();
    } else {
        this.actMagnifying();
    }
}

/**
 * Color
 *
 * @fileOverview Color
 * @name Color
 */

/*
 * Inspired by Plotr
 * Copyright 2007 (c) Bas Wenneker <sabmann[a]gmail[d]com>
 * For use under the BSD license. <http://www.solutoire.com/plotr>
 */

/**
 * Create a Color object that can be used to manipulate colors programmatically.
 */
Timeplot.Color = function(color) {
    this._fromHex(color);
};

Timeplot.Color.prototype = {

    /**
     * Sets the RGB values of this coor
     * 
     * @param {Number} r,g,b    Red green and blue values (between 0 and 255)
     */
    set: function (r,g,b,a) {
        this.r = r;
        this.g = g;
        this.b = b;
        this.a = (a) ? a : 1.0;
        return this.check();
    },

    /**
     * Set the color transparency
     * 
     * @param {float} a   Transparency value, between 0.0 (fully transparent) and 1.0 (fully opaque).
     */
    transparency: function(a) {
    	this.a = a;
    	return this.check();
    },
    
    /**
     * Lightens the color.
     * 
     * @param {integer} level   Level to lighten the color with.
     */
    lighten: function(level) {
        var color = new Timeplot.Color();
        return color.set(
            this.r += parseInt(level, 10),
            this.g += parseInt(level, 10),
            this.b += parseInt(level, 10)
        );
    },

    /**
     * Darkens the color.
     * 
     * @param {integer} level   Level to darken the color with.
     */
    darken: function(level){
        var color = new Timeplot.Color();
        return color.set(
            this.r -= parseInt(level, 10),
            this.g -= parseInt(level, 10),
            this.b -= parseInt(level, 10)
        );
    },

    /**
     * Checks and validates if the hex values r, g and b are
     * between 0 and 255.
     */
    check: function() {
        if (this.r > 255) { 
        	this.r = 255;
        } else if (this.r < 0){
        	this.r = 0;
        }
        if (this.g > 255) {
        	this.g = 255;
        } else if (this.g < 0) {
        	this.g = 0;
        }
        if (this.b > 255){
        	this.b = 255;
        } else if (this.b < 0){
        	this.b = 0;
        }
        if (this.a > 1.0){
            this.a = 1.0;
        } else if (this.a < 0.0){
            this.a = 0.0;
        }
        return this;
    },

    /**
     * Returns a string representation of this color.
     * 
     * @param {float} alpha   (optional) Transparency value, between 0.0 (fully transparent) and 1.0 (fully opaque).
     */
    toString: function(alpha) {
        var a = (alpha) ? alpha : ((this.a) ? this.a : 1.0);
        return 'rgba(' + this.r + ',' + this.g + ',' + this.b + ',' + a + ')';
    },

    /**
     * Returns the hexadecimal representation of this color (without the alpha channel as hex colors don't support it)
     */
    toHexString: function() {
    	return "#" + this._toHex(this.r) + this._toHex(this.g) + this._toHex(this.b); 
    },
    
    /*
     * Parses and stores the hex values of the input color string.
     * 
     * @param {String} color    Hex or rgb() css string.
     */
    _fromHex: function(color) {
        if(/^#?([\da-f]{3}|[\da-f]{6})$/i.test(color)){
            color = color.replace(/^#/, '').replace(/^([\da-f])([\da-f])([\da-f])$/i, "$1$1$2$2$3$3");
            this.r = parseInt(color.substr(0,2), 16);
            this.g = parseInt(color.substr(2,2), 16);
            this.b = parseInt(color.substr(4,2), 16);
        } else if(/^rgb *\( *\d{0,3} *, *\d{0,3} *, *\d{0,3} *\)$/i.test(color)){
            color = color.match(/^rgb *\( *(\d{0,3}) *, *(\d{0,3}) *, *(\d{0,3}) *\)$/i);
            this.r = parseInt(color[1], 10);
            this.g = parseInt(color[2], 10);
            this.b = parseInt(color[3], 10);
        }
        this.a = 1.0;
        return this.check();
    },
    
    /*
     * Returns an hexadecimal representation of a 8 bit integer 
     */
    _toHex: function(dec) {
        var hex = "0123456789ABCDEF"
        if (dec < 0) return "00";
        if (dec > 255) return "FF";
        var i = Math.floor(dec / 16);
        var j = dec % 16;
        return hex.charAt(i) + hex.charAt(j);
    }

};/**
 * Math Utility functions
 * 
 * @fileOverview Math Utility functions
 * @name Math
 */

Timeplot.Math = { 

    /**
     * Evaluates the range (min and max values) of the given array
     */
    range: function(f) {
        var F = f.length;
        var min = Number.MAX_VALUE;
        var max = Number.MIN_VALUE;

        for (var t = 0; t < F; t++) {
            var value = f[t];
            if (value < min) {
                min = value;
            }
            if (value > max) {
                max = value;
            }    
        }

        return {
            min: min,
            max: max
        }
    },

    /**
     * Evaluates the windows average of a given array based on the
     * given window size
     */
    movingAverage: function(f, size) {
        var F = f.length;
        var g = new Array(F);
        for (var n = 0; n < F; n++) {
            var value = 0;
            for (var m = n - size; m < n + size; m++) {
                if (m < 0) {
                    var v = f[0];
                } else if (m >= F) {
                    var v = g[n-1];
                } else {
                    var v = f[m];
                }
                value += v;
            }
            g[n] = value / (2 * size);
        }
        return g;
    },

    /**
     * Returns an array with the integral of the given array
     */
    integral: function(f) {
        var F = f.length;

        var g = new Array(F);
        var sum = 0;

        for (var t = 0; t < F; t++) {
           sum += f[t];
           g[t] = sum;  
        }

        return g;
    },

    /**
     * Normalizes an array so that its complete integral is 1.
     * This is useful to obtain arrays that preserve the overall
     * integral of a convolution. 
     */
    normalize: function(f) {
        var F = f.length;
        var sum = 0.0;

        for (var t = 0; t < F; t++) {
            sum += f[t];
        }

        for (var t = 0; t < F; t++) {
            f[t] /= sum;
        }

        return f;
    },

    /**
     * Calculates the convolution between two arrays
     */
    convolution: function(f,g) {
        var F = f.length;
        var G = g.length;

        var c = new Array(F);

        for (var m = 0; m < F; m++) {
            var r = 0;
            var end = (m + G < F) ? m + G : F;
            for (var n = m; n < end; n++) {
                var a = f[n - G];
                var b = g[n - m];
                r += a * b;
            }
            c[m] = r;
        }

        return c;
    },

    // ------ Array generators ------------------------------------------------- 
    // Functions that generate arrays based on mathematical functions
    // Normally these are used to produce operators by convolving them with the input array
    // The returned arrays have the property of having 

    /**
     * Generate the heavyside step function of given size
     */
    heavyside: function(size) {
        var f =  new Array(size);
        var value = 1 / size;
        for (var t = 0; t < size; t++) {
            f[t] = value;
        }
        return f;
    },

    /**
     * Generate the gaussian function so that at the given 'size' it has value 'threshold'
     * and make sure its integral is one.
     */
    gaussian: function(size, threshold) {
        with (Math) {
            var radius = size / 2;
            var variance = radius * radius / log(threshold); 
            var g = new Array(size);
            for (var t = 0; t < size; t++) {
                var l = t - radius;
                g[t] = exp(-variance * l * l);
            }
        }

        return this.normalize(g);
    },

    // ---- Utility Methods --------------------------------------------------

    /**
     * Return x with n significant figures 
     */
    round: function(x,n) {
        with (Math) {
            if (abs(x) > 1) {
                var l = floor(log(x)/log(10));
                var d = round(exp((l-n+1)*log(10)));
                var y = round(round(x / d) * d);
                return y;
            } else {
                log("FIXME(SM): still to implement for 0 < abs(x) < 1");
                return x;
            }
        }
    },
    
    /**
     * Return the hyperbolic tangent of x
     */
    tanh: function(x) {
    	if (x > 5) {
    		return 1;
    	} else if (x < 5) {
    		return -1;
    	} else {
	    	var expx2 = Math.exp(2 * x);
	    	return (expx2 - 1) / (expx2 + 1);
    	}
    },
    
    /** 
     * Returns true if |a.x - b.x| < value && | a.y - b.y | < value
     */
    isClose: function(a,b,value) {
    	return (a && b && Math.abs(a.x - b.x) < value && Math.abs(a.y - b.y) < value);
    }

}/**
 * Processing Data Source
 * 
 * @fileOverview Processing Data Source and Operators
 * @name Processor
 */

/* -----------------------------------------------------------------------------
 * Operators
 * 
 * These are functions that can be used directly as Timeplot.Processor operators
 * ----------------------------------------------------------------------------- */

Timeplot.Operator = { 

    /**
     * This is the operator used when you want to draw the cumulative sum
     * of a time series and not, for example, their daily values.
     */
    sum: function(data, params) {
        return Timeplot.Math.integral(data.values);
    },

    /**
     * This is the operator that is used to 'smooth' a given time series
     * by taking the average value of a moving window centered around
     * each value. The size of the moving window is influenced by the 'size'
     * parameters in the params map.
     */
    average: function(data, params) {
        var size = ("size" in params) ? params.size : 30;
        var result = Timeplot.Math.movingAverage(data.values, size);
        return result;
    }
}

/*==================================================
 *  Processing Data Source
 *==================================================*/

/**
 * A Processor is a special DataSource that can apply an Operator
 * to the DataSource values and thus return a different one.
 * 
 * @constructor
 */
Timeplot.Processor = function(dataSource, operator, params) {
    this._dataSource = dataSource;
    this._operator = operator;
    this._params = params;

    this._data = {
        times: new Array(),
        values: new Array()
    };

    this._range = {
        earliestDate: null,
        latestDate: null,
        min: 0,
        max: 0
    };

    var processor = this;
    this._processingListener = {
        onAddMany: function() { processor._process(); },
        onClear:   function() { processor._clear(); }
    }
    this.addListener(this._processingListener);
};

Timeplot.Processor.prototype = {

    _clear: function() {
        this.removeListener(this._processingListener);
        this._dataSource._clear();
    },

    _process: function() {
        // this method requires the dataSource._process() method to be
        // called first as to setup the data and range used below
        // this should be guaranteed by the order of the listener registration  

        var data = this._dataSource.getData();
        var range = this._dataSource.getRange();

        var newValues = this._operator(data, this._params);
        var newValueRange = Timeplot.Math.range(newValues);

        this._data = {
            times: data.times,
            values: newValues
        };

        this._range = {
            earliestDate: range.earliestDate,
            latestDate: range.latestDate,
            min: newValueRange.min,
            max: newValueRange.max
        };
    },

    getRange: function() {
        return this._range;
    },

    getData: function() {
        return this._data;
    },
    
    getValue: Timeplot.DataSource.prototype.getValue,

    addListener: function(listener) {
        this._dataSource.addListener(listener);
    },

    removeListener: function(listener) {
        this._dataSource.removeListener(listener);
    }
}
// Copyright 2006 Google Inc.
//
// Licensed under the Apache License, Version 2.0 (the "License");
// you may not use this file except in compliance with the License.
// You may obtain a copy of the License at
//
//   http://www.apache.org/licenses/LICENSE-2.0
//
// Unless required by applicable law or agreed to in writing, software
// distributed under the License is distributed on an "AS IS" BASIS,
// WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
// See the License for the specific language governing permissions and
// limitations under the License.


// Known Issues:
//
// * Patterns are not implemented.
// * Radial gradient are not implemented. The VML version of these look very
//   different from the canvas one.
// * Clipping paths are not implemented.
// * Coordsize. The width and height attribute have higher priority than the
//   width and height style values which isn't correct.
// * Painting mode isn't implemented.
// * Canvas width/height should is using content-box by default. IE in
//   Quirks mode will draw the canvas using border-box. Either change your
//   doctype to HTML5
//   (http://www.whatwg.org/specs/web-apps/current-work/#the-doctype)
//   or use Box Sizing Behavior from WebFX
//   (http://webfx.eae.net/dhtml/boxsizing/boxsizing.html)
// * Optimize. There is always room for speed improvements.

// only add this code if we do not already have a canvas implementation
if (!window.CanvasRenderingContext2D) {

(function () {

  // alias some functions to make (compiled) code shorter
  var m = Math;
  var mr = m.round;
  var ms = m.sin;
  var mc = m.cos;

  // this is used for sub pixel precision
  var Z = 10;
  var Z2 = Z / 2;

  var G_vmlCanvasManager_ = {
    init: function (opt_doc) {
      var doc = opt_doc || document;
      if (/MSIE/.test(navigator.userAgent) && !window.opera) {
        var self = this;
        doc.attachEvent("onreadystatechange", function () {
          self.init_(doc);
        });
      }
    },

    init_: function (doc) {
      if (doc.readyState == "complete") {
        // create xmlns
        if (!doc.namespaces["g_vml_"]) {
          doc.namespaces.add("g_vml_", "urn:schemas-microsoft-com:vml");
        }

        // setup default css
        var ss = doc.createStyleSheet();
        ss.cssText = "canvas{display:inline-block;overflow:hidden;" +
            // default size is 300x150 in Gecko and Opera
            "text-align:left;width:300px;height:150px}" +
            "g_vml_\\:*{behavior:url(#default#VML)}";

        // find all canvas elements
        var els = doc.getElementsByTagName("canvas");
        for (var i = 0; i < els.length; i++) {
          if (!els[i].getContext) {
            this.initElement(els[i]);
          }
        }
      }
    },

    fixElement_: function (el) {
      // in IE before version 5.5 we would need to add HTML: to the tag name
      // but we do not care about IE before version 6
      var outerHTML = el.outerHTML;

      var newEl = el.ownerDocument.createElement(outerHTML);
      // if the tag is still open IE has created the children as siblings and
      // it has also created a tag with the name "/FOO"
      if (outerHTML.slice(-2) != "/>") {
        var tagName = "/" + el.tagName;
        var ns;
        // remove content
        while ((ns = el.nextSibling) && ns.tagName != tagName) {
          ns.removeNode();
        }
        // remove the incorrect closing tag
        if (ns) {
          ns.removeNode();
        }
      }
      el.parentNode.replaceChild(newEl, el);
      return newEl;
    },

    /**
     * Public initializes a canvas element so that it can be used as canvas
     * element from now on. This is called automatically before the page is
     * loaded but if you are creating elements using createElement you need to
     * make sure this is called on the element.
     * @param {HTMLElement} el The canvas element to initialize.
     * @return {HTMLElement} the element that was created.
     */
    initElement: function (el) {
      el = this.fixElement_(el);
      el.getContext = function () {
        if (this.context_) {
          return this.context_;
        }
        return this.context_ = new CanvasRenderingContext2D_(this);
      };

      // do not use inline function because that will leak memory
      el.attachEvent('onpropertychange', onPropertyChange);
      el.attachEvent('onresize', onResize);

      var attrs = el.attributes;
      if (attrs.width && attrs.width.specified) {
        // TODO: use runtimeStyle and coordsize
        // el.getContext().setWidth_(attrs.width.nodeValue);
        el.style.width = attrs.width.nodeValue + "px";
      } else {
        el.width = el.clientWidth;
      }
      if (attrs.height && attrs.height.specified) {
        // TODO: use runtimeStyle and coordsize
        // el.getContext().setHeight_(attrs.height.nodeValue);
        el.style.height = attrs.height.nodeValue + "px";
      } else {
        el.height = el.clientHeight;
      }
      //el.getContext().setCoordsize_()
      return el;
    }
  };

  function onPropertyChange(e) {
    var el = e.srcElement;

    switch (e.propertyName) {
      case 'width':
        el.style.width = el.attributes.width.nodeValue + "px";
        el.getContext().clearRect();
        break;
      case 'height':
        el.style.height = el.attributes.height.nodeValue + "px";
        el.getContext().clearRect();
        break;
    }
  }

  function onResize(e) {
    var el = e.srcElement;
    if (el.firstChild) {
      el.firstChild.style.width =  el.clientWidth + 'px';
      el.firstChild.style.height = el.clientHeight + 'px';
    }
  }

  G_vmlCanvasManager_.init();

  // precompute "00" to "FF"
  var dec2hex = [];
  for (var i = 0; i < 16; i++) {
    for (var j = 0; j < 16; j++) {
      dec2hex[i * 16 + j] = i.toString(16) + j.toString(16);
    }
  }

  function createMatrixIdentity() {
    return [
      [1, 0, 0],
      [0, 1, 0],
      [0, 0, 1]
    ];
  }

  function matrixMultiply(m1, m2) {
    var result = createMatrixIdentity();

    for (var x = 0; x < 3; x++) {
      for (var y = 0; y < 3; y++) {
        var sum = 0;

        for (var z = 0; z < 3; z++) {
          sum += m1[x][z] * m2[z][y];
        }

        result[x][y] = sum;
      }
    }
    return result;
  }

  function copyState(o1, o2) {
    o2.fillStyle     = o1.fillStyle;
    o2.lineCap       = o1.lineCap;
    o2.lineJoin      = o1.lineJoin;
    o2.lineWidth     = o1.lineWidth;
    o2.miterLimit    = o1.miterLimit;
    o2.shadowBlur    = o1.shadowBlur;
    o2.shadowColor   = o1.shadowColor;
    o2.shadowOffsetX = o1.shadowOffsetX;
    o2.shadowOffsetY = o1.shadowOffsetY;
    o2.strokeStyle   = o1.strokeStyle;
    o2.arcScaleX_    = o1.arcScaleX_;
    o2.arcScaleY_    = o1.arcScaleY_;
  }

  function processStyle(styleString) {
    var str, alpha = 1;

    styleString = String(styleString);
    if (styleString.substring(0, 3) == "rgb") {
      var start = styleString.indexOf("(", 3);
      var end = styleString.indexOf(")", start + 1);
      var guts = styleString.substring(start + 1, end).split(",");

      str = "#";
      for (var i = 0; i < 3; i++) {
        str += dec2hex[Number(guts[i])];
      }

      if ((guts.length == 4) && (styleString.substr(3, 1) == "a")) {
        alpha = guts[3];
      }
    } else {
      str = styleString;
    }

    return [str, alpha];
  }

  function processLineCap(lineCap) {
    switch (lineCap) {
      case "butt":
        return "flat";
      case "round":
        return "round";
      case "square":
      default:
        return "square";
    }
  }

  /**
   * This class implements CanvasRenderingContext2D interface as described by
   * the WHATWG.
   * @param {HTMLElement} surfaceElement The element that the 2D context should
   * be associated with
   */
   function CanvasRenderingContext2D_(surfaceElement) {
    this.m_ = createMatrixIdentity();

    this.mStack_ = [];
    this.aStack_ = [];
    this.currentPath_ = [];

    // Canvas context properties
    this.strokeStyle = "#000";
    this.fillStyle = "#000";

    this.lineWidth = 1;
    this.lineJoin = "miter";
    this.lineCap = "butt";
    this.miterLimit = Z * 1;
    this.globalAlpha = 1;
    this.canvas = surfaceElement;

    var el = surfaceElement.ownerDocument.createElement('div');
    el.style.width =  surfaceElement.clientWidth + 'px';
    el.style.height = surfaceElement.clientHeight + 'px';
    el.style.overflow = 'hidden';
    el.style.position = 'absolute';
    surfaceElement.appendChild(el);

    this.element_ = el;
    this.arcScaleX_ = 1;
    this.arcScaleY_ = 1;
  };

  var contextPrototype = CanvasRenderingContext2D_.prototype;
  contextPrototype.clearRect = function() {
    this.element_.innerHTML = "";
    this.currentPath_ = [];
  };

  contextPrototype.beginPath = function() {
    // TODO: Branch current matrix so that save/restore has no effect
    //       as per safari docs.

    this.currentPath_ = [];
  };

  contextPrototype.moveTo = function(aX, aY) {
    this.currentPath_.push({type: "moveTo", x: aX, y: aY});
    this.currentX_ = aX;
    this.currentY_ = aY;
  };

  contextPrototype.lineTo = function(aX, aY) {
    this.currentPath_.push({type: "lineTo", x: aX, y: aY});
    this.currentX_ = aX;
    this.currentY_ = aY;
  };

  contextPrototype.bezierCurveTo = function(aCP1x, aCP1y,
                                            aCP2x, aCP2y,
                                            aX, aY) {
    this.currentPath_.push({type: "bezierCurveTo",
                           cp1x: aCP1x,
                           cp1y: aCP1y,
                           cp2x: aCP2x,
                           cp2y: aCP2y,
                           x: aX,
                           y: aY});
    this.currentX_ = aX;
    this.currentY_ = aY;
  };

  contextPrototype.quadraticCurveTo = function(aCPx, aCPy, aX, aY) {
    // the following is lifted almost directly from
    // http://developer.mozilla.org/en/docs/Canvas_tutorial:Drawing_shapes
    var cp1x = this.currentX_ + 2.0 / 3.0 * (aCPx - this.currentX_);
    var cp1y = this.currentY_ + 2.0 / 3.0 * (aCPy - this.currentY_);
    var cp2x = cp1x + (aX - this.currentX_) / 3.0;
    var cp2y = cp1y + (aY - this.currentY_) / 3.0;
    this.bezierCurveTo(cp1x, cp1y, cp2x, cp2y, aX, aY);
  };

  contextPrototype.arc = function(aX, aY, aRadius,
                                  aStartAngle, aEndAngle, aClockwise) {
    aRadius *= Z;
    var arcType = aClockwise ? "at" : "wa";

    var xStart = aX + (mc(aStartAngle) * aRadius) - Z2;
    var yStart = aY + (ms(aStartAngle) * aRadius) - Z2;

    var xEnd = aX + (mc(aEndAngle) * aRadius) - Z2;
    var yEnd = aY + (ms(aEndAngle) * aRadius) - Z2;

    // IE won't render arches drawn counter clockwise if xStart == xEnd.
    if (xStart == xEnd && !aClockwise) {
      xStart += 0.125; // Offset xStart by 1/80 of a pixel. Use something
                       // that can be represented in binary
    }

    this.currentPath_.push({type: arcType,
                           x: aX,
                           y: aY,
                           radius: aRadius,
                           xStart: xStart,
                           yStart: yStart,
                           xEnd: xEnd,
                           yEnd: yEnd});

  };

  contextPrototype.rect = function(aX, aY, aWidth, aHeight) {
    this.moveTo(aX, aY);
    this.lineTo(aX + aWidth, aY);
    this.lineTo(aX + aWidth, aY + aHeight);
    this.lineTo(aX, aY + aHeight);
    this.closePath();
  };

  contextPrototype.strokeRect = function(aX, aY, aWidth, aHeight) {
    // Will destroy any existing path (same as FF behaviour)
    this.beginPath();
    this.moveTo(aX, aY);
    this.lineTo(aX + aWidth, aY);
    this.lineTo(aX + aWidth, aY + aHeight);
    this.lineTo(aX, aY + aHeight);
    this.closePath();
    this.stroke();
  };

  contextPrototype.fillRect = function(aX, aY, aWidth, aHeight) {
    // Will destroy any existing path (same as FF behaviour)
    this.beginPath();
    this.moveTo(aX, aY);
    this.lineTo(aX + aWidth, aY);
    this.lineTo(aX + aWidth, aY + aHeight);
    this.lineTo(aX, aY + aHeight);
    this.closePath();
    this.fill();
  };

  contextPrototype.createLinearGradient = function(aX0, aY0, aX1, aY1) {
    var gradient = new CanvasGradient_("gradient");
    return gradient;
  };

  contextPrototype.createRadialGradient = function(aX0, aY0,
                                                   aR0, aX1,
                                                   aY1, aR1) {
    var gradient = new CanvasGradient_("gradientradial");
    gradient.radius1_ = aR0;
    gradient.radius2_ = aR1;
    gradient.focus_.x = aX0;
    gradient.focus_.y = aY0;
    return gradient;
  };

  contextPrototype.drawImage = function (image, var_args) {
    var dx, dy, dw, dh, sx, sy, sw, sh;

    // to find the original width we overide the width and height
    var oldRuntimeWidth = image.runtimeStyle.width;
    var oldRuntimeHeight = image.runtimeStyle.height;
    image.runtimeStyle.width = 'auto';
    image.runtimeStyle.height = 'auto';

    // get the original size
    var w = image.width;
    var h = image.height;

    // and remove overides
    image.runtimeStyle.width = oldRuntimeWidth;
    image.runtimeStyle.height = oldRuntimeHeight;

    if (arguments.length == 3) {
      dx = arguments[1];
      dy = arguments[2];
      sx = sy = 0;
      sw = dw = w;
      sh = dh = h;
    } else if (arguments.length == 5) {
      dx = arguments[1];
      dy = arguments[2];
      dw = arguments[3];
      dh = arguments[4];
      sx = sy = 0;
      sw = w;
      sh = h;
    } else if (arguments.length == 9) {
      sx = arguments[1];
      sy = arguments[2];
      sw = arguments[3];
      sh = arguments[4];
      dx = arguments[5];
      dy = arguments[6];
      dw = arguments[7];
      dh = arguments[8];
    } else {
      throw "Invalid number of arguments";
    }

    var d = this.getCoords_(dx, dy);

    var w2 = sw / 2;
    var h2 = sh / 2;

    var vmlStr = [];

    var W = 10;
    var H = 10;

    // For some reason that I've now forgotten, using divs didn't work
    vmlStr.push(' <g_vml_:group',
                ' coordsize="', Z * W, ',', Z * H, '"',
                ' coordorigin="0,0"' ,
                ' style="width:', W, ';height:', H, ';position:absolute;');

    // If filters are necessary (rotation exists), create them
    // filters are bog-slow, so only create them if abbsolutely necessary
    // The following check doesn't account for skews (which don't exist
    // in the canvas spec (yet) anyway.

    if (this.m_[0][0] != 1 || this.m_[0][1]) {
      var filter = [];

      // Note the 12/21 reversal
      filter.push("M11='", this.m_[0][0], "',",
                  "M12='", this.m_[1][0], "',",
                  "M21='", this.m_[0][1], "',",
                  "M22='", this.m_[1][1], "',",
                  "Dx='", mr(d.x / Z), "',",
                  "Dy='", mr(d.y / Z), "'");

      // Bounding box calculation (need to minimize displayed area so that
      // filters don't waste time on unused pixels.
      var max = d;
      var c2 = this.getCoords_(dx + dw, dy);
      var c3 = this.getCoords_(dx, dy + dh);
      var c4 = this.getCoords_(dx + dw, dy + dh);

      max.x = Math.max(max.x, c2.x, c3.x, c4.x);
      max.y = Math.max(max.y, c2.y, c3.y, c4.y);

      vmlStr.push("padding:0 ", mr(max.x / Z), "px ", mr(max.y / Z),
                  "px 0;filter:progid:DXImageTransform.Microsoft.Matrix(",
                  filter.join(""), ", sizingmethod='clip');")
    } else {
      vmlStr.push("top:", mr(d.y / Z), "px;left:", mr(d.x / Z), "px;")
    }

    vmlStr.push(' ">' ,
                '<g_vml_:image src="', image.src, '"',
                ' style="width:', Z * dw, ';',
                ' height:', Z * dh, ';"',
                ' cropleft="', sx / w, '"',
                ' croptop="', sy / h, '"',
                ' cropright="', (w - sx - sw) / w, '"',
                ' cropbottom="', (h - sy - sh) / h, '"',
                ' />',
                '</g_vml_:group>');

    this.element_.insertAdjacentHTML("BeforeEnd",
                                    vmlStr.join(""));
  };

  contextPrototype.stroke = function(aFill) {
    var lineStr = [];
    var lineOpen = false;
    var a = processStyle(aFill ? this.fillStyle : this.strokeStyle);
    var color = a[0];
    var opacity = a[1] * this.globalAlpha;

    var W = 10;
    var H = 10;

    lineStr.push('<g_vml_:shape',
                 ' fillcolor="', color, '"',
                 ' filled="', Boolean(aFill), '"',
                 ' style="position:absolute;width:', W, ';height:', H, ';"',
                 ' coordorigin="0 0" coordsize="', Z * W, ' ', Z * H, '"',
                 ' stroked="', !aFill, '"',
                 ' strokeweight="', this.lineWidth, '"',
                 ' strokecolor="', color, '"',
                 ' path="');

    var newSeq = false;
    var min = {x: null, y: null};
    var max = {x: null, y: null};

    for (var i = 0; i < this.currentPath_.length; i++) {
      var p = this.currentPath_[i];

      if (p.type == "moveTo") {
        lineStr.push(" m ");
        var c = this.getCoords_(p.x, p.y);
        lineStr.push(mr(c.x), ",", mr(c.y));
      } else if (p.type == "lineTo") {
        lineStr.push(" l ");
        var c = this.getCoords_(p.x, p.y);
        lineStr.push(mr(c.x), ",", mr(c.y));
      } else if (p.type == "close") {
        lineStr.push(" x ");
      } else if (p.type == "bezierCurveTo") {
        lineStr.push(" c ");
        var c = this.getCoords_(p.x, p.y);
        var c1 = this.getCoords_(p.cp1x, p.cp1y);
        var c2 = this.getCoords_(p.cp2x, p.cp2y);
        lineStr.push(mr(c1.x), ",", mr(c1.y), ",",
                     mr(c2.x), ",", mr(c2.y), ",",
                     mr(c.x), ",", mr(c.y));
      } else if (p.type == "at" || p.type == "wa") {
        lineStr.push(" ", p.type, " ");
        var c  = this.getCoords_(p.x, p.y);
        var cStart = this.getCoords_(p.xStart, p.yStart);
        var cEnd = this.getCoords_(p.xEnd, p.yEnd);

        lineStr.push(mr(c.x - this.arcScaleX_ * p.radius), ",",
                     mr(c.y - this.arcScaleY_ * p.radius), " ",
                     mr(c.x + this.arcScaleX_ * p.radius), ",",
                     mr(c.y + this.arcScaleY_ * p.radius), " ",
                     mr(cStart.x), ",", mr(cStart.y), " ",
                     mr(cEnd.x), ",", mr(cEnd.y));
      }


      // TODO: Following is broken for curves due to
      //       move to proper paths.

      // Figure out dimensions so we can do gradient fills
      // properly
      if(c) {
        if (min.x == null || c.x < min.x) {
          min.x = c.x;
        }
        if (max.x == null || c.x > max.x) {
          max.x = c.x;
        }
        if (min.y == null || c.y < min.y) {
          min.y = c.y;
        }
        if (max.y == null || c.y > max.y) {
          max.y = c.y;
        }
      }
    }
    lineStr.push(' ">');

    if (typeof this.fillStyle == "object") {
      var focus = {x: "50%", y: "50%"};
      var width = (max.x - min.x);
      var height = (max.y - min.y);
      var dimension = (width > height) ? width : height;

      focus.x = mr((this.fillStyle.focus_.x / width) * 100 + 50) + "%";
      focus.y = mr((this.fillStyle.focus_.y / height) * 100 + 50) + "%";

      var colors = [];

      // inside radius (%)
      if (this.fillStyle.type_ == "gradientradial") {
        var inside = (this.fillStyle.radius1_ / dimension * 100);

        // percentage that outside radius exceeds inside radius
        var expansion = (this.fillStyle.radius2_ / dimension * 100) - inside;
      } else {
        var inside = 0;
        var expansion = 100;
      }

      var insidecolor = {offset: null, color: null};
      var outsidecolor = {offset: null, color: null};

      // We need to sort 'colors' by percentage, from 0 > 100 otherwise ie
      // won't interpret it correctly
      this.fillStyle.colors_.sort(function (cs1, cs2) {
        return cs1.offset - cs2.offset;
      });

      for (var i = 0; i < this.fillStyle.colors_.length; i++) {
        var fs = this.fillStyle.colors_[i];

        colors.push( (fs.offset * expansion) + inside, "% ", fs.color, ",");

        if (fs.offset > insidecolor.offset || insidecolor.offset == null) {
          insidecolor.offset = fs.offset;
          insidecolor.color = fs.color;
        }

        if (fs.offset < outsidecolor.offset || outsidecolor.offset == null) {
          outsidecolor.offset = fs.offset;
          outsidecolor.color = fs.color;
        }
      }
      colors.pop();

      lineStr.push('<g_vml_:fill',
                   ' color="', outsidecolor.color, '"',
                   ' color2="', insidecolor.color, '"',
                   ' type="', this.fillStyle.type_, '"',
                   ' focusposition="', focus.x, ', ', focus.y, '"',
                   ' colors="', colors.join(""), '"',
                   ' opacity="', opacity, '" />');
    } else if (aFill) {
      lineStr.push('<g_vml_:fill color="', color, '" opacity="', opacity, '" />');
    } else {
      lineStr.push(
        '<g_vml_:stroke',
        ' opacity="', opacity,'"',
        ' joinstyle="', this.lineJoin, '"',
        ' miterlimit="', this.miterLimit, '"',
        ' endcap="', processLineCap(this.lineCap) ,'"',
        ' weight="', this.lineWidth, 'px"',
        ' color="', color,'" />'
      );
    }

    lineStr.push("</g_vml_:shape>");

    this.element_.insertAdjacentHTML("beforeEnd", lineStr.join(""));

    this.currentPath_ = [];
  };

  contextPrototype.fill = function() {
    this.stroke(true);
  }

  contextPrototype.closePath = function() {
    this.currentPath_.push({type: "close"});
  };

  /**
   * @private
   */
  contextPrototype.getCoords_ = function(aX, aY) {
    return {
      x: Z * (aX * this.m_[0][0] + aY * this.m_[1][0] + this.m_[2][0]) - Z2,
      y: Z * (aX * this.m_[0][1] + aY * this.m_[1][1] + this.m_[2][1]) - Z2
    }
  };

  contextPrototype.save = function() {
    var o = {};
    copyState(this, o);
    this.aStack_.push(o);
    this.mStack_.push(this.m_);
    this.m_ = matrixMultiply(createMatrixIdentity(), this.m_);
  };

  contextPrototype.restore = function() {
    copyState(this.aStack_.pop(), this);
    this.m_ = this.mStack_.pop();
  };

  contextPrototype.translate = function(aX, aY) {
    var m1 = [
      [1,  0,  0],
      [0,  1,  0],
      [aX, aY, 1]
    ];

    this.m_ = matrixMultiply(m1, this.m_);
  };

  contextPrototype.rotate = function(aRot) {
    var c = mc(aRot);
    var s = ms(aRot);

    var m1 = [
      [c,  s, 0],
      [-s, c, 0],
      [0,  0, 1]
    ];

    this.m_ = matrixMultiply(m1, this.m_);
  };

  contextPrototype.scale = function(aX, aY) {
    this.arcScaleX_ *= aX;
    this.arcScaleY_ *= aY;
    var m1 = [
      [aX, 0,  0],
      [0,  aY, 0],
      [0,  0,  1]
    ];

    this.m_ = matrixMultiply(m1, this.m_);
  };

  /******** STUBS ********/
  contextPrototype.clip = function() {
    // TODO: Implement
  };

  contextPrototype.arcTo = function() {
    // TODO: Implement
  };

  contextPrototype.createPattern = function() {
    return new CanvasPattern_;
  };

  // Gradient / Pattern Stubs
  function CanvasGradient_(aType) {
    this.type_ = aType;
    this.radius1_ = 0;
    this.radius2_ = 0;
    this.colors_ = [];
    this.focus_ = {x: 0, y: 0};
  }

  CanvasGradient_.prototype.addColorStop = function(aOffset, aColor) {
    aColor = processStyle(aColor);
    this.colors_.push({offset: 1-aOffset, color: aColor});
  };

  function CanvasPattern_() {}

  // set up externs
  G_vmlCanvasManager = G_vmlCanvasManager_;
  CanvasRenderingContext2D = CanvasRenderingContext2D_;
  CanvasGradient = CanvasGradient_;
  CanvasPattern = CanvasPattern_;

})();

} // if
