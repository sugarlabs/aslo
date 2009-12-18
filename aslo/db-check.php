<?php

// Before doing anything, test to see if we are calling this from the command
// line.  If this is being called from the web, HTTP environment variables will
// be automatically set by Apache.  If these are found, exit immediately.
if (isset($_SERVER['HTTP_HOST'])) {
    exit;
}

require_once('database.class.php');

// New database class
$db = new Database();

$sql = "
    SELECT
        a.id
    FROM
        addons as a
    WHERE
        0 = (select count(*) from translations as t where t.id=a.name and t.locale=a.defaultlocale)
        AND a.status=4
";

$rows = $db->read($sql);

while ($row = mysql_fetch_array($rows)) {
    debug("Missed name for default locale id={$row['id']}", true);
}

/**
 * Give this function your output.  If the debug flag (in the database) is set or if the error is serious it will get printed
 *
 * @param string what to print
 * @param boolean if the error is fatal or not
 */
function debug($msg, $serious=false) {
    if (CRON_DEBUG || $serious) {
        $_ts = strftime('%H:%M:%S');
        echo "{$_ts} {$msg}\n";
    }
}

exit;
?>
