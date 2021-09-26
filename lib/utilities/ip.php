<?php

/**
 * @package kata
 */
/**
 * contains IP-class
 * @package kata
 */

/**
 * some ip utility functions
 * @package kata_utility
 * @author mnt@codeninja.de
 */
class IpUtility {

	private $simpleHeaders = array(
		'REMOTE_ADDR',
		'HTTP_CLIENT_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_FORWARDED',
		'HTTP_FORWARDED_FOR',
		'HTTP_FORWARDED',
		'HTTP_X_COMING_FROM',
		'HTTP_COMING_FROM'
	);
	private $proxyHeaders = array(
		'HTTP_VIA',
		'HTTP_PROXY_CONNECTION',
		'HTTP_XROXY_CONNECTION',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_FORWARDED',
		'HTTP_FORWARDED_FOR',
		'HTTP_FORWARDED',
		'HTTP_X_COMING_FROM',
		'HTTP_COMING_FROM',
		'HTTP_CLIENT_IP',
		'HTTP_PC_REMOTE_ADDR',
		'HTTP_CLIENTADDRESS',
		'HTTP_CLIENT_ADDRESS',
		'HTTP_SP_HOST',
		'HTTP_SP_CLIENT',
		'HTTP_X_ORIGINAL_HOST',
		'HTTP_X_ORIGINAL_REMOTE_ADDR',
		'HTTP_X_ORIG_CLIENT',
		'HTTP_X_CISCO_BBSM_CLIENTIP',
		'HTTP_X_AZC_REMOTE_ADDR',
		'HTTP_10_0_0_0',
		'HTTP_PROXY_AGENT',
		'HTTP_X_SINA_PROXYUSER',
		'HTTP_XXX_REAL_IP',
		'HTTP_X_REMOTE_ADDR',
		'HTTP_RLNCLIENTIPADDR',
		'HTTP_REMOTE_HOST_WP',
		'HTTP_X_HTX_AGENT',
		'HTTP_XONNECTION',
		'HTTP_X_LOCKING',
		'HTTP_PROXY_AUTHORIZATION',
		'HTTP_MAX_FORWARDS',
		'HTTP_X_IWPROXY_NESTING',
		'HTTP_X_TEAMSITE_PREREMAP',
		'HTTP_X_SERIAL_NUMBER',
		'HTTP_CACHE_INFO',
		'HTTP_X_BLUECOAT_VIA'
	);

	//see http://www.zytrax.com/tech/web/mobile_ids.html
	private $mobileAgents = array(
		'IPhone',
		'Android',
		'BlackBerry',
		'DoCoMo',
		'Maemo',
		'MeeGo',
		'NetFront',
		'Nokia',
		'PalmOS',
		'PalmSource',
		'SonyEricsson',
		'Symbian',
		'Windows CE',
		'IEMobile',
		'J2ME',
		'Minimo',
		'UP.Browser',
		'AvantGo'
	);

	/**
	 * simplified ip guess, so we don't end up with local ips
	 * @return string user-ip
	 */
	public function getIp() {
		$ip = '0.0.0.0';

		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
				$ip = $_SERVER['HTTP_CLIENT_IP'];
			} else {
				$ip = $_SERVER['REMOTE_ADDR'];
			}
		}

		if (!empty($_SERVER['HTTP_CLIENTADDRESS'])) {
			$ip = $_SERVER['HTTP_CLIENTADDRESS'];
		}

		return $ip;
	}

	/**
	 * try to do an educated guess about the users real ip, even if he is behind proxies
	 */
	public function getEndIp() {
		foreach ($this->simpleHeaders as $header) {
			$h = env($header);
			if (isset($h) && !empty($h)) {
				return $h;
			}
		}
		return '0.0.0.0';
	}

	/**
	 * is user using a proxy?
	 * @return bool
	 */
	public function isUsingProxy() {
		foreach ($this->proxyHeaders as $header) {
			$h = env($header);
			if (isset($h) && !empty($h)) {
				return true;
				break;
			}
		}
		return false;
	}

	/**
	 * is user using a handheld device to surf?
	 * @return bool
	 */
	public function isMobileDevice() {
		$agent = env('HTTP_USER_AGENT');
		if (empty($agent)) {
			return false;
		}

		foreach ($this->mobileAgents as $mobile) {
			if (false !== strpos($agent,$mobile)) {
				return true;
			}
		}
		return false;
	}

}
