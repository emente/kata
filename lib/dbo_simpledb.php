<?php


/**
 * Contains a wrapper-class for mssql, so models can access simpledb
 *
 * Kata - Lightweight MVC Framework <http://www.codeninja.de/>
 * Copyright 2007-2008, mnt@codeninja.de
 *
 * Licensed under The LGPL License
 * Redistributions of files must retain the above copyright notice.
 * @package kata
 */

/**
 * this class is used by the model to access the database itself
 * @package kata
 * @author mnt@codeninja.de
 */
class dbo_simpledb {

	/**
	 * a copy of the matching db-config entry in config/database.php
	 * @var array
	 */
	public $dbconfig= null;

	/**
	 * a placeholder for any result the database returned
	 * @var array
	 */
	private $result= null;

	/**
	 * a placeholder for the database link needed for this database
	 * @var int
	 */
	private $link= null;

	/**
	 * are we already connected? used to connect to the database in the last possible moment to save unneeded connects
	 * @var boolean
	 */
	private $connected= false;

	/**
	 * an array that holds all queries and some relevant information about them if DEBUG
	 * @var array
	 */
	private $queries= array ();

	/**
	 * constants used to quote table and field names
	 *
	 */
	public $quoteLeft= '[';
	public $quoteRight= ']';

	/**
	 * connect to the database
	 */
	function connect() {
		$this->link= simpledb_connect($this->dbconfig['host'], $this->dbconfig['login'], $this->dbconfig['password']);
		if (!$this->link) {
			throw new DatabaseConnectException("Could not connect to Database ".$this->dbconfig['host']);
		}
		if (!empty ($this->dbconfig['database'])) {
			if (!simpledb_select_db($this->dbconfig['database'], $this->link)) {
				throw new DatabaseConnectException("Could not select Database ".$this->dbconfig['database']);
			}
		}

		if (!empty($this->dbconfig['encoding'])) {
			//TODO well... do semething $this->query("FOO '".$this->dbconfig['encoding']."'");
		}

		$this->connected= true;
	}

	function isConnected() {
		return $this->connected;
	}

	/**
	 * return the current link to the database, connect first if needed
	 */
	public function getLink() {
		if (!$this->connected) {
			$this->connect();
		}
		return $this->link;
	}

	public function setLink($l) {
		$this->link= $l;
		$this->connected= true;
	}

	/**
	 * execute this query
	 * @return mixed
	 */
	private function execute($sql) {
		if (!$this->connected) {
			$this->connect();
		}

		$start= microtime(true);
		$this->result= simpledb_query($sql, $this->link);

		if (false === $this->result) {
			writeLog(simpledb_get_last_message().': '.$sql, 1);
			throw new DatabaseErrorException(simpledb_get_last_message());
		}
		if (DEBUG > 0) {
			$this->queries[]= array (
				kataGetLineInfo(),
				$sql,
				'',
				simpledb_get_last_message(),
				 (microtime(true) - $start).'sec'
			);
		}
	}

	/**
	 * return numbers of rows affected by last query
	 * @return int
	 */
	private function lastAffected() {
	}

	/**
	 * return id of primary key of last insert
	 * @return int
	 */
	private function lastInsertId() {
	}

	/**
	 * return the result of the last query.
	 * @param mixed $idname if $idname is false keys are simply incrementing from 0, if $idname is string the key is the value of the column specified in the string
	 */
	private function & lastResult() {
	}

	/**
	 * execute query and return useful data depending on query-type
	 *
	 * @param string $s sql-statement
	 * @param string $idname which field-value to use as the key of the returned array (false=dont care)
	 * @return array
	 */
	function & query($s, $idnames= false) {
		$s= trim($s);

		$this->execute($s);

		$what= strtolower(substr($s, 0, 5));
		$result= null;
		switch ($what) {
			case 'delet' :
				break;

			case 'repla' :
			case 'updat' :
				break;

			case 'inser' :
				break;

			case 'selec' :
				$result= $this->lastResult();
				return $result;
				break;

			case 'set n' :
				$result= null;
				return $result;
				break;
		}

		throw new Exception('Dont know what to do with your query');
	}

	/**
	 * escape the given string so it can be safely appended to any sql
	 * @param string $sql string to escape
	 * @return string
	 */
	function escape($sql) {
		return str_replace("'", "''", $sql);
	}

	/**
	 * output any queries made, how long it took, the result and any errors if DEBUG
	 */
	function __destruct() {
		if (DEBUG > 0) {
			array_unshift($this->queries, array (
				'line',
				'',
				'affected',
				'error',
				'time'
			));
			kataDebugOutput($this->queries, true);
		}
		if ($this->connected) {
			simpledb_close($this->link);
		}
	}

	/**
	 * build a sql-string that returns paged data
	 * @return string finished query
	 */
	function getPageQuery($sql, $page, $perPage) {

	}

	/**
	 * return the sql needed to convert a unix timestamp to datetime
	 * @param integer $t unixtime
	 * @return string
	 */
	function makeDateTime($t) {

	}

	/**
	 * try to reduce the fields of given table to the basic types bool, unixdate, int, string, float, date, enum
	 *
	 * <code>example:
	 *
	 * Array
	 * (
	 *     [table] => test
	 *     [primary] => a
	 *     [cols] => Array
	 *         (
	 *             [a] => Array
	 *                 (
	 *                     [default] => CURRENT_TIMESTAMP
	 *                     [null] =>
	 *                     [unique] =>
	 *                     [length] => 0
	 *                     [type] => date
	 *                 )
	 *
	 *             [g] => Array
	 *                 (
	 *                     [default] =>
	 *                     [null] =>
	 *                     [unique] =>
	 *                     [length] => 0
	 *                     [type] => unsupported:time
	 *                 )
	 *
	 *             [j] => Array
	 *                 (
	 *                     [default] =>
	 *                     [null] =>
	 *                     [unique] =>
	 *                     [length] => 0
	 *                     [type] => enum
	 *                     [values] => Array
	 *                         (
	 *                             [0] => a
	 *                             [1] => B
	 *                             [2] => c
	 *                         )
	 *
	 *                 )
	 *
	 *         )
	 *
	 * )
	 * </code>
	 *
	 * @param string $tableName name of the table to analyze
	 * @return unknown
	 */
	function & describe($tableName) {
	}
}
