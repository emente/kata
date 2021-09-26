<?php



/**
 * helper base-class. helpers are the classes you can access via $this->helpername inside a view
 * @package kata_helper
 */
abstract class Helper {

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
	 * placeholder for the tag-templates inside the config folder
	 * @param array
	 */
	public $tags = array (
		'link' => '<a href="%s" %s>%s</a>',
		'image' => '<img src="%s" %s/>',
		'selectstart' => '<select name="%s" %s>',
		'selectmultiplestart' => '<select name="%s[]" %s>',
		'selectempty' => '<option value="" %s></option>',
		'selectoption' => '<option value="%s" %s>%s</option>',
		'selectend' => '</select>',
		'cssfile' => '<link rel="stylesheet" type="text/css" href="%s" />',
		'jsfile' => '<script type="text/javascript" src="%s"></script>',
		'formstart' => '<form method="%s" action="%s" %s>',
		'formend' => '</form>',
		'formerror' => '<div class="formError">%s</div>',
		'input' => '<input name="%s" value="%s" type="%s" %s />',
		'checkbox' => '<input type="hidden" name="%s" value="0" /><input type="checkbox" name="%s" value="1" %s %s />',
		'textarea' => '<textarea name="%s" %s >%s</textarea>',
		'button' => '<button type="button" name="%s" value="%s" %s>%s</button>',
		'submit' => '<input type="submit" value="%s" %s />',
		'reset' => '<input type="reset" value="%s" %s />'
	);

	/**
	 * @var string complete url (including http) to the base of our framework
	 */
	public $base = null;

	/**
	 * @var string path to the base of our framework, sans http
	 */
	public $basePath = null;

	/**
	 * @var array view-vars
	 */
	public $vars = null;

	/**
	 * constructor, loads tags-templates from config folder
	 */
	function __construct() {
		if (file_exists(ROOT . 'config' . DS . 'tags.php')) {
			$tags = array ();
			require_once ROOT . 'config' . DS . 'tags.php';
			$this->tags = array_merge($this->tags, $tags);
		}
	}

	/**
	 * construct an relative url with the base url of the framework
	 * @param string $url url to expand
	 * @return string
	 */
	public function urlRel($url = null) {
		if (empty ($url)) {
			return $this->basePath;
		}
		if ($url[0] == '/') {
			return $this->basePath . substr($url, 1);
		}
		if (isset ($url[5]) && ($url[4] == ':' || $url[5] == ':')) {
			return $url;
		}

		if (defined('CDN_URL') && (DEBUG < 1)) {
			$ext = strtolower(substr($url, -4, 4));
			if (($ext == '.jpg') || ($ext == '.gif') || ($ext == '.png')) {
				return sprintf(CDN_URL, ord($url[0]) % 4) . $url;
			}
		}

		return $this->basePath . $url;
	}

	/**
	 * shortcut. this is what your are normally using everywhere inside a view
	 * @param string $url url to expand
	 * @return string
	 */
	public function url($url = null) {
		return $this->urlAbs($url);
	}

	/**
	 * construct an absolute url (including http(s)) with the base url of the framework. normally needed if you send a view via email and you need the http-part
	 * @param string $url url to expand
	 * @return string
	 */
	public function urlAbs($url = null) {
		if (empty ($url)) {
			return $this->base;
		}
		if ($url[0] == '/') {
			return $this->base . substr($url, 1);
		}
		if (isset ($url[5]) && ($url[4] == ':' || $url[5] == ':')) {
			return $url;
		}
		return $this->base . $url;
	}

	/**
	 * build an attribute-string of an html-tag out of an array
	 * @param array $options the name=>value pairs to append to the tag
	 * @param mixed $exlude null or array of attribute-names not to append (eg. when they are framework-parameters, not html-attributes)
	 * @param string $insertBefore string to prepand
	 * @param mixed $insertAfter string to append, or null if you want nothing appended
	 * @return string attributes as html
	 */
	public function parseAttributes($options, $exclude = null, $insertBefore = ' ', $insertAfter = '') {
		//maintain compatibility if options is string. ignore $exclude because it makes no sense in this case
		if (!is_array($options)) {
			return $options ? $insertBefore . $options . $insertAfter : '';
		}

		if (!is_array($exclude)) {
			//again, maintain compatibility
			if (is_string($exclude)) {
				$eclude = array($exclude);
			} else {
				$exclude = array();
			}
		}

		$escape = true;
		if (isset ($options['escape'])) {
			$escape = $options['escape'];
			unset ($options['escape']);
		}
		if (isset ($exclude['escape'])) {
			$escape = $exclude['escape'];
			unset ($exclude['escape']);
		}

		if (count($options) > 0) {
			$minimized = array (
				'compact' => 1,
				'checked' => 1,
				'declare' => 1,
				'readonly' => 1,
				'disabled' => 1,
				'selected' => 1,
				'defer' => 1,
				'ismap' => 1,
				'nohref' => 1,
				'noshade' => 1,
				'nowrap' => 1,
				'multiple' => 1,
				'noresize' => 1
			);
			$options = array_diff_key($options, array_flip($exclude));
			$optionsMinimized = array_intersect_key($options, $minimized);
			$options = array_diff_key($options, $optionsMinimized);
		} else {
			$optionsMinimized = array ();
		}

		$out = '';
		foreach ($options as $n => $v) {
			if ($escape) {
				$out .= $n . '="' . h($v) . '" ';
			} else {
				$out .= $n . '="' . $v . '" ';
			}
		}
		foreach ($optionsMinimized as $n => $v) {
			$out .= $n . '="' . $n . '" ';
		}

		return $out ? $insertBefore . $out . $insertAfter : '';
	}

	/**
	 * @deprecated 1.1 - 09.11.2008 not needed anymore. use url() instead
	 * @return string
	 */
	public function urlWebroot($url) {
		return $this->url($url);
	}

/**
 * output a url with __token get parameter appended. used for xsrf-detection
 *
 * @param string $url url to add __token to
 * @return string url with token appended
 */
	function tokenUrl($url) {
		$url = $this->url($url);
		$token = is($this->vars['__token'],'');
		if ('' == $token) {
			return $url;
		}

		$x = strpos($url,'?');
		if (false !== $x) {
			return substr($url,0,$x).'?__token='.$token.'&'.substr($url,$x+1);
		}

		$x = strpos($url,'#');
		if (false !== $x) {
			return substr($url,0,$x).'?__token='.$token.substr($url,$x+1);
		}

		return $url.'?__token='.$token;
	}

	/**
	 * returns initialized class of other helpers. you have to take care of initialization order!
	 *
	 * @param string $name name of helper you need access to
	 * @return object
	 */
	function getHelper($name) {
		$classname = ucfirst(strtolower($name)) . 'Helper';
		if (!classRegistry::hasObject($classname)) {
			throw new RuntimeException("Helper $name not initialized yet. Wrong initialization order?");
		}
		return classRegistry::getObject($classname);
	}

}