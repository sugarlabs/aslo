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
 * The Initial Developer of the Original Code is The Mozilla Foundation.
 *
 * Portions created by the Initial Developer are Copyright (C) 2009
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *      l.m.orchard <lorchard@mozilla.com> (Original Author)
 *      Frederic Wenzel <fwenzel@mozilla.com>
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

class SharingApiTest extends WebTestHelper {

    const XML_NS = 'http://www.w3.org/XML/1998/namespace';

    function setUp()
    {
        // Instantiate some useful models.
        $models = array(
            'User', 'Collection', 'Addon'
        );
        foreach ($models as $model) {
            loadModel($model);
            $this->{$model} =& new $model();
            $this->{$model}->caching = false;
            $this->{$model}->cacheQueries = false;
        }

        // Grab a pile of known addons for testing.
        $addon_rows = $this->Addon->findAll(array(
            'Addon.inactive' => 0,
            'Addon.addontype_id' => array(
                ADDON_EXTENSION, ADDON_THEME, ADDON_DICT, 
                ADDON_SEARCH, ADDON_PLUGIN
            )
        ), null, null, 20, 1);

        // Index the addons by GUID.
        $this->addons = array();
        foreach ($addon_rows as $row) {
            $this->addons[$row['Addon']['guid']] = $row;
        }

        // Build the service URL
        $this->service_url = $this->actionURI('/api/1.3/sharing/');

        // Establish the user/pass for the test user.
        $this->username = 'nobody@mozilla.org';
        $this->password = 'test';

        // Ensure each test starts off logged out.
        $this->get($this->actionURI('/users/logout'));

        // Get the record for the test user
        $this->test_user = $this->User->findByEmail($this->username);
        $this->nickname = $this->test_user['User']['nickname'];

        // Get some other test users
        $this->test_user_2 = $this->User->findByEmail('user@test');
        $this->test_user_3 = $this->User->findByEmail('nobody@addons.mozilla.org');
        $this->test_user_4 = $this->User->findByEmail('sandbox@test');

        $this->all_test_users = array(
            $this->test_user, $this->test_user_2, 
            $this->test_user_3, $this->test_user_4
        );

        // Delete all collections for the test users.
        foreach ($this->all_test_users as $user) {
            $c = $this->User->getCollections($user['User']['id']);
            if (!empty($c)) foreach ($c as $row) {
                $this->Collection->delete($row);
            }
        }
    }

    /**
     * Ensure that the service document can be fetched, but that authentication 
     * is demanded.
     */
    function testServiceDocumentBasics()
    {
        // Try fetching the service document, but assert that auth is required.
        $this->get($this->service_url);
        $this->assertAuthentication('Basic');
        $this->assertResponse(401);

        // Logout, and assert that auth is required again.
        $this->get($this->actionURI('/users/logout'));
        $this->get($this->service_url);
        $this->assertAuthentication('Basic');
        $this->assertResponse(401);

        // Bug 493302: try HTTP Basic Auth with some known unverified users
        // and assert failure.
        $this->authenticate('fligtar@gmail.com', 'test');
        $doc = $this->getXml($this->service_url);
        $this->assertResponse(401);

        $this->authenticate('bill@ms.com', 'logmein');
        $doc = $this->getXml($this->service_url);
        $this->assertResponse(401);

        // Now, try HTTP Basic Auth and assert auth is satisfied.
        $this->authenticate($this->username, $this->password);
        $doc = $this->getXml($this->service_url);
        $this->assertResponse(200);

        // There should at least be <email> and <collections> elements, each 
        // with href attributes.
        $this->assertTrue( isset($doc->email), 
            'email element present' );
        $this->assertTrue( !empty($doc->email['href']), 
            'email element has @href' );
        $this->assertTrue( isset($doc->collections), 
            'collections element present' );
        $this->assertTrue( !empty($doc->collections['href']), 
            'collections has @href' );
    }

    /**
     * Ensure that GET requests for service doc, collections, and collection 
     * documents all reflect presence of collections in the database.
     */
    function testEmptyCollectionsWithModel()
    {
        // Use website cookie login and assert that auth is satisfied.
        $this->login();
        $doc = $this->getXml($this->service_url);
        $this->assertResponse(200,
            "Service document should be found at {$this->service_url}");

        // Try to get a base URL, based on either the service doc URL or an 
        // xml:base in the document
        $base_url = $this->getBaseUrl($this->service_url, $doc);

        // Get an absolute URL for collections.
        $collections_url = 
            $this->resolveUrl($base_url, $doc->collections['href']);

        // Since collections for the test user are deleted in setup, there 
        // shouldn't be any collections at first.
        $nodes = $doc->collections->children();
        $this->assertTrue(count($nodes) == 0,
            'Collections for user should be empty at first');

        // Now, create new test collections
        $test_collections = $this->createTestCollections();

        // Assert that service doc reflects addition of collection.
        $doc = $this->getXml($this->service_url);
        $this->assertExpectedCollectionNodes(
            $test_collections,
            $doc->collections->children()
        );

        // Iterate through each collection in the collections doc and verify 
        // contents of individual empty collections.
        $nodes = $doc->collections->children();
        for ($i=0; $i<count($test_collections); $i++) {
            $test  = $test_collections[$i];
            $node  = $nodes[$i];
            $c_url = $this->resolveUrl($base_url, (string)$node['href']);

            $collection_doc = $this->getXml($c_url);
            $this->assertExpectedCollectionNodes(
                array($test), array($collection_doc)
            );
        }

    }

    /**
     * Create / delete collections with the API, verify results.
     */
    function testEmptyCollectionsWithApi()
    {
        $user_id = $this->test_user['User']['id'];

        $test_collection = array(
            'Collection' => array(
                'name'        => 'New creation',
                'description' => 'check out this new sensation!',
                'nickname'    => 'newcollection',
                'app'         => 'thunderbird',
                'listed'      => 1
            )
        );

        $this->login();
        $doc = $this->getXml($this->service_url);
        $this->assertResponse(200,
            "Service document should be found at {$this->service_url}");

        $base_url = 
            $this->getBaseUrl($this->service_url, $doc);
        $collections_url = 
            $this->resolveUrl($base_url, $doc->collections['href']);

        $this->post($collections_url, array());
        $this->assertResponse(400,
            "Missing / invalid fields should result in 400 Bad Request");

        $this->post($collections_url, array('name' => 'blah'));
        $this->assertResponse(400,
            "Missing / invalid fields should result in 400 Bad Request");

        $this->post($collections_url, array(
            'name'        => $test_collection['Collection']['name'],
            'description' => $test_collection['Collection']['description'],
            'nickname'    => $test_collection['Collection']['nickname'],
            'app'         => $test_collection['Collection']['app'],
            'listed'      => $test_collection['Collection']['listed']
        ));

        $this->assertResponse(201,
            "POST to collections URL should result in 201 Created");

        $headers = $this->getBrowserHeaders();
        $this->assertTrue(isset($headers['location']),
            'Collection creation should yield a location: header');
        $result_url = $headers['location'];

        // Ensure that the same content is found at the detail URL for the new collection.
        $resp_doc = $this->getXml($result_url);
        $this->assertExpectedCollectionNodes(array($test_collection), array($resp_doc));

        // Look for the <link> element and find expected links.
        $links = $resp_doc->links;
        $this->assertTrue(!empty($links), "Links element should be found.");
        $uuid = basename($result_url); // HACK: Carve the UUID out of the collection URL.
        $expected_links = array(
            'view'        => "collections/view/{$uuid}",
            'edit'        => "collections/edit/{$uuid}",
            'subscribe'   => "collections/subscribe/{$uuid}",
        );
        foreach ($links->link as $link) {
            $result_id = (string)$link['id'];
            $result_href = (string)$link['href'];

            $this->assertTrue(!empty($result_id),
                "Link @id = {$result_id} should not be empty");
            $this->assertTrue(!empty($result_href),
                "Link @href = {$result_href} should not be empty");
            $this->assertTrue(!empty($expected_links[$result_id]),
                "Link should be expected.");

            $this->assertEqual(
                $expected_links[$result_id], $result_href,
                "Link {$result_id} {$result_href} should match ".
                "expected {$expected_links[$result_id]}"
            );

            unset($expected_links[$result_id]);
        }
        $this->assertTrue(empty($expected_links),
            "All expected links should have been seen.");

        // Ensure that the same content is found at the service doc URL.
        $resp_doc = $this->getXml($this->service_url);
        $nodes = $resp_doc->collections->children();
        $this->assertExpectedCollectionNodes(array($test_collection), $nodes);

        // Try editing collection details.
        $this->put($result_url, array(
            'name'        => 'edited name',
            'description' => 'edited description',
            'nickname'    => 'edited nickname',
            'listed'      => 'no'
        ));
        $this->assertResponse(200,
            "PUT to update a collection should result in 200 OK");

        // Assert that the edited details are in place.
        $resp_doc = $this->getXml($result_url);
        $new_details = array(
            'Collection' => array( 
                'name'        => 'edited name',
                'description' => 'edited description',
                'nickname'    => 'edited nickname',
                'listed'      => 'no'
            )
        );
        $this->assertExpectedCollectionNodes(
            array($new_details), array($resp_doc)
        );

        // Delete the collection and ensure it's gone.
        $this->delete($result_url);
        $this->assertResponse(410,
            "DELETE to a collection resource should result in 410 Gone");
        $this->get($result_url);
        $this->assertResponse(404,
            "GET to a deleted collection resource should result in 404 Not Found");

        // Ensure that the deleted collection appears nowhere in the collections doc.
        $resp_doc = $this->getXml($collections_url);
        $nodes = $resp_doc->children();
        foreach ($nodes as $node) {
            $url = $this->resolveUrl($collections_url, $node['href']);
            $this->assertNotEqual($url, $result_url,
                'The deleted collection resource should not be found in the collections document');
        }

    }

    /**
     * Create a collection, try adding and deleting some addons.
     */
    function testCollectionAddonCrud() {
        $this->login();
        $doc = $this->getXml($this->service_url);
        $base_url = $this->getBaseUrl($this->service_url, $doc);
        $collections_url = 
            $this->resolveUrl($base_url, $doc->collections['href']);

        $this->post($collections_url, array(
            'name'        => 'fresh addons',
            'description' => 'new collection expectant for addons'
        ));

        $this->assertResponse(201,
            "POST to collections URL should result in 201 created");

        $headers = $this->getBrowserHeaders();
        $collection_url = $headers['location'];

        $collection_doc = $this->getXml($collection_url);

        $addons_url = $this->resolveUrl(
            $this->getBaseUrl($collection_url, $collection_doc),
            $collection_doc->addons['href']
        );

        $test_adds = array();
        $guids = array_slice(array_keys($this->addons), 0, 5);

        foreach ($guids as $guid) {

            $data = array(
                'guid'     => $guid,
                'comments' => "these are test notes for addon {$guid} " . rand(0,100)
            );
            $this->post($addons_url, $data);

            $this->assertResponse(201,
                "POST to collection URL ({$collection_url}) should result in 201 Created");

            $headers = $this->getBrowserHeaders();
            $this->assertTrue(isset($headers['location']),
                'Collection addon addition should yield a location: header');
            $addon_url = $headers['location'];

            $this->post($addons_url, $data);
            $this->assertResponse(409,
                "Attempt to add addon already added should result in 409 Conflict");

            $addon_doc = $this->getXml($addon_url);

            if ($addon_doc) {
                $addon_base_url = $this->getBaseUrl($addon_url, $addon_doc);
                $addon_collection_url = $this->resolveUrl(
                    $addon_base_url, @$addon_doc->meta->collection['href']
                );
            }

            // Verify a link in addon back to its collection
            $this->assertEqual(
                $collection_url, $addon_collection_url,
                'Addon doc should have a reference back to collection ' . 
                    $collection_url . ' == ' . $addon_collection_url
            );

            $this->assertEqual(
                $this->nickname, @$addon_doc->meta->addedby,
                'addedby element should match name of the user who added it '.
                '('.$this->nickname.'=='.(@$addon_doc->meta->addedby).')' 
            );

            // Verify that the addon has submitted comments.
            $this->assertEqual(
                $data['comments'], @$addon_doc->meta->comments,
                'Comments in addon doc "' . (@$addon_doc->meta->comments) . '" '.
                    'should match submitted comments "' . $data['comments'] . '"'
            );

            // Verify a addon details.
            // TODO: verify more?
            $this->assertTrue(
                $this->addons[$guid]['Translation']['name']['string'] ==
                @$addon_doc->name,
                'Name of addon in doc should match addon added'
            );
            // verify stats tracking codes in installation URL and learnmore URL
            if (!empty($addon_doc->install)) {
                $this->assertTrue(preg_match(
                    "/\?collection_id=.+&src=sharingapi/",
                    @$addon_doc->install),
                    "install URL ({$addon_doc->install}) should contain stats codes"
                );
            }
            $this->assertTrue(preg_match(
                "#addon/\d+/?\?src=sharingapi$#",
                @$addon_doc->learnmore),
                "learnmore link ({$addon_doc->learnmore}) should contain stats codes"
            );

            // Try changing the comments.
            $data['comments'] = 'this has been edited! ' . $data['comments'];
            $this->put($addon_url, array('comments' => $data['comments']));
            $this->assertResponse(200,
                "Attempt to edit addon should result in 200 OK");

            // Re-fetch the addon, ensure the comments have been changed.
            $addon_doc = $this->getXml($addon_url);
            $this->assertEqual(
                $data['comments'], (string)$addon_doc->meta->comments,
                'Comments in addon doc "' . (@$addon_doc->meta->comments) . '" '.
                    'should match submitted comments "' . $data['comments'] . '"'
            );

            $data['url'] = $addon_url;
            $test_adds[$guid] = $data;
        }

        // Now, fetch the collection document and look through its addons.
        $collection_doc = $this->getXml($collection_url);
        $nodes = $collection_doc->addons->addon;
        $this->assertEqual(
            count(array_keys($test_adds)), count($nodes),
            'Number of addons in the collection doc should reflect number added. ' .
            count(array_keys($test_adds)) . '==' . count($nodes)
        );

        foreach ($nodes as $addon_node) {

            $guid = (string)$addon_node->guid;
            $data = @$test_adds[$guid];

            if (empty($data)) {
                $this->fail(
                    'addons in collection doc should all have GUIDs '.
                    'corresponding to addons submitted'
                );
                continue;
            }

            // Verify that the addon has submitted comments.
            $this->assertEqual(
                $data['comments'], @$addon_node->meta->comments,
                'Comments in addon doc "' . (@$addon_node->meta->comments) . '" '.
                    'should match submitted comments "' . $data['comments'] . '"'
            );

            $this->assertEqual(
                $this->nickname, @$addon_node->meta->addedby,
                'addedby element should match name of the user who added it '.
                '('.$this->nickname.'=='.(@$addon_node->meta->addedby).')' 
            );

            $this->assertTrue(
                $this->addons[$guid]['Translation']['name']['string'] ==
                @$addon_node->name,
                'Name of addon in doc should match addon added'
            );

        }

        // Now try deleting all the addons and ensure that they're gone from 
        // the collection as individual documents.
        foreach ($test_adds as $guid=>$data) {
            $addon_url = $data['url'];
            $this->delete($addon_url);
            $this->assertResponse(410,
                "Attempt to delete addon successfully should result in 410 Gone");
            $this->get($addon_url);
            $this->assertResponse(404,
                "Attempt to get deleted addon should result in 404 Not Found");
        }

        // Finally, ensure that the addons are gone from the collection.
        $collection_doc = $this->getXml($collection_url);
        $nodes = $collection_doc->addon;
        $this->assertEqual(0, count($nodes),
            'No addons should remain in the collection'
        );

    }

    /**
     * Ensure that Last-Modified / If-Modified-Since headers work as 
     * conditional GET to shortcircuit full responses with 304 Not Modified 
     * when there have been no changes, and to correctly return full 200 OK 
     * responses when there have been changes.
     */
    function testConditionalGet()
    {
        $this->login();
        $doc = $this->getXml($this->service_url);

        $base_url = $this->getBaseUrl($this->service_url, $doc);
        $collections_url = 
            $this->resolveUrl($base_url, $doc->collections['href']);

        $this->post($collections_url, array(
            'name'        => 'fresh addons',
            'description' => 'new collection expectant for addons'
        ));

        $this->assertResponse(201,
            "POST to collections URL should result in 201 created");

        $headers = $this->getBrowserHeaders();
        $collection_url = $headers['location'];

        $collection_doc = $this->getXml($collection_url);

        $collection_headers = $this->getBrowserHeaders();
        $collection_modified = @$collection_headers['last-modified'];
        $this->assertTrue(!empty($collection_modified),
            'Service doc should yield a last-modified header');

        $this->setRequestHeaders(array(
            'If-Modified-Since: ' . date('r', strtotime($collection_modified))
        ));
        $resp = $this->get($collection_url);
        $this->assertResponse(304,
            "GET to unmodified collection doc should yield 304");

        $this->setRequestHeaders(array());
        $doc = $this->get($this->service_url);
        $doc_headers = $this->getBrowserHeaders();
        $doc_modified = @$doc_headers['last-modified'];
        $this->assertTrue(!empty($doc_modified),
            'Service doc should yield a last-modified header');

        $this->setRequestHeaders(array(
            'If-Modified-Since: ' . date('r', strtotime($doc_modified))   
        ));
        $resp = $this->get($this->service_url);
        $this->assertResponse(304,
            "GET to service doc with unmodified collections should yield 304");

        $this->put($collection_url, array(
            'name'        => 'edited name',
            'description' => 'edited description',
            'nickname'    => 'edited nickname',
            'listed'      => 'no'
        ));
        $this->assertResponse(200,
            "PUT to update a collection should result in 200 OK");

        $this->setRequestHeaders(array(
            'If-Modified-Since: ' . date('r', strtotime($doc_modified))   
        ));
        $resp = $this->get($this->service_url);
        $this->assertResponse(200,
            "GET to service doc since collection changed should yield 200 OK");

        $addons_url = $this->resolveUrl(
            $this->getBaseUrl($collection_url, $collection_doc),
            $collection_doc->addons['href']
        );

        $test_adds = array();
        $guids = array_slice(array_keys($this->addons), 0, 5);

        foreach ($guids as $guid) {
            $data = array(
                'guid'     => $guid,
                'comments' => "these are test notes for addon {$guid} " . rand(0,100)
            );
            $this->post($addons_url, $data);
            $this->assertResponse(201,
                "POST to addons URL ({$addons_url}) should result in 201 Created");

            $addon_headers = $this->getBrowserHeaders();
            $addon_url = $addon_headers['location'];

            $this->setRequestHeaders(array());
            $addon = $this->get($addon_url);
            $addon_headers = $this->getBrowserHeaders();
            $addon_modified = @$addon_headers['last-modified'];
            $this->assertTrue(!empty($addon_modified),
                'Addon should yield a last-modified header');

            $this->setRequestHeaders(array(
                'If-Modified-Since: ' . date('r', strtotime($addon_modified))   
            ));
            $resp = $this->get($addon_url);
            $this->assertResponse(304,
                "GET to addon doc with unmodified addon should yield 304");
            $resp = $this->get($collection_url);
            $this->assertResponse(304,
                "GET to unmodified collection doc should yield 304");
            $resp = $this->get($this->service_url);
            $this->assertResponse(304,
                "GET to unmodified service_doc doc should yield 304");

            $this->put($addon_url, array(
                'comments' => 'edited comments'
            ));

            $this->setRequestHeaders(array(
                'If-Modified-Since: ' . date('r', strtotime($addon_modified))   
            ));
            $resp = $this->get($addon_url);
            $this->assertResponse(200,
                "GET to addon doc with modified addon should yield 200");
            $resp = $this->get($collection_url);
            $this->assertResponse(200,
                "GET to collection doc should yield 200");
            $resp = $this->get($this->service_url);
            $this->assertResponse(200,
                "GET to service_doc doc should yield 200");
        }

    }

    /**
     * Ensure that valid collection types work.
     */
    function testCollectionTypes()
    {
        $this->login();
        $doc = $this->getXml($this->service_url);

        $base_url = $this->getBaseUrl($this->service_url, $doc);
        $collections_url = 
            $this->resolveUrl($base_url, $doc->collections['href']);

        $this->post($collections_url, array(
            'name'        => 'fresh addons',
            'description' => 'new collection expectant for addons'
        ));
        $headers = $this->getBrowserHeaders();
        $collection_doc = $this->getXml($headers['location']);
        $this->assertTrue($collection_doc['type'] == 'autopublisher',
            "Default type should be 'autopublisher'");

        $this->post($collections_url, array(
            'name'        => 'fresh addons',
            'description' => 'new collection expectant for addons',
            'type'        => 'normal'
        ));
        $headers = $this->getBrowserHeaders();
        $collection_doc = $this->getXml($headers['location']);
        $this->assertTrue($collection_doc['type'] == 'normal',
            "Type 'normal' should be allowed.");

        $this->post($collections_url, array(
            'name'        => 'fresh addons',
            'description' => 'new collection expectant for addons',
            'type'        => 'editorspick'
        ));
        $headers = $this->getBrowserHeaders();
        $collection_doc = $this->getXml($headers['location']);
        $this->assertTrue($collection_doc['type'] == 'editorspick',
            "Type 'editorspick' should be allowed.");

        $this->post($collections_url, array(
            'name'        => 'fresh addons',
            'description' => 'new collection expectant for addons',
            'type'        => 'spacemonkey'
        ));
        $headers = $this->getBrowserHeaders();
        $collection_doc = $this->getXml($headers['location']);
        $this->assertTrue($collection_doc['type'] == 'autopublisher',
            "Unknown type should result in default 'autopublisher'");
    }

    /**
     * Bug 486125:  Sharing API should accept names with apostrophes
     */
    function testCollectionNamesWithApostrophesShouldBeAccepted()
    {
        $this->login();
        $doc = $this->getXml($this->service_url);

        $base_url = $this->getBaseUrl($this->service_url, $doc);
        $collections_url = 
            $this->resolveUrl($base_url, $doc->collections['href']);

        $this->post($collections_url, array(
            'name'        => "Someone's collection",
            'description' => "This is also someone's collection"
        ));

        $this->assertResponse(201,
            "POST to collections URL should result in 201 Created");

        $headers = $this->getBrowserHeaders();
        $collection_url = $headers['location'];
        $collection_doc = $this->getXml($headers['location']);

        $addons_url = $this->resolveUrl(
            $this->getBaseUrl($collection_url, $collection_doc),
            $collection_doc->addons['href']
        );

        $guids = array_slice(array_keys($this->addons), 0, 5);
        foreach ($guids as $guid) {

            $data = array(
                'guid'     => $guid,
                'comments' => "these are someone's test notes for addon {$guid} " . rand(0,100)
            );
            $this->post($addons_url, $data);

            $this->assertResponse(201,
                "POST to collection URL ({$collection_url}) should result in 201 Created");

        }

    }

    /**
     * Bug 486126: Sharing API should return new resources rather than empty document
     */
    function testResourceCreationShouldRespondWithNewResourceData()
    {

        $this->login();
        $doc = $this->getXml($this->service_url);
        $base_url = $this->getBaseUrl($this->service_url, $doc);
        $collections_url = 
            $this->resolveUrl($base_url, $doc->collections['href']);

        $this->post($collections_url, array(
            'name'        => 'fresh addons',
            'description' => 'new collection expectant for addons'
        ));

        $this->assertResponse(201,
            "POST to collections URL should result in 201 created");
        $resp_doc = simplexml_load_string($this->getBrowser()->getContent());
        $this->assertTrue(!empty($resp_doc));

        $headers = $this->getBrowserHeaders();
        $collection_url = $headers['location'];
        $collection_doc = $this->getXml($collection_url);

        $this->assertEqual(
            (string)$resp_doc['name'], (string)$collection_doc['name']
        );

        $addons_url = $this->resolveUrl(
            $this->getBaseUrl($collection_url, $collection_doc),
            $collection_doc->addons['href']
        );

        $test_adds = array();
        $guids = array_slice(array_keys($this->addons), 0, 5);

        foreach ($guids as $guid) {

            $data = array(
                'guid'     => $guid,
                'comments' => "these are test notes for addon {$guid} " . rand(0,100)
            );
            $this->post($addons_url, $data);

            $this->assertResponse(201,
                "POST to collection URL ({$collection_url}) should result in 201 Created");
            $content = $this->getBrowser()->getContent();

            $resp_doc = simplexml_load_string($content);
            $this->assertTrue(!empty($resp_doc), 
                "Response document should not be empty");

            $headers = $this->getBrowserHeaders();
            $addon_url = $headers['location'];

            $addon_doc = $this->getXML($addon_url);

            $this->assertEqual(
                (string)$resp_doc->meta->comments, 
                (string)$addon_doc->meta->comments
            );

        }

    }

    /**
     * Bug 486145: Auth token resource
     */
    function testAuthResourceShouldYieldUsableAuthToken()
    {
        // Try fetching the service document, but assert that auth is required.
        $service_doc = $this->getXML($this->service_url);
        $this->assertAuthentication('Basic');
        $this->assertResponse(401);
        $this->assertEqual(
            'unauthorized', $service_doc['reason'],
            'GET to service doc without auth should yield unauthorized error'
        );
        $this->assertTrue(
            !empty($service_doc['href']), 
            'Error should offer href for auth'
        );

        $auth_url = (string)$service_doc['href'];

        // GET without auth should be 401 Unauthorized.
        $auth_doc = $this->getXML($auth_url);
        $this->assertAuthentication('Basic');
        $this->assertResponse(401);

        // Try basic auth, which should get through to a 405 Method Not Allowed
        $this->authenticate($this->username, $this->password);
        $doc = $this->getXml($auth_url);
        $this->assertResponse(405);

        // Try a post with basic auth and expect a token.
        $this->authenticate($this->username, $this->password);
        $this->post($auth_url, array());
        $content = $this->getBrowser()->getContent();
        $auth_doc = simplexml_load_string($content);
        $this->assertTrue(
            !empty($auth_doc['value']),
            "Auth doc should offer a token"
        );
        $auth_token = (string)$auth_doc['value'];
        $auth_token_url = (string)$auth_doc['href'];
        $this->restart();

        // Invalid token should result in error
        $this->setRequestHeaders(array('X-API-Auth: THISISNOTAVALIDTOKEN'));
        $service_doc = $this->getXML($this->service_url);
        $this->assertResponse(401, 
            'Invalid token should throw 401');
        $this->restart();

        // Valid token should work
        $this->setRequestHeaders(array('X-API-Auth: ' . $auth_token));
        $service_doc = $this->getXML($this->service_url);
        $this->assertResponse(200, 
            'Valid token should yield 200');
        $this->restart();

        // Valid token URL should work.
        $this->setRequestHeaders(array('X-API-Auth: ' . $auth_token_url));
        $service_doc = $this->getXML($this->service_url);
        $this->assertResponse(200, 
            'Valid token URL should yield 200');
        $this->restart();

        // Try deleting the token to invalidate it
        $this->setRequestHeaders(array('X-API-Auth: ' . $auth_token));
        $this->delete($auth_token_url);
        $this->assertResponse(410, 
            'DELETE of an auth URL should result in 410');
        $this->restart();

        // Token should no longer be accepted.
        $this->setRequestHeaders(array('X-API-Auth: ' . $auth_token));
        $service_doc = $this->getXML($this->service_url);
        $this->assertResponse(401, 
            'Token should no longer be accepted after DELETE');
        $this->restart();
    }

    /**
     * bug 487397: Ensure that collections have default icons.
     */
    function testCollectionDefaultIconUrl()
    {
        $expected_default_image = $this->rawURI("/img/collection.png");
        
        // Login and create a collection.
        $this->login();
        $doc = $this->getXml($this->service_url);
        $base_url = $this->getBaseUrl($this->service_url, $doc);
        $collections_url = 
            $this->resolveUrl($base_url, $doc->collections['href']);

        $this->post($collections_url, array(
            'name'        => 'fresh addons',
            'description' => 'new collection expectant for addons'
        ));

        // Ensure the icon appears in the new response.
        $resp_doc = simplexml_load_string($this->getBrowser()->getContent());
        $this->assertEqual(
            (string)$resp_doc['icon'], 
            $expected_default_image
        );

        // Grab the new collection and look for the icon.
        $headers = $this->getBrowserHeaders();
        $collection_url = $headers['location'];
        $collection_doc = $this->getXml($collection_url);
        $this->assertEqual(
            (string)$collection_doc['icon'], 
            $expected_default_image
        );

        // Reload the service doc and look for the icon in the new collection.
        $service_doc = $this->getXml($this->service_url);
        $this->assertEqual(
            (string)$service_doc->collections->collection[0]['icon'], 
            $expected_default_image
        );
    }

    /**
     * Bug 488154: Ensure that collections can have custom icons
     */
    function testCollectionCustomIconUrl()
    {
        $expected_icon_pattern = '#/images/collection_icon/\d+#';
        $iconfile = ROOT.DS.APP_DIR.DS.WEBROOT_DIR.DS.'img'.DS.'collection.png';

        // Login and create a collection.
        $this->login();
        $doc = $this->getXml($this->service_url);
        $base_url = $this->getBaseUrl($this->service_url, $doc);
        $collections_url =
            $this->resolveUrl($base_url, $doc->collections['href']);

        $this->postMultipart($collections_url, array(
            'name'        => 'fresh addons',
            'description' => 'new collection expectant for addons'
        ), array(
            'icon'        => $iconfile // attach icon
        ));

        // Ensure the icon appears in the new response.
        $resp_doc = simplexml_load_string($this->getBrowser()->getContent());
        $this->assertTrue(preg_match(
            $expected_icon_pattern,
            (string)$resp_doc['icon']), 'custom icon in "new" response'
        );

        // Grab the new collection and look for the icon.
        $headers = $this->getBrowserHeaders();
        $collection_url = $headers['location'];
        $collection_doc = $this->getXml($collection_url);
        $this->assertTrue(preg_match(
            $expected_icon_pattern,
            (string)$collection_doc['icon']),
            'custom icon in collection details'
        );

        // Reload the service doc and look for the icon in the new collection.
        $service_doc = $this->getXml($this->service_url);
        $this->assertTrue(preg_match(
            $expected_icon_pattern,
            (string)$service_doc->collections->collection[0]['icon']),
            'custom icon in service doc'
        );
    }

    /**
     * Bug 486142: Collection subscribe / unsubscribe
     */
    function testSubscriptions()
    {
        $collections = $this->createTestCollectionsWithMoreUsers();

        $collection_urls = array();

        // First, login as all known test users and discover all known collections by URL.
        foreach ($this->all_test_users as $user) {
            $this->login($user['User']['email'], 'test');
            $service_doc = $this->getXml($this->service_url);
            $base_url = $this->getBaseUrl($this->service_url, $service_doc);
            $collections_url = 
                $this->resolveUrl($base_url, $service_doc->collections['href']);
            foreach ($service_doc->collections->children() as $node) {
                $collection_urls[$this->resolveUrl(
                    $base_url, $node['href']
                )] = 1;
            }
        }
        $collection_urls = array_keys($collection_urls);

        $this->login();

        $service_doc = $this->getXml($this->service_url);
        $base_url = $this->getBaseUrl($this->service_url, $service_doc);
        $this->assertEqual(
            1, count($service_doc->collections->children()),
            'Main test user should only see one known collection, initially'
        );
        $this->assertEqual(
            'no', (string)$service_doc->collections->collection[0]['subscribed'],
            'Initial known collection should not yet be subscribed'
        );

        $expected_count = 0;
        foreach ($collection_urls as $collection_url) {
            $expected_count++;
            $this->put($collection_url, array('subscribed' => 'yes'));
            $this->assertResponse(200,
                "PUT to set collection 'subscribed' flag should yield 200 OK, ".
                "regardless of writable permission");
        }

        $service_doc = $this->getXml($this->service_url);
        $base_url = $this->getBaseUrl($this->service_url, $service_doc);
        $result_count = count($service_doc->collections->children());
        $this->assertEqual(
            $expected_count, $result_count,
            "Main test user should now see {$expected_count} collections ".
            "(saw {$result_count})"
        );

        foreach ($service_doc->collections->collection as $collection) {
            $this->assertEqual(
                'yes', (string)$collection['subscribed'],
                'Known collections should be subscribed now'
            );
            $found_unsubscribe = false;
            foreach ($collection->links->link as $link) {
                if ('unsubscribe' == (string)$link['id']) {
                    $found_unsubscribe = true;
                }
                if ('subscribe' == (string)$link['id']) {
                    $this->fail("No subscribe link should appear on subscribed collection");
                }
            }
            $this->assertTrue($found_unsubscribe, "Unsubscribe link should be found.");
        }

        foreach ($collection_urls as $idx=>$collection_url) {
            if (0 == $idx) {
                $this->put($collection_url, array('name' => 'this should work'));
                $this->assertResponse(200,
                    "PUT on the main test user's collection should work");
            } else {
                $this->put($collection_url, array('name' => 'this should not work'));
                $this->assertResponse(403,
                    "PUT of any other properties should fail on other's collections");
            }
        }

        foreach ($collection_urls as $collection_url) {
            $this->put($collection_url, array('subscribed' => 'no'));
            $this->assertResponse(200,
                "PUT to set collection 'subscribed' flag should yield 200 OK, ".
                "regardless of writable permission");
        }

        $service_doc = $this->getXml($this->service_url);
        $base_url = $this->getBaseUrl($this->service_url, $service_doc);
        $this->assertEqual(
            1, count($service_doc->collections->children()),
            'Main test user should only see one known collection after unsubscribing'
        );
        $this->assertEqual(
            'no', (string)$service_doc->collections->collection[0]['subscribed'],
            'Initial known collection should not yet be subscribed'
        );


    }

    /**
     * Bug 487427: Ensure proper comments and added-by appear for addons 
     * appearing in multiple collections
     */
    function testAddonCommentsAndAddedByAssociatedWithCorrectUsers()
    {
        $collections = $this->createTestCollectionsWithMoreUsers();

        $collection_urls = array();

        $addon_guids = array_keys($this->addons);
        $addon_guid = $addon_guids[0];

        foreach ($this->all_test_users as $user) {
            $this->login($user['User']['email'], 'test');
            
            $service_doc = $this->getXml($this->service_url);
            $base_url = $this->getBaseUrl($this->service_url, $service_doc);
            $collections_url = $this->resolveUrl($base_url, $service_doc->collections['href']);

            $node = $service_doc->collections->collection[0];

            $collection_url = $this->resolveUrl($base_url, $node['href']);
            $collection_doc = $this->getXml($collection_url);

            $addons_url = $this->resolveUrl(
                $this->getBaseUrl($collection_url, $collection_doc),
                $collection_doc->addons['href']
            );

            $data = array(
                'guid'     => $addon_guid,
                'comments' => "notes for addon {$addon_guid} by {$user['User']['email']}"
            );
            $this->post($addons_url, $data);
        }

        foreach ($this->all_test_users as $user) {
            $this->login($user['User']['email'], 'test');
            
            $service_doc = $this->getXml($this->service_url);
            $base_url = $this->getBaseUrl($this->service_url, $service_doc);
            $collections_url = $this->resolveUrl($base_url, $service_doc->collections['href']);

            $node = $service_doc->collections->collection[0];

            $collection_url = $this->resolveUrl($base_url, $node['href']);
            $collection_doc = $this->getXml($collection_url);

            $addon = $collection_doc->addons->addon[0];

            $this->assertEqual(
                $addon->guid, $addon_guid, 
                "Addon GUID should match what was added"
            );
            
            $this->assertEqual(
                $addon->meta->comments, 
                "notes for addon {$addon_guid} by {$user['User']['email']}", 
                "Addon comments should match what was added -- " .
                    " {$addon->meta->comments} != ".
                    "notes for addon {$addon_guid} by {$user['User']['email']}"
            );

            $user_display_name = !empty($user['User']['nickname']) ?
                $user['User']['nickname'] : 
                "{$user['User']['firstname']} {$user['User']['lastname']}";
            $this->assertEqual(
                $addon->meta->addedby, $user_display_name,
                "Addon user name should match what was added " .
                    " {$addon->meta->addedby} != {$user_display_name}"
            );
            
        }

    }

    /**
     * Bug 494317: Ensure that experimental addons in collections offer
     * an <install> element.
     */
    function testExperimentalAddonInstallInCollections()
    {
        // Assemble a list of unique addon GUIDs, at least one of which is
        // experimental.
        $addon_guids = array(
            // Known experimental addon in test data
            '{A17C1C5A-04C1-11DB-9805-B632A1EF5496}',
            // Other known addons with files available
            'farming@microfarmer.org',
            'fisher@farmerland.org',
            'hunter@farmerland.org',
        );

        // Login and get a handle on the collections URL
        $this->login();
        $doc = $this->getXml($this->service_url);
        $base_url = $this->getBaseUrl($this->service_url, $doc);
        $collections_url =
            $this->resolveUrl($base_url, $doc->collections['href']);

        // Create the new collection.
        $this->post($collections_url, array(
            'name'        => 'experiments included',
            'description' => 'this collection includes experimental addons'
        ));
        $headers = $this->getBrowserHeaders();
        $collection_url = $headers['location'];
        $collection_doc = $this->getXml($collection_url);
        $addons_url = $this->resolveUrl(
            $this->getBaseUrl($collection_url, $collection_doc),
            $collection_doc->addons['href']
        );

        // Add the known addons to the collection.
        foreach ($addon_guids as $guid) {
            $data = array(
                'guid'     => $guid,
                'comments' => "these are test notes for addon {$guid} " . rand(0,100)
            );
            $this->post($addons_url, $data);
        }

        // Now, fetch the collection document and look through its addons.
        $collection_doc = $this->getXml($collection_url);
        $nodes = $collection_doc->addons->addon;

        // Assert that install elements exist on all addons in the collection
        foreach ($nodes as $addon_node) {
            $this->assertTrue(
                !empty($addon_node->install),
                "Addon {$addon_node->guid} in collection should have install element"
            );
        }
    }

    function testEmailSharing()
    {
        $this->fail('TODO: Test out email sharing');
    }

    function testUserRolesAuthorization()
    {
        $this->fail('TODO: Ensure writable flag works in collections');
        $this->fail('TODO: Ensure editing / deletion of collections forbidden without proper roles');
        $this->fail('TODO: Ensure adding / removing addons in collections forbidden without proper roles');
    }

    /**
     * Set request headers for the next request.
     */
    function setRequestHeaders($headers) {
        // HACK: The browser / user agent offers no way to replace headers 
        // between requests, so digging into the actual array maintained by the 
        // user agent itself.
        $user_agent = $this->getBrowser()->_user_agent;
        // Store any existing headers on the user_agent so things like
        // X-AMO-TEST aren't broken.
        if (!isset($user_agent->_original_headers)) {
            $user_agent->_original_headers = $user_agent->_additional_headers;
        }
        $user_agent->_additional_headers = array_merge($user_agent->_original_headers, $headers);
    }

    /**
     * Issue a GET request for XML at a URL
     *
     * @param string URL
     * @return SimpleXML
     */
    function getXml($url) {
        $this->get($url);
        $resp_doc = simplexml_load_string($this->getBrowser()->getContent());
        if (!$resp_doc) {
            return $this->fail('failed to parse document at ' . $url);
        }
        return $resp_doc;
    }

    /**
     * Get raw headers from the scriptable browser and parse into an assoc 
     * array.
     */
    function getBrowserHeaders() {
        $headers = array();
        foreach (split("\r\n", $this->getBrowser()->getHeaders()) as $part) {
            if (FALSE !== ($pos = strpos($part, ': '))) {
                $headers[strtolower(substr($part, 0, $pos))] = 
                    substr($part, $pos+2, strlen($part)-($pos+2));
            }
        }
        return $headers;
    }

    /**
     * Create a set of test collections in the DB and return an array of the 
     * records created.
     */
    function createTestCollections()
    {
        $user_id = $this->test_user['User']['id'];
        $test_collections = array(
            array(
                'Collection' => array(
                    'name'        => 'Testing 1',
                    'description' => 'first test collection',
                    'nickname'    => 'test1',
                    'listed'      => 1
                )
            ),
            array(
                'Collection' => array(
                    'name'        => 'Testing 2',
                    'description' => 'second test collection',
                    'nickname'    => 'test2',
                    'listed'      => 1
                )
            ),
            array(
                'Collection' => array(
                    'name'        => 'Testing 3',
                    'description' => 'third test collection',
                    'nickname'    => 'test3',
                    'listed'      => 1
                )
            )
        );
        foreach ($test_collections as $collection) {
            $this->Collection->create();
            $this->Collection->save($collection);
            $new_id = $this->Collection->id;
            $this->Collection->addUser(
                $new_id, $user_id, COLLECTION_ROLE_ADMIN
            );
        }
        return $test_collections;
    }

    /**
     * Create test collections, but with different owners than the main test 
     * user.
     *
     * @return array List of collections created in model
     */
    function createTestCollectionsWithMoreUsers()
    {
        $test_collections = array(
            array( 
                $this->test_user,
                array(
                    'Collection' => array(
                        'name'        => 'Testing 0',
                        'description' => 'zeroth test collection',
                        'nickname'    => 'test0',
                        'listed'      => 1
                    )
                )
            ),
            array( 
                $this->test_user_2,
                array(
                    'Collection' => array(
                        'name'        => 'Testing 1',
                        'description' => 'first test collection',
                        'nickname'    => 'test1',
                        'listed'      => 1
                    )
                )
            ),
            array( 
                $this->test_user_3,
                array(
                    'Collection' => array(
                        'name'        => 'Testing 2',
                        'description' => 'second test collection',
                        'nickname'    => 'test2',
                        'listed'      => 1
                    )
                )
            ),
            array( 
                $this->test_user_2,
                array(
                    'Collection' => array(
                        'name'        => 'Testing 3',
                        'description' => 'third test collection',
                        'nickname'    => 'test3',
                        'listed'      => 1
                    )
                )
            ),
            array( 
                $this->test_user_3,
                array(
                    'Collection' => array(
                        'name'        => 'Testing 4',
                        'description' => 'fourth test collection',
                        'nickname'    => 'test4',
                        'listed'      => 1
                    )
                )
            ),
            array( 
                $this->test_user_4,
                array(
                    'Collection' => array(
                        'name'        => 'Testing 5',
                        'description' => 'fifth test collection',
                        'nickname'    => 'test5',
                        'listed'      => 1
                    )
                )
            ),
        );

        $collections = array();
        foreach ($test_collections as $item) {
            list($user, $collection) = $item;
            $this->Collection->create();
            $this->Collection->save($collection);
            $new_id = $this->Collection->id;
            $this->Collection->addUser(
                $new_id, $user['User']['id'], COLLECTION_ROLE_ADMIN
            );
            $collections[] = $this->Collection->findById($new_id);
        }

        return $collections;
    }

    /**
     * Given a set of test collections and simplexml nodes from a collection 
     * document, assert that they match.
     *
     * @param mixed set of test collections
     * @param mixed set of simplexml nodes from a collection document
     */
    function assertExpectedCollectionNodes($test_collections, $nodes) {
        global $app_shortnames;

        $this->assertEqual(count($test_collections), count($nodes),
            'expected count of collections '.
                '('.count($test_collections).'=='.count($nodes).')'
        );

        for ($i=0; $i<count($test_collections); $i++) {
            $result    = $nodes[$i];
            $test_data = $test_collections[$i]['Collection'];
            $test_user = $this->User->findById($this->test_user['User']['id']);

            $expected  = array(
                'name'        => $test_data['name'],
                'description' => $test_data['description'],
                'creator'     => isset($test_user['User']['nickname']) ?
                    $test_user['User']['nickname'] : 
                    "{$test_user['User']['firstname']} {$test_user['User']['lastname']}"
            );
            if (isset($test_data['app'])) {
                $expected['app'] = $test_data['app'];
            }

            foreach ($expected as $k => $v) {
                $this->assertEqual($v, $result[$k], "{$v} == {$result[$k]}");
            }

        }

    }
    
   /**
    * Logs in with test account info.
    */
    function login($username=null, $password=null) {
        if (null==$username) $username = $this->username;
        if (null==$password) $password = $this->password;

        $this->restart();

        // HACK: Not sure why, but the web tester seems to require an initial 
        // 401 to get the idea that Basic auth is needed.
        $this->get($this->service_url);
        $this->assertAuthentication('Basic');
        $this->assertResponse(401);

        $this->authenticate($username, $password);
    }

    /**
     * Given a default base URL and a SimpleXML document, return a base URL.
     */
    function getBaseUrl($base_url, $doc) {
        if (empty($doc)) return null;
        $attrs = $doc->attributes(self::XML_NS);
        if (!empty($attrs['base'])) 
            $base_url = $this->resolveUrl($base_url, (string)$attrs['base']);
        return $base_url;
    }

    /**
     * Given a base URL and a relative URL, produce an absolute URL.
     * see: http://us.php.net/manual/en/function.parse-url.php#76979
     */
    function resolveUrl($base, $url) {
        if (!strlen($base)) return $url;
        if (!strlen($url)) return $base;
        if (preg_match('!^[a-z]+:!i', $url)) return $url;

        $base = parse_url($base);
        if ($url{0} == "#") {
            $base['fragment'] = substr($url, 1);
            return $this->unparseUrl($base);
        }
        unset($base['fragment']);
        unset($base['query']);

        if (substr($url, 0, 2) == "//") {
            return $this->unparseUrl(array(
                'scheme'=>$base['scheme'],
                'path'=>substr($url,2),
            ));
        } else if ($url{0} == "/") {
            $base['path'] = $url;
        } else {
            $path = explode('/', $base['path']);
            $url_path = explode('/', $url);
            array_pop($path);
            $end = array_pop($url_path);
            foreach ($url_path as $segment) {
                if ($segment == '.') {
                    // skip
                } else if ($segment == '..' && $path && $path[sizeof($path)-1] != '..') {
                    array_pop($path);
                } else {
                    $path[] = $segment;
                }
            }
            if ($end == '.') {
                $path[] = '';
            } else if ($end == '..' && $path && $path[sizeof($path)-1] != '..') {
                $path[sizeof($path)-1] = '';
            } else {
                $path[] = $end;
            }
            $base['path'] = join('/', $path);

        }
        return $this->unparseUrl($base);
    }

    /**
     * Given the results of parse_url, produce a URL.
     * see: http://us.php.net/manual/en/function.parse-url.php#85963
     */
    function unparseUrl($parsed)
    {
        if (!is_array($parsed)) return false;

        $uri = isset($parsed['scheme']) ? $parsed['scheme'].':'.((strtolower($parsed['scheme']) == 'mailto') ? '' : '//') : '';
        $uri .= isset($parsed['user']) ? $parsed['user'].(isset($parsed['pass']) ? ':'.$parsed['pass'] : '').'@' : '';
        $uri .= isset($parsed['host']) ? $parsed['host'] : '';
        $uri .= isset($parsed['port']) ? ':'.$parsed['port'] : '';

        if (isset($parsed['path'])) {
            $uri .= (substr($parsed['path'], 0, 1) == '/') ?
                $parsed['path'] : ((!empty($uri) ? '/' : '' ) . $parsed['path']);
        }

        $uri .= isset($parsed['query']) ? '?'.$parsed['query'] : '';
        $uri .= isset($parsed['fragment']) ? '#'.$parsed['fragment'] : '';

        return $uri; 
    }

}
