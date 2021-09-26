<?php

/**
 * include this file inside your config.php to turn any error,warning,notice into a hard error that causes a stacktrace
 *
 * @author mnt@codeninja.de
 * @package kata_debugging
 */
if (DEBUG > 0) {

    xdebug_disable();

    /**
     * our own exception to inject line and file into exception
     * @package kata_debugging
     */
    class kataStrictError extends Exception {

        protected $linestr = '';

        function __construct($message = null, $file, $line, $str) {
            $this->message = $message;
            $this->file = $file;
            $this->line = $line;
            $this->code = 0;
            $this->linestr = $str;
        }

        function getLineStr() {
            return $this->linestr;
        }

        /**
         * error handler that throws kataStrictError-exception on error
         */
        function kataStrictErrorHandler($code, $msg, $file, $line) {
            if (0 == error_reporting()) {
                return;
            }
            if (is_null($file)) {
                return;
            }

            $lines = file($file);
            throw new kataStrictError($msg, $file, $line, trim($lines[$line - 1]));
//restore_error_handler();
        }

        function kataStrictExceptionHandler($e) {
            
        }

    }

    class kataErrboxDumper {

        private function minivardump($vars) {
            if (count($vars) == 0) {
                return;
            }

            $varCnt = 0;
            foreach ($vars as $var) {
                if (is_string($var)) {
                    if (strlen($var) > 20) {
                        echo '<span title="' . h($var) . '">\'' . h(substr($var, 0, 20)) . '\'...</span>';
                    } else {
                        echo "'" . h($var) . "'";
                    }
                } else if (is_numeric($var)) {
                    echo h($var);
                } else {
                    echo h(kataFunc::getValueInfo($var));
                }

                $varCnt++;
                if (($varCnt > 2) && (count($vars) > 2)) {
                    echo '...';
                    break;
                }
                if ($varCnt < count($vars)) {
                    echo ', ';
                }
            }
        }

        function echoTrace() {
            $traceArray = $this->getTrace();

            $tdCell = '<td style="border:1px solid red;padding:2px;">';
            echo '<table style="min-width:100%;border:1px solid red;color:black;background-color:#e8e8e8;border-collapse:collapse;"><tr>' . $tdCell . '#</td>' . $tdCell . 'Function</td>' . $tdCell . 'Location</td></tr>';
            $cnt = 0;
            foreach ($traceArray as $traceLine) {
                $cnt++;

                echo '<tr>' . $tdCell . '<a name="kataErrorTop' . $cnt . '">#' . $cnt . '</a></td>' . $tdCell . '<a style="color:black;" href="javascript:var e=document.getElementById(\'kataError' . $cnt . '\');e.style.display=(e.style.display==\'none\'?\'table-row\':\'none\');void(0);">';

                if (isset($traceLine['function']) && ($traceLine['function'] == 'kataStrictErrorHandler')) {
                    $str = $e->getLineStr();
                    if (strlen($str) > 40) {
                        echo h(substr($str, 0, 40)) . '...';
                    } else {
                        echo h($str);
                    }
                    $traceLine = array(
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    );
                }

                if (isset($traceLine['class'])) {
                    echo $traceLine['class'] . $traceLine['type'] . $traceLine['function'] . '(';
                    if (isset($traceLine['args'])) {
                        $this->minivardump($traceLine['args']);
                    }
                    echo ')';
                } else
                if (isset($traceLine['function'])) {
                    echo $traceLine['function'] . '(';
                    $this->minivardump($traceLine['args']);
                    echo ')';
                }
                echo '</a></td>' . $tdCell;
                if (isset($traceLine['file'])) {
                    echo str_replace(ROOT, '', $traceLine['file']) . ':' . $traceLine['line'];
                }
                echo '</td></tr>';

                echo '<tr id="kataError' . $cnt . '" style="display:none"><td colspan="3"><pre>';
                print_R($traceLine);
                echo "\n";
                echo '<a style="color:black;" href="#kataErrorTop' . $cnt . '">^ top</a> <a style="color:black;" href="javascript:var e=document.getElementById(\'kataError' . $cnt . '\');e.style.display=(e.style.display==\'none\'?\'table-row\':\'none\');void(0);">close</a>';
                echo '</pre></td></tr>';
            }
            echo '</table><br /><br />';
        }

    }

    set_error_handler('kataStrictErrorHandler');
    set_exception_handler('kataStrictExceptionHandler');
}//debug
