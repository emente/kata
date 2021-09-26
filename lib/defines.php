<?php
/**
 * create some shortcut-defines for often-needed strings. after this you can include boot.php
 * @package kata_internal
 */




/**
 * shortcut for / or \ (depending on OS)
 */
if (!defined('DS')) {
	define('DS', DIRECTORY_SEPARATOR);
}

	/**
	 * absolute filesystem path to the root directory of this framework
	 */
if (!defined('ROOT')) {
	define('ROOT',dirname(dirname(__FILE__)).DS.'app'.DS);
}


	/**
	 * absolute filesystem path to the webroot-directory of this framework
	 */
if (!defined('WWW_ROOT')) {
	define('WWW_ROOT', ROOT.DS.'app'.DS.'webroot'. DS);
}

	/**
	 * absolute filesystem path to the lib-directory of this framework
	 */
if (!defined('LIB')) {
	define('LIB',dirname(dirname(__FILE__)).DS.'lib'.DS);
}

/**
 * @ignore
 */
if (php_sapi_name() == 'cli') {
	define('CLI',1);
} else {
	define('CLI',0);
}

/**
 * some often used constants that should be part of PHP
 */
define('MINUTE', 60 * SECOND);
define('HOUR', 60 * MINUTE);
define('DAY', 24 * HOUR);
define('WEEK', 7 * DAY);
define('MONTH', 30 * DAY);
define('YEAR', 365 * DAY);
