<?php

/**
 * Contains the registry: can read/write configuration settings and persists them on disk
 *
 * Kata - Lightweight MVC Framework <http://www.codeninja.de/>
 * Copyright 2007-2015 mnt@codeninja.de
 *
 * Licensed under The GPL License
 * Redistributions of files must retain the above copyright notice.
 * @package kata_internal
 */

/**
 * registry, a configuration object that persists itself on disk.
 * split keys into individial sections with a dot. if you read a section an array will be returned with all keys in that section
 *
 * <code>
 * kataReg::set('my.stuff',1);
 * kataReg::set('my.foo',2);
 * var_dump(kataReg::get('my.stuff'); // =1
 * var_dump(kataReg::get('my')); // =array('my'=>array('stuff'=>1,'foo'=>2))
 * </code>
 * @package kata_internal
 */
class kataReg {

	/**
	 * array to save objects of any classed created
	 * @var array
	 */
	static protected $dataArr= array ();


/**
 * did we already load data from disk?
 * @var boolean
 */
	static protected $didLoadData= false;

/**
 * load data from disk and put it into $dataArr
 */
	static protected function loadData() {
		if (self :: $didLoadData)
			return;

		$file= KATATMP.'cache'.DS.CACHE_IDENTIFIER.'-kataReg';
		if (file_exists($file)) {
			$data= array ();
			include $file;
			self :: $dataArr= $data;
			self :: $didLoadData= true;
		}
	}

/**
 * save data to disk. throws an exception if writing failed and DEBUG>0
 */
	static protected function saveData() {
		kataMakeTmpPath('cache');
		$data = '<? $data='.var_export(self :: $dataArr,true).';';

		if (false === file_put_contents(KATATMP.'cache'.DS.CACHE_IDENTIFIER.'-kataReg', $data)) {
			if (DEBUG > 0) {
				throw new Exception('katareg: cannot write data. wrong rights?');
			}
			return false;
		}
		return true;
	}

/**
 * get variable from registry. use a dot to split key into individual sections
 * 
 * <samp>get('showShop',false)</samp>
 * 
 * @param string $id key to read
 * @param mixed $default default value to use if key is not yet in the registr
 * @return mixed value of key or default value (which is null)
 */
	static public function get($id, $default= null) {
		self :: loadData();

		$temp= explode('.', $id);
		if (count($temp) == 1) {
			if (isset (self :: $dataArr[$id])) {
				return self :: $dataArr[$id];
			}
		} else {
			$start = & self::$dataArr;
			foreach ($temp as $keyname) {
				if (isset($start[$keyname])) {
					$start= & $start[$keyname];
				} else {
					return $default;
				}
			}
			return $start;
		}
		return $default;
	}

/**
 * set variable inside registry. use a dot to split key into individual sections
 * @param string $id key to write
 * @param mixed $vars any value
 */
	static public function set($id, $vars) {
		self :: loadData();

		$temp= explode('.', $id);
		if (count($temp) == 1) {
			self :: $dataArr[$id]= $vars;
		} else {
			$start= & self :: $dataArr;
			foreach ($temp as $keyname) {
				$start= & $start[$keyname];
			}
			$start= $vars;
		}

		return self :: saveData();
	}

/**
 * remove variable inside registry. use a dot to split key into individual sections
 * @param string $id key to write
 */	
	static public function delete($id) {
		self :: loadData();
		
		$temp= explode('.', $id);
		if (count($temp) == 1) {
			unset(self :: $dataArr[$id]);
		} else {
			$start= & self :: $dataArr;
			foreach ($temp as $keyname) {
				$start= & $start[$keyname];
			}
			unset($start);
		}

		return self :: saveData();
	}

}