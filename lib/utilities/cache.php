<?php
/**
 * contains Cache-class
 * @package kata
 */





/**
 * a universal caching class that can store data using several methods
 * @package kata_utility
 * @author mnt@codeninja.de
 * @author mnt@codeninja.de
 * @author jo@wurzelpilz.de
 */
class CacheUtility {

	/**
	 * which store/read-method to use
	 * @var string
	 */
	protected $method = null;

	protected $defaultMethod = null;

	protected $useRequestCache = true;
	protected $requestCache = array();

	/**
	 * some internal constants
	 */
	const CM_FILE = 'file';
	const CM_EACC = 'eacc'; //eaccelerator (please use apc)
	const CM_APC = 'apc';
	const CM_XCACHE = 'xcache';
	const CM_MEMCACHED = 'memcached'; //yeah. with a d
	const CM_MEMCACHE = 'memcache'; //without a d

	protected $isInitialized = false;
	/**
	 * set first store/read-method as default
	 */
	function initialize() {
		if ($this->isInitialized) {
			return;
		}
		$this->results = array ();
		kataMakeTmpPath('cache');

		if (defined('CACHE_USEMETHOD')) {
			$this->method = CACHE_USEMETHOD;
			$this->defaultMethod = CACHE_USEMETHOD;
			$this->isInitialized = true;
			return;
		}

		if (defined('MEMCACHED_SERVERS') && ('' != MEMCACHED_SERVERS) && class_exists('Memcached')) {
			$this->method = self :: CM_MEMCACHED;
			$this->defaultMethod = self :: CM_MEMCACHED;
			$this->initMemcached(true);
			$this->isInitialized = true;
			return;
		}
		if (defined('MEMCACHED_SERVERS') && ('' != MEMCACHED_SERVERS) && class_exists('Memcache')) {
			$this->method = self :: CM_MEMCACHE;
			$this->defaultMethod = self :: CM_MEMCACHE;
			$this->initMemcached();
			$this->isInitialized = true;
			return;
		}
		if (function_exists('apc_fetch')) {
			if (((CLI!=1) && ini_get('apc.enabled')) || ((CLI==1) && ini_get('apc.enable_cli'))) {
				$this->method = self :: CM_APC;
				$this->defaultMethod = self :: CM_APC;
				$this->isInitialized = true;
				return;
			}
		}
		if (function_exists('xcache_get')) {
			$this->method = self :: CM_XCACHE;
			$this->defaultMethod = self :: CM_XCACHE;
			$this->isInitialized = true;
			return;
		}
		if (function_exists('eaccelerator_get') && ini_get('eaccelerator.enable')) {
			$this->method = self :: CM_EACC;
			$this->defaultMethod = self :: CM_EACC;
			$this->isInitialized = true;
			return;
		}

		$this->method = self :: CM_FILE;
		$this->defaultMethod = self :: CM_FILE;
		$this->isInitialized = true;
	}

	/**
	 * @return int caching-method used for the last read/write
	 */
	function getMethodUsed() {
		return $this->method;
	}

	protected $memcachedClass = null;
	
	/**
	 * add all memcache-servers we know about, or just return if we already did it.
	 * uses all servers defined in MEMCACHED_SERVERS, see core.php
	 */
	protected function initMemcached($withD = false) {
		if (null !== $this->memcachedClass) {
			return;
		}

		if ($withD) {
			$this->memcachedClass = new Memcached;
		} else {
			ini_set('memcache.allow_failover', true);
			ini_set('memcache.hash_strategy', 'consistent');
			ini_set('memcache.hash_function', 'fnv');
			$this->memcachedClass = new Memcache;
		}

		$servers = explode(',', MEMCACHED_SERVERS);
		foreach ($servers as $server) {
			$temp = explode(':', $server);
			$this->memcachedClass->addServer($temp[0], empty ($temp[1]) ? 11211 : $temp[1], 1);
		}
	}

	function getMemcacheStats() {
		$this->initialize();

		if (is_a($this->memcachedClass,'Memcache')) {
			return $this->memcachedClass->getExtendedStats();
		}
		if (is_a($this->memcachedClass,'Memcached')) {
			return $this->memcachedClass->getStats();
		}

		return array();
	}


	/**
	 * holds data for debug-output
	 */
	protected $results=array();

	/**
	 * output debugging data if needed
	 */
	function __destruct() {
		if (DEBUG > 0) {
			array_unshift($this->results , array (
					'line',
					'op',
					'id',
					'data',
					'time'
			));
			kataDebugOutput($this->results, true);
		}
		unset($this->requestCache);
	}

	function add($id, $data, $ttl = 0, $forceMethod = false) {
		if (DEBUG > 2) {
			$this->results[] = array (
					kataFunc::getLineInfo(),
					'add',
					$id,
					'*caching off*',
					0
			);
			return false;
		}
		$startTime = microtime(true);
		$this->initialize();
		$r = $this->_add($id, $data, $ttl, $forceMethod);

		if ($r && $this->useRequestCache) {
			$this->requestCache[$id] = $data;
		}

		if (DEBUG > 0) {
			$this->results[] = array (
					kataFunc::getLineInfo(),
					'add',
					$id,
					kataFunc::getValueInfo($data),
					microtime(true) - $startTime
			);
		}
		return $r;
	}

	function write($id, $data, $ttl = 0, $forceMethod = false) {
		if (DEBUG > 2) {
			$this->results[] = array (
					kataFunc::getLineInfo(),
					'write',
					$id,
					'*caching off*',
					0
			);
			return false;
		}
		$startTime = microtime(true);
		$this->initialize();
		$r = $this->_write($id, $data, $ttl, $forceMethod);

		if ($r && $this->useRequestCache) {
			$this->requestCache[$id] = $data;
		}

		if (DEBUG > 0) {
			$this->results[] = array (
					kataFunc::getLineInfo(),
					'write',
					$id,
					kataFunc::getValueInfo($data),
					microtime(true) - $startTime
			);
		}
		return $r;
	}

	function read($id, $forceMethod = false) {
		if (DEBUG > 2) {
			$this->results[] = array (
					kataFunc::getLineInfo(),
					'read',
					$id,
					'*caching off*',
					0
			);
			return false;
		}

		if ($this->useRequestCache && isset($this->requestCache[$id])) {
			$data = $this->requestCache[$id];
			if (DEBUG > 0) {
				$this->results[] = array (
						kataFunc::getLineInfo(),
						'reqCache',
						$id,
						kataFunc::getValueInfo($data),
						0
				);
			}
			return $data;
		}

		$startTime = microtime(true);
		$this->initialize();
		$data = $this->_read($id, $forceMethod);
		if (DEBUG > 0) {
			$this->results[] = array (
					kataFunc::getLineInfo(),
					'read',
					$id,
					kataFunc::getValueInfo($data),
					microtime(true) - $startTime
			);
		}

		if ($this->useRequestCache) {
			$this->requestCache[$id] = $data;
		}

		return $data;
	}

	/**
	 * Disables the request cache
	 */
	public function disableRequestCache() {
		$this->useRequestCache = false;
	}

	/**
	 * Enables the request cache
	 */
	public function enableRequestCache() {
		$this->useRequestCache = true;
	}

	/**
	 * @var float|null
	 */
	protected $casToken=null;

	/**
	 * Read data from the cache
	 *
	 * @param string $id an unique-id of the data you want to read
	 * @param int $forceMethod which caching-method to use (only this time)
	 * @return mixed returns array or false if data could not be read
	 */
	protected function _read($id, $forceMethod = false) {
		$false = false;
		$id = CACHE_IDENTIFIER . '-' . $id;

		if (false === $forceMethod) {
			$this->method = $this->defaultMethod;
		} else {
			$this->method = $forceMethod;
		}

		if (self :: CM_MEMCACHED == $this->method) {
			$this->initMemcached(true);
			return $this->memcachedClass->get($id,null,$this->casToken);
		}

		if (self :: CM_MEMCACHE == $this->method) {
			$this->initMemcached();
			return $this->memcachedClass->get($id);
		}

		if (self :: CM_APC == $this->method) {
			return apc_fetch($id);
		}

		if (self :: CM_XCACHE == $this->method) {
			if (xcache_isset($id)) {
				return xcache_get($id);
			}
			return false;
		}

		if (self :: CM_EACC == $this->method) {
			$data = eaccelerator_get($id);
			if (null === $data) {
				return $false;
			}
			return @unserialize($data);
		}

		if (self :: CM_FILE == $this->method) {
			$fname = KATATMP . 'cache' . DS . urlencode($id);
			if (file_exists($fname)) {
				$temp = file_get_contents($fname);
				if ($temp !== false) {
					$temp = unserialize($temp);
					if ((0 == $temp['ttl']) || ($temp['ttl'] > time())) {
						return $temp['data'];
					}
				}
			}
			return $false;
		}

		throw new Exception('cacheUtil: unknown cache-method used');
	}

	/**
	 * write data to the cache. if data is false, the item will be purged from cache
	 *
	 * @param string $id  an unique-id of the data you want to write
	 * @param mixed $data data to write
	 * @param int $ttl time to live in seconds
	 * @param int $forceMethod which caching method to use (only this time)
	 * @return boolean true on success
	 */
	protected function _write($id, $data, $ttl = 0, $forceMethod = false) {
		$id = CACHE_IDENTIFIER . '-' . $id;

		if (false === $forceMethod) {
			$this->method = $this->defaultMethod;
		} else {
			$this->method = $forceMethod;
		}

		if (self :: CM_MEMCACHED == $this->method) {
			$this->initMemcached(true);
			if (false === $data) {
				return $this->memcachedClass->delete($id);
			}
			return $this->memcachedClass->set($id, $data, $ttl);
		}

		if (self :: CM_MEMCACHE == $this->method) {
			$this->initMemcached();
			if (false === $data) {
				return $this->memcachedClass->delete($id);
			}
			return $this->memcachedClass->set($id, $data, false, $ttl);
		}

		if (self :: CM_APC == $this->method) {
			if (false === $data) {
				return apc_delete($id);
			}
			return apc_store($id, $data, $ttl);
		}

		if (self :: CM_XCACHE == $this->method) {
			if (false === $data) {
				return xcache_unset($id);
			}
			return xcache_set($id, $data, $ttl);
		}

		if (self :: CM_FILE == $this->method) {
			$fname = KATATMP . 'cache' . DS . urlencode($id);
			if (false === $data) {
				return unlink($fname);
			}
			$temp = serialize(array (
					'ttl' => ($ttl > 0 ? time() + $ttl : 0),
					'data' => $data
			));
			return file_put_contents($fname, $temp);
		}

		if (self :: CM_EACC == $this->method) {
			if (false === $data) {
				return eaccelerator_rm($id);
			}
			return eaccelerator_put($id, serialize($data), $ttl);
		}

		throw new Exception('cacheUtil: unknown cache-method used');
	}

	/**
	 * write only data to the cache if item is nonexistant or expired
	 *
	 * @param string $id  an unique-id of the data you want to write
	 * @param mixed $data data to write
	 * @param int $ttl time to live in seconds
	 * @param int $forceMethod which caching method to use (only this time)
	 * @return boolean true on success
	 */
	protected function _add($id, $data, $ttl = 0, $forceMethod = false) {
		$id = CACHE_IDENTIFIER . '-' . $id;

		if (false === $forceMethod) {
			$this->method = $this->defaultMethod;
		} else {
			$this->method = $forceMethod;
		}

		if (self :: CM_MEMCACHED == $this->method) {
			$this->initMemcached(true);
			return $this->memcachedClass->add($id, $data, $ttl);
		}

		if (self :: CM_MEMCACHE == $this->method) {
			$this->initMemcached();
			return $this->memcachedClass->add($id, $data, false, $ttl);
		}

		if (self :: CM_APC == $this->method) {
			return apc_add($id, $data, $ttl);
		}

		if (self :: CM_XCACHE == $this->method) {
			if (!xcache_isset($id)) {
				return xcache_set($id, $data, $ttl);
			}
			return false;
		}

		if (self :: CM_FILE == $this->method) {
			$fname = KATATMP . 'cache' . DS . urlencode($id);
			if (file_exists($fname)) {
				return false;
			}

			// file not expired?
			$temp = file_get_contents($fname);
			if ($temp !== false) {
				$temp = unserialize($temp);
				if (($temp['ttl'] > 0) && ($temp['ttl'] < time())) {
					return false;
				}
			}

			$temp = serialize(array (
					'ttl' => ($ttl > 0 ? time() + $ttl : 0),
					'data' => $data
			));
			return file_put_contents($fname, $temp);
		}

		if (self :: CM_EACC == $this->method) {
			if (null !== eaccelerator_get($id)) {
				return false;
			}
			return eaccelerator_put($id, $data, $ttl);
		}

		throw new Exception('cacheUtil: unknown cache-method used');
	}

}
