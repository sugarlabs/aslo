/**
 * Round a number to the specified number of decimal places and format
 * using localized decimal and thousands separator.
 *
 * This is meant to be a client-side version of PHP's number_format()
 * http://www.php.net/manual/en/function.number-format.php
 */
function number_format(num, decimal_places, decimal_char, comma_char) {
    var result = '';

    // set some reasonable defaults for all formating arguments
    if (typeof decimal_places != 'number') {
        decimal_places = 0;
    }

    if (typeof decimal_char == 'undefined') {
        if (typeof localized == 'object' && 'decimal_point' in localized) {
            decimal_char = localized['decimal_point'];
        } else {
            decimal_char = '.';
        }
    }

    if (typeof comma_char == 'undefined') {
        if (typeof localized == 'object' && 'thousands_sep' in localized) {
            comma_char = localized['thousands_sep'];
        } else {
            comma_char = '';
        }
    }

    // round and stringify
    var k = Math.pow(10, decimal_places);
    var num_str = '' + (Math.round(k*num)/k);

    // split into whole number and fraction parts (with some 0 padding)
    var parts = num_str.split('.', 2);
    var whole = parts[0],
     fraction = (parts.length > 1 ? parts[1] : '') + (new Array(decimal_places+1)).join('0');

    // whole number with thousands separated
    while (whole.length > 3 && comma_char.length) {
        result = comma_char + '' + whole.substr(whole.length-3, whole.length-1) + result;
        whole = whole.substr(0, whole.length - 3);
    }
    result = whole + '' + result;

    // decimal and fraction
    if (decimal_places > 0) {
        result += '' + decimal_char + '' + fraction.substr(0, decimal_places);
    }

    return result;
}

