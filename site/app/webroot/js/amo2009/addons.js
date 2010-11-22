/** Constants **/
var PLATFORM_OTHER    = 0;
var PLATFORM_WINDOWS  = 1;
var PLATFORM_LINUX    = 2;
var PLATFORM_MACOSX   = 3;
var PLATFORM_MAC      = 4;
var UA_PATTERN_FIREFOX = /Mozilla.*(Firefox|Minefield|Namoroka|Shiretoko|GranParadiso|BonEcho|Iceweasel)\/([^\s]*).*$/;
var UA_PATTERN_SEAMONKEY = /Mozilla.*(SeaMonkey|Iceape)\/([^\s]*).*$/;
var UA_PATTERN_MOBILE = /Mozilla.*(Fennec)\/([^\s]*)$/;

/** Global Variables **/

// determine browser platform
if (navigator.platform.indexOf("Win32") != -1)
    gPlatform = PLATFORM_WINDOWS;
else if (navigator.platform.indexOf("Linux") != -1)
    gPlatform = PLATFORM_LINUX;
else if (navigator.userAgent.indexOf("Mac OS X") != -1)
    gPlatform = PLATFORM_MACOSX;
else if (navigator.userAgent.indexOf("MSIE 5.2") != -1)
    gPlatform = PLATFORM_MACOSX;
else if (navigator.platform.indexOf("Mac") != -1)
    gPlatform = PLATFORM_MAC;
else
    gPlatform = PLATFORM_OTHER;

// determine browser type and version
gIsFirefox = false;
gIsSeaMonkey = false;
gIsMobile = false;
gBrowserVersion = null;

var ua = navigator.userAgent;
var uamatch = UA_PATTERN_FIREFOX.exec(ua);
if (uamatch && uamatch.length == 3) { // Firefox-like browser
    gIsFirefox = true;
    gBrowserVersion = uamatch[2];
    var uamobilematch = UA_PATTERN_MOBILE.exec(ua);
    if (uamobilematch && uamobilematch.length == 3) { // Mobile Firefox Browser
        gIsMobile = true;
        // Overwrite Firefox version with mobile browser version.  This may not be a good idea.
        gBrowserVersion = uamobilematch[2];
    }
} else {

    var uamatch = UA_PATTERN_SEAMONKEY.exec(ua);
    if (uamatch && uamatch.length == 3) { // SeaMonkey-like browser
        gIsSeaMonkey = true;
        gBrowserVersion = uamatch[2];
    }
}

/** Functions **/
function getPlatformName()
{
  if (gPlatform == PLATFORM_WINDOWS)
    return "Windows";
  if (gPlatform == PLATFORM_LINUX)
    return "Linux";
  if (gPlatform == PLATFORM_MACOSX)
    return "MacOSX";
  return "Unknown";
}

function getInstallURL(aEvent) {
    // The event target might be the link itself or one of its children
    var target = aEvent.target;
    while (target && !target.href)
      target = target.parentNode;

    return target && target.href;
}

function checkMatchUserAgentAppId() {
    return (gIsFirefox && APP_ID == APP_FIREFOX
        || gIsSeaMonkey && APP_ID == APP_SEAMONKEY)
}

/**
 * checks that the file-input elements are uploading the correct filetypes (by
 * check extension)
 * @param inputs jQuery selector for the input element(s)
 * @param validImages a regular expression of valid image types (includes period)
 * @return true if the files are valid image types that remora accepts.
 *         otherwise it returns the file extension that caused the failure
 */
function checkInputFileExtensions(inputs, validImages) {
    var elements = $(inputs);
    for (var i = 0; i < elements.length; ++i) {
        var filename = elements.eq(i).val();
        if (filename.length == 0)
            continue;
        // account for files that do not have extensions
        var j = filename.lastIndexOf('.');
        // Sometimes windows makes extensions UPPERCASE. Fix it.
        var extension = filename.substr(j < 0 ? 0 : j).toLowerCase();
        if (validImages.test(extension) == false) {
            return extension;
        }
    }
    return true;
}

/**
 * Install an add-on into the current browser-type application
 * (mostly: Firefox, SeaMonkey)
 */
function install( aEvent, extName, iconURL, extHash)  {

    if (aEvent.altKey || !window.InstallTrigger || !checkMatchUserAgentAppId())
        return true;

    var url = getInstallURL(aEvent);

    if (url) {

        var params = new Array();

        params[extName] = {
            URL: url,
            IconURL: iconURL,
            toString: function () { return this.URL; }
        };

        // Only add the Hash param if it exists.
        //
        // We optionally add this to params[] because installTrigger
        // will still try to compare a null hash as long as the var is set.
        if (extHash) {
            params[extName].Hash = extHash;
        }

        InstallTrigger.install(params);

        return false;
    }
    return true;
}

/**
 * Install a search engine (opensearch)
 * Returns false in case of success (sic!) because that will keep the file link
 * from being followed.
 */
function addEngine(engineURL) {
    if (window.external && ("AddSearchProvider" in window.external)) {
        window.external.AddSearchProvider(engineURL);
        return false;
    } else {
        alert(error_opensearch_unsupported);
        return true;
    }
}

/**
 * Detect which install button should show, and hide the rest
 */
function fixPlatformLinks(versionID, name) {
    if (gPlatform == PLATFORM_OTHER) return true; // only hide something if we were able to detect platforms
    var platform = getPlatformName();
    var outer = $("#install-"+ versionID);
    var installs = outer.find("p.install-button");

    // hide incompatible installs
    var others = installs.not(".platform-ALL,.platform-"+platform);
    others.hide();
    others.each(function() {
        var expParents = $(this).parents('.exp-loggedout, .exp-confirmed');
        if ($(expParents).length)
            $(expParents).hide();
        else
            $(this).hide();
    });


    if (installs.length == others.length) {
        outer.find(".exp-loggedout").hide();
        outer.addClass('unavailable').empty().prepend('<strong class="compatmsg">'+sprintf(addOnNotAvailableForPlatform, name, platform)+'</strong>');
    }

    return true;
}

/**
* Show "add to x" or "download now" depending on user agent
*/
function installVersusDownloadCheck(triggerID, installString, downloadString) {
    var buttonMessage = installString;
    if (!checkMatchUserAgentAppId())
        buttonMessage = downloadString;
    $("#" + triggerID + " strong").text(buttonMessage);
    $("#" + triggerID + " .install-button-text").text(buttonMessage);
}

/**
*   Initializes the download instructions popup
*/
function initDownloadPopup(triggerID, popupID)
{
    var container = $("#"+popupID).parents(".app_install");

    function positionPopup(e) {
        var offset = container.offset();
        $("#"+popupID)
            .remove()
            .appendTo("body")
            .css({ position: 'absolute', left: offset.left, top: offset.top })
            .show();
    }

    $("#"+triggerID).click(positionPopup);

    var dd = new DropdownArea();
    dd.trigger = $("#"+triggerID);
    dd.target = ("#"+popupID+" .app_install-popup");
    dd.targetParent = ("#"+popupID);
    dd.preventDefault = false;
    dd.showSpeed = 600;
    dd.hideSpeed = 300;
    dd.init();
}

/**
 * Provide hints on install buttons for add-ons incompatible with the
 * currently used browser.
 *
 * @param int addonID
 * @param int versionID
 * @param string fromVer minimum compatible Firefox version
 * @param string toVer maximum compatible Firefox version
 * @param bool showVersionLink offer a link to the user which will remove the compatibility hint (and allow them to download the add-on)
 */
function addCompatibilityHints(addonID, versionID, fromVer, toVer, showVersionLink) {
    // we are sugar, do not be so strong for anon users
    showVersionLink = true;

    var vc = new VersionCompare();

    var uapattern_olpc = /OLPC\/0\.([^-]*)-/;
    var uamatch_olpc = uapattern_olpc.exec(navigator.userAgent);
    if (uamatch_olpc) {
        if (vc.compareVersions(uamatch_olpc[1], "4.6") <= 0)
            var version = "0.82";
        else
            var version = "0.84";
    } else {
        var uapattern = /Sugar Labs\/([^\s]*).*$/;
        var uamatch = uapattern.exec(navigator.userAgent);
        if (!uamatch || uamatch.length < 2) {
            var version = SITE_SUGAR_STABLE;
        } else {
            var version = uamatch[1];
        }
    }

    var outer = $("#install-"+ versionID);

    if (vc.compareVersions(version, fromVer)<0)
        var needUpgrade = true;
    else if(vc.compareVersions(version, toVer)>0)
        var needUpgrade = false;
    else {
        return true;
	}

    var links = outer.find("p:visible a"); // find visible install boxes
    if (links.length == 0) return true; // nothing to do

    // duplicate button and hide original (to be able to restore it later)
    var cloned = outer.clone();
    cloned.attr('id', 'orig-'+ versionID);
    cloned.hide();

    outer.addClass('unavailable');
    outer.find(".exp-confirm-install").hide();
    outer.after(cloned);

    // remove link
    var url = links.attr('href');
    links.removeAttr("href");
    links.removeAttr("onClick");
    links.removeAttr("title");
    links.css('cursor', 'default');
    links.parent().css('float', 'none');
    // freeze button
    links.attr('frozen', 'true');

    // determine "all versions" page url
    if (url.indexOf('downloads') > 0)
        url = url.substring(0, url.indexOf('downloads'));
    else if (url.indexOf('addons') > 0)
        url = url.substring(0, url.indexOf('addons'));
    url = url+'addons/versions/'+addonID;

    var l_parent = links.parent();
    links.remove();

    if (needUpgrade && showVersionLink) {
        l_parent.after(
            '<strong class="compatmsg">'+
            sprintf(app_compat_older_version_or_ignore_check, url, "removeCompatibilityHint('"+versionID+"');return false;")+
            '</strong>'
        );
    } else if (!needUpgrade && showVersionLink) {
        l_parent
            .after('<strong class="compatmsg">'+app_compat_older_firefox_only+'</strong>'
                   +'<strong class="compatmsg">'
                   +'<a href="#" onclick="removeCompatibilityHint(\''+versionID+'\');return false;">' +app_compat_ignore_check+ '</a>'
                   +'</strong>');
    } else if (!needUpgrade) {
        l_parent.after(
            '<strong class="compatmsg">'+
            app_compat_older_firefox_only+
            '</strong>'
        );
    } else {
        l_parent.after(
            '<strong class="compatmsg">'+
            sprintf(app_compat_try_old_version, url)+
            '</strong>'
        );
    }

    // we are sugar
    if (true) {
        // pass
    } else
    if (needUpgrade) {
        if (vc.compareVersions(fromVer, LATEST_FIREFOX_DEVEL_VERSION)>=0) {
            l_parent.after(sprintf('<strong class="compatmsg">' + app_compat_unreleased_version + "</strong>", 'http://www.mozilla.com/' + LANG + '/firefox/all-beta.html#' + LANG, LATEST_FIREFOX_DEVEL_VERSION));
        }else if (vc.compareVersions(fromVer, LATEST_FIREFOX_VERSION)<0) {
            l_parent.after(sprintf('<strong class="compatmsg">' + app_compat_update_firefox + "</strong>", 'http://www.mozilla.com/' + LANG + '/firefox/all.html#' + LANG, LATEST_FIREFOX_VERSION));
        }
    }

    return true;
}

/**
 * Gray out themes in the theme browser that are incompatible with the current
 * browser version
 */
function addThemeCompatibility(addonID, fromVer, toVer) {
    var vc = new VersionCompare();
    // only determine compatibility for the same app
    if (!(gIsFirefox && APP_ID == APP_FIREFOX
        || gIsSeaMonkey && APP_ID == APP_SEAMONKEY))
        return true;

    if (vc.compareVersions(gBrowserVersion, fromVer) < 0
        || vc.compareVersions(gBrowserVersion, toVer) > 0) {
        // add overlay
        $('#addon-'+addonID+' div.thumb_item').addClass('incompatible');
        $('#addon-'+addonID)
            .append('<div class="overlay"><p>'
                +'<a>'+sprintf(app_compat_incompatible, APP_PRETTYNAME)+'</a>'
                +'</p></div>')
            .find('a').attr('href', $('#addon-'+addonID+' h4.name a').attr('href'));
        // show overlay on hover
        $('#addon-'+addonID)
            .hover(
                function() {
                    $(this).children('div.overlay').show();
                },
                function() {
                    $(this).children('div.overlay').fadeOut();
                });
    }
    return true;
}

/**
 * Remove "incompatible" message for given version ID, by restoring the
 * original button.
 */
function removeCompatibilityHint(versionID) {
    // find all hidden install buttons
    var orig = $('#orig-' + versionID);
    // remove compatibility hints
    orig.prev().remove();
    // show original buttons
    orig.attr('id', 'install-'+versionID);

    orig.show();

    return true;
}

/**
 *  replaces options in a select drop-down (used for advanced search)
 *  @param select_id - the id of the select tag to replace the options of
 *  @param opt_array - array of options to use
 *  @param selected - which option will be marked as selected
 *
 */
function replaceOptions( select_id , opt_array, selected) {
  $(select_id + " > *").remove();

  for( opt in opt_array) {
	  sel_text = "";
      val = opt_array[opt];
      opt_obj = document.createElement("option");
	  if( val == selected) {opt_obj.selected = "selected";}
      opt_obj.value = val;
      opt_obj.appendChild(document.createTextNode(opt));
      $(select_id).append(opt_obj);
  }
}

/**
 * replace noscript email by an actual link
 * @param obj id of email node
 * @param lp local part
 * @param hp host part
 */
function emailLink(obj, lp, hp) {
    var cont = document.getElementById(obj);
    var em = lp +'@'+ hp;
    var a = document.createElement('a');
    a.setAttribute('href', 'mailto:'+em);
    a.appendChild(document.createTextNode(em));
    cont.replaceChild(a, cont.lastChild);
}

var translation_box = {
    switchLocale: function(tab, locale) {
        var translationBox = $(tab).parent().parent().parent();
        if (translationBox.find('.translation-deletelocale').size() > 0) {
            return;
        }
        translationBox.find('.selected').removeClass('selected');
        $(tab).addClass('selected');
        translationBox.find('.' + locale).addClass('selected');
        this.checkDeleteButton(translationBox);
        translationBox.find('.translation-area .input.selected').trigger('onchange');
        translationBox.find('.' + locale).focus();
    },

    checkDeleteButton: function(translationBox) {
        var defaultLocale = translationBox.find('.translation-area').attr('defaultLocale');
        var selectedLocale = translationBox.find('.translation-tab.selected').text();
        if (selectedLocale == defaultLocale || selectedLocale == '') {
            translationBox.find('.translation-button.remove').hide();
        }
        else {
            translationBox.find('.translation-button.remove').show();
        }

        if (translationBox.find('.translation-tab').length == $('.translation-newlocale-container select option').length) {
            translationBox.find('.translation-button.add').hide(); 
        } else {
            translationBox.find('.translation-button.add').show();
        }
    },

    checkLength: function(field, max) {
        var translationBox = $(field).parent().parent();
        translationBox.find('.translation-maxlength.selected span').text(field.value.length);

        if (field.value.length > max) {
            translationBox.find('.translation-maxlength.selected').addClass('over');
            translationBox.addClass('errors');
        }
        else {
            translationBox.find('.translation-maxlength.selected').removeClass('over');

            if (translationBox.find('.translation-area .over').size() == 0) {
                translationBox.removeClass('errors');
            }
        }
    },

    addTab: function(button) {
        var translationBox = $(button).parent().parent().parent();
        if (translationBox.find('.translation-deletelocale').size() > 0) {
            return;
        }

        if (translationBox.find('.translation-tab').hasClass('new')) {
            translationBox.find('.selected').removeClass('selected');
            translationBox.find('.new').addClass('selected');
            return;
        }

        var tab = '<div class="translation-tab selected new" onclick="translation_box.switchLocale(this, \'new\');"></div>';
        translationBox.find('.selected').removeClass('selected');
        translationBox.find('.translation-tabs').append(tab);
        translationBox.find('.translation-area').append($('.translation-newlocale-container').html());

        // Remove existing translations from available dropdown
        translationBox.find('.translation-tab:not(.new)').each(function(index, item) {
            translationBox.find('.translation-newlocale select option[value="' + $(item).text() + '"]').remove();
        });
        this.checkDeleteButton(translationBox);
    },

    addLocale: function(button, addToAll) {
        var translationBox = $(button).parent().parent().parent().parent().parent();
        var thisBox = translationBox;
        if (addToAll == true) {
            translationBox = translationBox.parent();
        }

        var locale = thisBox.find('select').val();
        var localeName = thisBox.find('select option:selected').text();

        var tab = '<div class="translation-tab selected" title="' + localeName + '" onclick="translation_box.switchLocale(this, \'' + locale + '\');">' + locale + '</div>';

        translationBox.find('.input.' + locale + '.deleted').remove();
        translationBox.find('.translation-area:not(:has(.' + locale + '))').each(function(index, item) {
            var itemID = $(item).attr('itemID');
            var field = 'data[' + $(item).attr('table') + ']' + (itemID != null ? '[' + itemID + ']' : '') + '[' + $(item).attr('field') + '][' + locale + ']';
            $(item).parent().find('.selected').removeClass('selected');

            var fieldType = $(item).find('.input')[0].tagName.toLowerCase();
            var style = $(item).find('.input').attr('style');

            if (fieldType == 'textarea') {
                var fieldHTML = '<textarea class="input ' + locale + ' selected" name="' + field + '" style="' + style + '"';
                var maxLength = $(item).find('.input').attr('maxLength');
                if (maxLength) {
                    fieldHTML += ' maxLength="' + maxLength + '" onkeyup="translation_box.checkLength(this, ' + maxLength + ');" onchange="translation_box.checkLength(this, ' + maxLength + ');"';
                    var afterHTML = '<div class="translation-maxlength ' + locale + ' selected">' + $(item).find('.translation-maxlength').html() + '</div>';
                }
                fieldHTML += '></textarea>';
            }
            else if (fieldType == 'input') {
                var fieldHTML = '<input type="text" class="input ' + locale + ' selected" value="" name="' + field + '" style="' + style + '" />';
                var afterHTML = '';
            }
            $(item).append(fieldHTML).append(afterHTML);
            translationBox.find('.translation-area .input.selected').trigger('onchange');

            $(item).parent().find('.translation-tabs').append(tab);
        });

        thisBox.find('.new').remove();
        this.checkDeleteButton(translationBox);
        thisBox.find('.translation-area .selected').focus();
    },

    confirmRemove: function(button) {
        var translationBox = $(button).parent().parent().parent();

        if (translationBox.find('.translation-deletelocale').size() > 0) {
            return;
        }

        translationBox.find('.translation-area .input.selected').hide();

        translationBox.find('.translation-area .input:first').before($('.translation-deletelocale-container').html());
        translationBox.find('.translation-deletelocale').css('width', translationBox.css('width'));
    },

    removeLocale: function(button) {
        var translationBox = $(button).parent().parent().parent().parent().parent();
        var locale = translationBox.find('.translation-row .selected').text();
        translationBox.find('.translation-tab:contains(' + locale + ')').remove();
        translationBox.find('.translation-area .input.' + locale).val('');
        translationBox.find('.translation-area .input.' + locale).addClass('deleted');
        translationBox.find('.translation-area .input.' + locale).removeClass('selected');

        translationBox.find('.translation-maxlength.' + locale).remove();

        translationBox.find('.translation-deletelocale').remove();
        translationBox.find('.translation-area .input').removeClass('confirm-delete');

        var defaultLocale = translationBox.find('.translation-area').attr('defaultLocale');
        translationBox.find('.translation-tab:contains(' + defaultLocale + ')').addClass('selected');
        translationBox.find('.translation-area .' + defaultLocale).addClass('selected');
        this.checkDeleteButton(translationBox);
    },

    cancelRemove: function(button) {
        var translationBox = $(button).parent().parent().parent().parent().parent();
        translationBox.find('.translation-deletelocale').remove();
        translationBox.find('.translation-area .input').removeClass('confirm-delete');
        translationBox.find('.translation-area .input.selected').show();
        translationBox.find('.translation-area .input.selected').focus();
    },

    cancelAdd: function(button) {
        var translationBox = $(button).parent().parent().parent().parent().parent();
        translationBox.find('.translation-tabs .new').remove();
        translationBox.find('.translation-newlocale').remove();

        var defaultLocale = translationBox.find('.translation-area').attr('defaultLocale');
        translationBox.find('.translation-tab:contains(' + defaultLocale + ')').addClass('selected');
        translationBox.find('.translation-area .' + defaultLocale).addClass('selected');
        this.checkDeleteButton(translationBox);
    },

    showHelp: function(button) {
        var translationBox = $(button).parent().parent();
        if (translationBox.prev('.translation-help').size() > 0) {
            return;
        }
        translationBox.before($('.translation-help-container').html());
        translationBox.prev('.translation-help').slideDown('slow');
    },

    hideHelp: function(button) {
        var helpBox = $(button).parent().parent().parent().parent();
        helpBox.slideUp('normal', function() {
            helpBox.remove();
        });
    }
};

/**
 * sprintf() implementation for Javascript
 * adapted from public domain code initially published at:
 * http://jan.moesen.nu/code/javascript/sprintf-and-printf-in-javascript/
 */
function sprintf()
{
    if (!arguments || arguments.length < 1 || !RegExp) {
        return null;
    }
    var str = arguments[0];
    var re = /([^%]*)%((\d+)\$)?('.|0|\x20)?(-)?(\d+)?(\.\d+)?(%|b|c|d|u|f|o|s|x|X)(.*)/;
    var a = b = [], numSubstitutions = 0, numMatches = 0;

    while ((a = re.exec(str))) {
        var leftpart = a[1], pPos = a[3], pPad = a[4], pJustify = a[5];
        var pMinLength = a[6], pPrecision = a[7], pType = a[8];
        var rightPart = a[9];

        numMatches++;
        if (pType == '%') {
            subst = '%';
        } else {
            if (pPos == '') {
                numSubstitutions++;
                pPos = numSubstitutions;
            }
            if (parseInt(pPos) >= arguments.length) {
                alert('Error! Not enough function arguments (' + (arguments.length - 1)
                    + ', excluding the string)\n'
                    + 'for the number of substitution parameters in string ('
                    + numSubstitutions + ' so far).');
            }
            var param = arguments[parseInt(pPos)];
            var pad = '';
            if (pPad && pPad.substr(0,1) == "'")
                pad = leftpart.substr(1,1);
            else if (pPad)
                pad = pPad;
            var justifyRight = true;
            if (pJustify && pJustify === "-") justifyRight = false;
            var minLength = -1;
            if (pMinLength) minLength = parseInt(pMinLength);
            var precision = -1;
            if (pPrecision && pType == 'f')
                precision = parseInt(pPrecision.substring(1));
            var subst = param;

            switch (pType) {
            case 'b':
                subst = parseInt(param).toString(2);
                break;
            case 'c':
                subst = String.fromCharCode(parseInt(param));
                break;
            case 'd':
                subst = parseInt(param) ? parseInt(param) : 0;
                break;
            case 'u':
                subst = Math.abs(param);
                break;
            case 'f':
                subst = (precision > -1)
                    ? Math.round(parseFloat(param) * Math.pow(10, precision))
                    / Math.pow(10, precision)
                    : parseFloat(param);
                break;
            case 'o':
                subst = parseInt(param).toString(8);
                break;
            case 's':
                subst = param;
                break;
            case 'x':
                subst = ('' + parseInt(param).toString(16)).toLowerCase();
                break;
            case 'X':
                subst = ('' + parseInt(param).toString(16)).toUpperCase();
                break;
            }
            var padLeft = minLength - subst.toString().length;
            if (padLeft > 0) {
                var arrTmp = new Array(padLeft+1);
                var padding = arrTmp.join(pad?pad:" ");
            } else {
                var padding = "";
            }
        }
        str = leftpart + padding + subst + rightPart;
    }
    return str;
}

/*
 ### jQuery Star Rating Plugin v2.0 - 2008-03-12 ###
 By Diego A, http://www.fyneworks.com, diego@fyneworks.com
 - v2 by Keith Wood, http://keith-wood.name/, kbwood@virginbroadband.com.au

 Project: http://plugins.jquery.com/project/MultipleFriendlyStarRating
 Website: http://www.fyneworks.com/jquery/star-rating/

	This is a modified version of the star rating plugin from:
 http://www.phpletter.com/Demo/Jquery-Star-Rating-Plugin/
*/
// ORIGINAL COMMENTS:
/*************************************************
 This is hacked version of star rating created by <a href="http://php.scripts.psu.edu/rja171/widgets/rating.php">Ritesh Agrawal</a>
 It thansform a set of radio type input elements to star rating type and remain the radio element name and value,
 so could be integrated with your form. It acts as a normal radio button.
 modified by : Logan Cai (cailongqun[at]yahoo.com.cn)
 website:www.phpletter.com
************************************************/


/*# AVOID COLLISIONS #*/
;if(jQuery) (function($){
/*# AVOID COLLISIONS #*/

$.fn.rating = function(settings) {
 settings = $.extend({
		cancel: '', // advisory title for the 'cancel' link
		cancelValue: 0,         // value to submit when user click the 'cancel' link
		required: false,         // disables the 'cancel' button so user can only select one of the specified values
		readOnly: false          // disable rating plugin interaction/ values cannot be changed
	}, settings || {});

  var container = this;

 // multiple star ratings on one page
 var groups = {};

	// plugin events
 var event = {
  fill: function(n, el, style){ // fill to the current mouse position.
	  this.drain(n);
	  $(el).prevAll('.star').andSelf().addClass( style || 'star_hover' );
  },
  drain: function(n) { // drain all the stars.
  	$(groups[n].valueElem).siblings('.star').
		 removeClass('star_on').removeClass('star_hover');
  },
  reset: function(n){ // Reset the stars to the default index.
  	if (!$(groups[n].currentElem).is('.cancel')) {
 		 $(groups[n].currentElem).prevAll('.star').andSelf().addClass('star_on');
	  }
  },
  click: function(n, el) { // Selected a star or cancelled
			groups[n].currentElem = el;
			var curValue = $(el).children('a').text();
			// Set value
			$(groups[n].valueElem).val(curValue);
			// Update display
			event.drain(n);
			event.reset(n);
			// callback function, as requested here: http://plugins.jquery.com/node/1655
			if(settings.callback) settings.callback.apply(groups[n].valueElem, [curValue, el]);
  }
 };

	// loop through each matched element

	var radioButtons = $(this).find('input[type=radio]');

	$(this).empty();
	$(this).removeClass('degrade');

 	radioButtons.each(function(i){
		// grouping:
		var n = this.name;

		if(!groups[n]) groups[n] = {count: 0};
		i = groups[n].count;
		groups[n].count++;

		// Things to do with the first element...
		if(i == 0){
			// Accept readOnly setting from 'disabled' property
			settings.readOnly = $(this).attr('disabled') || settings.readOnly;
			// Create value element (disabled if readOnly)
		 groups[n].valueElem = $('<input type="hidden" name="' + n + '" value=""' + (settings.readOnly ? ' disabled="disabled"' : '') + '>');
			// Insert value element into form
   $(container).append(groups[n].valueElem);

			if(settings.readOnly || settings.required){
			// DO NOT display 'cancel' button
			}
			else{


			}
		}; // if (i == 0) (first element)

		// insert rating option right after preview element
		eStar = $('<div class="star"><a title="' + (this.title || this.value) + '">' + this.value + '</a></div>');
		$(container).append(eStar);

		if(settings.readOnly){
			// Mark star as readOnly so user can customize display
			$(eStar).addClass('star_readonly');
		}
		else{
			// Attach mouse events
			$(eStar)
			.mouseover(function(){ event.drain(n); event.fill(n, this); })
			.mouseout(function(){ event.drain(n); event.reset(n); })
			.click(function(){ event.click(n, this); });
		};

		//if(console) console.log(['###', n, this.checked, groups[n].initial]);
		if(this.checked) groups[n].currentElem = eStar;



		// reset display if last element
		if(i + 1 == this.length) event.reset(n);

	}); // each element



	// initialize groups...
	for(n in groups)//{ not needed, save a byte!
		if(groups[n].currentElem){
			event.fill(n, groups[n].currentElem, 'star_on');
			$(groups[n].valueElem).val($(groups[n].currentElem).children('a').text());
		}
	//}; not needed, save a byte!

	return this; // don't break the chain...
};



/*# AVOID COLLISIONS #*/
})(jQuery);
/*# AVOID COLLISIONS #*/


function VersionCompare() {
    /**
     * Mozilla-style version numbers comparison in Javascript
     * (JS-translated version of PHP versioncompare component)
     * @return -1: a<b, 0: a==b, 1: a>b
     */
    this.compareVersions = function(a,b) {
        var al = a.split('.');
        var bl = b.split('.');

        for (var i=0; i<al.length || i<bl.length; i++) {
            var ap = (i<al.length ? al[i] : null);
            var bp = (i<bl.length ? bl[i] : null);

            var r = this.compareVersionParts(ap,bp);
            if (r != 0)
                return r;
        }

        return 0;
    }

    /**
     * helper function: compare a single version part
     */
    this.compareVersionParts = function(ap,bp) {
        var avp = this.parseVersionPart(ap);
        var bvp = this.parseVersionPart(bp);

        var r = this.cmp(avp['numA'],bvp['numA']);
        if (r) return r;

        r = this.strcmp(avp['strB'],bvp['strB']);
        if (r) return r;

        r = this.cmp(avp['numC'],bvp['numC']);
        if (r) return r;

        return this.strcmp(avp['extraD'],bvp['extraD']);
    }

    /**
     * helper function: parse a version part
     */
    this.parseVersionPart = function(p) {
        if (p == '*') {
            return {
                'numA'   : Number.MAX_VALUE,
                'strB'   : '',
                'numC'   : 0,
                'extraD' : ''
                };
        }

        var pattern = /^([-\d]*)([^-\d]*)([-\d]*)(.*)$/;
        var m = pattern.exec(p);

        var r = {
            'numA'  : parseInt(m[1]),
            'strB'   : m[2],
            'numC'   : parseInt(m[3]),
            'extraD' : m[4]
            };

        if (r['strB'] == '+') {
            r['numA']++;
            r['strB'] = 'pre';
        }

        return r;
    }

    /**
     * helper function: compare numeric version parts
     */
    this.cmp = function(an,bn) {
        if (isNaN(an)) an = 0;
        if (isNaN(bn)) bn = 0;

        if (an < bn)
            return -1;

        if (an > bn)
            return 1;

        return 0;
    }

    /**
     * helper function: compare string version parts
     */
    this.strcmp = function(as,bs) {
        if (as == bs)
            return 0;

        // any string comes *before* the empty string
        if (as == '')
            return 1;

        if (bs == '')
            return -1;

        // normal string comparison for non-empty strings (like strcmp)
        if (as < bs)
            return -1;
        else if(as > bs)
            return 1;
        else
            return 0;
    }
}

/**
 * jQuery slider plugin - 2008-07-07
 * lorchard@mozilla.com
 */

/*# AVOID COLLISIONS #*/
;if(jQuery) (function($){
/*# AVOID COLLISIONS #*/

$.fn.slider = function(settings) {
    var $slider = arguments.callee.support;
    new $slider(this[0].id, settings);
    return this;
}

$.fn.slider.support = function(slider_id, options) {
    this.init(slider_id, options);
};

$.fn.slider.support.prototype = function() {

    return {

        /**
         * Wire up the scroll events.
         */
        init: function(slider_id, options) {
            this.options = $.extend({
                duration: 250,
                prev_img_src: '/img/slider-prev.gif',
                prev_disabled_img_src: '/img/slider-prev-disabled.gif',
                next_img_src: '/img/slider-next.gif',
                next_disabled_img_src: '/img/slider-next-disabled.gif'
            }, options || {});

            this.slider_id  = slider_id;
            this.slider_sel = '#' + slider_id;

            this._resize_timer = null;

            var that = this;
            $(window).unload(function() { return that.onUnload(); });
            $(window).resize(function() { return that.onResize(); });
            $(document).ready(function() { return that.onReady(); });
        },

        /**
         * Perform scroller initialization on page readiness
         */
        onReady: function() {
            this.items = $(this.slider_sel + ' .item');
            this.item_idx = 0;

            var that = this;
            $(this.slider_sel + ' .controls .prev').click(function(e) {
                return that.onClickPrev(e)
            });
            $(this.slider_sel + ' .controls .next').click(function(e) {
                return that.onClickNext(e)
            });

            this.onResize();
        },

        /**
         * Unload some resources on page unload, being superstitous about a
         * memory leak.
         */
        onUnload: function() {
            delete this.items;
        },

        /**
         * React to browser resizing, with a delayed timer to prevent lots of
         * overlapping calls.
         */
        onResize: function() {
            var that = this;
            if (this._resize_timer)
                clearTimeout(this._resize_timer);
            this._resize_timer = window.setTimeout(function() {
                that._doResize();
            }, 50);
        },

        /**
         * Perform the actual work of readjusting after resize.
         */
        _doResize: function() {
            var slider = $(this.slider_sel);
            var viewport = slider.select('.viewport')[0];

            $(this.slider_sel + ' .addon').width(viewport.offsetWidth - 260);
            this.revealSelectedItem(15);
        },

        /**
         * React to "prev" button click.
         */
        onClickPrev: function(e) {
            if ( (this.item_idx - 1) < 0 ) return false;
            this.item_idx--;
            return this.changeSelectedItem();
        },

        /**
         * React to "next" button click.
         */
        onClickNext: function(e) {
            if ( (this.item_idx + 1) >= this.items.length ) return false;
            this.item_idx++;
            return this.changeSelectedItem();
        },

        /**
         * Update the selected item, item number, and button display states.
         */
        changeSelectedItem: function() {
            this.updateItemNumber();
            this.updateButtonStates();
            this.revealSelectedItem();
            return false;
        },

        /**
         * Update the number displayed indicating current item.
         */
        updateItemNumber: function() {
            $(this.slider_sel + ' .controls .index').text( this.item_idx + 1 );
        },

        /**
         * Update the enabled / disabled images for the next / prev buttons.
         */
        updateButtonStates: function() {
            var img_p = $(this.slider_sel + ' .controls .prev img')[0];
            if ( this.item_idx == 0 ) {
                img_p.src = this.options.prev_disabled_img_src;
            } else {
                img_p.src = this.options.prev_img_src;
            }

            var img_n = $(this.slider_sel + ' .controls .next img')[0];
            if ( this.item_idx == this.items.length - 1 ) {
                img_n.src = this.options.next_disabled_img_src;
            } else {
                img_n.src = this.options.next_img_src;
            }
        },

        /**
         * Reveal the selected item with an animation.
         */
        revealSelectedItem: function(delay) {
            if (!delay) delay = this.options.slide_duration;
            $(this.slider_sel + ' .viewport').animate({
                scrollLeft: this.items[ this.item_idx ].offsetLeft
            }, delay)
        },

        EOF: null // I hate trailing comma errors.
    };
}();

/*# AVOID COLLISIONS #*/
})(jQuery);
/*# AVOID COLLISIONS #*/

/*# AVOID COLLISIONS #*/
;if(jQuery) (function($){
/*# AVOID COLLISIONS #*/

$.fn.collection = function(options) {
    options = $.extend({
               'shoppingCart':false
        }, options || {});

    var installUrl = options.installUrl;

    $('input.want').change(function() {
        $('#done-'+$(this).attr('value')).slideToggle('fast');
        $('.installsubmit input').attr('disabled', ($('input.want:checked').length == 0))
    });
	$('input.want:checked').each(function() {
		$('#done-'+$(this).attr('value')).show();
	});
    $('.installsubmit input').attr('disabled', ($('input.want:checked').length == 0))

        var confirmInstall = function() {
            $.post(installUrl, $('input.want:checked').serialize(),
                function(data,status){
                    $('#installdialog').html(data)
                                       .jqmAddClose('#installdialog .installsubmit input.cancel')
                                       .show();
                });
        };
        $('#installdialog').jqm({onShow: confirmInstall,
                                trigger: '#collectionform .installsubmit input,a.installlink',
                                overlay: 80});
        $(document).keypress(function(e){if (e.keyCode == 27) $('#installdialog').jqmHide();}) // esc
}
/*# AVOID COLLISIONS #*/
})(jQuery);
/*# AVOID COLLISIONS #*/

function confirmExpInstall(div) {
    $(div).removeClass('exp-loggedout');
    $(div).addClass('exp-confirmed');
    var bt = $(div).find('.install-button a');

    var href = $(bt).attr('href');
    if (href && href.match(/(policy|\.xml|\.xpi|\.jar)/)) {
          if (href.match(/(collection_id|\?)/)) {
              href += '&confirmed';
          } else {
              href += '?confirmed';
          }
        $(bt).attr('href', href);
    }

    var tmp = $(bt).attr('engineURL');
    if (tmp && tmp.match(/\.xml$/)) {
          if (tmp.match(/(collection_id|\?)/)) {
              tmp += "&confirmed";
          } else {
              tmp += "?confirmed";
          }
        $(bt).attr('engineURL', tmp);
    }

    $(bt).removeAttr('frozen');
}

function unconfirmExpInstall(div) {
    $(div).removeClass('exp-confirmed');
    $(div).addClass('exp-loggedout');
    var bt = $(div).find('.install-button a');

    var href = $(bt).attr('href');
    if (href) {
        href = href.replace(/\?confirmed/, '').replace(/&confirmed/,'');
        $(bt).attr('href', href);
    }
    var tmp = $(bt).attr('engineURL');
    if (tmp) {
        tmp = tmp.replace(/\?confirmed/, '').replace(/&confirmed/,'');
        $(bt).attr('engineURL', tmp);
    }

    $(bt).attr('frozen', 'true');
}

$(document).ready(function() {
    $('.exp-confirm-install input').each(function () {
	var div = $(this).parents('.exp-loggedout, .exp-confirmed');
	if (this.checked)
	    confirmExpInstall(div);
	else
	    unconfirmExpInstall(div);
    });
    $('.exp-confirm-install input').change(function (e) {
	var div = $(this).parents('.exp-loggedout, .exp-confirmed');
	if (this.checked) {
	    confirmExpInstall(div);
	} else {
	    unconfirmExpInstall(div);
	}
    });
});
function initExpConfirm(versionId) {
    var outer = $('#install-'+ versionId);
    $(outer).find('.exp-confirm-install').show();
    $(outer).find('.exp-loggedout .install-button').show();
}

/**
 * jQuery rollover reveal widget
 * lorchard@mozilla.com
 *
 * Example markup:
 *      <div id="foo">
 *          <a href="#" class="activator">Hover me</a>
 *          <div class="to-reveal">This content will appear</div>
 *      </div>
 *      <script>
 *          $('#foo').rolloverReveal({
 *              reveal_delay: 1000, dismiss_delay: 2000
 *          })
 *      </script>
 *
 * Whenever the activator element is hovered, the to-reveal content will appear
 * after a short delay.  If the mouse leaves the activator or revealed content,
 * the content will disappear after a short delay unless the mouse returns.
 *
 * Clicking on a link within the revealed content will dismiss the content.
 */
;if(jQuery) (function($){

    $.fn.rolloverReveal = function(options) {
        var $cls = arguments.callee.support;
        $.each(this, function() { new $cls(this, options) });
        return this;
    }

    $.fn.rolloverReveal.support = function(el, options) {
        this.init(el, options);
    };

    $.fn.rolloverReveal.support.prototype = function() {

        var option_defaults = {
            reveal_delay:  250,
            dismiss_delay: 1000,
            enable_rollover: true
        };

        return {
            // Delayed execution timers.
            timers: {},

            /** Set up the object instance and event handlers for this widget. */
            init: function(el, options) {
                var that = this;
                this.options = $.extend({}, option_defaults, options);

                this.root = $(el);
                this.to_reveal = this.root.find('.to-reveal');

                // Wire up the event handlers for significant elements of
                // the widget.
                this.root
                    .find('.activator')
                        .click(function()     { that.toggle(); return false; })
                        .mouseover(function() {
                            if (!that.options.enable_rollover) return;
                            that.schedule('reveal'); that.cancel('dismiss');
                        })
                        .mouseout(function()  {
                            if (!that.options.enable_rollover) return;
                            that.cancel('reveal'); that.schedule('dismiss');
                        })
                    .end()
                    .find('.to-reveal')
                        .mouseover(function() { that.cancel('dismiss'); })
                        .mouseout(function()  { that.schedule('dismiss'); })
                    .end()
                    .find('.to-reveal a')
                        .click(function()     { that.dismiss(); return true; })
                        .mouseover(function() { that.cancel('dismiss'); })
                        .mouseout(function()  { that.schedule('dismiss'); })
                    .end();
            },

            /** Reveal the hidden content */
            reveal: function() {
                this.to_reveal.show().addClass('revealed');
            },

            /** Determine whether the hidden content is revealed */
            revealStatus: function() {
                return this.to_reveal.hasClass('revealed');
            },

            /** Dismiss the hidden content */
            dismiss: function() {
                this.to_reveal.hide().removeClass('revealed');
            },

            /** Determine whether the hidden content is hidden */
            dismissStatus: function() {
                return !this.to_reveal.hasClass('revealed');
            },

            /** Toggle the hide/show of the content */
            toggle: function() {
                return (this.revealStatus()) ?
                    this.dismiss() : this.reveal();
            },

            /** Schedule delayed execution of the given action. */
            schedule: function(action) {
                var that = this;
                // Skip if the action is already in effect.
                if (this[action+'Status']()) return;
                // De-bounce any existing running timer.
                this.cancel(action);
                // Schedule a call to the given action.
                this.timers[action] = setTimeout(function() {
                    that[action]();
                }, this.options[action + '_delay']);
            },

            /** Cancel delayed execution of the given action. */
            cancel: function(action) {
                if (this.timers[action])
                    clearTimeout(this.timers[action]);
            },

            EOF:null
        };
    }();

})(jQuery);

/**
 * jQuery utility for PHP's nl2br equivalent in JavaScript,
 * along with the inverse method br2nl
 * fwenzel@mozilla.com
 */
;if(jQuery) (function($){
    $.nl2br = function(input) {
        return input.replace(/\n/g, '<br/>');
    }
    $.br2nl = function(input) {
        return input.replace(RegExp('<br\s*/?>', 'g'), "\n");
    }
})(jQuery);

/**
 * jQuery plugin to delay execution of a callback by x milliseconds
 * fwenzel@mozilla.com
 */
;if(jQuery) (function($){
    $.fn.delay = function(msec, callback) {
        return this.each(function() {
            $(this).animate({opacity: 1.0}, msec, callback);
        });
    }
})(jQuery);

/**
 * jQuery containing some simple UI extensions
 * fwenzel@mozilla.com
 */
;if(jQuery) (function($){
    /**
     * fade out, then remove an element
     */
    $.fn.fadeRemove = function() {
        return this.each(function() {
            $(this).fadeOut('normal', function(){
                $(this).remove();
            });
        });
    }
})(jQuery);


// make a call to Urchin, for tracking download button clicks
function urchinDownloadTrackingEvent(path_to_download) {
    urchinTracker(path_to_download); // actual
//    alert(path_to_download);  // debug
}

// attach a mousedown handler for Urchin, see urchinDownloadTrackingEvent()
function installButtonAttachUrchin(button) {
    // don't attach urchin to buttons that just link to a EULA page
    if($(button).attr('isEULAPageLink')) return false;

    $(button).mousedown(function (e) {
        if ($(this).attr('frozen') == 'true') return false;
        urchinDownloadTrackingEvent($(this).attr('href'));
    });
}

// attach javascript install methods to an install button
// e.g. install(), addEngine()
function installButtonAttachInstallMethod(button) {

    var method = $(button).attr('jsInstallMethod');

    $(button).click(function (e) {
        if ($(this).attr('frozen') == 'true') return false;

        if (method == 'browser_app_addon_install')
            return install(e, $(this).attr('addonName'), $(this).attr('addonIcon'), $(this).attr('addonHash'));
        else if (method == 'search_engine_install')
            return addEngine($(this).attr('engineURL'));
    });
}

$(document).ready(function() {
    $('p.install-button a').each(function () {
        installButtonAttachUrchin(this);
        installButtonAttachInstallMethod(this);
    });
});

/**
 * bandwagon: fire a custom refresh event for bandwagon extension
 * @return void
 */
function bandwagonRefreshEvent() {
    var bandwagonSubscriptionsRefreshEvent = document.createEvent("Events");
    bandwagonSubscriptionsRefreshEvent.initEvent("bandwagonRefresh", true, false);
    document.dispatchEvent(bandwagonSubscriptionsRefreshEvent);
}

/** Collections add page **/
var collections_add = {
    /**
     * initialize collections add page
     */
    init: function(options) {
        this.options = options;

        $('#addonname').autocomplete(this.options.lookup_url, {
            minChars: 2,
            max: 0,
            dataType: 'json',
            parse: function(data) {
                var rows = new Array();
                for (var i=0; i<data.length; i++){
                    rows[i] = {
                        data: data[i],
                        value: '',
                        result: data[i].name+' ['+data[i].id+']'
                    };
                }
                return rows;
            },
            formatItem: function(row) { return '<img src="' + row.iconpath + '"/>&nbsp;' + row.html_name; },
            extraParams: { timestamp: null }
        });
        $('#addonname').result(function(event,data) {
            collections_add.addAddon(data);
            $(this).val('');
        });
        $('#firstaddons').show();
    },

    addAddon: function(data) {
        var newid = 'addon-'+data.id;
        if ($('#'+newid).size() > 0) {
            $('#'+newid)
                .attr('checked', true)
                .parent()
                    .addClass('flash')
                    .delay(3000, function() {
                        $(this).removeClass('flash');
                    });
            return true;
        }
        $('#selectedaddons').children('ul:first').append(
            '<li>'+
            '<input type="checkbox" name="addons[]" value="'+data.id+'" id="'+newid+'" checked="true"/>'+
            '<label for="'+newid+'"><img src="'+data.iconpath+'"/>&nbsp;'+data.html_name+'</label></li>'
        );
        $('#selectedaddons').show();
        return true;
    }
}

/** Collections edit page **/
var collections_edit = {
    /**
     * initialize collections edit page
     */
    init: function() {
        $('#coll-edit .jsonly').show();

        this.tabs_init();
        this.nickname_init();
        this.icon_init();
        this.user_init();
        this.addon_init();
        this.addon_comment_init();

        // delete button
        $('#delete-confirm').hide();
        $('#delete-coll').click(function() {
            $(this).hide();
            $('#delete-confirm').show();
        });
        $('#delete-coll-noscript').change(function() {
            if ($(this).is(':checked')) {
                $('#delete-warning').fadeIn();
                $('#submitbutton').val(collections_edit_submit_deletecollection);
            } else {
                $('#delete-warning').fadeOut();
                $('#submitbutton').val(collections_edit_submit);
            }
        });
        $('#saved_success').delay(10000, function(){$(this).fadeRemove()}); // "saved" success message
    },

    /**
     * initialize tabbed layout
     */
    tabs_init: function() {
        $("#coll-edit > ul")
            .tabs()
            .bind('tabsselect', function(e, ui, tab) {
                window.location.hash = '#'+$(tab.panel).attr('id');
            });
    },

    /**
     * initialize nickname check UI
     */
    nickname_init: function() {
        this.nickname_old = $('#CollectionNickname').val(); // save original nickname
        $('#nick-avail').click(this.nickname_check);
        $('#CollectionNickname')
            .blur(this.nickname_check)
            .keypress(function(e) {
                if (e.which == KEYCODE_ENTER) {
                    collections_edit.nickname_check();
                    e.preventDefault();
                }
            })
            .keyup(function(e) {
                if (e.which!=KEYCODE_ENTER) {
                    collections_edit.nickname_checked = false;
                    collections_edit.nickname_showButton();
                }
            });
    },
    /**
     * check if a nickname is already taken
     */
    nickname_check: function() {
        if (collections_edit.nickname_checked)
            return true;
        else
            collections_edit.nickname_checked = true;

        var name = $.trim($('#CollectionNickname').val());
        $('#CollectionNickname').val(name);
        if (name == collections_edit.nickname_old) { // nickname unchanged
            collections_edit.nickname_showLabel('available');
            return true;
        }
        if (name.length > 0) {
            $('#CollectionNickname').siblings('img').show();
            $.getJSON(jsonURL+'/nickname', {nickname: name}, function(data) {
                $('#CollectionNickname').siblings('img').hide();
                $('#nick-avail').hide();
                if (data.nickname) $('#CollectionNickname').val(data.nickname);
                if (data.error) {
                    var msg = $('<span class="error">'+data.error_message+'</span>');
                    msg.insertAfter($('#CollectionNickname'));
                    msg.delay(3000, function() {
                        $(this).fadeRemove();
                        collections_edit.nickname_checked = false;
                        collections_edit.nickname_showButton();
                    });
                    $('#CollectionNickname').select();
                } else {
                    collections_edit.nickname_showLabel(data.taken ? 'taken' : 'available');
                }
            });
        } else {
            $('#nick-avail')
                .hide()
                .siblings('span').hide();
        }
        return true;
    },
    /**
     * show nickname check result
     */
    nickname_showLabel: function(classname) {
        $('#nick-avail')
            .hide()
            .siblings('span').hide()
            .filter('.'+classname).show();
    },
    /**
     * show/hide button to initiate nickname check
     */
    nickname_showButton: function() {
        $('#nick-avail').siblings('span').hide();
        if ($('#CollectionNickname').val().length > 0) {
            $('#nick-avail').show();
        } else {
            $('#nick-avail').hide();
        }
    },

    /**
     * Initialize icon upload UI
     */
    icon_init: function() {
        $('#icon_replace').click(this.icon_replace);
        $('#icon_remove').click(this.icon_delete);
        $('#icon>a.cancel').click(this.icon_reset);
        this.icon_reset();
    },
    /**
     * initialize/reset icon upload UI
     */
    icon_reset: function() {
        var icondiv = $('#icon');
        if (icondiv.children('img').length == 0) return false;
        icondiv.children('input:file,.toberemoved,.cancel').hide();
        icondiv.children('.replaceremove').show();
        $('#IconDelete').remove();
        return false;
    },
    /**
     * remove icon
     */
    icon_delete: function() {
        var icondiv = $('#icon');
        if (icondiv.children('img').length == 0) return false;
        icondiv.children('input:file,.replaceremove').hide();
        icondiv.children('.cancel,.toberemoved').show();
        if ($('#IconDelete').length == 0)
            icondiv.append('<input type="hidden" id="IconDelete" name="data[Icon][delete]" value="1"/>');
        return false;
    },
    /**
     * replace an existing icon
     */
    icon_replace: function() {
        var icondiv = $('#icon');
        if (icondiv.children('img').length == 0) return false;
        icondiv.children('input:file,.cancel').show();
        icondiv.children('.replaceremove,.toberemoved').hide();
        return false;
    },

    /**
     * initialize addon-related UI
     */
    addon_init: function() {
        $('#addonname').autocomplete(collURL+'/addonLookup', {
            minChars: 2,
            max: 0,
            dataType: 'json',
            parse: function(data) {
                var rows = new Array();
                for (var i=0; i<data.length; i++){
                    rows[i] = {
                        data: data[i],
                        value: '',
                        result: data[i].name+' ['+data[i].id+']'
                    };
                }
                return rows;
            },
            formatItem: function(row) { return '<img src="' + row.iconpath + '"/>&nbsp;' + row.html_name; },
            extraParams: { timestamp: null }
        });
        $('#addonname').keypress(function(e){
            if (e.which == KEYCODE_ENTER) {
                $('#addon-add').click();
                return false;
            }
            return true;
        });

        $('#addon-add').click(function() {
            var name = /\[(\d+)\]/.exec($('#addonname').val());
            if (undefined == name || name.length != 2) return false;
            collections_edit.addon_add(name[1]);
            return true;
        }); // button
    },
    /**
     * show an add-on in the UI
     */
    addon_show: function(id, name, iconurl, date, publisher, comment, editable, ontop, addon_version) {
        var div = $('<div class="coll-addon" id="addon-'+id+'"/>');
        var tpl = $('#addon-new'); // template

        var idfield = tpl.children('input:hidden').clone();
        idfield.val(id);
        div.append(idfield);

        var addon_name_value = tpl.find('.addon_name_value').clone(true);
        addon_name_value.text(name);
        div.append(addon_name_value);

        var addon_version_value = tpl.find('.addon_version_value').clone(true);
        addon_version_value.text(addon_version);
        div.append(addon_version_value);

        var p = tpl.children('p').clone();
        p.find('img').attr('src', iconurl);
        p.find('.name').text(name + (addon_version ? "-" + addon_version : ""));
        p.find('.added').html(sprintf(p.find('.added').text(), date, publisher));
        div.append(p);
        if (editable) div.append(tpl.children('.removeaddon').clone(true));
        if (!ontop) {
            $('#currentaddons').append(div);
        } else {
            $('#currentaddons #addon-new').after(div);
        }
        collections_edit.addon_comment_show(id, comment, editable);
        if (editable) {
            div.append(tpl.children('.version_sep').clone(true));
            div.append(tpl.children('.set_version').clone(true));
        }
        $('#currentaddons').show();
    },
    /**
     * add a new add-on to this collection
     */
    addon_add: function(id) {
        $.post(jsonURL+'/addon/add', {
            sessionCheck: $('#collections>div.hsession>input[name=sessionCheck]').val(),
            collection_id: collection_id,
            addon_id: id
            }, function(data) {
                if (data.error) {
                    var msg = $('<span class="error">'+data.error_message+'</span>');
                    $('#addon-add').after(msg);
                    msg.delay(2000, function(){ $(this).fadeRemove(); });
                    $('#addonname').select();
                } else {
                    collections_edit.addon_show(data.id, data.name, data.iconURL,
                        data.date, data.publisher, '', 1, 1, '');
                    $('#addonname').val('');
                }
                return true;
            }, 'json');
    },
    /**
     * remove an add-on from the collection
     */
    addon_delete: function() {
        var idstring = $(this).parent().attr('id');
        var id = idstring.substr(idstring.lastIndexOf('-')+1);
        $.post(jsonURL+'/addon/del', {
            sessionCheck: $('#collections>div.hsession>input[name=sessionCheck]').val(),
            collection_id: collection_id,
            addon_id: id
            }, function(data) {
                if (data.error) {
                    alert(data.error_message);
                } else {
                    $('#addon-'+data.id).fadeRemove();
                    if ($('#currentaddons').children('div:visible').length==0) $('#currentaddons').hide();
                }
                return true;
            }, 'json');
        return false;
    },

    /**
     * initialize comment-related UI
     */
    addon_comment_init: function() {
        var tpl = $('#addon-new');
        tpl.children('a.removeaddon').click(this.addon_delete);
        tpl.children('a.addlink').click(this.addon_comment_add);
        tpl.children('a.set_version').click(this.addon_set_version);
        tpl.find('a.editlink').click(this.addon_comment_edit);
        tpl.find('a.deletelink').click(function() {
            var container = $(this).parent().parent();
            var idstring = container.attr('id');
            var addonid = idstring.substr(idstring.lastIndexOf('-')+1);
            collections_edit.addon_comment_save(addonid, '');
            return false;
        });
        tpl.find('.editbox>input:button').click(function() { // save
            var comment = $(this).siblings('textarea').val();
            var container = $(this).parent().parent();
            var idstring = container.attr('id');
            var addonid = idstring.substr(idstring.lastIndexOf('-')+1);
            collections_edit.addon_comment_save(addonid, comment);
            return false;
        });

        tpl.find('.version_box>input:button').click(function() {
            var addon_version = $(this).parent().children('input:text').attr('value');
            var container = $(this).parent().parent();
            var idstring = container.attr('id');
            var addonid = idstring.substr(idstring.lastIndexOf('-')+1);

            $.post(jsonURL+'/addon/save_addon_version', {
                sessionCheck: $('#collections>div.hsession>input[name=sessionCheck]').val(),
                collection_id: collection_id,
                addon_id: addonid,
                addon_version: addon_version
                }, function(data) {
                    var addonid = /addon_id=(\d+)/.exec(this.data)[1];
                    var container = $('#addon-'+addonid);
                    if (data.error) {
                        var msg = $('<div class="error">'+data.error_message+'</div>');
                        container.append(msg);
                        msg.delay(2000, function(){ $(this).fadeRemove(); });
                    } else {
                        var name = container.children('.addon_name_value').text();
                        container.children('.addon_version_value').text(addon_version);
                        container.find('p>.name').text(name + (addon_version ? "-" + addon_version : ""));

                        container.children('.version_box').remove();
                        var tpl = $('#addon-new');
                        container.append(tpl.children('.set_version').clone(true));
                    }
                    return true;
                }, 'json');

            return false;
        });
    },
    /**
     * show a publisher comment in the UI
     */
    addon_comment_show: function(addonid, comment, editable) {
        var div = $('#addon-'+addonid);
        var tpl = $('#addon-new');
        if (comment.length > 0) {
            div.append(tpl.children('blockquote').clone().html($.nl2br(comment)));
            if (editable) div.append(tpl.children('.editdelete').clone(true));
        } else {
            if (editable) div.append(tpl.children('.addlink').clone(true));
        }
    },
    /**
     * add a new publisher comment in the UI
     */
    addon_comment_add: function() {
        var editbox = $('#addon-new>.editbox').clone(true);
        $(this).parent().append(editbox);
        $(this).remove();
        editbox.children('textarea').focus();
        return false;
    },
    addon_set_version: function() {
        var addon_version = $(this).parent().find('.addon_version_value').text();
        var version_box = $('#addon-new>.version_box').clone(true);
        $(this).parent().append(version_box);
        $(this).remove();
        version_box.children('input:text').attr('value', addon_version);
        version_box.children('input:text').focus();
        return false;
    },
    /**
     * edit an existing publisher comment in the UI
     */
    addon_comment_edit: function() {
        var comment = $(this).parent().siblings('blockquote');
        var editbox = $('#addon-new>.editbox').clone(true);
        editbox.children('textarea').html($.br2nl(comment.html()));
        $(this).parent().parent().append(editbox);
        $(this).parent().remove();
        comment.remove();
        editbox.children('textarea').select();
        return false;
    },
    /**
     * save/remove a publisher comment
     */
    addon_comment_save: function(addonid, comment) {
        $.post(jsonURL+'/addon/savecomment', {
            sessionCheck: $('#collections>div.hsession>input[name=sessionCheck]').val(),
            collection_id: collection_id,
            addon_id: addonid,
            comment: comment
            }, function(data) {
                var addonid = /addon_id=(\d+)/.exec(this.data)[1];
                var container = $('#addon-'+addonid);
                if (data.error) {
                    var msg = $('<div class="error">'+data.error_message+'</div>');
                    container.append(msg);
                    msg.delay(2000, function(){ $(this).fadeRemove(); });
                } else {
                    container.children('blockquote,.addlink,.editdelete,.editbox').remove();// wipe old UI
                    collections_edit.addon_comment_show(addonid, data.comment, true); // show comment
                }
                return true;
            }, 'json');
    },

    /**
     * initialize user-related UI
     */
    user_init: function() {
        $('#publishers>input:text,#managers>input:text').keypress(function(e) {
            if (e.which == KEYCODE_ENTER) {
                $(this).siblings('input:button').click();
                return false;
            }
            return true;
        });
        $('#publishers>input:button,#managers>input:button').click(collections_edit.user_check);
    },
    /**
     * add a user to this collection
     */
    user_check: function() {
        var role = $(this).parent().attr('id');
        var email = $('#'+role+'>:text').val();
        if (email.length==0) return;

        $(this).siblings('img').show();
        $.post(jsonURL+'/user/add', {
            sessionCheck: $('#collections>div.hsession>input[name=sessionCheck]').val(),
            collection_id: collection_id,
            role: role,
            email: email
            }, function(data) {
                var role = /role=(\w+)/.exec(this.data)[1];
                $('#'+role+'>img').hide();
                if (data.error) {
                    var msg = $('<li class="error">'+data.error_message+'</li>');
                    $('#'+role+'>ul').append(msg);
                    msg.delay(2000, function(){ $(this).fadeRemove(); });
                    $('#'+role+'>input:text').select();
                } else {
                    collections_edit.user_add(role, data.id, data.email);
                    $('#'+role+'>input:text').val('').focus();
                }
                return true;
            }, 'json');
    },
    /**
     * show a user in this collection's user list
     */
    user_add: function(role, id, email) {
        $('#'+role).siblings('input:radio[value=0]').attr('checked', 'checked');
        $('#'+role+'>ul').append('<li>'
            +'<input type="hidden" name="'+role+'[]" value="'+id+'"/>'
            +email+' <a href="#" onclick="collections_edit.user_remove(this);return false;">Remove</a>'
            +'</li>');
    },
    /**
     * remove a user from this collection
     */
    user_remove: function(link) {
        var id = $(link).siblings('input:hidden').val();
        $.post(jsonURL+'/user/del', {
            sessionCheck: $('#collections>div.hsession>input[name=sessionCheck]').val(),
            role: $(link).parent().parent().parent().attr('id'),
            collection_id: collection_id,
            user_id: id
            }, function(data) {
                var role = /role=(\w+)/.exec(this.data)[1];
                if (data.error) {
                    var msg = $('<li class="error">'+data.error_message+'</li>');
                    $('#'+role+'>ul').append(msg);
                    msg.delay(2000, function(){ $(this).fadeRemove(); });
                    $('#'+role+'>input:text').select();
                } else {
                    $('#'+role+' input:hidden[value='+data.id+']').parent().fadeRemove();
                }
                return true;
            }, 'json');
    }
}

/** Addons Display page */
var addons_display = {
    /**
     * initialization
     */
    init: function(options) {
        this.options = options;
        $('#form-review .stars').rating({readOnly:(!options.loggedIn)});
        $('.rollover-reveal').rolloverReveal({ enable_rollover: false });

        $('#coll_publish button').click(this.coll_publish);
    },

    /**
     * publish an add-on to a collection
     */
    coll_publish: function() {
        var coll_uuid = $('#coll_publish option:selected').val();
        if (!coll_uuid)
            return false;
        else if (coll_uuid == 'new')
            return true;
        var addon_id = $('#coll_publish input[name=\'data[addon_id]\']').val();

        $.post(addons_display.options.jsonURL+'/addon/add', {
            sessionCheck: $('#coll_publish div.hsession>input[name=sessionCheck]').val(),
            collection_uuid: coll_uuid,
            addon_id: addon_id
            }, function(data) {
                if (data.error) {
                    var msg = $('<div class="error">'+data.error_message+'</div>');
                    $('#coll_publish>button').after(msg);
                    msg.delay(3000, function(){ $(this).fadeRemove(); });
                } else {
                    var coll_uuid = $('#coll_publish option:selected');
                    var msg = $('<div>'
                            +sprintf(addons_display_collection_publish_success,
                                data.name, '<a href="'
                                +addons_display.options.collViewURL+coll_uuid.val()
                                +'">'+coll_uuid.text()+'</a>')
                            +'</div>');
                    $('#coll_publish button').after(msg);
                    msg.delay(10000, function(){ $(this).fadeRemove(); });
                    coll_uuid.remove();
                }
            }, 'json');
        return false;
    }
}

/** Addons Version History Page */
var addons_history = {
    latestVersionID: null, // latest compatible version ID
    vc: null, // version compare object
    versions: new Array(),

    init: function() {
        this.vc = new VersionCompare();
    },

    /**
     * Add a version (compatible with Firefox) to the list of versions
     */
    addVersion: function(versionid, fromVer, toVer) {
        this.versions[this.versions.length] = [versionid, fromVer, toVer];
    },

    /**
     * Find the latest known compatible version for the current app
     */
    findLatestCompatibleVersion: function() {
        var success = false;
        for (var i = 0; i < this.versions.length; i++) {
            var v = this.versions[i];
            if (v < this.latestVersionID) continue;

            if (this.vc.compareVersions(gBrowserVersion, v[1]) > 0
                && this.vc.compareVersions(gBrowserVersion, v[2]) < 0) {
                var installbuttons = $('#install-'+v[0]).find('.platform-ALL,.platform-'+getPlatformName(gPlatform));
                if (installbuttons.size() > 0) {
                    this.latestVersionID = v[0];
                    success = true;
                }
            }
        }
        return success;
    },

    /**
     * Create an element with the most recent compatible version of an addon
     * @returns bool success
     */
    createLatestVersionElement: function(get_latest_version_text, app) {
        var found = this.findLatestCompatibleVersion();
        if (!found) return false;

        var container = $('#latest-version-container');
        var newid = 'install-latest-'+ this.latestVersionID; // new unique ID

        var installButtonOrig = $('#install-'+this.latestVersionID);

        // make a version info box
        var wrapper = $('<div></div>');
        var titletext = "<h3>" + sprintf(get_latest_version_text, app, gBrowserVersion) + "</h3>"
        var verinfo = installButtonOrig.siblings('h3').clone().html();
        var installButton =
            installButtonOrig.clone()
            .attr('id', newid);
        wrapper
            .attr('class', installButtonOrig.parent().attr('class'))
            .append(titletext)
            .append(installButton)
            .append(verinfo);
        container
            .append(wrapper)
            .attr('id', 'latest-version');

        fixPlatformLinks('latest-'+this.latestVersionID, ''); // show only the relevant platform
        container.show();
        return true;
    }
}

/* Remove "Go" buttons from <form class="go" */
$(document).ready(function(){
    var f = $('form.go');
    f.find('select').change(function(){ this.form.submit(); });
    f.find('button').hide();
});
