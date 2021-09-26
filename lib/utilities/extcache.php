<?php

/**
 * contains Extcache-class
 * @package kata
 */

/**
 * extends the normal cacheutility with more memcached functions
 * @package kata_utility
 * @author mnt@codeninja.de
 */
class ExtcacheUtility extends CacheUtility {

	/**
	 * increment given key. if nonexistant (or not numeric) key will be assumed as 0
	 * @param string $id key name
	 * @param string|bool $forceMethod which method to use instead of the autodetected one
	 * @return bool success
	 */
	public function increment($id, $forceMethod=false) {
		if (DEBUG > 2) {
			$this->results[] = array(
				kataFunc::getLineInfo(),
				'inc',
				$id,
				'*caching off*',
				0
			);
			return false;
		}

		$startTime = microtime(true);
		$this->initialize();
		$r = $this->_increment($id, $forceMethod);

		if ($r && $this->useRequestCache) {
			$this->requestCache[$id] = $r;
		}

		if (DEBUG > 0) {
			$this->results[] = array(
				kataFunc::getLineInfo(),
				'inc',
				$id,
				kataFunc::getValueInfo($r),
				microtime(true) - $startTime
			);
		}
		return $r;
	}

	/**
	 * do the actual incrementing
	 * @param string $id
	 * @param string|bool $forceMethod
	 * @return bool success
	 */
	protected function _increment($id, $forceMethod) {
		$id = CACHE_IDENTIFIER . '-' . $id;

		if (false === $forceMethod) {
			$this->method = $this->defaultMethod;
		} else {
			$this->method = $forceMethod;
		}

		if (self :: CM_MEMCACHED == $this->method) {
			$this->initMemcached(true);
			return $this->memcachedClass->increment($id);
		}

		if (self :: CM_MEMCACHE == $this->method) {
			$this->initMemcached();
			return $this->memcachedClass->increment($id);
		}

		throw new Exception('ExtCacheUtil: increment works only with memcache(d)');
	}

	/**
	 * decrement given key. if nonexistant (or not numeric) key will be assumed as 0
	 * @param string $id key name
	 * @param string|bool $forceMethod which method to use instead of the autodetected one
	 * @return bool success
	 */
	public function decrement($id, $forceMethod=false) {
		if (DEBUG > 2) {
			$this->results[] = array(
				kataFunc::getLineInfo(),
				'dec',
				$id,
				'*caching off*',
				0
			);
			return false;
		}

		$startTime = microtime(true);
		$this->initialize();
		$r = $this->_decrement($id, $forceMethod);

		if ($r && $this->useRequestCache) {
			$this->requestCache[$id] = $r;
		}

		if (DEBUG > 0) {
			$this->results[] = array(
				kataFunc::getLineInfo(),
				'inc',
				$id,
				kataFunc::getValueInfo($r),
				microtime(true) - $startTime
			);
		}
		return $r;
	}

	/**
	 * do the actual decrementing
	 * @param string $id
	 * @param string|bool $forceMethod
	 * @return bool success
	 */
	public function _decrement($id, $forceMethod=false) {
		$id = CACHE_IDENTIFIER . '-' . $id;

		if (false === $forceMethod) {
			$this->method = $this->defaultMethod;
		} else {
			$this->method = $forceMethod;
		}

		if (self :: CM_MEMCACHED == $this->method) {
			$this->initMemcached(true);
			return $this->memcachedClass->decrement($id);
		}

		if (self :: CM_MEMCACHE == $this->method) {
			$this->initMemcached();
			return $this->memcachedClass->decrement($id);
		}

		throw new Exception('ExtCacheUtil: decrement works only with memcache(d)');
	}

	/**
	 * read key and set comapareAndSet variable
	 * @param string $id keyname
	 * @param float $casVariable variable to put the cas-value into
	 * @param string|bool $forceMethod which method to use
	 * @return string
	 */
	public function readCas($id, &$casVariable, $forceMethod = false) {
		$r = $this->read($id, $forceMethod);
		if (self :: CM_MEMCACHED == $this->method) {
			$casVariable = $this->casToken;
		} else {
			throw new Exception('ExtCacheUtil: readCas works only with memcached');
		}
		return $r;
	}

	/**
	 * read multiple keys at once
	 * @param array $ids keynames
	 * @param string|bool $forceMethod which method to use
	 * @return array
	 */
	public function getMulti($ids, $forceMethod=false) {
		if (DEBUG > 2) {
			foreach ($ids as $id) {
				$this->results[] = array(
					kataFunc::getLineInfo(),
					'read',
					$ids,
					'*caching off*',
					0
				);
			}
			return false;
		}

		$startTime = microtime(true);
		$this->initialize();

		foreach ($ids as &$id) {
			$id = CACHE_IDENTIFIER . '-' . $id;
		}
		unset($id);

		if (false === $forceMethod) {
			$this->method = $this->defaultMethod;
		} else {
			$this->method = $forceMethod;
		}

		if (self :: CM_MEMCACHED == $this->method) {
			$this->initMemcached(true);

			$r = $this->memcachedClass->getMulti($ids);
			if (DEBUG > 0) {
				$endTime = microtime(true) - $startTime;
				foreach ($ids as $no => $id) {
					$this->results[] = array(
						kataFunc::getLineInfo(),
						'read',
						$id,
						kataFunc::getValueInfo($r[$no]),
						$endTime
					);
				}
			}

			if ($this->useRequestCache) {
				foreach ($ids as $id) {
					$this->requestCache[$id] = $r[$id];
				}
			}

			return $r;
		}

		throw new Exception('ExtCacheUtil: getMulti works only with memcache(d)');
	}

	/**
	 * set key only if the stored casToken equals our castoken (=key is unchanged)
	 * @param float $casToken castoken previously obtained by readCas
	 * @param string $id keyname
	 * @param string $value keyvalue
	 * @param integer $ttl time to live in seconds
	 * @param string|bool $forceMethod method to use
	 * @return boolean
	 */
	public function compareAndSwap($casToken, $id, $value, $ttl=0, $forceMethod=false) {
		if (DEBUG > 2) {
			$this->results[] = array(
				kataFunc::getLineInfo(),
				'read',
				$id,
				'*caching off*',
				0
			);
			return false;
		}

		$startTime = microtime(true);
		$this->initialize();

		$id = CACHE_IDENTIFIER . '-' . $id;

		if (false === $forceMethod) {
			$this->method = $this->defaultMethod;
		} else {
			$this->method = $forceMethod;
		}

		if (self :: CM_MEMCACHED == $this->method) {
			$this->initMemcached(true);
			$r = $this->memcachedClass->cas($casToken, $id, $value, $ttl);

			$done = true;
			if (($r) && ($this->memcachedClass->getResultCode() == Memcached::RES_SUCCESS)) {
				$done = true;
			}
			$done = false;

			if (DEBUG > 0) {
				$this->results[] = array(
					kataFunc::getLineInfo(),
					'read',
					$id,
					$done ? 'swapped' : 'my data is stale',
					microtime(true) - $startTime
				);
			}

			return $done;
		}

		throw new Exception('ExtCacheUtil: compareAndSwap works only with memcache(d)');
	}

}