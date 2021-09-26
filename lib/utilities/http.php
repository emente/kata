<?php

/**
 * @package kata
 */

/**
 * http-request class that does GET and POST (and even SSL) and has no dependencies
 *
 * @author mnt@codeninja.de
 * @package kata_utility
 *
 * Kata - Lightweight MVC Framework <http://www.codeninja.de/>
 * Copyright 2007-2015 mnt@codeninja.de
 *
 * Licensed under The GPL License
 * Redistributions of files must retain the above copyright notice.
 */
class HttpUtility {

	/**
	 * @var array will hold all returned webserver-headers
	 */
	private $returnHeaders;
	/**
	 * @var bool true to automatically follow redirects
	 */
	private $followRedirects= true;
/**
 * send cookies we got on the last request?
 */
	private $rememberCookies=false;
/**
 * cookies from last request
 */
	private $lastCookies=array();

	const TYPE_GET= 1;
	const TYPE_POST= 2;
	const TYPE_HEAD= 3;

	/**
	 * do a GET request to given url. you dont have to encode get-parameters, just give it as array
	 *
	 * @param string $url base-url to request to eg. http://example.com/foo/bar
	 * @param array $getVars optional get-parameters to append to url
	 * @param array $headers optional request-headers to add to request
	 * @return mixed returns html or false if something went wrong (then ask lastError())
	 */
	public function get($url, $getVars= false, $headers= false) {
		return $this->doRequest(self :: TYPE_GET, $url, $getVars, false, $headers);
	}

	/**
	 * do a HEAD request to given url. you dont have to encode get-parameters, just give it as array
	 *
	 * @param string $url base-url to request to eg. http://example.com/foo/bar
	 * @param array $getVars optional get-parameters to append to url
	 * @param array $headers optional request-headers to add to request
	 * @return mixed returns html or false if something went wrong (then ask lastError())
	 */
	public function head($url, $getVars= false, $headers= false) {
		return $this->doRequest(self :: TYPE_HEAD, $url, $getVars, false, $headers);
	}

	/**
	 * do a GET request to given url. you dont have to encode get-parameters, just give it as array
	 *
	 * @param string $url base-url to request to eg. http://example.com/foo/bar
	 * @param array $getVars optional get-parameters to append to url
	 * @param array $postVars optional post-parameters to add to request
	 * @param array $headers optional request-headers to add to request
	 * @return mixed returns html or false if something went wrong (then ask lastError())
	 */
	public function post($url, $getVars= false, $postVars= false, $headers= false) {
		return $this->doRequest(self :: TYPE_POST, $url, $getVars, $postVars, $headers);
	}

	/**
	 * @param bool whether or not to follow redirects like 301,303 etc.
	 */
	public function setFollowRedirects($follow) {
		$this->followRedirects= $follow;
	}

	/**
	 * @param bool whether or not to remember cookies
	 */
	public function setRemembercookies($remember) {
		$this->rememberCookies= $remember;
	}

	/**
	 * actual wrapper
	 * @param int $type see TYPE_ consts. get/post/head
	 * @param string $url
	 * @param array $getVars
	 * @param array $postVars
	 * @param array $headers
	 * @return string html
	 */
	protected function doRequest($type, $url, $getVars, $postVars, $headers) {
		if (false === $headers) {
			$headers= array ();
		}

		$urlArr= parse_url($url);
		if (isset ($urlArr['user'], $urlArr['pass'])) {
			$url= str_replace($urlArr['user'].':'.$urlArr['pass'].'@', '', $url);
			$headers['Authorization']= 'Basic '.base64_encode($urlArr['user'].':'.$urlArr['pass']);
		}

		if ($type == self :: TYPE_GET) {
			$params= array (
				'http' => array (
					'method' => 'GET'
				)
			);
		}

		if ($type == self :: TYPE_HEAD) {
			$params= array (
				'http' => array (
					'method' => 'HEAD'
				)
			);
		}

		if ($type == self :: TYPE_POST) {
			$postStr= http_build_query($postVars);
			$params= array (
				'http' => array (
					'method' => 'POST',
					'content' => $postStr
				)
			);
			$headers['Content-Type']= 'application/x-www-form-urlencoded';
			$headers['Content-Length']= strlen($postStr);
		}

		if ($this->rememberCookies && !empty($this->lastCookies)) {
			$cookieStr = '';
			$idx = 0;
			foreach ($this->lastCookies as $n=>$v) {
				if ($idx++>0) { $cookieStr.=' $'; }
				$cookieStr .= $n.'='.$v.';';
				if ($idx+1<count($this->lastCookies)) { $cookieStr.=' '; }
			}
			$headers['Cookie'] = $cookieStr;
		}

		if (is_array($getVars)) {
			$url .= '?'.http_build_query($getVars);
		}

		$headers['User-Agent']= 'kata httpUtility - http://codeninja.de';

		$headerStr= '';
		foreach ($headers as $name => $value) {
			$headerStr .= $name.': '.$value."\r\n";
		}
		$params['http']['header']= $headerStr;
		$context= stream_context_create($params);
		$stream=  @fopen($url, 'rb', false, $context);

		if (!$stream) {
			if (isset($http_response_header)) {
				$http_response_header = array_reverse($http_response_header);
				$headArr = array();
				foreach ($http_response_header as $s) {
					if (substr($s,0,5) == 'HTTP/') {
						break;
					}
					$headArr[] = $s;
				}
				$headArr[] = $this->parseHeaders($headArr);
				return false;
			}

			$this->returnHeaders= array (
				'Status' => 0,
				'X-Kata-Error' => 'Stream init failed'
			);
			return false;
		}

		$html= @ stream_get_contents($stream);
		$headers= stream_get_meta_data($stream);
		if (isset ($headers['wrapper_data'])) {
			$this->returnHeaders= $this->parseHeaders($headers['wrapper_data']);
		} else {
			$this->returnHeaders= $this->parseHeaders($headers);
		}
		fclose($stream);

		if (false === $html) {
			return false;
		}

		if ($this->followRedirects) {
			$location= isset ($this->returnHeaders['Location']) ? $this->returnHeaders['Location'] : '';
			switch ($this->getStatus()) {
				case 301 :
				case 302 :
				case 307 :
					return $this->doRequest($type, $location, $getVars, $postVars, $headers);
					break;
				case 303 : //post->get
					return $this->doRequest($type == self :: TYPE_POST ? self :: TYPE_GET : $type, $location, $getVars, $postVars, $headers);
					break;
			}
		}

		return $html;
	}

	/**
	 * returns the http-status code of the last request. 0 if we could not figure out status-code
	 *
	 * @return int
	 */
	public function getStatus() {
		if (isset ($this->returnHeaders['Status'])) {
			return $this->returnHeaders['Status'];
		}
		if (!empty ($this->returnHeaders)) {
			$s= $this->returnHeaders[0];
			if (substr($s, 0, 4) == 'HTTP') {
				$temp= explode(' ', $s);
				return $temp[1];
			}
		}
		return 0;
	}

	/**
	 * return all webserver-headers from the last request
	 */
	public function getReturnHeaders() {
		return is($this->returnHeaders, array ());
	}

	protected function parseHeaders($headers) {
		$headOut= array ();
		foreach ($headers as $h) {
			$x= strpos($h, ':');
			if ($x === false) {
				$headOut[]= $h;
			} else {
				$headOut[substr($h, 0, $x)]= substr($h, $x +2);
			}
		}
		if (isset($headOut['Set-Cookie']) && $this->rememberCookies) {
			$this->lastCookies = $this->parseCookies($headOut['Set-Cookie']);
		}
		return $headOut;
	}

	protected function parseCookies($s) {
		$cookies = array();
		$sArr = explode(';',$s);
		foreach ($sArr as $s) {
			$s = trim($s);
			$name = '';
			$x = strpos($s,'=');
			if ($x>0) {
				$name = substr($s,0,$x);
				$s = substr($s,$x+1);
			}
			if (!empty($name)) {
				$cookies[$name] = $s;
			}
		}
		return $cookies;
	}
}