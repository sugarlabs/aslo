function showMenu() {
    document.getElementById('showMenuItem').style.display = 'none';
    document.getElementById('hideMenuItem').style.display = '';
    $("#editorMenu").slideDown('medium');
}
function hideMenu() {
    $("#editorMenu").slideUp('medium');
    document.getElementById('showMenuItem').style.display = '';
    document.getElementById('hideMenuItem').style.display = 'none';
}
/*************************************************
*               editors/queue                  *
*************************************************/
var editors_queue = {
    init: function() {
        $('#filterHeader').click(this.toggleFilters);

        $('input#FilterAddonOrAuthor').autocomplete(addonAutocompleteUrl, {
            minChars: 4,
            max: 40,
            formatItem: function(row) {
                // encode results using a temporary jquery element
                return '<b>' + $('<div/>').text(row[0]).html() + '</b>';
            },
            formatResult: function(row) { return row[0]; },
        });

        $('select#FilterApplication').change(this.populateAppVersions);

        if (! $('select#FilterApplication').val()) {
            $('select#FilterMaxVersion').attr('disabled', 'disabled');
        }
    },

    /**
     * Expand/collapse the filter box
     */
    toggleFilters: function() {
        var div = document.getElementById('filterTable');
        if (div.style.display == 'none') {
            div.style.display = '';
        }
        else {
            div.style.display = 'none';
        }
    },

    /**
     * Fill the maxVersion select dropdown based on selected application
     */
    populateAppVersions: function() {
        var appId = $('select#FilterApplication').val();
        var selectNode = $('select#FilterMaxVersion');
        if (appId) {
            selectNode.attr('disabled', '');
            selectNode.load(appversionLookupUrl+appId);
        } else {
            selectNode.html('');
            selectNode.attr('disabled', 'disabled');
        }
    },
}

/*************************************************
*               editors/review                 *
*************************************************/
//Array of possible actions
var actions = ['public', 'sandbox', 'info', 'superreview'];

//Simulate radio button group for action icons
function selectAction(action) {
    var actionField = document.getElementById('actionField');
    actionField.value = action;
    
    //Select action and deselect other actions
    for (var i = 0; i < actions.length; i++) {
        if (actions[i] == action) {
            changeIcon(actions[i], 'color');
            document.getElementById('details-' + actions[i]).style.display = '';
        }
        else {
            changeIcon(actions[i], 'bw');
            document.getElementById('details-' + actions[i]).style.display = 'none';
        }
    }
    
    // when rejecting, pre-select notification box
    $('#subscribe input').attr('checked', (action=='sandbox'));
    // no canned responses/app/os for info request
    if (action=='info')
        $('#canned,#testing').hide();
    else
        $('#canned,#testing').show();
    
    $('#subform').show('medium');
}

//Turn an icon colored/bw
function changeIcon(action, colorbw) {
    var icon = document.getElementById(action + 'Icon');
    var span = document.getElementById(action);
   
    icon.src = icon.src.substring(0, icon.src.lastIndexOf('/')+1) + action + '-' + colorbw + '.png';
    span.className = 'action_'+colorbw;
}

//Get number of selected files
function selectedFileCount() {
    var filesSelected = 0;
    var elements = document.getElementsByTagName('input');
    for (var i = 0; i < elements.length; i++) {
        if (elements[i].className == 'fileCheckbox' && elements[i].disabled == false) {
            if (elements[i].checked == true) {
                filesSelected++;
            }
        }
    }
    
    return filesSelected;
}

//Show notice if more than one file selected
function selectedFile() {
    var filesSelected = selectedFileCount();
    
    if (filesSelected > 1) {
        document.getElementById('multipleNotice').style.display = '';
    }
    else {
        document.getElementById('multipleNotice').style.display = 'none';
    }
}

//Validate review form
function validateReview(type) {
    //Make sure an action was selected
    var action = document.getElementById('actionField').value;
    if (action == '') {
        errors += '- ' + localized['action'] + '\n';
    }
    
    if (type == 'pending' && action!='info') {
        //Make sure at least one file is selected
        var filesSelected = selectedFileCount();
        
        if (filesSelected == 0) {
            alert(localized['files']);
            return false;
        }
    }
    
    var errors = '';
    
    //Make sure comments were entered
    if (document.getElementById('comments').value == '') {
        errors += '- ' + localized['comments'] + '\n';
    }
    if (type == 'pending' && action!='info') {
        //Make sure tested operating system was entered
        if (document.getElementById('ApprovalOs').value == '') {
            errors += '- ' + localized['os'] + '\n';
        }
        //Make sure tested application was entered
        if (document.getElementById('ApprovalApplications').value == '') {
            errors += '- ' + localized['applications'] + '\n';
        }
    }
    
    if (errors != '') {
        alert(localized['errors'] + '\n' + errors);
        return false;
    }
    else {
        return true;
    }
}

/*************************************************
*               editors/reviewlog                *
*************************************************/
//Show a review entry's comments
function showComments(id) {
    document.getElementById('reviewComment_' + id).style.display = '';
    document.getElementById('reviewShow_' + id).style.display = 'none';
    document.getElementById('reviewHide_' + id).style.display = '';
    document.getElementById('reviewEntry_' + id).className = 'reviewEntryActive';
}

//Hide a review entry's comments
function hideComments(id) {
    document.getElementById('reviewComment_' + id).style.display = 'none';
    document.getElementById('reviewShow_' + id).style.display = '';
    document.getElementById('reviewHide_' + id).style.display = 'none';
    document.getElementById('reviewEntry_' + id).className = '';
}

function clearInput(input) {
    if (input.value == 'YYYY-MM-DD') {
        input.value = '';
    }
}

/*************************************************
*               editors/featured                 *
*************************************************/


/*
    Creates new autocomplete object for whichever input that just recieved focus.
    Most likely done on focus to reduce # of objects instantiated on page load
*/
function prepAutocomplete(tagid) {
    $('#new-addon-id-' + tagid).autocomplete(autocompleteurl,
        {
            minChars:4,
            formatItem: function(row) { return '<b>' + row[0] + '</b><br><i>' + row[1] + '</i>'; },
            formatResult: function(row) { return row[2]; }
        });
    $('#new-addon-id-' + tagid).focus();
}

/*
    Parses input for addon id and name, then sends to server
*/
function addFeatureSubmit(tagid) {

    var addonid = document.getElementById('new-addon-id-' + tagid).value;

    addonname = addonid.substring(0, addonid.lastIndexOf('['));
    addonid = addonid.substring(addonid.lastIndexOf('[')+1, addonid.lastIndexOf(']'));
    
    if (addonid.length == 0) {
        editFeatureMessage(tagid, featureaddfailure, false);
        return false;
    }
    
    $.ajax({
        type: 'POST',
        url: featuredurl + '/add/ajax',
        data: $('#feature-add-form-'+tagid).serialize(),
        success : function() {
            $('#new-addon-id-' + tagid).attr('value', '');
            addNewFeatureRowBeforeElement($('#feature-add-tr-form-' + tagid), tagid, addonid, addonname);
        },
        error : function() {
            editFeatureMessage(tagid, featureaddfailure, false);
        }
    });
    
    return false;
}

/*
    After an addon is added to a featured list, it is added above the search box
*/
function addNewFeatureRowBeforeElement(sibling, tagid, addonid, addonname) {
    // Sure would be nice if we had a newer Prototype library :(

    var addonrow = document.createElement('tr');
    addonrow.setAttribute('id', 'feature-' + tagid + '-' + addonid);

    // First <td>
        var deletelink = document.createElement('a');
        deletelink.setAttribute('href', featuredurl + '/remove/' + tagid + '/' + addonid);
        deletelink.setAttribute('id', 'delete-' + tagid + '-' + addonid);
        deletelink.setAttribute('onclick', 'removeFeature(' + tagid + ',' + addonid + '); return false;');

        var deleteimage = document.createElement('img');
        deleteimage.setAttribute('src', imageurl + '/developers/delete.png');
        deleteimage.setAttribute('class', 'featureremove');
        deletelink.appendChild(deleteimage);

        var addonlink = document.createElement('a');
        addonlink.setAttribute('href', addonurl + '/' + addonid);
        addonlink.appendChild(document.createTextNode(addonname));

        var addontd1 = document.createElement('td');
        addontd1.appendChild(deletelink);
        addontd1.appendChild(addonlink);

    // Second <td>
        var addonform = document.createElement('form');
        addonform.setAttribute('id', 'feature-edit-form-' + tagid + '-' + addonid);
        addonform.setAttribute('onsubmit', 'editFeatureSubmit(' + tagid + ',' + addonid + '); return false;');
        addonform.setAttribute('action', featuredurl + '/edit');
        addonform.setAttribute('method', 'post');

        var addonforminputlocale = document.createElement('input');
        addonforminputlocale.setAttribute('name', 'data[AddonTag][feature_locales]');
        addonforminputlocale.setAttribute('id', 'edit-addon-locales-' + tagid + '-' + addonid);
        addonforminputlocale.setAttribute('size', '40');
        addonforminputlocale.setAttribute('type', 'text');
        addonform.appendChild(addonforminputlocale);

        var addonforminputsubmit = document.createElement('input');
        addonforminputsubmit.setAttribute('id', 'edit-feature-submit-' + tagid + '-' + addonid);
        addonforminputsubmit.setAttribute('value', featureeditsubmit);
        addonforminputsubmit.setAttribute('type', 'submit');
        addonforminputsubmit.setAttribute('value', featureeditsubmit);
        addonform.appendChild(addonforminputsubmit);

        var addonformfeaturemessage = document.createElement('span');
        addonformfeaturemessage.setAttribute('id', 'edit-feature-message-' + tagid + '-' + addonid);
        addonform.appendChild(addonformfeaturemessage);


        var addontd2 = document.createElement('td');
        addontd2.appendChild(addonform);

        addonrow.appendChild(addontd1);
        addonrow.appendChild(addontd2);

    sibling.before(addonrow);
    return true;
}


function editFeatureSubmit(tagid, addonid) {
    var locales = document.getElementById('edit-addon-locales-' + tagid + '-' + addonid).value;
    
    if (locales.match(/[^A-Za-z,-]/)) {
        editFeatureMessage(tagid, addedinvalidlocale, false);
        return false;
    }
    
    $.ajax({
        type: 'POST',
        url: featuredurl + '/edit/ajax',
        data: $('#feature-edit-form-'+tagid+'-'+addonid).serialize(),
        success : function(){
            editFeatureMessage(tagid, featureeditsuccess, true);
        },
        error : function(){
            editFeatureMessage(tagid, featureeditfailure, false);
        }
    });

    return false;
}

/*
    Shows a message when editing a featured addon, then hides after 5 seconds
*/
function editFeatureMessage(tagid, message, success) {
    var target = $('#edit-feature-message-' + tagid);
    if (success) {
        target.attr('class', 'success');
    } else {
        target.attr('class', 'failure');
    }
    target.html(message);

    var toclear = $('#edit-feature-message-' + tagid);

    setTimeout( function() {toclear.html('');} , 5000);
}


function removeFeature(tagid, addonid) {
    $.ajax({
        url: featuredurl + '/remove/ajax',
        type: 'POST',
        data: $('#feature-remove-form-'+tagid+'-'+addonid).serialize(),
        success: function(){
            $('#feature-' + tagid + '-' + addonid).fadeOut();
        },
        error : function(){
            editFeatureMessage(tagid, featureremovefailure, false);
        }
    });
    return false;
}

/*************************************************
*               editors/performance              *
*************************************************/
var editors_performance = {
    init: function() {
        $('.performanceHeader.collapsible').click(function() {
            var el = $(this).next();
            if(! $(':animated', el).length) {
                if ($(':visible', el).length) {
                    el.slideUp('200', function(){});
                    $(this).removeClass('expanded');
                } else {
                    el.slideDown('200', function(){});
                    $(this).addClass('expanded');
                }
            }
        });

        $('select#performanceUser').change(this.switchUser);

        $('#historyTable').tablesorter({cssHeader:'headerSort'}); 
    },

    switchUser: function() {
        var qs = '?user='+encodeURIComponent($('select#performanceUser').val());
        if (document.location.search.length > 0) {
            var params = document.location.search.substr(1).split("&");
            for (var i = 0; i < params.length; i++) {
                if (params[i].substr(0, 5) != 'user=') {
                    qs = qs + '&' + params[i];
                }
            }
        }
        window.location.search = qs;
    },

    showChartTooltip: function(x, y, contents) {
        $('<div id="chartTip">' + contents + '</div>').css( {
            position: 'absolute',
            display: 'none',
            top: y - 25,
            left: x + 5,
            border: '1px solid #fdd',
            padding: '2px',
            'background-color': '#fee',
            opacity: 0.80
        }).appendTo('body').fadeIn(100);
    },

    pieLegendEntry: function(color, text, value, total, id) {
        var percent = 0;
        if (total > 0) {
            percent = 100 * value / total;
        }
        var precision = 0;
        if (percent < 1 && percent > 0) {
            precision = 2;
        }
        var label = text + ' ' + percent.toFixed(precision) + '%';
        return $('<div id="' + id + '">' + label + '</div>').css('border-left','1.5em solid '+color);
    }
}
