<?php

/**
 * sessions via memcache
 * 
 * @package kata_component
 */

/**
 * A component for object oriented session handling using memcached
 * needs PECL memcached-extension 2.1.2 or bigger
 *
 * @author mnt@codeninja.de
 * @package kata_component
 */
class SessionComponent extends baseSessionComponent {

	/**
	 * setting some ini-parameters and starting the actual session
	 */
	protected function startupSession() {
		$this->initCookie();
		$this->initSessionParams();

		$isMemcacheD = extension_loaded('memcached');

		$servers = explode(',', MEMCACHED_SERVERS);
		$path = '';
		foreach ($servers as $server) {
			$temp = explode(':', $server);
			$path .= ($isMemcacheD?'':'tcp://').$temp[0] . ':' . (empty ($temp[1]) ? 11211 : $temp[1]) . ',';
		}

		if ($isMemcacheD) {
			ini_set('session.save_handler', 'memcached');
			ini_set('session.save_path', substr($path, 0, -1));
		} else {
			if (version_compare(phpversion('memcache'), '2.1.2', '<')) {
				throw new Exception('You need at least PECL memcached 2.1.2 for session support');
			}

			ini_set('memcache.allow_failover', true);
			ini_set('memcache.hash_strategy', 'consistent');
			ini_set('memcache.hash_function', 'fnv');
			ini_set('session.save_path', substr($path, 0, -1));
			ini_set('session.save_handler', 'memcache');
		}

		@ session_start();
		$this->renewCookie();
	}

	/**
	 * read value(s) from the session container.
	 * returns all currently set values if called with null
	 * returns null when nothing could be found under the name you gave
	 * @param string $name name under which the value(s) are to find
	 */
	public function read($name = null) {
		if (CLI) {
			return false;
		}
		if ($this->initSession(true)) {
			if (empty ($name)) {
				return $_SESSION;
			}
			if (isset ($_SESSION[$name])) {
				return $_SESSION[$name];
			}
		}
		return null;
	}

	/**
	 * write mixed values to the session-component.
	 * @param string $name identifier, may contain alphanumeric characters or .-_
	 * @param mixed $value values to store
	 */
	public function write($name, $value) {
		if ($this->preamble($name, false)) {
			unset ($_SESSION[$name]);
			$_SESSION[$name] = $value;
			return true;
		}
		return false;
	}

	/**
	 * delete values stored under given name from the session-container
	 * @param string $name identifier
	 */
	public function delete($name) {
		if ($this->preamble($name, false)) {
			unset ($_SESSION[$name]);
			return true;
		}
		return false;
	}

	/**
	 * destroy any current session and all variables stored in the session-container with it
	 */
	public function destroy() {
		@session_destroy();
		$_SESSION = null;
		$this->clearCookie();
	}
}