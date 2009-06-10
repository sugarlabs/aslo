
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

    SimileAjax.loaded = true;
}

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
})();


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
