<?php
/**
 * include this file inside your config.php to get a profiling-overview of your webapp.
 * note: you need the xdebug extension
 * 
 * @author mnt@codeninja.de
 * @package kata_debugging
 *
 * Kata - Lightweight MVC Framework <http://www.codeninja.de/>
 * Copyright 2007-2015 mnt@codeninja.de
 *
 * Licensed under The GPL License
 * Redistributions of files must retain the above copyright notice.
 */

if (extension_loaded('xhprof') && (DEBUG>=0)) {
	
	/*
	 * xhprof_lib/ path must be in your include_path!
	 */
    require 'utils/xhprof_lib.php';
    require 'utils/xhprof_runs.php';
    xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);

	register_shutdown_function('kataXhprofShutdown');
}


function kataXhprofShutdown() {
    $xhprof_data = xhprof_disable();
    $xhprof_runs = new XHProfRuns_Default();
    $run_id = $xhprof_runs->save_run($xhprof_data, CACHE_IDENTIFIER);

	if (!defined('XHPROF_URL')) {
		die('you need to set XHPROF_URL to the absolute path of the xhprof_html directory');
	}
    $profiler_url = sprintf('%s?run=%s&source=%s', XHPROF_URL, $run_id, CACHE_IDENTIFIER);
    
    switch (DEBUG) {
    case 2:
    case 3:
    	echo '<div style="padding:10px;border:5px solid red;z-index:2000;position:absolute;top:0;left:0;background-color:white;">'.
	    '<a href="'.$profiler_url.'">xhprof report</a></div>';
	    break;
    case 1:
    	debug('xhprof report: '.$profiler_url);
    	break;
    case 0:
    	writeLog('xhprof report: '.$profiler_url.' '.env('QUERY_STRING'),2);
    	break;
    }
}