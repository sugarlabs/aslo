<?php

require_once('../../config/config.php');
require_once('../../config/config-local.php');
require_once('../../config/constants.php');
require_once('./functions.php');

$errors = array();

foreach (array('collection_nickname') as $var) {
    if (empty($_GET[$var]))
        $errors[] = 'Required variable '.$var.' not set.';
}

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
            max(versions.version) as version,
            files.size,
            files.filename,
            files.id as file_id
        FROM
            collections
            INNER JOIN addons_collections ON collections.id = addons_collections.collection_id
            INNER JOIN addons ON addons.id = addons_collections.addon_id
            INNER JOIN versions ON versions.addon_id = addons.id AND (addons_collections.addon_version IS NULL OR versions.version = addons_collections.addon_version)
            INNER JOIN files ON files.version_id = versions.id
        WHERE
            collections.nickname = '{$_GET['collection_nickname']}'
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
        if (defined(FILES_HOST))
            $url = FILES_HOST . '/' . $row['id'] . '/' . $row['filename'];
        else
            $url = SITE_URL . '/downloads/file/' . $row['file_id'] . '/' . $row['filename'];
        echo "<tr>\n";
        echo "<td class=\"olpc-activity-info\">\n";
        echo "<span class=\"olpc-activity-id\">{$row['guid']}</span>\n";
        echo "<span class=\"olpc-activity-version\">{$row['version']}</span>\n";
        echo "<span class=\"olpc-activity-size\">{$row['size']}</span>\n";
        echo "<span class=\"olpc-activity-url\"><a href=\"{$url}\">download</a></span>\n";
        echo "</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
}

echo "</body>\n";

echo "</html>";
?>
