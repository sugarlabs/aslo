<?php
/* This is a special stylesheet for use with Facebook's FBML. It should not be
  included on any other page. */

include_once('../../config/config.php');
?>
<!--
    Due to Facebook bug, we can only use classes - not ids.
    Due to Facebook bug, only one url() can be referenced in a single style tag.
    Due to Facebook bug, I could not enclose "style" in the previous line.
    Due to Facebook bug, the first style is a dummy so that background URLs are not mismatched.
-->
<style>
    .dummy {
        background: url('<?=FB_IMAGE_SITE?>/img/facebook/new_media_button_active.gif');
    }
    
    /* Browse/search results */
    .sort_box {
        background-color: #f7f7f7;
        padding: 5px 15px;
    }
    .filter_tab_bar {
        padding-left: 10px;
        border-top: 1px solid #d8dfea;
    }
    .filter_tab_bar div {
        padding: 0;
    }
    .filter_tab_bar div ul.tabs {
        border-left: 1px solid #d8dfea;
        border-bottom: 0px;
        float: left; 
        list-style: none;
        margin: 0px;
        padding: 0px;
    }
    .filter_tab_bar div ul.tabs li {
        border-right: 1px solid #d8dfea; 
        float: left;
        margin: 0px;
        padding: 3px 0px 4px 0px;
    } 
    .filter_tab_bar div ul.tabs li a {
        border-bottom: 2px solid white; 
        padding: 3px 6px 2px 6px; 
        font-weight: normal;
    }
    .filter_tab_bar div ul.tabs li.current a {
        border-bottom: 2px solid #6d84b4;
        padding: 3px 6px 3px 6px;
        font-weight: bold;
    } 
    .filter_tab_bar div ul.tabs li a:hover {
        background: #ebeff4;
        border-bottom: 1px solid #ebeff4;
        padding-bottom: 3px;
        text-decoration: none;
    }
    .filter_tab_bar div ul.tabs li.current a:hover {
        background: none;
        border-bottom: 2px solid #6d84b4; 
        color: #3b5998;
        padding: 3px 6px 3px 6px;
    }
    .filter_tab_bar div ul.tabs li.empty {
        padding: 3px 6px 3px 6px; 
        color: #666666;
    }
    .filter_tab_bar div ul.tabs li.empty a:hover {
        background: none;
        border: none;
    }
    .noresults {
        padding: 25px 0;
        text-align: center;
        color: gray;
    }

    /* Add-on view profile page */
    .profile {
        margin: 0px;
        padding: 0px 10px 0px;
    }
    .profile .left {
        padding: 0px;
        width: 415px;
    }
    .profile .right {
        padding: 0px 0px 0px 10px;
        width: 200px;
    }
    .header {
        margin: 0px;
        padding: 2px 8px;
        background: #D8DFEA;
        border-top: solid 1px #3B5998;
    }
    .header h2 {
        font-size: 11px;
        font-weight: bold;
        color: #3B5998;
    }
    .red .header {
        background: #FFCCCC;
        border-top: solid 1px #990000;
    }
    .red .header h2,
    .red .header a {
        color: #990000;
    }
    .profile .box {
        margin: 0px;
        padding: 10px 8px 15px 8px;
        overflow: visible;
    }
    .profile .box h4 {
        margin-top: 1px;
        margin-left: 0px;
        margin-bottom: 0;
        padding-left: 0px;
    }
    .profile .darklink {
        color: #555;
    }
    .profile .fallback {
        background: #f7f7f7;
        border-top: solid 1px #ccc;
        border-bottom: solid 1px #D8DFEA;
        color: gray;
        padding: 15px 0px 15px 0px; 
        text-align: center;
    }
    .profile .people_table {
        margin: 0px;
        padding: 0px;
    }
    .profile .people_table td {
        width: 62px;
        vertical-align: middle;
        text-align: center;
        padding: 0px 0px 5px;
    }
    .profile .people_table table {
        height: 100%;
    }
    .profile .people_table table td.image {
        padding-bottom: 0px;
        font-size: 1px;
        line-height: 1px;
    }
    .profile .people_table table td.name div {
        width: 60px;
    }
    .profile .see_all {
        margin: 5px 0px 0px;
    }
    .box_subhead {
        color: #444; 
        padding: 2px 8px 2px 8px;
        border-top: solid 1px #ccc;
        overflow: hidden;
        background: #eee;
    }
    .box_subhead .box_subtitle {
        float: left;
    }
    .box_subhead .box_actions {
        float: right;
    }
    .box_nopeople {
        color: #444; 
        padding: 20px 0;
        border-top: solid 1px #ccc;
        overflow: hidden;
        background: #eee;
        text-align: center;
    }
</style>
<style type="text/css">
    /* Add-on profile gray bar and add to favorite button */
    .grayheader .dh_new_media_shell {
        float: right; 
        background: url('<?=FB_IMAGE_SITE?>/img/facebook/new_media_button_active.gif') no-repeat bottom;
        margin: 7px 0px 0px;
    }
</style>
<style type="text/css">
    .grayheader .dh_new_media {
        float: left;
        display: block;
        color: #777;
        text-decoration: none;
        background: url('<?=FB_IMAGE_SITE?>/img/facebook/new_media_button.gif') no-repeat;
    }
</style>
<style type="text/css">
    .grayheader .dh_new_media .tr {
        background: url('<?=FB_IMAGE_SITE?>/img/facebook/new_media_button.gif') no-repeat top right;
    }
</style>
<style type="text/css">
    .grayheader .dh_new_media .bl {
        background: url('<?=FB_IMAGE_SITE?>/img/facebook/new_media_button.gif') no-repeat bottom left;
    }
</style>
<style type="text/css">
    .grayheader .dh_new_media .br {
        background: url('<?=FB_IMAGE_SITE?>/img/facebook/new_media_button.gif') no-repeat bottom right;
    }
</style>
<style type="text/css">
    .grayheader .dh_new_media span {
        color: #333;
        font-size: 11px;
        font-weight: bold; 
        display: block;
        padding: 3px 9px 5px 22px;
        text-shadow: white 0px 1px 1px;
        background: url('<?=FB_IMAGE_SITE?>/img/facebook/new_media_button_plus.gif') no-repeat 9px center;
    }
</style>
<style type="text/css">
    .grayheader .remove {
        background: url('<?=FB_IMAGE_SITE?>/img/facebook/icon_remove.gif') no-repeat;
        padding-left: 15px;
    }
</style>
<style type="text/css">
    .grayheader .dh_new_media:hover {
        text-decoration: underline;
    }
    .grayheader .dh_new_media:active,
    .grayheader .dh_new_media:active .tr,
    .grayheader .dh_new_media:active .bl,
    .grayheader .dh_new_media:active .br {
        background-image: url('<?=FB_IMAGE_SITE?>/img/facebook/new_media_button_active.gif');
    }
    
    /* profile action buttons */
    .profileActions {
        margin: 0px;
        padding: 10px 0px;
        background: white;
        z-index: 999;
    }
    .profileActions a {
        border-bottom: 1px solid #D8DFEA;
        display: block;
        width: 187px;
        margin: 0px;
        padding: 2px 3px 2px 9px;
    }
    .profileActions a:hover {
        color: white;
        background: #3B5998;
        text-decoration: none;
    }
</style>
<style type="text/css">
    /* install now button */
    .confirm_button a {
        float: left;
        display: block;
        background: url('<?=FB_IMAGE_SITE?>/img/facebook/confirm_button.gif') no-repeat top left;
        font-size: 13px;
        font-weight: bold;
        color: #d8dfea;
        cursor: pointer;
    }
</style>
<style type="text/css">
    .installbox {
        padding: 10px;
        margin-left: 15%;
    }
    .confirm_button div {
        background: url('<?=FB_IMAGE_SITE?>/img/facebook/confirm_button.gif') no-repeat top right;
    }
</style>
<style type="text/css">
    .confirm_button div div {
        background-position: bottom left;
    }
    .confirm_button div div div {
        background-position: bottom right;
        padding: 0px 0px 0px 10px;
    }
    .confirm_button span {
        display: block;
        padding: 4px 10px 5px 22px;
        background: url('<?=FB_IMAGE_SITE?>/img/facebook/confirm_button_extension.gif') no-repeat left center;
        color: #FFF;
    }
</style>
<style type="text/css">
    .confirm_button a:active,
    .confirm_button a:active div {
        background-image: url('<?=FB_IMAGE_SITE?>/img/facebook/confirm_button_active.gif');
    }
</style>
<style type="text/css">
    /* Favorites Page */
    .subtabs {
        display: block;
        margin: 0px 0px 0px -7px;
        padding: 10px 25px 0px 25px;
        border-bottom: solid 1px #ccc;
    }
    .subtabs .tab {
        margin: 0px 7px -1px 0px;
        border: solid 1px #ccc;
        border-bottom: solid 1px #ffffff;
        z-index: 10;
        position: relative;
        padding: 5px 7px 4px;
        color: #555555;
        float: left;
        line-height: 13px;
        font-size: 13px;
        font-weight: bold;
    }
    .subtabs .tab a {
        color: #555555;
    }
    .subtabs .unselected {
        border: solid 1px #dedede;
        border-bottom: solid 1px #ccc;
        color: #888888;
        font-size: 11px;
    }
    .subtabs .unselected a {
        color: #888888;
    }
    .subtabs .unselected:hover {
        cursor: pointer;
        color: #3B5998;
        text-decoration: underline;
    }
    table.myfavorites {
        width: 100%;
    }
    table.myfavorites td {
        padding-top: 7px;
        padding-bottom: 7px;
        vertical-align: middle;
        border-bottom: solid 1px #ddd;
    }
    table.myfavorites td.info {
        margin: 0px;
        padding: 0px 20px 0px 5px;
        width: 50%;
        font-size: 13px;
        font-weight: bold;
        color: #555;
    }
    table.myfavorites td.imported {
        width: 30%;
        color: gray;
    }
    table.myfavorites td.remove {
        width: 20%;
    }
    .favorites .remove a {
        background: url('<?=FB_IMAGE_SITE?>/img/facebook/icon_remove.gif') no-repeat;
        padding-left: 15px;
    }
    .favorites div.nofavorites {
        text-align: center;
        padding: 30px 0;
        color: gray;
        font-size: 13px;
    }
</style>
<style>
    /* Layout */
    body {
        font-family: verdana, sans-serif;
    }
    .content {
        min-height: 300px;
    }
    .page-header {
        margin: 0px;
        height: 80px;
    }
    .search {
        float: right;
        padding-right: 15px;
        color:#555;
    }
    .search input {
        font-size: 11px;
        width: 100px;
        padding-left: 17px;
        margin-left: 2px;
    }
    .tabs {
        margin-top: 5px;
    }
    .page-footer {
        border-top: 1px solid #3b5998;
        padding: 5px;
        color: #666666;
        text-align: center;
        clear: both;
    }
    .page-footer a {
        color: #333333;
    }
</style>
