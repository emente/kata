<?php
/**
 * include this file inside your config.php to get a codecoverage overview of your webapp.
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
	if (function_exists('xdebug_start_code_coverage')) {

		/**
		 * shutdown function to generate coverage 
		 */
		function kataCodeCoverage() {
			$data= xdebug_get_code_coverage(XDEBUG_CC_DEAD_CODE | XDEBUG_CC_UNUSED);
			ksort($data);
			foreach ($data as $name => & $lines) {
				if (substr($name, 0, strlen(ROOT)) != ROOT) {
					continue;
				}

				$temp= explode(DS, $name);
				if (in_array('lib',$temp)) {
					continue;
				}
				$shortFile= array_pop($temp);
				$shortDir = array_pop($temp);
				$shortName= $shortDir.DS.$shortFile;

				if (($shortDir == 'config') || ($shortName == 'webroot'.DS.'index.php')) {
					continue;
				}

				echo '<br /><h2>'.$shortName.'</h2>';

				if ($shortDir == 'lang') {
					echo 'skipping... '.$shortDir.' <br />';
					continue;
				}

				$file= file($name);
				$tdCell = '<td style="border:1px solid red;padding:2px;">';
				echo '<table style="min-width:100%;border:1px solid red;color:black;background-color:#e8e8e8;border-collapse:collapse;">';
				foreach ($file as $lineno => $line) {
					$count= 0;
					if (isset ($lines[$lineno +1])) {
						$count = $lines[$lineno +1];
					}
					if ($count>2) {
						$count = 3;
					}

					echo '<tr>'.$tdCell.$lineno.'</td>'.$tdCell.'<tt';
					switch ($count) {
						case 0:echo ' style="color:#c0c0c0;"';break;
						case 1:echo '';break;
						case 2:
						case 3:echo ' style="color:#ffe0e0;"';break;
					}
					echo '>'.h($line).'</tt></td></tr>';
				}
				echo '</table>';

			}
			unset ($lines);
			echo '</table>';
		}
		register_shutdown_function('kataCodeCoverage');
		xdebug_start_code_coverage();

	} else {
		kataDebugOutput('You need a current XDebug-Extension!');
	}
}
