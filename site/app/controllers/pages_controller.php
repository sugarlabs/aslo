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
 *   Frederic Wenzel <fwenzel@mozilla.com> (Original Author)
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

/**
 * This Pages controller is largely identical to the cake one, but it
 * had to be copied in order to load additional helpers.
 */
class PagesController extends AppController{

    var $name = 'Pages';
    var $helpers = array('Html', 'Localization', 'InstallButton');
    var $components = array('Image', 'Pagination');
    var $uses = array('Addon', 'Collection', 'File', 'Platform', 'User');

    var $securityLevel = 'low';

    function beforeFilter() {
        // Disable ACLs because this controller is entirely public.
        $this->SimpleAuth->enabled = false;
        $this->SimpleAcl->enabled = false;
    }

/**
 * Displays a view
 *
 */
    function display() {
        if (!func_num_args()) {
            $this->redirect('/');
        }

        $path = func_get_args();
        $path_string = join('/', $path);

        if (!count($path) || ($path[0] == 'home')) {
            $this->redirect('/');
        }

        $count  =count($path);
        $page   =null;
        $subpage=null;
        $title  =null;

        if (!empty($path[0])) {
            $page = $path[0];
        }

        if (!empty($path[1])) {
            $subpage = $path[1];
        }

        // Adding a redirect here because putting it in routes.php would serve up the right content but
        // not actually redirect
        if ($page=='policy') {
            $this->redirect('/developers/docs/policies/', 301);
            exit;
        }

        // settings titles for individual pages
        switch ($path_string) {
        case 'appversions':
            $title = ___('Application Versions'); break;
        case 'credits':
            $title = ___('Credits', 'page_title_credits'); break;
        case 'fashionyourfirefox_faq':
            $title = ___('Fashion your Firefox FAQ');
            $this->set('cssAdd', array('collection-style'));
            $this->publish('suppressHeader', true, false);
            $this->publish('suppressLanguageSelector', true, false);
            $this->publish('suppressCredits', true, false);
            break;
        case 'privacy':
            $title = ___('Mozilla Privacy Policy'); break;
        case 'review_guide':
            $title = ___('Review Guidelines'); break;
        case 'sandbox':
            $title = ___('Sandbox Review System'); break;
        case 'submissionhelp':
            $title = ___('Submission Help', 'page_title_submissionhelp'); break;
        case 'faq':
            $title = ___('Frequently Asked Questions', 'page_title_faq'); break;
        case 'developer_faq':
            $title = ___('Developer FAQ'); break;
        case 'developer_agreement':
            $title = ___('Developer Agreement'); break;
        case 'collector':
            $title = ___('Add-on Collector', 'page_title_collector');
            $this->publish('collectionSearch', true); break;
        case 'collector_faq':
            $title = ___('Add-on Collector FAQ');
            $this->publish('collectionSearch', true); break;
        case 'collector_features':
            $title = ___('Add-on Collector Features');
            $this->publish('collectionSearch', true); break;
        case 'collector_firstrun':
            $this->set('jsAdd', array('amo2009/collections'));
            $title = ___('Welcome to the Add-on Collector');
            $this->publish('collectionSearch', true); break;
        default:
            if (!empty($path[$count - 1])) {
                $title = ucfirst($path[$count - 1]);
            }
            break;
        }

        if (preg_match('|\.js$|', $path_string))
        {
            DboAmoMysql::$disable_log = true;
        }
        $this->publish('page', $page);
        $this->publish('subpage', $subpage);
        $this->set('title', $title .' :: '.sprintf(___('Add-ons for %1$s'), APP_PRETTYNAME));
        $this->render($path_string);
    }
}
?>
