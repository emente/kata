<?php

/**
 * several convenience defines and functions
 *
 * Kata - Lightweight MVC Framework <http://www.codeninja.de/>
 * Copyright 2007-2015 mnt@codeninja.de
 *
 * Licensed under The GPL License
 * Redistributions of files must retain the above copyright notice.
 * 
 * @author mnt
 * @package kata_internal
 */
/**
 * internal function to dump variables into the browser if DEBUG==2. just define your own function if you want firebug or something like it
 */
if (!function_exists('debug')) {

    /**
     * print out type and content of the given variable if DEBUG-define (in config/core.php) > 0
     * @param mixed $var     Variable to debug
     * @param mixed $deprecated  deprecated
     */
    function debug($var = false, $deprecated = false) {
        if (DEBUG < 1) {
            return;
        }

        ob_start();
        if (function_exists('xdebug_var_dump')) {
            xdebug_var_dump($var);
        } else {
            var_dump($var);
        }
        $var = ob_get_clean();

        kataDebugOutput($var);
    }

}

/**
 * Recursively strips slashes from all values in an array
 * @param mixed $value
 * @return mixed
 */
function stripslashes_deep($value) {
    if (is_array($value)) {
        return array_map('stripslashes_deep', $value);
    } else {
        return stripslashes($value);
    }
}

/**
 * Recursively urldecodes all values in an array
 * @param mixed $value
 * @return mixed
 */
function urldecode_deep($value) {
    if (is_array($value)) {
        return array_map('urldecode_deep', $value);
    } else {
        return urldecode($value);
    }
}

/**
 * write a string to the log in KATATMP/logs. 
 * if DEBUG<0 logentries that have $where==0 are not written.
 *
 * @param string $what string to write to the log
 * @param int $where log-level to log (default: 0)
 */
function writeLog($what, $where = 2) {
    $logname = 'error';
    if (is_numeric($where)) {
        if ((DEBUG < 0) && (2 == $where)) {
            return;
        }
        if (2 == $where) {
            $logname = 'debug';
        } elseif (0 == $where) {
            $logname = 'panic';
        }
    } else {
        $logname = basename($where);
    }

    kataMakeTmpPath('logs');
    $h = fopen(KATATMP . 'logs' . DS . $logname . '.log', 'a');
    if ($h) {
        fwrite($h, date('d.m.Y H:i:s ') . $what . "\n");
        fclose($h);
    }
}

/**
 * include all neccessary classes and the given model
 * 
 * @param string model name without .php
 * @package kata_model
 */
function loadModel($name) {
    require_once ROOT . 'models' . DS . strtolower($name) . '.php';
}

/**
 * return a handle to the given model. loads and initializes the model if needed. 
 * You always get the same object, singleton-alike.
 * 
 * @param string $value model name (without .php)
 * @return object
 */
function getModel($name) {
    if (!class_exists($name)) {
        loadModel($name);
    }
    $o = classRegistry :: getObject($name);
    return $o;
}

/**
 * return class-handle of a utility-class
 * You always get the same object, singleton-alike.
 *
 * @param string $name name of the utility
 * @return object class-handle
 */
function getUtil($name) {
    $classname = $name . 'Utility';
    return classRegistry :: getObject($classname);
}

/**
 * Gets an environment variable from available sources.
 * env() knows what to do if $_SERVER/$_ENV are not available.
 *
 * @param  string $key Environment variable name.
 * @return string Environment variable setting.
 */
function env($key) {
    if ($key == 'HTTPS') {
        if (isset($_SERVER) && !empty($_SERVER)) {
            return (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on');
        } else {
            return (strpos(env('SCRIPT_URI'), 'https://') === 0);
        }
    }

    if (isset($_SERVER[$key])) {
        return $_SERVER[$key];
    } elseif (isset($_ENV[$key])) {
        return $_ENV[$key];
    } elseif (getenv($key) !== false) {
        return getenv($key);
    }

    // fallback
    if ($key == 'DOCUMENT_ROOT') {
        $offset = 0;
        if (!strpos(env('SCRIPT_NAME'), '.php')) {
            $offset = 4;
        }
        return substr(env('SCRIPT_FILENAME'), 0, strlen(env('SCRIPT_FILENAME')) - (strlen(env('SCRIPT_NAME')) + $offset));
    }

    // fallback
    if ($key == 'PHP_SELF') {
        return str_replace(env('DOCUMENT_ROOT'), '', env('SCRIPT_FILENAME'));
    }

    return null;
}

/**
 * merge any number of arrays
 * @param array first array
 * @param array second array and so on
 * @return array the merged array
 */
function am() {
    $result = array();
    foreach (func_get_args() as $arg) {
        if (!is_array($arg)) {
            $arg = array(
                $arg
            );
        }
        $result = array_merge($result, $arg);
    }
    return $result;
}

/**
 * loads the given files in the VENDORS directory if not already loaded
 * @param string $name Filename without the .php part.
 */
function vendor($name) {
    $args = func_get_args();
    foreach ($args as $arg) {
        require_once (ROOT . 'vendors' . DS . $arg . '.php');
    }
}

/**
 * Convenience method for htmlspecialchars. you should use this instead of echo to avoid xss-exploits
 * @param string $text
 * @return string
 */
function h($text) {
    if (is_array($text)) {
        return array_map('h', $text);
    }
    return htmlspecialchars($text);
}

/**
 * convenience method to check if given value is set. if so, value is return, otherwise the default
 * @param mixed $arg value to check
 * @param mixed $default value returned if $value is unset
 */
function is(& $arg, $default = null) {
    if (isset($arg)) {
        return $arg;
    }
    return $default;
}
