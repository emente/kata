<?php

/**
 * @package kata
 */

/**
 * check if an array of parameters matches certain criterias.
 * you can still use the (deprecated) defines of model.php (until i murder you)
 *
 * @package kata_utility
 * @author mnt@codeninja.de
 */
class ValidateUtility {

	/**
	 * checks the given values of the array match certain criterias
	 *
	 * <code>
	 * check(array(
	 * 'email'=>'INT'
	 * ),$this->params['form'])
	 * </code>
	 *
	 * @param array $how key/value pair. key is the name of the key inside the $what-array, value is a rule, the name of a function that is given the string (should return bool wether the string validates)
	 * @param array $params the actual data as name=>value array (eg. $this->params['form'])
	 * @return true if data validates OR $params-key, for example 'EMAIL' if no correct email given OR false if $params is empty
	 */
	function check($how, $params) {
		$result = $this->checkAll($how, $params);

		if (!is_array($result))
			return $result;
		if (is_array($result) && (count($result) == 0))
			return false;
		return array_shift($result);
	}

	/**
	 * checks the given values of the array match certain criterias
	 *
	 * <code>
	 * check(array(
	 * 'email'=>'INT'
	 * ),$this->params['form'])
	 * </code>
	 *
	 * @param array $how key/value pair. key is the name of the key inside the $what-array, value is a rule, the name of a function that is given the string (should return bool wether the string validates)
	 * @param array $params the actual data as name=>value array (eg. $this->params['form'])
	 * @return true if data validates OR $params-key, for example 'EMAIL' if no correct email given OR array() if $params is empty
	 */
	function checkAll($how, $params) {
		if (!is_array($how)) {
			throw new InvalidArgumentException("validateutil: 'how' should be an array");
		}
		if (!is_array($params) || empty ($params)) {
			return array ();
		}

		$errors = array ();
		foreach ($how as $inpname => $howname) {
			// be compatible with old validate
			if (substr($howname, 0, 6) == 'VALID_') {
				$howname = substr($howname, 6);
			}

			$inpvalue = is($params[$inpname], '');
			switch (strtoupper($howname)) {
				case 'NOT_EMPTY' :
					if (empty ($inpvalue))
						$errors[$inpname] = $howname;
					break;
				case 'ALLOW_EMPTY' :
					break;
				case 'ALLOW_MULTI' :
					if (!is_array($inpvalue) || (count($inpvalue) == 0))
						$errors[$inpname] = $howname;
					break;
				case 'ALLOW_MULTI_EMPTY':
					break;
				case 'BOOL' :
					if ($inpvalue != '1' && $inpvalue != '0')
						$errors[$inpname] = $howname;
					break;
				case 'BOOL_LAZY' :
					if ($inpvalue != '1' && $inpvalue != '')
						$errors[$inpname] = $howname;
					break;
				case 'BOOL_TRUE' :
					if ($inpvalue != '1')
						$errors[$inpname] = $howname;
				break;
					case 'FLOAT' :
					if (!filter_var($inpvalue, FILTER_VALIDATE_FLOAT))
						$errors[$inpname] = $howname;
					break;
				case 'UINT' :
				case 'NUMBER' :
					if (!filter_var($inpvalue, FILTER_VALIDATE_INT, array (
							'options' => array (
								'min_range' => 0
							)
						)))
						$errors[$inpname] = $howname;
					break;
				case 'INT' :
					if (!filter_var($inpvalue, FILTER_VALIDATE_INT))
						$errors[$inpname] = $howname;
					break;
				case 'HEX' :
					if (!filter_var($inpvalue, FILTER_VALIDATE_INT || FILTER_FLAG_ALLOW_HEX))
						$errors[$inpname] = $howname;
					break;
				case 'EMAIL' :
					if (filter_var($inpvalue, FILTER_VALIDATE_EMAIL) === false)
						$errors[$inpname] = $howname;
					break;
				case 'IP' :
					if (!filter_var($inpvalue, FILTER_VALIDATE_IP))
						$errors[$inpname] = $howname;
					break;
				case 'URL' :
					if (!filter_var($inpvalue, FILTER_VALIDATE_URL))
						$errors[$inpname] = $howname;
					break;
				case 'YEAR' :
					if (!is_numeric($inpvalue) || ($inpvalue < 1900) || ($inpvalue > 2099))
						$errors[$inpname] = $howname;
				case is_callable($howname) :
					if (!call_user_func($howname, $inpname, $inpvalue))
						$errors[$inpname] = $howname;
					break;
				default :
					if (false === strpos($howname, '/')) {
						throw new InvalidArgumentException("validateutil: '$howname' is no rule, function or regex. maybe a typo?");
					}
					if (!filter_var($inpvalue, FILTER_VALIDATE_REGEXP, array( 'options' => array (
							'regexp' => $howname
						))))
						$errors[$inpname] = $howname;
					break;
			} //switch
		} //foreach

		if (empty ($errors)) {
			return true;
		}

		return $errors;
	}

	/**
	 * check the given parameter-array against the rows of a model
	 * 
	 * @param string $modelname name of the model to validate against
	 * @param array $params the actual data as name=>value array (eg. $this->params['form'])
	 * @return true if data validates OR $params-key, for example 'EMAIL' if no correct email given OR array() if $params is empty
	 */
	function checkAllWithModel($modelname, $params) {
		$model = getModel($modelname);
		$describe = $model->describe($model->getTableName(null, true, false));
		$valArray = array ();

		//remove primary key 
		if (isset ($describe['identity'])) {
			unset ($describe['cols'][$describe['identity']]);
		}

		foreach ($describe['cols'] as $colname => $coldata) {
			switch ($coldata['type']) {
				case 'int' :
					$valArray[$colname] = 'INT';
					break;
				case 'string' :
				case 'text' :
					$valArray[$colname] = 'NOT_EMPTY';
					break;
				case 'bool' :
					$valArray[$colname] = 'BOOL';
					break;
				case 'float' :
					$valArray[$colname] = 'FLOAT';
					break;
				default :
					//TODO support more stuff
					throw new InvalidArgumentException("validateModel: no support (yet) for " . $coldata['type']);
			}
		}

		return $this->checkAll($valArray, $params);
	}

}