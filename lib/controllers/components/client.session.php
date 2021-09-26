<?php

/**
 * @package kata_component
 */

/**
 * An experimental component for object oriented session handling using encrypted client-side cookies.
 * keep in mind: this cookie is VERY BIG and is sent on EVERY request, even images!
 *
 * @deprecated 10.3.2010
 * @author mnt@codeninja.de
 * @package kata_component
 * @ignore
 */
class SessionComponent extends baseSessionComponent {

	/**
	 * placeholder for decoded session data 
	 */
	protected $sessionData = array ();

	/**
	 * setting some ini-parameters and starting the actual session
	 */
	protected function startupSession() {
		$this->initCookie();
	}

	///////////////////////////////////////////////////////////

	/**
	 * calculate the hash we use to check if the data is still valid
	 */
	protected function calculateHash() {
		return md5(serialize($this->sessionData) . SESSION_STRING);
	}

	/**
	 * did we already decode the session?
	 * @var boolean
	 */
	protected $isSessionDecoded = false;

	/**
	 * decode the session if not already done.
	 * throws SessionFailureException if strange things happen. 
	 */
	protected function decodeSession() {
		if ($this->isSessionDecoded) {
			return;
		}

		if (!empty ($_COOKIE[SESSION_COOKIE])) {
			$data = base64_decode($_COOKIE[SESSION_COOKIE]);
			if (false === $data) {
				return;
			}

			$data = gzinflate($data);
			if (false === $data) {
				return;
			}

			$data = mcrypt_decrypt( MCRYPT_BLOWFISH, SESSION_STRING, $data, MCRYPT_MODE_ECB);

			$this->sessionData = unserialize($data);
			if (false === $this->sessionData) {
				$this->sessionData = array ();
				return;
			}

			$hash = is($this->sessionData['Config.hash'], '');
			unset ($this->sessionData['Config.hash']);
			if ($hash != $this->calculateHash()) {
				$this->sessionData = array ();
				return;
			}
		}

		$this->isSessionDecoded = true;
	}

	protected function encodeSession() {
		$this->sessionData['Config.hash'] = $this->calculateHash();
		$data = base64_encode(gzdeflate(mcrypt_encrypt(MCRYPT_BLOWFISH,SESSION_STRING,serialize($this->sessionData), MCRYPT_MODE_ECB)));
		unset ($this->sessionData['Config.hash']);

		setcookie(SESSION_COOKIE, $data, time() + SESSION_TIMEOUT, $this->path, $this->domain);
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
			$this->decodeSession();
			if (empty ($name)) {
				return $this->sessionData;
			}
			if (isset ($this->sessionData[$name])) {
				return $this->sessionData[$name];
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
			$this->decodeSession();
			$this->sessionData[$name] = $value;
			$this->encodeSession();
			return true;
		}
		return false;
	}

	/**
	 * write multiple key value arrays to session-component
	 * @param $arr array with key-value pairs
	 */
	public function writeArray($arr) {
		if (CLI) {
			return false;
		}
		if ($this->initSession(false)) {
			$this->decodeSession();
			foreach ($arr as $k => $v) {
				$this->validateKeyName($k);
				$this->sessionData[$k] = $v;
			}
			$this->encodeSession();
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
			$this->decodeSession();
			unset ($this->sessionData[$name]);
			$this->encodeSession();
			return true;
		}
		return false;
	}

	/**
	 * destroy any current session and all variables stored in the session-container with it
	 */
	public function destroy() {
		$this->sessionData = array ();
		$this->clearCookie();
	}
}
