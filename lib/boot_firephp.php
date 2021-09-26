<?php
/**
 * include this file inside your config.php to put debug-output into the firephp-console if DEBUG=1
 * the firephp-class must be in your include-path (!) see http://www.firephp.org/
 *
 * If you get errors like 'Unknown Exception in line 0' or
 * 'only one table at a time shows up': update to a newer version of firebug.
 *
 * @author mnt@codeninja.de
 * @package kata_debugging
 */

if (DEBUG == 1) {

	/**
	 * include firebug. duh.
	 */
	require 'fb.php';

	ob_start(); // or fb cant send its headers

	/**
	 * replace internal katadebug-function
	 */
	function kataDebugOutput($var= null, $isTable= false) {
		if (!$isTable) {
			fb($var);
		} else {
/*
  			$widths = array();
			foreach ($var as $line) {
				$cellNo = 0;
				foreach ($line as $cell) {
					if (!isset($widths[$cellNo])) { $widths[$cellNo]=0; }
					$widths[$cellNo] = max($widths[$cellNo],strlen($cell));
					$cellNo++;
				}
			}
			foreach ($var as $line) {
				$s = '';
				$cellNo = 0;
				foreach ($line as $cell) {
					$s.=$cell.str_repeat(' ',$widths[$cellNo]-strlen($cell)).' | ';
					$cellNo++;
				}
				fb($s);
			}
*/
			fb($var, 'see below', FirePHP::TABLE);
		}
	}
}
