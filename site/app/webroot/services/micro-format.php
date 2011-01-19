<?php

require_once('../../config/config.php');
require_once('../../config/config-local.php');
require_once('../../config/constants.php');
require_once('./functions.php');

$errors = array();

$supported_languages = array(
    'ar'    => 'ar_EG.utf8',
    'ca'    => 'ca_ES.utf8',
    'cs'    => 'cs_CZ.utf8',
    'da'    => 'da_DK.utf8',
    'de'    => 'de_DE.utf8',
    'en-US' => 'en_US.utf8',
    'el'    => 'el_GR.utf8',
    'es-ES' => 'es_ES.utf8',
    'eu'    => 'eu_ES.utf8',
    'fa'    => 'fa_IR.utf8',
    'fi'    => 'fi_FI.utf8',
    'fr'    => 'fr_FR.utf8',
    'ga-IE' => 'ga_IE.utf8',
    'he'    => 'he_IL.utf8',
    'hu'    => 'hu_HU.utf8',
    'id'    => 'id_ID.utf8',
    'it'    => 'it_IT.utf8',
    'ja'    => 'ja_JP.utf8',
    'ko'    => 'ko_KR.utf8',
    'mn'    => 'mn_MN.utf8',
    'nl'    => 'nl_NL.utf8',
    'pl'    => 'pl_PL.utf8',
    'pt-BR' => 'pt_BR.utf8',
    'pt-PT' => 'pt_PT.utf8',
    'ro'    => 'ro_RO.utf8',
    'ru'    => 'ru_RU.utf8',
    'sk'    => 'sk_SK.utf8',
    'sq'    => 'sq_AL.utf8',
    'sv-SE' => 'sv_SE.utf8',
    'uk'    => 'uk_UA.utf8',
    'vi'    => 'vi_VN.utf8',
    'zh-CN' => 'zh_CN.utf8',
    'zh-TW' => 'zh_TW.utf8'
);

$collection_nickname = $_GET["collection_nickname"];
if (empty($collection_nickname))
    $errors[] = 'Required variable collection_nickname not set.';

$lang = $_GET["lang"];
if (empty($lang))
    $lang = "en-US";
else if (strstr($lang, "_") || strstr($lang, "-"))
    $lang = str_replace("_", "-", $lang);
else {
	foreach ($supported_languages as $code => $locale) {
		if (strstr($code, $lang . "-")) {
			$lang = $code;
			break;
		}
	}
}

$sugar = $_GET["sugar"];

$dbh = @mysql_connect(DB_HOST.':'.DB_PORT,DB_USER,DB_PASS);

if (!is_resource($dbh)) {
    $errors[] = 'MySQL connection to DB failed.';
} elseif (!@mysql_select_db(DB_NAME, $dbh)) {
    $errors[] = 'Could not select database '.DB_NAME.'.';
}

if (empty($errors)) {
    $sql_query = "
        SELECT
            addons.id,
            addons.guid,
            default_lang.localized_string as default_name,
            requested_lang.localized_string as name,
            max(versions.version) as version
        FROM
            collections
            INNER JOIN addons_collections ON collections.id = addons_collections.collection_id
            INNER JOIN addons ON addons.id = addons_collections.addon_id
            INNER JOIN versions ON versions.addon_id = addons.id AND (addons_collections.addon_version IS NULL OR versions.version = addons_collections.addon_version)
            INNER JOIN applications_versions ON applications_versions.version_id = versions.id 
            INNER JOIN appversions appmin ON appmin.id = applications_versions.min
            INNER JOIN appversions appmax ON appmax.id = applications_versions.max  
        LEFT JOIN translations AS default_lang ON default_lang.id = addons.name AND default_lang.locale = addons.defaultlocale
        LEFT JOIN translations AS requested_lang ON requested_lang.id = addons.name AND requested_lang.locale = '{$lang}'
        WHERE
            collections.nickname = '{$collection_nickname}'
        ";

    if ($sugar) {
        $sql_query .= " AND (addons_collections.addon_version OR CAST('{$sugar}' AS DECIMAL(3,3)) >= CAST(appmin.version AS DECIMAL(3,3)) AND CAST('{$sugar}' AS DECIMAL(3,3)) <= CAST(appmax.version AS DECIMAL(3,3)))";
    }

    $sql_query .= "
        GROUP BY
            addons.id
        ";
    
    $query = mysql_query($sql_query);
    
    if (!$query)
        $errors[] = 'MySQL query for update information failed.';
}

echo "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01//EN\" \"http://www.w3.org/TR/html4/strict.dtd\">\n";
echo "<html lang=\"en\">\n";

echo "<head>\n";
echo "<title>ASLO micro-format output</title>\n";
echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">\n";
echo "</head>\n";

echo "<body>\n";

if (!empty($errors)) {
    foreach ($errors as $error)
        echo $error;
} else {
    echo "<table>\n";
    while ($row = mysql_fetch_array($query, MYSQL_ASSOC)) {
        $sql_query = "
            SELECT
                files.size,
                files.filename,
                files.id as file_id
            FROM
                files
                INNER JOIN versions ON versions.id = files.version_id
            WHERE
                versions.addon_id = {$row['id']} AND
                versions.version = {$row['version']}
            LIMIT 1
            ";
        $files = mysql_fetch_array(mysql_query($sql_query));

        if (defined(FILES_HOST))
            $url = FILES_HOST . '/' . $row['id'] . '/' . $files['filename'];
        else
            $url = SITE_URL . '/downloads/file/' . $files['file_id'] . '/' . $files['filename'];
        $size = (int)$files['size'] * 1024;
        if ($row['name'])
            $name = $row['name'];
        else
            $name = $row['default_name'];

        echo "<tr>\n";
        echo "<td class=\"olpc-activity-info\">\n";
        echo "<span class=\"olpc-activity-id\">{$row['guid']}</span>\n";
        echo "<span class=\"olpc-activity-name\">{$name}</span>\n";
        echo "<span class=\"olpc-activity-version\">{$row['version']}</span>\n";
        echo "<span class=\"olpc-activity-size\">{$size}</span>\n";
        echo "<span class=\"olpc-activity-url\"><a href=\"{$url}\">download</a></span>\n";
        echo "</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
}

echo "</body>\n";

echo "</html>";
?>
