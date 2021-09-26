<?php

/**
 * use other models inside models just like inside controllers. extends appmodel.
 *
 * Kata - Lightweight MVC Framework <http://www.codeninja.de/>
 * Copyright 2007-2015 mnt@codeninja.de
 *
 * Licensed under The GPL License
 * Redistributions of files must retain the above copyright notice.
 * @package kata_controller
 */

/**
 * use other models inside models just like inside controllers
 *
 * Example:
 * <code>
 * public $uses = 'Foo';
 *
 * function myfunction() {
 *    echo $this->Foo->read();
 * }
 * </code>
 * @package kata_model
 */

class ModelLoaderModel extends AppModel {

	public $uses = array();

	public function __get($name) {
		if (!is_array($this->uses)) {
			throw new InvalidArgumentException('uses needs to be an array');
		}
		if (!in_array($name, $this->uses)) {
			throw new InvalidArgumentException("Model $name is not in uses class of this model");
		}

		$mdl = getModel($name);
		$this->$name = $mdl;
		return $mdl;
	}
	
}