<?php

// Before doing anything, test to see if we are calling this from the command
// line.  If this is being called from the web, HTTP environment variables will
// be automatically set by Apache.  If these are found, exit immediately.
if (isset($_SERVER['HTTP_HOST'])) {
    exit(1);
}

$root = dirname(__FILE__).'/../';

// If we get here, we're on the command line, which means we can continue.
// Include config file
require_once($root.'/site/app/config/config.php');
require_once($root.'/site/app/config/constants.php');

echo system('mysql'
        .' --host='.DB_HOST
        .' --user='.DB_USER
        .' --password='.DB_PASS
        .' --database='.DB_NAME
        .' --batch'
        .' <'.$root.'/aslo/sql/db-update-search.sql', $retval);

exit($retval)

?>
