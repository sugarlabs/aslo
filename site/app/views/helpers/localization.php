<?php
/**
 *
 */
class LocalizationHelper extends Helper 
{
    
    /**
     * Outputs localized strings in the header for use in javascript functions stored in .js files
     * @param array $localizations the localized strings
     * @return string
     */
    function jsLocalization($localizations) {
        if (!empty($localizations)) {
            $array = array();
            foreach ($localizations as $key => $value) {
                $array[] = "$key:'$value'";
            }
            
            $return = '<script language="JavaScript" type="text/javascript">';
            $return .= 'var localized = {'.implode(', ', $array).'};';
            $return .= '</script>';
            
            return $return;
        }
        else {
            return '';
        }
    }
    
    /**
     * include a static, localized page from
     * /locale/{LANG}/pages/$name.thtml
     * falls back to default_language if page is not localized in LANG,
     * returns '' if no page is found there either.
     * @param string name name of the page snippet (without .thtml)
     * @param array replacements uses vsprintf() to replace parts of the snippet
     * @return string
     */
    function includeLocalPage($name, $replacements = array()) {
        $page_path = APP.'locale'.DS.'%s'.DS.'pages'.DS.$name.'.thtml';
        if (file_exists(sprintf($page_path, str_replace('-','_',LANG)))) {
            $page = sprintf($page_path, str_replace('-','_',LANG));
        } elseif (file_exists(sprintf($page_path, 'en_US'))) {
            $page = sprintf($page_path, 'en_US');
        } else {
            return ''; // no luck!
        }
        return vsprintf(file_get_contents($page), $replacements);
    }

    /**
     * format a file size (Kilobytes) in the local number format
     * @param float file size in kilobytes
     * @param int decimals
     * @return string localized file size, false in case of error
     */
    function localFileSize($size = null, $decimals = 0) {
        if (!is_numeric($size)) return false;
        
        loadHelper('AddonsHtml');
        $html = new AddonsHtmlHelper();
        $formatted_no = $html->number_format($size, $decimals);

        return sprintf(___('%1$s KB'), $formatted_no);
    }
}
?>
