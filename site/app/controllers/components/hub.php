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
 * Portions created by the Initial Developer are Copyright (C) 2009
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *   Jeff Balogh <jbalogh@mozilla.com> (Original Author)
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



class HubComponent extends Object {

    function startup(&$controller) {
        $this->controller =& $controller;

        $mdc = new HubSite('Mozilla Developer Center', 'http://developer.mozilla.org');
        $wiki = new HubSite('Mozilla Wiki', 'http://wiki.mozilla.org');
        $jetpack = new HubSite('Mozilla Labs - Jetpack', 'http://jetpack.mozillalabs.com');
        $designchallenge = new HubSite('Mozilla Labs Design Challenge', 'http://design-challenge.mozilla.com');
        
        /**
         * If you add a new how-to, increment this number!
         *  Last used id: 48
         */
        $this->categories = array(
            new HubCategory('Getting Started', ___('Learn the basics of developing an extension on the Mozilla platform with this section full of beginner\'s guides.'), 'getting-started', array(
                new SubCategory('The Basics', array(
                    new Howto(1, 'Firefox Add-ons Developer Guide',
                              'https://developer.mozilla.org/En/Firefox_addons_developer_guide',
                              $mdc,
                              'In this detailed guide to extension development, you\'ll learn the basics of packaging extensions, building an interface with XUL, implementing advanced processes with XPCOM, and how to put it all together.',
                              'editorspick'),
                    new Howto(2, 'Setting Up an Extension Development Environment',
                              'https://developer.mozilla.org/en/Setting_up_extension_development_environment',
                              $mdc,
                              'This article gives suggestions on how to set up your Mozilla application for extension development, including setting up a profile, changing preferences, and helpful development tools.'),
                    new Howto(3, 'Extension Bootcamp: Zero to Hello World! in 45 Minutes',
                              'http://design-challenge.mozilla.com/resources/#extension_bootcamp',
                              $designchallenge,
                              'In this video tutorial, Myk Melez explains how extensions integrate into Firefox, what they can do, and shows you how to set up an environment to ease their development. He\'ll then walk you through the making of a simple "Hello World!" extension. By the end of this session, you\'ll be an extension developer.',
                              'video'),
                    new Howto(4, 'Building an Extension',
                              'https://developer.mozilla.org/en/Building_an_Extension',
                              $mdc,
                              'This tutorial will take you through the steps required to build a very basic extension - one which adds a status bar panel to the Firefox browser containing the text "Hello, World!"'),
                    new Howto(5, 'Extension Packaging',
                              'https://developer.mozilla.org/en/Extension_Packaging',
                              $mdc,
                              'Learn more details on how extensions are packaged and installed.'),
                    new Howto(6, 'Install Manifests',
                              'https://developer.mozilla.org/en/Install_Manifests',
                              $mdc,
                              'This document explains install manifests (install.rdf) and all of properties available for use in your add-on.')
                )),
                new SubCategory('Other Tutorials', array(
                    new Howto(7, 'How to Develop a Firefox Extension',
                              'http://robertnyman.com/2009/01/24/how-to-develop-a-firefox-extension/',
                              new HubSite('Robert\'s talk', 'http://www.robertnyman.com'),
                              'In this walk-through, Robert Nyman explains how to develop a Firefox extension from scratch.'),
                    new Howto(8, 'How to Build a Firefox Extension',
                              'http://lifehacker.com/264490/how-to-build-a-firefox-extension',
                              new HubSite('Lifehacker', 'http://www.lifehacker.com'),
                              'Lifehacker gives tips and helpful hints on developing your first Firefox extension.'),
                    new Howto(9, 'Firefox Extension Development Tutorial',
                              'http://www.rietta.com/firefox/Tutorial/overview.html',
                              new HubSite('Extend Firefox!', 'http://www.rietta.com/firefox/index.html'),
                              'A multi-page tutorial covering a variety of extension development topics.'),
                    new Howto(10, 'Creating a Status Bar Extension',
                              'https://developer.mozilla.org/en/Creating_a_status_bar_extension',
                              $mdc,
                              'Learn how to create a simple status bar extension and add more advanced functionality in subsequent tutorials, linked below.'),
                    new Howto(11, 'Creating a Dynamic Status Bar Extension',
                              'https://developer.mozilla.org/en/Creating_a_dynamic_status_bar_extension',
                              $mdc,
                              'This article modifies the status bar extension created in the above tutorial by fetching content from a website at a regular interval.'),
                    new Howto(12, 'Adding Preferences to an Extension',
                              'https://developer.mozilla.org/en/Adding_preferences_to_an_extension',
                              $mdc,
                              'This article shows how to add preferences to the dynamic status bar created in the above tutorial.'),
                )),
                new SubCategory('Books', array(
                    new Howto(13, 'Build Your Own Firefox Extension E-book',
                              'http://www.sitepoint.com/books/byofirefoxpdf1/',
                              new HubSite('SitePoint', 'http://www.sitepoint.com'),
                              'With a little JavaScript know-how, author James Edwards will show you just how straightforward it is to build your own Firefox extensions.')
                ))
            )),
            new HubCategory('Extension Development', ___('Once you know the basics, these guides will help you with intermediate to advanced extension development topics.'), 'extension-development', array(
                new SubCategory('Best Practices', array(
                    new Howto(14, 'Respecting the JavaScript Global Namespace',
                              'http://blogger.ziesemer.com/2007/10/respecting-javascript-global-namespace.html',
                              new HubSite('Mark A. Ziesemer', 'http://blogger.ziesemer.com'),
                              'In this blog post, Mark Ziesemer explains how to prevevent global namespace pollution by wrapping your extension\'s variables.'),
                    new Howto(15, 'Security Best Practices in Extensions',
                              'https://developer.mozilla.org/en/Security_best_practices_in_extensions',
                              $mdc,
                              'Every extension developer should know and follow the security best practices outlined in this document to keep users safe.',
                              'editorspick'),
                    new Howto(16, 'Localizing an Extension',
                              'https://developer.mozilla.org/en/Localizing_an_extension',
                              $mdc,
                              'This article explains how to localize an extension, including XUL and JavaScript strings.'),
                    new Howto(48, 'Responsible First-run Usage',
                              'http://blog.fligtar.com/2008/10/16/responsible-first-run-usage/',
                              new HubSite('fligtar.com', 'http://blog.fligtar.com'),
                              'This blog post describes what a bad first-run experience means for users, and gives tips for improving that experience.')
                )),
                new SubCategory('Security', array(
                    new Howto(17, 'Evaluating Code with Restricted Privileges',
                              'https://developer.mozilla.org/en/Components.utils.evalInSandbox',
                              $mdc,
                              'This article describes the use of Components.utils.evalInSandbox, which is a way to evaluate code (such as remote code) without chrome privileges.'),
                    new Howto(18, 'Creating Sandboxed HTTP Connections',
                              'https://developer.mozilla.org/En/Creating_Sandboxed_HTTP_Connections',
                              $mdc,
                              'This article explains how to created sandboxed HTTP connections which don\'t affect the user\'s cookies.'),
                    new Howto(19, 'Displaying Web Content in an Extension',
                              'https://developer.mozilla.org/En/Displaying_web_content_in_an_extension_without_security_issues',
                              $mdc,
                              'Learn how to display web content in an extension without security issues.'),
                    new Howto(20, 'Five Wrong Reasons to Use eval() in an Extension',
                              'http://adblockplus.org/blog/five-wrong-reasons-to-use-eval-in-an-extension',
                              new HubSite('Adblock Plus Blog', 'http://www.adblockplus.org'),
                              'In this blog post, Wladimir Palant gives five wrong ways to evaluate code in an extension.')
                )),
                new SubCategory('Localization', array(
                    new Howto(21, 'Localizing Extension Descriptions',
                              'https://developer.mozilla.org/en/Localizing_extension_descriptions',
                              $mdc,
                              'Learn how to localize the names and descriptions in extension install manifests.'),
                    new Howto(22, 'Localization and Plurals',
                              'https://developer.mozilla.org/en/Localization_and_Plurals',
                              $mdc,
                              'This article explains how to properly localize strings with plurals.')
                )),
                new SubCategory('Advanced Topics', array(
                    new Howto(23, 'Stupid/Awesome Extension Development Hacks',
                              'http://design-challenge.mozilla.com/resources/#extension_hacks',
                              $designchallenge,
                              'In this video, Jono Xia explains how to go further in extension development using XPCOM, overlays, XHRs, DOM manipulation, etc. in order to make Firefox do things you might have never thought possible.',
                              'video'),
                    new Howto(24, 'JavaScript Code Modules',
                              'https://developer.mozilla.org/en/JavaScript_code_modules',
                              $mdc,
                              'JavaScript code modules let multiple privileged JavaScript scopes share code. For example, a module could be used by Firefox itself as well as by extensions, in order to avoid code duplication.'),
                    new Howto(25, 'Creating Custom Firefox Extensions with the Mozilla Build System',
                              'https://developer.mozilla.org/en/Creating_Custom_Firefox_Extensions_with_the_Mozilla_Build_System',
                              $mdc,
                              'This article describes how to set up the development environment for a large, complex Firefox extension with a need for high-performance, use of third-party libraries in C/C++, or interfaces not exposed via XPCOM.'),
                    new Howto(26, 'Multiple Item Packaging',
                              'https://developer.mozilla.org/en/Multiple_Item_Packaging',
                              $mdc,
                              'This article explains how to create an extension package with multiple items (extensions).'),
                    new Howto(27, 'Extension Documentation Index',
                              'https://developer.mozilla.org/en/Extensions',
                              $mdc,
                              'If you can\'t find what you\'re looking for in the above articles, try the Mozilla Developer Center\'s Extensions landing page.')
                ))
            )),
            new HubCategory('Thunderbird & Mobile Add-ons', ___('Add-ons aren\'t just for Firefox. Learn how to extend other Mozilla applications, such as the Thunderbird mail client and Firefox for mobile devices.'), 'thunderbird-mobile', array(
                new SubCategory('Developing Add-ons for Thunderbird', array(
                    new Howto(28, 'Building a Thunderbird Extension',
                              'https://developer.mozilla.org/en/Building_a_Thunderbird_extension',
                              $mdc,
                              'This tutorial will take you through the steps required to build a very basic extension - one which adds a status bar panel to the Thunderbird Mail Client containing the text "Hello, World!"'),
                    new Howto(29, 'An Overview of Thunderbird Components',
                              'https://developer.mozilla.org/En/Extensions/Thunderbird/An_overview_of_the_Thunderbird_interface',
                              $mdc,
                              'This article describes the Thunderbird user interface, discusses example modifications, and introduces some of the APIs most commonly used by extension developers.'),
                    new Howto(30, 'Thunderbird How-tos',
                              'https://developer.mozilla.org/en/Extensions/Thunderbird/HowTos',
                              $mdc,
                              'This section contains many code samples and how-to articles on common Thunderbird extension tasks.'),
                    new Howto(31, 'Thunderbird Extensions Documentation Index',
                              'https://developer.mozilla.org/en/Extensions/Thunderbird',
                              $mdc,
                              'If you can\'t find what you\'re looking for in the above articles, try the Mozilla Developer Center\'s Thunderbird extensions landing page.'),
                )),
                new SubCategory('Developing Add-ons for Mobile', array(
                    new Howto(32, 'Mobile Architecture',
                              'https://wiki.mozilla.org/Mobile/Fennec/Architecture',
                              $wiki,
                              'This document describes the architecture of Firefox on mobile and includes performance tips for mobile code.'),
                    new Howto(33, 'Mobile Extensions',
                              'https://wiki.mozilla.org/Mobile/Fennec/Extensions',
                              $wiki,
                              'This document explains the specifics on creating an extension for mobile.'),
                    new Howto(34, 'Best Practices for Mobile Extensions',
                              'https://wiki.mozilla.org/Mobile/Fennec/Extensions/BestPractices',
                              $wiki,
                              'Mobile extensions have small screen space and limited resources to work with. This document explains best practices for designing and developing for a mobile environment.'),
                    new Howto(35, 'Mobile Code Snippets',
                              'https://wiki.mozilla.org/Mobile/Fennec/CodeSnippets',
                              $wiki,
                              'Code snippets specific to mobile.'),
                    new Howto(47, 'Designing User Interfaces for Mobile',
                              'https://wiki.mozilla.org/Mobile/Fennec/Extensions/UserInterface',
                              $wiki,
                              'How to design an interface for your mobile extension.')
                ))
            )),
            new HubCategory('Theme Development', ___('Style Mozilla applications the way you want with pixel-perfect themes.'), 'theme-development', array(
                new SubCategory('Getting Started', array(
                    new Howto(36, 'Creating a Skin for Firefox',
                              'https://developer.mozilla.org/en/Creating_a_Skin_for_Firefox',
                              $mdc,
                              'Learn how to find the right files to edit, make your changes, and package up your new theme in this tutorial.'),
                    new Howto(37, 'How to Create a Firefox Theme',
                              'http://www.twistermc.com/blog/2006/09/22/how-to-create-a-firefox-theme/',
                              new HubSite('Blog on a Stick', 'http://www.twistermc.com'),
                              'Thomas McMahon explains how to create a Firefox theme from start-to-finish in this tutorial.')
                )),
                new SubCategory('Theme Development', array(
                    new Howto(38, 'Theme Packaging',
                              'https://developer.mozilla.org/en/Theme_Packaging',
                              $mdc,
                              'This article explains the packaging of themes.'),
                    new Howto(39, 'First Steps in Theme Design',
                              'http://cheeaun.com/blog/2004/12/first-steps-in-theme-design',
                              new HubSite('cheeaun blog', 'http://www.cheeaun.com'),
                              'This blog post by Lim Chee Aun explains the use of -moz-image-region in themes.'),
                    new Howto(40, 'Making Sure Your Theme Works with RTL Locales',
                              'https://developer.mozilla.org/en/Making_Sure_Your_Theme_Works_with_RTL_Locales',
                              $mdc,
                              'It\'s important to make sure your theme works in all locales. This article explains how to tweak your theme to look great for users who browse in right-to-left.'),
                    new Howto(41, 'Theme Documentation Index',
                              'https://developer.mozilla.org/en/Themes',
                              $mdc,
                              'If you haven\'t found what you\'re looking for yet, try the Mozilla Developer Center\'s Themes section.'),
                ))
            )),
            new HubCategory('Other Types of Add-ons', ___('Find information on Search Plug-ins, Jetpack, Personas, and other types of add-ons here.'), 'other-addons', array(
                new SubCategory('Jetpack', array(
                    new Howto(42, 'Jetpack Tutorial',
                              'https://jetpack.mozillalabs.com/tutorial.html',
                              $jetpack,
                              'Learn how easy it is to extend Firefox with Jetpack in this tutorial.'),
                    new Howto(43, 'Jetpack API Documentation',
                              'https://jetpack.mozillalabs.com/api.html',
                              $jetpack,
                              'This Jetpack API documentation is a must-have for working on your Jetpack.')
                )),
                new SubCategory('Personas', array(
                    new Howto(44, 'How to Create Personas',
                              'http://getpersonas.com/demo_create',
                              new HubSite('Personas for Firefox', 'http://www.getpersonas.com'),
                              'Learn how to create your very own Persona with this official walk-through.')
                )),
                new SubCategory('Search Plug-ins', array(
                    new Howto(45, 'Creating OpenSearch Plug-ins for Firefox',
                              'https://developer.mozilla.org/en/Creating_OpenSearch_plugins_for_Firefox',
                              $mdc,
                              'This article explains the structure and requirements of search plug-ins in Firefox.')
                )),
                new SubCategory('Plug-ins', array(
                    new Howto(46, 'Plug-in Documentation Index',
                              'https://developer.mozilla.org/en/Plugins',
                              $mdc,
                              'Plug-ins to Mozilla-based applications are binary components that can display content that the application itself can\'t display natively. Explore the Mozilla Developer Center\'s plug-in documentation index to learn more.')
                )),
            ))
        );

        $this->policies = array(
            new HubCategory('Add-on Submission',
                            ___('Find out what is expected of add-ons we host and our policies on specific add-on practices.'),
                            'submission',
                            array($this->controller->url('/developers/docs/policies/contact'))),
            new HubCategory('Review Process',
                            ___('What happens after your add-on is submitted? Learn about how our Editors review submissions.'),
                            'reviews',
                            array($this->controller->url('/developers/docs/policies/submission'),
                                  $this->controller->url('/developers/docs/how-to'),
                                  $this->controller->url('/pages/validation'),
                                  $this->controller->url('/developers/docs/policies/contact'))),
            new HubCategory('Maintaining Your Add-on',
                            ___('Add-on updates, transferring ownership, user reviews, and what to expect once your add-on is approved.'),
                            'maintenance',
                            array($this->controller->url('/developers/docs/policies/contact'),
                                  $this->controller->url('/pages/review_guide'))),
            new HubCategory('Recommended Add-ons',
                            ___('How up-and-coming add-ons become recommended and what\'s involved in the process.'),
                            'recommended',
                            array($this->controller->url('/recommended'),
                                  $this->controller->url('/developers/docs/policies/contact'))),
            new HubCategory('Developer Agreement',
                            ___('Terms of Service for submitting your work to our site. Developers are required to accept this agreement before submission.'),
                            'agreement',
                            array($this->controller->url('/pages/developer_faq'))),
            new HubCategory('Contacting Us',
                            ___('How to get in touch with us regarding these policies or your add-on.'),
                            'contact',
                            array('https://bugzilla.mozilla.org/enter_bug.cgi?assigned_to=nobody%40mozilla.org&bit-23=1&bug_file_loc=http%3A%2F%2F&bug_severity=normal&bug_status=NEW&cf_status_192=---&component=Add-on%20Security&flag_type-270=X&flag_type-271=X&flag_type-369=X&flag_type-385=X&flag_type-4=X&flag_type-485=X&flag_type-506=X&flag_type-507=X&flag_type-540=X&form_name=enter_bug&op_sys=All&priority=--&product=addons.mozilla.org&qa_contact=security%40add-ons.bugs&rep_platform=All',
                                  'https://bugzilla.mozilla.org/enter_bug.cgi?product=addons.mozilla.org',
                                  'emails' => array(
                                                'amo-editors@mozilla.org',
                                                'amo-admins@mozilla.org'
                                            )))
        );
        
        $lorem = 'foo';
        $this->casestudies = array(
            new HubCaseStudy('Personalized Browsing with StumbleUpon',
                             ___('StumbleUpon is a recommendation engine that helps users discover and share great websites. With over 8 million users, it has become one of the most popular sharing services on the Web today.'),
                             'stumbleupon',
                             138,
                             '/img/docs/case-studies/stumbleupon-wordmark.png',
                             ___('Learn how StumbleUpon got its start with Firefox'),
                             new HubSite('StumbleUpon, Inc.', 'http://www.stumbleupon.com'),
                             '2002-02',
                             array(
                                ___('Number of Users') => '8 million',
                                ___('Pages Indexed') => '35 million',
                                ___('Total Stumbles') => '10 billion'
                             ))
            );

        // generate by-slug lookup arrays
        $objecttypes = array('categories', 'policies', 'casestudies');
        foreach ($objecttypes as $type) {
            $slugname = $type.'_slugs';
            $this->$slugname = array();
            foreach ($this->$type as &$item) {
                $this->{$slugname}[$item->slug] = $item;
            }
            unset($item);
        }
    }

    /**
     * Helper for creating an HTML link.
     */
    function link($title, $url, $absolute=false) {
        $this->controller->_sanitizeArray($title);
        return '<a href="' . ($absolute ? SITE_URL : '') . $this->controller->url($url) . "\">{$title}</a>";
    }

    /**
     * Helper for creating a localized and optionally absolute URL
     */
    function url($url, $absolute=false) {
        return ($absolute ? SITE_URL : '') . $this->controller->url($url);
    }

    /**
     * Generate a paginated news feed for one or more add-ons
     *
     * @param array $ids array of add-on ids
     * @param string $filter 'collections', 'reviews', 'approvals', 'updates', or '' (none)
     * @param array $pagination_options
     * @param bool $absolute_links if true, makes all links absolute rather than relative
     * @return array of stories
     */
    function getNewsForAddons($ids, $filter='', $pagination_options=array(), $absolute_links=false) {
        $filter_groups = array(
            'collections' => array(
                Addonlog::ADD_TO_COLLECTION,
                Addonlog::REMOVE_FROM_COLLECTION,
            ),
            'reviews' => array(
                Addonlog::ADD_REVIEW,
            ),
            'approvals' => array(
                Addonlog::APPROVE_VERSION,
                Addonlog::RETAIN_VERSION,
                Addonlog::ESCALATE_VERSION,
                Addonlog::REQUEST_VERSION,
            ),
            'updates' => array(
                Addonlog::CREATE_ADDON,
                Addonlog::ADD_VERSION,
                Addonlog::EDIT_VERSION,
                Addonlog::DELETE_VERSION,
                Addonlog::ADD_FILE_TO_VERSION,
                Addonlog::DELETE_FILE_FROM_VERSION,
            ),
        );

        // localized names we will probably need
        $applications = $this->controller->Application->getNames();
        $statuses = $this->controller->Amo->getStatusNames();
        $user_roles = $this->controller->Amo->getAuthorRoleNames();

        // fetch some logs
        $this->controller->Amo->clean($ids);
        $in_str = "'" . implode("','", $ids) . "'";
        $criteria = "(Addonlog.addon_id IN({$in_str}) OR Addonlog.addon_id IS NULL)";
        if (array_key_exists($filter, $filter_groups)) {
            $criteria .= " AND Addonlog.`type` IN(".implode(',', $filter_groups[$filter]).")";
        }

        $this->controller->Pagination->modelClass = 'Addonlog';
        $this->controller->Pagination->sortBy = 'created';
        $this->controller->Pagination->direction = 'DESC';
        $this->controller->Pagination->show = 10;
        list($_order,$_limit,$_page) = $this->controller->Pagination->init($criteria, array(), $pagination_options);
        $logs = $this->controller->Addonlog->findAll($criteria, null, $_order, $_limit, $_page, -1);

        // make localized news stories
        $newsFeed = array();
        $addons = array(); // addon links cache
        $users = array(); // user links cache
        foreach ($logs as $log) {

            // determine user name
            $user = '';
            $user_id = $log['Addonlog']['user_id'];
            if (!empty($user_id)) {
                if (isset($users[$user_id])) {
                    $user = $users[$user_id];
                } else if ($userInfo = $this->controller->User->getUser($user_id)) {
                    $user_name = trim($userInfo['User']['display_name']);
                    if (empty($user_name)) $user_name = $userInfo['User']['email'];
                    $user = $this->link($user_name, "/users/info/{$user_id}", $absolute_links);
                    $users[$user_id] = $user;
                } else {
                    if (defined('DEBUG') && DEBUG) {
                        $user = '<b>UNKNOWN USER</b>';
                    }
                    $users[$user_id] = $user;
                }
            }

            // determine addon name
            $addon = '';
            $addon_id = $log['Addonlog']['addon_id'];
            if (!empty($addon_id)) {
                if (isset($addons[$addon_id])) {
                    $addon = $addons[$addon_id]['html'];
                } else if ($addonInfo = $this->controller->Addon->getAddon($addon_id)) {
                    $addon_name = $addonInfo['Translation']['name']['string'];
                    $addon = $this->link($addon_name, "/addon/{$addon_id}", $absolute_links);
                    $addons[$addon_id] = array('text' => $addon_name, 'html' => $addon);
                } else {
                    if (defined('DEBUG') && DEBUG) {
                        $addon = '<b>UNKNOWN ADDON</b>';
                    }
                    $addons[$addon_id] = array('text' => $addon, 'html' => $addon);
                }
            }

            // default title (for rss) is the add-on's name
            if (!empty($addons[$addon_id]['text'])) {
                $story_title = $addons[$addon_id]['text'];
            } else {
                $story_title = '';
            }

            // determine story based on log type
            switch ($log['Addonlog']['type']) {
            case Addonlog::CREATE_ADDON:
                $story = sprintf(___('%1$s created %2$s.'), $user, $addon);
                $story_class = 'addon_created';
                $story_link = $this->url("/developers/dashboard#addon-{$addon_id}", $absolute_links);
                break;

            case Addonlog::EDIT_PROPERTIES:
                $story = sprintf(___('%1$s edited %2$s\'s properties.'), $user, $addon);
                $story_class = 'properties_edited';
                $story_link = $this->url("/developers/addon/edit/{$addon_id}/properties", $absolute_links);
                break;

            case Addonlog::EDIT_DESCRIPTIONS:
                $story = sprintf(___('%1$s edited %2$s\'s descriptions.'), $user, $addon);
                $story_class = 'descriptions_edited';
                $story_link = $this->url("/developers/addon/edit/{$addon_id}/descriptions", $absolute_links);
                break;

            case Addonlog::EDIT_CATEGORIES:
                $story = sprintf(___('%1$s modified %2$s\'s category associations.'), $user, $addon);
                $story_class = 'categories_modified';
                $story_link = $this->url("/developers/addon/edit/{$addon_id}/categories", $absolute_links);
                break;

            case Addonlog::ADD_USER_WITH_ROLE:
            case Addonlog::REMOVE_USER_WITH_ROLE:
                $userInfo = $this->controller->User->getUser($log['Addonlog']['object1_id']);
                $user_name = trim($userInfo['User']['display_name']);
                if(empty($user_name)) $user_name = $userInfo['User']['email'];
                $user2 = $this->link($user_name,
                                        '/users/info/'.$log['Addonlog']['object1_id'],
                                        $absolute_links);

                $role = $log['Addonlog']['object2_id'];
                $role = !empty($user_roles[$role]) ? $user_roles[$role] : $role;

                if ($log['Addonlog']['type'] == Addonlog::ADD_USER_WITH_ROLE) {
                    $story = sprintf(___('%1$s made %2$s a/an %3$s of %4$s.'), $user, $user2, $role, $addon);
                    $story_class = 'author_added';
                }
                elseif ($log['Addonlog']['type'] == Addonlog::REMOVE_USER_WITH_ROLE) {
                    $story = sprintf(___('%1$s removed %2$s as a/an %3$s of %4$s.'), $user, $user2, $role, $addon);
                    $story_class = 'author_removed';
                }
                $story_link = $this->url("/developers/addon/edit/{$addon_id}/authors", $absolute_links);
                break;

            case Addonlog::EDIT_CONTRIBUTIONS:
                $story = sprintf(___('%1$s modified contributions settings for %2$s.'), $user, $addon);
                $story_class = 'contributions_modified';
                $story_link = $this->url("/developers/addon/edit/{$addon_id}/contributions", $absolute_links);
                break;

            case Addonlog::SET_INACTIVE:
                $story = sprintf(___('%1$s marked %2$s as active.'), $user, $addon);
                $story_class = 'addon_inactive';
                $story_link = $this->url("/developers/addon/status/{$addon_id}/", $absolute_links);
                break;

            case Addonlog::UNSET_INACTIVE:
                $story = sprintf(___('%1$s marked %2$s as inactive.'), $user, $addon);
                $story_class = 'addon_active';
                $story_link = $this->url("/developers/addon/status/{$addon_id}/", $absolute_links);
                break;

            case Addonlog::SET_PUBLICSTATS:
                $story = sprintf(___('%1$s marked %2$s\'s statistics dashboard as public.'), $user, $addon);
                $story_class = 'stats_public';
                $story_link = $this->url("/statistics/settings/{$addon_id}", $absolute_links);
                break;

            case Addonlog::UNSET_PUBLICSTATS:
                $story = sprintf(___('%1$s marked %2$s\'s statistics dashboard as private.'), $user, $addon);
                $story_class = 'stats_private';
                $story_link = $this->url("/statistics/settings/{$addon_id}", $absolute_links);
                break;

            case Addonlog::CHANGE_STATUS:
                $status = $log['Addonlog']['object1_id'];
                $status = !empty($statuses[$status]) ? $statuses[$status] : $status;

                $story = sprintf(___('%1$s changed %2$s\'s status to %3$s.'), $user, $addon, $status);
                $story_class = 'status';
                $story_link = $this->url("/developers/addon/status/{$addon_id}/", $absolute_links);
                break;

            case Addonlog::ADD_PREVIEW:
                $story = sprintf(___('%1$s added a new preview screenshot to %2$s.'), $user, $addon);
                $story_class = 'preview_add';
                $story_link = $this->url("/developers/previews/{$addon_id}/", $absolute_links);
                break;

            case Addonlog::EDIT_PREVIEW:
                $story = sprintf(___('%1$s modified the preview screenshots for %2$s.'), $user, $addon);
                $story_class = 'preview_edit';
                $story_link = $this->url("/developers/previews/{$addon_id}/", $absolute_links);
                break;

            case Addonlog::DELETE_PREVIEW:
                $story = sprintf(___('%1$s removed a preview screenshot from %2$s.'), $user, $addon);
                $story_class = 'preview_delete';
                $story_link = $this->url("/developers/previews/{$addon_id}/", $absolute_links);
                break;

            case Addonlog::ADD_VERSION:
            case Addonlog::EDIT_VERSION:
            case Addonlog::DELETE_VERSION:
                $version = $log['Addonlog']['name1'];

                if ($log['Addonlog']['type'] == Addonlog::ADD_VERSION) {
                    $story = sprintf(___('%1$s uploaded version %2$s to %3$s.'), $user, $version, $addon);
                    $story_class = 'version_added';
                }
                elseif ($log['Addonlog']['type'] == Addonlog::EDIT_VERSION) {
                    $story = sprintf(___('%1$s edited version %2$s of %3$s.'), $user, $version, $addon);
                    $story_class = 'version_edited';
                }
                elseif ($log['Addonlog']['type'] == Addonlog::DELETE_VERSION) {
                    $story = sprintf(___('%1$s deleted version %2$s from %3$s.'), $user, $version, $addon);
                    $story_class = 'version_deleted';
                }
                $story_link = $this->url("/developers/versions/{$addon_id}/", $absolute_links);
                break;

            case Addonlog::ADD_FILE_TO_VERSION:
            case Addonlog::DELETE_FILE_FROM_VERSION:
                $file_name = $log['Addonlog']['name1'];
                $version = $log['Addonlog']['name2'];

                if ($log['Addonlog']['type'] == Addonlog::ADD_FILE_TO_VERSION) {
                    $story = sprintf(___('%1$s added file %2$s to %3$s %4$s.'), $user, $file_name, $addon, $version);
                    $story_class = 'file_added';
                }
                else if ($log['Addonlog']['type'] == Addonlog::DELETE_FILE_FROM_VERSION) {
                    $story = sprintf(___('%1$s deleted file %2$s from %3$s %4$s.'), $user, $file_name, $addon, $version);
                    $story_class = 'file_deleted';
                }
                $story_link = $this->url("/developers/versions/{$addon_id}/", $absolute_links);
                break;

            case Addonlog::APPROVE_VERSION:
            case Addonlog::RETAIN_VERSION:
            case Addonlog::ESCALATE_VERSION:
            case Addonlog::REQUEST_VERSION:
                $version = $log['Addonlog']['name1'];

                if ($log['Addonlog']['type'] == Addonlog::APPROVE_VERSION) {
                    $story = sprintf(___('%1$s approved %2$s %3$s for public.'), $user, $addon, $version);
                    $story_class = 'approved';
                }
                else if ($log['Addonlog']['type'] == Addonlog::RETAIN_VERSION) {
                    $story = sprintf(___('%1$s retained %2$s %3$s in the sandbox.'), $user, $addon, $version);
                    $story_class = 'retained';
                }
                else if ($log['Addonlog']['type'] == Addonlog::ESCALATE_VERSION) {
                    $story = sprintf(___('%1$s escalated %2$s %3$s for super-review.'), $user, $addon, $version);
                    $story_class = 'escalated';
                }
                else if ($log['Addonlog']['type'] == Addonlog::REQUEST_VERSION) {
                    $story = sprintf(___('%1$s requested more information in order to review %2$s %3$s.'), $user, $addon, $version);
                    $story_class = 'request_info';
                }
                $story_link = $this->url("/developers/versions/{$addon_id}/", $absolute_links);
                break;

            case Addonlog::ADD_TAG:
            case Addonlog::REMOVE_TAG:
                $tag = $log['Addonlog']['name1'];
                $tag = $this->link($tag, "/tag/{$tag}", $absolute_links);

                if ($log['Addonlog']['type'] == Addonlog::ADD_TAG) {
                    $story = sprintf(___('%1$s tagged %2$s as %3$s.'), $user, $addon, $tag);
                    $story_class = 'tag_added';
                }
                else if ($log['Addonlog']['type'] == Addonlog::REMOVE_TAG) {
                    $story = sprintf(___('%1$s untagged %2$s as %3$s.'), $user, $addon, $tag);
                    $story_class = 'tag_removed';
                }
                $story_link = $this->url("/developers/addon/edit/{$addon_id}/tags", $absolute_links);
                break;

            case Addonlog::ADD_TO_COLLECTION:
            case Addonlog::REMOVE_FROM_COLLECTION:
                // lookup collection and use localized name
                $collection = $this->controller->Collection->getCollection($log['Addonlog']['object1_id']);
                if (!empty($collection['Translation']['name']['string'])) {
                    $name = $this->link($collection['Translation']['name']['string'], 
                                $this->controller->Collection->getDetailUrl($collection),
                                $absolute_links);

                // else use logged name
                } else {
                    $name = $log['Addonlog']['name1'];
                }

                if ($log['Addonlog']['type'] == Addonlog::ADD_TO_COLLECTION) {
                    $story = sprintf(___('%1$s added %2$s to the %3$s collection.'), $user, $addon, $name);
                }
                else if ($log['Addonlog']['type'] == Addonlog::REMOVE_FROM_COLLECTION) {
                    $story = sprintf(___('%1$s removed %2$s from the %3$s collection.'), $user, $addon, $name);
                }
                $story_class = 'collection';
                $story_link = $this->url("/addon/{$addon_id}", $absolute_links);
                break;

            case Addonlog::ADD_REVIEW:
                $story = sprintf(___('%1$s wrote a review of %2$s.'), $user, $addon);
                $story_class = 'review_added';
                $story_link = $this->url("/addon/{$addon_id}", $absolute_links);
                break;

            case Addonlog::ADD_RECOMMENDED_CATEGORY:
            case Addonlog::REMOVE_RECOMMENDED_CATEGORY:
                $cat = $this->controller->Category->findById($log['Addonlog']['object1_id'], null, null, -1);
                $category = $this->link($cat['Translation']['name']['string'],
                    "/browse/type:{$cat['Category']['addontype_id']}/cat:{$cat['Category']['id']}",
                    $absolute_links);

                if ($log['Addonlog']['type'] == Addonlog::ADD_RECOMMENDED_CATEGORY) {
                    $story = sprintf(___('%1$s was marked as recommended in the %2$s category.'), $addon, $category);
                    $story_class = 'recommended_add';
                }
                else if ($log['Addonlog']['type'] == Addonlog::REMOVE_RECOMMENDED_CATEGORY) {
                    $story = sprintf(___('%1$s was removed as recommended in the %2$s category.'), $addon, $category);
                    $story_class = 'recommended_remove';
                }
                $story_link = $this->url("/addon/{$addon_id}", $absolute_links);
                break;

            case Addonlog::ADD_RECOMMENDED:
                $story = sprintf(___('%1$s was added to the Recommended List.'), $addon);
                $story_class = 'recommended_add';
                $story_link = $this->url("/addon/{$addon_id}", $absolute_links);
                break;

            case Addonlog::REMOVE_RECOMMENDED:
                $story = sprintf(___('%1$s was removed from the Recommended List.'), $addon);
                $story_class = 'recommended_remove';
                $story_link = $this->url("/addon/{$addon_id}", $absolute_links);
                break;

            case Addonlog::ADD_APPVERSION:
                $app = $applications[$log['Addonlog']['object1_id']];
                $appversion = $log['Addonlog']['name2'];

                $story = sprintf(___('Add-ons can now be compatible with %1$s %2$s.'), $app, $appversion);
                $story_class = 'versions_compat_add';
                $story_link = $this->url("/", $absolute_links);
                $story_title = $app;
                break;

            case Addonlog::CUSTOM_TEXT:
                $story = $log['Addonlog']['notes'];
                $this->controller->_sanitizeArray($story);
                $story_class = 'custom';
                $story_link = $this->url("/", $absolute_links);
                $story_title = $story;
                break;

            case Addonlog::CUSTOM_HTML:
                $story = $log['Addonlog']['notes'];
                $story_class = 'custom';
                $story_link = $this->url("/", $absolute_links);
                $story_title = $story;
                break;

            default:
                $story = 'unrecognized add-on activity';
                $story_class = 'custom';
                $story_link = $this->url("/", $absolute_links);
                $story_title = $story;
                break;
            }

            $newsFeed[] = array(
                'id' => $log['Addonlog']['id'],
                'addon_id' => $log['Addonlog']['addon_id'],
                'type' => $log['Addonlog']['type'],
                'created' => $log['Addonlog']['created'],
                'story' => $story,
                'class' => $story_class,
                'link' => $story_link,
                'title' => $story_title,
            );
        }

        return $newsFeed;
    }
}


class HubCategory extends Object {

    function __construct($title, $description, $slug, $items = array()) {
        $this->title = $title;
        $this->description = $description;
        $this->slug = $slug;
        $this->items = $items;
    }

    function get_ids() {
        /* Get all the howto ids in this Category,
         * which are held in SubCategories. */
        $ids = array();
        foreach ($this->items as $item) {
            $ids = array_merge($ids, $item->get_ids());
        }
        return $ids;
    }
}


class SubCategory extends HubCategory {

    function __construct($title, $items) {
        parent::__construct($title, '', '', $items);
    }

    function get_ids() {
        $ids = array();
        foreach ($this->items as $item) $ids[] = $item->id;
        return $ids;
    }
}


class Howto extends Object {

    function __construct($id, $title, $href, $site, $description, $type = 'article') {
        $this->id = $id;
        $this->title = $title;
        $this->href = $href;
        $this->site = $site;
        $this->description = $description;
        $this->type = $type;
    }
}

class HubCaseStudy extends Object {
    function __construct($title, $description, $slug, $addonid, $logo,
        $actiontext, $developer, $firstreleased, $otherstats) {
        $this->title = $title;
        $this->description = $description;
        $this->slug = $slug;
        $this->addonid = $addonid;
        $this->logo = $logo;
        $this->actiontext = $actiontext;
        $this->developer = $developer;
        $this->firstreleased = $firstreleased; // will be fed to strtotime()
        $this->otherstats = $otherstats;
    }
}

class HubSite extends Object {

    function __construct($title, $href) {
        $this->title = $title;
        $this->href = $href;
    }
}
