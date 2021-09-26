<?php


/**
 * The Base Model. used to access the database (via dbo_ objects)
 *
 * Kata - Lightweight MVC Framework <http://www.codeninja.de/>
 * Copyright 2007-2015 mnt@codeninja.de
 *
 * Licensed under The GPL License
 * Redistributions of files must retain the above copyright notice.
 * @package kata_model
 */

/**
 * validation string define to check if string is not empty
 * @deprecated 31.04.2009
 */
define('VALID_NOT_EMPTY', 'VALID_NOT_EMPTY');
/**
 * @deprecated 31.04.2009
 * validation string define to check if string is numeric
 */
define('VALID_NUMBER', 'VALID_NUMBER');

/**
 * @deprecated 31.04.2009
 * validation string define to check if string is an email-address
 */
define('VALID_EMAIL', 'VALID_EMAIL');

/**
 * @deprecated 31.04.2009
 * validation string define to check if string is a numeric year
 */
define('VALID_YEAR', 'VALID_YEAR');

/**
 * The base model-class that all models derive from
 * @package kata_model
 * @todo review replace() (emulation in mssql vs. remove), query only supports basic Commands (but easily can support all)
 */
class Model {
	/**
	 * which connection to use of the ones defines inside config/database.php
	 * 
	 * @var string
	 */
	public $connection = 'default';

	/**
	 * whether to use a specific table for this model. false if not specific, otherwise the tablename
	 * 
	 * @var string
	 */
	public $useTable = false;

	/**
	 * which fieldname to use for primary key. is 'id' by default, override it in your
	 * model wo 'table_id' or 'tableId' as you like.
	 * 
	 * @var string 
	 */
	public $useIndex = 'id';

	/**
	 * containts the appropriate class used to access the database
	 * 
	 * @var object
	 */
	protected $dboClass = null;

	/**
	 * convenience method for writeLog
	 * 
	 * @param string $what what to log
	 * @param string $where errorlevel (0-2)
	 */
	function log($what, $where) {
		writeLog($what, $where);
	}

	/**
	 * lazy setup dbo the first time its used
	 * 
	 * @return object intialized dbo-class
	 */
	function dbo() {
		if (null === $this->dboClass) {
			$this->setupDbo($this->connection);
		}
		return $this->dboClass;
	}

	/**
	 * load dbo-class, give dbconfig to class
	 * 
	 * @param string $connName name of the connection to use
	 */
	protected function setupDbo($connName) {
		require_once ROOT . 'config' . DS . 'database.php';
		if (!class_exists('DATABASE_CONFIG')) {
			throw new Exception('Incorrect config/database.php');
		}

		$dbvars = get_class_vars('DATABASE_CONFIG');
		if (empty ($dbvars[$connName])) {
			throw new DatabaseConnectException("Cant find configdata for database-connection '$connName'");
		}
		$dboname = 'dbo_' . $dbvars[$connName]['driver'];

		if (!class_exists($dboname)) {
			require_once LIB.$dboname.'.php';
		}

		//for evil people modifying the connection-parameters
		$connHandle = $connName.implode('|',$dbvars[$connName]);
		$this->dboClass = classRegistry :: getObject($dboname, $connHandle);
		$this->dboClass->setConfig($dbvars[$connName]);
	}

	/**
	 * allowes you to switch the current connection dynamically.
	 *
	 * @param string $connName name of the new connection to use
	 */
	function changeConnection($connName) {
		$this->connection = $connName;
		$this->setupDbo($connName);
	}

	/**
	 * return currently used connection name (see database.php)
	 *
	 * 	 * @return string
	 */
	function getConnectionName() {
		return $this->connection;
	}

	/**
	 * getter for the config options of the current model
	 * 
	 * @param string $what which part of the config you want returned. if null the whole config-array is returned
	 * @var string
	 */
	public function getConfig($what = null) {
		return $this->dbo()->getConfig($what);
	}

	/**
	 * getter for the database link of the current model. whats returned here depends greatly on the dbo-class
	 * 
	 * @var resource
	 */
	public function getLink() {
		return $this->dbo()->getLink();
	}

	/**
	 * utility function to generate correct tablename
	 *
	 * @param string $n tablename to use. if null uses $this->useTable. if that is also null uses modelname.
	 * @param bool $withPrefix if true adds prefix and adds the correct quote-signs to the name
	 * @return string
	 */
	public function getTableName($n = null, $withPrefix = true, $quoted = true) {
		$name = get_class($this);

		if ($withPrefix) {
			if (null !== $n) {
				return ($quoted ? $this->quoteName($this->getPrefix() . $n) : $this->getPrefix() . $n);
			}
			if ($this->useTable) {
				return ($quoted ? $this->quoteName($this->getPrefix() . $this->useTable) : $this->getPrefix() . $this->useTable);
			}
			return ($quoted ? $this->quoteName($this->getPrefix() . strtolower($name)) : $this->getPrefix() . strtolower($name));
		}

		if (null !== $n) {
			return $n;
		}
		if ($this->useTable) {
			return $this->useTable;
		}
		return strtolower($name);
	}

	/**
	 * return the prefix configured for this connection
	 *
	 * @return string
	 */
	public function getPrefix() {
		return $this->dbo()->getConfig('prefix');
	}

	/**
	 * execute an actual query on the database
	 * 
	 * @param string $s the sql to execute
	 * @param string $idnames can be used to have the keys of the returned array equal the value ob the column given here (instead of just heaving 0..x as keys)
	 * @return mixed returns array with results OR integer with insertid OR integer updated rows OR null
	 */
	function & query($s = null, $idnames = false) {
		if (empty ($s)) {
			throw new InvalidArgumentException('no query is specified');
		}
		return $this->dbo()->query($s, $idnames);
	}

	/**
	 * Do a query that is cached via the cacheUtility. caching is done 'dumb', so altering the database wont invalidate the cache
	 *
	 * A word of warning: If you dont supply an $idname, queries on different lines will result in different cachefiles
	 *
	 * @param string $s sql-string
	 * @param string $idname if set the key of the array is set to the value of this field of the result-array. So the result is not numbered from 0..x but for example the value of the primary key
	 * @param string $cacheid the id used to store this query in the cache. if ommited we try to build a suitable key
	 * @param int $ttl time to live in seconds (0=infinite)
	 */
	function & cachedQuery($s, $idname = false, $cacheid = false, $ttl = 0) {
		if (!$cacheid) {
			$bt = debug_backtrace();
			$cacheid = $bt[1]['class'] . '.' . $bt[1]['function'] . '.' . $bt[1]['line'];

			if (isset ($bt[1]['args']) && is_array($bt[1]['args'])) {
				foreach ($bt[1]['args'] as $arg) {
					if (null === $arg) {
						$cacheid .= '-null';
					}
					elseif (false === $arg) {
						$cacheid .= '-false';
					} else {
						$cacheid .= '-' . $arg;
					}
				}
			}
		}

		$cacheUtil = getUtil('Cache');

		$res = $cacheUtil->read($cacheid);
		if (false !== $res) {
			return $res;
		}

		$res = $this->query($s, $idname);
		$cacheUtil->write($cacheid, $res, $ttl);
		return $res;
	}

	/**
	 * escape possibly harmful strings so you can safely append them to an sql-string
	 * 
	 * @param string $s string to escape
	 * @return string escaped string
	 */
	function escape($s) {
		return $this->dbo()->escape($s);
	}

	/**
	 * enclose string in single-quotes AND escape it
	 * 
	 * @param string $s string to escape
	 * @return string quoted AND escaped string
	 */
	function quote($s) {
		return '\'' . $this->escape($s) . '\'';
	}

	/**
	 * enclose table- or fildname in whatever the database needs (depends on used dbo)
	 * 
	 * @param string $s field or tablename
	 * @return string escaped name 
	 */
	function quoteName($s) {
		return $this->dbo()->quoteName($s);
	}

	/**
	 * turn a unix timestamp into datetime-suitable SQL-function like FROM_UNIXTIME(timestamp) (depends on used dbo)
	 * 
	 * @param integer $t unix timestamp
	 * @return string sql-statement
	 */
	function makeDateTime($t) {
		return $this->dbo()->makeDateTime($t);
	}

	/**
	 * turn the given array into "name=value,name=value" pairs suitable for INSERT or UPDATE-sqls. strings are automatically quoted+escaped, fieldnames also
	 * 
	 * @param array $params the data
	 * @return string �foo�='bar',�baz�='ding'
	 */
	function pairs($params) {
		if (empty ($params)) {
			throw new InvalidArgumentException('no pairs are specified');
		}
		if (!is_array($params)) {
			throw new InvalidArgumentException('pairs: params must be an array');
		}
		$out = '';
		foreach ($params as $v => $k) {
			if (is_null($k)) {
				$out .= $this->quoteName($v) . "=NULL,";
			} else {
				$out .= $this->quoteName($v) . "=" . $this->quote($k) . ",";
			}
		}
		return substr($out, 0, strlen($out) - 1);
	}

	/**
	 * construct a suitable where-clause for a query from an array of conditions
	 * 
	 * @param mixed $id
	 * @param string $tableName needed to generate a primary key name
	 * @return string full 'WHERE x=' string
	 */
	function getWhereString($id, $tableName, $allowKey = false) {
		if (empty ($id)) {
			return '';
		}
		if (($tableName!==null) && !is_string($tableName)) {
			throw new InvalidArgumentException('tableName needs to be null or string');
		}

		if ($allowKey) {
			if (!is_array($id) && (is_numeric($id) || is_string($id))) {
				$id = array (
					$this->useIndex => $id
				);
			}
		}

		return ' WHERE ' . $this->getWhereStringHelper($id, $tableName);
	}

	/**
	 * do the actual work for getWhereString(). analyse strings and branch for arrays
	 * @param mixed $id
	 * @param string $tableName needed to generate a primary key name
	 * @return string 'x=y AND x=z' string without 'WHERE'
	 */
	private function getWhereStringHelper(& $id, $tableName) {
		if (!is_array($id)) {
			throw new InvalidArgumentException('condition needs to have array() as value');
		}

		$orMode = false;
		foreach ($id as $value) {
			if (is_string($value) && (strtolower($value) === 'or')) {
				$orMode = true;
				break;
			}
		}

		reset($id);
		$num = 0;
		$s = '';
		foreach ($id as $name => $value) {
			$name = trim($name);
			$num++;
			//dont count or || and
			if (!is_array($value) && (('or' == strtolower($value)) || ('and' == strtolower($value)))) {
				$num--;
				continue;
			}
			//place or/and between two conditions
			if ($num > 1) {
				$s .= ($orMode ? ' OR ' : ' AND ');
			}
			//throw Exception on key is numeric. Since we continued on value or || and, numeric key is only valid for new sub-condition=array
			if (is_numeric($name) && !is_array($value)) {
				throw new InvalidArgumentException('condition-array needs to have strings as keys');
			}
			//fieldName
			$fieldName = $this->quoteName($name);
			//operator
			$tempOperator = strpos($name, ' ');
			$operator = '=';

			if ($tempOperator !== false) {
				$fieldName = $this->quoteName(trim(substr($name, 0, $tempOperator)));
				$operator = strtolower(trim(substr($name, $tempOperator)));
			}
			//fieldValue depends on operator
			$fieldValue = '';
			if (!is_array($value)) {
				if ($value === false) {
					$value = '0';
				}
				//On fieldValue = null we use nullsensitive operators
				if (is_null($value)) {
					$fieldValue = 'null';
					if ($operator == '=' || $operator == 'is') {
						$operator = 'is null';
					} else
						if ($operator == '!=' || $operator == '<>' || $operator == 'is not') {
							$operator = 'is not null';
						}
				} else {
					$fieldValue = $this->quote($value);
				}
			}
			if ($operator == 'is null' || $operator == 'is not null') {
				$fieldValue = '';
			}
			if ($operator == 'in' || $operator == 'not in') {
				if (!is_array($value)) {
					throw new InvalidArgumentException($operator . ' operator needs to have array() as value');
				}
				// $value may be empty
				if (empty($value)) {
					if ($orMode) {
						$num--;
						continue;
					} else {
						return '0';
					}
				}

				$fieldValue = '( ';
				foreach ($value as $val) {
					if ($fieldValue != '( ') {
						$fieldValue .= ' , ';
					}
					$fieldValue .= $this->quote($val);
				}
				$fieldValue .= ' )';
			}
			if ($operator == 'between') {
				if (!is_array($value)) {
					throw new InvalidArgumentException($operator . 'operator needs to have array() as value');
				}
				$fieldValue = '';
				foreach ($value as $val) {
					if ($fieldValue != '') {
						$fieldValue .= ' and ';
					}
					$fieldValue .= $this->quote($val);
				}
			}
			//seperate subcondition
			if ($operator == '=' && $fieldValue == '' && is_array($value)) {
				$s .= ' ( ' . $this->getWhereStringHelper($value, $tableName) . ' ) ';
				continue;
			}
			if (!$this->dbo()->isValidOperator($operator)) {
				throw new InvalidArgumentException('operator:\'' . $operator . '\' is not supported');
			}

			$s .= $fieldName . ' ' . $operator . ' ' . $fieldValue;
		}
		return $s;
	}

	/**
	 * select rows using various methods (see $method for full list)
	 * 
	 * $method can be:
	 * 'all': return all matching rows as an array of results
	 * 'list': return matching rows as a multidimensional array that use keynames from your supplied 'listby' fields. similar to $idfields of query()  
	 * 'count': return number of rows matching
	 * 'first': return first matching row
	 * 
	 * $conditions is used to construct a suitable WHERE-clause. fieldnames are default AND connected, just add an 'or' somewhere to change that. 
	 * Example: 'conditions'=>array('field1'=>5',array('name LIKE'=>'%eter','or','name LIKE'=>'%eta'))
	 *
	 * $order: array of fieldnames used to construct ORDER BY clause
	 * 
	 * $group: array of fieldnames used to construct GROUP BY clause
	 * 
	 * $listby: see above
	 * 
	 * $limit: how many rows to return (per page)
	 * $page: which page to return (if $limit is set), first page is 1
	 *
	 * <code>
	 * $rows = $this->find('all',array(
	 * 	'conditions' => array( // WHERE conditions to use. default all elements are AND, just add 'or' to the condition-array to change this
	 * 		'field' => $thisValue,
	 * 		'or',
	 * 		'field2'=>$value2,
	 * 		'field3'=>$value3,
	 * 		'field4'=>$value4
	 * 	),
	 * 	'fields' => array( //array of field names that we should return. first field name is used as array-key if you use method 'list' and listby is unset
	 * 		'field1',
	 * 		'field2'
	 * 	),
	 *  'order' => array( //string or array defining order. you can add DESC or ASC
	 * 		'created',
	 * 		'field3 DESC'
	 * 	),
	 *  'group' => array( //fields to GROUP BY
	 * 		'field'
	 *	),
	 *  'listby' => array( //only if find('list'): fields to arrange result-array by
	 *		'field1','field2'
	 *  ),
	 *  'limit' => 50, //int, how many rows per page
	 *  'page' => 1, //int, which page, starting at 1
	 * ),'mytable');
	 * </code>
	 *
	 * @param string $method can be 'all','list','count','first','neighbors'
	 * @param array $params see example
	 * @param mixed $tableName string or null to use modelname
	 */
	function find($method = '', $params = array (), $tableName = null) {
		$orderBy = '';
		if (!empty ($params['order'])) {
			if (!is_array($params['order'])) {
				throw new InvalidArgumentException('order must be an array');
			}
			$orderBy = ' ORDER BY ' . implode(',', $params['order']);
		}
		$groupBy = '';
		if (!empty ($params['group'])) {
			if (!is_array($params['group'])) {
				throw new InvalidArgumentException('group must be an array');
			}
			$groupBy = ' GROUP BY ' . implode(',', $params['group']);
		}
		$fields = '*';
		if (!empty($params['fields']) && is_array($params['fields'])) {
			$fields = implode(',', $params['fields']);
		}
		$where = '';
		if (isset ($params['conditions'])) {
			$where = $this->getWhereString($params['conditions'], $tableName);
		}
		$indexFields = false;
		switch ($method) {
			case 'list' :
				if (empty ($params['listby'])) {
					if (empty ($params['fields'])) {
						$indexFields = array (
							$this->useIndex
						);
					} else {
						$indexFields = $params['fields'];
					}
				} else {
					if (!is_array($params['listby'])) {
						throw new InvalidArgumentException('listby must be an array');
					}
					if (strpos($fields, '*') === false) {
						foreach ($params['listby'] as $key => $value) {
							if (!in_array($value, $params['fields'])) {
								$fields = $fields . ',' . $value;
							}
						}
					}
					$indexFields = $params['listby'];
				}
			case 'all' :
				$sql = 'SELECT ' . $fields . ' FROM ' . $this->getTableName($tableName) . $where . $groupBy . $orderBy;
				if (!empty ($params['page']) && is_numeric($params['page'])) {
					$page = (int) $params['page'];
					$perPage = 50;
					if (!empty ($params['limit']) && is_numeric($params['limit'])) {
						$perPage = $params['limit'];
					}
					$sql = $this->dbo()->getPageQuery($sql, $page, $perPage);
				}
				return $this->query($sql, $indexFields);
				break;
				
			case 'count' :
				$sql = 'SELECT count(*) AS c FROM ' . $this->getTableName($tableName) . $where . $groupBy;
				$return = $this->query($sql);
				$count = isset ($return[0]['c']) ? $return[0]['c'] : 0;
				if (!empty ($params['page']) && is_numeric($params['page'])) {
					$page = (int) $params['page'];
					$perPage = 50;
					if (!empty ($params['limit']) && is_numeric($params['limit'])) {
						$perPage = $params['limit'];
					}
					$count = min($perPage, max(0, $count - ($page * $perPage)));
				}
				return $count;
				break;

			case 'first' :
				$sql = 'SELECT ' . $fields . ' FROM ' . $this->getTableName($tableName) . $where . $groupBy . $orderBy;
				$sql = $this->dbo()->getFirstRowQuery($sql, 1);
				return $this->query($sql);
				break;

			case 'neighbors' :
				die('not implemented yet');
				break;

			default :
				throw new InvalidArgumentException('model: find() doesnt know method ' . $method);
				break;
		}
	}

	/**
	 * read data from the database.
	 * 
	 * <code>
	 * $rows = $this->read(array('foobarId'=>5));
	 * $rows = $this->read(array(
	 * 	'foobarId'=>6,
	 *  'and',
	 *  'someId'=>2
	 * ));
	 * </code>
	 *
	 * @param mixed $id array of fieldnames used to construct WHERE clause
	 * @param array $fields return these colums (if null: all fields)
	 * @param string $tableName read from this table (if ommitted: use tablename of this model, including prefix)
	 * @param mixed $fieldName string or array to use as key for the returned result, see query()
	 */
	function read($id = null, $fields = null, $tableName = null, $fieldName = false) {
		if (is_array($fields) && (count($fields)==0)) {
			$fields = null;
		}
		return $this->query('SELECT ' .
		 ($fields === null ? '*' : implode(',', $fields)) .
		' FROM ' . $this->getTableName($tableName) .
		$this->getWhereString($id, $tableName, true), $fieldName);
	}

	/**
	 * mass insert function
	 * @param array $fields array of row-array to insert
	 * @param bool $ignore if we should do an INSERT INGORE
	 * @param mixed $tableName name of table to use or null for model default
	 * @return int number of successfully inserted rows
	 */
	function bulkcreate($fields = null, $ignore=false, $tableName = null) {
		if (!is_array($fields)) {
			throw new InvalidArgumentException('bulkinsert expects array');
		}
		$fieldNames = reset($fields);
		if (!is_array($fieldNames)) {
			throw new InvalidArgumentException('bulkinsert expects array of key-value-array');
		}
		$quotedFieldNames = array();
		foreach ($fieldNames as $fieldName=>$value) {
			if (is_numeric($fieldName)) {
				throw new InvalidArgumentException("rowname '$fieldName' is numeric, seems very odd");
			}
			$quotedFieldNames[] = $this->quoteName($fieldName);
		}

		$cntInner=0;
		$dntTotal=count($fields);
		$success=false;
		$sql = '';
		foreach ($fields as $rows) {
			foreach ($rows as &$row) {
				$row = $this->quote($row);
			}
			unset($row);
			$sql.='('.implode(',',$rows).'),';
			
			if (($cntInner++==100) || (--$dntTotal<=1)) {
				$result = $this->query('INSERT '.($ignore?' IGNORE ':'').'INTO '.
					$this->getTableName($tableName).' ('.
					implode($quotedFieldNames,',').') VALUES '.substr($sql,0,-1));
				$success = $success | (bool)$result;
				$cntInner=0;
				$sql='';
			}
		}
		
		return $success;
	}

	/**
	 * insert a record into the database.
	 *
	 * <code>
	 * $this->create(array('fooId'=>1,'int1'=>10,'int2'=>20));
	 * </code>
	 *
	 * @param array $fields name=>value pairs to be inserted into the table
	 * @param string $tableName insert into this table (if ommitted: use tablename of this model, including prefix)
	 */
	function create($fields = null, $tableName = null) {
		$fieldstr = '';
		$valuestr = '';
		if (empty ($fields)) {
			throw new InvalidArgumentException('insert without fields, seems odd');
		}
		foreach ($fields as $fieldname => $value) {
			$fieldstr .= $this->quoteName($fieldname) . ',';
			if (null === $value) {
				$valuestr .= 'null,';
			} else {
				$valuestr .= $this->quote($value) . ',';
			}
		}

		return $this->query('INSERT INTO ' . $this->getTableName($tableName) . ' (' . substr($fieldstr, 0, -1) . ') VALUES (' . substr($valuestr, 0, -1) . ')');
	}

	/**
	 * delete the row whose id is matching
	 *
	 * <code>
	 * $this->delete(array('rowId'=>10));
	 * $this->delete(array(
	 * 	'rowId'=>20,
	 * 	'and',
	 *  'parentId'=>10
	 * ));
	 * </code>
	 *
	 * @param mixed $id primary key of row to delete
	 * @param string $tableName delete from this table (if ommitted: use tablename of this model, including prefix)
	 */
	function delete($id = null, $tableName = null) {
		if (is_bool($id)) {
			throw new InvalidArgumentException('delete with bool condition, seems odd');
		}
		if (empty ($id)) {
			throw new InvalidArgumentException('delete with empty condition, seems odd');
		}
		$sql = 'DELETE FROM ' .
		$this->getTableName($tableName) .
		$this->getWhereString($id, $tableName, true);
		return $this->query($sql);
	}

	/**
	 * update a row whose id is matching. 
	 * 
	 * <code>
	 * $this->update(array(
	 * 	'fooId'=>10,
	 *  'data1'=>20
	 * ));
	 * </code>
	 *
	 * @param mixed $id primary array of fields suitable to construct a WHERE clause
	 * @param array $fields name=>value pairs of new values
	 * @param string $tableName update data in this table (if ommitted: use tablename of this model, including prefix)
	 */
	function update($id, $fields, $tableName = null) {
		if (empty ($id)) {
			throw new InvalidArgumentException('update with empty id, seems odd');
		}
		if (empty ($fields)) {
			throw new InvalidArgumentException('insert without fields, seems odd');
		}
		if (!is_array($fields)) {
			throw new InvalidArgumentException('fields must be an array');
		}
		return $this->query('UPDATE ' .
		$this->getTableName($tableName) .
		' SET ' . $this->pairs($fields) .
		$this->getWhereString($id, $tableName, true));
	}

	/**
	 * REPLACE works exactly like Insert, but removes previous entries.
	 *
	 * Warning: if an old row in the table has the same value as a new row for a PRIMARY KEY or a UNIQUE index,
	 * the old row is deleted before the new row is inserted. In short: It may be that more than 1 row is deleted.
	 *
	 * <code>
	 * $this->replace(array(
	 * 	'fooId'=>10,
	 *  'data1'=>20
	 * ));
	 * </code>
	 *
	 * @param mixed $id primary key of row to replace
	 * @param array $fields name=>value pairs of new values
	 * @param string $table replace from this table (if ommitted: use tablename of this model, including prefix)
	 */
	function replace($fields = null, $tableName = null) {
		if (empty ($fields)) {
			throw new InvalidArgumentException('insert without fields, seems odd');
		}
		if (!is_array($fields)) {
			throw new InvalidArgumentException('fields must be an array');
		}

		return $this->dbo()->replace($this->getTableName($tableName), $fields, $this->pairs($fields));
	}

	/**
	 * tries to reduce all fields of the given table to basic datatypes
	 *
	 * @param string $tableName optional tablename to use
	 * @return array
	 */
	function & describe($tableName = null) {
		$tableName = $this->getTableName($tableName);

		$cacheUtil = getUtil('Cache');
		$cacheId = 'describe.' . $this->connection . '.' . $tableName;
		$data = $cacheUtil->read($cacheId, CacheUtility :: CM_FILE);
		if (false !== $data) {
			return $data;
		}

		$data = $this->dbo()->describe($tableName);
		$cacheUtil->write($cacheId, $data, MINUTE, CacheUtility :: CM_FILE);
		return $data;
	}

	/**
	 * checks the given values of the array match certain criterias
	 * 
	 * @param array $params key/value pair. key is the name of the key inside the $what-array, value is a "VALID_" define (see above) OR the name of a (global) function that is given the string (should return bool wether the string validates) OR a regex string
	 * @param array $what the actual data, eg. GET/POST parameters
	 * @return bool true if everything is okay OR $params-array-key of the row that is not validating
	 * @deprecated 31.04.2009
	 */
	function validate($params = null, $what = null) {
		$validateUtil = getUtil('Validate');
		return $validateUtil->check($params, $what);
	}

	/**
	 * getModel() wrapper
	 */
	 function getModel($name) {
		 return getModel($name);
	 }

}