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
*
* These members must be set prior to calling init()
*   subscribe_url
*   unsubscribe_url
*   subscribe_text
*   unsubscribe_text
*
*************************************************/
var editors_review = {
    init: function () {
        // indent comment replies
        var margin = 'margin-left';
        if ($('body').hasClass('html-rtl')) {
            margin = 'margin-right';
        }
        var depth = 1;
        var comments = $('#editorComments .commentDepth'+depth);
        while (comments.length > 0) {
            comments.css(margin, 2*depth+'em');
            depth += 1;
            comments = $('#editorComments .commentDepth'+depth);
        }

        // apply syntax highlighting (and localize its strings)
        SyntaxHighlighter.config.strings.viewSource = localized['editors_syntax_view_source'];
        SyntaxHighlighter.config.strings.print = localized['editors_syntax_print'];
        SyntaxHighlighter.config.strings.help = localized['editors_syntax_about'];
        SyntaxHighlighter.all();

        // click a root comment header to toggle showing/hiding the entire thread
        $('#editorComments .commentDepth0 .commentHeader').click(this.toggleThread);
        
        // show the comment form
        $('#editorComments .replyLink, #editorComments .newThreadLink').click(this.showCommentForm);

        // hide the comment form
        $('#VersioncommentCancel').click(function() {
            $(this.form).slideUp('fast');
        });

        // highlight any comment specified in the location hash
        if (window.location.hash.match(/^#editorComment(\d+)$/)) {
            $(window.location.hash).css('background-color', '#efefef');
        }

        this.miuMarkdownConfig.markupSet = [
            { name:localized['editors_review_bold'], key:'B', openWith:'**', closeWith:'**' },
            { name:localized['editors_review_italics'], key:'I', openWith:'_', closeWith:'_' },
            { separator:'---------------' },
            { name:localized['editors_review_unordered_lists'], openWith:'* ' },
            { name:localized['editors_review_ordered_lists'], openWith:function(markItUp) { return markItUp.line+'. '; } },
            { separator:'---------------' },
            { name:localized['editors_review_block_quotes'], openWith:'> ' },
            { name:localized['editors_review_code_blocks'], dropMenu:[
                    { name:localized['editors_review_code_text'], openWith:'~~~~~ {.text}\n', closeWith:'\n~~~~~\n' },
                    { name:localized['editors_review_code_html'], openWith:'~~~~~ {.html}\n', closeWith:'\n~~~~~\n' },
                    { name:localized['editors_review_code_css'], openWith:'~~~~~ {.css}\n', closeWith:'\n~~~~~\n' },
                    { name:localized['editors_review_code_javascript_xul'], openWith:'~~~~~ {.javascript}\n', closeWith:'\n~~~~~\n' },
                    { name:localized['editors_review_code_diff'], openWith:'~~~~~ {.diff}\n', closeWith:'\n~~~~~\n' },
                    { name:localized['editors_review_code_sql'], openWith:'~~~~~ {.sql}\n', closeWith:'\n~~~~~\n' }
                ]
            },
            { separator:'---------------' },	
            { name:localized['editors_markdown_preview'], className:'preview',  call:'preview' },
            { name:localized['editors_review_comment_help_heading'], openWith:function(h) { editors_review.showMarkitupHelp(); return ''; } }
        ];

        // attach Markitup editor to comment textarea (with a basic markdown config)
        $('#VersioncommentComment').markItUp(this.miuMarkdownConfig);

        // events to close help popup
        $('#markitupHelpClose').click(this.hideMarkitupHelp);
        $(document).keypress(function(e) {  
            if ($('#markitupHelp').is(':visible') && e.keyCode == 27) {
                editors_review.hideMarkitupHelp();
            }
        })  

        // create subscribe/unsubscribe links based on non-JS hidden forms
        var self = this;
        $('form.subscription').each(function(){
            var alink = $('<a href="#"></a>')
                .addClass('subscription')
                .click(self.threadSubscribeUnsubscribe);
            if ($(this).attr('action').match(/threadsubscribe/)) {
                alink.text(self.subscribe_text);
            } else {
                alink.text(self.unsubscribe_text).addClass('subscribed');
            }
            $(this).before(alink);
        });
    },

    // Markitup markdown configuration (modified)
    // original code Copyright (C) 2007-2008 Jay Salvat
    // http://markitup.jaysalvat.com/
    // Dual licensed under the MIT and GPL licenses.
    miuMarkdownConfig:  {
        previewParserPath: '',
        previewParserVar: '',
        previewInWindow: false,
        previewAutoRefresh: false,
        onShiftEnter: { keepDefault:false, openWith:'\n\n' },
        markupSet: []
    },

    // show comment help popup
    showMarkitupHelp: function() {
        // get dimensions in order to center popup
        var winHeight = $(window).height();
        var winWidth = $(window).width();

        $('#helpBackground').fadeIn('fast');
        $('#markitupHelp')
            .css({
                position: 'fixed',
                top: 0.1*winHeight,
                left: 0.2*winWidth,
                height: 0.8*winHeight,
                width: 0.6*winWidth,
                overflow: 'auto',
                'overflow-x': 'hidden'
            })
            .fadeIn('fast');
    },

    // hide comment help popup
    hideMarkitupHelp: function() {
        $('#helpBackground').fadeOut('fast');
        $('#markitupHelp').fadeOut('fast');
    },

    // toggle showing/hiding a single comment body
    toggleOneComment: function(e) {
        var commentBody = $('.commentBody', $(this).parent());
        if (! commentBody.is(':animated')) {
            if (commentBody.is(':visible')) {
                commentBody.slideUp('fast');
                $(this).addClass('collapsed');
            } else {
                commentBody.slideDown('fast');
                $(this).removeClass('collapsed');
            }
        }
        return false;
    },

    // toggle showing/hiding an entire comment thread
    toggleThread: function(e) {
        var root = $(this).parent();
        var rootBody = $('.commentBody', root);

        if (! rootBody.is(':animated')) {
            // toggle body of root comment
            var visible = rootBody.is(':visible');
            if (visible) {
                $(rootBody).slideUp();
                $(this).addClass('collapsed');
            } else {
                $(rootBody).slideDown();
                $(this).removeClass('collapsed');
            }

            // walk and toggle list of siblings until we hit a root node (or the end)
            for (   var sibling = root.next('.editorComment').not('.commentDepth0');
                    sibling.length;
                    sibling = sibling.next('.editorComment').not('.commentDepth0')) {
                if (visible) {
                    sibling.slideUp();
                } else {
                    sibling.slideDown();
                }
            }
        }
    },

    // move and show the comment form under the link clicked
    showCommentForm: function(e) {
        var re = /^#editorComment(\d+)$/;
        var match = re.exec($(this).attr('href'));
        var commentId = '';
        if (match) {
            commentId = match[1];
        }

        // when relocating the form, initialize subject and comment 
        if (! $(this).next().is('#editorCommentForm')) {
            if (match) {
                var subject = $('#editorComment'+commentId+' .commentHeader').text();
                subject = subject.replace(/^(re: )?/, 're: ');
                $('#VersioncommentSubject').val(subject);
            } else {
                $('#VersioncommentSubject').val('');
            }
            $('#VersioncommentComment').val('');
            $('#editorCommentForm').insertAfter(this);
        }
        // always initialize reply_to just to be safe
        $('#VersioncommentReplyTo').val(commentId);

        $('#editorCommentForm').slideDown('fast');
        return false;
    },

    // subscribe/unsubscribe to a thread via ajax
    threadSubscribeUnsubscribe: function(e) {
        // 'this' is the clicked link
        var alink = $(this);
        var self = editors_review;

        // already waiting on a request
        if (alink.hasClass('loading')) {
            return false;
        }

        // fire up a new request
        alink.addClass('loading');
        $.ajax({
            type: 'POST',
            url: alink.hasClass('subscribed') ? self.unsubscribe_url : self.subscribe_url,
            data: $('form', alink.parent()).serialize(),
            dataType: 'json',
            success: function(data, textStatus){
                if ('success' in data && data.success) {
                    // toggle link text/subscribed status
                    if (alink.hasClass('subscribed')) {
                        alink.removeClass('subscribed');
                        alink.text(self.subscribe_text);
                    } else {
                        alink.addClass('subscribed');
                        alink.text(self.unsubscribe_text);
                    }
                }
            },
            complete: function(xhr, textStatus){
                alink.removeClass('loading'); // all done
            }
        });

        return false;
    }
}

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
        addonforminputlocale.setAttribute('name', 'data[AddonCategory][feature_locales]');
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

        $.plot($('#byMonthChart'), this.monthlyData, {
            lines: { show: true },
            points: { show: true },
            xaxis: { ticks: this.monthlyTicks },
            grid: { backgroundColor: "#fffaff", hoverable: true }
        });
        $('#byMonthChart').bind('plothover', {previousPoint: null}, this.plotHover);

        if (this.pieData) {
            var mp = new MultiPie(this.pieData, this.pieLabels, this.pieOptions);

            mp.showSlices('byCatUserChart', 10);
            $('#topUserSlices').click(    function() { mp.showSlices('byCatUserChart', 10); return false; });
            $('#topTeamSlices').click(    function() { mp.showSlices('byCatTeamChart', 10); return false; });
            $('#allSlices').click(        function() { mp.showSlices('byCatTeamChart'); return false; });
            $('#noSlices').click(         function() { mp.noSlices(); return false; });
            $('#toggleSliceLabels').click(function() { mp.toggleSliceLabels(); return false; });
        }
    },

    plotHover: function (event, pos, item) {
        if (item) {
            if (event.data.previousPoint != item.datapoint) {
                event.data.previousPoint = item.datapoint;
                $('#plotHoverTip').remove();
                var y = item.datapoint[1].toFixed(2);
                $('<div id="plotHoverTip">' + y + '</div>').css( {
                    position: 'absolute',
                    display: 'none',
                    top: item.pageY - 25,
                    left: item.pageX + 5,
                    border: '1px solid #fdd',
                    padding: '2px',
                    'background-color': '#fee',
                    opacity: 0.80
                }).appendTo('body').fadeIn(100);
            }
        }
        else {
            $('#plotHoverTip').remove();
            event.data.previousPoint = null;            
        }
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
    }
};

/**
 * MultiPie - generate multiple interactive pie charts linked by a common set of labels
 *
 * The number of labels and length of each dataset must all be equal
 * @param dataSets [{id:'containerId', data:[1,2,3], height:100}, ... ] or selector
 * @param labels ['label1', 'label2', 'label3']
 * @param options { ... }
 *      pieColors: array of strings - colors to use for selected items
 *      otherColor: string - color to use for the unselected items 'others' slice
 *      otherLabel: string - label for the 'others' slice
 *      emptyLabel: string - label for the empty pie (when a data set sums to 0)
 *      labelSlices: bool - true to label each slice with a percentage
 *      legendId: string - id of a container to build the legend in
 *      defaultHeight: number - default pie height (used if height not specified in a data set)
 *      offset: number - degrees to rotate each pie
 */
function MultiPie(dataSets, labels, options) {
    this.init(dataSets, labels, options);
}
MultiPie.prototype = {
    init: function(dataSets, labels, options) {
        if (typeof dataSets == 'string') {
            // be more sparkline-ish and let data/containers be specified via selector
            this.dataSets = [];
            var containers = $(dataSets);
            for (var i = 0; i<containers.length; i++) {
                var id = $(containers[i]).attr('id');
                var values = $.map($(containers[i]).text().split(','), function(n) { return Number(n); });
                if (id) {
                    this.dataSets.push({id:id, data:values});
                }
            }

        } else {
            // specify datasets explicitly
            this.dataSets = dataSets;
        }

        this.labels = labels;
        $.extend(this.options, options);
        this.pieColors = this.options.pieColors.slice();

        if (! this.dataSets.length) {
            return;
        }

        this.createLegend();
        this.showSlices(0, 10, false);
    },

    options: {
        pieColors: [ '#f0b400', '#1e6c0b', '#00488c', '#332600', '#d84000', '#b30023',
                     '#f8d753', '#529746', '#3e75a7', '#7a653e', '#e1662a', '#c4384f',
                     '#fff8a3', '#a9cc8f', '#b2c8d9', '#bea37a', '#f3aa79', '#e6a5a4' ],
        otherColor: '#666666',
        otherLabel: 'others',
        emptyLabel: 'no data',
        labelSlices: true,
        legendId: '',
        defaultHeight: 200,
        offset: -90
    },

    // callback for highlighting (outlining) pie slices and their legend entry
    highlightSlices: function(e, item, self) {
        var re = /(\d)+$/;
        var match = re.exec($(item).attr('id'));
        if (! match) {
            return;
        }

        var dataIdx = Number(match[0]);
        var coloredIdx = $.inArray(dataIdx, self.sliceOrder);
        var otherIdx = $.inArray(dataIdx, self.sliceOrder[self.sliceOrder.length-1]);

        // highlight legend entry
        if (self.options.legendId) {
            $('#'+self.options.legendId+'Item'+dataIdx).css('border', '1px solid black');
        }

        // no slices found
        if (coloredIdx < 0 && otherIdx < 0) {
            return;
        }

        // highlight any matching non-empty slice on each pie
        for (var i=0; i<self.sliceMeta.length; i++) {
            if (self.dataSets[i].data[dataIdx] <= 0 || self.sliceMeta[i].total <= 0) {
                continue; // empty slice or empty pie
            }

            var start, end;
            if (coloredIdx >= 0) {
                // use precalculated coords for colored slices
                start = self.sliceMeta[i][coloredIdx].radStart;
                end = self.sliceMeta[i][coloredIdx].radStop;
            } else {
                // manually calculate a portion of the 'others' slice
                // 'others' is always the last slice
                start = self.sliceMeta[i][self.sliceMeta[i].length-1].radStart;
                end = start + 2*Math.PI*(self.dataSets[i].data[dataIdx] / self.sliceMeta[i].total);
            }

            // draw the slice outline, reusing existing pie canvas if available
            var target = $('#'+self.dataSets[i].id).simpledraw(undefined, undefined, true);
            if (target) {
                target.drawPieSlice(target.height/2, target.height/2, target.height/2,
                    start, end, '#000000');
            }
        }
    },

    // callback for clearing highlights
    clearHighlights: function(e, item, self) {
        var re = /(\d)+$/;
        var match = re.exec($(item).attr('id'));
        if (match && self.options.legendId) {
            // unhighlight legend entry
            var dataIdx = Number(match[0]);
            $('#'+self.options.legendId+'Item'+dataIdx).css('border', '1px solid transparent');
        }
        // redraw to clear any slice outlines
        self.drawPies();
    },

    // creates a legend as a 2 column list
    // initially all legend entries are uncolored
    // style li.col2 with a left margin larger than the width of li.col1 items
    createLegend: function() {
        if (! this.options.legendId) {
            return;
        }

        $('ul', '#'+this.options.legendId).remove();

        var border = 'border-left';
        if ($('body').hasClass('html-rtl')) {
            border = 'border-right';
        }

        var idBase = this.options.legendId;
        var legendUl = $('<ul></ul>');
        var self = this; // used by event handlers

        for (var i=0; i<this.labels.length; i++) {
            var liClass = 'col1';
            if (i >= this.labels.length/2) {
                liClass = 'col2';
            }
            var li = $('<li id="'+idBase+'Item'+i+'" class="'+liClass+'" />')
                .css('border', '1px solid transparent')
                .appendTo(legendUl)
                .click(function(e) { self.legendClick(e, this, self); })
                .mouseenter(function(e) { self.highlightSlices(e, this, self); })
                .mouseleave(function(e) { self.clearHighlights(e, this, self); });
            $('<span>'+this.labels[i]+'</span>')
                .css(border, '1.2em solid '+this.options.otherColor)
                .appendTo(li);

        }
        legendUl.appendTo('#'+this.options.legendId);

        // adjust the second column of legend items up even with the first
        var liHeight = $('li.col1', legendUl).outerHeight();
        var liRows = $('li.col1', legendUl).length + 4.2;
        // @TODO: the above 4.2 fudge-factor shouldn't be needed
        $('li.col2:first', legendUl).css('margin-top', '-' + (liHeight*liRows) + 'px');

        // @TODO: dont rely on external css - determine and apply a left or right margin to all col2 items

    },

    // recolors all legend entries according to selected items/slices
    recolorLegend: function() {
        if (! this.options.legendId) {
            return;
        }

        var border = 'border-left';
        if ($('body').hasClass('html-rtl')) {
            border = 'border-right';
        }

        for (var i=0; i<this.labels.length; i++) {
            var j = $.inArray(i, this.sliceOrder);
            if (j >= 0) {
                var color = this.sliceColors[j];
            } else {
                var color = this.options.otherColor;
            }
            $('#'+this.options.legendId+'Item'+i+' span').css(border, '1.2em solid '+color);
        }
    },

    // callback for selected/unselecting colored slices
    legendClick: function(e, item, self) {
        var re = /(\d)+$/;
        var match = re.exec($(item).attr('id'));
        if (! match) {
            return;
        }

        var dataIdx = Number(match[0]);
        var coloredIdx = $.inArray(dataIdx, self.sliceOrder);
        var otherIdx = $.inArray(dataIdx, self.sliceOrder[self.sliceOrder.length-1]);
        if (coloredIdx >= 0) {
            // remove slice
            self.sliceOrder.splice(coloredIdx, 1);
            self.pushColor(self.sliceColors[coloredIdx]);
            self.sliceColors.splice(coloredIdx, 1);
            for (var i=0; i < self.sliceData.length; i++) {
                self.sliceData[i].splice(coloredIdx, 1);
            }

            // add to other
            self.sliceOrder[self.sliceOrder.length-1].push(dataIdx);

            // sum to other slice totals
            for (var i=0; i < self.sliceData.length; i++) {
                self.sliceData[i][self.sliceData[i].length-1] += self.dataSets[i].data[dataIdx];
            }

            self.updateSliceMeta();
            self.drawPies();

            if (item.nodeName == 'LI') {
                // mouse is still over a legend item, so re-highlight
                self.highlightSlices(e, item, self);
            } else {
                // mouse is somewhere else - remove any highlights
                self.clearHighlights(e, item, self);
            }

        } else if (otherIdx >= 0) {
            // remove from other
            self.sliceOrder[self.sliceOrder.length-1].splice(otherIdx, 1);

            // subtract from other slice totals
            for (var i=0; i < self.sliceData.length; i++) {
                self.sliceData[i][self.sliceData[i].length-1] -= self.dataSets[i].data[dataIdx];
            }

            // add new colored slice
            self.sliceOrder.splice(self.sliceOrder.length-2, 0, dataIdx);
            self.sliceColors.splice(self.sliceColors.length-2, 0, self.nextColor());
            for (var i=0; i< self.sliceData.length; i++) {
                self.sliceData[i].splice(self.sliceData[i].length-2, 0, self.dataSets[i].data[dataIdx]);
            }

            self.updateSliceMeta();
            self.drawPies();
            self.highlightSlices(e, item, self);
        }
    },

    // creates an image map overlay for efficient mouse interaction with slices
    // this should only be called after the initial drawPies() rendering
    // otherwise the overlay positioning will not align with the pies
    createMaps: function() {
        // create a map overlay and map for each pie
        for (var i=0; i<this.dataSets.length; i++) {
            var id = this.dataSets[i].id;
            var container = $('#'+id);

            // already created
            if ($('#'+id+'MapOverlay').length > 0) {
                continue;
            }

            var height = this.dataSets[i].height || this.options.defaultHeight;

            var overlay = $('<div id="'+id+'MapOverlay"/>')
                .css({ position: 'absolute',
                            top: $(container).position().top,
                            left: $(container).position().left,
                            'margin-top': $(container).css('margin-top'),
                            'margin-bottom': $(container).css('margin-bottom'),
                            'margin-right': $(container).css('margin-right'),
                            'margin-left': $(container).css('margin-left'),
                            height: height,
                            'z-index': 100 })
                .insertAfter(container);

            var blank = $('<img />')
                .attr('src', imageURL + '/developers/blank.gif')
                .attr('alt', '')
                .attr('width', height)
                .attr('height', height)
                .attr('border', 0)
                .attr('usemap', '#'+id+'ImageMap')
                .appendTo(overlay);

            // create imagemap
            $('<map id="'+id+'ImageMap" name="'+id+'ImageMap"/>').appendTo(overlay);
        }

        // add slice areas to all maps
        this.updateMaps();
    },

    // update the image map areas with the current slice data
    updateMaps: function() {
        var self = this; // used by event handlers

        for (var i=0; i<this.dataSets.length; i++) {
            // fetch existing map
            var pieMap = $('#'+this.dataSets[i].id+'ImageMap');

            if (pieMap.length !== 1) {
                // map has not been created - drawPies probably has not been called yet
                continue;
            }

            // clear existing map areas
            $('area', pieMap).remove();

            // height of pie canvas
            var height = this.dataSets[i].height || this.options.defaultHeight;

            // center coordinate of circle (and starting vertex for all slices)
            var center = Math.round(height/2)+','+Math.round(height/2);

            // since slice points will probably overlap after rounding to nearest pixel,
            // block out the pie center to avoid confusion
            $('<area />')
                .attr('alt', '')
                .attr('nohref', '')
                .attr('shape', 'circle')
                .attr('coords', center+',5')
                .appendTo(pieMap);

            // length of each segment for slice arcs (in radians)
            var tdelta = this.degreesToRadians(2);

            // create a polygon area for each slice
            for (var j=0; j<this.sliceMeta[i].length; j++) {
                var meta = this.sliceMeta[i][j];

                // no slice for you
                if (meta.percent <= 0) {
                    continue;
                }

                // slice path starts at the center
                var coords = center;
                var point;

                // approximate slice arc with small line segments
                for (var t = meta.radStart; t < meta.radStop; t += tdelta) {
                    point = this.polarToCartesian(t, height/2, height/2);
                    coords += ','+point[0].toFixed(0)+','+point[1].toFixed(0);
                }

                // end of arc
                point = this.polarToCartesian(meta.radStop, height/2, height/2);
                coords += ','+point[0].toFixed(0)+','+point[1].toFixed(0);


                // prepare area attributes
                // unencode slice label since setting attributes encodes entities 
                var label = '';
                var areaId = $(pieMap).attr('id')+'Area';
                if (typeof this.sliceOrder[j] == 'object') {
                    // 'others' slice
                    label = $('<div>'+this.options.otherLabel+'</div>').text();
                } else {
                    // colored slice
                    label = $('<div>'+this.labels[this.sliceOrder[j]]+'</div>').text();
                    areaId += this.sliceOrder[j]; // used by callbacks
                }

                // create the slice area
                $('<area />')
                    .attr('id', areaId)
                    .attr('alt', label+' '+meta.percent.toFixed(1)+'%')
                    .attr('title', label+' '+meta.percent.toFixed(1)+'%')
                    .attr('href', '#')
                    .attr('shape', 'poly')
                    .attr('coords', coords)
                    .appendTo(pieMap)
                    .mouseenter(function(e) { self.highlightSlices(e, this, self); })
                    .mouseleave(function(e) { self.clearHighlights(e, this, self); })
                    .click(function(e) { self.legendClick(e, this, self); return false; });
            }
        }
    },

    // unselect all items (100% others)
    noSlices: function(redraw) {
        if (arguments.length < 1) redraw = true;
        this.showSlices(0, -1, redraw);
    },

    // select all, none, or top N items from a dataset
    // @param idOrIndex dataset specifier (first dataset is the default)
    // @param topN number of items to select, -1 for none, 0 for all (default)
    // @param redraw redraw pies after calculations (default true)
    showSlices: function(idOrIndex, topN, redraw) {
        if (arguments.length < 1) idOrIndex = 0;
        if (arguments.length < 2) topN = 0;
        if (arguments.length < 3) redraw = true;

        var dataIdx = idOrIndex;

        if (typeof idOrIndex === 'string') {
            dataIdx = -1;
            for (var i=0; i<this.dataSets.length; i++) {
                if (this.dataSets[i].id === idOrIndex) {
                    dataIdx = i;
                    break;
                }
            }
        }

        if (this.dataSets[dataIdx] === undefined) {
            return; 
        }

        // reset colors
        this.pieColors = this.options.pieColors.slice();

        // sort dataset descending, keeping track of indices
        var dataSorted = this.dataSets[dataIdx].data.slice(); // create copy
        for (var i = 0; i<dataSorted.length; i++) {
            dataSorted[i] = [i, dataSorted[i]];
        }
        dataSorted.sort(function(a, b) { return (b[1] - a[1]) });

        // record slice ordering, colors, and data
        this.sliceOrder = [];
        this.sliceColors = [];
        this.sliceData = [];
        for (var i = 0; i<dataSorted.length; i++) {
            if ((topN == -1) || (topN && i === topN) || (dataSorted[i][1] <= 0)) {
                break;
            }
            this.sliceOrder.push(dataSorted[i][0]);
            this.sliceColors.push(this.nextColor());
        }

        // sparkline pie wont render without at least 2 datum
        // a zero value along with 'other' will meet this minimum
        this.sliceOrder.push(-1);
        this.sliceColors.push(this.options.otherColor);

        for (var otherOrder = []; i<dataSorted.length; i++) {
            otherOrder.push(dataSorted[i][0]);
        }
        this.sliceOrder.push(otherOrder);
        this.sliceColors.push(this.options.otherColor);

        // ordered data
        for (var i = 0; i<this.dataSets.length; i++) {
            var sliceData = [];

            // everything up to 'zero'
            for (var j = 0; j<this.sliceOrder.length-2; j++) {
                sliceData.push(this.dataSets[i].data[this.sliceOrder[j]]);
            }

            // zero value
            sliceData.push(0);
            j++;

            // sum 'others'
            var otherSum = 0;
            for (var k = 0; k<this.sliceOrder[j].length; k++) {
                otherSum += this.dataSets[i].data[this.sliceOrder[j][k]];
            }
            sliceData.push(otherSum);

            this.sliceData.push(sliceData);
        }

        this.updateSliceMeta();

        if (redraw) {
            this.drawPies();
        }
    },

    // calculate metadata for each pie slice
    updateSliceMeta: function() {
        this.sliceMeta = [];
        for (var i = 0; i<this.sliceData.length; i++) {
            var sliceMeta = [];

            // sum all for percentage and radian calculations
            sliceMeta.total = 0;
            for (var j = 0; j<this.sliceData[i].length; j++) {
                sliceMeta.total += this.sliceData[i][j];
            }

            var height = this.dataSets[i].height || this.options.defaultHeight;
            var radius = height / 2;

            var circle = this.degreesToRadians(360);
            var next = 0 + this.degreesToRadians(this.options.offset);
            for (var j = 0; j<this.sliceData[i].length; j++) {
                var meta = { percent: sliceMeta.total && 100 * this.sliceData[i][j] / sliceMeta.total };

                meta.radStart = next;
                next += circle*meta.percent/100;
                meta.radStop = next;
                meta.arcMidCoord = this.sliceArcMid(meta.radStart, meta.radStop, 0.8*radius, radius);

                sliceMeta.push(meta);
            }

            this.sliceMeta.push(sliceMeta)
        }
    },

    // convert degrees to radians
    degreesToRadians: function(deg) { return 2.0 * Math.PI * deg / 360.0; },

    // convert polar coordinates to cartesian, applying an optional offset translation
    polarToCartesian: function(theta, radius, offset) {
        offset = offset || 0;
        return [radius * Math.cos(theta) + offset, radius * Math.sin(theta) + offset];
    },

    // return cartesian coordinates of a pie slice arc midpoint (upper left origin)
    sliceArcMid: function(startRadians, endRadians, radius, offset) {
        offset = offset || radius;
        var theta = (startRadians + endRadians) / 2.0;
        return {x: radius * Math.cos(theta) + offset, y: radius * Math.sin(theta) + offset};
    },

    // color generator - cycles through all colors specified in options
    nextColor: function() {
        var c = this.pieColors.shift();
        this.pieColors.push(c);
        return c;
    },

    // move the specified color to the front of the cycle
    pushColor: function(color) {
        var i = $.inArray(color, this.pieColors);
        if (i > 0) {
            // move color to front of array (if not already there)
            this.pieColors.splice(i, 1);
            this.pieColors.unshift(color);
        }
    },

    // label a slice
    addSliceLabel: function(labelId, text, leftX, topY, container) {
        $('<div id="'+labelId+'" class="sliceLabel">'+text+'</div>').css({
            position: 'absolute',
            left: leftX.toFixed(0)+'px',
            'top': topY.toFixed(0)+'px',
            padding: '0.1em',
            'background-color': 'rgba(225,225,225,0.4)'
        }).appendTo(container);
    },

    // remove all slice labels
    clearSliceLabels: function() {
        for (var i=0; i<this.dataSets.length; i++) {
            $('#'+this.dataSets[i].id+' div.sliceLabel').remove();
        }
    },

    // remove, or recreate slice labels
    toggleSliceLabels: function() {
        this.options.labelSlices = ! this.options.labelSlices;
        this.labelSlices();
    },

    // label all slices
    labelSlices: function() {
        this.clearSliceLabels();
        if (! this.options.labelSlices) {
            return;
        }

        for (var i=0; i<this.dataSets.length; i++) {
            // container must be relative for label positioning to work
            $('#'+this.dataSets[i].id).css({position:'relative'});

            // create a dummy label offscreen to get its measurements
            this.addSliceLabel('dummySliceLabel', '0.0%', -5000, -5000, '#'+this.dataSets[i].id);
            var labelHeight = $('#dummySliceLabel').outerHeight();
            var labelWidth = $('#dummySliceLabel').outerWidth();
            $('#dummySliceLabel').remove();

            var selId = this.dataSets[i].id;
            var height = this.dataSets[i].height || this.options.defaultHeight;

            if (this.sliceMeta[i].total <= 0) {
                var leftX = height/2 - labelWidth/2;
                var topY = height/2 - labelHeight/2;
                this.addSliceLabel(selId+'SliceLabelEmpty', this.options.emptyLabel,
                                leftX, topY, '#'+selId);
            }

            for (var j=0; j<this.sliceMeta[i].length; j++) {
                if (this.sliceMeta[i][j].percent <= 0) {
                    continue;
                }
                var leftX = this.sliceMeta[i][j].arcMidCoord.x - labelWidth/2;
                var topY = this.sliceMeta[i][j].arcMidCoord.y - labelHeight/2;
                this.addSliceLabel(selId+'SliceLabel'+j, this.sliceMeta[i][j].percent.toFixed(1)+'%',
                                leftX, topY, '#'+selId);
            }
        }
    },

    // render pie charts and update all embellishments
    drawPies: function() {
        this.recolorLegend();
        for (var i=0; i<this.dataSets.length; i++) {
            $('#'+this.dataSets[i].id).sparkline(this.sliceData[i], {
                type:'pie',
                height:this.dataSets[i].height || this.options.defaultHeight,
                offset:this.options.offset,
                sliceColors:this.sliceColors
            });
        }
        this.labelSlices();
        this.createMaps();
    }
};
