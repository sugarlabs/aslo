var addons;
var currentSort;
var currentAddon;

$(document).ready(function(){
    $("#TagId1").change(function(){ loadAddons(null, null); });
    $("#TagId2").change(function(){ loadAddons(null, null); });
    $("#AddontypeId").change(function(){ changeTags(); loadAddons(null, null); });
});
    
function loadAddons(caption, sort, url_tail){
    $("#loading").show();
    $("#pagenavigation").hide();
    $(".browselist > li").removeClass('selected');
    $("#" + sort).addClass('selected');
    
    if (sort == null)
        sort = currentSort;
    else
        currentSort = sort;
        
    if (url_tail == null)
        url_tail = '';
        
    var url = base_url + 'sort:' + sort + '/type:' + $("#AddontypeId").val() + '/cat:' + $("#TagId" + $("#AddontypeId").val()).val() + url_tail;
    //alert(url);
    $("#addonbox_title").html(caption);

    var req = $.getJSON(url, null, function(json){ populateAddons(json); });
    
    //setTimeout(function(){alert(req.responseText);}, 5000);
}

function search() {
    alert('Search doesn\'t work yet, k!');
}

function populateAddons(json) {
    $("#loading").hide();
    $("#addonlist").empty();
    
    if (json.noresults != 'true') {
        $.each(json.addons, function(i, n){
            var item = '<div class="addon"><div class="name">';
            item += '<a href="#" onClick="addonDetails(' + n.id + ');">' + n.name + '</a>';
            if (n.favorite == 'true') {
                item += '&nbsp;<img src="../../../img/smallMedal.png" title="Favorite Add-on">';
            }
            if (n.friend != null) {
                item += ' (' + n.friend + ')';
            }
            item += '</div>';
            item += '<div class="summary">' + n.summary + '</div></div>';
            
            $(item).appendTo("#addonlist");
            
            $("#pagenavigation").show();
        });
        
        addons = json.addons;
        if (json.query_url != null) {
            $("#moreresults").show();
            $("#moreresults").attr('href', json.query_url);
        }
        else {
            $("#moreresults").hide();
        }
    }
    else {
        $("#addonlist").html('No add-ons found. Please try different criteria.');
    }
}

function changeTags() {
    $("#TagId" + $("#AddontypeId").val()).css('display', '');
    $("#TagId" + (3 - $("#AddontypeId").val())).css('display', 'none');
}

function addonDetails(id) {
    $("#fav_loading").hide();
    if ($("#addonwindow").css('display') == 'none') {
        $("#addonwindow").slideDown();
    }
    currentAddon = id;
    
    $("#addonwindow > .name > .name").html(addons[id].name);
    $("#addonwindow > .summary").html(addons[id].summary);
    $("#addonwindow > .details > .version").html(addons[id].version);
    $("#addonwindow > .details > .released").html(addons[id].released_pretty);
    $("#addonpreview").attr('src', addons[id].preview_url);
    $("#addoninstall_link").attr('href', addons[id].display_url);
    $("#addonwindow > .details > .compat_versions").html(addons[id].apps[1].min + ' - ' + addons[id].apps[1].max);
    
    var authors = '';
    var count = 0;
    $.each(addons[id].authors, function (i, n){
        if (count != 0)
            authors += ', ';
        authors += n.firstname + ' ' + n.lastname;
        count++;
    });
    $("#addonwindow > .authors > .authors").html(authors);
    
    if (addons[id].favorite == 'true') {
        $("#add_fav").hide();
        $("#remove_fav").show();
        $("#fav_medal").show();
    }
    else {
        $("#add_fav").show();
        $("#remove_fav").hide();
        $("#fav_medal").hide();
    }
}

function hideAddonDetails() {
    $("#addonwindow").slideUp();
}

function addFavorite(url) {
    $("#fav_loading").show();
    url = url + currentAddon;
    
    $.getJSON(url, null, function(json) {
            $("#fav_loading").hide();
            if (json.result == 'success') {
                $("#add_fav").hide();
                $("#remove_fav").show();
                $("#fav_medal").show();
            }
            else {
                alert(json.error);
            }
        });
}

function removeFavorite(url) {
    $("#fav_loading").show();
    url = url + currentAddon;
    
    $.getJSON(url, null, function(json) {
            $("#fav_loading").hide();
            if (json.result == 'success') {
                $("#remove_fav").hide();
                $("#add_fav").show();
                $("#fav_medal").hide();
            }
            else {
                alert(json.error);
            }
        });
}