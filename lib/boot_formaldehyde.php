<?php
/**
 * include this file inside your config.php to put debug-output into the firephp-console if DEBUG=1
 * formaldehyde.php must be in your include path, see http://code.google.com/p/formaldehyde/
 *
 * @author mnt@codeninja.de
 * @package kata_debugging
 */

/**
 * include formaldehyde. duh.
 */	
require_once("formaldehyde.php");

/**
 * if debug==1 replace debugoutput
 */
if (DEBUG == 1) {

	/**
	 * replace internal katadebug-function
	 */
	function kataDebugOutput($var= null, $isTable= false) {
    	formaldehyde_log('debug', $var);
	}
	
}
