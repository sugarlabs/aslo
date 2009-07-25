var author_roles = {
    NONE: 0,
    VIEWER: 1,
    DEV: 4,
    OWNER: 5,
    ADMIN: 6,
    ADMINOWNER: 7
};

var addon_statuses = {
    NULL: 0,
    SANDBOX: 1,
    PENDING: 2,
    NOMINATED: 3,
    PUBLIC: 4,
    DISABLED: 5
};

var upload = {
    'type': null,
    'response': null,
    'pastFirstLoad': false,
    
    showAgreement: function() {
        $('#step-intro').slideUp();
        $('#step-agreement').slideDown();
    },
    
    hideAgreement: function() {
        $('#step-agreement').slideUp();
        $('#step-intro').slideDown();
    },
    
    acceptAgreement: function() {
        if (license_picker.acceptable()) {
            $('#step-agreement').slideUp();
            $('#file-upload').slideDown();
        } else {
            license_picker.complain();
        }
    },
    
    platformAll: function() {
        $('#file-upload input[type=checkbox]').attr('disabled', 'disabled');
        $('#file-upload .specific-platforms').addClass('disabled');
    },
    
    platformSpecific: function() {
        $('#file-upload input[type=checkbox]').attr('disabled', '');
        $('#file-upload .specific-platforms').removeClass('disabled');
    },
    
    uploadFile: function() {
        if (!license_picker.acceptable()) {
            $('#file-upload').slideUp();
            upload.showAgreement();
            upload.acceptAgreement();
            return false;
        }
        else if ($('#upload-field').val() != '') {
            $('#file-upload input[type=submit]').attr('disabled', 'disabled');
            $('#upload-loading').show();
            $('#upload-error').slideUp('slow');
            $.each(['#license-name', '#license-translationbox'], function(i, s){
                       $(s).hide().appendTo('#upload-form');
            });
            upload.pastFirstLoad = true;
            return true;
        }
        else {
            alert(devcp_js_upload_alert);
            return false;
        }
    }
};

function iframeLoaded() {
    if (!upload.pastFirstLoad)
        return;
    
    $('#upload-loading').hide();
    
    upload.response = JSON.parse(document.getElementById('upload-frame').contentWindow.document.getElementById('json').innerHTML);
    
    if (upload.response.error == '1') {
        $('#upload-error-text').html(urldecode(upload.response.error_message));
        $('#upload-error').slideDown('slow');
        $('#file-upload input[type=submit]').attr('disabled', '');
    }
    else {
        $('#submission-area').slideUp('slow');
        $('#upload-success').slideDown('slow');
        
        if (upload.response.uploadtype == 'new') {
            $('#status-link').attr('href', $('#status-link').attr('href') + upload.response.addon_id);
            $('#complete-link').attr('href', $('#complete-link').attr('href') + upload.response.addon_id);
        }
        else if (upload.response.uploadtype == 'update') {
            if (upload.response.status == addon_statuses.PUBLIC) {
                $('#pending-message').hide();
                $('#new-file-status').text(addons_status_public);
            }
            else if (upload.response.status == addon_statuses.SANDBOX) {
                $('#new-file-status').text(addons_status_sandbox);
            }
            else if (upload.response.status == addon_statuses.PENDING) {
                $('#new-file-status').text(addons_status_pending);
            }
            $('#new-file-status').addClass('status-' + upload.response.status);
            $('#new-version-number').text(upload.response.version);
            $('#queue-count').text(upload.response.queuecount);
            $('#version-link').attr('href', $('#version-link').attr('href') + upload.response.version_id);
            $('#complete-link').attr('href', $('#complete-link').attr('href') + upload.response.version_id);
        }
        else if (upload.response.uploadtype == 'file') {
            if (upload.response.status == addon_statuses.PUBLIC) {
                $('#pending-message').hide();
                $('#new-file-status').text(addons_status_public);
            }
            else if (upload.response.status == addon_statuses.PENDING) {
                $('#new-file-status').text(addons_status_pending);
            }
            $('#new-file-status').addClass('status-' + upload.response.status);
            $('#queue-count').text(upload.response.queuecount);
        }
    }
}

function urldecode(str) {
    str = str.replace(/\+/g, ' ');
    str = unescape(str);
    return str;
}

var addon_edit_authors = {    
    showAddForm: function() {
        $('#add-author').show();
    },
    
    deleteAuthor: function(a) {
        var container = $(a).parent();
        container.addClass('open');
        container.find('.inline-delete-box').slideDown();
        $(a).blur();
    },
    
    confirmDelete: function(a) {
        this.cancelDelete(a);
        
        var container = $(a).parent().parent().parent();
        var tr = container.parent().parent();
        tr.fadeOut('slow', function() {
            tr.remove();
            addon_edit_authors.checkRowColors();
            addon_edit_authors.checkArrows();
            addon_edit_authors.checkAuthors();
        });
        tr.parent().parent().parent().find('.save-changes').slideDown();
    },
    
    cancelDelete: function(a) {
        var container = $(a).parent().parent().parent();
        container.find('.inline-delete-box').slideUp('normal', function() {
            container.removeClass('open');
        });
    },
    
    addAuthor: function(user_id, author, role, visible, markChanges) {
        var row = '<tr><td>';
        row += '<a class="down-arrow" href="#" onclick="addon_edit_authors.moveDownRow(this); return false;"><img src="' + imageURL + '/developers/arrow_down.png" alt="' + devcp_js_img_move_down + '" title="' + devcp_js_img_move_down + '" /></a>';
        row += '<a class="up-arrow" href="#" onclick="addon_edit_authors.moveUpRow(this); return false;"><img src="' + imageURL + '/developers/arrow_up.png" alt="' + devcp_js_img_move_up + '" title="' + devcp_js_img_move_up + '" /></a>';
        row += '</td><td><a href="' + profileURL + '/' + user_id + '">' + author + '</a></td><td>';
        row += '<select name="data[addons_users][' + user_id + '][role]">';
        row += '<option value="' + author_roles.OWNER + '" ' + (role == author_roles.OWNER ? ' selected="selected"' : '') + '>' + devcp_js_option_owner + '</option>';
        row += '<option value="' + author_roles.DEV + '" ' + (role == author_roles.DEV ? ' selected="selected"' : '') + '>' + devcp_js_option_developer + '</option>';
        row += '<option value="' + author_roles.VIEWER + '" ' + (role == author_roles.VIEWER ? ' selected="selected"' : '') + '>' + devcp_js_option_viewer + '</option>';
        row += '</td><td>';
        row += '<input type="checkbox" name="data[addons_users][' + user_id + '][listed]" value="1" ' + (visible == true ? ' checked="checked"' : '') + ' title="' + devcp_js_input_list_author + '"/>';
        row += '</td><td style="width: 25px;">';
        row += '<div class="inline-delete-button uses-image">';
        row += '<a href="#" onclick="addon_edit_authors.deleteAuthor(this); return false;"><img src="' + imageURL + '/developers/delete.png" alt="' + devcp_js_remove_author + '" title="' + devcp_js_remove_author + '" /></a>';
        row += '<div class="inline-delete-box">';
        row += '<p>' + devcp_js_sure_remove + '</p><br/>';
        row += '<p><a href="#" onclick="addon_edit_authors.confirmDelete(this); return false;" class="remove-button rounded">' + devcp_js_remove_author + '</a>&nbsp;&nbsp;';
        row += '<a href="#" onclick="addon_edit_authors.cancelDelete(this); return false;" class="button rounded">' + devcp_js_a_cancel + '</a></p>';
        row += '</div></div>';
        row += '</td></tr>';

        var newrow = $('#author-table tbody').append(row);
        this.checkRowColors();
        this.checkArrows();
        // check author roles
        this.checkAuthors();
        newrow.find('select[name*=role]').change(addon_edit_authors.checkAuthors);

        if (markChanges) $('.save-changes').slideDown();
    },
    
    moveUpRow: function(a) {
        var row = $(a).parent().parent();
        var prev = row.prev();
        if (prev.html() != null) {
            var selectedIndex = a.parentNode.parentNode.getElementsByTagName('select')[0].selectedIndex;
            prev.before(row.clone(true));
            prev.prev().find('select').attr('selectedIndex', selectedIndex);
            row.remove();
            
            this.checkArrows();
            this.checkRowColors();
        }
    },
    
    moveDownRow: function(a) {
        var row = $(a).parent().parent();
        var next = row.next();
        if (next.html() != null) {
            var selectedIndex = a.parentNode.parentNode.getElementsByTagName('select')[0].selectedIndex;
            next.after(row.clone(true));
            next.next().find('select').attr('selectedIndex', selectedIndex);
            row.remove();
            
            this.checkArrows();
            this.checkRowColors();
        }
    },
    
    checkRowColors: function() {
        $('#author-table tbody tr:visible:even').addClass('alt');
        $('#author-table tbody tr:visible:odd').removeClass('alt');
    },
    
    checkArrows: function() {
        $('#author-table tbody .down-arrow').css('visibility', 'visible');
        $('#author-table tbody .up-arrow').show();
        $('#author-table tbody tr:first .up-arrow').hide();
        $('#author-table tbody tr:last .down-arrow').css('visibility', 'hidden');
    },
    
    checkAddForm: function() {
        var email = $('#add-email').val();
        
        if (email != '') {
            $('#add-error').slideUp();
            $('#add-loading').show();
            $.getJSON(jsonURL + '/verifyauthor/?email=' + email, null, function(data) {
                $('#add-loading').hide();
                if (data.error == '0') {
                    var listed = $('#add-listed').attr('checked');
                    var role = $('#add-role-developer').attr('checked') ? author_roles.DEV : ($('#add-role-viewer').attr('checked') ? author_roles.VIEWER : author_roles.OWNER);
                    addon_edit_authors.addAuthor(data.id, data.displayname, role, listed, true);
                    addon_edit_authors.resetAddForm();
                }
                else {
                    $('#add-error').text(data.error_message);
                    $('#add-error').slideDown();
                }
            });
            
            return true;
        }
        else {
            $('#add-error').html(devcp_js_add_email);
            $('#add-error').slideDown();
            return false;
        }
    },
    
    resetAddForm: function() {
        $('#add-author').hide();
        $('#add-email').val('');
        $('#add-role-owner').attr('checked', 'checked');
        $('#add-listed').attr('checked', 'checked');
        $('#add-error').slideUp();
        $('#add-loading').hide();
    },
    
    checkAuthors: function() {
        var empty_authors = (!$('#author-table select[name*=role]>option:selected[value='+author_roles['OWNER']+']').size());
        // TODO: with jQuery 1.3+, just use toggle(empty_authors)
        if (empty_authors) {
            $('#submit').hide();
            $('#empty-authors').fadeIn();
        } else {
            $('#empty-authors').fadeOut('normal', function(){$('#submit').show();});
        }
    },

    save: function() {
        $('#addon-edit-authors-form').submit();
    }
};

var addon_edit_descriptions = {
    save: function() {
        if ($('.translation-error').size() > 1 || $('.translation-maxlength.over').size() > 0) {
            $('#edit-error').show();
        }
        else {
            $('#addon-edit-descriptions-form').submit();
        }
    }
};

var addon_edit_properties = {
    deleteIcon: function() {
        $('#delete-icon').val('1');
        $('#addon-icon').addClass('deleted');
        $('#delete-icon-area').hide();
        $('#undelete-icon-area').show();
    },
    
    undeleteIcon: function() {
        $('#delete-icon').val('0');
        $('#addon-icon').removeClass('deleted');
        $('#undelete-icon-area').hide();
        $('#delete-icon-area').show();
    },
    
    save: function() {
        $('#addon-edit-properties-form').submit();
    }
};

var addon_edit_categories = {
    updateDescription: function(app_id, description) {
        $('#edit-categories-descriptions' + app_id).html(description);
    },
    
    toggleDropdowns: function(checkbox, app_id) {
        if (checkbox.checked) {
            $('.app-' + app_id + ' select').attr('disabled', 'disabled');
        }
        else {
            $('.app-' + app_id + ' select').attr('disabled', '');
        }
    },
    
    save: function() {
        $('#addon-edit-categories-form').submit();
    }
};

var versions_validate = {

    showMore: function(a) {
        // Show more validation results
        a.parent().parent().children('.hidden-results').slideDown();
        a.parent().slideUp();
    },

    runTest: function(fileId) {

        // If tests are running, just bail
        if($('#test-results-' + fileId + ' .action-button').hasClass('disabled')) return;

        var sendRequest = function(file, testNum, name) {
            versions_validate.running_tests++;
            $.ajax({
                type: 'GET',
                url: '../../verify/' + fileId + '/' + testNum,
                success: displayResults,
                error: displayError
            });
            $('#results-summary-' + fileId + '-' + testNum +' .loading-count').show();
        }

        var displayResults = function(response, status) {
            // JSON parser chokes on ', since it uses eval().  
            // Since the result is just a big tree, we can simply replace them.
            response = response.replace(/'/g, '"');
            var result = JSON.parse(response);
        
            $('#test-details-' + result.file_id).append(result.result);
            $('#results-summary-' + result.file_id + '-' + result.test_group_id + ' .results').fadeOut('slow', function () {
                $(this).html(result.stats).fadeIn('slow');
            });

            for (var i in result.next_tests) {
                var testInfo = result.next_tests[i].TestGroup;
                sendRequest(result.file_id, testInfo.id, testInfo.name);
            } 

            versions_validate.running_tests--;
            if (versions_validate.running_tests == 0) {
                $('#test-details-' + result.file_id).slideDown('slow');
                $('#test-results-' + fileId + ' .action-button').removeClass('disabled');
                $('#test-results-' + fileId + ' .tests-running').hide();
                $('#test-results-total-' + fileId).fadeOut('slow', function() {
                    $(this).html(result.total_stats).fadeIn('slow');
                });
            }
        };
    
        var displayError = function(req, status, errorThrown) {
            $('#test-error .status').html('Error: ' + status + ' - ' + errorThrown).show();
        }

        $('#test-details-' + fileId).slideUp('slow', function () {
            $('#test-details-' + fileId).html('');
            versions_validate.running_tests = 0;
            sendRequest(fileId, 1, 'General');
        });

        $('#test-results-' + fileId + ' .action-button').addClass('disabled');
        $('#test-results-' + fileId + ' .tests-running').show();
        $('#test-summary-' + fileId + ' .results span:not(.loading-count)').remove();
    }
};

var versions = {
    deleteVersion: function(a) {
        var container = $(a).parent();
        container.addClass('open');
        container.find('.inline-delete-box').slideDown();
        $(a).blur();
    },
    
    cancelDelete: function(a) {
        var container = $(a).parent().parent().parent();
        container.find('.inline-delete-box').slideUp('normal', function() {
            container.removeClass('open');
        });
    }
};

var versions_delete = {
    confirm: function() {
        $('#versions-delete-form').submit();
    }
};

var versions_edit = {
    deleteVersion: function(a) {
        var container = $(a).parent();
        container.addClass('open');
        container.find('.inline-delete-box').slideDown();
        $(a).blur();
    },
    
    confirmDelete: function(a) {
        this.cancelDelete(a);
        
        var container = $(a).parent().parent().parent();
        var tr = container.parent().parent();
        tr.fadeOut('slow', function() {
            tr.parent().find('tr:visible:even').addClass('alt');
            tr.parent().find('tr:visible:odd').removeClass('alt');
        });
        tr.find('input.delete').val('1');
        tr.parent().parent().parent().find('.save-changes').slideDown();
    },
    
    cancelDelete: function(a) {
        var container = $(a).parent().parent().parent();
        container.find('.inline-delete-box').slideUp('normal', function() {
            container.removeClass('open');
        });
    },
    
    showAppPicker: function() {
        $.each(application_names, function(app_id, app_name) {
            var disabled = ($('#edit-versions-targetapps-table tr.' + app_id + ':visible').size() > 0) ? 'disabled' : '';
            $('#new-app-picker select option[value="' + app_id + '"]').attr('disabled', disabled);
        });
        
        $('#new-app-picker').show();
        $('#new-app-picker select').focus();
    },
    
    addApplication: function(select) {
        var application_id = $(select).val();
        if (application_id == '')
            return;
        
        var newRow = '<tr class="' + application_id + '">';
        newRow += '<td><img src="' + imageBase + '/' + application_names[application_id].toLowerCase() + '.png" alt="' + application_names[application_id] + '"/></td>';
        newRow += '<td class="appname">' + application_names[application_id];
        newRow += '<input type="hidden" name="data[Application][' + application_id + '][new]" value="1"/></td>';
        newRow += '<td>' + $('#app' + application_id + '-dropdowns').html() + '</td>';
        newRow += '<td style="width: 25px;"><div class="inline-delete-button">';
        newRow += '<a href="#" onclick="versions_edit.confirmDelete(this); return false;"><img src="' + imageBase + '/delete.png" alt="' + devcp_js_img_remove_compat + '" title="' + devcp_js_img_remove_compat + '" /></a>';
        newRow += '</div></td>';
        newRow += '</tr>';
        
        $('#edit-versions-targetapps-table').append(newRow);
        $('#new-app-picker').hide();
        $('#new-app-picker select option:first').attr('selected', 'selected');
        
        $('#edit-versions-targetapps-table tr:visible:even').addClass('alt');
        $('#edit-versions-targetapps-table tr:visible:odd').removeClass('alt');
    },
    
    save: function() {
        if (!license_picker.acceptable(true)) {
            license_picker.complain();
        } else {
            $('#versions-edit-form').submit();
        }
    }

};

var license_picker = {
    // Called from document.ready.
    // license_trans is a dict {"license picker vals": "transbox HTML"}
    init: function(license_trans) {
        // Turn the strings into DOM elements.
        for (var k in license_trans) {
            license_trans[k] = $(license_trans[k]);
        }
        $("input[name='data[License][name]']").change(function() {
            var val = $("input[name='data[License][name]']:checked").val();
            $("#license-translationbox").contents().remove();
            if(val in license_trans) {
                license_trans[val].appendTo($('#license-translationbox'));
            }
        });
        $("input[name='data[License][name]']").change();
    },

    acceptable: function(accept_null) {
        var val = $("input[name='data[License][name]']:checked").val();
        var trans = $.grep($("#license-translationbox textarea"),
                           function(e) {
                               return $(e).val() != "";
                           });
        var is_null = val == 'null';
        var empty_custom = val == 'other' && trans.length < 1;
        return !(!accept_null && is_null || empty_custom);
    },

    complain: function(accept_null) {
        if (!license_picker.acceptable(accept_null)) {
            if ($("input[name='data[License][name]']:checked").val() == 'other') {
                // The text box must be empty.
                alert(devcp_js_license_text);
            } else {
                alert(devcp_js_license_select);
            }
        }
    }
};

var previews = {
    showReplaceBox: function(a) {
        var box = $(a).parent().parent().parent().parent().parent();
        box.find('.replace-preview').slideDown().focus();
    },
    
    showAddBox: function(button) {
        $('#add-preview-box').show();
        $(button).parent().hide();
    },
    
    cancelReplace: function(a) {
        var replaceBox = $(a).parent().parent();
        replaceBox.slideUp();
        replaceBox.find('input').val('');
    },
    
    addUploadBox: function() {
        $('#new-preview-container').append('<label class="new-preview">' + $('.new-preview:first').html() + '</label>');
    },
    
    deletePreview: function(a) {
        var box = $(a).parent().parent().parent().parent().parent().parent().parent().parent().parent();
        box.addClass('errors');
        box.find('input.delete').val('true');
    },
    
    cancelDelete: function(a) {
        var box = $(a).parent().parent();
        box.removeClass('errors');
        box.find('input.delete').val('false');
    },
    
    save: function() {
        $('#previews-form').submit();
    }
};

var addon_status = {
    confirm: function() {
        $('#status-form').submit();
    }
}

/************************************************
*             addontype constants               *
************************************************/
var ADDON_EXTENSION = '1';
var ADDON_THEME = '2';
var ADDON_DICT = '3';
var ADDON_SEARCH = '4';
var ADDON_LPAPP = '5';
var ADDON_LPADDON = '6';

/************************************************
*         developers/add_step1.thtml            *
************************************************/
//Show appropriate form items based on addontype
function selectType(select) {
    var addonType = select.options[select.selectedIndex].value;

    if (addonType == ADDON_SEARCH) {
        document.getElementById('file2').style.display = 'none';
        document.getElementById('file3').style.display = 'none';
        document.getElementById('addrow').style.display = 'none';
        document.getElementById('platform1').style.display = 'none';
        document.getElementById('platform2').style.display = 'none';
    }
    else {
        document.getElementById('platform1').style.display = '';        
        document.getElementById('platform2').style.display = '';
    }
}

//Show the next available file upload box
function addFile() {
    if(document.getElementById('file2').style.display == 'none') {
        //show file 2
        document.getElementById('file2').style.display = '';
    }
    else {
        //show file 3
        document.getElementById('file3').style.display = '';
        document.getElementById('addrow').style.display = 'none';
    }
}

//Show or hide additional version boxes
function selectPlatform(select) {
    //If the first file is version ALL, hide other boxes
    if (select.selectedIndex == 0) {
        document.getElementById('file2').style.display = 'none';
        document.getElementById('file3').style.display = 'none';
        document.getElementById('addrow').style.display = 'none';
    }
    //If the first file is not ALL, allow other boxes
    else {
        if (document.getElementById('file3').style.display == 'none') {
            document.getElementById('addrow').style.display = '';
        }
    }
}

/************************************************
*         developers/add_step2.thtml            *
*         developers/edit                       *
************************************************/
//Show addontype change form
function showAddontypes() {
    document.getElementById('changeAddontype').style.display = '';
}

//Change addontype
function changeAddontype(select, current) {
    var addontype_id = select.options[select.selectedIndex].value;
    
    var tags = document.getElementById('TagTag');
    var msg = document.getElementById('tagNext');
    var desc = document.getElementById('tagDescription');
    
    if (addontype_id != current) {
        tags.style.display = 'none';
        desc.style.display = 'none';
        msg.style.display = '';
    }
    else {
        tags.style.display = '';
        desc.style.display = '';
        msg.style.display = 'none';
    }
}

//Make sure summary is the correct length
function checkSummary(summary, message) {
    if (summary.value.length > 250) {
        alert(message.replace('%s', summary.value.length));
    }
}

//Shows the add author textbox/button
function showAuthorForm() {
    document.getElementById('addAuthorRow').style.display = 'none';
    document.getElementById('newAuthorRow').style.display = '';
    document.getElementById('newAuthor').focus();
}

//Retrieves a user's name by email and creates a hidden input with id
function addAuthor(url) {
    var newAuthorElt = document.getElementById('newAuthor');
    var addButtonElt = document.getElementById('addButton');
    var authorsElt = document.getElementById('authors');
	
    //Remove any "not found" errors
    var divs = authorsElt.getElementsByTagName('div');
    for(var i = 0; i < divs.length; i++) {
        if(divs[i].className.indexOf('notfound') != -1) {
            $(divs[i]).fadeOut();
        }
    }

    //Only proceed if the text field is not empty, else highlight the field
    if (newAuthorElt.value != '') {
        newAuthorElt.disabled = true;
        addButtonElt.disabled = true;

        //Callback function for success
        var updateAuthors = function(t, status) {            
            var div = document.createElement('div');
            //If not found, mark for later removal
            if (t.indexOf('<strong>') != -1) {
                div.className += ' notfound';
            }
            div.innerHTML = t;
            authorsElt.appendChild(div);
           
            newAuthorElt.disabled = false;
            newAuthorElt.value = '';
            addButtonElt.disabled = false;
            newAuthorElt.focus();
        }

        //Callback function for failure
        var showError = function(req, status, errorThrown) {
            //Error messages won't be localized, but... what can we do?
            alert('Error: ' + status + ' - ' + errorThrown);
            newAuthorElt.disabled = false;
            addButtonElt.disabled = false;
            newAuthorElt.focus();
        }
        $.ajax({
            type : 'GET',
            url : url,
            data : {q : newAuthorElt.value},
            success : updateAuthors,
            error : showError
        });
    }
}

//Removes an author by clearing the hidden input value and hiding the div
function removeAuthor(author) {
    var div = author.parentNode;
    var hidden = div.getElementsByTagName('input')[0];
    hidden.value = '';
    $(div).fadeOut();
}

//Update tag description div
function updateTagDescription(select) {
    var option = select.options[select.selectedIndex];
    document.getElementById('tagDescription').innerHTML = tagDescriptions[option.value];
}

/************************************************
*           developers/add_step4.thtml          *
************************************************/
//currently shown locale
var previousLocale = '';
var previousSpan = '';

//show a locale
function showLocale(locale, span) {
    if (previousLocale != '') {
        document.getElementById('locale_' + previousLocale).style.display = 'none'; 
        previousSpan.className = '';
    }
    document.getElementById('locale_' + locale).style.display = '';
    span.className = 'selected';

    previousLocale = locale;
    previousSpan = span;
}

/************************************************
*            developers/edit.thtml              *
************************************************/
//Shows icon upload boxes
function addIcon(type) {
    document.getElementById('newIcon').style.display = '';
    
    //Only unhide delete div if updating
    if (type == 'edit') {
        document.getElementById('deleteIcon').style.display = '';
        document.getElementById('iconLink').style.display = 'none';
    }
    else if (type =='new') {
        document.getElementById('iconDiv').style.display = 'none';
    }
}

/************************************************
*            developers/editversion.thtml       *
************************************************/
//Only confirm delete if checking the box
function confirmDelete(checkbox) {
    if (checkbox.checked == false) {
        return true;
    }
    else {
        return confirm(localized['deleteMessage']);
    }
}

/************************************************
*            previews/edit.thtml                *
************************************************/
//Confirm making default if not already default
function confirmMakeDefault(checkbox) {
    if (checkbox.checked == false) {
        return true;
    }
    else {
        return confirm(localized['makeDefaultNotice']);
    }
}

//Confirm clearing default
function confirmClearDefault(checkbox) {
    if (checkbox.checked == true) {
        return true;
    }
    else {
        return confirm(localized['clearDefaultNotice']);
    }
}

var edit_profile = function() {
    $('#create-dev-profile button').click(function(){
        $('#create-dev-profile').slideUp();
        $('#dev-profile').slideDown();
    });
};

var edit_contributions = function() {
    $('#start-asking button').click(function(){
        $('#start-asking').slideUp();
        $('#contributions').slideDown();
    });
};
