<?php


/**
 * Contains a wrapper-class for mssql, so models can access mssql
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
 * @author mnt@codeninja.de
 * @author marcel.boessendoerfer@gameforge.de
 */

class dbo_mssql { //implements dbo_interface {

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
	 * an array that holds all queries and some relevant information about them if DEBUG
	 * @var array
	 */
	private $queries = array ();

	/**
	 * constants used to quote table and field names
	 *
	 */
	private $quoteLeft = '[';
	private $quoteRight = ']';

	/**
	 * connect to the database
	 */
	function connect() {
		$this->link = mssql_connect($this->dbconfig['host'], $this->dbconfig['login'], $this->dbconfig['password'], true);
		if (!$this->link) {
			throw new DatabaseConnectException("Could not connect to server " . $this->dbconfig['host']);
		}
		if (!empty ($this->dbconfig['database'])) {
			$db = $this->dbconfig['database'];
			if ($db[0] != '[') {
				$db = '['.$db.']';
			}
			if (!mssql_select_db($db, $this->link)) {
				throw new DatabaseConnectException("Could not select Database " . $this->dbconfig['database']);
			}
		}

		if (!empty ($this->dbconfig['encoding'])) {
			$this->setEncoding($this->dbconfig['encoding']);
		}

		//freetds hack: freetds does not offer this function :(
		if (!function_exists("mssql_next_result")) {
			function mssql_next_result($res = null) {
				return false;
			}
		}
	}

	function isConnected() {
		return (bool) $this->link;
	}

	/**
	 * return the current link to the database, connect first if needed
	 */
	public function getLink() {
		if (empty($this->link)){
			return false;
		}
		return $this->link;
	}

	/**
		 * inject db link into dbo
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
		$this->result = mssql_query($sql, $this->link);

		if (false === $this->result) {
			$msg = mssql_get_last_message();
			//TODO another way would be to check @@ERROR for errors 2601/2627 which is ALSO language dependend *facepalm*  
			if (stripos($msg,'duplicate') !== false) {
				DatabaseDuplicateException($msg);				
			} else {
				writeLog($msg . ': ' . $sql, 1);
				throw new DatabaseErrorException($msg,$sql);
			}
		}
		if (DEBUG > 0) {
			$this->queries[] = array (
				kataFunc::getLineInfo(),
				trim($sql),
				'',
				mssql_get_last_message(),
				 (microtime(true) - $start) . 'sec'
			);
		}
	}

	/**
	 * unused right now, later possibly used by model to set right encoding
	 */
	function setEncoding($enc) {
		//TODO
	}

	/**
	 * return numbers of rows affected by last query
	 * @return int
	 */
	private function lastAffected() {
		if ($this->link) {
			if (function_exists('mssql_rows_affected')) {
				return mssql_rows_affected($this->link);
			} else {
				$result = mssql_query("select @@rowcount as rows", $this->link);
				$rows = mssql_fetch_assoc($result);
				return $rows['rows'];
			}
		}
		$null = null;
		return $null;
	}

	/**
	 * return id of primary key of last insert
	 * @return int
	 */
	private function lastInsertId() {
		$this->execute("select SCOPE_IDENTITY() AS id");
		if ($this->result) {
			$res = mssql_fetch_assoc($this->result);
			if ($res) {
				$id = $res['id'];
				if ($this->result) {
					mssql_free_result($this->result);
				}
				return $id;
			}
		}
		return null;
	}

	/**
	 * return the result of the last query.
	 * @param mixed $idname if $idname is false keys are simply incrementing from 0, if $idname is string the key is the value of the column specified in the string
	 */
	private function & lastResult($idnames = false) {
		do {
			$result = array ();
			if (@mssql_num_rows($this->result) > 0) {
				if ($idnames === false) {
					while ($row = mssql_fetch_assoc($this->result)) {
						$result[] = $row;
					}
				} else {
					while ($row = mssql_fetch_assoc($this->result)) {
						$current = & $result;
						foreach ($idnames as $idname) {
							if (!array_key_exists($idname,$row)) {
								throw new InvalidArgumentException('Cant order result by a field thats not in the resultset (forgot to select it?)');
							}
							if ($row[$idname] === null) {
								$row[$idname] = 'null';
							}
							$current = & $current[$row[$idname]];
						}
						$current = $row;
					} //while
				} //idnames!=false
			} //num_rows>0
		} //do
		while (mssql_next_result($this->result));
		return $result;
	}
	/**
	 * REPLACE works like INSERT,
	 * except that if an old row in the table has the same value as a new row for a PRIMARY KEY or a UNIQUE index,
	 * the old row is deleted before the new row is inserted
	 *
	 * @param string $tableName replace from this table
	 * @param array $fields name=>value pairs of new values
	 * @param string $pairs enquoted names to escaped pairs z.B.[name]='value'
	 * @return int modified rows.
	 */
	function replace($tableName, $fields, $pairs) {
		throw new Exception('Not easily supportable on MSSQL. Direct your thanks for this to Microsoft.');
	}

	/**
	 * execute query and return useful data depending on query-type
	 *
	 * @param string $s sql-statement
	 * @param string $idname which field-value to use as the key of the returned array (false=dont care)
	 * @return array
	 */
	function & query($s, $idnames = false, $fields = false) {
		$result = null;
		switch ($this->getSqlCommand($s)) {
			case 'update' :
			case 'delete' :
			case 'alter':
				$this->execute($s);
				$result = $this->lastAffected();
				break;
			case 'insert' :
				$this->execute($s);
				$result = $this->lastInsertId();
				break;
			case 'select' :
			case 'exec':
			case 'execute':
			case 'show':
			case '/*page*/if' :
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
		return str_replace("'", "''", $sql); //seems odd but in mssql a single ' can be escaped by another
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
		if ((bool) $this->link) {
			if (is_resource($this->link) && (get_resource_type($this->link) == 'mssql link')) {
				mssql_close($this->link);
			}
			$this->link = null;
		}
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
		//TODO UNION,EXCEPT,INTERSECT... not implemented, mostly not supported anyway
		$command = $this->getSqlCommand($sql);
		$validTopComands = array (
			'select' => 1,
			'insert' => 1,
			'update' => 1,
			'delete' => 1,
			'merge' => 1
		);
		//set TOP after first Command
		if (isset($validTopComands[$command])) {
			$first = mb_strpos(strtolower($sql), $command);
			$firstPart = mb_substr($sql, 0, $first);
			$secondPart = mb_substr($sql, ($first +strlen($command)));
			return $firstPart . $command . " TOP(" . $perPage . ")" . $secondPart;
		}
		return $sql;
	}

	/**
	 * build a sql-string that returns paged data
	 * every computed output has to be named !!! so 'max(x)' has to be 'max(x) as maxX' or something like that...
	 *
	 * some warnings/comments from dietmar riess:
	 * IF There is no IDENTITY FIELD we can numbering Rows with temp Table
	 * IF There is an IDENTITY FIELD we have to execute the much slower EXCEPT Query
	 * Also we know there is an IDENTITY FIELD we can't use it, because we do not! know which column it is !
	 *
	 * @see getPageQuery Interface
	 * @param boolean $orderd true is depreacated fliping TOPS
	 * @return string finished query
	 */
	function getPageQuery($sql, $page, $perPage) {
		$command = $this->getSqlCommand($sql);
		if ($command != "select") {
			throw new InvalidArgumentException('paging is not possible for given query');
			return $sql;
		}
		$fastQuery = $this->getFirstRowQuery($sql, 1);
		$fastInsertQuery = 'IF OBJECT_ID(\'tempdb..#temp\') IS NOT NULL DROP TABLE #temp;SELECT * INTO #temp FROM (' . $fastQuery . ') as a';
		$this->execute($fastInsertQuery);
		$ID = $this->lastInsertId();
		if ($ID === null) {
			$fastQuery = $this->getFirstRowQuery($sql, $page * $perPage);
			$tmptable = '/*PAGE*/IF OBJECT_ID(\'tempdb..#table\') IS NOT NULL DROP TABLE #table;SELECT IDENTITY(int,1,1) as tempRowNumID,* INTO #table FROM (' . $fastQuery . ') as a;';
			$tmptableAndQuery = $tmptable . 'SELECT * FROM #table where tempRowNumID between ' . (($page -1) * $perPage +1) . ' AND ' . (($page) * $perPage);
			return $tmptableAndQuery;
		} else {
			$topPages = $this->getFirstRowQuery($sql, $page * $perPage);
			$lastPages = $this->getFirstRowQuery($sql, ($page -1) * $perPage);
			$Query = 'SELECT * FROM (' . $topPages . ') as a EXCEPT SELECT * FROM (' . $lastPages . ') as a';
			return $Query;
		}
	}
	/**
	 * return the sql needed to convert a unix timestamp to datetime
	 * @param integer $t unixtime
	 * @return string
	 */
	function makeDateTime($t) {
		//may lie to you: mssql does not calculate summertime
		return "CONVERT(char(20),dateadd(ss," . $t . "+DATEDIFF(ss, GetUtcDate(), GetDate()),'1970-01-01 00:00:00'),120)";
	}

	/**
	 * try to reduce the fields of given table to the basic types bool, unixdate, int, string, float, date, enum
	 *
	 * <code>example:
	 *
	Array
	 * (
	 *     [table] => test
	 *     [primary] => a
	 *     [cols] => Array
	 *         (
	 *             [a] => Array
	 *                 (
	 *                     [default] => CURRENT_TIMESTAMP
	 *                     [null] =>
	 *                     [length] => 0
	 *                     [type] => date
	 *                 )
	 *
	 *             [g] => Array
	 *                 (
	 *                     [default] =>
	 *                     [null] =>
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
		$tableName = $this->dequote($tableName);
		/*possibly incomplete*/
		$sql = "Select a.COLUMN_NAME,a.[IS_NULLABLE],a.[COLUMN_DEFAULT],a.[DATA_TYPE],a.[CHARACTER_MAXIMUM_LENGTH],a.[NUMERIC_PRECISION],b.[CONSTRAINT_NAME],COLUMNPROPERTY(OBJECT_ID('" . $tableName . "'),a.COLUMN_NAME, 'IsIdentity') as [identity]
										From INFORMATION_SCHEMA.COLUMNS a left join INFORMATION_SCHEMA.CONSTRAINT_COLUMN_USAGE as b on (a.COLUMN_NAME = b.COLUMN_NAME AND a.TABLE_NAME=b.TABLE_NAME)
										where a.TABLE_NAME='" . $tableName . "'";
		/*tested this do it ,cause CONSTRAINT_NAME can be defined by user!(fehlender Beweis für:"unkorrelierte Subqueries werden nur einmal ausgeführt")
		$sql = "Select a.COLUMN_NAME,[IS_NULLABLE],[COLUMN_DEFAULT],[DATA_TYPE],[CHARACTER_MAXIMUM_LENGTH],[NUMERIC_PRECISION],[CONSTRAINT_NAME],[CONSTRAINT_TYPE],a.COLUMN_NAME, 'IsIdentity') as [identity]
						from INFORMATION_SCHEMA.COLUMNS a left join
							(SELECT a.COLUMN_NAME,b.CONSTRAINT_TYPE,b.CONSTRAINT_NAME
							from INFORMATION_SCHEMA.CONSTRAINT_COLUMN_USAGE as a,INFORMATION_SCHEMA.TABLE_CONSTRAINTS as b
							where a.TABLE_NAME='".$tableName."' AND a.TABLE_CATALOG = db_name()
							AND b.TABLE_NAME='".$tableName."' AND b.TABLE_CATALOG = db_name()
							AND b.CONSTRAINT_NAME=a.CONSTRAINT_NAME
							)as b on (a.COLUMN_NAME = b.COLUMN_NAME)
						where a.TABLE_NAME='".$tableName."' AND a.TABLE_CATALOG =db_name()";
		*/
		$r = mssql_query($sql, $this->getLink());
		if (false === $r) {
			throw new Exception('model: cant describe, missing rights?');
		}
		$noResult = true;
		while ($row = mssql_fetch_assoc($r)) {
			$noResult = false;
			$data = array ();
			$data['default'] = empty ($row['COLUMN_DEFAULT']) ? false : $row['COLUMN_DEFAULT'];
			$data['null'] = 'NO' != $row['IS_NULLABLE'];
			$data['length'] = 0;
			if ($row['identity'] == 1) {
				$identity = $row['COLUMN_NAME'];
			}
			/*deprecated
			if('UNIQUE' == $row['CONSTRAINT_TYPE'] ){
				if(!isset($uniqueKeys[$row['CONSTRAINT_NAME']])){
					$uniqueKeys[$row['CONSTRAINT_NAME']] = array();
				}
				$uniqueKeys[$row['CONSTRAINT_NAME']][] = $row['COLUMN_NAME'];
			}
			if ('PRIMARY KEY' == $row['CONSTRAINT_TYPE']) {
				$primaryKey[] = $row['COLUMN_NAME'];
			}
			*/
			//keys
			$data['key'] = null;
			if (isset ($row['CONSTRAINT_NAME'])) {
				$key = substr($row['CONSTRAINT_NAME'], 0, 2);
				if ($key == 'PK') {
					$primaryKey[] = $row['COLUMN_NAME'];
					$data['key'] = 'PRI';
				} else
					if ($key == 'UQ') {
						$data['key'] = 'UNI';
					}
			}
			//types
			switch ($row['DATA_TYPE']) {
				case 'bit' :
					$data['type'] = 'bool';
					$data['length'] = $row['NUMERIC_PRECISION'];
					break;
				case 'bigint' :
				case 'int' :
				case 'smallint' :
				case 'tinyint' :
					$data['length'] = $row['NUMERIC_PRECISION'];
					$data['type'] = 'int';
					break;
				case 'char' :
				case 'varchar' :
					$data['length'] = $row['CHARACTER_MAXIMUM_LENGTH'];
					$data['type'] = 'string';
					break;
				case 'text' :
					$data['type'] = 'text';
					break;
				case 'float' :
				case 'real' :
					$data['length'] = $row['NUMERIC_PRECISION'];
					$data['type'] = 'float';
					break;
				case 'date' :
				case 'datetime' :
				case 'datetime2' :
				case 'smalldatetime' :
				case 'datetimeoffset' :
				case 'time' :
					$data['type'] = 'date';
			}
			$cols[$row['COLUMN_NAME']] = $data;
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
	 * @param string $what spezifies what to get ... null=complete config array
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
			'=' => 1,
			'>' => 1,
			'<' => 1,
			'>=' => 1,
			'<=' => 1,
			'<>' => 1,
			'!=' => 1,
			'!<' => 1,
			'>!' => 1,
			'is null' => 1,
			'is not null' => 1,
			'between' => 1,
			'in' => 1,
			'not in' => 1,
			'like' => 1,
			'not like' => 1
		);
		return isset($ops[$operator]);
	}
}
