<?php

/**
 * @package kata_component
 */
/**
 * locale-component. reads and caches an phpfile with language-strings
 * components are lightweight supportclasses for controllers
 * @package kata_component
 */
/**
 * global variable to cache the locale-class
 * @global object $GLOBALS['__cachedLocaleComponent']
 * @name __cachedLocaleComponent
 */
$GLOBALS['__cachedLocaleComponent'] = null;

/**
 * global function used to access language-strings. returns warning-string if key does not exist
 * @param string $msgId name of the language-string to return
 * @param array $msgArgs any parameters for printf if you have
 * @parma bool $safe true if you want no error to be thrown
 * @return string
 */
function __($msgId, $msgArgs = NULL, $safe = false) {
    if (null == $GLOBALS['__cachedLocaleComponent']) {
        $GLOBALS['__cachedLocaleComponent'] = classRegistry :: getObject('LocaleComponent');
    }
    if ($safe) {
        return $GLOBALS['__cachedLocaleComponent']->safeGetString($msgId, $msgArgs);
    }
    return $GLOBALS['__cachedLocaleComponent']->getString($msgId, $msgArgs);
}

/**
 * The Locale-Component Class
 * @package kata_component
 */
class LocaleComponent extends Component {

    /**
     * placeholder for all languages
     * @var array
     */
    protected $acceptedLanguages = null;

    /**
     * which language-code is currently in use
     * @var string
     * @private
     */
    protected $code = false;

    /**
     * the array with all locale-strings for the current language are cached here
     * @var mixed
     */
    protected $messages = null;

    /**
     * called by controller after the component was instanciated first
     * @param object $controller the calling controller
     */
    public function startup($controller) {
        parent::startup($controller);

        $this->setCode($this->findLanguage());

        if (!defined('LANGUAGE_FALLBACK')) {
            define('LANGUAGE_FALLBACK', false);
        }
        if (!defined('LANGUAGE_WARNEMPTY')) {
            define('LANGUAGE_WARNEMPTY', true);
        }
        if (!defined('LANGUAGE_ESCAPE')) {
            define('LANGUAGE_ESCAPE', false);
        }
    }

    /**
     * returns html with h()ed entities and tags. entities are _not_ double-encoded, certain tags survive als html
     *
     * @param string $html raw html with umlauts
     * @return string html with
     */
    public function escapeHtml($html) {
        //[roman] da es die schöne double_encode Sache bei htmlentities erst ab der PHP 5.2.3 gibt hier ein fieser Mist...
        if (version_compare(PHP_VERSION, '5.2.3', '>=')) {
            $html = htmlentities($html, ENT_QUOTES, 'UTF-8', FALSE);
        } else {
            $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
            $html = htmlentities($html, ENT_QUOTES, 'UTF-8');
        }
        return $html;
    }

    function getStringInternal($id, $messageArgs = null) {
        if (empty($this->code)) {
            throw new Exception('Locale: code not set yet');
        }

        if (empty($this->messages)) {
            $this->getMessages();
        }

        $ret = null;
        if (isset($this->messages[$id])) {
            $ret = $this->messages[$id];
        }

        if (empty($ret) && LANGUAGE_FALLBACK) {
            if (empty($this->enCache)) {
                $messages = array();
                include ROOT . 'controllers' . DS . 'lang' . DS . 'en.php';
                $this->enCache = $messages;
            }
            if (isset($this->enCache[$id])) {
                $ret = $this->enCache[$id];
            }
        }

        if (empty($ret)) { //null or ''
            return $ret;
        }

        if (count($messageArgs) > 0) {
            $replaced = 0;
            if (!empty($messageArgs)) {
                foreach ($messageArgs as $name => $value) {
                    $ret = str_replace('%' . $name . '%', $value, $ret);
                    $replaced++;
                }
            }
            if ((DEBUG > 0) && ($replaced != count($messageArgs))) {
                throw new Exception('locale: "' . $id . '" called with wrong number of arguments for language ' . $this->code . ' (i replaced:' . $replaced . ' i was given:' . count($messageArgs) . ') key value is "' . $this->messages[$id] . '"');
            }
        }
        if (LANGUAGE_ESCAPE) {
            $ret = $this->escapeHtml($ret);
        }

        return $ret;
    }

    /**
     * return the translation for the given string-identifier. throws expetions (if DEBUG>0) if key is missing or wrong parameters.
     * @param string $id identifier to look up translation
     * @param array $messageArgs optional parameters that will be formatted into the string with printf
     */
    function getString($id, $messageArgs = null) {
        $ret = $this->getStringInternal($id, $messageArgs);
        if (null === $ret) {
            if (DEBUG > 0) {
                throw new exception('locale: cant find "' . $id . '" in language ' . $this->code);
            } else {
                writeLog("'$id' unset", 'locale');
                return '---UNSET(' . $id . ')---';
            }
        }
        if (($ret === '') && LANGUAGE_WARNEMPTY) {
            writeLog("'$id' empty", 'locale');
            return '---EMPTY(' . $id . ')---';
        }
        return $ret;
    }

    /**
     * return given key or NULL if key is not found
     * @param string $id
     * @param array|null $messageArgs
     * @return string|null
     */
    function safeGetString($id, $messageArgs = null) {
        $ret = $this->getStringInternal($id, $messageArgs);
        if (empty($ret)) {
            return null;
        }
        return $ret;
    }

    /**
     * sets a language-code. writes the code into the session of the user
     * and sets Lang_Code for all views
     * @param string $code short iso-code for language ("de" "en" "fr")
     */
    function setCode($code) {
        if (empty($code)) {
            return;
        }

        if ($this->code != $code) {
            $this->messages = null;
            /*
              if (isset ($this->Session)) {
              $this->Session->write('Lang.Code', $code);
              }
             */
            $this->code = $code;
            if ($this->controller) {
                $this->controller->set('Lang_Code', $code);
            }
            if (function_exists('setlocale')) {
                $lang = $this->getLanguageFromTld($code);
                $loc = $lang . '.utf8';
                setlocale(LC_COLLATE, $loc, $lang, $code);
                //"workaround" for http://bugs.php.net/bug.php?id=35050
                if ('tr' != $code) {
                    setlocale(LC_CTYPE, $loc, $lang, $code);
                }
                setlocale(LC_TIME, $loc, $lang, $code);
            }
        }
    }

    function getCode() {
        return $this->code;
    }

    private function fillAcceptedLanguages() {
        if ($this->acceptedLanguages !== null) {
            return;
        }

        $this->acceptedLanguages = array();
        if ($h = opendir(ROOT . 'controllers' . DS . 'lang' . DS)) {
            while (($file = readdir($h)) !== false) {
                if ($file {
                        0 } == '.') {
                    continue;
                }
                $temp = explode('.', $file);
                if (isset($temp[1]) && ('php' == $temp[1])) {
                    $this->acceptedLanguages[$temp[0]] = $temp[0];
                }
            }
            closedir($h);
        }

        if (isset($this->acceptedLanguages['en'])) {
            unset($this->acceptedLanguages['en']);
            $this->acceptedLanguages['en'] = 'en';
        }

        if (isset($this->acceptedLanguages['de'])) {
            unset($this->acceptedLanguages['de']);
            $this->acceptedLanguages['de'] = 'de';
        }
    }

    function doesLanguageExist($lang) {
        $this->fillAcceptedLanguages();
        return !empty($this->acceptedLanguages[$lang]);
    }

    function getAcceptedLanguages() {
        $this->fillAcceptedLanguages();
        return $this->acceptedLanguages;
    }

    /**
     * find the startup-language by looking at the LANGUAGE define in core/config.php
     */
    function findLanguage() {
        if ((LANGUAGE == 'NULL') || (LANGUAGE === NULL)) {
            return null;
        }
        /*
          if (isset ($this->Session)) {
          $code= $this->Session->read('Lang.Code');
          if (isset ($code) && !empty ($code)) {
          return $code;
          }
          }
         */
        if (LANGUAGE == 'VHOST') {
            $l = $this->getVhostLang();
            if (empty($l)) {
                $l = $this->getBrowserLang();
            }
            return $l;
        }
        if (LANGUAGE == 'BROWSER') {
            return $this->getBrowserLang();
        }
        return LANGUAGE;
    }

    /**
     * try to find an language that we have as a file and that has a high priority in the users browser.
     * returns EN if anything fails
     * @return string short iso-code
     */
    function getBrowserLang() {
        $this->fillAcceptedLanguages();

        $wanted = env('HTTP_ACCEPT_LANGUAGE');
        $key = '';
        if (isset($wanted)) {
            $Languages = explode(",", $wanted);
            $SLanguages = array();
            foreach ($Languages as $Key => $Language) {
                $Language = str_replace("-", "_", $Language);
                $Language = explode(";", $Language);
                if (isset($Language[1])) {
                    $Priority = explode("q=", $Language[1]);
                    $Priority = $Priority[1];
                } else {
                    $Priority = "1.0";
                }
                $SLanguages[] = array(
                    'priority' => $Priority,
                    'language' => strtolower($Language[0])
                );
            }

            foreach ($SLanguages as $key => $row) {
                $priority[$key] = $row['priority'];
                $language[$key] = $row['language'];
            }

            array_multisort($priority, SORT_DESC, $language, SORT_ASC, $SLanguages);

            foreach ($SLanguages as $A) {
                // Check full codes first (xx_XX), then check 2digit-codes
                $key = $this->getTldFromLanguage($A['language']);
                if (empty($key)) {
                    $GenericLanguage = explode("_", $A['language']);
                    if (!empty($this->acceptedLanguages[$GenericLanguage[0]])) {
                        $key = $this->getTldFromLanguage($GenericLanguage[0]);
                    }
                }

                if (!empty($key)) {
                    break;
                }
            }
        }

        return is($this->acceptedLanguages[$key], '');
    }

    /**
     * try to find an language that we have as a file depending on the current domain name
     * foo.example.tr -> "tr"
     * [foo.bar.]tr.example.com -> "tr"
     */
    function getVhostLang($useTld = false) {
        $this->fillAcceptedLanguages();

        $name = explode('.', env('SERVER_NAME'));
        if (count($name) < 2) {
            return '';
        }

        foreach ($this->acceptedLanguages as & $lang) {
            // www.DE.example.com DE.example.com
            if (($name[0] == $lang) || ($name[1] == $lang)) {
                return $this->getTldFromLanguage($lang);
            }
            if ($useTld) {
                // www.example.DE
                if (isset($name[count($name) - 1]) && ($name[count($name) - 1] == $lang)) {
                    return $this->getTldFromLanguage($lang);
                }
            }
        }

        return '';
    }

    /**
     * return the array containing all locale-codes by reference
     */
    public function & getMessageArray() {
        if (null === $this->messages) {
            $this->getMessages();
        }
        return $this->messages;
    }

    /**
     * load the message-array by loading controllers/lang/XX.php
     */
    function getMessages() {
        if (empty($this->messages)) {
            $messages = array();
            include ROOT . 'controllers' . DS . 'lang' . DS . $this->code . '.php';
            $this->messages = & $messages;
        } //null
    }

    protected $tldToLanguageArr = array(
        'ae' => 'ar_AR',
        'ar' => 'es_AR',
        'bg' => 'bg_BG',
        'br' => 'pt_BR',
        'by' => 'be_BY',
        'cl' => 'es_CL',
        'cn' => 'zh_CN',
        'co' => 'es_CO',
        'cz' => 'cs_CZ',
        'de' => 'de_DE',
        'dk' => 'da_DK',
        'ee' => 'et_EE',
        'eg' => 'ar_EG',
        'en' => 'en_UK',
        'es' => 'es_ES',
        'fi' => 'fi_FI',
        'fr' => 'fr_FR',
        'gr' => 'el_GR',
        'hk' => 'zh_HK',
        'hr' => 'hr_HR',
        'hu' => 'hu_HU',
        'id' => 'id_ID',
        'il' => 'he_IL',
        'in' => 'en_IN',
        'ir' => 'fa_IR',
        'it' => 'it_IT',
        'jp' => 'ja_JP',
        'kr' => 'ko_KR',
        'lt' => 'lt_LT',
        'lv' => 'lv_LV',
        'mx' => 'es_MX',
        'nl' => 'nl_NL',
        'no' => 'nb_NO',
        'pe' => 'es_PE',
        'ph' => 'tl_PH',
        'pk' => 'ur_PK',
        'pl' => 'pl_PL',
        'pt' => 'pt_PT',
        'ro' => 'ro_RO',
        'rs' => 'sr_RS',
        'ru' => 'ru_RU',
        'se' => 'sv_SE',
        'si' => 'sl_SI',
        'sk' => 'sk_SK',
        'th' => 'th_TH',
        'tr' => 'tr_TR',
        'tw' => 'zh_TW',
        'ua' => 'ru_UA',
        'us' => 'en_US',
        've' => 'es_VE',
        'vn' => 'vi_VN',
        'yu' => 'yu_YU',
        'com' => 'en_UK',
        'dev' => 'de_DE',
        'int' => 'en_US',
        '00' => '00_00',
    );

    /**
     * map given language-tld-code to DINISO code for setLocale()
     * @param string language-code
     * @return string DINISO-code
     */
    function getLanguageFromTld($lang) {
        return empty($this->tldToLanguageArr[$lang]) ? '' : $this->tldToLanguageArr[$lang];
    }

    /**
     * map given DINISO to language-tld-code code for setLocale()
     * @param string DINISO-code
     * @return string language-code
     */
    function getTldFromLanguage($langcode) {
        $langcode = strtolower($langcode);
        if (strlen($langcode) == 2) {
            if (isset($this->tldToLanguageArr[$langcode])) {
                return $langcode;
            }
            foreach ($this->tldToLanguageArr as $tld => $code) {
                if (substr($code, 0, 2) == $langcode) {
                    return $tld;
                }
            }
        } else {
            foreach ($this->tldToLanguageArr as $tld => $code) {
                if (strtolower($code) == $langcode) {
                    return $tld;
                }
            }
        }
        return '';
    }

}
