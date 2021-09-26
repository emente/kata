<?php
/**
 * command line invoker for kata
 *
 * @author mnt
 * @author Raimar Lutsch
 * @package kata_internal
 */

/**
 * start
 */
require(dirname(__FILE__).DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'defines.php');
require(LIB."boot.php");

set_time_limit(600);

if (posix_getuid() == 0) {
   echo "Warning: If this is your first call to the framework, kata\n".
        "may create the tmp-folders with rights that may make them\n".
        "inaccessible for apache-users (eg. www-data)\n";
}

if ($argc<2) {
   die("Usage: cron.php controller action [param1] [param2] [...]\n");
}

$args = $argv;
array_shift($args);

$dispatcher=new dispatcher();
echo $dispatcher->dispatch(implode('/',$args),null);
