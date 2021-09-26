<?php
/**
 * The cluster Model - supports replication and failover, like hyperdb
 *
 * Kata - Lightweight MVC Framework <http://www.codeninja.de/>
 * Copyright 2007-2015 mnt@codeninja.de
 *
 * Licensed under The GPL License
 * Redistributions of files must retain the above copyright notice.
 * @package kata_model
 */





/**
 * The cluster enabled model-class
 * give it an array of connection-names for database-masters (those are used to alter data)
 * and an array of connection names for slaves (those are used to read data) and it
 * automatically cycles between them if one fails. master and slave may overlap, the
 * same connection is used then.
 *
 * @package kata_model
 * @author mnt@codeninja.de
 */
class ClusterModel extends Model {
/**
 * @var array array of connection names of database-slaves to use for reads
 */
	public $slaves = null;
	/**
	 * @var integer index of the slave-array so we know which server to use
	 */
	private $slaveIndex = 0;

/**
 * @var array array of connection names of database-masters to use for writes
 */
	public $masters = null;
	/**
	 * @var integer index of the master-array so e know to use for writes
	 */
	private $masterIndex = 0;

/**
 * @var boolean connect to a random slave on startup?
 */
	public $randomSlave = false;

/**
 * @var boolean allow model to connect to next master/slave if connection fails
 */
	public $allowFailover = true;

/**
 * @var boolean switch reads to master after we altered db, so we dont read stale data because of replication-lag
 */
	public $readFromMasterAfterWrite = true;

/**
 * @var boolean we just did a write, switch reading to master
 */
	private $readFromMaster = false;

/**
 * @var boolean should we cache writes ourself so we dont have read from masters?
 */
	public $cacheQueries = false;

/**
 * @var hold status of current connections
 */
	private $connArr = array ();

	public function __construct() {
		if ($this->randomSlave) {
			$this->slaveIndex = rand(0, count($this->slaves) - 1);
		}
	}

	public function query($s,$idname=null) {
		if ($this->isReadQuery($s) && (!$this->readFromMaster)) {
			return $this->clusterQuery($s, $this->slaves, $this->slaveIndex);
		} else {
			if ($this->readFromMasterAfterWrite) {
				$this->readFromMaster = true;
			}
			return $this->clusterQuery($s, $this->masters, $this->masterIndex);
		}
	}

	private function clusterQuery($s, & $connArr, & $connIdx, $recCount = 0) {
		if (!is_array($connArr) || empty ($connArr)) {
			throw new Exception('clusterModel: connection-array is empty. where should i connect?');
		}

		// do we have the correct connection? if not change it
		if ($this->connection != $connArr[$connIdx]) {
			$this->changeConnection($connArr[$connIdx]);
		}

		// connect if this is the first time we use this host
		if (!$this->dbo()->isConnected()) {
			try {
				$this->dbo->connect();
			} catch (Exception $e) {
				// connection error, try next one if we did not try the whole array already
				if ($recCount = count($connArr)) {
					throw new Exception('clusterModel: could not connect, tried all servers');
				}
				if (!$this->allowFailover) {
					throw new Exception('clusterModel: cant connect to database');
				}
				// cycle to next connection, try again
				$connIdx = $connIdx % count($connArr);
				return $this->clusterQuery($s, $connArr, $connIdx, $recCount +1);
			}
			$this->setConnection($connArr[$connIdx],$this->dbo->getLink());
		}

		return parent :: query($s);
	}

/**
 * does the current sql read or write?
 * @param string $s sql-string
 * @return boolean true if read
 */
	private function isReadQuery($s) {
		$s = substr(strtolower(trim($s)), 0, 6);
		if ('select' == $s) {
			return true;
		}
		if ('insert' == $s) {
			return false;
		}
		if ('replac' == $s) {
			return false;
		}
		if ('update' == $s) {
			return false;
		}
		if ('delete' == $s) {
			return false;
		}
		if ('trunca' == $s) {
			return false;
		}
		if ('alter ' == $s) {
			return false;
		}
		if ('show t' == $s) {
			return false;
		}
		return true;
	}

}
