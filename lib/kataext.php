<?php
/**
 * Add normal callable methods to this class at runtime
 *
 * Kata - Lightweight MVC Framework <http://www.codeninja.de/>
 * Copyright 2007-2015 mnt@codeninja.de
 *
 * Licensed under The GPL License
 * Redistributions of files must retain the above copyright notice.
 * @package kata_internal
 */

/**
 * you can freely add methods to this class at runtime
 *
 * <code>Example:
 * class Foo extends kataExt;
 * $foo = new Foo;
 * Foo->_('bla',function(){echo'bla';})->_('blubb',function(){echo'blubb'));
 * </code>
 *
 * @package kata_internal
 */
class kataExt {

	/**
	 * stores all added methods
	 * @var array
	 */
    private $__addedMethods = array();

    public function __call($name, $args) {
        $class = get_class($this);
        do {
            if (array_key_exists($class, $this->__addedMethods)
                    && array_key_exists($name, $this->__addedMethods[$class]))
                break;

            $class = get_parent_class($class);
        } while ($class !== false);

        if ($class === false)
            throw new Exception("Method not found");

        $func = $this->__addedMethods[$class][$name];
        array_unshift($args, $this);

        return call_user_func_array($func, $args);
    }

    public function _($methodName, $method) {
        $class = get_called_class();
        if (!array_key_exists($class, $this->__addedMethods))
            $this->__addedMethods[$class] = array();

        $this->__addedMethods[$class][$methodName] = $method;

		return $this;
    }

}