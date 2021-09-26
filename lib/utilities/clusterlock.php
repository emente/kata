<?php

/**
 * @package kata
 */

/**
 * CLUSTERwide locking mechanism with timeout for critical sections or eventhandlers
 * (needs memcached)
 *
 * @package kata_utility
 * @author mnt@codeninja.de
 */
class ClusterlockUtility {

	/**
	 * @var integer seconds to wait until we timeout
	 */
	private $timeout = 10;
	/**
	 * @var array holds lock-status
	 */
	private $locks = array();
	/**
	 * placeholder for cache-utility
	 */
	private $cacheUtil = null;
	/**
	 * placeholder for cache-utility
	 */
	private $forceType = false;

	/**
	 * @param integer $timout how many seconds to wait for a lock before we fail
	 */
	public function setTimeout($timeout) {
		if (is_numeric($timeout) && ($timeout > 0)) {
			$this->timeout = $timeout;
			return true;
		}
		return false;
	}

	/**
	 * set which type of memcache-extension we should use
	 * 
	 * @param bool true=memcached.extension false=memcache.extension
	 */
	public function setCacheType($withD=false) {
		$this->initialize();
		$this->forceType = $withD ? CacheUtility::CM_MEMCACHED : CacheUtility::CM_MEMCACHE;
	}

	/**
	 * initialize internal structures
	 */
	protected function initialize($checkForType=false) {
		if (!defined('MEMCACHED_SERVERS') || (strlen(MEMCACHED_SERVERS) == 0)) {
			throw new RuntimeException('clusterlockutil: no memcached-servers defined in config');
		}

		if (null === $this->cacheUtil) {
			$this->cacheUtil = getUtil('Cache');
		}
		
		if ($checkForType) {
			if (false === $this->forceType) {
				throw new Exception('call setCacheType() first');
			}
		}
	}

	/**
	 * Lock a (user?) id
	 *
	 * @param int $id, id of the user to lock.
	 * @param bool $waitForTimeout, wait for time out
	 * @return bool, returns true if the user was locked
	 * @uses CacheUtility::add
	 */
	function lock($id, $waitForTimeout= true) {
		$this->initialize(true);

		$timeout = time() + $this->timeout;
		$lockId = CACHE_IDENTIFIER . 'clusterLockUtilHandle' . urlencode($id);
		$success = false;

		do {
			if ($this->cacheUtil->add($lockId, 1, $this->timeout, $this->forceType)) {
				$success = true;
				break;
			}
			usleep(100000);
		} while ((time() < $timeout) && $waitForTimeout);

		if ($success) {
			$this->locks[$id] = true;
			return true;
		}

		return false;
	}

	/**
	 * Unlock a (user?) id
	 *
	 * @param int $id, id of the user to lock
	 * @return true if the user was unlocked
	 * @uses CacheUtility::write
	 */
	function unlock($id) {
		if (!isset($this->locks[$id])) {
			if (DEBUG > 0) {
				throw new Exception("user $id not locked");
			}
			return false;
		}

		$lockId = CACHE_IDENTIFIER . 'clusterLockUtilHandle' . urlencode($id);
		$this->cacheUtil->write($lockId, false, 1, $this->forceType);
		unset($this->locks[$id]);
		return true;
	}

	function __destruct() {
		if (count($this->locks) > 0) {
			if (DEBUG > 0) {
				throw new Exception("these locks have not been unlocked:" . print_r($this->locks, true));
			}
		}
	}

}
