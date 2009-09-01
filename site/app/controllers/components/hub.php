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

        $this->category_slugs = array();
        foreach ($this->categories as &$category) {
            $this->category_slugs[$category->slug] = $category;
        }

        $this->policies = $this->categories;
    }
}


class HubCategory extends Object {

    function __construct($title, $description, $slug, $items) {
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

class HubSite extends Object {

    function __construct($title, $href) {
        $this->title = $title;
        $this->href = $href;
    }
}
