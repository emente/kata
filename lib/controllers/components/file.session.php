<?php

/**
 * @package kata_component
 */

/**
 * A component for object oriented session handling using standard filesystem cookies 
 *
 * @author mnt@codeninja.de
 * @package kata_component
 */
class SessionComponent extends baseSessionComponent {

	/**
	 * setting some ini-parameters and starting the actual session. is done lazy (only when needed)
	 * @param $forRead boolean if true we dont initialize the session if no sessioncookie exists
	 */
	protected function startupSession() {
		$this->initSessionParams();
		$this->initCookie();
		@session_start();
		$this->renewCookie();
	}

	///////////////////////////////////////////////////////////

	/**
	 * read value(s) from the session container.
	 * returns all currently set values if called with null
	 * returns null when nothing could be found under the name you gave
	 * @param string $name name under which the value(s) are to find
	 */
	public function read($name = null) {
		if (CLI) {
			return null;
		}
		if ($this->initSession(true)) {
			if (empty($name)) {
				return $_SESSION;
			}
			if (isset($_SESSION[$name])) {
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
			unset($_SESSION[$name]);
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
			unset($_SESSION[$name]);
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