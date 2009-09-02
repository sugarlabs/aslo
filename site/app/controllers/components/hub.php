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

        $lorem = ___("Lorem ipsum dolor sit amet, consectetur adipiscing elit. Cras posuere malesuada congue. Etiam dignissim lectus ut dui rhoncus elementum commodo orci sodales. Donec placerat tortor non nulla viverra euismod");

        $mdc = new HubSite('Mozilla Developer Center', 'http://developer.mozilla.org');

        $this->categories = array(
            new HubCategory('Getting Started', $lorem, 'getting-started', array(
                new SubCategory('The Basics', array(
                    new Howto(0, 'Firefox Add-ons Developer Guide',
                              'https://developer.mozilla.org/En/Firefox_addons_developer_guide',
                              $mdc,
                              "In this detailed guide to extension development, you'll learn the basics of packaging extensions, building an interface with XUL, implementing advanced processes with XPCOM, and how to put it all together."),
                )),
            )),
        );

        $this->policies = array(
            new HubCategory('Add-on Submission', ___('Find out what is expected of add-ons we host and our policies on specific add-on practices.'), 'submission'),
            new HubCategory('Review Process', ___('What happens after your add-on is submitted? Learn about how our Editors review submissions.'), 'reviews'),
            new HubCategory('Maintaining Your Add-on', ___('Add-on updates, transferring ownership, user reviews, and what to expect once your add-on is approved.'), 'maintenance'),
            new HubCategory('Recommended Add-ons', ___('How up-and-coming add-ons become recommended and what\'s involved in the process.'), 'recommended'),
            new HubCategory('Developer Agreement', ___('Terms of Service for submitting your work to our site. Developers are required to accept this agreement before submission.'), 'agreement'),
            new HubCategory('Contacting Us', ___('How to get in touch with the AMO team regarding these policies or your add-on.'), 'contact')
        );

        $this->casestudies = array(
            new HubCaseStudy('Cooliris lorem ipsum', $lorem, 'cooliris', 5579,
                '/img/amo2009/logo-firefox.gif', ___('Cooliris action text')),
            new HubCaseStudy('Firebug lorem ipsum', $lorem, 'firebug', 1843,
                '/img/amo2009/logo-seamonkey.gif', ___('Firebug action text')),
            new HubCaseStudy('Adblock Plus dolor sit amet', $lorem, 'adblockplus',
                1865, '/img/amo2009/logo-thunderbird.gif', ___('ABP action text'))
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
}


class HubCategory extends Object {

    function __construct($title, $description, $slug, $items = array()) {
        $this->title = $title;
        $this->description = $description;
        $this->slug = $slug;
        $this->items = $items;
    }
}


class SubCategory extends HubCategory {

    function __construct($title, $items) {
        parent::__construct($title, '', '', $items);
    }
}


class Howto extends Object {

    function __construct($id, $title, $href, $site, $description) {
        $this->id = $id;
        $this->title = $title;
        $this->href = $href;
        $this->site = $site;
        $this->description = $description;
    }
}

class HubCaseStudy extends Object {
    function __construct($title, $description, $slug, $addonid, $logo, $actiontext) {
        $this->title = $title;
        $this->description = $description;
        $this->slug = $slug;
        $this->addonid = $addonid;
        $this->logo = $logo;
        $this->actiontext = $actiontext;
    }
}

class HubSite extends Object {

    function __construct($title, $href) {
        $this->title = $title;
        $this->href = $href;
    }
}
