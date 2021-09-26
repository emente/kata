<?php
/**
 * includes everything needed to call the dispatcher and start the whole mvc machinery
 * @package kata_internal
 */




/**
 * include base config
 */
require_once ROOT."config".DS."core.php";

/**
 * in case we dont use ignorant installer software: simply use kata's builtin tmp-dir
 */
if (!defined('KATATMP')) {
	define('KATATMP',ROOT.'tmp'.DS);
}
if (!defined('CACHE_IDENTIFIER')) {
   define('CACHE_IDENTIFIER','kataDef');
}

/**
 * needed for the updater
 */
define('KATAVERSION','1.4');

/**
 * set default encodings to utf-8 (you don't want to use anything less, anyway)
 */
mb_internal_encoding( 'UTF-8' );
mb_regex_encoding('UTF-8');

iconv_set_encoding("input_encoding", "UTF-8");
iconv_set_encoding("internal_encoding", "UTF-8");
iconv_set_encoding("output_encoding", "UTF-8");

if (defined('TZ')) {
	date_default_timezone_set(TZ);
} else {
	date_default_timezone_set('Europe/Berlin');
}

/**
 * do we have to turn on error messages and asserts?
 */
if (DEBUG>0) {
	error_reporting(E_ALL);
	
	assert_options(ASSERT_ACTIVE,true);
	assert_options(ASSERT_WARNING,1);
	assert_options(ASSERT_QUIET_EVAL,0);
	
	ini_set('display_errors',1);
}

/**
 * include all neccessary files to start up the framework
 */
require LIB.'class_registry.php';
require LIB.'kata_functions.php';
require LIB."basics.php";
require LIB."dispatcher.php";

