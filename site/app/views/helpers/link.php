<?php
/*
* This helper builds links to all parts of the site that aren't covered
* by function-specific components/helpers
*/
class LinkHelper extends Helper
{
    var $helpers = array('Html');

    function addonDisplay($name, $addon="", $attributes="") {
        $link = $this->Html->link($name, "/addon/$addon", $attributes);
        return $this->output($link);
    }

    function addonBrowse($tag="", $attributes="", $name="") {
        if (empty($name)) {
            $link = $this->Html->link($tag, "/browse/" . $tag, $attributes);
        }
        else {
            $link = $this->Html->link($name, "/browse/" . $tag, $attributes);
        }

        return $this->output($link);
    }

    /**
     * Make an email link that displays the real email by javascript
     * including noscript fallback for spam protection.
     * Relies on emailLink from addons.js.
     *
     * @param string Email address to link to
     * @param string js_id (Unique) Javascript ID for the email field
     * @param sting spanclass CSS class to give to email string
     */
    function email($email = '', $js_id = '', $spanclass = 'email') {
        if (!$email) return false;
        if (!$js_id) $js_id = md5($email); // ugly but unique.

        $noscriptemail = str_replace(array('@', '.'), array(' at ', ' dot '), $email);

        // unsanitize and re-encode email so html entities are not shown in plain text by javascript code
        $email = addslashes($this->Html->unsanitize($email));
        $emailparts = explode('@', $email, 2);

        $o = '<span id="'.$js_id.'" class="'.$spanclass.'">'.$noscriptemail."</span>\n"
        . "<script language=\"JavaScript\">"
        . "emailLink('{$js_id}', '{$emailparts[0]}', '{$emailparts[1]}');"
        . "</script>\n";

        return $this->output($o);
    }

    /**
     * Create a link to a collection.
     * @param array $coll Collection (from collection model)
     * @param string $app (optional) application to link to, defaults to current app
     */
    function collection($coll, $app = null, $attributes=array()) {
        if (empty($coll)) return false;

        $url = '/collection/';
        if (!empty($coll['Collection']['nickname']))
            $url .= $coll['Collection']['nickname'];
        else
            $url .= $coll['Collection']['uuid'];

        if (!empty($app)) {
            return $this->Html->linkNoLocaleNoApp($coll['Translation']['name']['string'],
                sprintf('/%s/%s%s', LANG, $app, $url), $attributes);
        } else {
            return $this->Html->link($coll['Translation']['name']['string'], $url, $attributes);
        }
    }
}
?>
