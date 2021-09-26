<?php

/**
 * contains geoip lookup
 * @package kata
 */

/**
 * routines to turn ip info to country
 *
 * can use pecl-geoip (recommended):
 * 'pecl install geoip'
 *
 * can use geoip-tools (fallback):
 * 'apt-get install geoip'
 *
 * can use pear-geoip (sloooow):
 * 'pear install Net_GeoIP'
 * install database: http://www.maxmind.com/app/geoip_country
 *
 * @package kata_utility
 * @author mnt@codeninja.de
 */
class GeoipUtility {
	/*
	  GeoIP Country Edition: --, N/A
	  GeoIP City Edition, Rev 1: DE, 01, Karlsruhe, (null), 49.004700, 8.385800, 0, 0
	  GeoIP City Edition, Rev 0: DE, 01, Karlsruhe, (null), 49.004700, 8.385800

	  GeoIP Country Edition: --, N/A
	  GeoIP City Edition, Rev 1: IP Address not found
	  GeoIP City Edition, Rev 0: IP Address not found
	 */

	/**
	 * @var string path to geoiplookup binary
	 */
	private $cmdLine = '/usr/bin/geoiplookup';

	/**
	 * look up ip and return 2-char country code
	 *
	 * @param string $ip optional, if empty will use iputility to obtain ip
	 * @return array 'de' or '--' if unshure
	 * @throws ErrorException
	 */
	function lookup($ip='') {
		if (empty($ip)) {
			$ipUtil = getUtil('Ip');
			$ip = $ipUtil->getIp();
		}

		if (function_exists('geoip_country_code_by_name')) {
			$temp = geoip_country_code_by_name($ip);
			if (!empty($temp)) {
				return $temp;
			}
			return '--';
		}

		if (is_file($this->cmdLine)) {
			$ret = shell_exec($this->cmdLine . ' ' . $ip);
			if (!empty($ret)) {
				foreach ($ret as $line) {
					$temp = explode(':', $line);
					if (!empty($line[1])) {
						$parts = explode(',', $line);

						if (!empty($parts[0]) && ('--' != $parts[0])) {
							return $parts[0];
						}//if !--
					}//!empty(line)
				}//foreach
			}//empty $ret
		}//file

		if (class_exists('Net_GeoIP')) {
			$inst = NetGeoIP::getInstance();
			$temp = $inst->lookupCountryCode($ip);
			if (!empty($temp)) {
				return $temp;
			}
			return '--';
		}

		throw new ErrorException('geoip: no extension, no binary, no pear-class/db, fatal error');
	}

}