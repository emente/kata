<?php
/**
 * @package kata
 */




/**
 * nano-helper for javascript. simply add() javascript in your view, and get() it inside the head-section of your layout
 * @author mnt@codeninja.de
 * @package kata_helper
 */

class JsHelper extends Helper {

	private $jsLines='';

/**
 * add javascript to buffer inside a view
 * 
 * @param string $js javascript
 */
	function add($js) {
		$this->jsLines = $this->jsLines."\n".$js;
	}

/**
 * return buffer. use inside your layout:
 * 
 * <code>
 * ...inside head-element inside your layout
 * [script type="application/javascript"]
 * echo $js->get();
 * [/script]
 * </code>
 * 
 * @return string joined javascript-strings you gave the helper via add()
 */
	function get() {
	   return $this->jsLines;
	}

/**
 * like get, but compresses the javascript on the fly
 *
 * @return string compressed javascript
 */
	function getCompressed() {
		return $this->compress($this->jsLines);
	}

/**
 * quote string for inclusion in javascript-strings
 *
 * @param string $s string to quote
 * @return string quoted string
 */
	function quote($s,$withDoubleQuotes=false) {
		if ($withDoubleQuotes) {
			return str_replace('"','\\"',$s);
		}
		return str_replace("'","\\'",$s);
	}

/**
 * return the given javascript-string in compressed form
 * 
 * @param string $js your javascript
 * @return string your javascript compressed
 */
	function compress($js) {
		$minifyUtil = getUtil('Minify');
		return $minifyUtil->js($js);
	}

}
