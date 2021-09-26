<?php
/**
 * @package kata
 */





/**
 * routines for super simple pattern parsing
 * @package kata_utility
 * @author mnt@codeninja.de
 */
 class SimpleparseUtility {

	/**
	 * cut out a text depending on a searchpattern.
	 * example: text is "abcdefghij",
	 * 			searchpattern is "ab*fg",
	 * 			then result is "cde"
	 *
	 * @param string $text text to search in
	 * @param string $pattern pattern to search in text
	 * @return mixed false when nothing is found, otherwise an array with text-results
	 */
	public function getPattern($text, $pattern, $casesensitive= true) {
		if ($text === false) {
			$text= $this->_http->getBody();
		}
		$pattern= explode("*", $pattern);

		if ($casesensitive) {
			$x1= strpos($text, $pattern[0]);
		} else {
			$x1= stripos($text, $pattern[0]);
		}
		if ($x1 === false) {
			return false;
		}

		if ($casesensitive) {
			$x2= strpos($text, $pattern[1], $x1);
		} else {
			$x2= stripos($text, $pattern[1], $x1);
		}
		if ($x2 === false) {
			return false;
		}

		return substr($text, $x1 +strlen($pattern[0]), $x2 - $x1 -strlen($pattern[0]));
	}

/**
 * cut out text depending on pattern, multiple wildcards allowed
 * example: text is "abcdefghij",
 * 			searchpattern is "ab*fg*ij",
 * 			then result is array("cde","h")
 *
 * @param string $text text to search in
 * @param string $pattern pattern to search in text
 * @return mixed false when nothing is found, otherwise an array with text-results
 */
	public function getPatterns($text, $pattern, $casesensitive= true) {
		$pattern= explode("*", $pattern);
		if (count($pattern)<2) {
			return false;
		}
		$out= array ();
		for ($i= 1; $i < count($pattern); $i++) {
			$temp= $this->getPattern($text, $pattern[$i -1].'*'.$pattern[$i], $casesensitive);
			if ($temp !== false) {
				$out[]= $temp;
			}
		}
		return $out;
	}


}