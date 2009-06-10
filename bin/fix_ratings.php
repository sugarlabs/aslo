<?php
    /**
     * Run-once script to fix our rating migration problems (bug 375809).
     * @author Wil Clouser <clouserw@gmail.com>
     */
    require_once '../site/app/config/config.php';
    require_once '../site/app/config/constants.php';

    $dbh = mysql_connect(DB_HOST.':'.DB_PORT,DB_USER,DB_PASS);

    if (!is_resource($dbh)) {
        die('MySQL connection to DB failed.');
    } elseif (!@mysql_select_db(DB_NAME, $dbh)) {
        die('Could not select database '.DB_NAME.'.');
    }


    // Reset all ratings to zero
    $_query = "UPDATE addons SET averagerating=0";

    if (!mysql_query($_query)) {
        die('Failed to clear ratings: '.mysql_error());
    }

    $_query = "SELECT 
                    addon_id, 
                    ROUND(SUM(rating) / COUNT(version_id),2) AS averagerating
               FROM reviews JOIN versions on versions.id = reviews.version_id
               GROUP BY version_id;";

    $res = mysql_query($_query);

    if (!$res) {
        die('Failed to retrieve new ratings: '.mysql_error());
    }

    while ($row = mysql_fetch_row($res)) {
        // can't update many rows at the same time, so we've got a query in a loop.
        // this is a run-once script though, so I'm not too worried.
        if (!mysql_query("UPDATE addons SET averagerating = '{$row[1]}' WHERE id = {$row[0]}")) {
            echo "Failing update: ".mysql_error()."\n";
        }
    }

?>
