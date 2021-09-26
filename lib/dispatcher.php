<?php


/**
 * Contains the dispatcher-class. Here is where it all starts.
 *
 * Kata - Lightweight MVC Framework <http://www.codeninja.de/>
 * Copyright 2007-2015 mnt@codeninja.de
 *
 * Licensed under The GPL License
 * Redistributions of files must retain the above copyright notice.
 * @package kata_internal
 */

/**
 * dispatcher. this is the first thing that is constructed.
 * the dispatcher then collects all parameters given via get/post and instanciates the right controller
 * @package kata_internal
 */
class dispatcher {
	/**
	 * placeholer-array for all relevant variables a class may need later on (e.g. controller)
	 * [isAjax] => false (boolean, tells you if view got called with /ajax/)
	 * [url] => Array (
	 *       [url] => locations
	 *       [foo] => bar (if url read ?foo=bar)
	 * )
	 * [form] => Array (
	 * 	  (all post-variables, automatically dequoted if needed)
	 * )
	 * [controller] => main (name of the controller of this request)
	 * [action] => index (name of the view of this request)
	 * @var array
	 */
	public $params;

	/**
	 * name of the current controller
	 * @var string
	 */
	public $controller;

	/**
	 * time in us the dispather started (used to calculate how long the framework took to completely render anything)
	 */
	private $starttime;

	/**
	 * constructor, just initializes starttime
	 */
	function __construct() {
		$this->starttime = microtime(true);
	}

	/**
	 * destructor, outputs Total Render time if DEBUG>0
	 */
	function __destruct() {
		if (DEBUG > 0) {
			kataDebugOutput('Total Render Time (including Models) ' . (microtime(true) - $this->starttime) . ' secs');
			kataDebugOutput('Memory used ' . number_format(memory_get_usage(true)) . ' bytes');
			kataDebugOutput('Parameters ' . print_R($this->params, true));
			if (function_exists('xdebug_get_profiler_filename')) {
				$fn = xdebug_get_profiler_filename();
				if (false !== $fn) {
					kataDebugOutput('profilefile:' . $fn);
				}
			}
			kataDebugOutput('Loaded classes: ' . implode(' ', array_keys(classRegistry :: getLoadedClasses())));
		}
	}

	private $forbiddenActions = array(
		'dispatch'=>1,
		'render'=>1,
		'renderView'=>1,
		'renderCachedHtml'=>1,
		'redirect'=>1,
		'set'=>1,
		'setRef'=>1,
		'setPageTitle'=>1,
		'log'=>1,
	);

	/**
	 * start the actual mvc-machinery
	 * 1. constructs all needed params by calling constructParams
	 * 2. loads the controller
	 * 3. sets all needed variables of the controller
	 * 4. calls constructClasses of the controller, which in turn constructs all needed models and components
	 * 5. render the actual view and layout (if autoRender is true)
	 * 6. return the output
	 * @param string $url raw url string passed to the array (eg. /main/index/foo/bar)
	 */
	final function dispatch($url, $routes = null) {
		$this->constructParams($url, $routes);

		try {
			$lowername = strtolower($this->params['controller']);

			if ('app' == $lowername) {
				$this->fourohfour();
				return;
			}

			//cache-controller? handle internally
			if (isset($this->params['action'][0]) && ($this->params['action'][0]=='_') && (substr($this->params['action'],0,6) == '_cache')) {
				$this->handleCachedFiles($lowername, substr($this->params['action'], 7));
			}
			
			// load controller and check if action exists
			require_once LIB.'controller.php';
			if (file_exists(ROOT . 'controllers' . DS . $lowername . '_controller.php')) {
				require_once ROOT . 'controllers' . DS . $lowername . '_controller.php';
			} else {
				if (file_exists(LIB . 'controllers' . DS . $lowername . '_controller.php')) {
					require_once LIB . 'controllers' . DS . $lowername . '_controller.php';
				} else {
					if (empty ($this->params['action'])) {
						$this->params['action'] = 'index';
					}

					$c = $this->constructController('AppController');
					$c->beforeAction();
					if ($c->before404()) {
						$this->fourohfour();
					}
					return '';
				}
			}

			$classname = ucfirst($lowername) . 'Controller';
			$c = $this->constructController($classname);

			if (!empty ($this->params['isAjax'])) {
				$c->layout = null;
			}

			$c->beforeAction();

			if (!is_callable(array (
					$c,
					$this->params['action']
					)) || ($this->params['action'][0] == '_')
					|| isset($this->forbiddenActions[$this->params['action']])) {
				if ($c->before404()) {
					$this->fourohfour();
					return;
				}
			} else {
				$c->dispatch();

				if ($c->autoRender) {
					$c->render($this->params['action']);
				}
			}
		} catch (Exception $e) {
			$basePath = $this->constructBasePath();
			if (file_exists(ROOT . "views" . DS . "layouts" . DS . "error.thtml")) {
				include ROOT . "views" . DS . "layouts" . DS . "error.thtml";
			} else {
				include LIB . "views" . DS . "layouts" . DS . "error.thtml";
			}
			return '';
		}

		return $c->output;
	}

	function fourohfour() {
		$basePath = $this->constructBasePath();
		if (file_exists(ROOT . "views" . DS . "layouts" . DS . "404.thtml")) {
			include ROOT . "views" . DS . "layouts" . DS . "404.thtml";
		} else {
			include LIB . "views" . DS . "layouts" . DS . "404.thtml";
		}
	}


	private function constructController($classname) {
			$c = new $classname;
			$this->controller = $c;

			if (empty ($this->params['action'])) {
				$this->params['action'] = $c->defaultAction;
			}
			$c->basePath = $this->constructBasePath();
			$c->base = $this->constructBaseUrl();
			$c->webroot = $c->base . 'webroot/';
			$c->params = & $this->params;
			$c->action = $this->params['action'];

			$c->_constructClasses();

			return $c;
	}

	/**
	 * extract,clean and dequote any given get/post-parameters
	 * find out which controller and view we should use
	 * @param string $url raw url (see dispatch())
	 */
	private function constructParams($url, $routes = null) {
		//do we have routes?
		if (!empty ($routes) && is_array($routes)) {
			krsort($routes);
			if (!empty($routes[$url])) {
				$url = $routes[$url];
			} else {
				foreach ($routes as $old => $new) {
//					if (($old != '') && ($old.'/' == substr($url, 0, strlen($old.'/')))) {
					if (($old != '') && ($old == substr($url, 0, strlen($old)))) {
						$url = $new . substr($url, strlen($old));
						break;
					}
				}//foreach
			}//!empty

			// does route-target have a query-string? parse it
			$x = strpos($url,'?');
			if (false !== $x) {
				$result = array();
				parse_str(substr($url,$x+1),$result);
				$_GET = array_merge($_GET,$result);
				$url = substr($url,0,$x-1);
			}
		}

		$paramList = explode('/', $url);
		while ((count($paramList)>0) && ('' == $paramList[count($paramList)-1])) {
			array_pop($paramList);
		}

		if (isset ($paramList[0]) && ($paramList[0]) == 'ajax') {
			array_shift($paramList);
			$this->params['isAjax'] = 1;
		} else {
			$this->params['isAjax'] = 0;
		}

		$controller = "main";
		if (isset ($paramList[0]) && !empty ($paramList[0])) {
			$controller = strtolower(array_shift($paramList));
		}

		$action = '';
		if (isset ($paramList[0]) && !empty ($paramList[0])) {
			$action = strtolower(array_shift($paramList));
		} else {
			if (isset ($paramList[0])) {
				unset ($paramList[0]);
			}
		}

		$this->params['pass'] = $paramList;

		$kataUrl = is($_GET['kata'], '');
		unset ($_GET['kata']);
		if (!empty ($_GET)) {
			if (ini_get('magic_quotes_gpc') == 1) {
				$this->params['url'] = stripslashes_deep($_GET);
			} else {
				$this->params['url'] = $_GET;
			}
		}
		$this->params['callUrl'] = $kataUrl;

		if (!empty ($_POST)) {
			if (ini_get('magic_quotes_gpc') == 1) {
				$this->params['form'] = stripslashes_deep($_POST);
			} else {
				$this->params['form'] = $_POST;
			}
		}

		$this->params['controller'] = $controller;
		$this->params['action'] = $action;
	}

	/**
	 * construct the url path under which this framework can be called from the browser. adds / at the end
	 * @return string
	 */
	private function constructBasePath() {
		$base = dirname(dirname(env('PHP_SELF')));
		if (substr($base, -1, 1) == '\\') { //XAMMP
			$base = substr($base, 0, -1);
		}
		if (substr($base, -1, 1) != '/') {
			$base .= '/';
		}
		return $base;
	}

	/**
	 * tries to construct the base url under which this framework can be called from the browser. adds a "/" at the end
	 */
	private function constructBaseUrl() {
		$isHttp = env('HTTPS') != '';
		$port = (env('SERVER_PORT') != '80' ? (':' . env('SERVER_PORT')) : '');
		if ($isHttp && (':443' == $port)) {
			$port = ''; //thank you IE! :(
		}
		
		return 'http' . ($isHttp ? 's' : '') . '://' . env('SERVER_NAME') . $port .
				$this->constructBasePath();
	}

	private function handleCachedFiles($what, $version) {
		$filename = KATATMP . 'cache' . DS . $what. '.cache.' . basename($version);
		if (file_exists($filename)) {
			if (DEBUG>=3) {
				header('Expires: ' . gmdate('D, d M Y H:i:s', time() - HOUR). ' GMT');
			} else {
				header('Expires: ' . gmdate('D, d M Y H:i:s', time() + (60 * WEEK)) . ' GMT');
			}

			switch ($what) {
				case 'css' :
					header('Content-Type: text/css');
					break;
				case 'js' :
					header('Content-Type: text/javascript');
					break;
			}

			readfile($filename);
			die;
		}

		$this->fourohfour();
	}

} //class