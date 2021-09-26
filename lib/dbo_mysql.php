<?php


/**
 * Contains a wrapper-class for mysql, so models can access mysql
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
class dbo_mysql { //implements dbo_interface {

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
		$this->link = mysql_connect($this->dbconfig['host'], $this->dbconfig['login'], $this->dbconfig['password'], true);
		
		if (!$this->link) {
			throw new DatabaseConnectException("Could not connect to server " . $this->dbconfig['host']);
		}
		if (!mysql_select_db($this->dbconfig['database'], $this->link)) {
			throw new DatabaseConnectException("Could not select database " . $this->dbconfig['database']);
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
		if (empty($this->link)){
			return false;
		}
		return mysql_ping($this->link);
	}

	/**
	 * return the current link to the database, connect first if needed
	 */
	public function getLink() {
		if (!mysql_ping($this->link)) {
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
		if (!$this->isConnected()) {
			$this->connect();
		}

		$start = microtime(true);
		$this->result = mysql_query($sql, $this->link);

		if (false === $this->result) {
			switch (mysql_errno($this->link)) {
				case 1062:
					throw new DatabaseDuplicateException(mysql_error($this->link));
					break;
				default:
					writeLog(mysql_error($this->link) . ': ' . $sql, 1);
					throw new DatabaseErrorException(mysql_error($this->link),$sql);
					break;
			}
		}
		if (DEBUG > 0) {
			$this->queries[] = array (
				kataFunc::getLineInfo(),
				trim($sql),
				mysql_affected_rows($this->link),
				mysql_error($this->link),
				 (microtime(true) - $start) . 'sec'
			);
		}
	}

	/**
	 * unused right now, later possibly used by model to set right encoding
	 */
	function setEncoding($enc) {
		$this->execute('SET NAMES ' . $enc);
		return $this->result;
	}

	/**
	 * return numbers of rows affected by last query
	 * @return int
	 */
	private function lastAffected() {
		if ($this->isConnected()) {
			return mysql_affected_rows($this->link);
		}
		$null = null;
		return $null;
	}

	/**
	 * return id of primary key of last insert
	 * @return int
	 */
	private function lastInsertId() {
		//TODO possible fix: select insertid from table status. problem: table name unknown

		//may lie to you: http://bugs.mysql.com/bug.php?id=26921
		$id = mysql_insert_id($this->link);
		if ($id) {
			return $id;
		}
		//may lie to you: if you have an auto-increment-table and you supply a primary key LAST_INSERT_ID is unchanged
		$result = $this->query("SELECT LAST_INSERT_ID() as id");
		if (!empty ($result)) {
			return $result[0]['id'];
		}
		$null = null;
		return $null;
	}

	/**
	 * return the result of the last query.
	 * @param mixed $idname if $idname is false keys are simply incrementing from 0, if $idname is string the key is the value of the column specified in the string
	 */
	private function & lastResult($idnames = false) {
		$result = array ();
		if (@ mysql_num_rows($this->result) > 0) {
			if ($idnames === false) {
				while ($row = mysql_fetch_assoc($this->result)) {
					$result[] = $row;
				}
			} else {
				while ($row = mysql_fetch_assoc($this->result)) {
					$current = & $result;
					foreach ($idnames as $idname) {
						if (!array_key_exists($idname,$row)) {
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
		if (!$this->link) {
			$this->connect();
		}
		return mysql_real_escape_string($sql, $this->link);
	}

	/**
	 * return sql needed to convert unix timestamp to datetime
	 * @param integer $t unixtime
	 * @return string
	*/
	function makeDateTime($t) {
		return 'FROM_UNIXTIME(' . $t . ')';
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
		if ((bool) $this->link) {
			//sometimes the resource FNORDs
			if (is_resource($this->link) && (get_resource_type($this->link) == 'mysql link')) {
				mysql_close($this->link);
			}
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

	/*deprecated(complex) tested. works fine
	function & describe($tableName) {
		$tableName = str_replace(array ($this->quoteLeft,$this->quoteRight), '', $tableName)
		$primaryKey= array();
		$desc= array ();
		$cols= array ();
		$sql = "Select a.COLUMN_NAME,IS_NULLABLE,COLUMN_DEFAULT,DATA_TYPE,CHARACTER_MAXIMUM_LENGTH,NUMERIC_PRECISION,NUMERIC_SCALE,CONSTRAINT_NAME,CONSTRAINT_TYPE
						from INFORMATION_SCHEMA.COLUMNS as a left join
							(SELECT a.COLUMN_NAME,b.CONSTRAINT_TYPE,b.CONSTRAINT_NAME
							from INFORMATION_SCHEMA.KEY_COLUMN_USAGE as a,INFORMATION_SCHEMA.TABLE_CONSTRAINTS as b
							where a.TABLE_NAME='".$tableName."' AND a.TABLE_SCHEMA = DATABASE()
							AND b.TABLE_NAME='".$tableName."' AND b.CONSTRAINT_SCHEMA = DATABASE()
							AND b.CONSTRAINT_NAME=a.CONSTRAINT_NAME
							)as b on (a.COLUMN_NAME = b.COLUMN_NAME)
						where a.TABLE_NAME='".$tableName."' AND a.TABLE_SCHEMA =DATABASE()";
	
		$r= mysql_query($sql,$this->getLink());
		if (false == $r) {
			throw new Exception('model: cant describe, missing rights?');
		}
		$noResult = true;
		while ($row= mysql_fetch_assoc($r)) {
			$noResult = false;
			$data= array ();
			$data['default']= empty ($row['COLUMN_DEFAULT']) ? false : $row['COLUMN_DEFAULT'];
			$data['null']= 'NO' != $row['IS_NULLABLE'];
			$data['length']= 0;
	
			if('UNIQUE' == $row['CONSTRAINT_TYPE'] ){
				if(!isset($uniqueKeys[$row['CONSTRAINT_NAME']])){
					$uniqueKeys[$row['CONSTRAINT_NAME']] = array();
				}
				$uniqueKeys[$row['CONSTRAINT_NAME']][] = $row['COLUMN_NAME'];
			}
	
			if ('PRIMARY KEY' == $row['CONSTRAINT_TYPE']) {
				$primaryKey[] = $row['COLUMN_NAME'];
			}
			switch ($row['DATA_TYPE']) {
				case 'bit' :
					$data['type']= 'bool';
					$data['length']= $row['NUMERIC_PRECISION'];
					break;
				case 'bigint':
				case 'int':
				case 'smallint':
				case 'tinyint':
					$data['length']= $row['NUMERIC_PRECISION'];
					$data['type']= 'int';
					break;
				case 'char':
				case 'varchar':
					$data['length']= $row['CHARACTER_MAXIMUM_LENGTH'];
					$data['type']= 'string';
					break;
				case 'text':
					$data['type']= 'text';
					break;
				case 'float':
				case 'double':
				case 'real':
					$data['type']= 'float';
					break;
				case 'date':
				case 'datetime':
				case 'time':
				case 'timestamp':
					$data['type']= 'date';
			}
			$cols[$row['COLUMN_NAME']]= $data;
		}
	
		if ($noResult === true) {
			throw new Exception('table does not exists in selected Database');
		}
		$unique = array();
		foreach ($uniqueKeys as $uniqueKey){
				$unique[]= $uniqueKey;
		}
		$desc= array (
			'table' => str_replace(array (
				$this->quoteLeft,
				$this->quoteRight
			), '', $tableName),
			'primary' => $primaryKey,
			'unique' => $unique,
			'cols' => $cols
		);
		return $desc;
	}
	*/
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
