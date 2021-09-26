<?php
/**
 * include this file inside your config.php to put debug-output into a logfile if DEBUG==1
 *
 * @author mnt@codeninja.de
 * @package kata_debugging
 */

if (DEBUG == 1) {

	/**
	 * replace internal katadebug-function
	 */
	function kataDebugOutput($var= null, $isTable= false) {
		if (!$isTable) {
			writeLog(var_export($var,true),'boot');
		} else {

  			$widths = array();
			foreach ($var as $line) {
				$cellNo = 0;
				foreach ($line as $cell) {
					if (!isset($widths[$cellNo])) { $widths[$cellNo]=0; }
					$widths[$cellNo] = max($widths[$cellNo],strlen($cell));
					$cellNo++;
				}
			}
			$output = "\n";
			foreach ($var as $line) {
				$s = '';
				$cellNo = 0;
				foreach ($line as $cell) {
					$s.=$cell.str_repeat(' ',$widths[$cellNo]-strlen($cell)).' | ';
					$cellNo++;
				}
				$output.=$s."\n";
			}
			
			writeLog($output,'boot');
			
		}
	}
}
