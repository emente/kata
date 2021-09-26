<?php

/**
 * @package kata_component
 */
/**
 * use igbinary for session serialization if loaded
 */
if (extension_loaded('igbinary')) {
	ini_set('session.serialize_handler', 'igbinary');
}

/**
 * base session class
 * 
 * @author mnt@codeninja.de
 * @author joachim.eckert@gameforge.com
 * @author martin.contento@gameforge.com
 * @package kata_component
 */
class baseSessionComponent extends Component {

	/**
	 * path that we use when we set the cookie
	 * @var string
	 */
	protected $path;
	/**
	 * domain that we use when we set the cookie
	 * @var string
	 */
	protected $domain;
	/**
	 * useragent that we use when we set/check a session-cookie
	 * @var string
	 */
	protected $userAgent = null;
	/**
	 * time that we use when we check a session cookie
	 * @var int
	 */
	protected $time = null;
	/**
	 * time after that the session expires (normally time+SESSION_TIMEOUT, as set in config/core.php)
	 * @var int
	 */
	protected $sessionTime = 0;

	/**
	 * perform needed initialization and cache the controller that called us
	 */
	public function startup($controller) {
		parent::startup($controller);

		if (!defined('SESSION_UNSAFE')) {
			define('SESSION_UNSAFE', false);
		}

		if (CLI) {
			return;
		}
	}

	function constructParams() {
		//already constructed?
		if (null !== $this->time) {
			return;
		}

		$this->domain = env('SERVER_NAME');
		if (defined('SESSION_BASEDOMAIN') && (SESSION_BASEDOMAIN)) {
			$parts = explode('.', env('SERVER_NAME'));

			//co.uk
			$count = 2;
			if (count($parts) > 1) {
				$tld = $parts[count($parts) - 1];
				if (in_array($tld, array('uk'))) {
					$count = 3;
				}
			}

			if (count($parts) > 1) {
				while (count($parts) > 2) {
					array_shift($parts);
				}
				$this->domain = '.' . implode('.', $parts);
			}
		}

		if (empty($this->controller->basePath)) {
			$this->path = '/';
		} else {
			$this->path = $this->controller->basePath;
		}

		$this->time = time();

		if (env('HTTP_USER_AGENT') != null) {
			$this->userAgent = md5(env('HTTP_USER_AGENT') . (!SESSION_UNSAFE ? $this->getIp(SESSION_UNSAFE) : '') . SESSION_STRING);
		} else {
			$this->userAgent = md5((!SESSION_UNSAFE ? $this->getIp(SESSION_UNSAFE) : '') . SESSION_STRING);
		}
	}

	/**
	 * did we already initialize the session?
	 * @var boolean
	 */
	private $didInitSession = false;

	/**
	 * setting some ini-parameters and starting the actual session. is done lazy (only when needed)
	 * @param $forRead boolean if true we dont initialize the session if no sessioncookie exists
	 */
	protected function initSession($forRead) {
		if ($this->didInitSession) {
			return true;
		}

		if ($forRead) {
			if (!isset($_COOKIE[SESSION_COOKIE]) || empty($_COOKIE[SESSION_COOKIE])) {
				return false;
			}
		}

		$this->constructParams();
		$this->startupSession();
		$this->didInitSession = true;
		$this->checkValid();
		return true;
	}

	protected function initSessionParams() {
		ini_set('url_rewriter.tags', '');
		ini_set('session.use_cookies', 1);
		ini_set('session.name', SESSION_COOKIE);
		ini_set('session.hash_bits_per_character', 6);
		ini_set('session_cache_limiter', 'nocache');
		ini_set('session.cookie_path', $this->path);
		ini_set('session.cookie_domain', $this->domain);
		ini_set('session.cookie_lifetime', SESSION_TIMEOUT);
		ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);

		if (!defined('SESSION_SYSPATH') || !SESSION_SYSPATH) {
			kataMakeTmpPath('sessions');
			ini_set('session.save_path', KATATMP . 'sessions');
		}
	}

	protected function initCookie() {
		header('P3P: CP="NOI ADM DEV PSAi COM NAV OUR OTRo STP IND DEM"');
	}

	/**
	 * set cookie again to refresh session timeout
	 */
	protected function renewCookie() {
		$id = session_id();
		if (!empty($id)) {
			if ('localhost' === $this->domain) {
				setcookie(SESSION_COOKIE, $id, false, "/", false); // GRMBL!!!
			} else {
				$timeout = SESSION_TIMEOUT;
				if (SESSION_TIMEOUT != 0) {
					$timeout = $timeout + time();
				}
				setcookie(SESSION_COOKIE, $id, $timeout, $this->path, $this->domain, false, true);
			}
		}
	}

	protected function clearCookie() {
		setcookie(SESSION_COOKIE, '', time() - DAY, $this->path, $this->domain);
	}

	/**
	 * check if the session expired, or something suspicious happend
	 */
	protected function checkValid() {
		if (!is_null($this->read('SessionConfig'))) {
			if ($this->userAgent != $this->read('SessionConfig.userAgent')) {
				// session hijacking
				$this->destroy();
			}
		} else {
			srand((double) microtime() * 1000000);
			$this->write('SessionConfig', 1);
			$this->write('SessionConfig.userAgent', $this->userAgent);
			$this->write('SessionConfig.rand', rand());
		}
	}

	/**
	 * checks if you used a valid string  as identifier
	 * @param string $name may contain a-z, A-Z, 0-9, ._-
	 */
	protected function validateKeyName($name) {
		if (is_string($name) && preg_match("/^[0-9a-zA-Z._-]+$/", $name)) {
			return;
		}
		throw new InvalidArgumentException("'$name' is not a valid session string identifier");
	}

	/**
	 * check obvious conditions for all operations
	 * 
	 * @param string $name name under which the value(s) are to find
	 * @param bool $forRead if we initialize for write (read: if we need to create a session if non-existing)
	 * @return bool success
	 */
	protected function preamble($name = null, $forRead = true) {
		if (CLI) {
			return false;
		}
		if (empty($name)) {
			return false;
		}
		$this->validateKeyName($name);
		return $this->initSession($forRead);
	}

	/**
	 * try to do an educated guess about the users real ip, even if he is behind proxies
	 * 
	 * @param bool $unsafe if true set the last two bytes (ipv4) or words (ipv6) to zero for really borked configurations
	 * @return string ip or '0.0.0.0' if failure
	 */
	public function getIp($unsafe=null) {
		if (is_null($unsafe)) {
			$unsafe = (bool)SESSION_UNSAFE;
		}
		
		
		$ip = $this->getRawIp();

		if ($unsafe && !empty($ip)) {
			$temp = explode('.', $ip);
			//IPv6?
			if (count($temp) == 0) {
				$temp = explode(':', $ip);
				if (count($temp) > 3) {
					array_pop($temp);
					array_pop($temp);
					$ip = implode(':', $temp);
				}
			} else {
				$ip = $temp[0] . '.' . $temp[1] . '.0.0';
			}
		}

		if (!empty($ip)) {
			return $ip;
		}

		return '0.0.0.0';
	}

	/**
	 * check all gazillion proxy headers out there, return ip (NAAAARF)
	 * 
	 * @return string raw ip
	 */
	public function getRawIp() {
		if (!empty($_SERVER['HTTP_CLIENT_IP']) && $this->validateIp($_SERVER['HTTP_CLIENT_IP'])) {
			return $_SERVER['HTTP_CLIENT_IP'];
		}

		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			foreach (explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']) as $tip) {
				if ($this->validateIp($tip)) {
					return $tip;
				}
			}
		}

		if (!empty($_SERVER['HTTP_X_FORWARDED']) && $this->validateIp($_SERVER['HTTP_X_FORWARDED'])) {
			return $_SERVER['HTTP_X_FORWARDED'];
		}
		if (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP']) && $this->validateIp($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
			return $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
		}
		if (!empty($_SERVER['HTTP_FORWARDED_FOR']) && $this->validateIp($_SERVER['HTTP_FORWARDED_FOR'])) {
			return $_SERVER['HTTP_FORWARDED_FOR'];
		}
		if (!empty($_SERVER['HTTP_FORWARDED']) && $this->validateIp($_SERVER['HTTP_FORWARDED'])) {
			return $_SERVER['HTTP_FORWARDED'];
		}

		return $_SERVER['REMOTE_ADDR'];
	}

	/**
	 * validates that a given ip is a valid ip4 or ip6 ip and neither
	 * from private nor from reserved ranges
	 *
	 * @param string $ip
	 * @return boolean
	 */
	public function validateIp($ip) {
		$result = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
		return (bool) $result !== false;
	}

}

/**
 * included derived classes depending on storage-method
 */
if (!defined('SESSION_STORAGE')) {
	require_once (LIB . 'controllers' . DS . 'components' . DS . 'file.session.php');
} else {
	require_once (LIB . 'controllers' . DS . 'components' . DS . strtolower(SESSION_STORAGE) . '.session.php');
}
