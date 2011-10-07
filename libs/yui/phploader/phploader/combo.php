<?PHP
/**
 *  Copyright (c) 2009, Yahoo! Inc. All rights reserved.
 *  Code licensed under the BSD License:
 *  http://developer.yahoo.net/yui/license.html
 *  version: 1.0.0b1
 */
 
/*
    This feature will allow YUI PHP Loader to combine files without relying 
    on a remote combo-service.  The key use case here would be someone 
    writing their own website/application in PHP.

    1. The main endpoint for combo requests in this case is combo.php.  Place
        this file in the same location as loader.php.

        Note: If the phploader directory does not live in the webserver's root 
        folder then modify the PATH_TO_LOADER variable in combo.php accordingly

    2. Download and extract each version of YUI you intend to support into
        the phploader/lib directory.

        A valid setup would look something like:
        htdocs/phploader/lib/2.7.0/build
        htdocs/phploader/lib/2.6.0/build
        etc...
*/

//Web accessible path to the YUI PHP loader lib directory (Override as needed)
define("PATH_TO_LOADER", server() . "/phploader/phploader/lib/");

//server(): Computes the base URL of the current page (protocol, server, path)
//credit: http://code.google.com/p/simple-php-framework/ (modified version of full_url), license: MIT
function server()
{
    $s = getenv('HTTPS') ? '' : (getenv('HTTPS') == 'on') ? 's' : '';
    $protocol = substr(strtolower(getenv('SERVER_PROTOCOL')), 0, strpos(strtolower(getenv('SERVER_PROTOCOL')), '/')) . $s;
    $port = (getenv('SERVER_PORT') == '80') ? '' : (":".getenv('SERVER_PORT'));
    return $protocol . "://" . getenv('HTTP_HOST') . $port;
}

$queryString = getenv('QUERY_STRING') ? urldecode(getenv('QUERY_STRING')) : '';
if (isset($queryString) && !empty($queryString)) {
    $yuiFiles    = explode("&amp;", $queryString);
    $contentType = strpos($yuiFiles[0], ".js") ? 'application/x-javascript' : ' text/css';
    
    //Use the first module to determine which version of the YUI meta info to load
    if (isset($yuiFiles) && !empty($yuiFiles)) {
        $metaInfo = explode("/", $yuiFiles[0]);
        $yuiVersion = $metaInfo[0];
    }
    
    include("./loader.php");
    $loader = new YAHOO_util_Loader($yuiVersion);
    $base = PATH_TO_LOADER . $loader->comboDefaultVersion . "/build/"; //Defaults to current version

    //Detect and load the required components now
    $baseOverrides = array();
    $yuiComponents = array();
    foreach($yuiFiles as $yuiFile) {
        $parts = explode("/", $yuiFile);
        if (isset($parts[0]) && isset($parts[1]) && isset($parts[2])) {
            //Add module to array for loading
            $yuiComponents[] = $parts[2];
        } else {
           die('<!-- Unable to determine module name! -->');
        }
    }
    
    //Load the components
    call_user_func_array(array($loader, 'load'), $yuiComponents);

    //Set cache headers and output raw file content
    header("Cache-Control: max-age=315360000");
    header("Expires: " . date("D, j M Y H:i:s", strtotime("now + 10 years")) ." GMT");
    header("Content-Type: " . $contentType);
    if ($contentType == "application/x-javascript") {
        echo $loader->script_raw();
    } else {
        echo $loader->css_raw();
    }
    
} else {
    die('<!-- No YUI modules defined! -->');
}

?>