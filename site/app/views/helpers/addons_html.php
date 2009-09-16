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
 * Frederic Wenzel <fwenzel@mozilla.com> .
 * Portions created by the Initial Developer are Copyright (C) 2006
 * the Initial Developer. All Rights Reserved.
 *
 * Contributor(s):
 *      Wil Clouser <clouserw@mozilla.com>
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

require_once(LIBS.DS.'view'.DS.'helpers'.DS.'html.php');

/**
 * Extends the cake Html helper in order to make urls including locales
 */
class AddonsHtmlHelper extends HtmlHelper
{
    /**
     * Add the locale to the  URLs?
     */
    var $addLocale = true;

    /**
     * Add the application to the URLs?
     */
    var $addApplication = true;

    var $doctype = 'html4-trans'; 

    /**
     * Overrides the HTMLHelper's formTag to handle AMO CSRF stuff in a way not involving cookies Bug427974
     */
    function formTag($target = null, $type = 'post', $htmlAttributes = array()) {
        $ret = HtmlHelper::formTag($target, $type, $htmlAttributes);
        $ret .= $this->hiddenSession();
        return $ret;
    }

    /**
     * Adds a hidden form variable to do session/token checking to handle
     * AMO CSRF stuff in a way not involving cookies (bug 427974)
     *
     * WARNING: $this->controller->Session not always defined when this method
     * called so using $_SESSION
     * For example, this happens when editng settings on the statistics dashboard.
     * 
     * @see AppController::checkCSRF  
     */
    function hiddenSession() {
        if (isset($_SESSION['User']['id'])) {
            $id = $_SESSION['User']['id'];
        } else {
            return ""; // no session so bail.
        }
        
        $current_epoch = (int)(time()/CAKE_SESSION_TIMEOUT);
        
        return sprintf('<div class="hsession"><input type="hidden" '
            .'name="sessionCheck" value="%s" /></div>',
            md5(session_id().$id.$current_epoch));
    }
    
    /**
     * same as link(), but no locale info added to base URL
     */
    function linkNoLocale($title, $url = null, $htmlAttributes = null, $confirmMessage = false, $escapeTitle = false, $return = false) {
        // switch off adding of locales for this
        $oldAddLocale = $this->addLocale;
        $this->addLocale = false;
        $ret = $this->link($title, $url, $htmlAttributes, $confirmMessage, $escapeTitle, $return);

        // switch locale handling back on
        $this->addLocale = $oldAddLocale;
        return $ret;
    }

    function linkNoLocaleNoApp($title, $url = null, $htmlAttributes = null, $confirmMessage = false, $escapeTitle = false, $return = false) {
        // switch off adding of locales for this
        $_oldAddLocale      = $this->addLocale;
        $_oldAddApplication = $this->addApplication;
        $this->addLocale = $this->addApplication = false;

        $ret = $this->link($title, $url, $htmlAttributes, $confirmMessage, $escapeTitle, $return);

        // switch locale handling back on
        $this->addLocale      = $_oldAddLocale;
        $this->addApplication = $_oldAddApplication;
        return $ret;
    }

    function link($title, $url = null, $htmlAttributes = null, $confirmMessage = false, $escapeTitle = false, $return = false) {
        return parent::link($title, $url, $htmlAttributes, $confirmMessage, $escapeTitle, $return);
    }
    
    /**
     * creates an <a href> link to a file
     */
    function linkFile($fileId, $title, $path, $htmlAttributes = array(), $return = false, $filename = null, $collection_id = null) {
        if (strpos($path, '://')!==false) {
            $url = $path;
        } else {
            $url = $this->webroot;
            // Rewrite base address to include locale, if applicable
            if ($this->addLocale) {
                $url .= LANG;
            }
            // Rewrite base address to include application name, if applicable
            if ($this->addApplication) {
                $url .= '/'.APP_SHORTNAME;
            }
            $url .= '/' . FILES_URL . "/{$fileId}";
            
            // optionally add file name for Save As to work correctly
            if (!empty($filename))
                $url .= "/$filename";

            // optionally add a collection stats code to the URL
            if (!empty($collection_id))
                $url .= "?collection_id={$collection_id}";
        }
        return $this->output(sprintf($this->tags['link'], $url, $this->parseHtmlOptions($htmlAttributes, null, '', ' '), $title), $return);
    }
    /**
     * returns the url to $fileId
     */
    function urlFile($fileId, $filename = null, $collection_id = null) {
        $url = $this->base . '/';
        $url .= LANG;
        $url .= '/'.APP_SHORTNAME;
        $url .= '/' . FILES_URL . "/{$fileId}";
        
        //optionally add file name for Save As to work correctly
        if (!empty($filename))
            $url .= "/$filename";

        // optionally add a collection stats code to the URL
        if (!empty($collection_id))
            $url .= "?collection_id={$collection_id}";

        return $url;
    }
    
    function urlImage($filename) {
        $base = $this->url('/', true, false, false);

        $imgpath = $base . IMAGES_URL . $filename;
        
        return $imgpath;
    }
    /**
    * Link to a user's profile, using their nickname if present and first+last if not.
    */
    function linkUser($userId, $first, $last, $nickname = null, $params = array('class' => 'profileLink')) {
        if (empty($nickname))
            $nickname = $first . ' ' . $last;
        return $this->link($nickname, '/user/' . $userId, $params);
    }

    /**
     * Link to a user's profile given a user model result array
     */
    function linkUserFromModel($userModel, $params = array('class'=>'profileLink')) {
        if ($userModel['nickname'] == 'Deleted User') // XXX slightly ugly way of determining a deleted user
            $nickname = ___('Deleted User');
        else
            $nickname = $userModel['nickname'];
        return $this->linkUser($userModel['id'], $userModel['firstname'],
                               $userModel['lastname'], $nickname, $params);
    }

    /**
     * Link to a list of users, using a model result set
     * @param array usersModel model array with multiple users
     * @param int maxUsers show this many users before writing "and others" (0 = no limit)
     * @param string moreLink URL to link to on "and others"
     * @param array params additional link parameters for the author links
     */
    function linkUsersFromModel($usersModel, $maxUsers = null, 
        $moreLink = null, $params = array('class'=>'profileLink')) {

        if (!is_int($maxUsers) || $maxUsers < 0) $maxUsers = 2;
        
        if ($maxUsers > 0) {
            $showOthers = (count($usersModel)>$maxUsers);
            $usersModel = array_slice($usersModel, 0, $maxUsers);
        } else {
            $showOthers = false;
        }

        $text = @implode(", ", array_map(array(&$this, 'linkUserFromModel'), $usersModel));
        
        if ($showOthers) {
            $text .= ', ';
            $_linktext = ___('others');
            if ($moreLink)
                $text .= $this->link($_linktext, $moreLink);
            else
                $text .= $_linktext;
        }
        return $text;
    }
    
    /**
     * Helper for getting a URL without application and locale.
     */
    function rootUrl($url = null) {
        return $this->url($url, false, false, false);
    }

    /**
     * locale and application aware url generator function
     */
    function url($url = null, $return = false, $addLocale = true, $addApplication = true) {
        $oldbase = $this->base;

        if (!empty($this->plugin)) {
            $newbase = strip_plugin($this->base, $this->plugin);
        } else {
            $newbase = $this->base;
        }

        // Rewrite base address to include locale, if applicable
        if ($addLocale && $this->addLocale) {
            $newbase .= '/'.LANG;
        }

        // Don't add the application/layout if url goes to an other_layout controller
        global $other_layouts;
        $parts = explode('/', $url);
        if (count($parts) > 1) {
            $controller = !empty($parts[0]) ? $parts[0] : $parts[1];
        }
        if (!empty($controller) && array_key_exists($controller, $other_layouts)) {
            $addApplication = false;
        }

        // Rewrite base address to include application name, if applicable
        if ($addApplication && $this->addApplication) {
            $newbase .= '/'.APP_SHORTNAME;
        }

        if (!empty($this->plugin)) {
            $newbase .= '/'.$this->plugin;
        }
        
        $this->base = $newbase;
        $ret = parent::url($url, $return, true);
        
        $this->base = $oldbase;
        return $ret;
    }
    
    /**
     * (Cake-relative) URL to login page, including return-to parameter
     */
    function login_url($target = null, $add_get_parameters = true, $logout = false) {
        if (!empty($target)) {
            $_forward_to = $target;
        } else {
            // Shouldn't see anything weird here, because it has to be a valid cake url
            $_forward_to = empty($this->params['url']['url']) ?  '' : $this->params['url']['url'];
        }
        $get_params = array();
        
        if ($add_get_parameters) {
            // add GET parameters
            foreach($this->params['url'] as $_key => $_param)
                if ($_key != 'url') $get_params[] = "{$_key}={$_param}";
            if (!empty($get_params)) $_forward_to .= '?'.implode('&',$get_params);
        }
        
        if(!isset($this->params['url']['to'])) {
            $_forward_to = urlencode($_forward_to); // urlencode entities in the parameters
        } else {
            $_forward_to = urlencode($this->params['url']['to']);
        }
        
        $_forward_to = '?to='.$_forward_to;
        return '/users/log'.(!$logout?'in':'out').$_forward_to;
    }
    
    /**
     * (Cake-relative) URL to logout page, including return-to parameter
     */
    function logout_url($target = null, $add_get_parameters = true) {
        return $this->login_url($target, $add_get_parameters, true);
    }
    
    /**
     * wrapper for htmlentities, quote-safe.
     */
    function entities($unsafe) {
        return htmlentities($unsafe, ENT_QUOTES, 'UTF-8');
    }

    /**
     * un-apply cakePHP's Sanitize::html
     * use this to recover initial strings from publish()ed data
     *
     * @param String pubdata a string that was html-encoded by the publish() function
     * @return un-sanitized string
     */
    function unsanitize($pubdata) {
        global $unsanitize_patterns;
        // apply tag replacements backwards, then return
        return preg_replace($unsanitize_patterns['patterns'], $unsanitize_patterns['replacements'], $pubdata);
    }
    
    /**
     * for echoing files without blowing the memory limit
     */
    function readfile_chunked($filename) {
        $chunk = 1*(1024*1024);
        $buffer = '';
        $fh = fopen($filename, 'rb');
        if (!$fh) {
           return false;
        }
        while (!feof($fh)) {
            $buffer = fread($fh, $chunk);
            echo $buffer;
            ob_flush();
            flush();
        }
        $status = fclose($fh);
        
        return $status;
    }

    /**
     * Change Cake's default from escaping fields with HTML entities
     * to requiring explicit instructions via the 'escape' parameter
     *
     * See line 984 of real HtmlHelper.
     */
    function _parseAttributes($options, $exclude = null, $insertBefore = ' ', $insertAfter = null) {
        if (empty($options)) {
            $options = array();
        }

        if (!is_array($options) || !array_key_exists('escape', $options)) {
            $options['escape'] = false;
        }
        
        return parent::_parseAttributes($options, $exclude, $insertBefore, $insertAfter);
    }
    
    /**
     * Replace '\n' by actual line break character (to be used with gettext
     * strings, which return '\n' in plain text)
     *
     * @param string $text Text to replace line breaks in
     * @param bool $br replace by HTML <br/> tags instead
     */
    function lineBreaks($text, $br = false) {
        if ($br)
            return str_replace('\n', '<br/>', $text);
        else
            return str_replace('\n', "\n", $text);
    }

    /**
     * Creates an image input widget (backported from Cake 1.2's form helper)
     *
     * @param  string  $path           Path to the image file, relative to the webroot/img/ directory.
     * @param  array   $options Array of HTML attributes.
     * @return string  HTML submit image element
     */
	function submitImage($path, $options = array()) {
		if (strpos($path, '://')) {
			$url = $path;
		} else {
			$url = $this->webroot . $this->themeWeb . IMAGES_URL . $path;
		}
		return $this->output(sprintf($this->tags['submitimage'], $url, $this->_parseAttributes($options, null, '', ' ')));
	}
    
    /**
     * Create an HTML select tag. Behaves exactly like CakePHP's html->selectTag(),
     * but it allows simpler name attributes than Cake's Model/Field style.
     */
    function simpleSelectTag($fieldName, $optionElements, $selected = null, $selectAttr = array(), $optionAttr = null, $showEmpty = true, $return = false) {
        $result = $this->selectTag('Re/Place', $optionElements, $selected, $selectAttr, $optionAttr, $showEmpty, true);
        $result = str_replace(array('data[Re][Place]', 'RePlace'), $fieldName, $result);
        
        if ($return)
            return $result;
        else
            echo $result;
    }

     /**
      * multibyte-safe number_format function.
      * Uses regular php number_format with "safe" placeholders, then replaces
      * them by their actual (possibly multi-byte) counterparts.
      */
     function number_format($number, $num_decimal_places = 0) {
         $localeconv = localeconv();
         $placeholders = array('@', '~');
         $actual = array($localeconv['decimal_point'], $localeconv['thousands_sep']);
         
         // format number with placeholders
         $formatted = number_format($number, $num_decimal_places,
             $placeholders[0], $placeholders[1]);
         
         // replace by localized characters
         $formatted = str_replace($placeholders, $actual, $formatted);
         
         return $formatted;
     }

     /**
      * Returns a formatted SELECT element.
      *
      * Bug 458329: Cake's selectTag() helper doesn't properly 
      * escape quotes or line breaks for use as option
      * values. So, it's done here in an overridden version
      *
      * @param string $fieldName Name attribute of the SELECT
      * @param array $optionElements Array of the OPTION elements (as 'value'=>'Text' pairs) to be used in the SELECT element
      * @param mixed $selected Selected option
      * @param array $selectAttr Array of HTML options for the opening SELECT element
      * @param array $optionAttr Array of HTML options for the enclosed OPTION elements
      * @param boolean $show_empty If true, the empty select option is shown
      * @param  boolean $return         Whether this method should return a value
      * @return string Formatted SELECT element
      * @access public
      */
    function selectTag($fieldName, $optionElements, $selected = null, $selectAttr = array(), $optionAttr = null, $showEmpty = true, $return = false) {
        $this->setFormTag($fieldName);
        if ($this->tagIsInvalid($this->model, $this->field)) {
            if (isset($selectAttr['class']) && trim($selectAttr['class']) != "") {
                $selectAttr['class'] .= ' form_error';
            } else {
                $selectAttr['class'] = 'form_error';
            }
        }
        if (!isset($selectAttr['id'])) {
            $selectAttr['id'] = $this->model . Inflector::camelize($this->field);
        }

        if (!is_array($optionElements)) {
            return null;
        }

        if (!isset($selected)) {
            $selected = $this->tagValue($fieldName);
        }

        if (isset($selectAttr) && array_key_exists("multiple", $selectAttr)) {
            $select[] = sprintf($this->tags['selectmultiplestart'], $this->model, $this->field, $this->parseHtmlOptions($selectAttr));
        } else {
            $select[] = sprintf($this->tags['selectstart'], $this->model, $this->field, $this->parseHtmlOptions($selectAttr));
        }

        if ($showEmpty == true) {
            $select[] = sprintf($this->tags['selectempty'], $this->parseHtmlOptions($optionAttr));
        }

        foreach ($optionElements as $name => $title) {
            $optionsHere = $optionAttr;

            if (($selected != null) && ($selected == $name)) {
                $optionsHere['selected'] = 'selected';
            } elseif (is_array($selected) && in_array($name, $selected)) {
                $optionsHere['selected'] = 'selected';
            }

            // HTML-escape the option value, even the line breaks
            $name = h($name);
            $name = str_replace("\n", '&#10;', $name);
            $name = str_replace("\r", '&#13;', $name);

            $select[] = sprintf($this->tags['selectoption'], $name, $this->parseHtmlOptions($optionsHere), h($title));
        }

        $select[] = sprintf($this->tags['selectend']);
        return $this->output(implode("\n", $select), $return);
    }

    function truncateChars($length, $string, $onSpaces = false) {
        if (mb_strlen($string) <= $length) {
            return $string;
        } else {
            if($onSpaces) {
                $string = mb_substr($string, 0, $length-3);
                $sub = mb_substr($string, 0, mb_strrpos($string, ' '));
            } else {
                $sub = mb_substr($string, 0, $length - 3);
            }
            return $sub.'...';
        }
    }

    /**
     * Returns the value of $statusMap according to the status of $addon.
     *
     * @param array $addon Addon object
     * @param array $statusMap assoc array with keys 'experimental',
     *                         'recommended', and 'default'
     */
    function byStatus($addon, $statusMap) {
        global $experimental_status;
        if (!array_key_exists('default', $statusMap)) {
            $statusMap['default'] = '';
        }
        if (in_array($addon['Addon']['status'], $experimental_status)) {
            return $statusMap['experimental'];
        } elseif ((isset($addon['Addon']['recommended']) && $addon['Addon']['recommended']) || $this->isFeatured($addon))
        {
            return $statusMap['recommended'];
        } else {
            return $statusMap['default'];
        }
    }

    function extraClass($addon, $default='') {
        return $this->byStatus($addon, array('experimental' => 'exp',
                                             'recommended'  => 'rec',
                                             'default'      => $default));
    }
    
    /**
     * Returns true or false if the addon is featured
     * 
     * @param array $addon Addon object
     * @return boolean True/false if addon is featured
     */
    function isFeatured($addon) {
        $featured = false;
        
        foreach($addon['AddonCategory'] as $tag) {
            if($tag['feature'] == 1) {
                $featured = true;
                break;
            }
        }
        return $featured;
    }

    function flag($addon, $default='') {
        $flag = $this->byStatus($addon, array(
            'experimental' => $this->link(___('experimental'),
                                          '/pages/faq#experimental-addons'),
            'recommended'  => $this->link(___('recommended'),
                                          '/pages/faq#recommended-addons'),
            'default' => $default
        ));
        if (!empty($flag)) {
            return '<h5 class="flag">'.$flag.'</h5>';
        } else {
            return '';
        }
    }

    /**
     * This is copied from cake, but with less fail.
     *
     * If $model->validationErrors[$field] is 1, we'll show $default_text.
     * This is for backwards compat with $model->invalidate.
     *
     * If $model->validationErrors[$field] is a string, we'll use that as
     * the error message.
     */
    function tagErrorMsg($field, $text) {
        $this->setFormTag($field);
        if ($error = $this->tagIsInvalid($this->model, $this->field)) {
            $msg = is_string($error) ? $error : $text;
            return sprintf('<div class="error_message">%s</div>', $msg);
        } else {
            return null;
        }
    }

    /**
     * Appends a parameter to a URL, checking if it should add a ? or &.  This does no URL encoding!
     *
     * @param string url
     * @param array parameters
     */
    function appendParametersToUrl($url, array $params) {
        if (strpos($url, '?') === false) {
            $url .= "?";
        }

        foreach ($params as $var => $val) {
            if (!in_array(substr($url,-1), array('?','&'))) {
                $url .= '&';
            }
            $url .= "{$var}={$val}";
        }

        return $url;
    }

    function radio_list($fieldName, $options, $htmlAttributes=array()) {
        $this->setFormTag($fieldName);
		$value = isset($htmlAttributes['value']) ? $htmlAttributes['value'] : $this->tagValue($fieldName);
        $out = array();

		foreach ($options as $optValue => $optTitle) {
            $attrs = array('value' => $optValue);
            if ($optValue == $value) {
                $attrs['checked'] = 'checked';
            }
            $attrs = $this->_parseAttributes(array_merge($htmlAttributes, $attrs));
            $name = "{$this->field}_{$optValue}";
            $title = "<label for='{$name}'>{$optTitle}</label>";
            $out[] = '<li>'.sprintf($this->tags['radio'], $this->model, $this->field,
                                    $name, $attrs, $title)
                     .'</li>';
        }
        return '<ul class="radio">'.join('', $out).'</ul>';
    }
    
    /**
     * Print a nice, formatted 'Posted yesterday/days/weeks/months ago' for a time
     * @param $time a UNIX timestamp in the past
     */
    function postedTimeAgo($time) {
        $now = time();
        $diff = $now - $time;
        $year = 365 * 24 * 60 * 60;
        $month = 28 * 24 * 60 * 60;
        $week = 7 * 24 * 60 * 60;
        $day = 24 * 60 * 60; 
        
        if($diff < $year) {
            if($diff < $month) {
                if(strftime('%e', $time) == strftime('%e', $now)) {
                    return strftime(___('Posted today @ %l:%M %p'), $time);
                } elseif($diff < $day) {
                    return  strftime(___('Posted yesterday @ %l:%M %p'), $time);
                } elseif($diff < $week) {
                    $days = (int)$diff/$day;
                    return sprintf(n___("Posted yesterday", "Posted %d days ago", $days), $days);
                } elseif(floor($diff/$week) > 1) {
                    $weeks = floor($diff / $week);    
                    return sprintf(n___("Posted last week", "Posted %d weeks ago", $weeks), $weeks);
                } else {
                    return ___("Posted last week");
                }
            } else {
                $months = floor($diff/$month);
                return sprintf(n___("Posted a month ago", "Posted %d months ago", 
                    $months), $months);
            }
        } else {
            $years = floor($diff/$year);
            return sprintf(n___("Posted a year ago", "Posted %d years ago",
                $years), $years);
        }
        return 'today';
    }

    /**
     * Print a nice, formatted 'yesterday/days/weeks/months ago' for a time
     * @param $time a UNIX timestamp in the past
     */
    function timeAgo($time) {
        $now = time();
        $diff = $now - $time;
        $year = 365 * 24 * 60 * 60;
        $month = 28 * 24 * 60 * 60;
        $week = 7 * 24 * 60 * 60;
        $day = 24 * 60 * 60; 
        
        
        if($diff < $year) {
            if($diff < $month) {
                if(strftime('%e', $time) == strftime('%e', $now)) {
                    return strftime(___('Today @ %l:%M %p'), $time);
                } elseif($diff < $day) {
                    return  strftime(___('Yesterday @ %l:%M %p'), $time);
                } elseif($diff < $week) {
                    $days = (int)$diff/$day;
                    return sprintf(n___("Yesterday", "%d days ago", $days), $days);
                } elseif(floor($diff/$week) > 1) {
                    $weeks = floor($diff / $week);    
                    return sprintf(n___("Last week", "%d weeks ago", $weeks), $weeks);
                } else {
                    return ___("Last week");
                }
            } else {
                $months = floor($diff/$month);
                return sprintf(n___("A month ago", "%d months ago", 
                    $months), $months);
            }
        } else {
            $years = floor($diff/$year);
            return sprintf(n___("A year ago", "%d years ago",
                $years), $years);
        }
    }
}
?>
