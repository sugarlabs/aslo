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
 *   l.m.orchard <lorchard@mozilla.com> (Original Author)
 *   Frederic Wenzel <fwenzel@mozilla.com>
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

uses('sanitize');

/**
 * Controller implementing addon sharing API
 * see: https://wiki.mozilla.org/User:LesOrchard/BandwagonAPI
 */
class SharingApiController extends AppController
{
    var $name = 'SharingApi';

    // bump for new releases
    // 0 or unspecified is for Fx3b3
    // 0.9 is for Fx3b4
    var $newest_api_version = 1.4;

    var $beforeFilter = array(
        '_checkSandbox'
    );
    var $uses = array(
        'Addon', 'AddonCollection', 'Addontype', 'ApiAuthToken',
        'Application', 'Collection', 'File', 'Platform', 'Category', 'Translation', 
        'UpdateCount', 'Version'
    );
    var $components = array(
        'Amo', 'Developers', 'Email', 'Image', 'Pagination', 'Search', 'Session',
        'Versioncompare'
    );
    var $helpers = array(
        'Html', 'Link', 'Time', 'Localization', 'Ajax', 'Number',
        'Pagination'
    );

    var $securityLevel = 'low';

    const STATUS_OK                 = '200 OK';
    const STATUS_CREATED            = '201 Created';
    const STATUS_ACCEPTED           = '202 Accepted';
    const STATUS_FOUND              = '302 Found';
    const STATUS_SEE_OTHER          = '303 See Other';
    const STATUS_NOT_MODIFIED       = '304 Not Modified';
    const STATUS_BAD_REQUEST        = '400 Bad Request';
    const STATUS_UNAUTHORIZED       = '401 Unauthorized';
    const STATUS_FORBIDDEN          = '403 Forbidden';
    const STATUS_NOT_FOUND          = '404 Not Found';
    const STATUS_METHOD_NOT_ALLOWED = '405 Method Not Allowed';
    const STATUS_CONFLICT           = '409 Conflict';
    const STATUS_GONE               = '410 Gone';
    const STATUS_UNSUPPORTED_MEDIA  = '415 Unsupported Media Type';
    const STATUS_ERROR              = '500 Internal Server Error';

    var $cache_lifetime = 0; // 0 seconds

    function forceCache() {
        header('Cache-Control: public, max-age=' . $this->cache_lifetime);
        header('Vary: X-API-Auth');
        header('Last-Modified: ' . gmdate("D, j M Y H:i:s", $this->last_modified) . " GMT");
        header('Expires: ' . gmdate("D, j M Y H:i:s", $this->last_modified + $this->cache_lifetime) . " GMT");
    }

    function beforeFilter() {
        Configure::write('Session.checkAgent', false);

        $this->last_modified = time();

        $this->layout = 'rest';

        if (!$this->isWriteHttpMethod()) {
            // Only force shadow DB on reads.
            $this->forceShadowDb();
        }

        // HACK: No cache invalidation on write, so disable caching on these
        // models for now.
        $this->Collection->caching = false;
        $this->AddonCollection->caching = false;
        $this->User->caching = false;
        $this->ApiAuthToken->caching = false;

        $this->SimpleAuth->enabled = false;
        $this->SimpleAcl->enabled = false;

        // extract API version
        $url = $_SERVER['REQUEST_URI'];

        $matches = array();
        if (preg_match('/api\/([\d\.]*)\//', $url, $matches)) {
            $this->api_version = $matches[1];
            if (!is_numeric($this->api_version)) {
                $this->api_version = $this->newest_api_version;
            }
        } else {
           // nothing supplied: assume Fx3b3
            $this->api_version = 0;
        }

        // set up translation table for os names
        // this is hardcoded in
        $this->os_translation = array(
            'ALL' => 'ALL',
            'bsd' => 'BSD_OS',
            'BSD' => 'BSD_OS',
            'Linux' => 'Linux',
            'macosx' => 'Darwin',
            'MacOSX' => 'Darwin',
            'Solaris' => 'SunOS',
            'win' => 'WINNT',
            'Windows' => 'WINNT',
        );

        // Establish a base URL for this request.
        $this->base_url = ( empty($_SERVER['HTTPS']) ? 'http' : 'https' ) .
            '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        if ( ($qpos = strpos($this->base_url, '?')) !== FALSE) {
            // Toss out any query parameters.
            $this->base_url = substr($this->base_url, 0, $qpos);
        }
        $this->publish('base_url', $this->base_url);

        $pos = strpos($this->base_url, 'api/');
        $this->site_base_url = substr($this->base_url, 0, $pos);
        $this->publish('site_base_url', $this->site_base_url);

        // Attempt to get an auth user.
        $this->auth_user = $this->getAuthUser();
        if (!$this->auth_user) {
            header('HTTP/1.1 401 Unauthorized');
            header('WWW-Authenticate: Basic realm="AMO"');
            $this->publish('reason', 'unauthorized');
            $this->publish('href', $this->base_url . 'auth');
            $this->render('error');
            exit();
        }
        $this->publish('auth_user', $this->auth_user);
    }

    /**
     * Service doc resource dispatch
     */
    function service_doc() {
        $args = func_get_args();
        return $this->dispatchHttpMethod(array(
            'GET'  => 'service_doc_GET',
        ), $args);
    }

    /**
     * Service doc resource
     */
    function service_doc_GET($context) {
        extract($context);

        $this->User->bindModel(array(
            'hasAndBelongsToMany' => array(
                'Collections' =>
                    $this->User->hasAndBelongsToMany_full['Collections'],
                'CollectionSubscriptions' =>
                    $this->User->hasAndBelongsToMany_full['CollectionSubscriptions'],
            )
        ));
        $user = $this->User->findById($this->auth_user['id']);

        $collection_ids = array();
        foreach ($user['Collections'] as $row)
            $collection_ids[$row['id']] = 1;
        foreach ($user['CollectionSubscriptions'] as $row)
            $collection_ids[$row['id']] = 1;
        $collection_ids = array_keys($collection_ids);

        // Collect last-modified times for collections known to the user.
        $modifieds = array();
        foreach ($collection_ids as $id)
            $modifieds[] =
                $this->Collection->getLastModifiedForCollection($id);

        if (!empty($modifieds)) {
            // Set last-modified to newest stamp available.
            rsort($modifieds);
            $this->last_modified = $modifieds[0];
            if ($this->isNotModifiedSince()) return;
        }

        $collections = $this->publishCollections(
            'collections', $collection_ids
        );
    }

    /**
     * Dispatcher for collections resource.
     */
    function collections() {
        $args = func_get_args();
        return $this->dispatchHttpMethod(array(
            'POST' => 'collections_POST'
        ), $args);
    }

    /**
     * Create a collection
     */
    function collections_POST($context) {
        global $app_shortnames;

        extract($context);

        $params = $this->getParams(array(
            'name'        => '',
            'description' => '',
            'nickname'    => '',
            'type'        => 'autopublisher',
            'app'         => 'firefox',
            'listed'      => 1
        ));

        // Convert from a type name to a type constant.
        switch ($params['type']) {
            case 'normal':
                $type = Collection::COLLECTION_TYPE_NORMAL; break;
            case 'editorspick':
                $type = Collection::COLLECTION_TYPE_EDITORSPICK; break;
            case 'autopublisher':
            default:
                $type = Collection::COLLECTION_TYPE_AUTOPUBLISHER; break;
        };

        // Convert app name to a constant
        if (!empty($app_shortnames[$params['app']])) {
            $appid = $app_shortnames[$params['app']];
        } else {
            $appid = APP_FIREFOX;
        }

        $data = array(
            'Collection' => array(
                'name'            => $params['name'],
                'description'     => $params['description'],
                'nickname'        => $params['nickname'],
                'collection_type' => $type,
                'application_id'  => $appid,
                'defaultlocale'   => LANG, // defaults to current lang
                'listed' =>
                    ($params['listed'] === '1' || $params['listed'] == 'yes') ?
                    1 : 0
            )
        );
        $this->Amo->clean($data);

        // handle icon upload
        if (!empty($_FILES['icon']['name'])) {
            $iconData = $this->Developers->validateIcon($_FILES['icon']);
            if (is_array($iconData)) {
                $data['Collection'] = array_merge($data['Collection'], $iconData);
            }
        }

        if (!$this->Collection->validates($data) ||
                !$this->Collection->save($data)) {
            $invalid = $this->Collection->invalidFields();
            return $this->renderStatus(
                self::STATUS_BAD_REQUEST, 'error',
                array(
                    'reason'  => 'invalid_parameters',
                    'details' => join(',', array_keys($invalid))
                )
            );
        }

        $new_collection = $this->Collection->findById($this->Collection->id);

        // Make the auth user a manager of this new collection.
        $this->Collection->addUser(
            $this->Collection->id, $this->auth_user['id'], COLLECTION_ROLE_ADMIN
        );

        $new_url = ( empty($_SERVER['HTTPS']) ? 'http' : 'https' ) .
            '://' . $_SERVER['HTTP_HOST'] .
            $this->resolveUrl($_SERVER['REQUEST_URI'], $new_collection['Collection']['uuid'].'/');
        $this->publish('base_url', $new_url);

        $collections = $this->publishCollections(
            'collections', array( $new_collection['Collection']['id'] ), true
        );
        return $this->renderStatus(
            self::STATUS_CREATED, 'collection_detail', array(), $new_url
        );
    }

    /**
     * Dispatcher for collection detail resource.
     */
    function collection_detail($uuid) {

        // Attempt to set the last-modified header, throwing a 404 if no
        // last-modified could be found.
        $this->last_modified =
            $this->Collection->getLastModifiedForCollection(null, $uuid);
        if (null === $this->last_modified) {
            return $this->renderStatus(
                self::STATUS_NOT_FOUND, 'error',
                array('reason' => 'collection_unknown')
            );
        }
        if ($this->isNotModifiedSince()) return;

        // Try to find the collection by the ID in the URL.  Kick out a 404
        // error if not found.
        $collection = $this->Collection->findByUuid($uuid);
        if (empty($collection)) {
            return $this->renderStatus(
                self::STATUS_NOT_FOUND, 'error',
                array('reason' => 'collection_unknown')
            );
        }

        $writable = $this->Collection->isWritableByUser(
            $collection['Collection']['id'], $this->auth_user['id']
        );
        $method = $this->getHttpMethod();
        if (('PUT' !== $method) && $this->isWriteHttpMethod()) {
            if (!$writable) {
                return $this->renderStatus(
                    self::STATUS_FORBIDDEN, 'error',
                    array('reason' => 'not_writable')
                );
            }
        }

        $args = func_get_args();
        return $this->dispatchHttpMethod(
            array(
                'GET'    => 'collection_detail_GET',
                'PUT'    => 'collection_detail_PUT',
                'DELETE' => 'collection_detail_DELETE'
            ),
            $args,
            compact('collection', 'writable')
        );
    }

    /**
     * Produce collection details.
     */
    function collection_detail_GET($context, $uuid) {
        extract($context);

        $collections = $this->publishCollections(
            'collections', array( $collection['Collection']['id'] ), true
        );
    }

    /**
     * Update details in a collection.
     */
    function collection_detail_PUT($context) {
        extract($context);

        $params = $this->getParams(array(
            'name'        => $collection['Translation']['name']['string'],
            'description' => $collection['Translation']['description']['string'],
            'nickname'    => $collection['Collection']['nickname'],
            'listed'      => $collection['Collection']['listed'],
            'subscribed'  => NULL
        ));

        // Whether the user can write to this collection in general, they
        // should be able to modify their own subscription status...
        switch ($params['subscribed']) {

            case 'yes':
                // Subscribe the user to the collection on subscribed=yes
                $this->Collection->subscribe(
                    $collection['Collection']['id'],
                    $this->auth_user['id']
                );
                break;

            case 'no':
                // Unsubscribe the user from the collection on subscribed=no
                $this->Collection->unsubscribe(
                    $collection['Collection']['id'],
                    $this->auth_user['id']
                );
                break;

            case NULL:
            default:
                // If this collection is not writable, the user has no business
                // trying to change anything beyond the subscribed flag.
                if (!$writable) {
                    return $this->renderStatus(
                        self::STATUS_FORBIDDEN, 'error',
                        array('reason' => 'not_writable')
                    );
                }
                break;

        }

        if ($writable) {
            // Beyond subscription changes above, accept further changes to
            // collection if the user is allowed.

            $data = array(
                'Collection' => array(
                    'id'          => $collection['Collection']['id'],
                    'name'        => $params['name'],
                    'description' => $params['description'],
                    'nickname'    => $params['nickname'],
                    'listed'      => ($params['listed'] === '1' || $params['listed'] == 'yes') ? 1 : 0
                )
            );
            $this->Amo->clean($data);

            if (!$this->Collection->validates($data) ||
                    !$this->Collection->save($data)) {
                $invalid = $this->Collection->invalidFields();
                return $this->renderStatus(
                    self::STATUS_BAD_REQUEST, 'error',
                    array(
                        'reason'  => 'invalid_parameters',
                        'details' => join(',', array_keys($invalid))
                    )
                );
            }

        }

        $collections = $this->publishCollections(
            'collections', array( $collection['Collection']['id'] ), true
        );
    }

    /**
     * Delete a collection.
     */
    function collection_detail_DELETE($context, $uuid) {
        extract($context);
        $this->Collection->del($collection['Collection']['id']);
        return $this->renderStatus(self::STATUS_GONE, 'empty');
    }

    /**
     * Dispatcher for collection detail resource.
     */
    function collection_addons($uuid) {

        // Try to find the collection by the ID in the URL.  Kick out a 404
        // error if not found.
        $collection = $this->Collection->findByUuid($uuid);
        if (empty($collection)) {
            return $this->renderStatus(
                self::STATUS_NOT_FOUND, 'error',
                array('reason' => 'collection_unknown')
            );
        }

        if ($this->isWriteHttpMethod()) {
            $writable = $this->Collection->isWritableByUser(
                $collection['Collection']['id'], $this->auth_user['id']
            );
            if (!$writable) {
                return $this->renderStatus(
                    self::STATUS_FORBIDDEN, 'error',
                    array('reason' => 'not_writable')
                );
            }
        }

        $args = func_get_args();
        return $this->dispatchHttpMethod(
            array(
                'POST' => 'collection_addons_POST'
            ),
            $args,
            compact('collection')
        );
    }

    /**
     * Produce collection details.
     */
    function collection_addons_POST($context, $uuid) {
        extract($context);

        $params = $this->getParams(array(
            'guid'     => '',
            'comments' => ''
        ));

        // Look for the addon, throwing an error if there's none found.
        $this->Addon->bindOnly('User', 'Category');
        $addon = $this->Addon->findByGuid($params['guid']);
        if (empty($addon)) {
            return $this->renderStatus(
                self::STATUS_BAD_REQUEST, 'error',
                array('reason' => 'unknown_addon_guid')
            );
        }

        // Check to see if this addon already exists in the collection, and
        // throw an error if so.
        $added = $this->AddonCollection->find(array(
            'AddonCollection.collection_id' =>
                $collection['Collection']['id'],
            'AddonCollection.addon_id' =>
                $addon['Addon']['id'],
        ), null, null, 1);
        if (!empty($added)) {
            return $this->renderStatus(
                self::STATUS_CONFLICT, 'error',
                array('reason' => 'addon_already_in_collection')
            );
        }

        // Build the fields for adding the addon to the collection.
        $data = array(
            'AddonCollection' => array(
                'collection_id' => $collection['Collection']['id'],
                'user_id'       => $this->auth_user['id'],
                'addon_id'      => $addon['Addon']['id'],
                'added'         => date('c'),
                'comments'      => $params['comments']
            )
        );
        $this->Amo->clean($data);

        // Validate the fields.
        if (!$this->AddonCollection->validates($data)) {
            $invalid = $this->AddonCollection->invalidFields();
            return $this->renderStatus(
                self::STATUS_BAD_REQUEST, 'error',
                array(
                    'reason'  => 'invalid_parameters',
                    'details' => join(',', array_keys($invalid))
                )
            );
        }

        // Finally, try adding the addon.
        if (!$this->AddonCollection->save($data)) {
            $invalid = $this->AddonCollection->invalidFields();
            return $this->renderStatus(
                self::STATUS_BAD_REQUEST, 'error',
                array(
                    'reason'  => 'invalid_parameters',
                    'details' => join(',', array_keys($invalid))
                )
            );
        }

        // Build the URL where the new addon can be found in the collection and
        // return it.
        $new_url = ( empty($_SERVER['HTTPS']) ? 'http' : 'https' ) .
            '://' . $_SERVER['HTTP_HOST'] .
            $this->resolveUrl(
                $_SERVER['REQUEST_URI'],
                rawurlencode($addon['Addon']['guid'])
            );
        $this->publish('base_url', $new_url);

        $addons = $this->getCollectionAddonsForView(
            $collection['Collection']['id'], $addon['Addon']['id']
        );
        $this->publish('addon', $addons[0]);

        return $this->renderStatus(
            self::STATUS_CREATED, 'addon', array(), $new_url
        );
    }

    /**
     * Dispatcher for collection detail resource.
     */
    function collection_addon_detail($collection_uuid, $addon_guid) {

        // Try to find the collection by the ID in the URL.  Kick out a 404
        // error if not found.
        $collection = $this->Collection->findByUuid($collection_uuid);
        if (empty($collection)) {
            return $this->renderStatus(
                self::STATUS_NOT_FOUND, 'error',
                array('reason' => 'collection_unknown')
            );
        }

        $this->Addon->bindOnly('User', 'Category');
        $addon = $this->Addon->findByGuid($addon_guid);
        $addon_collection = $this->AddonCollection->find(array(
            'AddonCollection.collection_id' => $collection['Collection']['id'],
            'AddonCollection.addon_id'      => $addon['Addon']['id']
        ));
        if (empty($addon_collection)) {
            return $this->renderStatus(
                self::STATUS_NOT_FOUND, 'error',
                array('reason' => 'addon_not_in_collection')
            );
        }

        $this->last_modified =
            strtotime($addon_collection['AddonCollection']['modified']);
        if ($this->isNotModifiedSince()) return;

        // Enforce role access for write HTTP methods.
        if ($this->isWriteHttpMethod()) {
            $writable = $this->Collection->isWritableByUser(
                $collection['Collection']['id'], $this->auth_user['id']
            );
            if (!$writable) {
                return $this->renderStatus(
                    self::STATUS_FORBIDDEN, 'error',
                    array('reason' => 'not_writable')
                );
            }
        }

        $args = func_get_args();
        return $this->dispatchHttpMethod(
            array(
                'GET'    => 'collection_addon_detail_GET',
                'PUT'    => 'collection_addon_detail_PUT',
                'DELETE' => 'collection_addon_detail_DELETE'
            ),
            $args,
            compact('collection', 'addon', 'addon_collection')
        );
    }

    /**
     * Get details on a single addon in a collection.
     */
    function collection_addon_detail_GET($context, $collection_uuid, $addon_guid) {
        extract($context);

        $addons = $this->getCollectionAddonsForView(
            $collection['Collection']['id'], $addon['Addon']['id']
        );
        $this->publish('addon', $addons[0]);
        return $this->render('addon');
    }

    /**
     * Update details for an addon in a collection
     */
    function collection_addon_detail_PUT($context, $collection_uuid, $addon_guid) {
        extract($context);

        $params = $this->getParams(array(
            'comments' => $addon_collection['AddonCollection']['comments']
        ));

        // HACK: Re-add the addon after deletion, because it's the easiest way
        // to re-trigger the translated fields update.
        $this->AddonCollection->deleteByAddonIdAndCollectionId(
            $addon['Addon']['id'], $collection['Collection']['id']
        );
        $data = array(
            'AddonCollection' => array(
                'collection_id' => $collection['Collection']['id'],
                'user_id'       => $this->auth_user['id'],
                'addon_id'      => $addon['Addon']['id'],
                'added'         => $addon_collection['AddonCollection']['added'],
                'comments'      => $params['comments']
            )
        );
        $this->Amo->clean($data);
        $this->AddonCollection->save($data);

        // Finally, render the addon with changes.
        $addons = $this->getCollectionAddonsForView(
            $collection['Collection']['id'], $addon['Addon']['id']
        );
        $this->publish('addon', $addons[0]);
        return $this->render('addon');
    }

    /**
     * Delete an addon from a collection.
     */
    function collection_addon_detail_DELETE($context, $collection_uuid, $addon_guid) {
        extract($context);
        $this->AddonCollection->deleteByAddonIdAndCollectionId(
            $addon['Addon']['id'], $collection['Collection']['id']
        );
        return $this->renderStatus(self::STATUS_GONE, 'empty');
    }

    /**
     * Dispatcher for email notification resource.
     */
    function email() {
        $args = func_get_args();
        return $this->dispatchHttpMethod(array(
            'POST'   => 'email_POST'
        ), $args);
    }

    /**
     * Email recommendations
     */
    function email_POST($context) {
        extract($context);

        $params = $this->getParams(array(
            'to'      => NULL,
            'guid'    => NULL,
            'message' => ''
        ));

        // Gripe if no email addresses supplied.
        if (empty($params['to'])) {
            return $this->renderStatus(
                self::STATUS_BAD_REQUEST, 'error',
                array(
                    'reason'  => 'invalid_parameters',
                    'details' => 'to'
                )
            );
        }

        // Split up and validate comma-separated email addresses.
        $emails = array();
        $parts = explode(',', $params['to']);
        foreach ($parts as $em) {
            $em = trim($em);
            if (preg_match(VALID_EMAIL, $em) === 0) {
                return $this->renderStatus(
                    self::STATUS_BAD_REQUEST, 'error',
                    array(
                        'reason'  => 'invalid_parameters',
                        'details' => 'to['.$em.']'
                    )
                );
            }
            $emails[] = $em;
        }

        // Look for the addon, throwing an error if there's none found.
        $this->Addon->bindOnly('User', 'Category');
        $addon = $this->Addon->findByGuid($params['guid']);
        if (empty($addon)) {
            return $this->renderStatus(
                self::STATUS_BAD_REQUEST, 'error',
                array('reason' => 'unknown_addon_guid')
            );
        }
        $this->publish('addon', $addon, false);

        $senderemail = $this->auth_user['email'];
        $this->set('senderemail', $senderemail);

        // Send recommendation email(s)
        $this->publish('message', $params['message'], false);
        $this->Email->fromName = null;
        $this->Email->from = $senderemail;
        $this->Email->sender = '"Mozilla Add-ons" <nobody@mozilla.org>';
        $this->Email->template = 'email/recommend_email';
        $this->Email->subject = sprintf('%1$s recommends %2$s',
            $this->auth_user['email'], $addon['Translation']['name']['string']);

        foreach ($emails as $em) {
            $this->Email->to = $em;
            $result = $this->Email->send();
        }

        // save stats
        $this->Addon->increaseShareCount($addon['Addon']['id'], count($emails));

        return $this->renderStatus(
            self::STATUS_ACCEPTED, 'empty', array()
        );
    }

    /**
     * Dispatcher for auth token resource.
     */
    function auth() {
        $args = func_get_args();
        return $this->dispatchHttpMethod(array(
            'POST' => 'auth_POST'
        ), $args);
    }

    /**
     * Generate a new auth token for the authenticated user.
     */
    function auth_POST($context) {
        extract($context);

        $token_value = $this->ApiAuthToken->generateTokenValue();

        $data = array(
            'ApiAuthToken' => array(
                'token' => $token_value,
                'user_id' => $this->auth_user['id']
            )
        );
        $this->Amo->clean($data);

        if (!$this->ApiAuthToken->save($data)) {
            return $this->renderStatus(
                self::STATUS_ERROR, 'error',
                array('reason' => 'auth_token_generation_failed')
            );
        }

        $token_url = $this->base_url . '/' . $token_value;

        return $this->renderStatus(
            self::STATUS_CREATED, 'auth_token', array(
                'value' => $token_value,
                'url'   => $token_url
            ), $token_url
        );
    }

    /**
     * Dispatcher for auth token detail resource.
     */
    function auth_detail($token) {
        $args = func_get_args();
        return $this->dispatchHttpMethod(array(
            'DELETE' => 'auth_detail_DELETE'
        ), $args);
    }

    /**
     * Delete an existing token, rendering it unusable in the future. (eg. for
     * logout)
     */
    function auth_detail_DELETE($context, $token) {
        extract($context);

        $rv = $this->ApiAuthToken->deleteByUserIdAndToken(
            $this->auth_user['id'], $token
        );

        if ($rv) {
            return $this->renderStatus(self::STATUS_GONE, 'empty');
        } else {
            return $this->renderStatus(
                self::STATUS_NOT_FOUND, 'error',
                array('reason' => 'token_unknown')
            );
        }
    }

    /**
     * Prepare a set of rows from the Collection model for use by the view.
     *
     * @param string View variable name
     * @param array  Authenticated user details
     * @param array  Rows from the Collection model
     * @return array Collection details published to view.
     */
    function publishCollections($name, $collection_ids, $addons_detail=FALSE) {
        global $app_shortnames;

        // Minimize the amount of data pulled back.
        $this->Collection->unbindFully();

        if ($addons_detail) {
            // If addons detail was requested, add a binding for Addons.
            $this->Collection->bindModel(array(
                'hasAndBelongsToMany' => array(
                    'Addon' =>
                        $this->Collection->hasAndBelongsToMany_full['Addon']
                )
            ));
        }

        // Assemble IDs for the user's subscriptions for use in detecting
        // subscriptions in the collection set.
        $subs = $this->User->getSubscriptions($this->auth_user['id']);
        $sub_ids = array();
        foreach ($subs as $sub)
            $sub_ids[] = $sub['Collection']['id'];

        $collections_out = array();
        $collection_rows = $this->Collection->findAllById($collection_ids);

        foreach ($collection_rows as $row) {

            // Come up with values for collection status flags
            $listed = $row['Collection']['listed'];
            $subscribed = in_array($row['Collection']['id'], $sub_ids);
            $writable = $this->Collection->isWritableByUser(
                $row['Collection']['id'], $this->auth_user['id']
            );

            // Try to look up one of the admin users for this collection and
            // derive a name.
            $admin_users = $this->Collection->getUsers(
                $row['Collection']['id'], array( COLLECTION_ROLE_ADMIN )
            );
            if (empty($admin_users)) {
                $creator_name = '';
            } else {
                $u = $admin_users[0]['User'];
                $creator_name = !empty($u['nickname']) ?
                    $u['nickname'] : "{$u['firstname']} {$u['lastname']}";
            }

            // Add a minimal set of details to the array for the view.
            $c_data = array(
                'id'           => $row['Collection']['id'],
                'uuid'         => $row['Collection']['uuid'],
                'icon'         => SITE_URL.$this->Image->getCollectionIconURL($row['Collection']['id']),
                'name'         => $row['Translation']['name']['string'],
                'description'  => $row['Translation']['description']['string'],
                'creator'      => $creator_name,
                'app'          => array_search($row['Collection']['application_id'], $app_shortnames),
                'listed'       => ($listed) ? 'yes' : 'no',
                'writable'     => ($writable) ? 'yes' : 'no',
                'subscribed'   => ($subscribed) ? 'yes' : 'no',
                'lastmodified' =>
                    date('c', $this->Collection->getLastModifiedForCollection(
                        $row['Collection']['id']
                    ))
            );

            // Convert the collection type into a name.
            switch (@$row['Collection']['collection_type']) {
                case Collection::COLLECTION_TYPE_EDITORSPICK:
                    $c_data['type'] = 'editorspick'; break;
                case Collection::COLLECTION_TYPE_AUTOPUBLISHER:
                    $c_data['type'] = 'autopublisher'; break;
                case Collection::COLLECTION_TYPE_NORMAL:
                default:
                    $c_data['type'] = 'normal'; break;
            }

            // If addon details were requested, look up and add the addons for
            // this collection.
            if ($addons_detail) {
                $addon_ids = array();
                foreach ($row['Addon'] as $addon_row)
                    $addon_ids[] = $addon_row['id'];
                $c_data['addons'] = $this->getCollectionAddonsForView(
                    $row['Collection']['id'], $addon_ids
                );
            }

            $collections_out[] = $c_data;
        }

        $this->publish($name, $collections_out);
        return $collections_out;
    }

    /**
     * Assemble view data for addons identified by ID
     *
     * @param array addon IDs
     * @return array
     */
    function getCollectionAddonsForView($collection_id, $addon_ids) {

        // Fetch the addons for the feed, first tearing down the model bindings
        // and selectively rebuilding them.
        $this->Addon->unbindFully();
        $this->Addon->bindModel(array(
            'hasAndBelongsToMany' => array(
                'User' => array(
                    'className'  => 'User',
                    'joinTable'  => 'addons_users',
                    'foreignKey' => 'addon_id',
                    'associationForeignKey'=> 'user_id',
                    'conditions' => 'addons_users.listed=1',
                    'order' => 'addons_users.position'
                ),
                'Category' => array(
                    'className'  => 'Category',
                    'joinTable'  => 'addons_categories',
                    'foreignKey' => 'addon_id',
                    'associationForeignKey'=> 'category_id'
                )
            )
        ));

        $conditions = array(
            'Addon.id' => $addon_ids,
            'Addon.inactive' => 0,
            'Addon.addontype_id' => array(
                ADDON_EXTENSION, ADDON_THEME, ADDON_DICT,
                ADDON_SEARCH, ADDON_PLUGIN
            )
        );

        $addons_data = $this->Addon->findAll($conditions);

        // Rather than trying to join categories and addon types in SQL, collect IDs 
        // and make a pair of queries to fetch them.
        $category_ids = array();
        $addon_type_ids = array();
        $addon_ids = array();
        foreach ($addons_data as $addon) {
            $addon_ids[] = $addon['Addon']['id'];
            $addon_type_ids[$addon['Addon']['addontype_id']] = true;
            foreach ($addon['Category'] as $category) 
                $category_ids[$category['id']] = true;
        }

        $user_names = array();

        $addon_collections = array();
        $collection_rows = $this->AddonCollection->findAll(array(
            'AddonCollection.collection_id' => $collection_id,
            'AddonCollection.addon_id' => $addon_ids
        ));
        foreach ($collection_rows as $c) {
            $user_id = $c['AddonCollection']['user_id'];
            if (!isset($user_names[$user_id])) {
                $user = $this->User->findById($user_id);
                $user_names[$user_id] =
                    !empty($user['User']['nickname']) ?
                        $user['User']['nickname'] :
                        "{$user['User']['firstname']} {$user['User']['lastname']}";
            }
            $c['addedby'] = $user_names[$user_id];
            $addon_collections[$c['AddonCollection']['addon_id']] = $c;
        }

        // Query for addon types found in this set of addons, assemble a map
        // for an in-code join later.
        $addon_type_rows = $this->Addontype->findAll(array(
            'Addontype.id' => array_keys($addon_type_ids)
        ));
        $addon_types = array();
        foreach ($addon_type_rows as $row) {
            $addon_types[$row['Addontype']['id']] = $row;
        }

        // Query for addon types found in this set of categories, assemble a map 
        // for an in-code join later.
        $category_rows = $this->Category->findAll(array(
            'Category.id' => array_keys($category_ids)
        ));
        $all_categories = array();
        foreach ($category_rows as $row) {
            $all_categories[$row['Category']['id']] = $row;
        }

        $app_names = $this->Application->getIDList();
        $guids = array_flip($this->Application->getGUIDList());

        $collection = $this->Collection->findById($collection_id, 'uuid', null, null, -1);
        $this->publish('collection_uuid', $collection['Collection']['uuid']);

        $this->publish('app_names', $app_names);
        $this->publish('guids', $guids);
        $this->publish('ids', $addon_ids);
        $this->publish('api_version', $this->api_version);
        $this->publish('os_translation', $this->os_translation);

        // Process addons list to produce a much flatter and more easily
        // sanitized array structure for the view, sprinkling in details
        // like categories and version information along the way.
        $addons_out = array();
        for ($i=0; $i<count($addons_data); $i++) {

            $addon = $addons_data[$i];
            $id    = $addon['Addon']['id'];

            $addontype_id = $addon['Addon']['addontype_id'];

            // make sure reported latest version matches version of file
            global $valid_status;
            $install_version = $this->Version->getVersionByAddonId(
                $addon['Addon']['id'], $valid_status
            );

            if (!isset($addon_collections[$id])) {
                // Skip addons that were found yet somehow not a part of a
                // collection.
                continue;
            }

            // Start constructing a flat minimal list of addon details made up of only
            // what the view will need.
            $addon_out = array(
                'collection_added' =>
                    date('c', strtotime($addon_collections[$id]['AddonCollection']['added'])),
                'collection_addedby' =>
                    $addon_collections[$id]['addedby'],
                'collection_comments' =>
                    $addon_collections[$id]['Translation']['comments']['string'],
                'id' => $addon['Addon']['id'],
                'guid' => $addon['Addon']['guid'],
                'name' => $addon['Translation']['name']['string'],
                'summary' => $addon['Translation']['summary']['string'],
                'description' =>
                    $addon['Translation']['description']['string'],
                'addontype_id' => $addontype_id,
                'addontype_name' =>
                    $this->Addontype->getName($addontype_id),
                'icon' =>
                    $this->Image->getAddonIconURL($id),
                'thumbnail' =>
                    $this->Image->getHighlightedPreviewURL($id),
                'install_version' => $install_version,
                'status' => $addon['Addon']['status'],
                'users' => $addon['User'],
                'eula' => $addon['Translation']['eula']['string'],
                'averagerating' => $addon['Addon']['averagerating'],
                'categories' => array(),
                'compatible_apps' => array(),
                'all_compatible_os' => array(),
                'fileinfo' => array()
            );

            // Add the list of categories into the addon details
            foreach ($addon['Category'] as $x) {
                $x = $all_categories[ $x['id'] ];
                $addon_out['categories'][] = array(
                    'id'   => $x['Category']['id'],
                    'name' => $x['Translation']['name']['string']
                );
            }

            // Add the list of compatible apps into the addon details
            $compatible_apps =
                $this->Version->getCompatibleApps($install_version);
            foreach ($compatible_apps as $x) {
                $addon_out['compatible_apps'][] = array(
                    'id'   => $x['Application']['application_id'],
                    'name' => $app_names[ $x['Application']['application_id']],
                    'guid' => $guids[$app_names[$x['Application']['application_id']]],
                    'min_version' => $x['Min_Version']['version'],
                    'max_version' => $x['Max_Version']['version']
                );
            }

            // Gather a list of platforms for files
            $fileinfo = $this->File->findAllByVersion_id(
                $install_version, null, null, null, null, 0
            );
            $this->Platform->unbindFully();
            $platforms = array();
            foreach($fileinfo as &$file) {
                $this_plat = $this->Platform->findById($file['Platform']['id']);
                $file['Platform']['apiname'] = $this_plat['Translation']['name']['string'];
                $platforms[] = $this_plat;
            }

            if ($this->api_version > 0 ) {
                // return an array of compatible os names
                // right now logic is still wrong, but this enables
                // xml changes and logic will be fixed later
                if (empty($platforms)) {
                    $all_compatible_os = array();
                } else {
                    $all_compatible_os = $platforms;
                }
                foreach ($all_compatible_os as $x) {
                    $addon_out['all_compatible_os'][] =
                        $this->os_translation[ $x['Translation']['name']['string'] ];
                }
            }

            // Add in the list of files available for the addon.
            foreach ($fileinfo as $x) {
                $addon_out['fileinfo'][] = array(
                    'id'       => $x['File']['id'],
                    'filename' => $x['File']['filename'],
                    'hash'     => $x['File']['hash'],
                    'os'       => $this->os_translation[ $x['Platform']['apiname'] ],
                );
            }

            // Finally, add this set of addon details to the list intended for
            // the view.
            $addons_out[] = $addon_out;
        }

        return $addons_out;
    }

    /**
     * API specific publish
     * Uses XML encoding and is UTF-8 safe
     * @param mixed the data array (or string) to be html-encoded (by reference)
     * @param bool clean the array keys as well?
     * @return void
    */
    function publish($viewvar, $value, $sanitizeme = true) {
        if ($sanitizeme) {
            if (is_array($value)) {
                $this->_sanitizeArrayForXML($value);
            } else {
                $tmp = array($value);
                $this->_sanitizeArrayForXML($tmp);
                $value = $tmp[0];
            }
        }
        $this->set($viewvar, $value);
    }

    /**
     * API specific sanitize
     * xml-encode an array, recursively
     * UTF-8 safe
     *
     * @param mixed the data array to be encoded
     * @param bool clean the array keys as well?
     * @return void
     */
    var $sanitize_patterns = array(
        "/\&/u", "/</u", "/>/u",
        '/"/u', "/'/u",
        '/[\cA-\cL]/u',
        '/[\cN-\cZ]/u',
     );
    var $sanitize_replacements = array(
        "&amp;", "&lt;", "&gt;",
        "&quot;", "&#39;",
        "",
        ""
    );
    var $sanitize_field_exceptions = array(
        'id'=>1, 'guid'=>1, 'addontype_id'=>1, 'status'=>1, 'higheststatus'=>1,
        'icontype'=>1, 'version_id'=>1, 'platform_id'=>1, 'size'=>1, 'hash'=>1,
        'codereview'=>1, 'password'=>1, 'emailhidden'=>1, 'sandboxshown'=>1,
        'averagerating'=>1, 'textdir'=>1, 'locale'=>1, 'locale_html'=>1,
        'created'=>1, 'modified'=>1, 'datestatuschanged'=>1
    );
    function _sanitizeArrayForXML(&$data, $cleankeys = false) {

        if (empty($data)) return;

        foreach ($data as $key => $value) {
            if (isset($this->sanitize_field_exceptions[$key])) {
                // @todo This if() statement is a temporary solution until we come up with
                // a better way of excluding fields from being sanitized.
                continue;
            } else if (empty($value)) {
                continue;
            } else if (is_array($value)) {
                $this->_sanitizeArrayForXML($data[$key], $cleankeys);
            } else {
                $data[$key] = preg_replace(
                    $this->sanitize_patterns,
                    $this->sanitize_replacements,
                    $data[$key]
                );
            }
        }

        // change the keys if necessary
        if ($cleankeys) {
            $keys = array_keys($data);
            $this->_sanitizeArrayForXML($keys, false);
            $data = array_combine($keys, array_values($data));
        }

    }

    /**
     * Render an HTTP status along with optional template and location.
     *
     * @param string HTTP status
     * @param string (optional) Name of a view to render
     * @param array  (optional) Vars to be published to the template
     * @param string (optional) URL for Location: header
     */
    function renderStatus($status, $view=null, $ns=null, $location=null) {
        $this->layout = ($view == 'empty') ? '' : 'rest';
        header('HTTP/1.1 ' . $status);
        if (!empty($ns)) foreach ($ns as $k=>$v)
            $this->publish($k, $v);
        if (null !== $location)
            header('Location: '.$location);
        if (null !== $view)
            return $this->render($view);
    }

    /**
     * Dispatch to the appropriate handler based on HTTP method and a map of
     * handlers.
     */
    function dispatchHttpMethod($map, $args=NULL, $context=null) {

        if (null == $args) $args = array();
        if (null == $context) $context = array();

        $method = $this->getHttpMethod();

        if ('OPTIONS' == $method) {
            header('Allow: ' . join(', ', array_keys($map)));
            $this->publish('methods', array_keys($map));
            return $this->render('options');
        }

        if (!isset($map[$method])) {
            return $this->renderStatus(
                self::STATUS_METHOD_NOT_ALLOWED, 'error',
                array('reason' => $method . '_not_allowed')
            );
        }

        return call_user_func_array(
            array($this, $map[$method]),
            array_merge(array($context), $args)
        );
    }

    /**
     * Grab named keys from POST parameters.  Missing parameters will be
     * set as null.
     *
     * @param array list of named parameters.
     */
    function getParams($list) {
        $params = array();
        $raw = array();
        if ($this->getHttpMethod() != 'PUT') {
            $raw = array_merge($_GET, $_POST);
        } else {
            $raw = array();
            if (!empty($_SERVER['CONTENT_LENGTH'])) {
                // HACK: $_POST isn't populated by PUT
                $data = file_get_contents('php://input');
                mb_parse_str($data, $raw);
            }
            $raw = array_merge($_GET, $raw);
        }
        foreach ($list as $name=>$default) {
            $params[$name] = isset($raw[$name]) ?
                $raw[$name] : $default;
        }
        return $params;
    }

    /**
     * Figure out the current HTTP method, with overrides accepted in a _method
     * parameter (GET/POST) or in an X_HTTP_METHOD_OVERRIDE header ala Google
     */
    function getHttpMethod() {
        if (!empty($_POST['_method']))
            return strtoupper($_POST['method']);
        if (!empty($_GET['_method']))
            return strtoupper($_GET['method']);
        if (!empty($_SERVER['X_HTTP_METHOD_OVERRIDE']))
            return strtoupper($_SERVER['X_HTTP_METHOD_OVERRIDE']);
        if (!empty($_SERVER['REQUEST_METHOD']))
            return strtoupper($_SERVER['REQUEST_METHOD']);
    }

    /**
     * Return whether the current HTTP method is a request to write in some
     * way.
     */
    function isWriteHttpMethod() {
        return in_array($this->getHttpMethod(), array('POST', 'DELETE', 'PUT'));
    }

    /**
     * If an if-modified-since header was provided, return a 304 if the
     * collection indeed has not been modified since the given time.
     */
    function isNotModifiedSince() {
        $since = @$_SERVER['HTTP_IF_MODIFIED_SINCE'];
        if ('GET' == $this->getHttpMethod() && $since) {
            if ($this->last_modified <= strtotime($since)) {
                return $this->renderStatus(
                    self::STATUS_NOT_MODIFIED, 'empty'
                );
            }
        }
    }

    /**
     * Return the current authenticated user, or return null and set 401
     * Unauthorized headers.
     *
     * @return mixed Authenticated user details.
     */
    function getAuthUser() {
        $auth_user = null;

        // 1: Check an auth header token
        if (null == $auth_user && !empty($_SERVER['HTTP_X_API_AUTH'])) {
            // Try accepting an API auth token in a header.
            $token = $_SERVER['HTTP_X_API_AUTH'];
            $auth_user = $this->ApiAuthToken->getUserForAuthToken($token);
        }

        // 2: Check HTTP basic auth
        if (null == $auth_user &&
                !empty($_SERVER['PHP_AUTH_USER']) &&
                !empty($_SERVER['PHP_AUTH_PW'])) {
            // Try validating the user by HTTP Basic auth username and password.
            $someone = $this->User->findByEmail($_SERVER['PHP_AUTH_USER']);
            if (!empty($someone['User']['id']) && $someone['User']['confirmationcode'] != '') {
                // User not yet verified.
                $auth_user = null;
            } else if ($this->User->checkPassword($someone['User'], $_SERVER['PHP_AUTH_PW'])) {
                $auth_user = $someone['User'];
                $auth_user['Group'] = $someone['Group'];
            }
        }

        return $auth_user;
    }

    /**
     * Standalone string sanitize for XML
     *
     * @param string
     * @return string
     */
    function sanitizeForXML($value) {
        return preg_replace(
            $this->sanitize_patterns,
            $this->sanitize_replacements,
            $value
        );
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
