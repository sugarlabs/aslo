<?php
class Addonlog extends AppModel
{
    var $name = "Addonlog";
    var $useTable = 'addonlogs';

    /**
     * AddonLog.type constants
     * The log type determines how object1_id, object2_id, name1, name2, and notes
     * fields are interpreted. See comments for each type constant.
     */
    const CREATE_ADDON                = 1;  // null, null, null, null, null
    const EDIT_PROPERTIES             = 2;  // null, null, null, null, null
    const EDIT_DESCRIPTIONS           = 3;  // null, null, null, null, null
    const EDIT_CATEGORIES             = 4;  // null, null, null, null, null
    const ADD_USER_WITH_ROLE          = 5;  // user_id, role_constant, null, null, null
    const REMOVE_USER_WITH_ROLE       = 6;  // user_id, role_constant, null, null, null
    const EDIT_CONTRIBUTIONS          = 7;  // null, null, null, null, null

    const SET_INACTIVE                = 8;  // null, null, null, null, null
    const UNSET_INACTIVE              = 9;  // null, null, null, null, null
    const SET_PUBLICSTATS             = 10; // null, null, null, null, null
    const UNSET_PUBLICSTATS           = 11; // null, null, null, null, null
    const CHANGE_STATUS               = 12; // status_constant, null, null, null, null

    const ADD_PREVIEW                 = 13; // null, null, null, null, null
    const EDIT_PREVIEW                = 14; // null, null, null, null, null
    const DELETE_PREVIEW              = 15; // null, null, null, null, null

    const ADD_VERSION                 = 16; // version_id, null, version, null, null
    const EDIT_VERSION                = 17; // version_id, null, version, null, null
    const DELETE_VERSION              = 18; // version_id, null, version, null, null
    const ADD_FILE_TO_VERSION         = 19; // file_id, version_id, filename, version, null
    const DELETE_FILE_FROM_VERSION    = 20; // file_id, version_id, filename, version, null

    const APPROVE_VERSION             = 21; // version_id, null, version, null, null
    const RETAIN_VERSION              = 22; // version_id, null, version, null, null
    const ESCALATE_VERSION            = 23; // version_id, null, version, null, null
    const REQUEST_VERSION             = 24; // version_id, null, version, null, null

    const ADD_TAG                     = 25; // tag_id, null, tag_name, null, null
    const REMOVE_TAG                  = 26; // tag_id, null, tag_name, null, null

    const ADD_TO_COLLECTION           = 27; // collection_id, null, collection_name, null, null
    const REMOVE_FROM_COLLECTION      = 28; // collection_id, null, collection_name, null, null

    const ADD_REVIEW                  = 29; // review_id, null, null, null, null

    const ADD_RECOMMENDED_CATEGORY    = 31; // category_id, null, null, null, null
    const REMOVE_RECOMMENDED_CATEGORY = 32; // category_id, null, null, null, null

    const ADD_RECOMMENDED             = 33; // null, null, null, null, null
    const REMOVE_RECOMMENDED          = 34; // null, null, null, null, null

    const ADD_APPVERSION              = 35; // application_id, appversion_id, null, version, null

    const CUSTOM_TEXT                 = 98; // null, null, null, null, customtext
    const CUSTOM_HTML                 = 99; // null, null, null, null, customhtml

    /**
     * Log an add-on related action
     * consider using a more specified Addonlog->logSomething() method to minimize argument confusion
     *
     * @param object $controller (by reference) used to get session user
     * @param int $type one of any Addonlog log-type constants
     * @param int $addon_id
     * @param int $object1_id
     * @param string $name1
     * @param int $object2_id
     * @param string $name2
     * @param string $notes
     * @return bool
     */
    function log(&$controller, $type, $addon_id=null, $object1_id=null, $name1=null, $object2_id=null, $name2=null, $notes=null) {

        $session = $controller->Session->read('User');
        $auditData = array(
                        'id'         => null,
                        'type'       => $type,
                        'addon_id'   => $addon_id,
                        'user_id'    => $session['id'],
                        'object1_id' => $object1_id,
                        'name1'      => $name1,
                        'object2_id' => $object2_id,
                        'name2'      => $name2,
                        'notes'      => $notes,
        );

        return $this->save($auditData);
    }

    /**
     * Use these convenience methods to minimize argument confusion
     */
    function logCreateAddon(&$controller, $addon_id) {
        return $this->log($controller, self::CREATE_ADDON, $addon_id);
    }

    function logEditProperties(&$controller, $addon_id) {
        return $this->log($controller, self::EDIT_PROPERTIES, $addon_id);
    }

    function logEditDescriptions(&$controller, $addon_id) {
        return $this->log($controller, self::EDIT_DESCRIPTIONS, $addon_id);
    }

    function logEditCategories(&$controller, $addon_id) {
        return $this->log($controller, self::EDIT_CATEGORIES, $addon_id);
    }

    function logAddUserWithRole(&$controller, $addon_id, $user_id, $role) {
        return $this->log($controller, self::ADD_USER_WITH_ROLE, $addon_id, $user_id, null, $role);
    }

    function logRemoveUserWithRole(&$controller, $addon_id, $user_id, $role) {
        return $this->log($controller, self::REMOVE_USER_WITH_ROLE, $addon_id, $user_id, null, $role);
    }

    function logEditContributions(&$controller, $addon_id) {
        return $this->log($controller, self::CREATE_ADDON, $addon_id);
    }

    function logSetInactive(&$controller, $addon_id) {
        return $this->log($controller, self::SET_INACTIVE, $addon_id);
    }

    function logUnsetInactive(&$controller, $addon_id) {
        return $this->log($controller, self::UNSET_INACTIVE, $addon_id);
    }

    function logSetPulicstats(&$controller, $addon_id) {
        return $this->log($controller, self::SET_PUBLICSTATS, $addon_id);
    }

    function logUnsetPulicstats(&$controller, $addon_id) {
        return $this->log($controller, self::UNSET_PUBLICSTATS, $addon_id);
    }

    function logChangeStatus(&$controller, $addon_id, $new_status) {
        return $this->log($controller, self::CHANGE_STATUS, $addon_id, $new_status);
    }

    function logAddPreview(&$controller, $addon_id) {
        return $this->log($controller, self::ADD_PREVIEW, $addon_id);
    }

    function logEditPreview(&$controller, $addon_id) {
        return $this->log($controller, self::EDIT_PREVIEW, $addon_id);
    }

    function logDeletePreview(&$controller, $addon_id) {
        return $this->log($controller, self::DELETE_PREVIEW, $addon_id);
    }

    function logAddVersion(&$controller, $addon_id, $version_id, $version_string) {
        return $this->log($controller, self::ADD_VERSION, $addon_id, $version_id, $version_string);
    }

    function logEditVersion(&$controller, $addon_id, $version_id, $version_string) {
        return $this->log($controller, self::EDIT_VERSION, $addon_id, $version_id, $version_string);
    }

    function logDeleteVersion(&$controller, $addon_id, $version_id, $version_string) {
        return $this->log($controller, self::DELETE_VERSION, $addon_id, $version_id, $version_string);
    }

    function logAddFileToVersion(&$controller, $addon_id, $file_id, $file_name, $version_id, $version_string) {
        return $this->log($controller, self::ADD_FILE_TO_VERSION, $addon_id, $file_id, $file_name, $version_id, $version_string);
    }

    function logDeleteFileFromVersion(&$controller, $addon_id, $file_id, $file_name, $version_id, $version_string) {
        return $this->log($controller, self::DELETE_FILE_FROM_VERSION, $addon_id, $file_id, $file_name, $version_id, $version_string);
    }

    function logApproveVersion(&$controller, $addon_id, $version_id, $version_string) {
        return $this->log($controller, self::APPROVE_VERSION, $addon_id, $version_id, $version_string);
    }

    function logRetainVersion(&$controller, $addon_id, $version_id, $version_string) {
        return $this->log($controller, self::RETAIN_VERSION, $addon_id, $version_id, $version_string);
    }
    
    function logEscalateVersion(&$controller, $addon_id, $version_id, $version_string) {
        return $this->log($controller, self::ESCALATE_VERSION, $addon_id, $version_id, $version_string);
    }

    function logRequestVersion(&$controller, $addon_id, $version_id, $version_string) {
        return $this->log($controller, self::REQUEST_VERSION, $addon_id, $version_id, $version_string);
    }

    function logAddTag(&$controller, $addon_id, $tag_id, $tag) {
        return $this->log($controller, self::ADD_TAG, $addon_id, $tag_id, $tag);
    }

    function logRemoveTag(&$controller, $addon_id, $tag_id, $tag) {
        return $this->log($controller, self::REMOVE_TAG, $addon_id, $tag_id, $tag);
    }

    function logAddToCollection(&$controller, $addon_id, $collection_id, $collection_name) {
        return $this->log($controller, self::ADD_TO_COLLECTION, $addon_id, $collection_id, $collection_name);
    }

    function logRemoveFromCollection(&$controller, $addon_id, $collection_id, $collection_name) {
        return $this->log($controller, self::REMOVE_FROM_COLLECTION, $addon_id, $collection_id, $collection_name);
    }

    function logAddReview(&$controller, $addon_id, $review_id) {
        return $this->log($controller, self::ADD_REVIEW, $addon_id, $review_id);
    }

    function logAddRecommendedCategory(&$controller, $addon_id, $category_id) {
        return $this->log($controller, self::ADD_RECOMMENDED_CATEGORY, $addon_id, $category_id);
    }

    function logRemoveRecommendedCategory(&$controller, $addon_id, $category_id) {
        return $this->log($controller, self::REMOVE_RECOMMENDED_CATEGORY, $addon_id, $category_id);
    }

    function logAddRecommended(&$controller, $addon_id) {
        return $this->log($controller, self::ADD_RECOMMENDED, $addon_id);
    }

    function logRemoveRecommended(&$controller, $addon_id) {
        return $this->log($controller, self::REMOVE_RECOMMENDED, $addon_id);
    }

    function logAddAppversion(&$controller, $application_id, $appversion_id, $version_string) {
        return $this->log($controller, self::ADD_APPVERSION, null, $application_id, null, $appversion_id, $version_string);
    }

    function logCustomText(&$controller, $text, $addon_id=null) {
        return $this->log($controller, self::CUSTOM_TEXT, $addon_id, null, null, null, null, $text);
    }

    function logCustomHtml(&$controller, $html, $addon_id=null) {
        return $this->log($controller, self::CUSTOM_HTML, $addon_id, null, null, null, null, $html);
    }
}

?>
