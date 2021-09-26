<?php
/**
 * never change this file!
 *
 * @package kata_internal
 * @author mnt@codeninja.de
 */


/**
 * setup always needed paths
 */
require('..'.DIRECTORY_SEPARATOR.'lib'.DIRECTORY_SEPARATOR.'defines.php');

/**
 * include needed files
 */
require(LIB."boot.php");

/**
 * call dispatcher to handle the rest
 */
$dispatcher=new dispatcher();
echo $dispatcher->dispatch(isset($_GET['kata'])?$_GET['kata']:'',isset($routes)?$routes:null);

