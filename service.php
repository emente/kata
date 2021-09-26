<?php
/**
 * the kata cleanup commandline tool, for the lazy among us
 *
 * @package kata_internal
 * @author mnt@codeninja.de
 */
require('lib' . DIRECTORY_SEPARATOR . 'defines.php');
require(LIB . "boot.php");

if (!CLI) {
	die('Command line only.');
}

$options = getopt('ycprRl:vh?');

if (isset($options['h']) || isset($options['?']) || !isset($options['y'])) {
	echo "Usage:	{$argv[0]}

	Purges all stale files kata eventually leaves behind, like
	lockfiles, sessions, cachentries...

	-y  actually DO something, so you dont start this by accident
	-c  remove all css/js caches
	-p  purge even non-expired cacheutil filesystem entries
	-r  rotate logs in logs/ directory if >1GB
	-R  rotate logs in logs/ directory, regardless of size
	-v  be verbose while cleaning up
	-h  help
";
	die;
}

if (isset($options['v'])) {

	function msg($s) {
		echo "$s\n";
	}

} else {

	function msg($s) {

	}

}

clearstatcache();

$isWindows = substr(PHP_OS, 0, 3) == 'WIN';

////////////////////////////////////////////////////////////////////////////////

if ($isWindows) {
	msg('Detected windows, skipping lockfiles cleanup');
} else {
	msg('Cleaning up lockfiles');
	$lockUtil = getUtil('Lock');
	$lockUtil->garbageCollect(true);
}

msg('Cleaning up sessions');
$sessDir = KATATMP . 'sessions' . DS;
if (file_exists($sessDir) && is_dir($sessDir)) {
	$timeOut = 2400; //php default
	if (defined('SESSION_TIMEOUT')) {
		$timeOut = SESSION_TIMEOUT;
	}
	$timeLimit = time() - (2 * $timeOut);
	$dh = opendir($sessDir);
	if ($dh) {
		while (($file = readdir($dh)) !== false) {
			if ((substr($file, 0, 5) == 'sess_') && is_file($sessDir . $file)) {
				$accTime = $isWindows ? filemtime($sessDir . $file) : fileatime($sessDir . $file);
				if ($accTime < $timeLimit) {
					msg($file);
					unlink($sessDir . $file);
				}
			}
		}//readdir
		closedir($dh);
	}//$dh
}//sessDir



if (isset($options['c'])) {
	msg('Cleaning up css/js');
	$cacheDir = KATATMP . 'cache' . DS;
	if (file_exists($cacheDir) && is_dir($cacheDir)) {
		$cacheFiles = glob($cacheDir . '*.cache.*');
		foreach ($cacheFiles as $file) {
			unlink($file);
			msg(basename($file));
		}//$file
	}//$cachedir
}//-c

msg('purging expired cacheutil-entries in filesystem');
$cacheDir = KATATMP . 'cache' . DS;
if (file_exists($cacheDir) && is_dir($cacheDir)) {
	$cacheFiles = glob($cacheDir . CACHE_IDENTIFIER . '-*');
	foreach ($cacheFiles as $file) {
		$txt = file_get_contents($file);
		if (substr($txt, 0, 17) === 'a:2:{s:3:"ttl";i:') { //is cache file?
			if (isset($options['p'])) {
				unlink($file);
			} else {
				$temp = @unserialize($txt);
				if (false !== $temp) { // unserialized?
					if (isset($temp['ttl']) && ($temp['ttl'] > 0) && (time() > $temp['ttl'])) { //cachefile stale?
						msg(basename($file));
						unlink($file);
					}//stale
				}//unser!=false
			}//force
		}//is serialized
	}//foreach
}//$cacheDir

function rotateLogFile($file) {
	$parts = explode('.', $file);
	$partCount = count($parts);

	if (is_numeric($parts[$partCount - 2])) {
		$parts[$partCount - 2] = $parts[$partCount - 2] + 1;
	} else {
		$parts[$partCount] = $parts[$partCount - 1];
		$parts[$partCount - 1] = 0;
	}

	$newFile = implode('.', $parts);
	if (file_exists($newFile)) {
		rotateLogFile($newFile);
	}

	for ($i = 0; $i < 10; $i++) {
		if (rename($file, $newFile)) {
			return;
		}
		sleep(rand(1, 10));
	}
	msg("Failed to rotate $file");
}

if (isset($options['r']) || isset($options['R'])) {
	msg('Rotating logfiles');
	$sizeLimit = 1 * 1024 * 1024 * 1024; //gb
	if (isset($options['R'])) {
		$sizeLimit = 0;
	}

	$logDir = KATATMP . 'logs' . DS;
	if (file_exists($logDir) && is_dir($logDir)) {
		$logFiles = glob($logDir . '*.log');
		foreach ($logFiles as $file) {
			$parts = explode('.', $file);
			if (is_numeric($parts[count($parts) - 2])) {
				continue;
			}
			unset($parts);
			if (filesize($file) >= $sizeLimit) {
				rotateLogFile($file);
				msg(basename($file));
			}
		}
	}
}

