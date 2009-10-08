<?php

/**
 * This is a command-line utility to extract the database connection string from PHP.
 * Yes, that is a terrible and awesome thing to do.
 *
 * Usage:
 *   php connection.php read
 *   php connection.php write
 */

require_once('database.class.php');

function connection_string($arr) {
    return json_encode(array(
        'host' => $arr['host'],
        'database' => $arr['name'],
        'user' => $arr['user'],
        'password' => $arr['pass'],
    ));
}

$db = new Database();
$action = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '';

echo connection_string($db->{$action.'_config'})."\n";
