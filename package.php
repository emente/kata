<?php
/**
 * KATA PACKAGER 0.2
 *
 * put this file in your kata-root and execute it.
 *
 * all classes will be joined into a single file and webroot/index.php is overwritten.
 * if you use a code cache you can achieve great performance improvements, please
 * heavily increase your caches TTL as parsing is now expensive.
 *
 * you can delete lib/ config/ and all models,controllers etc. afterwards,
 * only controllers/lang/*.php, tmp/, webroot/, views/ are still needed.
 */

require "lib".DIRECTORY_SEPARATOR."defines.php";
require "config".DS."core.php";

$buffer = '<?php //packager 0.12

	function compiledAutoloader($classname) {
		$cname = strtolower($classname);
		switch ($cname) {
			case substr($classname, 0, 3) == \'GF_\' or substr($classname, 0, 5) == \'Zend_\' or substr($classname, 0, 6) == \'ZendX_\' :
				require str_replace(\'_\', \'/\', $classname) . \'.php\';
				break;
		}
	}
	spl_autoload_register(\'compiledAutoloader\');

';

//dont fiddle with these files:
$filesDone = array(
           'lib/boot.php',
           'lib/app_controller.php',
           'lib/app_model.php',
           'lib/clustermodel.php',
		   'lib/tags.php',
           'lib/basics.php',
		   'lib/updateKata.php'
);

//ignore all debug utils
foreach (glob(LIB.'boot_*') as $filename) {
	$filesDone[] = 'lib/'.basename($filename);
}

////////////////////////////////////////////////////////////////////////////////

function addFile($name,$force=false,$handleRequire=false,$patches=array()) {
         global $buffer,$filesDone;

		 $unixname = str_replace(DS,'/',$name);
		 
		 //already done/skip?
		 if (in_array($unixname,$filesDone) && ($force==false)) {
            return;
         }

		 if ('vendors/' != substr($name,0,8)) {
			 //malformed filename?
			if (basename($name) != strtolower(basename($name))) {
				 die("uppercase filename violation: '$name'\n");
			 }
		 }

         $txt = trim(file_get_contents($name));
         if ($txt === false) {
            die("cannot open '$name'\n");
         }
         echo "** $name\n";

		 // remove preamble / closing tag
         if (substr($txt,0,5) == '<?php') {
            $txt = substr($txt,5);
         }
         if (substr($txt,0,2) == '<?') {
            $txt = substr($txt,2);
         }
         if (substr($txt,-2,2) == '?>') {
            $txt = substr($txt,0,strlen($txt)-2);
         }

         if ($handleRequire) {
			$txt = str_Replace("require","//require",$txt);
         }

		 foreach ($patches as $s=>$d) {
			$txt = str_replace($s,$d,$txt);
		 }

         $buffer.= "\n\n//============ $name =======================================================\n\n$txt\n\n";
         $filesDone[] = $name;
}

function addFilesInDir($dir,$handleRequire=false, $recursive=false) {
         $files = scandir($dir);
         foreach ($files as $file) {
                 if (substr($file,0,1) == '.') continue;
                 if (substr($file,-4,4) != '.php') continue;
                 addFile($dir.$file,false,$handleRequire);
         }
}

addFile('lib/defines.php');
addFile('config/core.php');
addFile('lib/boot.php',true,true);

if (file_exists(ROOT.'config'.DS.'database.php')) {
	addFile('config/database.php');
}
if (file_exists(ROOT.'config'.DS.'tags.php')) {
	addFile('config/tags.php');
}
if (file_exists(ROOT.'controllers'.DS.'app_controller.php')) {
	$filesDone[] = 'lib/controllers/app_controller.php';
}
if (file_exists(ROOT.'models'.DS.'app_model.php')) {
	$filesDone[] = 'lib/models/app_model.php';
}
if (file_exists(ROOT.'config'.DS.'tags.php')) {
	$filesDone[] = 'lib/models/app_model.php';
}

addFile('lib/dispatcher.php',false,true);
addFile('lib/helper.php',false,true,array('$tags = array();'=>'global $tags;'));
addFile('lib/view.php',false,false,array('require LIB'=>'//require LIB'));
addFilesInDir('lib/',true);
addFile('lib/controllers/components/locale.php');
addFile('lib/controllers/components/'.strtolower(SESSION_STORAGE).'.session.php');
addFile('lib/controllers/components/session.php',false,true);
addFilesInDir('lib/models/');
addFilesInDir('lib/utilities/');
addFilesInDir('lib/views/helpers/');
addFile('lib/basics.php',true,true);

addFilesInDir('controllers/');
addFilesInDir('controllers/components/');
addFilesInDir('models/');
addFilesInDir('utilities/');
addFilesInDir('views/helpers/');
addFilesInDir('vendors/',true);
if (strpos($buffer,'ScaffoldController') !== false) {
	addFile('lib/controllers/scaffold_controller.php');
}

$buffer.="\n\n\n".'$dispatcher= new dispatcher();'."\n".
'echo $dispatcher->dispatch(isset ($_GET[\'kata\']) ? $_GET[\'kata\'] : \'\', isset ($routes) ? $routes : null);'."\n\n";

file_put_contents('webroot/index.php',$buffer);

echo 'Packaged. Output Size '.ceil(strlen($buffer)/1024)." kb\n\n";
