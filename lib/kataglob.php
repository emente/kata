<?php

/**
 * Contains kataGlob
 *
 * Kata - Lightweight MVC Framework <http://www.codeninja.de/>
 * Copyright 2007-2015 mnt@codeninja.de
 *
 * Licensed under The GPL License
 * Redistributions of files must retain the above copyright notice.
 * @package kata_internal
 */

/**
 * class for easy handling of global variables
 * @package kata_internal
 */
class kataGlob {

	/**
	 * storage array
	 * 
	 * @var array
	 * @access private
	 */
	private static $container = array ();

	/**
	 * get variable (if set) or return null
	 * 
	 * @param string $name name of the variable
	 */
	function get($name) {
		if (isset (self :: $container[$name])) {
			return self :: $container[$name];
		}
		return null;
	}

	/**
	 * set variable
	 * 
	 * @param string $name name of the variable
	 * @param mixed $value contents
	 */
	function set($name, $value) {
		self :: $container[$name] = $value;
	}

	/**
	 * unset variable
	 * 
	 * @param string $name name of the variable
	 */
	function remove($name) {
		unset(self :: $container[$name]);
	}

	/**
	 * find out if given variable exists (=is set)
	 * 
	 * @param string $name name of the variable
	 * @return bool true if variable is set 
	 */
	function exists($name) {
		return isset (self :: $container[$name]);
	}

}