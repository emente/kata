<?php

/**
 * Contains the view-Class that is used to render view-templates (layout,views,elements)
 *
 * Kata - Lightweight MVC Framework <http://www.codeninja.de/>
 * Copyright 2007-2015 mnt@codeninja.de
 *
 * Licensed under The GPL License
 * Redistributions of files must retain the above copyright notice.
 * @package kata_view
 */

/**
 * default view class. used to render the view (and layout) for the controller
 * @package kata_view
 */
class View {
	/**
	 * controller class that the dispatcher called
	 * @var object
	 */
	protected $controller;

	/**
	 * array which holds all relevant information for the current view:
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
	 * name of the action of the current controller the dispatcher called
	 * @var string
	 */
	public $action;

	/**
	 * absolute filesystem path to the webroot folder
	 * @var string
	 * @deprecated 01.01.2010
	 */
	public $webroot;

	/**
	 * base url of this framework
	 * @var string
	 */
	public $base;

	/**
	 * array of helpers you can access inside the view via $this->helpername
	 * @var array
	 */
	public $helpers = array (
		"Html"
	);

	/**
	 * array that holds the actual instanciated classes of all constructed helpers for this view
	 */
	protected $helperClasses = array ();

	/**
	 * name of the element that is currently rendered inside the view via $this->renderElement
	 * @var string
	 */
	protected $elementName = '';

	/**
	 * name of the layout we are rendering to
	 * @var string
	 */
	protected $layoutName = '';

	/**
	 * used to stop us from accidently contructing helpers twice
	 * @var bool
	 */
	protected $didConstructHelpers = false;

	/**
	 * constructor. copies all needed variables from the controller
	 * @param object controller that uses this view
	 */
	function __construct(& $controller) {
		$this->controller = $controller;
		$this->params =  $controller->params;
		$this->action =  $controller->action;
		$this->webroot =  $controller->webroot;
		$this->helpers =  $controller->helpers;
		$this->base =  $controller->base;
		$this->basePath =  $controller->basePath;
	}

	/**
	 * construct all helpers we found in our helpers property
	 */
	protected function constructHelpers() {
		if ($this->didConstructHelpers) {
			return;
		}
		require_once LIB.'helper.php';

		foreach ($this->helpers as $name) {
			$name = strtolower($name);
			$classname = ucfirst(strtolower($name)) . 'Helper';
			$h = classRegistry :: getObject($classname);

			$h->webroot = $this->webroot;
			$h->action = $this->action;
			$h->params = $this->controller->params;
			$h->base = $this->base;
			$h->basePath = $this->basePath;
			$h->vars = $this->controller->viewVars;

			$this->helperClasses[$name] = $h;
		}

		$this->didConstructHelpers = true;
	}

	/**
	 * render the actual view and layout. normally done at the end of a action of a controller
	 * <code>
	 * 1. all helpers are constructed here, very late, so we dont accidently waste cpu cycles
	 * 2. all variables, helpers and params given from the controller are extracted to global namespace
	 * 3. the actual view-template is rendered
	 * 4. the content of the rendered view is rendered into the layout
	 * </code>
	 * @param string $action name of the view
	 * @param string $layout name of the layout
	 * @return string html of the view
	 */
	public function render($action, $layout) {
		$this->action = $action;
		$this->layoutName = $layout;

		$this->constructHelpers();
		extract(array (
			'params' => $this->controller->params
		));
		extract($this->controller->viewVars);
		extract($this->helperClasses);
		$GLOBALS['__THIS'] = $this;

		$viewfile = ROOT . 'views' . DS . strtolower(substr(get_class($this->controller), 0, -10)) . DS . $this->action . ".thtml";
		if ($this->action[0] == '.') {
			$viewfile = str_replace(DS,'/',$viewfile);
			do {
				$viewfile = preg_replace('/\w+\/\.\.\//', '', $viewfile, -1, $cnt);
			} while($cnt!=0);
			$viewfile = str_replace('/',DS,$viewfile);
		}
		
		ob_start();
		// well shirley... if you get a fatal error your view is missing ;)
		require $viewfile;
		return $this->renderLayout(ob_get_clean(), $this->layoutName);
	}

	/**
	 * renders the given string into the layout. normally called by renderView()
	 * <code>
	 * 1. title and content are extracted into global namespace
	 * 2. all variables, helpers and params given from the controller are extracted to global namespace
	 * 3. the given string is rendered into the layout
	 * 4. the html-output of this routine normally lands in the controllers output property
	 * </code>
	 * @param string $contentForLayout raw html to be rendered to the layout (normally the content of a view)
	 * @param string $layout name of the layout
	 */
	public function renderLayout($contentForLayout, $layout) {
		$this->layoutName = $layout; //in case $layout gets overwritten
		if ($this->layoutName !== null) {
			$this->constructHelpers();

			extract(array (
				'content_for_layout' => $contentForLayout,
				'title_for_layout' => $this->controller->pageTitle,
				'this' => $this
			));
			extract($this->controller->viewVars);
			extract($this->helperClasses);
			$GLOBALS['__THIS'] = $this;

			ob_start();

			$viewfile = ROOT . 'views' . DS . 'layouts' . DS . $this->layoutName . '.thtml';
			if ($this->layoutName[0] == '.') {
				$viewfile = str_replace(DS,'/',$viewfile);
				do {
					$viewfile = preg_replace('/\w+\/\.\.\//', '', $viewfile, -1, $cnt);
				} while($cnt!=0);
				$viewfile = str_replace('/',DS,$viewfile);
			} 


			// well shirley... if you get a fatal error your layout is missing ;)
			require $viewfile;
			return ob_get_clean();
		} else {
			return $contentForLayout;
		}
	}

	/**
	 * render a element and return the resulting html. an element is kind of like a mini-view you can use inside a view (via $this->renderElement()).
	 * it has (like a view) access to all variables a normal view has
	 * @param string $name name of the element (see views/elements/) without .thtml
	 * @param an array of variables the element can access in global namespace
	 */
	public function renderElement($name, $params = array ()) {
		$this->elementName = $name; //in case $name gets overwritten 
		extract($this->controller->viewVars);
		extract($params);
		extract($this->helperClasses);
		$GLOBALS['__THIS'] = $this;

		ob_start();
		require ROOT . 'views' . DS . 'elements' . DS . $this->elementName . '.thtml';
		return ob_get_clean();
	}

}

