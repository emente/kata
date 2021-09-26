<?php
/**
 * include this file inside your config.php to put debug-output into the firephp-console if DEBUG=1
 * dBug.php must be in your include path, see http://dbug.ospinto.com/
 *
 * @author mnt@codeninja.de
 * @package kata_debugging
 */

if (DEBUG > 1) {

/**
 * include dbug. duh.
 */	
	require_once("dBug.php");

/**
 * replace debug() function 
 */	
   	function debug($var= null, $escape= false) {
    	new dBug($var);
	}
}
