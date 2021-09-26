<?php


/**
 * several functions needed by kata
 *
 * Kata - Lightweight MVC Framework <http://www.codeninja.de/>
 * Copyright 2007-2015 mnt@codeninja.de
 *
 * Licensed under The GPL License
 * Redistributions of files must retain the above copyright notice.
 * @package kata_internal
 */

/**
 * internal function to send kata debug info to the browser. just define your own function if you want firebug or something like it
 */
if (!function_exists('kataDebugOutput')) {
	/**
	 * @ignore
	 * @param mixed $var variable to dump
	 * @param bool $isTable if variable is an array we use a table to display each line
	 */
	function kataDebugOutput($var = null, $isTable = false) {
		kataFunc :: debugOutput($var, $isTable);
	}
}

/**
 * create a directory in TMPPATH and check if its writable
 */
function kataMakeTmpPath($dirname) {
	if (!is_dir(KATATMP . $dirname . DS)) {
		if (!mkdir(KATATMP . $dirname, 0770, true)) {
			throw new Exception("makeTmpPath: cant create temporary path $dirname");
		}
	}
	if (!is_writable(KATATMP . $dirname)) {
		throw new Exception("makeTmpPath: " . KATATMP . "$dirname is not writeable");
	}
}

/**
 * static wrapper class so dont we pollute any namespace anymore
 */
class kataFunc {

	/**
	 * return the shortend path of the file currently begin executed
	 * 
	 * @return string
	 */
	function getLineInfo() {
		return;
		$nestLevel = -1;
		$bt = debug_backtrace();
		while ($nestLevel++ < count($bt)) {
			if (empty ($bt[$nestLevel]['file']))
				continue;
			foreach (array (
					LIB,
					ROOT . 'utilities' . DS
				) as $test) {
				if (substr($bt[$nestLevel]['file'], 0, strlen($test)) == $test)
					continue 2;
			}
			break;
		}
		return basename($bt[$nestLevel]['file']) . ':' . $bt[$nestLevel]['line'];
	}

	/**
	 * return stacktrace-like information about the given variable
	 * 
	 * @return string
	 */
	function getValueInfo($val) {
		if (is_null($val)) {
			return 'null';
		}
		if (is_array($val)) {
			return 'array[' . count($val) . ']';
		}
		if (is_bool($val)) {
			return ($val ? 'true' : 'false');
		}
		if (is_float($val) || is_int($val) || is_long($val) || is_real($val)) {
			return 'num:' . $val;
		}
		if (is_string($val)) {
			return 'string[' . strlen($val) . ']=' . substr($val, 0, 16);
		}
		if (is_resource($val)) {
			return 'resource' . get_resource_type($val);
		}
		if (is_object($val)) {
			return 'object';
		}
		return '?';
	}

	/**
	 * include files depending on name, if class is needed 
	 *
	 * @param string $cname classname
	 */
	static function autoloader($classname) {
		$cname = strtolower($classname);
		switch ($cname) {
			case 'appmodel' :
				if (is_file(ROOT . 'models' . DS . 'app.php')) {
					require ROOT . 'models' . DS . 'app.php';
				} else {
					require LIB . 'models' . DS . 'app.php';
				}
				break;

			case 'appcontroller' :
				if (is_file(ROOT . 'controllers' . DS . 'app.php')) {
					require ROOT . 'controllers' . DS . 'app.php';
				} else {
					require LIB . 'controllers' . DS . 'app.php';
				}
				break;

			case substr($cname, -9, 9) == 'component' :
				$cname = substr($cname, 0, -9);
				if (is_file(LIB . 'controllers' . DS . 'components' . DS . $cname . '.php')) {
					require LIB . 'controllers' . DS . 'components' . DS . $cname . '.php';
					break;
				}
				require ROOT . 'controllers' . DS . 'components' . DS . $cname . '.php';
				break;

			case substr($cname, -6, 6) == 'helper' :
				$cname = substr($cname, 0, -6);
				if (is_file(LIB . 'views' . DS . 'helpers' . DS . $cname . '.php')) {
					require LIB . 'views' . DS . 'helpers' . DS . $cname . '.php';
					break;
				}
				require ROOT . 'views' . DS . 'helpers' . DS . $cname . '.php';
				break;

			case substr($cname, -7, 7) == 'utility' :
				if (is_file(LIB . 'utilities' . DS . $cname . '.php')) {
					require LIB . 'utilities' . DS . $cname . '.php';
					break;
				}
				require ROOT . 'utilities' . DS . $cname . '.php';
				break;

			case is_file(LIB . $cname . '.php') :
				require LIB . $cname . '.php';
				break;

			case 'scaffoldcontroller' :
				require LIB . 'controllers' . DS . 'scaffold.php';
				break;

		}
	}

	/**
	 * the default debug output function. outputs print_r alike dump
	 * @param mixed $var variables to output
	 * @param bool $isTable if true variables are output in a table
	 */
	static function debugOutput($var = null, $isTable = false) {
		if (DEBUG < 2) {
			return;
		}
		if ($isTable) {
			echo '<table style="text-align:left;direction:ltr;border:1px solid red;color:black;background-color:#e8e8e8;border-collapse:collapse;text-align:left;direction:ltr;">';
			foreach ($var as $row) {
				echo '<tr>';
				foreach ($row as $col) {
					echo '<td style="border:1px solid red;padding:2px;">' . $col . '</td>';
				}
				echo '</tr>';
			}
			echo '</table>';
		} else {
			echo '<pre style="white-space:pre-wrap;text-align:left;direction:ltr;border:1px solid red;color:black;background-color:#e8e8e8;padding:3px;text-align:left;direction:ltr;">' . $var . '</pre>';
		}
	}

	/**
	 * shorthand function to read from serveral code caches
	 * @param string $id key to fetch
	 * @return bool success
	 */
	static function memoryRead($id) {
		if (function_exists('apc_fetch')) {
			return apc_fetch($id);
		}
		if (function_exists('eaccelerator_get')) {
			//eacc? bloody hell...
			$data = eaccelerator_get($id);
			if($data!==null) {
				$data = @unserialize($data); 
			}
			return $data;
		}
		if (function_exists('xcache_get')) {
			return xcache_get($id);
		}
		return false;
	}

	/**
	 * shorthand function to read from serveral code caches
	 * @param string $id key to fetch
	 * @param mixed $value value(s) to store. if FALSE key will be wiped from memory
	 * @return bool success
	 */
	static function memoryWrite($id, $value) {
		if (false !== $value) {
			if (function_exists('apc_store')) {
				return apc_store($id, $value,300);
			}
			if (function_exists('eaccelerator_put')) {
				return eaccelerator_put($id, serialize($value), 300);
			}
			if (function_exists('xcache_set')) {
				return xcache_set($id, $value, 300);
			}
			return false;
		}
		//yes, i'm checking for the wrong function
		if (function_exists('apc_store')) {
			return apc_delete($id);
		}
		if (function_exists('eaccelerator_put')) {
			return eaccelerator_rm($id);
		}
		if (function_exists('xcache_set')) {
			return xcache_unset($id);
		}
		return false;
	}

} //class

spl_autoload_register('kataFunc::autoloader');