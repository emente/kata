<?php
/**
 * include this file inside your config.php to get a profiling-overview of your webapp.
 * note: you need the xdebug extension
 * 
 * set PROFILE_LOG_MINIMUM in config if you don't need methods lasting shorter than this value
 *
 * @author mnt@codeninja.de, jo@wurzelpilz.de
 * @package kata_debugging
 *
 * Kata - Lightweight MVC Framework <http://www.codeninja.de/>
 * Copyright 2007-2015 mnt@codeninja.de
 *
 * Licensed under The GPL License
 * Redistributions of files must retain the above copyright notice.
 */

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
			
			// if PROFILE_LOG_MINIMUM is defined, only log request with a minimum runtime (in s)
			$logMinimum = (defined('PROFILE_LOG_MINIMUM') ? PROFILE_LOG_MINIMUM : 0);
			

			$log = '';
			$timePerBlock = ($totalTime/50);
			foreach ($out as $n=>$v) {
				// ignore dispatcher...
				if ($n == 'dispatcher->dispatch' || $n == 'require') {
					continue;
				}
				if ($v >= $logMinimum) {
					$sl = strlen($n);
					$tTimes = max(1, 6 - (int)($sl/8));
					$log .= $outCnt[$n]."\t".$n;
					for ($i=0; $i<$tTimes; $i++) {
						$log .= "\t";
					}
					$log .= number_format($v,3)."s ";
					$dl = '';
					for ($i=0; $i<(int)($v/$timePerBlock); $i++) {
						//$dl .= chr(0xDB);
						if ($i % 10 == 9) {
							$dl.='|';	
						}
						$dl .= '~';
					}
					if (!empty($dl)) {
						$log .= $dl;
					}
					
					$log .= "\n";
				}
			}
			if (!empty($log)) {
				$log = "===============================================================\n".
				date("d.m. H:i:s")."\n".$_SERVER['REQUEST_URI']."\nTotal: ".$totalTime."s".
				"\n---------------------------------------------------------------\n".
				$log.
				"===============================================================\n\n";
				file_put_contents($GLOBALS['kataProfileLogFile'], $log, FILE_APPEND);
			}
		}

		register_shutdown_function('kataSmallProfile');
		if (!defined('KATATMP')) {
			$GLOBALS['kataSmallProfileFile'] = ROOT.'tmp'.DS. 'trace' . rand(0, PHP_INT_MAX);
			$GLOBALS['kataProfileLogFile'] = ROOT.'tmp'.DS.'logtrace.log';
		} else {
			$GLOBALS['kataSmallProfileFile'] = KATATMP . 'trace' . rand(0, PHP_INT_MAX);
			$GLOBALS['kataProfileLogFile'] = KATATMP.'logtrace.log';
		}
		ini_set('xdebug.trace_format', 1);
		xdebug_start_trace($GLOBALS['kataSmallProfileFile']);

	} else {
		file_put_contents($GLOBALS['kataProfileLogFile'], 'You need a current XDebug-Extension!', FILE_APPEND);
	}
