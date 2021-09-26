<?php
/**
 * @package kata
 */




/**
 * systemwide locking mechanism with timeout for critical sections or eventhandlers
 *
 * @package kata_utility
 * @author mnt@codeninja.de
 * @author sven.bender@gameforge.de
 */
class LockUtility {
	/**
	 * @var integer seconds to wait until we timeout
	 */
	private $timeout= 10;

	/**
	 * @var array holds lock-status
	 */
	private $locks= array ();

	/**
	 * @param integer $timout how many seconds to wait for a lock before we fail
	 */
	public function setTimeout($timeout) {
		if (is_numeric($timeout) && ($timeout > 0)) {
			$this->timeout= $timeout;
			return true;
		}
		return false;
	}

	/**
	 * setup up session directory
	 */
	function  __construct() {
		kataMakeTmpPath('sessions');
	}

	/**
	 * Lock a user id
	 *
	 * @param int $id, id of the user to lock.
	 * @param bool $waitForTimeout, wait for time out
	 * @return bool, returns true if the user was locked
	 */
	function lock($id, $waitForTimeout= true) {
		if (substr(PHP_OS, 0, 3) == 'WIN') {
			return true;
		}

		$this->garbageCollect();

		$timeout= time() + $this->timeout;
		$lockname= KATATMP.'sessions'.DS.'lockfile'.urlencode($id);
		$fplock= null;

		do {
			$fplock = fopen($lockname, "w+");
			if ($fplock) {
				if (flock($fplock, LOCK_EX | LOCK_NB)) {
					break;
				}
				if ($fplock) {
					fclose($fplock);
					$fplock= null;
				}
			}
			usleep(100000);
		} while ((time() < $timeout) && $waitForTimeout);

		if ($fplock) {
			$this->locks[$id]= $fplock;
			return true;
		}

		return false;
	}

	/**
	 * Unlock a user id
	 *
	 * @param int $id, id of the user to lock
	 * @return true if the user was unlocked
	 */
	function unlock($id) {
		if (substr(PHP_OS, 0, 3) == 'WIN') {
			return true;
		}

		if (!isset($this->locks[$id])) {
			if (DEBUG > 0) {
				throw new Exception("entity $id not locked");
			}
			return false;
		}

		$fplock= $this->locks[$id];
		flock($fplock, LOCK_UN);
		fclose($fplock);

		unset ($this->locks[$id]);
		return true;
	}

	/**
	 * clean up leftover lockfiles
	 *
	 * @param bool $force collect now, even if propability is unmet
	 */
	function garbageCollect($force = false) {
		if (defined('LOCKUTIL_NOGC') && (LOCKUTIL_NOGC)) {
			return;
		}

		if (rand(0,100) > 5) {
			if (!$force) {
				return;
			}
		}
		
		$files = glob(KATATMP.'sessions'.DS.'lockfile*', GLOB_NOSORT);
		$maxAge = time()-100;
		foreach ($files as $file) {
			if (filemtime($file) < $maxAge) {
				@unlink($file);
			}
		}
	}

	/**
	 * output a word of warning if we forgot to unlock some ids
	 */
	function __destruct() {
		if (count($this->locks) > 0) {
			if (DEBUG > 0) {
				foreach ($this->locks as $id=>$fp) {
					$this->unlock($id);
				}
				writeLog("these locks have not been unlocked:".print_r($this->locks, true),1);
			}
		}
	}

}
