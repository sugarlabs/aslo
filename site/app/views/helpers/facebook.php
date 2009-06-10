<?php
/* ***** BEGIN LICENSE BLOCK *****
 * Version: MPL 1.1/GPL 2.0/LGPL 2.1
 *
 * The contents of this file are subject to the Mozilla Public License Version
 * 1.1 (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
 * for the specific language governing rights and limitations under the
 * License.
 *
 * The Original Code is addons.mozilla.org site.
 *
 * The Initial Developer of the Original Code is
 * The Mozilla Foundation.
 * Portions created by the Initial Developer are Copyright (C) 2007
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Justin Scott <fligtar@mozilla.com> (Original Author)
 *
 * Alternatively, the contents of this file may be used under the terms of
 * either the GNU General Public License Version 2 or later (the "GPL"), or
 * the GNU Lesser General Public License Version 2.1 or later (the "LGPL"),
 * in which case the provisions of the GPL or the LGPL are applicable instead
 * of those above. If you wish to allow use of your version of this file only
 * under the terms of either the GPL or the LGPL, and not to allow others to
 * use your version of this file under the terms of the MPL, indicate your
 * decision by deleting the provisions above and replace them with the notice
 * and other provisions required by the GPL or the LGPL. If you do not delete
 * the provisions above, a recipient may use your version of this file under
 * the terms of any one of the MPL, the GPL or the LGPL.
 *
 * ***** END LICENSE BLOCK ***** */

class FacebookHelper extends Helper {
    var $helpers = array('Html');
    
    var $globalURLAdditional = '';
    var $pagesString = '';

    function listAddons($addons) {
        echo '<div class="results" style="background-color: #f7f7f7; padding: 10px;">';
        
        if (!empty($addons)) {
            foreach ($addons as $addon) {
                echo '<div class="resultitem" style="border: 1px solid #cccccc; background-color: white; padding: 5px; height: 75px; margin-bottom: 5px;">';
                    echo '<a href="'.FB_URL.'/view/'.$addon['Addon']['id'].'">';
                    if ($addon['previewCount'] > 0)
                        echo '<img src="'.FB_IMAGE_SITE.'/images/addon_preview/'.$addon['Addon']['id'].'/1" width=100 height=75 border=0 style="float: left; padding-right: 5px;" alt="'.htmlentities($addon['Translation']['name']['string']).'">';
                    else
                        echo '<img src="'.FB_IMAGE_SITE.'/img/facebook/no-preview-thumb.png" width=100 height=75 border=0 style="float: left; padding-right: 5px;" alt="'.htmlentities($addon['Translation']['name']['string']).'">';
                    echo '</a>';
                    echo '<div>';
                        echo '<div style="font-weight: bold; font-size: 120%;">';
                        if ($this->view->controller->FacebookFavorite->isFavorite($this->view->controller->fbUser, $addon['Addon']['id'], true)) {
                            echo '<img src="'.FB_IMAGE_SITE.'/img/facebook/smallMedal.png" style="padding-right: 5px;" alt="You recommend this add-on" title="You recommend this add-on">';
                        }
                        echo '<a href="'.FB_URL.'/view/'.$addon['Addon']['id'].'">'.$addon['Translation']['name']['string'].'</a></div>';
                        echo '<div>'.$this->view->controller->_trimSummary($addon['Translation']['summary']['string']).'</div>';
                    if (!empty($addon['FacebookFavorite'])) {
                        $users = array();
                        if (count($addon['FacebookFavorite']) > 3) {
                            $users = array('<a href="'.FB_URL.'/favorites/addon/'.$addon['Addon']['id'].'">'.count($addon['FacebookFavorite']).' friends</a>');
                        }
                        else {
                            foreach ($addon['FacebookFavorite'] as $favUser) {
                                $users[] = '<a href="'.FB_URL.'/favorites/user/'.$favUser['FacebookFavorite']['fb_user'].'"><fb:name uid="'.$favUser['FacebookFavorite']['fb_user'].'" shownetwork="false" linked="false" /></a>';
                            }
                        }
                        echo '<div style="padding-top: 5px;"><span style="color: gray;">Recommended by:</span> '.implode(', ', $users).'</div>';
                    }
                    echo '</div>';
                echo '</div>';
            }
        }
        else {
            echo '<div class="noresults">No add-ons found. Please try different criteria.</div>';    
        }
        
        echo '</div>';
    }
    
    function listAddonsOfFriends($addons) {
        echo '<div class="results" style="background-color: #f7f7f7; padding: 10px;">';
        
        if (!empty($addons)) {
            foreach ($addons as $addon) {
                echo '<div class="resultitem" style="border: 1px solid #cccccc; background-color: white; padding: 5px; height: 75px; margin-bottom: 5px;">';
                    echo '<a href="'.FB_URL.'/view/'.$addon['addons']['id'].'">';
                    if ($addon[0]['pcount'] > 0)
                        echo '<img src="'.FB_IMAGE_SITE.'/images/addon_preview/'.$addon['addons']['id'].'/1" width=100 height=75 border=0 style="float: left; padding-right: 5px;" alt="'.htmlentities($addon['translations_name']['name']).'">';
                    else
                        echo '<img src="'.FB_IMAGE_SITE.'/img/facebook/no-preview-thumb.png" width=100 height=75 border=0 style="float: left; padding-right: 5px;" alt="'.htmlentities($addon['translations_name']['name']).'">';
                    echo '</a>';
                    echo '<div>';
                        echo '<div style="font-weight: bold; font-size: 120%;">';
                        if ($this->view->controller->FacebookFavorite->isFavorite($this->view->controller->fbUser, $addon['addons']['id'], true)) {
                            echo '<img src="'.FB_IMAGE_SITE.'/img/facebook/smallMedal.png" style="padding-right: 5px;" alt="You recommend this add-on" title="You recommend this add-on">';
                        }
                        echo '<a href="'.FB_URL.'/view/'.$addon['addons']['id'].'">'.$addon['translations_name']['name'].'</a></div>';
                        echo '<div>'.$this->view->controller->_trimSummary($addon['translations_summary']['summary']).'</div>';
                    if (!empty($addon[0]['friends'])) {
                        $users = array();
                        if ($addon[0]['fcount'] > 3) {
                            $users = array('<a href="'.FB_URL.'/favorites/addon/'.$addon['addons']['id'].'/friends">'.$addon[0]['fcount'].' friends</a>');
                        }
                        else {
                            $favUsers = explode(',', $addon[0]['friends']);
                            foreach ($favUsers as $favUser) {
                                $users[] = '<fb:userlink uid="'.$favUser.'" shownetwork="false" />';
                            }
                        }
                        echo '<div style="padding-top: 5px;"><span style="color: gray;">Recommended by:</span> '.implode(', ', $users).'</div>';
                    }
                    echo '</div>';
                echo '</div>';
            }
        }
        else {
            echo '<div class="noresults">No add-ons found. Please try different criteria.</div>';    
        }
        
        echo '</div>';
    }
    
    function pageNumbers($current, $count, $action = 'browse') {
        if ($count['pages'] < 2) {
            return;
        }
        
        echo '<ul class="pagerpro" id="pag_nav_links">';
        
        if (empty($this->pagesString)) {
            $pagesString = '';
            $pagenumber = '<li><a href="'.$this->buildURL($action, $current, 'page', 'page:%1$s').'">%1$s</a></li>';
            if ($current['page'] != 1)
                $pagesString .= '<li><a href="'.$this->buildURL($action, $current, 'page', 'page:'.($current['page'] - 1)).'">Prev</a></li>';
            if ($current['page'] == $count['pages'] && $count['pages'] >= 5)
                $pagesString .= sprintf($pagenumber, $current['page'] - 4);
            if (($count['pages'] - $current['page']) <= 1 && ($count['pages'] - $current['page']) >= 0 && $count['pages'] >= 5)
                $pagesString .= sprintf($pagenumber, $current['page'] - 3);
            if ($current['page'] > 2) 
                $pagesString .= sprintf($pagenumber, $current['page'] - 2);
            if ($current['page'] > 1)
                $pagesString .= sprintf($pagenumber, $current['page'] - 1);
            if ($count['pages'] > 0)
                $pagesString .= '<li class="current"><a href="'.$this->buildURL($action, $current).'">'.$current['page'].'</a></li>';
            if (($current['page'] + 1) <= $count['pages'])
                $pagesString .= sprintf($pagenumber, $current['page'] + 1);
            if (($current['page'] + 2) <= $count['pages'])
                $pagesString .= sprintf($pagenumber, $current['page'] + 2);
            if ($current['page'] <= 2 && $count['pages'] >= ($current['page'] + 3))
                $pagesString .= sprintf($pagenumber, $current['page'] + 3);
            if ($current['page'] == 1 && $count['pages'] >= 5)
                $pagesString .= sprintf($pagenumber, $current['page'] + 4);
            if ($current['page'] < $count['pages'])
                $pagesString .= '<li><a href="'.$this->buildURL($action, $current, 'page', 'page:'.($current['page'] + 1)).'">Next</a></li>';
            
            $this->pagesString = $pagesString;
        }
        
        echo $this->pagesString;
        echo '</ul>';
    }
    
    function buildURL($action, $current, $excludedParams = '', $additional = '') {
        if (!is_array($excludedParams)) {
            if (empty($excludedParams))
                $excludedParams = array();
            else
                $excludedParams = array($excludedParams);
        }
            
        $url = FB_URL.'/'.$action;
        
        $params = array('sort', 'cat', 'type', 'page');
        
        foreach ($params as $param) {
            if (!empty($current[$param]) && !in_array($param, $excludedParams)) {
                $url .= "/{$param}:{$current[$param]}";
            }
        }
        
        if (!empty($additional)) {
            $url .= "/{$additional}";
        }
        if (!empty($this->globalURLAdditional)) {
            $url .= "/{$this->globalURLAdditional}";
        }
        
        return $url;
    }
    
    function peopleBox($facebook_users, $width = 200, $rows = 1, $params = array()) {
        $perRow = floor($width / 70);
        $totalShown = $perRow * $rows;
        
        echo '<div style="width: '.$width.'px;">';
        echo '<div class="header"><h2>'.(!empty($params['title']) ? $params['title'] : 'Recommended By').'</h2></div>';
        
        if (!empty($facebook_users)) {
            echo '<div class="box_subhead">';
                echo '<div class="box_subtitle">';
                    if (!empty($params['showAllCount']) && $params['showAllCount'] == true) {
                        if (!empty($params['viewAllURL']))
                            echo '<a href="'.$params['viewAllURL'].'">';
                        if ($totalShown < $params['allCount'])
                            echo $totalShown.' of ';
                        echo $params['allCount'].' '.($params['allCount'] == 1 ? 'person' : 'people');
                        if (!empty($params['viewAllURL']))
                            echo '</a>';
                        if (!empty($params['showFriendCount']) && $params['showFriendCount'] == true)
                            echo ', ';
                    }
                    if (!empty($params['showFriendCount']) && $params['showFriendCount'] == true) {
                        if (!empty($params['viewFriendURL']))
                            echo '<a href="'.$params['viewFriendURL'].'">';
                        if ($totalShown < $params['friendCount'])
                            echo $totalShown.' of ';
                        echo $params['friendCount'].' '.($params['friendCount'] == 1 ? 'friend' : 'friends');
                        if (!empty($params['viewFriendURL']))
                            echo '</a>';
                    }
                echo '</div>';
                if (!empty($params['seeAllURL']))
                    echo '<div class="box_actions"><a href="'.$params['seeAllURL'].'">See All</a></div>';
            echo '</div>';
            
            echo '<div class="guests clearfix">';
                echo '<table class="people_table" cellspacing="2" cellpadding="0">';
                $total = 0;
                for ($r = 0; $r < $rows; $r++) {
                    if (count($facebook_users) > $total) {
                        echo '<tr>';
                            for ($i = 0; $i < $perRow; $i++) {
                                if (!empty($facebook_users[$total])) {
                                    echo '<td style="text-align: center; width: 70px;"><fb:profile-pic uid="'.$facebook_users[$total].'" linked="yes"/><br><fb:if-can-see uid="'.$facebook_users[$total].'"><fb:userlink uid="'.$facebook_users[$total].'" /><fb:else>Anonymous</fb:else></fb:if-can-see></td>';
                                }
                                $total++;
                            }
                        echo '</tr>';
                    }
                }
                echo '</table>';
            echo '</div>';
        }
        else {
            echo '<div class="box_nopeople">No one yet. <a href="'.FB_URL.'/favorite/add/'.$params['addon_id'].'">Be the first!</a></div>';
        }
        echo '</div>';
    }

}
?>
