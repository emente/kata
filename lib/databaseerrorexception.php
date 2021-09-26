<?php
/**
 * model related exception
 *
 * Kata - Lightweight MVC Framework <http://www.codeninja.de/>
 * Copyright 2007-2015 mnt@codeninja.de
 *
 * Licensed under The GPL License
 * Redistributions of files must retain the above copyright notice.
 * @package kata_model
 */



/**
 * Thrown if an sql-query generates an error
 * 
 * @package kata_model
 */
class DatabaseErrorException extends Exception {

	/**
	 * contains the query that generated the exception
	 * @var string
	 */
	protected $query = '';

	/**
	 *
	 * @param string $message informational message
	 * @param mixed $code query string
	 * @param Exception $previous previous Exception
	 */
	public function __construct($message = null, $code = 0, Exception $previous = null) {
		if ((0 !== $code) && is_String($code)) {
			$code = trim($code);
			if (strlen($code)>1024) {
				$code = substr($code,0,1023).' *snip*';
			}

			$this->query = $code;
			$message = $message." (SQL '".$code."')";
			$code = 0;
		}
		
		parent::__construct($message, $code);
	}

	/**
	 * return query that generated the exception
	 * @return string
	 */
	final public function getQueryString() {
		return $this->query;
	}


}