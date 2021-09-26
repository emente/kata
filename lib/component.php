<?
/**
 * @package kata
 */

/**
 * base component class
 * @package kata_component
 */
abstract class Component {

	/**
	 * startup method
	 * @param Controller $controller parent controller
	 */
	function startup($controller) {
		$this->controller = $controller;
	}

}