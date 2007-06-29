#!/home/y/bin/php -d open_basedir= 
<?php

// Input Args default values

$templatesBaseUrl = "http://localhost/templates";
$yuiDistRoot = "./yuidist";
$templatesRoot = "../../../../templates"; 

if ($argc > 1) {
	if ($argv[1] == "-h") {
		printHelp();
		return;
	}  else {
		$args = parseArgs($argv); 
		if (isset($args["u"])) { $templatesBaseUrl = $args["u"]; }
		if (isset($args["d"])) { $yuiDistRoot = $args["d"]; }
		if (isset($args["t"])) { $templatesRoot = $args["t"]; }
	}
}

include("$templatesRoot/examples/data/examplesInfo.php");

echo "\nCreating Static Dist Examples\n\n";

echo "Using Template Base URL: $templatesBaseUrl\n";
echo "Using YUI Dist Root: $yuiDistRoot\n";
echo "Using Template Root: $templatesRoot\n";

// 0. Create folders
createFolders($modules);

echo "\n=================================";
echo "\nCreating Uber Index Pages";
echo "\n=================================";

// 1. Main Landing Page (Mega Uber List)
generateExampleFile("index.php", "index.html");

// 2. Per Example Landing Page (Not so Uber List)
generateExampleFile("examples/index.php", "examples/index.html");

// 3. Generate Examples
generateExamples($modules, $examples);

// 4. Copy Assets
copyAssets(); 

echo "\n\nDone\n";

return;


#######################################################################
# Function Definitions
#######################################################################

/**
 * Create top level folders under dist root
 */
function createFolders($modules) {

	global $yuiDistRoot;

	echo "\n=================================";
	echo "\nCreating Folders";
	echo "\n=================================";

	foreach($modules as $moduleKey=>$module) {
		
		$folderPath = "$yuiDistRoot/examples/$moduleKey";

		if (file_exists($folderPath) === false) {
			echo "\nCreating $folderPath";
			if (mkdir($folderPath, 0777, true)) {
				echo " - OK";
			} else {
				echo " - Failed";
			}
		}
	}

	$assetsPath = "$yuiDistRoot/assets"; 

	if (file_exists($assetsPath) === false) {
		echo "\nCreating $assetsPath";
 		if(mkdir($assetsPath)) {
			echo " - OK";
		} else {
			echo " - Failed";
	 	}	
	}
}


/**
 * Copies top level assets
 */
function copyAssets() {

	global $yuiDistRoot;
	global $templatesRoot;

	echo "\n=================================";
	echo "\nCopying Top Level Assets";
	echo "\n=================================";

	$src = "$templatesRoot/assets"; 
	$dest = "$yuiDistRoot/"; 

        copyDirectory($src, $dest);
}


/**
 * Copies Module Assets from Templates Root to Dist
 */
function copyModuleAssets($moduleKey) {

	global $yuiDistRoot;
	global $templatesRoot;

	$src = "$templatesRoot/examples/$moduleKey/assets";
	$dest = "$yuiDistRoot/examples/$moduleKey/"; 

	copyDirectory($src, $dest);
}


/**
 * Copies one directory to another, using rsync -r --exclude=CVS 
 */
function copyDirectory($s, $d) {

       	// NOTE: Deciding to use rsync as opposed to DirectoryIterator, 
	// to save checking for CVS files. Also using --exclude
	// to limit exclusion to just the CVS dir. 
	// Could use -C if we wanted to exclude everything in 
	// CVSIGNORE, but thought this maybe too much (*.out, *.bak etc..) 
	// and also introduces ENV dependancies.

	if (file_exists($s)) {
		echo "\nCopying Directory $s to $d";

		$cmd = "rsync -r --exclude=CVS $s $d";
		exec($cmd, $out, $ret);

		if ($ret > 0) {
			echo " - Failed";
		} else {
			echo " - OK";
		}
	} else {
		echo "No $s to copy - OK";
	}
}


/**
 * Generates a file with the given filename/path under dist root
 * from the given output of the given URL. The path needs to be the 'real'
 * non-symlinked path.
 */
function generateExampleFile($srcUrl, $fileName) {

	global $yuiDistRoot;
	global $templatesBaseUrl;

	$file = $yuiDistRoot."/".$fileName;
	$url = $templatesBaseUrl."/".$srcUrl;

	echo "\nGenerating: $file [$url]";

	$cUrl = curl_init($url);
	$fHandle = fopen($file, "w");

	if ($fHandle) {
		curl_setopt($cUrl, CURLOPT_FILE, $fHandle);
		curl_setopt($cUrl, CURLOPT_HEADER, false);
		curl_exec($cUrl);
		curl_close($cUrl);
		fclose($fHandle);
		echo " - OK";
	} else {
		echo "- Failed";
	}
}


/**
 * Generates the set of static example HTML files under dist root, 
 * by iterating over the module/examples arrays
 */
function generateExamples($modules, $examples) {

	// MAKE CONSTANT?
	$types = array('css', 'utility', 'widget');

	foreach($types as $type) {

		$modulesForType = getModulesByType($type, $modules);

		foreach($modulesForType as $moduleKey=>$module) {

			echo "\n=================================";
			echo "\nGenerating $moduleKey examples";
			echo "\n=================================";

			generateExampleFile("examples/module/examplesModuleIndex.php?module=$moduleKey", 
						"examples/$moduleKey/index.html");

			copyModuleAssets($moduleKey);

			$moduleExamples = getExamplesByModule($moduleKey, $examples);
	
			if ($moduleExamples) {
				
				// 3. Example Pages
				foreach($moduleExamples as $exampleKey=>$example) {
	
					// Default Presentation (XXX.html)
					generateExampleFile("examples/module/example.php?name=$exampleKey",
								"examples/$moduleKey/$exampleKey".".html");

					// Requires New Window (XXX_source.html)
					if ($example["newWindow"] == "require") {
						generateExampleFile("examples/data/src/$moduleKey/$exampleKey"."_source.php", 
									"examples/$moduleKey/$exampleKey"."_source.html");
					}
					
					// Supports New Window (XXX_clean.html)
					if ($example["newWindow"] != "require" && $example["newWindow"] != "suppress") {
						generateExampleFile("examples/module/example.php?name=$exampleKey&clean=true", 
									"examples/$moduleKey/$exampleKey"."_clean.html");
					} 
					
					// Supports Logging (XXX_log.html)
					if ($example["loggerInclude"] != "require" && $example["loggerInclude"] != "suppress") {
						generateExampleFile("examples/module/example.php?name=$exampleKey&log=true", 
									"examples/$moduleKey/$exampleKey"."_log.html");
					}
				}
			}
		}
	}
}


/**
 * Parses input args into a hashmap with argname => argvalue key/value
 * pairs (argname is saved without the -)
 */ 
function parseArgs($argsArray) {

    $arr = array();

    for ($i=1; $i < count($argsArray); $i++) {
        $val = $argsArray[$i];
	echo $val;
	if ($val == "-u") {
		$arr["u"] = $argsArray[++$i];       
	}
	if ($val == "-d") {
		$arr["d"] = $argsArray[++$i];
	}
	if ($val == "-t") {
		$arr["t"] = $argsArray[++$i];
	}
    }

    return $arr;
}


/**
 * Prints help
 */ 
function printHelp() {
	echo "\nUsage: ./gendistexamples.php [-u templatesurl] [-d yuidistroot] [-t templatesroot]";

	echo "\n\ntemplatesurl\n\tThe absolute URL for the templates folder"
		."\n\ton a server hosting the yui build.\n\tDefaults to 'http://localhost/templates'";
	echo "\n\nyuidistroot\n\tThe path to the base directory for the yuidist package."
		."\n\tNeeds to be the 'real' non-symlinked path, due to php limitations with fopen."
		."\n\tDefaults to './yuidist'";
	echo "\n\ntemplatesroot\n\tThe path to the templates folder."
		."\n\tCan be relative to gendistexamples.php.\n\tDefaults to '../../../../templates'";

	echo "\n\nNOTE: All paths should be specified without trailing slashes.\n\n";
} 

?>
