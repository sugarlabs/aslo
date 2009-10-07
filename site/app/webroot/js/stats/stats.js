/**
 * Redirect to new add-on
 */
function changeAddon(item) {
    window.location = statsURL + 'addon/' + item.value;
}

/**
 * sprintf() implementation for Javascript
 * http://jan.moesen.nu/code/javascript/sprintf-and-printf-in-javascript/
 */
function sprintf()
{
        if (!arguments || arguments.length < 1 || !RegExp)
        {
                return;
        }
        var str = arguments[0];
        str = str.replace('&#37;', '%');
        var re = /([^%]*)%('.|0|\x20)?(-)?(\d+)?(\.\d+)?(%|b|c|d|u|f|o|s|x|X)(.*)/;
        var a = b = [], numSubstitutions = 0, numMatches = 0;
        while (a = re.exec(str))
        {
                var leftpart = a[1], pPad = a[2], pJustify = a[3], pMinLength = a[4];
                var pPrecision = a[5], pType = a[6], rightPart = a[7];
                
                //alert(a + '\n' + [a[0], leftpart, pPad, pJustify, pMinLength, pPrecision);

                numMatches++;
                if (pType == '%')
                {
                        subst = '%';
                }
                else
                {
                        numSubstitutions++;
                        if (numSubstitutions >= arguments.length)
                        {
                                alert('Error! Not enough function arguments (' + (arguments.length - 1) + ', excluding the string)\nfor the number of substitution parameters in string (' + numSubstitutions + ' so far).');
                        }
                        var param = arguments[numSubstitutions];
                        var pad = '';
                               if (pPad && pPad.substr(0,1) == "'") pad = leftpart.substr(1,1);
                          else if (pPad) pad = pPad;
                        var justifyRight = true;
                               if (pJustify && pJustify === "-") justifyRight = false;
                        var minLength = -1;
                               if (pMinLength) minLength = parseInt(pMinLength);
                        var precision = -1;
                               if (pPrecision && pType == 'f') precision = parseInt(pPrecision.substring(1));
                        var subst = param;
                               if (pType == 'b') subst = parseInt(param).toString(2);
                          else if (pType == 'c') subst = String.fromCharCode(parseInt(param));
                          else if (pType == 'd') subst = parseInt(param) ? parseInt(param) : 0;
                          else if (pType == 'u') subst = Math.abs(param);
                          else if (pType == 'f') subst = (precision > -1) ? Math.round(parseFloat(param) * Math.pow(10, precision)) / Math.pow(10, precision): parseFloat(param);
                          else if (pType == 'o') subst = parseInt(param).toString(8);
                          else if (pType == 's') subst = param;
                          else if (pType == 'x') subst = ('' + parseInt(param).toString(16)).toLowerCase();
                          else if (pType == 'X') subst = ('' + parseInt(param).toString(16)).toUpperCase();
                }
                str = leftpart + subst + rightPart;
        }
        return str;
}

// format a value into a localized number (if possible)
function numberFormat(v) {
    if (typeof number_format == 'function') {
        return number_format(v);
    } else {
        return '' + Math.round(v);
    }
}

// format a value into US Dollars
function dollarFormat(v) {
    if (typeof number_format == 'function') {
        return '$' + number_format(v, 2);
    } else {
        return '$' + (Math.round(v*100)/100);
    }
}

// format an addon id and name into a stat dashboard link
function addonLinkFormat(addonId, addonName) {
    return $('<a></a>').attr('href', statsURL + 'addon/' + addonId).text(addonName);
}

// parse raw field names from csv comments
function parseRawFields(data) {
    var fields = [];
    if (data != '') {
        var lineCount = (data.split("\n").length - 9);
        
        if (lineCount > 1) {
            var start = data.indexOf('Fields: [');
            var end = data.indexOf(']', start);
            var fieldsString = data.substring(start + 9, end);
            fields = fieldsString.split(';');
        }
    }
    return fields;
}
