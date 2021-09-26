<?php


/**
 * contains the base controller. app_controller is derived from this class, your controllers from the app_controller.
 *
 * Kata - Lightweight MVC Framework <http://www.codeninja.de/>
 * Copyright 2007-2015 mnt@codeninja.de
 *
 * Licensed under The GPL License
 * Redistributions of files must retain the above copyright notice.
 * @package kata_controller
 */

/**
 * The controller itself. Is initialized by the dispatcher.
 * @package kata_controller
 */
abstract class Controller {
	/**
	 * which models to use. Array of Modelnames in CamelCase, eg. array('User','Ship')
	 * 
	 * @var array
	 */
	public $uses = array ();

	/**
	 * which helpers to use inside the view. Array of Helpernames in CamelCase, eg. array('Js','My'). html is always included.
	 *  
	 * @var array
	 */
	public $helpers = array (
		'html'
	);

	/**
	 * which components to use. Array of componentnames in CamelCase, eg. array('Locale','Session')
	 * 
	 * @var array
	 */
	public $components = array ();

	/**
	 * which views to cache and how long (in seconds, 0=infinite)
	 * Example: public $cache = array('index'=>300,'second'=>0)
	 *
	 * @var array
	 */
	public $cache = null;

	/**
	 * which layout to use. null means an empty layout, otherwise kata will look for views/layouts/default.thtml
	 * 
	 * @var string
	 */
	public $layout = 'default';

	/**
	 * if true render the view automatically (just after the controllers appropriate action was called). if false you have to do $this->render('myview') yourself.
	 * 
	 * @var boolean
	 */
	public $autoRender = true;

	/**
	 * holds params (get,post,controller,action) of this request
	 * 
	 * @var array
	 */
	public $params = null;

	/**
	 * holds the contents of the view+layout after it has been rendered (via render() or automatically if autoRender is true)
	 * 
	 * @var string
	 */
	public $output = '';

	/**
	 * holds an array of variables that are extracted as global variables into the view. add variables via set() or setRef()
	 * 
	 * @var array
	 */
	public $viewVars = array ();

	/**
	 * action of the controller that was originally called
	 * 
	 * @var string
	 */
	public $action;

	/**
	 * the absolute filesystem-path to the webroot
	 * 
	 * @var string
	 * @deprecated 01.01.2010
	 */
	public $webroot;

	/**
	 * the absolute base-path of kata, slash. append controllername and actionname to this and you have a full url
	 * 
	 * @var string
	 */
	public $basePath;

	/**
	 * the absolute url of kata, including http(s),path and last slash. append controllername and actionname to this and you have a full url
	 * 
	 * @var string
	 */
	public $base;

	/**
	 * string with a pagetitle to render to the current layout ($title_for_layout inside the view)
	 * 
	 * @var string
	 */
	public $pageTitle = '';

	/**
	 * which view-class to use to render the view
	 * 
	 * @var string
	 */
	public $view = 'View';

	/**
	 * placeholder for the instanciated view-class
	 * 
	 * @var object
	 */
	protected $viewClass = null;

	/**
	 * The default action of the controller if none
	 * is given.
	 */
	public $defaultAction = 'index';

	/**
	 * constructor. builds an array of all models, components and helpers this controller (including the ones of the appController) needs.
	 */
	function __construct() {
		if (substr(get_class($this), -10) != 'Controller') {
			throw new InvalidArgumentException('controller::__construct my classname does not end with "Controller"');
		}

		if (is_subclass_of($this, 'AppController')) {
			$appVars = get_class_vars('AppController');
			$this->view = $appVars['view'];
			$uses = $appVars['uses'];
			$merge = array (
				'components',
				'helpers'
			);

			if (!empty ($this->uses) && ($uses == $this->uses)) {
				//array_unshift($this->uses, $this->modelClass);
			}
			elseif (!empty ($this->uses)) {
				$merge[] = 'uses';
			}

			foreach ($merge as $var) {
				if (isset ($appVars[$var]) && !empty ($appVars[$var]) && is_array($this-> {
					$var })) {
					$this-> {
						$var }
					= array_merge($this-> {
						$var }, array_diff($appVars[$var], $this-> {
						$var }));
				}
			}
		}
	}

	/*
		function __call($string,$args) {
			echo "calling $string with ".count($args)." args";
		}
	
		function __get($name) {
			if (isset($this->$name)) { return $this->$name; }
			echo "getting $name";
		}
	
		function __set($name,$value) {
			if (isset($this->$name)) { $this->$name=$value; }
			echo "setting $name	to $value";
		}
	*/

	/**
	 * loads all models this controller needs
	 */
	function _constructClasses() {
		if (!is_array($this->uses)) {
			throw new InvalidArgumentException('$uses must be an array');
		}
		require_once LIB.'model.php';
		foreach ($this->uses as $u) {
			$this-> $u = getModel($u);
		}
		$this->_constructComponents($this);
	}

	/**
	 * loads all components this controller needs, even late
	 */
	function _constructComponents() {
			if (!is_array($this->components)) {
				throw new InvalidArgumentException('$components must be an array');
			}
			require_once LIB.'component.php';
			foreach ($this->components as $comname) {
				$classname = $comname . 'Component';
				if (!isset($this->$comname)) {
					$this-> $comname = classRegistry :: getObject($classname);
					$this-> $comname->startup($this);
				}
			}
	}

	/**
	 * call the actual action of this controller and render it
	 */
	function dispatch() {
		try {
			call_user_func_array(array (
				$this,
				$this->params['action']
			), $this->params['pass']);
		} catch (Exception $e) {
			$this->afterError($e);
		}
	}

	/**
	 * render the given view with the given layout and put the result in the output-property of this controller. this just calls renderView which does the real work.
	 * 
	 * @param string $action name of the view (without .thtml) to render
	 * @param string $layout optional: name of the layout(-view) to render the view into. if false the default layout of this controller is taken
	 */
	function render($action, $layout = false) {
		$this->output = $this->renderView($action, $layout);
	}

	/**
	 * include and instanciate view-class
	 */
	protected function initViewClass() {
		if ($this->viewClass === null) {
			$viewClassName = $this->view;
			require_once LIB.strtolower($viewClassName).'.php';
			$this->viewClass = new $viewClassName ($this);
		}
	}

	/**
	 * render the given view with the given layout and put the result in the output-property of this controller.
	 * 
	 * @param string $action name of the view (without .php) to render
	 * @param string $layout optional: name of the layout(-view) to render the view into. if false the default layout of this controller is taken
	 */
	function & renderView($action, $layout = null) {
		$this->initViewClass();

		$this->autoRender = false;
		$this->beforeFilter();
		if ($layout === false) {
			$layout = $this->layout;
		}
		$html = $this->viewClass->render($action, $layout);
		$this->afterFilter();

		return $html;
	}

	/**
	 * render the given view with the given layout and put the result in the output-property of this controller.
	 * 
	 * @param string $html raw html
	 * @param string $layout optional: name of the layout(-view) to render the view into. if false the default layout of this controller is taken
	 */
	function & renderCachedHtml($html, $layout = null) {
		$this->initViewClass();

		if ($layout === false) {
			$layout = $this->layout;
		}
		$this->autoRender = false;
		// call no before/afterFilter because the whole view is cached and needs no data

		$html = $this->viewClass->renderLayout($html, $layout);
		return $html;
	}

	/**
	 * redirect to the given url. if relative the base-url to the framework is automatically added.
	 * 
	 * @param string $url to redirect to
	 * @param int $status http status-code to use for redirection (default 303=get the new url via GET even if this page was reached via POST)
	 * @param bool $die if we should die() after redirect (default: true);
	 */
	function redirect($url, $status = null, $die = true) {
		if (!is_numeric($status) || ($status < 100) || ($status > 505)) {
			$status = 303;
		}

		$this->autoRender = false;
		if (function_exists('session_write_close')) {
			session_write_close();
		}

		$pos = strpos($url, '://');
		if ($pos === false) { // is relative url, construct rest
			$url = $this->base . $url;
		}

		header('HTTP/1.1 ' . $status);
		header('Location: ' . $url);
		if ($die) {
			if ((DEBUG < 1) && headers_sent()) {
				echo '<html><head><title>Redirect</title>' .
				'<meta http-equiv="refresh" content="1; url=' . $url . '">' .
				'<meta name="robots" content="noindex" /><meta http-equiv="cache-control" content="no-cache" /><meta http-equiv="pragma" content="no-cache" />' .
				'</head>' .
				'<body>Redirect to <a href="' . $url . '">' . $url . '</a></body>' .
				'<script type="text/javascript">window.setTimeout(\'document.location.href="' . $url . '";\',1100);</script>';
				'</html>';
			}
			die;
		}
	}

	/**
	 * set the pagetitle for the current layout
	 * 
	 * @param string $n title
	 */
	function setPageTitle($n) {
		$this->pageTitle = $n;
	}

	/**
	 * get the pagetitle for the current layout
	 * 
	 * @return string current title
	 */
	function getPageTitle() {
		return $this->pageTitle;
	}

	/**
	 * set a variable to be available inside the view. the given name-string is the name of the global variable inside the view ('bla' => $bla)
	 * 
	 * @param string $name name of the variable that should be globally accessible inside the view
	 * @param mixed $value contents of the variable
	 **/
	function set($name, $value = null) {
		if ($name == 'title') {
			$this->setPageTitle($value);
		} else {
			$this->viewVars[$name] = $value;
		}
	}

	/**
	 * like set, but assignes the variable by reference
	 * 
	 * @param string $name name of the variable that should be globally accessible inside the view
	 * @param mixed $value contents of the variable
	 */
	function setRef($name, & $value) {
		if ($name == 'title') {
			$this->setPageTitle($value);
		} else {
			$this->viewVars[$name] = $value;
		}
	}

	/**
	 * Get a variable that's available inside the view.
	 * The given name-string is the name of the global variable
	 * inside the view.
	 *
	 * @param string $name name of the variable
	 * @return mixed
	 */
	function get($name) {
		if ($name == 'title') {
			return $this->getPageTitle();
		}
		if (isset ($this->viewVars[$name])) {
			return $this->viewVars[$name];
		}
		return null;
	}

	/**
	 * late-add a helper, just for this view. use if you dont want to add an 
	 * helper via $helpers because only 1 view uses the helper
	 * 
	 * @param string $name name of the helper ('Form')
	 */
	function addHelper($name) {
		if (!in_array($name, $this->helpers)) {
			$this->helpers[] = $name;
		}
	}

	/**
	 * late-load a component
	 * @param string $name name of the component to add (without 'Component')
	 */
	function addComponent($name) {
		if (!in_array($name,$this->components)) {
			$this->components[] = $name;
			$this->_constructComponents();
		}
	}

	/**
	 * shortcut to writeLog
	 * 
	 * @param string $what what text to log
	 * @param int $where errorlevel to log 
	 */
	function log($what, $where) {
		writeLog($what, $where);
	}

	/**
	 * deprecated. use beforeAction() everywhere
	 * 
	 * @deprecated 07.01.2009
	 */
	function beforeRender() {
	}

	/**
	 * call this after the controller has been initialized (read: models, components etc contructed) and we are about to call the myaction() method of the controller
	 */
	function beforeAction() {
		$this->beforeRender();
	}

	/**
	 * Called just before the view is rendered. Is never called if autoRender is false
	 */
	function beforeFilter() {
	}

	/**
	 * Called just after the view was rendered. Is never called if autoRender is false. Can be used to manipulate the views contents in the controllers output-property
	 */
	function afterFilter() {
	}

	/**
	 * renders 404-layout if you return true. you can also call+render a different action if you like
	 * @return bool render 404?
	 */
	function before404() {
		return true;
	}

	/**
	* default exception handler. if you dont overwrite it just rethrows the exception 
	* @param object $exception previously occured exception
	*/
	function afterError($exception) {
		throw $exception;
	}

	/**
	 * translate get/post or url/form to string suiteable for $this->params
	 * @access private
	 */
	private function normalizeMethod($method) {
		$method = strtoupper($method);
		if (($method == 'GET') || ($method == 'URL')) {
			return 'url';
		}
		if (($method == 'POST') || ($method == 'FORM')) {
			return 'form';
		}
		throw new InvalidArgumentException("'method' can only be 'get' or 'post'");
	}

	/**
	 * validate form-helper results, against model if needed
	 * 
	 * @param array $fields key(inputname)-value(validationparam) array of form-variables to validate
	 * @return bool true if everything validated successful
	 */
	function validate($method, $how) {
		$method = $this->normalizeMethod($method);
		if (empty ($this->params[$method])) {
			return array ();
		}

		$validateUtil = getUtil('Validate');
		$result = $validateUtil->checkAll($how, $this->params[$method]);
		$this->set('__validateErrors', $result);
		return $result;
	}

	function validateModel($method, $modelname) {
		$method = $this->normalizeMethod($method);

		$modelname = strtolower($modelname);
		if (empty ($this->params[$method][$modelname])) {
			return array ();
		}

		$validateUtil = getUtil('Validate');
		$result = $validateUtil->checkAllWithModel($modelname, $this->params[$method][$modelname]);
		$this->set('__validateErrors', array (
			$modelname => $result
		));
		return $result;
	}

}

