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
 * interface 
 * @package kata_model
 **/
interface dbo_interface {

	/**
	 * REPLACE works like INSERT,
	 * except that if an old row in the table has the same value as a new row for a PRIMARY KEY or a UNIQUE  index,
	 * the old row is deleted before the new row is inserted
	 *
	 * @param string $tableName replace from this table
	 * @param array $fields name=>value pairs of new values
	 * @param string $pairs enquoted names to escaped pairs z.B.[name]='value'
	 * @return int modified rows.
	 */
	function replace($tableName, $fields, $pairs);

	/**
	 * a copy of the matching db-config entry in config/database.php
	 * @param $string $what spezifies what to get ... null=complete config array
	 * @return array|string
	 */
	function getConfig($what= null);

	/**
	 * set db-config entry
	 * @param $array $config
	 */
	function setConfig($config);

	/**
		 * checks if given operator is valid
		 * @param string $operator
		 * @return boolean
		 */
	function isValidOperator($operator);

	/**
	 * connect to the database
	 */
	function connect();

	/**
	 * are we connected?
	 */
	function isConnected();

	/**
	 * return the current link to the database, connect first if needed
	 */
	function getLink();

	/**
	 * inject dblink into dbo
	 */
	function setLink($l);

	/**
	 * unused right now, later possibly used by model to set right encoding
	 */
	function setEncoding($enc);

	/**
	 * execute query and return useful data depending on query-type
	 *
	 * @param string $s sql-statement
	 * @param array $idname which field-value to use as the key of the returned array (false=dont care)
	 * @return array
	 *
	 */
	function & query($s, $idnames= false);

	/**
	 * escape the given string so it can be safely appended to any sql
	 * @param string $sql string to escape
	 * @return string
	 */
	function escape($sql);

	/**
	* used to quote table and field names
	* @param string $s string to enquote;
	* @return string enquoted string
	*/
	function quoteName($s);

	/**
	 * output any queries made, how long it took, the result and any errors if DEBUG
	 * close the connection
	 */
	function __destruct();

	/**
	 * build a sql-string that returns first matching row
	 * @param string $sql SQL-String
	 * @param string $perpage expresion
	 * @return string finished query
	 */
	function getFirstRowQuery($sql, $perpage);

	/**
	 * build a sql-string that returns paged data
	 * @return string finished query
	 */
	function getPageQuery($sql, $page, $perPage);

	/**
	 * return the sql needed to convert a unix timestamp to datetime
	 * @param integer $t unixtime
	 * @return string
	 */
	function makeDateTime($t);

	/**
	 * try to reduce the fields of given table to the basic types bool, unixdate, int, string, float, date, enum
	 *
	 * <code>example:
	 *
	 * Array
	 * (
	 *     [table] => test
	 *     [primary] => array
	 *     [unique] => array
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
	function & describe($tableName);
}