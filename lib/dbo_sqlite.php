<?php


/**
 * Contains a wrapper-class for sqlite3, so models can access sqlite3
 *
 * Kata - Lightweight MVC Framework <http://www.codeninja.de/>
 * Copyright 2007-2015 mnt@codeninja.de
 *
 * Licensed under The GPL License
 * Redistributions of files must retain the above copyright notice.
 * @package kata_model
 */

/**
 * this class is used by the model to access the database itself
 * @package kata_model
 */
class dbo_sqlite { //implements dbo_interface {

	/**
	 * a copy of the matching db-config entry in config/database.php
	 * @var array
	 */
	private $dbconfig = null;

	/**
	 * a placeholder for any result the database returned
	 * @var array
	 */
	private $result = null;

	/**
	 * a placeholder for the database link needed for this database
	 * @var int
	 */
	private $link = null;

	/**
	 * an array that holds all queries and some relevant information about them if DEBUG>1
	 * @var array
	 */
	private $queries = array ();

	/**
	 * constants used to quote table and field names
	 */
	private $quoteLeft = '`';
	private $quoteRight = '`';

	/**
	 * connect to the database
	 */
	function connect() {
		kataMakeTmpPath('sqlite');

		$db = $this->dbconfig['database'];
		if ($db[0] != DS) {
			$db = KATATMP.'sqlite'.DS.$db;
		}

		$this->link = new SQLite3($db,0750);
		if (!$this->link) {
			throw new DatabaseConnectException("Could not open database " . $db);
		}

		if (!empty ($this->dbconfig['encoding'])) {
			$this->setEncoding($this->dbconfig['encoding']);
		}
	}

	/**
	 * if we are already connected
	 * @return bool
	 */
	function isConnected() {
		return (bool) $this->link;
	}

	/**
	 * return the current link to the database, connect first if needed
	 */
	public function getLink() {
		if (!$this->link) {
			$this->connect();
		}
		return $this->link;
	}

	/**
	 * inject database link into dbo
	 */
	public function setLink($l) {
		$this->link = $l;
	}

	/**
	 * execute this query
	 * @return mixed
	 */
	private function execute($sql) {
		if (!$this->link) {
			$this->connect();
		}

		$start = microtime(true);
		$error = 0;
		$this->result = $this->link->query($sql);
		if (false === $this->result) {
			writeLog($this->link->lastErrorMsg() . ': ' . $sql, 1);
			throw new DatabaseErrorException($this->link->lastErrorMsg());
		}
		if (DEBUG > 0) {
			$this->queries[] = array (
				kataFunc::getLineInfo(),
				trim($sql),
				$this->link->changes(),
				$this->link->lastErrorMsg(),
				 (microtime(true) - $start) . 'sec'
			);
		}
	}

	/**
	 * unused right now
	 */
	function setEncoding($enc) {
	}

	/**
	 * return numbers of rows affected by last query
	 * @return int
	 */
	private function lastAffected() {
		if ($this->link) {
			return $this->link->changes();
		}
		$null = null;
		return $null;
	}

	/**
	 * return id of primary key of last insert
	 * @return int
	 */
	private function lastInsertId() {
		$id = $this->link->lastInsertRowId();
		return $id;
	}

	/**
	 * return the result of the last query.
	 * @param mixed $idname if $idname is false keys are simply incrementing from 0, if $idname is string the key is the value of the column specified in the string
	 */
	private function & lastResult($idnames = false) {
		$result = array ();
		if (($this->result instanceof SQLite3Result) &&($this->result->numColumns>0)) {
			if ($idnames === false) {
				while ($row = $this->result->fetchArray(SQLITE3_NUM)) {
					$result[] = $row;
				}
			} else {
				while ($row = $this->result->fetchArray(SQLITE3_ASSOC)) {
					$current = & $result;
					foreach ($idnames as $idname) {
						if (!isset($row[$idname])) {
							throw new InvalidArgumentException('Cant order result by a field thats not in the resultset (forgot to select it?)');
						}
						if ($row[$idname] === null) {
							$row[$idname] = 'null';
						}
						$current = & $current[$row[$idname]];
					} //foreach
					$current = $row;
				} //while fetch
			} //idnames
		} //rows>0
		return $result;
	}

	/**
	 * REPLACE works exactly like INSERT,
	 * except that if an old row in the table has the same value as a new row for a PRIMARY KEY or a UNIQUE  index,
	 * the old row is deleted before the new row is inserted
	 *
	 * @param string $tableName replace from this table
	 * @param array $fields name=>value pairs of new values
	 * @param string $pairs enquoted names to escaped pairs z.B.[name]='value'
	 * @return int modified rows.
	 */
	function replace($tableName, $fields, $pairs) {
		return $this->query('REPLACE INTO ' . $tableName . ' SET ' . $pairs);
	}

	/**
	 * execute query and return useful data depending on query-type
	 *
	 *  	SELECT / SHOW 											=> resultset array
	 * 		REPLACE / UPDATE / DELETE / ALTER 						=> affected rows (int)
	 * 		INSERT													=> last insert id (int)
	 * 		RENAME / LOCK / UNLOCK / TRUNCATE /SET / CREATE / DROP	=> Returns if the operation was successfull (boolean)
	 *
	 * @param string $s sql-statement
	 * @param string $idname which field-value to use as the key of the returned array (false=dont care)
	 * @return array
	 */
	function & query($s, $idnames = false, $fields = false) {
		$result = null;
		switch ($this->getSqlCommand($s)) {
			case 'replace' :
			case 'update' :
			case 'delete' :
			case 'alter' :
			case 'call':
				$this->execute($s);
				$result = $this->lastAffected();
				break;
			case 'insert' :
				$this->execute($s);
				$result = $this->lastInsertId();
				break;
			case 'select' :
			case 'show' :
				if (is_string($idnames)) {
					$idnames = array (
						$idnames
					);
				}
				$this->execute($s);
				$result = $this->lastResult($idnames, $fields);
				break;
			default :
				$this->execute($s);
				$result = $this->result;
				break;
		}
		return $result;
	}

	/**
	 * escape the given string so it can be safely appended to any sql
	 * @param string $sql string to escape
	 * @return string
	 */
	function escape($sql) {
		$sql = $this->link->quote($sql);
		return substr($sql,1,-1);
	}

	/**
	 * return sql needed to convert unix timestamp to datetime
	 * @param integer $t unixtime
	 * @return string
	*/
	function makeDateTime($t) {
		return 'datetime('.$t.', \'unixepoch\');';
	}

	/**
	 * output any queries made, how long it took, the result and any errors if DEBUG>1
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
		if ($this->link) {
			$this->link->close();
			$this->link = null;
		}
	}

	private function getFieldSize($str) {
		$x1 = strpos($str, '(');
		$x2 = strpos($str, ')');
		if ((false !== $x1) && (false !== $x2)) {
			return substr($str, $x1 +1, $x2 - $x1 -1);
		}
		return 0;
	}

	/**
	 * return the Sql-Command of given Query
	 * @param string $sql query
	 * @return string Sql-Command
	 */
	private function getSqlCommand($sql) {
		$sql = str_replace(array (
			"(",
			"\t",
			"\n"
		), " ", $sql);
		$Sqlparts = explode(" ", trim($sql));
		return strtolower($Sqlparts[0]);
	}

	/**
	 * build a sql-string that returns first matching row
	 * @param string $sql query
	 * @param string $perPage expression
	 * @return string (limited) Query
	 */
	function getFirstRowQuery($sql, $perPage) {
		return sprintf('%s LIMIT %d', $sql, $perPage);
	}

	/**
	 * build a sql-string that returns paged data
	 * @return string finished query
	 */
	function getPageQuery($sql, $page, $perPage) {
		return sprintf('%s LIMIT %d,%d', $sql, ($page -1) * $perPage, $perPage);
	}

	/**
	 * try to reduce the fields of given table to the basic types bool, unixdate, int, string, float, date, enum
	 *
	 * <code>example:
	 *
	 * Array
	 * (
	 *     [table] => test
	 *     [primary] => Array
	 * 	   [identity]=> a
	 *     [cols] => Array
	 *         (
	 *             [a] => Array
	 *                 (
	 *                     [default] => CURRENT_TIMESTAMP
	 *                     [null] =>
	 * 					   [key]	=> 'PRI'
	 *                     [length] => 0
	 *                     [type] => date
	 *                 )
	 *
	 *             [g] => Array
	 *                 (
	 *                     [default] =>
	 *                     [null] =>
	 * 					   [key]	=> 'UNI'
	 *                     [length] => 0
	 *                     [type] => unsupported:time
	 *                 )
	 *         )
	 *
	 * )
	 * </code>
	 *
	 * @param string $tableName name of the table to analyze
	 * @return unknown
	 */
	function & describe($tableName) {
		$primaryKey = array ();
		$identity = null;
		$desc = array ();
		$cols = array ();
		$sql = "SHOW COLUMNS FROM " . $tableName;
		$r = mysql_query($sql, $this->getLink());
		if (false == $r) {
			throw new Exception('model: cant describe, missing rights?');
		}
		$noResult = true;
		while ($row = mysql_fetch_assoc($r)) {
			$noResult = false;
			$data = array ();
			$data['default'] = $row['Default'];
			$data['null'] = 'NO' != $row['Null'];
			$data['length'] = 0;
			if ('auto_increment' == $row['Extra']) {
				$identity = $row['Field'];
			}
			//keys
			if ('PRI' == $row['Key']) {
				$primaryKey[] = $row['Field'];
			}
			$data['key'] = $row['Key'];

			//type
			$x = strpos($row['Type'], '(');
			$type = $x!==false ? substr($row['Type'], 0, $x) : $row['Type'];
			switch ($type) {
				case 'bit' :
					$data['type'] = 'bool';
					$data['length'] = 1;
					break;
				case 'bigint' :
				case 'int' :
				case 'smallint' :
				case 'tinyint' :
				case 'decimal':
					$data['length'] = $this->getFieldSize($row['Type']);
					$data['type'] = 'int';
					break;
				case 'char' :
				case 'varchar' :
					$data['length'] = $this->getFieldSize($row['Type']);
					$data['type'] = 'string';
					break;
				case 'text' :
					$data['type'] = 'string';
					break;
				case 'float' :
				case 'double' :
				case 'real' :
					$data['type'] = 'float';
					break;
				case 'date' :
				case 'datetime' :
				case 'time' :
				case 'timestamp' :
					$data['type'] = 'date';
					break;
				case 'set':
					$data['type'] = 'set';
					$data['values']  = 'foo';
				case 'blob':

					break;
			}
			$cols[$row['Field']] = $data;
		}

		if ($noResult === true) {
			throw new Exception('table does not exists in selected Database');
		}

		$desc = array (
			'table' => str_replace(array (
				$this->quoteLeft,
				$this->quoteRight
			), '', $tableName),
			'primary' => $primaryKey,
			'identity' => $identity,
			'cols' => $cols
		);
		return $desc;
	}

	/**
	 * a copy of the matching db-config entry in config/database.php
	 * @param $string $what spezifies what to get ... null=complete config array
	 * @return array|string
	 */
	function getConfig($what = null) {
		if (!empty ($what)) {
			return (isset ($this->dbconfig[$what])) ? $this->dbconfig[$what] : '';
		}
		return $this->dbconfig;
	}

	/**
	 * set db-config entry
	 * @param $array $config
	 */
	function setConfig($config) {
		if (empty ($this->dbconfig)) {
			$this->dbconfig = $config;
		}
	}

	/**
	* used to quote table and field names
	* @param string $s string to enquote;
	* @return string enquoted string
	*/
	function quoteName($s) {
		return $this->quoteLeft . $s . $this->quoteRight;
	}

	/**
	 * checks if given operator is valid
	 * @param string $operator
	 * @return boolean
	 */
	function isValidOperator($operator) {
		if (empty ($operator)) {
			return false;
		}
		$ops = array (
			'<=>'=>1,
			'='=>1,
			'>='=>1,
			'>'=>1,
			'<='=>1,
			'<'=>1,
			'<>'=>1,
			'!='=>1,
			'like'=>1,
			'not like'=>1,
			'is not null'=>1,
			'is null'=>1,
			'in'=>1,
			'not in'=>1,
			'between'=>1
		);
		return isset($ops[$operator]);
	}
}
