<?php
/**
 * include this file inside your config.php to get a profiling-overview of your webapp.
 * note: you need the xdebug extension
 *
 * @author mnt@codeninja.de
 * @package kata_debugging
 *
 * Kata - Lightweight MVC Framework <http://www.codeninja.de/>
 * Copyright 2007-2015 mnt@codeninja.de
 *
 * Licensed under The GPL License
 * Redistributions of files must retain the above copyright notice.
 */

if (DEBUG > 1) {
	if (function_exists('xdebug_start_trace')) {

/**
 * shutdown function to generate profile report 
 */
		function kataSmallProfile() {
			xdebug_stop_trace();
			$lines = file($GLOBALS['kataSmallProfileFile'] . '.xt');
			unlink($GLOBALS['kataSmallProfileFile'] . '.xt');

			array_shift($lines);//version
			array_shift($lines);//trace start
			array_pop($lines);//exit
			array_pop($lines);//trace end
			$lastLine = array_pop($lines);//dummy

			$temp = explode("\t",$lines[2]);
			$endTime = $temp[3];
			$temp = explode("\t",$lastLine);
			$totalTime = $temp[3]-$endTime;
			unset($temp);

			$callStack = array ();
			$outCnt = array();
			$out = array ();

			foreach ($lines as $line) {
				$line = explode("\t", $line);

				if (1 == $line[2]) {
					$retLine = array_pop($callStack);
					if ($retLine[6] == 1) { //user func
						$time = $line[3] - $retLine[3];
						$outCnt[$retLine[5]] = (empty($outCnt[$retLine[5]])?0:$outCnt[$retLine[5]])+1;
						$out[$retLine[5]] = (empty ($out[$retLine[5]]) ? 0 : $out[$retLine[5]]) + $time;
					}
					continue;
				}

				if (substr($line[8], 0, strlen(ROOT)) != ROOT) {
					continue;
				}

				if (0 == $line[2]) { // entry
					$callStack[] = $line;
				}
			}
			arsort($out);

			$tdCell = '<td style="border:1px solid red;padding:2px;">';
			echo '<table style="border:1px solid red;color:black;background-color:#e8e8e8;border-collapse:collapse;">';
			echo '<tr>'.$tdCell.'What</td>'.$tdCell.'x times</td>'.$tdCell.'ms total</td></tr>';
			foreach ($out as $n=>$v) {
				echo '<tr>'.$tdCell.'<pre class="c1">'.$n.'</pre></td>'.$tdCell.$outCnt[$n].'</td>'.$tdCell.$v.' ms</td></tr>';
			}
			echo "<tr>$tdCell Total (before destroy)</td>$tdCell</td>$tdCell $totalTime ms</td></tr>";
			echo '</table>';
		}

		register_shutdown_function('kataSmallProfile');
		if (!defined('KATATMP')) {
			$GLOBALS['kataSmallProfileFile'] = ROOT.'tmp'.DS. 'trace' . rand(0, PHP_INT_MAX);
		} else {
			$GLOBALS['kataSmallProfileFile'] = KATATMP . 'trace' . rand(0, PHP_INT_MAX);
		}
		ini_set('xdebug.trace_format', 1);
		xdebug_start_trace($GLOBALS['kataSmallProfileFile']);

	} else {
		echo('You need a current XDebug-Extension!');
	}
}
