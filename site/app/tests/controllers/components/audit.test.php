<?php

Mock::generate('SessionComponent');
Mock::generate('SimpleAclComponent');
Mock::generate('AmoComponent');

class AuditTest extends UnitTestCase {

    function setUp() {
        $this->controller =& new AppController();
        loadComponent('Audit');
        $this->controller->Audit =& new AuditComponent();
        $this->controller->Audit->startup($this->controller);

        $this->controller->User =& new User();
        $this->controller->Addon =& new Addon();
        $this->controller->Group =& new Group();
        $this->controller->Application =& new Application();
        $this->controller->Tag =& new Tag();
        $this->controller->Platform =& new Platform();
        $this->controller->Feature =& new Feature();
        $this->controller->Cannedresponse =& new Cannedresponse();

        $this->controller->Session =& new MockSessionComponent();
        $this->controller->SimpleAcl =& new MockSimpleAclComponent();
        $this->controller->Amo =& new AmoComponent();

        $this->user_id = 3;
        $user = $this->controller->User->findById($this->user_id);
        $this->userName = $user['User']['firstname'].' '.$user['User']['lastname'];
        $this->user = $this->link($this->userName, '/users/info/'.$this->user_id);
        $this->adminUser = $this->link($this->userName, '/admin/users/'.$this->user_id);


        $this->addon_id = 7;
        $addon = $this->controller->Addon->findById($this->addon_id);
        $name = $addon['Translation']['name']['string'];
        $this->addonIdLink = $this->link($this->addon_id, '/addon/'.$this->addon_id);
        $this->addonStatusLink = $this->link($addon['Translation']['name']['string'],
                                             '/admin/addons/status/'.$this->addon_id);

        $this->review_id = 1;

        $this->group_id = 1;
        $group = $this->controller->Group->findById($this->group_id);
        $this->groupLink = $this->link($group['Group']['name'], '/admin/groups');
    }

    function makeLog($type, $action, $kwargs) {
        $log = array('type' => $type, 'action' => $action,
                     'user_id' => $this->user_id, 'created' => 0);
        $log = array_merge($log, $kwargs);
        return array('Eventlog' => $log);
    }

    /**
     * @param string type: the Eventlog type
     * @param [(array, string)] actions: list of (kwargs, expected) pairs.
              The kwargs are passed to `makeLog`.
     */
    function checkLogs($type, $actions) {
        foreach($actions as $action=>$params) {
            $kwargs = $params[0];
            $expected = $params[1];
            $log = $this->makeLog($type, $action, $kwargs);
            $actual = $this->controller->Audit->explainLog(array($log));
            $this->assertEqual($actual[0]['entry'], $expected);
        }
    }

    function link($title, $url) {
        return "<a href=\"{$this->controller->url($url)}\">{$title}</a>";
    }

    function testExplainLogEditor() {
        $actions = array(
            'feature_add' => array(
                array('added' => $this->addon_id),
                "{$this->user} added addon {$this->addonIdLink} to feature list",
            ),
            'feature_remove' => array(
                array('removed' => $this->addon_id),
                "{$this->user} removed addon {$this->addonIdLink} from feature list",
            ),
            'feature_locale_change' => array(
                array('changed_id' => $this->addon_id),
                "{$this->user} changed locales for addon {$this->addonIdLink} on feature list",
            ),
            'review_approve' => array(
                array('changed_id' => $this->review_id),
                "{$this->user} approved review {$this->review_id}",
            ),
            # TODO: check with SimpleAcl->actionAllowed = True
            'review_delete' => array(
                array('changed_id' => $this->review_id),
                "{$this->user} deleted review {$this->review_id}",
            ),
        );
        $this->checkLogs('editor', $actions);
    }

    function testExplainLogL10n() {
        $lang = 'lang';
        $kwargs = array('notes' => $lang);
        $link = $this->link($lang, "/localizers/%s/?userlang=${lang}");
        $appLink = sprintf($link, 'applications');
        $tagLink = sprintf($link, 'tags');
        $platformLink = sprintf($link, 'platforms');

        $actions = array(
            'update_applications' => array(
                $kwargs,
                "{$this->user} updated application translations for {$appLink}",
            ),
            'update_tags' => array(
                $kwargs,
                "{$this->user} updated category translations for {$tagLink}",
            ),
            'update_platforms' => array(
                $kwargs,
                "{$this->user} updated platform translations for {$platformLink}",
            ),
            'update_blog' => array(
                $kwargs,
                "{$this->user} updated blog post translations for {$platformLink}",
            ),
        );
        $this->checkLogs('l10n', $actions);
    }

    function testExplainLogSecurity() {
        $notes = 'bla';

        $actions = array(
            'reauthentication_failure' => array(
                array('notes' => $notes),
                "{$this->user} failed to re-authenticate to access {$notes}.",
            ),
            'modify_locked_group' => array(
                array('changed_id' => $this->group_id),
                "{$this->user} attempted to modify locked group {$this->groupLink}",
            ),
            'modify_other_locale' => array(
                array('notes' => $notes),
                "{$this->user} attempted to modify translations in {$notes} without permission",
            ),
        );
        $this->checkLogs('security', $actions);
    }

    function testExplainLogUser() {
        $actions = array(
            'group_associated' => array(
                array('changed_id' => $this->group_id),
                "{$this->user} associated themselves with {$this->groupLink}",
            )
        );
        $this->checkLogs('user', $actions);
    }

    function testExplainLogAdmin() {
        $app_id = 1;
        $app = $this->controller->Application->findById($app_id);
        $appLink = $this->link($app['Translation']['name']['string'],
                               '/admin/applications');

        $tag_id = 1;
        $tag = $this->controller->Tag->findById($tag_id);
        $tagLink = $this->link($tag['Translation']['name']['string'],
                               '/admin/tags');

        $platform_id = 1;
        $platform = $this->controller->Platform->findById($platform_id);
        $platformLink = $this->link($platform['Translation']['name']['string'],
                                    '/admin/platforms');

        $feature_id = 1;
        $feature = $this->controller->Feature->findById($feature_id);

        $response_id = 1;
        $response = $this->controller->Cannedresponse->findById($response_id);
        $responseLink = $this->link($response['Translation']['name']['string'],
                                    '/admin/responses');

        $actions = array(
            'addon_status' => array(
                array('added' => $this->addon_id, 'changed_id' => $this->addon_id),
                "{$this->user} changed the status of {$this->addonStatusLink} to Unknown",
            ),
            'file_recalchash' => array(
                array('changed_id' => $this->addon_id),
                "{$this->user} recalculated the hash for file {$this->addon_id}",
            ),
            'application_create' => array(
                array('changed_id' => $app_id),
                "{$this->user} created application {$appLink}",
            ),
            'application_edit' => array(
                array('changed_id' => $app_id),
                "{$this->user} edited application {$appLink}",
            ),
            'appversion_create' => array(
                array('notes' => $app_id, 'added' => $app_id),
                "{$this->user} created version {$app_id} for {$appLink}",
            ),
            'appversion_delete' => array(
                array('notes' => $app_id, 'removed' => $app_id),
                "{$this->user} deleted version {$app_id} for {$appLink}",
            ),
            'tag_create' => array(
                array('changed_id' => $tag_id),
                "{$this->user} created tag {$tagLink}",
            ),
            'tag_edit' => array(
                array('changed_id' => $tag_id),
                "{$this->user} edited category {$tagLink}",
            ),
            'tag_delete' => array(
                array('changed_id' => $tag_id, 'removed' => $tag_id),
                "{$this->user} deleted category {$tag_id} (ID {$tag_id})",
            ),
            'platform_create' => array(
                array('changed_id' => $platform_id),
                "{$this->user} created platform {$platformLink}",
            ),
            'platform_edit' => array(
                array('changed_id' => $platform_id),
                "{$this->user} edited platform {$platformLink}",
            ),
            'platform_delete' => array(
                array('changed_id' => $platform_id, 'removed' => $platform_id),
                "{$this->user} deleted platform {$platform_id} (ID {$platform_id})",
            ),
            'feature_edit' => array(
                array('changed_id' => $feature_id),
                "{$this->user} changed a feature for {$feature['Feature']['locale']} locale",
            ),
            'feature_remove' => array(
                array('removed' => $feature_id),
                "{$this->user} removed feature {$feature_id}",
            ),
            'group_create' => array(
                array('changed_id' => $this->group_id),
                "{$this->user} created group {$this->groupLink}",
            ),
            'group_edit' => array(
                array('changed_id' => $this->group_id),
                "{$this->user} edited group {$this->groupLink}",
            ),
            'group_delete' => array(
                array('changed_id' => $this->group_id, 'removed' => $this->group_id),
                "{$this->user} deleted group {$this->group_id} (ID {$this->group_id})",
            ),
            'group_addmember' => array(
                array('changed_id' => $this->group_id, 'added' => $this->user_id),
                "{$this->user} added {$this->adminUser} to group {$this->groupLink}",
            ),
            'group_removemember' => array(
                array('changed_id' => $this->group_id, 'removed' => $this->user_id),
                "{$this->user} removed {$this->adminUser} from group {$this->groupLink}",
            ),
            'response_create' => array(
                array('changed_id' => $response_id),
                "{$this->user} created response {$responseLink}",
            ),
            'response_edit' => array(
                array('changed_id' => $response_id),
                "{$this->user} edited response {$responseLink}",
            ),
            'response_delete' => array(
                array('changed_id' => $response_id, 'removed' => $response_id),
                "{$this->user} deleted response {$response_id} (ID {$response_id})",
            ),
            'config' => array(
                array('field' => 'foo', 'removed' => 1, 'added' => 2),
                "{$this->user} changed config 'foo' from '1' to '2'",
            ),
            'user_edit' => array(
                array('changed_id' => $this->user_id),
                "{$this->user} edited {$this->adminUser}'s user information",
            ),
        );
        $this->checkLogs('admin', $actions);
    }
}
?>
