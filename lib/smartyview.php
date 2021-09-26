<?php


/**
 * Contains the class that is used to render views via smarty (layout,views,elements)
 *
 * Kata - Lightweight MVC Framework <http://www.codeninja.de/>
 * Copyright 2007-2015 mnt@codeninja.de
 *
 * Licensed under The GPL License
 * Redistributions of files must retain the above copyright notice.
 * @package kata_view
 */

/**
 * smarty view class. used to render the view (and layout) for the controller. ALPHAish.
 * to use put the following in your controller:
 * <code>
 * public $view = 'SmartyView';
 * </code>
 *
 * @package kata_view
 */
class SmartyView extends View {

	/**
	 * hold smarty-object
	 *
	 * @var object
	 */
	protected $smarty = null;

	/**
	 * constructor. copies all needed variables from the controller
	 * @param object controller that uses this view
	 */
	function __construct(& $controller) {
		/**
		 * include smarty. a smarty-installation must be in your include path!
		 */
		require_once 'Smarty.class.php';
		
		parent::__construct();

		$this->smarty = new Smarty;
		$this->smarty->register_function('helper', array (
				'SmartyView',
				'smarty_helper'
		));
	}

	/**
	 * allow smarty to use helpers
	 */
	function smarty_helper($params, & $smarty) {
		if (empty ($params['name'])) {
			throw new InvalidParameterException('smarty: {helper} needs name=');
		}
		if (!isset ($this->helperClasses[$params['name']])) {
			throw new InvalidParameterException('smarty: ' . $params['name'] . '-helper not found');
		}
		$helpername = $params['name'];
		unset ($params['name']);
		return call_user_func_array($this->helperClasses[$helpername], $params);
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

		$this->constructHelpers();
		$this->smarty->assign('params', $this->controller->params);
		$this->smarty->assign($this->passedVars);
		$this->smarty->assign($this->controller->viewVars);

		$viewfile = ROOT . 'views' . DS . strtolower(substr(get_class($this->controller), 0, -10)) . DS . $action . ".tpl";
		if (!file_exists($viewfile)) {
			throw new Exception('Cant find template [' . $this->action . '] of controller [' . get_class($this->controller) . '], Path is [' . $viewfile . ']');
		}
		return $this->smarty->fetch($viewfile);
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
		$this->layoutName = $layout;
		if ($this->layoutName !== null) {
			$this->constructHelpers();

			$this->smarty->assign('content_for_layout', $contentForLayout);
			$this->smarty->assign('title_for_layout', $this->controller->pageTitle);
			$this->smarty->assign('params', $this->controller->params);
			$this->smarty->assign($this->controller->viewVars);

			$layoutfile = ROOT . 'views' . DS . 'layouts' . DS . $this->layoutName . '.tpl';
			if (!file_exists($layoutfile)) {
				throw new Exception('Cant find layout template [' . $this->layoutName . ']');
			}
			return $this->smarty->fetch($layoutfile);
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
		$this->elementName = $name;
		$this->smarty->assign($this->controller->viewVars);
		$this->smarty->assign($params);

		$elemfile = ROOT . 'views' . DS . 'elements' . DS . $this->elementName . ".thtml";
		if (!file_exists($elemfile)) {
			throw new Exception('Cant find element [' . $this->elementName . ']');
		}
		return $this->smarty->fetch($elemfile);
	}

}