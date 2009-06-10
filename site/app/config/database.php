<?php
/* SVN FILE: $Id: database.php.default,v 1.1.1.1 2006/08/14 23:54:56 sancus%off.net Exp $ */
/**
 * This is core configuration file.
 *
 * Use it to configure core behaviour ofCake.
 *
 * PHP versions 4 and 5
 *
 * CakePHP :  Rapid Development Framework <http://www.cakephp.org/>
 * Copyright (c)	2006, Cake Software Foundation, Inc.
 *								1785 E. Sahara Avenue, Suite 490-204
 *								Las Vegas, Nevada 89104
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @copyright		Copyright (c) 2006, Cake Software Foundation, Inc.
 * @link				http://www.cakefoundation.org/projects/info/cakephp CakePHP Project
 * @package			cake
 * @subpackage		cake.app.config
 * @since			CakePHP v 0.2.9
 * @version			$Revision: 1.1.1.1 $
 * @modifiedby		$LastChangedBy: phpnut $
 * @lastmodified	$Date: 2006/08/14 23:54:56 $
 * @license			http://www.opensource.org/licenses/mit-license.php The MIT License
 */
/**
 * In this file you set up your database connection details.
 *
 * @package		cake
 * @subpackage	cake.config
 */
/**
 * Database configuration class.
 * You can specify multiple configurations for production, development and testing.
 *
 * driver =>
 * mysql, postgres, sqlite, adodb, pear-drivername
 *
 * connect =>
 * MySQL set the connect to either mysql_pconnect of mysql_connect
 * PostgreSQL set the connect to either pg_pconnect of pg_connect
 * SQLite set the connect to sqlite_popen  sqlite_open
 * ADOdb set the connect to one of these
 *	(http://phplens.com/adodb/supported.databases.html) and
 *	append it '|p' for persistent connection. (mssql|p for example, or just mssql for not persistent)
 *
 * host =>
 * the host you connect to the database
 * MySQL 'localhost' to add a port number use 'localhost:port#'
 * PostgreSQL 'localhost' to add a port number use 'localhost port=5432'
 *
 * Note:    Driver has been changed to amo_mysql to add support for fetching l10n data because of a problem
 *          caused by a CakePHP bug (https://trac.cakephp.org/ticket/1183) which as of 1.2 is not fixed. 
 *
 *
 * How the database is configured:

 * The ConnectionManager is a singleton object that manages loading database
 * connections according to the named configurations in app/config/database.php.
 * When it gets instantiated, it creates an instance of our DATABASE_CONFIG so
 * it can access the configurations as member variables.  We have three
 * configurations: $default, $test, and $shadow.
 *
 * cake/libs/model/connection_manager.php:ConnectionManager.__construct:
 * $this->config = new DATABASE_CONFIG();
 *
 * Clients call ConnectionManager::getDataSource($name), which translates into
 * <DATABASE_CONFIG instance>->$name.  All the calls are for 'default' or
 * 'shadow', never 'test'.  Usually the $name comes from $this->useDbConfig.
 *
 * Once ConnectionManager has been instantiated, we're stuck with the
 * configuration since it's bound to the instance.  The easiest way to point
 * tests at the test database is to reconfigure DATABASE_CONFIG before any
 * instances are created.
 *
 * The named configurations are static class variables so they can be
 * manipulated by the class, but that means they can't be accessed as member
 * variables.  The constructor manually binds each static var to the new
 * instance so they're accessible on the object.
 *
 * AMO has two kinds of tests: unit tests and web tests.  Unit tests run in
 * same process as the test manager so switching to the test config is easy.
 * Web tests, however, invoke real requests through the server; we can't tell
 * that tests are running.  We send an X-Amo-Test header during web testing to
 * switch on the test database.
 */
class DATABASE_CONFIG
{
    static $default = array('driver' => 'amo_mysql',
                            'connect' => 'mysql_connect',
                            'host' => DB_HOST,
                            'login' => DB_USER,
                            'password' => DB_PASS,
                            'database' => DB_NAME,
                            'prefix' => ''
    );

    static $test = array(   'driver' => 'amo_mysql',
                            'connect' => 'mysql_connect',
                            'host' => TEST_DB_HOST,
                            'login' => TEST_DB_USER,
                            'password' => TEST_DB_PASS,
                            'database' => TEST_DB_NAME,
                            'prefix' => ''
    );

    static $shadow = array( 'driver' => 'amo_mysql',
                            'connect' => 'mysql_connect',
                            'host' => SHADOW_DB_HOST,
                            'login' => SHADOW_DB_USER,
                            'password' => SHADOW_DB_PASS,
                            'database' => SHADOW_DB_NAME,
                            'prefix' => '',
                            'shadow' => true
    );

    function __construct() {
        $this->default = self::$default;
        $this->test = self::$test;
        $this->shadow = self::$shadow;
    }

    function useTestConfig() {
        self::$default = self::$test;
        self::$shadow = self::$test;
    }
}
?>
