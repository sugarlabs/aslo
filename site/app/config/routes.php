<?php
/* SVN FILE: $Id: routes.php,v 1.1.1.1 2006/08/14 23:54:56 sancus%off.net Exp $ */
/**
 * Short description for file.
 *
 * In this file, you set up routes to your controllers and their actions.
 * Routes are very important mechanism that allows you to freely connect
 * different urls to chosen controllers and their actions (functions).
 *
 * PHP versions 4 and 5
 *
 * CakePHP :  Rapid Development Framework <http://www.cakephp.org/>
 * Copyright (c)	2006, Cake Software Foundation, Inc.
 *								1785 E. Sahara Avenue, Suite 490-204
 *								Las Vegas, Nevada 89104
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @copyright		Copyright (c) 2006, Cake Software Foundation, Inc.
 * @link				http://www.cakefoundation.org/projects/info/cakephp CakePHP Project
 * @package			cake
 * @subpackage		cake.app.config
 * @since			CakePHP v 0.2.9
 * @version			$Revision: 1.1.1.1 $
 * @modifiedby		$LastChangedBy: phpnut $
 * @lastmodified	$Date: 2006/08/14 23:54:56 $
 * @license			http://www.opensource.org/licenses/mit-license.php The MIT License
 */

/**
 * Here, we are connecting '/' (base path) to controller called 'Pages',
 * its action called 'display', and we pass a param to select the view file
 * to use (in this case, /app/views/pages/home.thtml)...
 */

    $Route->connect('/', array('controller' => 'addons', 'action' => 'home'));

/**
 * ...and connect the rest of 'Pages' controller's urls.
 */
    $Route->connect('/pages/*', array('controller' => 'pages', 'action' => 'display'));

/**
 * Then we connect url '/test' to our test controller. This is helpful in
 * developement.
 */
    $Route->connect('/tests', array('controller' => 'tests', 'action' => 'index'));

    // Connect the routes for app and lang.  The order of these are important!
    // The first one that matches is what it will use.  If LANG and APP_SHORTNAME
    // aren't defined, bootstrap will be redirecting the person.  We're all pretty
    // sure these routes don't need to be added every time...

    if (defined('LANG') && defined('SITE_LAYOUT')) {
        global $other_layouts;

        if (array_key_exists(SITE_LAYOUT, $other_layouts)) {
            $prefix = LANG;
        }
        else {
            $prefix = LANG . '/' . SITE_LAYOUT;
        }

        // If they just go to /$lang/$app/
        $Route->connect("/{$prefix}", array('controller' => 'addons', 'action' => 'home'));

        // connect localized, static pages
        $Route->connect("/{$prefix}/pages/*", array('controller' => 'pages', 'action' => 'display'));

        // Setup old URL handlers.  These all are in the $lang's section because they
        // get redirected here in bootstrap.php.  Original redirects can be seen at:
        // http://lxr.mozilla.org/mozilla/source/webtools/addons/public/htaccess.dist

        // v2: section prefixes (eg. /extensions/)
        $Route->connect("/{$prefix}/extensions/", array('controller' => 'legacy_url', 'action' => 'oldSection', 'extensions'));
        $Route->connect("/{$prefix}/themes/", array('controller' => 'legacy_url', 'action' => 'oldSection', 'themes'));
        $Route->connect("/{$prefix}/plugins/", array('controller' => 'legacy_url', 'action' => 'oldSection', 'plugins'));
        $Route->connect("/{$prefix}/search-engines/", array('controller' => 'legacy_url', 'action' => 'oldSection', 'search-engines'));
        $Route->connect("/{$prefix}/dictionaries/", array('controller' => 'legacy_url', 'action' => 'oldSection', 'dictionaries'));
        $Route->connect("/{$prefix}/bookmarks/", array('controller' => 'legacy_url', 'action' => 'oldSection', 'bookmarks'));

        // Redirect for firefox 1.5
        $Route->connect("/{$prefix}/search-engines.php", array('controller' => 'addons', 'action' => 'browse', 'type:4'));

        // Some pleasant short forms
        $Route->connect("/{$prefix}/browse/*", array('controller' => 'addons', 'action' => 'browse'));
        $Route->connect("/{$prefix}/recommended/*", array('controller' => 'addons', 'action' => 'recommended'));
        $Route->connect("/{$prefix}/user/*/picture", array('controller' => 'users', 'action' => 'picture'));
        $Route->connect("/{$prefix}/user/*", array('controller' => 'users', 'action' => 'info'));
        $Route->connect("/{$prefix}/addon/share/*", array('controller' => 'addons', 'action' => 'share'));
        $Route->connect("/{$prefix}/addon/(\d+)/developers/*", array('controller' => 'addons', 'action' => 'developers'));
        $Route->connect("/{$prefix}/addon/(\d+)/about/*", array('controller' => 'addons', 'action' => 'about'));
        $Route->connect("/{$prefix}/addon/(\d+)/*", array('controller' => 'addons', 'action' => 'display'));
        $Route->connect("/{$prefix}/blog/*", array('controller' => 'blog', 'action' => 'view'));
        $Route->connect("/{$prefix}/collection/*", array('controller' => 'collections', 'action' => 'view'));

        // API hookup
        $Route->connect("/{$prefix}/api/addon/*", array('controller' => 'api', 'action'=>'addon'));
        $Route->connect("/{$prefix}/api/list/*", array('controller' => 'api', 'action'=>'list_addons'));

        // Add API versioning support
        $Route->connect("/{$prefix}/api/[\d\.]*/addon/*", array('controller' => 'api', 'action'=>'addon'));
        $Route->connect("/{$prefix}/api/[\d\.]*/list/*", array('controller' => 'api', 'action'=>'list_addons'));
        $Route->connect("/{$prefix}/api/[\d\.]*/search/*", array('controller' => 'api', 'action'=>'search'));



        $Route->connect("/{$prefix}/api/[\d\.]*/get_language_packs/*", array('controller' => 'api', 'action'=>'get_language_packs'));
        $Route->connect("/{$prefix}/api/[\d\.]*/stats/*", array('controller' => 'api', 'action' => 'stats'));

        // Bandwagon sharing API
        $Route->connect("/{$prefix}/api/[\d\.]*/sharing/collections/*/addons/", array('controller' => 'sharing_api', 'action'=>'collection_addons'));
        $Route->connect("/{$prefix}/api/[\d\.]*/sharing/collections/*/addons/*", array('controller' => 'sharing_api', 'action'=>'collection_addon_detail'));
        $Route->connect("/{$prefix}/api/[\d\.]*/sharing/collections/", array('controller' => 'sharing_api', 'action'=>'collections'));
        $Route->connect("/{$prefix}/api/[\d\.]*/sharing/collections/*", array('controller' => 'sharing_api', 'action'=>'collection_detail'));
        $Route->connect("/{$prefix}/api/[\d\.]*/sharing/auth", array('controller' => 'sharing_api', 'action'=>'auth'));
        $Route->connect("/{$prefix}/api/[\d\.]*/sharing/auth/*", array('controller' => 'sharing_api', 'action'=>'auth_detail'));
        $Route->connect("/{$prefix}/api/[\d\.]*/sharing/email", array('controller' => 'sharing_api', 'action'=>'email'));
        $Route->connect("/{$prefix}/api/[\d\.]*/sharing", array('controller' => 'sharing_api', 'action'=>'service_doc'));

        // Bandwagon/collections
        $Route->connect("/{$prefix}/fashionyourfirefox/", array('controller' => 'collections', 'action' => 'interactive'));

        // Forward old DevCP links to new DevCP
        $Route->connect("/{$prefix}/developers(/|/index|/hub)?", array('controller' => 'devhub', 'action' => 'hub'));
        $Route->connect("/{$prefix}/developers/(dashboard|addons)", array('controller' => 'developers', 'action' => 'dashboard'));
        $Route->connect("/{$prefix}/developers/edit/*", array('controller' => 'developers', 'action' => 'addon', 'edit'));
        $Route->connect("/{$prefix}/developers/add/*", array('controller' => 'developers', 'action' => 'versions' ,'add'));
        $Route->connect("/{$prefix}/developers/editversion/*", array('controller' => 'developers', 'action' => 'versions', 'edit'));

        // Developer hub.
        $Route->connect("/{$prefix}/developers/docs/reference/", array('controller' => 'devhub', 'action' => 'api_reference'));
        $Route->connect("/{$prefix}/developers/docs/getting-started/", array('controller' => 'devhub', 'action' => 'gettingstarted'));
        $Route->connect("/{$prefix}/developers/docs/how-to/", array('controller' => 'devhub', 'action' => 'howto_list'));
        $Route->connect("/{$prefix}/developers/docs/how-to/vote/*", array('controller' => 'devhub', 'action' => 'howto_vote'));
        $Route->connect("/{$prefix}/developers/docs/how-to/*", array('controller' => 'devhub', 'action' => 'howto_detail'));
        $Route->connect("/{$prefix}/developers/docs/policies/", array('controller' => 'devhub', 'action' => 'policy_list'));
        $Route->connect("/{$prefix}/developers/docs/policies/*", array('controller' => 'devhub', 'action' => 'policy_detail'));
        $Route->connect("/{$prefix}/developers/docs/case-studies/", array('controller' => 'devhub', 'action' => 'case_studies_list'));
        $Route->connect("/{$prefix}/developers/docs/case-studies/*", array('controller' => 'devhub', 'action' => 'case_studies_detail'));
        $Route->connect("/{$prefix}/developers/search/", array('controller' => 'devhub', 'action' => 'search'));
        $Route->connect("/{$prefix}/developers/community/newsletter/", array('controller' => 'devhub', 'action' => 'newsletter'));
        $Route->connect("/{$prefix}/developers/feed/*", array('controller' => 'devhub', 'action' => 'feed'));
        $Route->connect("/{$prefix}/developers/tools/builder/success/*", array('controller' => 'devhub', 'action' => 'builder_success'));
        $Route->connect("/{$prefix}/developers/tools/builder/downloads/*", array('controller' => 'devhub', 'action' => 'builder_download'));
        $Route->connect("/{$prefix}/developers/tools/builder/*", array('controller' => 'devhub', 'action' => 'builder'));

		// Tag page
		$Route->connect("/{$prefix}/tag/*", array('controller' => 'tags', 'action' => 'display'));
		// Top Tags
		$Route->connect("/{$prefix}/top-tags", array('controller' => 'tags', 'action' => 'top'));

        /* Personas */
        $Route->connect("/{$prefix}/personas/*", array('controller' => 'personas', 'action' => 'persona_list'));

        // Magical undocumented routing syntax - if nothing has matched up till now, it'll hit this
        $Route->connect("/{$prefix}/:controller/:action/*", array('controller' => 'pages', 'action' => 'index'));

    }
//}}

?>
