<?php
/**
 * Contains kataImmutable
 *
 * Kata - Lightweight MVC Framework <http://www.codeninja.de/>
 * Copyright 2007-2015 mnt@codeninja.de
 *
 * Licensed under The GPL License
 * Redistributions of files must retain the above copyright notice.
 * @package kata_internal
 */

/**
 * todo
 * @package kata_internal
 */
class kataImmutable {

	private $_isImmutable = false;

	public function __construct(array $items) {
		foreach ($items as $k => $v) {
			$this->$k = $v;
		}
		$this->_isImmutable = true;
	}

	final public function __set($name, $value) {
		if (!$this->_isImmutable) {
			$this->$name = $value;
		}
	}

}
