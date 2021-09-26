<?php
/**
 * include this file inside your config.php to turn any error,warning,notice into a hard error that causes a stacktrace
 *
 * @author mnt@codeninja.de
 * @package kata_debugging
 */

if (DEBUG > 0) {

	/**
	 * our own exception to inject line and file into exception
	 * @package kata_debugging
	 */
	class kataStrictError extends Exception {
		protected $linestr='';
		function __construct($message = null, $file, $line, $str) {
			$this->message = $message;
			$this->file = $file;
			$this->line = $line;
			$this->code = 0;
			$this->linestr = $str;
		}
		function getLineStr() {
			return $this->linestr;
		}
	}

	/**
	 * error handler that throws kataStrictError-exception on error
	 */
	function kataStrictErrorHandler($code, $msg, $file, $line) {
		if (0 == error_reporting()) {
			return;
		}
		if (is_null($file)) {
			return;
		}

		$lines = file($file);
		throw new kataStrictError($msg,$file,$line,trim($lines[$line-1]));
		//restore_error_handler();
	}

	set_error_handler('kataStrictErrorHandler');

}//debug