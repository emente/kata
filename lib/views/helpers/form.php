<?php
/**
 * Contains the form helper
 *
 * Kata - Lightweight MVC Framework <http://www.codeninja.de/>
 * Copyright 2007-2015 mnt@codeninja.de
 *
 * Licensed under The GPL License
 * Redistributions of files must retain the above copyright notice.
 * @package kata_helper
 */

/**
 * form helper for super-easy form handling
 * @package kata_helper
 */
class FormHelper extends Helper {

	/**
	 * which model to use. needed to format name-portion of tag accordingly
	 * 
	 * @var string
	 */
	private $modelName = '';

	/**
	 * which submit-method we use, used to access $this->params
	 */
	private $method = 'get';

	/**
	 * did we already open a form-tag?
	 * 
	 * @var bool
	 */
	private $formTagOpen = false;

	/**
	 * throw error if form still open
	 */
	function __destruct() {
		if ($this->formTagOpen) {
			//cant throw exception here, no stackframe
			trigger_error('formhelper: form not closed', E_USER_ERROR);
		}
	}

	/**
	 * throw error if form not open
	 */
	private function checkForOpenForm() {
		if (!$this->formTagOpen) {
			throw new Exception('formhelper: form not opened, cant close');
		}
	}

	/**
	 * create a opening form-tag for GET requests
	 * 
	 * @param string $url url to GET to
	 * @param mixed $htmlAttributes key-value array of html-attributes OR preformatted string ('readonly="true"') OR empty
	 * @param mixed $modelName string of model to use OR empty
	 * @return string html 
	 */
	function get($url=null, $htmlAttributes = null, $modelName = '') {
		if (empty($url)) {
			$url = $this->base.$this->params['controller'].'/'.$this->params['action'];
		}
		if ($this->formTagOpen) {
			throw new Exception('formhelper: form already opened, cant open again');
		}
		$this->formTagOpen = true;
		$this->method = 'url';
		$this->modelName = strtolower($modelName);
		return sprintf($this->tags['formstart'], 'get', $this->url($url), $this->parseAttributes($htmlAttributes));
	}

	/**
	 * create a opening form-tag for POST requests
	 * 
	 * @param string $url url to POST to
	 * @param mixed $htmlAttributes key-value array of html-attributes OR preformatted string ('readonly="true"') OR empty
	 * @param mixed $modelName string of model to use OR empty
	 * @return string html 
	 */
	function post($url=null, $htmlAttributes = null, $modelName = '') {
		if (empty($url)) {
			$url = $this->base.$this->params['controller'].'/'.$this->params['action'];
		}
		if ($this->formTagOpen) {
			throw new Exception('formhelper: form already opened, cant open again');
		}
		$this->formTagOpen = true;
		$this->method = 'form';
		$this->modelName = strtolower($modelName);
		return sprintf($this->tags['formstart'], 'post', $this->url($url), $this->parseAttributes($htmlAttributes));
	}

	/**
	 * close previously openend form-tag
	 * @return string html 
	 */
	function close() {
		$this->checkForOpenForm();
		$this->formTagOpen = false;
		return sprintf($this->tags['formend']);
	}

	/**
	 * return error message if the previously displayed form had any validation errors. obviously only works if you did a validate inside your models action.
	 * 
	 * @param string $name name-portion of input-tag
	 * @param string $errorString string to display on error
	 * @return string empty string if no error, otherwise errorstring inside configured error-tag-template
	 */
	function error($name, $errorString) {
		$this->checkForOpenForm();
		if (empty ($this->modelName)) {
			if (isset ($this->vars['__validateErrors'][$name])) {
				return sprintf($this->tags['formerror'], $errorString);
			}
			return '';
		}
		if (isset ($this->vars['__validateErrors'][$this->modelName][$name])) {
			return sprintf($this->tags['formerror'], $errorString);
		}
		return '';
	}

	/**
	 * create name-portion of tag, depends on wether we use a model or not:
	 * if no model is used simply returns name, otherwise returns 'model[name]'
	 * 
	 * @param mixed $name name-portion of the input tag
	 * @return string html 
	 */
	function fieldName($name) {
		if (empty ($this->modelName)) {
			return sprintf('%s', $name);
		}
		return sprintf('%s[%s]', $this->modelName, $name);
	}

	/**
	 * overwrite given (referenced) value with value from get/post, if existing
	 * 
	 * @param string $name name-portion of tag
	 * @param string $value value to manipulate (referenced) 
	 * @return string html 
	 */
	function setDefault($name, & $value) {
		if (empty ($this->modelName)) {
			if (isset ($this->params[$this->method][$this->fieldName($name)])) {
				$value = h($this->params[$this->method][$this->fieldName($name)]);
			}
			return;
		}
		if (isset ($this->params[$this->method][$this->modelName][$name])) {
			$value = h($this->params[$this->method][$this->modelName][$name]);
		}
	}

	/**
	 * generate input-tag
	 * 
	 * @param string $name name of the input-tag
	 * @param string $value default value of the input tag. will be overwritten if we land here again after the request  
	 * @param array $htmlAttributes tag-attributes
	 * @return string html 
	 */
	function input($name, $value = '', $htmlAttributes = null) {
		$this->checkForOpenForm();
		$type = 'text';
		if (!empty ($htmlAttributes['type'])) {
			$type = $htmlAttributes['type'];
			unset ($htmlAttributes['type']);
		}
		$this->setDefault($name, $value);

		return sprintf($this->tags['input'], $this->fieldName($name), $value, $type, $this->parseAttributes($htmlAttributes));
	}

	/**
	 * generate checkbox. will generate a value (0) even if unchecked, no more javascript fiddeling!
	 * 
	 * @param string $name name of the input-tag
	 * @param bool $checked if checkbox should be checked or not. will be overwritten if we land here again after the request  
	 * @param array $htmlAttributes tag-attributes
	 * @return string html 
	 */
	function checkbox($name, $checked = false, $htmlAttributes = null) {
		$this->checkForOpenForm();
		$this->setDefault($name, $checked);

		return sprintf($this->tags['checkbox'], $this->fieldName($name), $this->fieldName($name), $checked ? 'checked="checked"' : '', $this->parseAttributes($htmlAttributes));
	}

	/**
	 * generate textarea-tag
	 * 
	 * @param string $name name of the input-tag
	 * @param string $value default value of the textarea tag. will be overwritten if we land here again after the request  
	 * @param array $htmlAttributes tag-attributes
	 * @return string html 
	 */
	function textarea($name, $value = '', $htmlAttributes = null) {
		$this->checkForOpenForm();
		$this->setDefault($name, $value);

		return sprintf($this->tags['textarea'], $this->fieldName($name), $this->parseAttributes($htmlAttributes), $value);
	}

	/**
	 * build select/option tags
	 * 
	 * <samp>
	 * $arr = array('blue'=>'Blue color','red'=>'Red color');
	 * echo $html->selectTag('gameinput',$arr,'red');
	 * </samp>
	 * 
	 * @param string $fieldName name-part of the select-tag
	 * @param array $optionElements array with elements (key=option-tags value-part, name=between option tag)
	 * @param string $selected keyname of the element to be default selected
	 * @param array $selectAttr array of attributes of the select-tag, for example "class"=>"dontunderline"
	 * @param array $optionAttr array of attributes for each option-tag, for example "class"=>"dontunderline"
	 * @param boolean $showEmpty if we should display an empty option as the default selection so the user knows (s)he has to select something
	 * @return string html
	 */
	function select($name, $optionElements, $selected = null, $selectAttr = array (), $optionAttr = array (), $showEmpty = false) {
		$this->checkForOpenForm();
		$this->setDefault($name, $selected);

		$select = '';
		if (!is_array($optionElements)) {
			return '';
		}
		if (isset ($selectAttr) && array_key_exists("multiple", $selectAttr)) {
			$select .= sprintf($this->tags['selectmultiplestart'], $this->fieldName($name), $this->parseAttributes($selectAttr));
		} else {
			$select .= sprintf($this->tags['selectstart'], $this->fieldName($name), $this->parseAttributes($selectAttr));
		}
		if ($showEmpty == true) {
			$select .= sprintf($this->tags['selectempty'], $this->parseAttributes($optionAttr));
		}
		foreach ($optionElements as $optname => $title) {
			$optionsHere = $optionAttr;

			if (($selected != null) && ((string)$selected == (string)$optname)) {
				$optionsHere['selected'] = 'selected';
			}
			elseif (is_array($selected) && in_array($optname, $selected)) {
				$optionsHere['selected'] = 'selected';
			}

			$select .= sprintf($this->tags['selectoption'], $optname, $this->parseAttributes($optionsHere), h($title));
		}

		$select .= sprintf($this->tags['selectend']);
		return $select;
	}

	/**
	 * generate button-tag
	 * 
	 * @param string $name name of button
	 * @param string $value value of button
	 * @param array $htmlAttributes tag-attributes
	 * @param array $html html to insert inside button
	 * @return string html 
	 */
	function button($name, $value = '', $htmlAttributes = null, $html = '', $escapeHtml = false) {
		$this->checkForOpenForm();
		if ($escapeHtml) {
			$html = h($html);
		}
		return sprintf($this->tags['button'], $name, $value, $this->parseAttributes($htmlAttributes), $html);
	}

	/**
	 * generate submit-button
	 * 
	 * @param string $title title of button
	 * @param array $htmlAttributes tag-attributes
	 * @return string html 
	 */
	function submit($title, $htmlAttributes = null) {
		$this->checkForOpenForm();
		return sprintf($this->tags['submit'], $title, $this->parseAttributes($htmlAttributes));
	}

	/**
	 * generate reset-button
	 * 
	 * @param string $title title of button
	 * @param array $htmlAttributes tag-attributes
	 * @return string html 
	 */
	function reset($title, $htmlAttributes = null) {
		$this->checkForOpenForm();
		return sprintf($this->tags['reset'], $title, $this->parseAttributes($htmlAttributes));
	}

}