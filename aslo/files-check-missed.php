<?php

// Before doing anything, test to see if we are calling this from the command
// line.  If this is being called from the web, HTTP environment variables will
// be automatically set by Apache.  If these are found, exit immediately.
if (isset($_SERVER['HTTP_HOST'])) {
    exit;
}

require_once('database.class.php');

/**
 *  * Get time as a float.
 *   * @return float
 *    */
function getmicrotime() {
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

// Start our timer.
$start = getmicrotime();

// New database class
$db = new Database();

$files_sql = "
    SELECT DISTINCT
        addons.id as addon_id,
        files.filename as filename,
        addons.status as addon_status,
        files.status as file_status
    FROM
        versions
    INNER JOIN addons ON versions.addon_id = addons.id AND addons.inactive = 0
    INNER JOIN files ON files.version_id = versions.id
    ORDER BY
        addon_status,
        file_status,
        addons.id DESC
";

$files_result = $db->read($files_sql);

while ($row = mysql_fetch_array($files_result)) {
    $filename = REPO_PATH."/{$row['addon_id']}/{$row['filename']}";
    if (!file_exists($filename))
        debug("Missed addon_status={$row['addon_status']} file_status={$row['file_status']} filename={$filename} ", true);
}

// How long did it take to run?
$exectime = getmicrotime() - $start;

debug('Time: '.$exectime);
debug('Exiting ...');

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
