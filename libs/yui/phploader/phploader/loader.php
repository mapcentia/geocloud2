<?PHP
/**
 *  Copyright (c) 2009, Yahoo! Inc. All rights reserved.
 *  Code licensed under the BSD License:
 *  http://developer.yahoo.net/yui/license.html
 *  version: 1.0.0b1
 */
 
/**
 * Used to specify JavaScript and CSS module requirements.  It maintains a dependency
 * tree for these modules so when a module is requested, all of the other modules it 
 * depends on are included as well.  By default, the YUI Library is configured, and 
 * other modules and their dependencies can be added via PHP.
 * @module phploader
 */

define('YUI_AFTER',      'after');
define('YUI_BASE',       'base');
define('YUI_CSS',        'css');
define('YUI_DATA',       'DATA');
define('YUI_DEPCACHE',   'depCache');
define('YUI_DEBUG',      'DEBUG');
define('YUI_EMBED',      'EMBED');
define('YUI_FILTERS',    'filters');
define('YUI_FULLPATH',   'fullpath');
define('YUI_FULLJSON',   'FULLJSON');
define('YUI_GLOBAL',     'global');
define('YUI_JS',         'js');
define('YUI_JSON',       'JSON');
define('YUI_MODULES',    'modules');
define('YUI_NAME',       'name');
define('YUI_OPTIONAL',   'optional');
define('YUI_OVERRIDES',  'overrides');
define('YUI_PATH',       'path');
define('YUI_PKG',        'pkg');
define('YUI_PREFIX',     'prefix');
define('YUI_PROVIDES',   'provides');
define('YUI_RAW',        'RAW');
define('YUI_REPLACE',    'replace');
define('YUI_REQUIRES',   'requires');
define('YUI_ROLLUP',     'rollup');
define('YUI_SATISFIES',  'satisfies');
define('YUI_SEARCH',     'search');
define('YUI_SKIN',       'skin');
define('YUI_SKINNABLE',  'skinnable');
define('YUI_SUPERSEDES', 'supersedes');
define('YUI_TAGS',       'TAGS');
define('YUI_TYPE',       'type');
define('YUI_URL',        'url');

/**
 * The YUI PHP loader base class which provides dynamic server-side loading for YUI
 * @class YAHOO_util_Loader
 * @namespace PHP
 */
class YAHOO_util_Loader {
    
    /**
    * The base directory
    * @property base
    * @type string
    * @default http://yui.yahooapis.com/[YUI VERSION]/build/
    */
    var $base = "";

    var $filter = "";

    // current target not used
    var $target = "";

    /**
    * Combined into a single request using the combo service to pontentially reduce the number of http requests required.
    * @property combine
    * @type boolean
    * @default false
    */
    var $combine = false;

    /**
    * Should we allow rollups
    * @property allowRollups
    * @type boolean
    * @default true
    */
    var $allowRollups = true;

    /**
    * Whether or not to load optional dependencies for the requested modules
    * @property loadOptional
    * @type boolean
    * @default false
    */
    var $loadOptional = false;

    /**
    * Force rollup modules to be sorted as moved to the top of
    * the stack when performing an automatic rollup.  This has a very small performance consequence.
    * @property rollupsToTop
    * @type boolean
    * @default false
    */
    var $rollupsToTop = false;

    // first pass through we will check for meta-modules and remove 
    // dependencies where a supercedes is found.
    // var $firstPass = true;

    // the first time we output a module type we allow automatic rollups, this
    // array keeps track of module types we have processed
    var $processedModuleTypes = array();

    // all required modules
    var $requests = array();

    // modules that have been been outputted via getLink()
    var $loaded = array();

    // list of all modules superceded by the list of required modules 
    var $superceded = array();

    // module load count to catch circular dependencies
    // var $loadCount = array();

    // keeps track of modules that were requested that are not defined
    var $undefined = array();

    var $dirty=true;
    
    var $sorted=null;
    
    var $accountedFor = array();

    /**
    * A list of modules to apply the filter to.  If not supplied, all
    * modules will have any defined filters applied.  Tip: Useful for debugging.
    * @property filterList
    * @type array
    * @default null
    */
    var $filterList = null;

    // the list of required skins
    var $skins = array();

    var $modules = array();

    var $fullCacheKey = null;

    var $baseOverrides = array();

    var $cacheFound = false;
    var $delayCache = false;
    
    /* If the version is set, a querystring parameter is appended to the
    * end of all generated URLs.  This is a cache busting hack for environments
    * that always use the same path for the current version of the library.
    * @property version
    * @type string
    * @default null
    */
    var $version = null;
    var $versionKey = "_yuiversion";

    // the skin definition
    var $skin = array();

    var $rollupModules = array();
    var $globalModules = array();
    var $satisfactionMap = array();
    var $depCache = array();
    var $filters = array();

    /**
    * The base path to the combo service.  Uses the Yahoo! CDN service by default.
    * You do not have to set this property to use the combine option. YUI PHP Loader ships 
    * with an intrinsic, lightweight combo-handler as well (see combo.php).
    * @property comboBase
    * @type string
    * @default http://yui.yahooapis.com/combo?
    */
    var $comboBase = "http://yui.yahooapis.com/combo?";
    
    // additional vars used to assist with combo handling
    var $cssComboLocation = null;
    var $jsComboLocation  = null;

    /**
    * The YAHOO_util_Loader class constructor
    * @constructor
    * @param {string} yuiVersion Defines which version of YUI metadata to load
    * @param {string} cacheKey Unique APC cache key.  This is combined with the YUI base
    * so that updates to YUI will force a new cache entry.  However, if your custom config 
    * changes, this key should be changed (otherwise the old values will be used until the cache expires).
    * @param {array} modules A list of custom modules
    * @param {boolean} noYUI Pass true if you do not want the YUI metadata
    */
    function YAHOO_util_Loader($yuiVersion, $cacheKey=null, $modules=null, $noYUI=false) {
        if (!isset($yuiVersion)) {
            die("Error: The first parameter of YAHOO_util_Loader must specify which version of YUI to use!");
        }
        
        /* 
        * Include the metadata config file that corresponds to the requested YUI version
        * Note: we attempt to find a prebuilt config_{version}.php file which contains an associative array,
        * but if not available we'll attempt to find and parse the YUI json dependency file.
        */
        $parentDir = dirname(dirname(__FILE__));
        $phpConfigFile = $parentDir . '/lib/meta/config_' . $yuiVersion . '.php';
        $jsonConfigFile = $parentDir . '/lib/meta/json_' . $yuiVersion . '.txt';
        
        if (file_exists($phpConfigFile) && is_readable($phpConfigFile)) {
            require($phpConfigFile);
        } else if (file_exists($jsonConfigFile) && is_readable($jsonConfigFile) && function_exists('json_encode')) {
            $jsonConfigString = file_get_contents($jsonConfigFile);
            $inf = json_decode($jsonConfigString, true);
            $GLOBALS['yui_current'] = $inf;
        } else {
            die("Unable to find a suitable YUI metadata file!");
        }
        
        global $yui_current;

        $this->apcttl = 0;
        $this->curlAvail  = function_exists('curl_exec');
        $this->apcAvail   = function_exists('apc_fetch');
        $this->jsonAvail  = function_exists('json_encode');
        $this->embedAvail = ($this->curlAvail && $this->apcAvail);
        $this->base = $yui_current[YUI_BASE];
        $this->comboDefaultVersion = $yuiVersion; //(ex) 2.7.0
        $this->fullCacheKey = null;
        $cache = null;

        if ($cacheKey && $this->apcAvail) {
            $this->fullCacheKey = $this->base . $cacheKey;
            $cache = apc_fetch($this->fullCacheKey);
        } 
        
        if ($cache) {

            $this->cacheFound = true;

            // $this->log("using cache -------------------------------------------------------");
            // $this->log(var_export($cache[YUI_DEPCACHE], true));
            // $this->log("----------------------------------------------------------");
            $this->modules = $cache[YUI_MODULES];
            $this->skin = $cache[YUI_SKIN];
            $this->rollupModules = $cache[YUI_ROLLUP];
            $this->globalModules = $cache[YUI_GLOBAL];
            $this->satisfactionMap = $cache[YUI_SATISFIES];
            $this->depCache = $cache[YUI_DEPCACHE];
            $this->filters = $cache[YUI_FILTERS];

        } else {

            // $this->log("initializing metadata-------------------------------------------------------");
            // set up the YUI info for the current version of the lib
            if ($noYUI) {
                $this->modules = array();
            } else {
                $this->modules = $yui_current['moduleInfo'];
            }

            if ($modules) {
                $this->modules = array_merge_recursive($this->modules, $modules);
            }

            $this->skin = $yui_current[YUI_SKIN];
            $this->skin['overrides'] = array();
            $this->skin[YUI_PREFIX] = "skin-";

            $this->filters = array(
                    YUI_RAW => array(
                            YUI_SEARCH => "-min\.js",
                            YUI_REPLACE => ".js"
                        ),
                    YUI_DEBUG => array(
                            YUI_SEARCH => "-min\.js",
                            YUI_REPLACE => "-debug.js"
                        )
               );

            foreach ($this->modules as $name=>$m) {

                if (isset($m[YUI_GLOBAL])) {
                    $this->globalModules[$name] = true;
                }

                if (isset($m[YUI_SUPERSEDES])) {
                    $this->rollupModules[$name] = $m;
                    foreach ($m[YUI_SUPERSEDES] as $sup) {
                        $this->mapSatisfyingModule($sup, $name);
                    }
                }
            }
        }
    }
    
    function updateCache() {
        if ($this->fullCacheKey) {
            $cache = array();
            $cache[YUI_MODULES] = $this->modules;
            $cache[YUI_SKIN] = $this->skin;
            $cache[YUI_ROLLUP] = $this->rollupModules;
            $cache[YUI_GLOBAL] = $this->globalModules;
            $cache[YUI_DEPCACHE] = $this->depCache;
            $cache[YUI_SATISFIES] = $this->satisfactionMap;
            $cache[YUI_FILTERS] = $this->filters;
            apc_store($this->fullCacheKey, $cache, $this->apcttl);
        }
    }

    /**
    * Used to load YUI and/or custom components
    * @method load
    * @param string $varname [, string $... ] List of component names
    */
    function load() {
        //Expects N-number of named components to load 
        $args = func_get_args();
        foreach ($args as $arg) {
            $this->loadSingle($arg);
        }
    }

    function setProcessedModuleType($moduleType='ALL') {
        $this->processedModuleTypes[$moduleType] = true;
    }

    function hasProcessedModuleType($moduleType='ALL') {
        return isset($this->processedModuleTypes[$moduleType]);
    }

    /**
    * Used to specify modules that are already on the page that should not be loaded again
    * @method setLoaded
    * @param string $varname [, string $... ] List of module names
    */
    function setLoaded() {
        $args = func_get_args();

        // prevent rollups when no module type is specified
        //$this->setProcessedModuleType(null);

        foreach ($args as $arg) {
            if (isset($this->modules[$arg])) {
                $this->loaded[$arg] = $arg;
                $mod = $this->modules[$arg];

                $sups = $this->getSuperceded($arg);
                foreach ($sups as $supname=>$val) {
                    //$this->log("accounting for by way of supersede: " . $supname);
                    $this->loaded[$supname] = $supname;
                }

                // prevent rollups for this module type
                $this->setProcessedModuleType($mod[YUI_TYPE]);
            } else {
                $msg = "YUI_LOADER: undefined module name provided to setLoaded(): " . $arg;
                error_log($msg, 0);
            }
        }

        //var_export($this->loaded);
    }

    function parseSkin($moduleName) {
        if (strpos( $moduleName, $this->skin[YUI_PREFIX] ) === 0) {
            return split('-', $moduleName);
        }

        return null;
    }

    function formatSkin($skin, $moduleName) {
        $prefix = $this->skin[YUI_PREFIX];
        //$prefix = (isset($this->skin[YUI_PREFIX])) ? $this->skin[YUI_PREFIX] : 'skin-';
        $s = $prefix . $skin;
        if ($moduleName) {
            $s = $s . '-' . $moduleName;
        }

        return $s;
    }

    function addSkin($skin) {
    }

    function loadSingle($name) {
        $skin = $this->parseSkin($name);

        if ($skin) {
            $this->skins[] = $name;
            $this->dirty = true;
            return true;
        }

        if (!isset($this->modules[$name])) {
            $this -> undefined[$name] = $name;
            return false;
        }

        if (isset($this->loaded[$name]) || isset($this->accountedFor[$name])) {
            // skip
            //print_r($name);
            //var_export($this->loaded);
            //var_export($this->accountedFor);
        } else {
            $this->requests[$name] = $name;
            $this->dirty = true;
        }
        
        return true;
    }

    /**
    * Used to output each of the required script tags
    * @method script
    * @return {string}
    */
    function script() {
        return $this->tags(YUI_JS);
    }
    
    /**
    * Used to output each of the required link tags
    * @method css
    * @return {string} (e.g.)
    */
    function css() {
        return $this->tags(YUI_CSS);
    }

    /**
    * Used to output each of the required html tags (i.e.) script or link
    * @method tags
    * @param {string} moduleType Type of html tag to return (i.e.) js or css.  Default is both.
    * @param {boolean} skipSort
    * @return {string}
    */
    function tags($moduleType=null, $skipSort=false) {
        return $this->processDependencies(YUI_TAGS, $moduleType, $skipSort);
    }
    
    /**
    * Used to embed the raw JavaScript inline
    * @method script_embed
    * @return {string} Returns the script tag(s) with the JavaScript inline
    */
    function script_embed() {
        return $this->embed(YUI_JS);
    }
    
    /**
    * Used to embed the raw CSS 
    * @method css_embed
    * @return {string} (e.g.) Returns the style tag(s) with the CSS inline
    */
    function css_embed() {
        return $this->embed(YUI_CSS);
    }

    /**
    * Used to output each of the required html tags inline (i.e.) script and/or style
    * @method embed
    * @param {string} moduleType Type of html tag to return (i.e.) js or css.  Default is both.
    * @param {boolean} skipSort
    * @return {string} Returns the style tag(s) with the CSS inline and/or the script tag(s) with the JavaScript inline
    */
    function embed($moduleType=null, $skipSort=false) {
        return $this->processDependencies(YUI_EMBED, $moduleType, $skipSort);
    }

    /**
    * Used to fetch an array of the required JavaScript components 
    * @method script_data
    * @return {array} Returns an array of data about each of the identified JavaScript components
    */
    function script_data() {
        return $this->data(YUI_JS);
    }

    /**
    * Used to fetch an array of the required CSS components
    * @method css_data
    * @return {array} Returns an array of data about each of the identified JavaScript components
    */
    function css_data() {
        return $this->data(YUI_CSS);
    }
    
    /**
    * Used to output an Array which contains data about the required JavaScript & CSS components
    * @method data
    * @param {string} moduleType Type of html tag to return (i.e.) js or css.  Default is both.
    * @param {boolean} allowRollups
    * @param {boolean} skipSort
    * @return {string}
    */
    function data($moduleType=null, $allowRollups=false, $skipSort=false) {
        if (!$allowRollups) {
            $this->setProcessedModuleType($moduleType);
        }

        $type = YUI_DATA;

        return $this->processDependencies($type, $moduleType, $skipSort);
    }

    /**
    * Used to fetch a JSON object with the required JavaScript components 
    * @method script_json
    * @return {string} Returns a JSON object containing urls for each JavaScript component
    */
    function script_json() {
        return $this->json(YUI_JS);
    }
    
    /**
    * Used to fetch a JSON object with the required CSS components
    * @method css_json
    * @return {string} Returns a JSON object containing urls for each CSS component
    */
    function css_json() {
        return $this->json(YUI_CSS);
    }

    /**
    *  Used to fetch a JSON object with the required JavaScript and CSS components
    * @method json
    * @param {string} moduleType
    * @param {boolean} allowRollups
    * @param {boolean} skipSort
    * @param {boolean} full
    * @return {string} Returns a JSON object with the required JavaScript and CSS components
    */
    function json($moduleType=null, $allowRollups=false, $skipSort=false, $full=false) {
        //$this->firstPass = $allowRollups; // TODO: Seems like an awkward way to force it to not use rollups
        if (!$allowRollups) {
            $this->setProcessedModuleType($moduleType);
        }

        // the original JSON output only sent the provides data, not the requires
        $type = YUI_JSON;

        if ($full) {
            $type = YUI_FULLJSON;
        }

        return $this->processDependencies($type, $moduleType, $skipSort);
    }
 
    /**
    * Used to produce the raw JavaScript code inline without the actual script tags
    * @method script_raw
    * @return {string} Returns the raw JavaScript code inline without the actual script tags
    */
    function script_raw() {
        return $this->raw(YUI_JS);
    }

    /**
    * Used to produce the raw CSS code inline without the actual style tags
    * @method css_raw
    * @return {string} Returns the raw CSS code inline without the actual style tags
    */
    function css_raw() {
        return $this->raw(YUI_CSS);
    }

    /**
    * Used to produce the raw Javacript and CSS code inline without the actual script or style tags
    * @method raw
    * @param {string} moduleType
    * @param {boolean} allowRollups
    * @param {boolean} skipSort
    * @return {string} Returns the raw JavaScript and/or CSS code inline without the actual style tags
    */
    function raw($moduleType=null, $allowRollups=false, $skipSort=false) {
        return $this->processDependencies(YUI_RAW, $moduleType, $skipSort);
    }

    function log($msg) {
        //error_log($msg, 3, "yui_loader_log.txt");
        error_log($msg, 0);
        //print_r("<p>" . $msg . "</p>");
    }
    
    function accountFor($name) {

        //$this->log("accountFor: " . $name);
        $this->accountedFor[$name] = $name;
        
        if (isset($this->modules[$name])) {
            $dep = $this->modules[$name];
            $sups = $this->getSuperceded($name);
            foreach ($sups as $supname=>$val) {
                // $this->log("accounting for by way of supersede: " . $supname);
                $this->accountedFor[$supname] = true;
            }
        }
    }

    
    function prune($deps, $moduleType) {
        if ($moduleType) {
            $newdeps = array();
            foreach ($deps as $name=>$val) {
                $dep = $this->modules[$name];
                if ($dep[YUI_TYPE] == $moduleType) {
                    $newdeps[$name] = true;
                }
            }
            return $newdeps;
        } else {
            return $deps;
        }
   }

   function getSuperceded($name) {
        $key = YUI_SUPERSEDES . $name;

        if (isset($this->depCache[$key])) {
            return $this->depCache[$key];
        }

        $sups = array();

        if (isset($this->modules[$name])) {
            $m = $this->modules[$name];
            if (isset($m[YUI_SUPERSEDES])) {
                foreach ($m[YUI_SUPERSEDES] as $supName) {
                    $sups[$supName] = true;
                    if (isset($this->modules[$supName])) {
                        $supsups = $this->getSuperceded($supName);
                        if (count($supsups) > 0) {
                            $sups = array_merge($sups, $supsups);
                        }
                    } 
                }
            }
        }

        $this->depCache[$key] = $sups;
        return $sups;
    }

    
    function skinSetup($name) {
        $skinName = null;
        $dep = $this->modules[$name];

        // $this->log("Checking skin for " . $name);

        if ($dep && isset($dep[YUI_SKINNABLE])) {

            $s = $this->skin;
            //print_r($s);
            if (isset($s[YUI_OVERRIDES][$name])) {
                foreach ($s[YUI_OVERRIDES][$name] as $name2 => $over2) {
                    $skinName = $this->formatSkin($over2, $name);
                }
            } else {
                $skinName = $this->formatSkin($s["defaultSkin"], $name);
            }
            
            // $this->log("Adding new skin module " . $name . ": " . $skinName);

            $this->skins[] = $skinName;

            $skin = $this->parseSkin($skinName);

            // module-specific
            if (isset($skin[2])) {
                $dep = $this->modules[$skin[2]];
                $package = (isset($dep[YUI_PKG])) ? $dep[YUI_PKG] : $skin[2];
                $path = $package . '/' . $s[YUI_BASE] . $skin[1] . '/' . $skin[2] . '.css';
                $this->modules[$skinName] = array(
                        "name" => $skinName,
                        "type" => YUI_CSS,
                        "path" => $path,
                        "after" => $s[YUI_AFTER]
                    );

            // rollup skin
            } else {
                $path = $s[YUI_BASE] . $skin[1] . '/' . $s[YUI_PATH];
                $newmod = array(
                        "name" => $skinName,
                        "type" => YUI_CSS,
                        "path" => $path,
                        "rollup" => 3,
                        "after" => $s[YUI_AFTER]
                    );
                $this->modules[$skinName] = $newmod;
                $this->rollupModules[$skinName] = $newmod;
            }

        }    

        return $skinName;

    }

    function getAllDependencies($mname, $loadOptional=false, $completed=array()) {

        // $this->log("Building deps list for " . $mname);

        $key = YUI_REQUIRES . $mname;
        if ($loadOptional) {
            $key .= YUI_OPTIONAL;
        }
        if (isset($this->depCache[$key])) {
            // $this->log("Using cache " . $mname);
            return $this->depCache[$key];
        }

        $m = $this->modules[$mname];
        $reqs = array();

        if (isset($m[YUI_REQUIRES])) {
            $origreqs = $m[YUI_REQUIRES];
            foreach($origreqs as $r) {
                $reqs[$r] = true;
            }
        }

        if ($loadOptional && isset($m[YUI_OPTIONAL])) {
            $o = $m[YUI_OPTIONAL];
            foreach($o as $opt) {
                $reqs[$opt] = true;
            }

        }

        foreach ($reqs as $name=>$val) {

            $skinName = $this->skinSetup($name);

            if ($skinName) {
                // $this->log("Adding skin req for " . $name . ": " . $skinName);
                $reqs[$skinName] = true;
            }
    
            //$this->log("recursing deps for " . $name);
            if (!isset($completed[$name]) && isset($this->modules[$name])) {
                $dep = $this->modules[$name];

                $newreqs = $this->getAllDependencies($name, $loadOptional, $completed);
                $reqs = array_merge($reqs, $newreqs);

                //foreach ($newreqs as $newname=>$newval) {
                    //if (!isset($reqs[$newname])) {
                        //$reqs[$newname] = true;
                    //}
                //}

            } else {

                //$this->log("ERROR " . $name . " not defined");
                //print_r(array_keys($this->modules));
            }

        }

        $this->depCache[$key] = $reqs;

        return $reqs;
    }

    // @todo restore global dependency support
    function getGlobalDependencies() {
        return $this->globalModules;
    }

    /**
     * Returns true if the supplied $satisfied module is satisfied by the
     * supplied $satisfier module
     */
    function moduleSatisfies($satisfied, $satisfier) {
        //$this->log("moduleSatisfies: " . $satisfied . ", " . $satisfier); 
        if($satisfied == $satisfier) { 
            //$this->log("true");
            return true;
        }

        if (isset($this->satisfactionMap[$satisfied])) {
            $satisfiers = $this->satisfactionMap[$satisfied];
            return isset($satisfiers[$satisfier]);
        }

        //$this->log("false");
        return false;
    }

    /**
    * Used to override the base dir for specific set of modules (Note: not supported when using the combo service)
    * @method overrideBase
    * @param {string} base Base path (e.g.) 2.6.0/build
    * @param {array} modules Module names of which to override base
    */
    function overrideBase($base, $modules) {
        foreach ($modules as $name) {
            $this->baseOverrides[$name] = $base;
        }
    }

    function listSatisfies($satisfied, $moduleList) {
        //$this->log("listSatisfies: " . $satisfied . ", " . count($moduleList)); 

        if (isset($moduleList[$satisfied])) {
            // $this->log("***satisfied by list " . $satisfied);

            $this->log("***satisfied by list " .  var_export($moduleList[$satisfied], true) );
            return true;
        } else {
            
            if (isset($this->satisfactionMap[$satisfied])) {
                $satisfiers = $this->satisfactionMap[$satisfied];
                foreach ($satisfiers as $name=>$val) {
                    if (isset($moduleList[$name])) {
                        return true;
                    }
                }
            }
        
        }

        return false;
    }

    function checkThreshold($module, $moduleList) {

        if (count($moduleList) > 0 && isset($module[YUI_ROLLUP])) {
            $matched = 0;
            //$thresh = (isset($module[YUI_ROLLUP])) ? $module[YUI_ROLLUP] : count($module[YUI_SUPERSEDES]);
            $thresh = $module[YUI_ROLLUP];
            foreach ($moduleList as $moduleName=>$moddef) {
                if (in_array($moduleName, $module[YUI_SUPERSEDES])) {
                    $matched++;
                }
            }

            //$this->log("Module: " . var_export($module, true));
            //$this->log("threshold number " . $matched . " of " . $thresh);

            return ($matched >= $thresh);
        }

        return false;
    }

    function sortDependencies($moduleType, $skipSort=false) {
        // only call this if the loader is dirty

        $reqs = array();
        $top = array();
        $bot = array();
        $notdone = array();
        $sorted = array();
        $found = array();

        // add global dependenices so they are included when calculating rollups
        $globals = $this->getGlobalDependencies($moduleType);

        foreach ($globals as $name=>$dep) {
            $reqs[$name] = true;
        }

        //print_r($this->requests);
        //return;

        // get and store the full list of dependencies.
        foreach ($this->requests as $name=>$val) {
            $reqs[$name] = true;
            $dep = $this->modules[$name];
            $newreqs = $this->getAllDependencies($name, $this->loadOptional);
            foreach ($newreqs as $newname=>$newval) {
                if (!isset($reqs[$newname])) {
                    $reqs[$newname] = true;
                }
            }
        }


        // if we skip the sort, we just return the list that includes everything that
        // was requested, all of their requirements, and global modules.  This is
        // filtered by module type if supplied
        if ($skipSort) {
            return $this->prune($reqs, $moduleType);
        }

        // $this->log("------------------------------------------------------------------");
        // $this->log(var_export($reqs, true));
        // $this->log("rollups------------------------------------------------------------------");
        //$t1 = split(" ", microtime());
        
        //$this->log("accountedFor: " . var_export($this->accountedFor, true));

        // if we are sorting again after new modules have been requested, we
        // do not rollup, and we can remove the accounted for modules
        if (count($this->accountedFor) > 0 || count($this->loaded) > 0) {
            foreach ($this->accountedFor as $name=>$val) {
                if (isset($reqs[$name])) {
                    // $this->log( "removing satisfied req (accountedFor) " . $name . "\n");
                    unset($reqs[$name]);
                }
            }

            foreach ($this->loaded as $name=>$val) {
                if (isset($reqs[$name])) {
                    // $this->log( "removing satisfied req (loaded) " . $name . "\n");
                    unset($reqs[$name]);
                }
            }

        // otherwise, check for rollups
        // } else if (!$this->hasProcessedModuleType($moduleType)) {
        } else if ($this->allowRollups) {
            // First we go through the meta-modules we know about to 
            // see if the replacement threshold has been met.
            $rollups = $this->rollupModules;
            //$this->log( "testing rollups " . count($rollups) . "\n");
            if (count($rollups > 0)) {
                foreach ($rollups as $name => $rollup) {
                    //$this->log( "checking rollup " . $name . "\n");
                    if (!isset($reqs[$name]) && $this->checkThreshold($rollup, $reqs) ) {
                        // $this->log( "rollup " . $name . "\n");
                        $reqs[$name] = true;
                        $dep = $this->modules[$name];
                        $newreqs = $this->getAllDependencies($name, $this->loadOptional, $reqs);
                        foreach ($newreqs as $newname=>$newval) {
                            if (!isset($reqs[$newname])) {
                                $reqs[$newname] = true;
                            }
                        }
                    }
                }
            }
        }

    //var_export($reqs);
    //exit;

        //$t2 = split(" ", microtime());
        //$total = (($t2[1] - $t1[1]) + ($t2[0] - $t1[0]));
        //$this->log("<----rollups: " . $total . "----------------------------");

        // clear out superceded packages
        foreach ($reqs as $name => $val) {

            $dep = $this->modules[$name];

            if (isset($dep[YUI_SUPERSEDES])) {
                $override = $dep[YUI_SUPERSEDES];
                //$this->log("override " . $name . ", val: " . $val . "\n");
                foreach ($override as $i=>$val) {
                    if (isset($reqs[$val])) {
                        unset($reqs[$val]);
                        // $this->log( "Removing (superceded by val) " . $val . "\n");
                    }

                    // debugging
                    if (isset($reqs[$i])) {
                        unset($reqs[$i]);
                        // $this->log( "Removing (superceded by i) " . $i . "\n");
                    }
                }
            }
        }

        //$this->log("------------------------------------------------------------------");
        //$this->log(var_export($reqs, true));
        //$this->log("------------------------------------------------------------------");

        //$this->log("globals to top------------------------------------------------------------------");
        //$t1 = split(" ", microtime());

        // move globals to the top
        foreach ($reqs as $name => $val) {
            $dep = $this->modules[$name];
            if (isset($dep[YUI_GLOBAL]) && $dep[YUI_GLOBAL]) {
                $top[$name] = $name;
            } else {
                $notdone[$name] = $name;
            }
        }

        //$t2 = split(" ", microtime());
        //$total = (($t2[1] - $t1[1]) + ($t2[0] - $t1[0]));
        //$this->log("<----globals to top: " . $total . "-----------------------------" );

        // merge new order if we have globals
        
        if (count($top > 0)) {
            $notdone = array_merge($top, $notdone);
        }

        //$this->log("Not done: " . var_export($notdone, true) . ", loaded: " . var_export($this->loaded, true));
        //$this->log("Not done: " . var_export($notdone, true));

        // keep track of what is accounted for
        foreach ($this->loaded as $name=>$module) {
            $this->accountFor($name);
        }

        // $this->log("done: " . var_export($this->loaded, true));

        // keep going until everything is sorted
        $count = 0;
        while (count($notdone) > 0) {
            //$this->log("processing loop " . $count);
            if ($count++ > 200) {
                $msg = "YUI_LOADER ERROR: sorting could not be completed, there may be a circular dependency";
                error_log($msg, 0);
                return array_merge($sorted, $notdone);
            }

            // each pass only processed what has not been completed
            foreach ($notdone as $name => $val) {
                //$this->log("processing notdone: " . $name . " of " . count($notdone));
                //$this->log("----------------------------------------------------");
                //$this->log(var_export($notdone, true));
                //$this->log("----------------------------------------------------");
                //$this->log("done: ");
                //$this->log(var_export($this->loaded, true));
                //$this->log("----------------------------------------------------");

                $dep = $this->modules[$name];

                $newreqs = $this->getAllDependencies($name, $this->loadOptional);


                // $this->log($name . ": checking after " . var_export($newreqs, true));
                // $this->log($name . ": checking after " . var_export($dep, true));
                // add 'after' items

                /*
                if (isset($dep[YUI_AFTER])) {
                    $after = $dep[YUI_AFTER];
                    $this->log("* " .$name . ": has after " . $after);
                    foreach($after as $a) {
                        $this->log("** " .$name . ": needs to be after " . $a);

                        // only react if the requirement is in the dependency list
                        // if ($this->listSatisfies($newreqs, $a)) {
                        // if (isset($newreqs[$a])) {
                        // if (isset($this->loaded[$a])) {
                        if ($this->listSatisfies($notdone, $a)) {
                            $newreqs[$a] = true;
                        }
                    }
                }
                */

                $failed = false;

                if (count($newreqs) == 0) {
                    //$this->log("-----No requirements: "  . $name);
                    $sorted[$name] = $name;
                    $this->accountFor($name);
                    unset($notdone[$name]);
                } else {
                    foreach ($newreqs as $depname=>$depval) {
                        //$this->log("accountedFor: " . var_export($this->accountedFor, true));
                        //$this->log("checking " . $depname . " newreqs: " . var_export($newreqs, true));
                        // check if the item is accounted for in the $done list
                        if (isset($this->accountedFor[$depname])) {
                            //$this->log("----Satisfied by 'accountedfor' list: " . $depname);
                        } else if ($this->listSatisfies($depname, $sorted)) {
                            //$this->log("----Satisfied by 'done' list: " . $depname);
                        } else {
                            $failed = true;

                            $tmp = array();
                            $found = false;
                            foreach ($notdone as $newname => $newval) {
                                if ($this->moduleSatisfies($depname, $newname)) {
                                //if ($newname != $depname && $this->moduleSatisfies($depname, $newname)) {
                                    $tmp[$newname] = $newname;
                                    unset($notdone[$newname]);
                                    $found = true;
                                    //$this->log("moving " . $depname . " because it satisfies " . $name);
                                    break; // found something that takes care of the dependency, so jump out
                                }
                            }
                            if ($found) {
                                //$this->log("found merge: "  . var_export($tmp, true).  ", " . var_export($notdone, true));
                                // this should put the module that handles the dependency on top, immediately
                                // over the the item with the missing dependency
                                $notdone = array_merge($tmp, $notdone);
                                //$this->log("after merge: "  . var_export($notdone, true));
                            } else {
                                $msg = "YUI_LOADER ERROR: requirement for " . $depname . " (needed for " . $name . ") not found when sorting";
                                error_log($msg, 0);
                                $notdone[$name] = $name;
                                return array_merge($sorted, $notdone);
                            }
                            
                            //$this->log("bouncing out of loops");
                            break(2); // break out of this iteration so we can get the missed dependency

                        }
                    }
                    // if so, add to the the sorted array, removed from notdone and add to done
                    if (!$failed) {
                        //$this->log("----All requirements satisfied: " . $name);
                        $sorted[$name] = $name;
                        $this->accountFor($name);
                        unset($notdone[$name]);
                    }
                }
            }
        }

        //print_r("before skin");

        //foreach ($reqs as $name => $val) {
        foreach ($sorted as $name => $val) {
            $skinName = $this->skinSetup($name);
        }

        //print_r("mid skin");
        //print_r($this->skins);

        if ( count($this->skins) > 0 ) {
            foreach ($this->skins as $name => $val) {
                $sorted[$val] = true;
            }
        }

        //print_r("after skin");

        //$this->log("skins " . $this->skins);
        //print_r(" <br /><br /><br /> skin");
        //print_r($this->skins);
        //print_r(" <br /><br /><br /> skin");

        //$this->log("iterations" + $count);
        $this->dirty = false;
        $this->sorted = $sorted;

        // store the results, set clear the diry flag
        return $this->prune($sorted, $moduleType);

    }
 
    function mapSatisfyingModule($satisfied, $satisfier) {
        if (!isset($this->satisfactionMap[$satisfied])) {
            $this->satisfactionMap[$satisfied] = array();
        }

        $this->satisfactionMap[$satisfied][$satisfier] = true;
    }

    function processDependencies($outputType, $moduleType, $skipSort=false, $showLoaded=false) {

        $html = '';

        // sort the output with css on top unless the output type is json
        if ((!$moduleType) && (strpos($outputType, YUI_JSON) === false) && $outputType != YUI_DATA) {
            $this->delayCache = true;
            $css = $this->processDependencies($outputType, YUI_CSS, $skipSort, $showLoaded);
            $js  = $this->processDependencies($outputType, YUI_JS, $skipSort, $showLoaded);

            // If the data has not been cached, cache what we have
            if (!$this->cacheFound) {
                $this->updateCache();
            }

            return $css . $js;
        }
        
        $json = array();

        if ($showLoaded || (!$this->dirty && count($this->sorted) > 0)) {
            $sorted = $this->prune($this->sorted, $moduleType);
        } else {
            $sorted = $this->sortDependencies($moduleType, $skipSort);
        }

        foreach ($sorted as $name => $val) {
            
            if ($showLoaded || !isset($this->loaded[$name])) {
                $dep = $this->modules[$name];
                // only generate the tag once
                switch ($outputType) {
                    case YUI_EMBED:
                        $html .= $this->getContent($name, $dep[YUI_TYPE])."\n";
                        break;
                    case YUI_RAW:
                        $html .= $this->getRaw($name)."\n";
                        break;
                    case YUI_JSON:
                    case YUI_DATA:
                        //$json[$dep[YUI_TYPE]][$this->getUrl($name)] = $this->getProvides($name);
                        $json[$dep[YUI_TYPE]][] = array(
                                $this->getUrl($name) => $this->getProvides($name)
                            );

                        break;
                    case YUI_FULLJSON:
                        $json[$dep[YUI_NAME]] = array();
                        $item = $json[$dep[YUI_NAME]];
                        $item[YUI_TYPE] = $dep[YUI_TYPE];
                        $item[YUI_URL] = $this->getUrl($name);
                        $item[YUI_PROVIDES] = $this->getProvides($name);
                        $item[YUI_REQUIRES] = $dep[YUI_REQUIRES];
                        $item[YUI_OPTIONAL] = $dep[YUI_OPTIONAL];
                        break;
                    case YUI_TAGS:
                    default: 
                        if ($this->combine === true) {
                            $this->addToCombo($name, $dep[YUI_TYPE]);
                            $html = $this->getComboLink($dep[YUI_TYPE]);
                        } else {
                           $html .= $this->getLink($name, $dep[YUI_TYPE])."\n";
                        }
                }
            }
        }

        // If the data has not been cached, and we are not running two
        // rotations for separating css and js, cache what we have
        if (!$this->cacheFound && !$this->delayCache) {
            $this->updateCache();
        }

        if (!empty($json)) {
            if ($this->canJSON()) {
                $html .= json_encode($json);
            } else {
                $html .= "<!-- JSON not available, request failed -->";
            }
        }

        // after the first pass we no longer try to use meta modules
        $this->setProcessedModuleType($moduleType);

        // keep track of all the stuff we loaded so that we don't reload 
        // scripts if the page makes multiple calls to tags
        $this->loaded = array_merge($this->loaded, $sorted);

        // return the raw data structure
        if ($outputType == YUI_DATA) {
            return $json;
        }

        if ( count($this->undefined) > 0 ) {
            $html .= "<!-- The following modules were requested but are not defined: " . join($this -> undefined, ",") . " -->\n";
        }

        return $html;

    }

    /**
    * Retrieve the calculated url for the component in question
    * @method getUrl
    * @param {string} name YUI component name
    */
    function getUrl($name) {
        // figure out how to set targets and filters
        $url = "";
        $b = $this->base;
        if (isset($this->baseOverrides[$name])) {
            $b = $this->baseOverrides[$name];
        }

        if (isset($this->modules[$name])) {
            $m = $this->modules[$name];
            if (isset($m[YUI_FULLPATH])) {
                $url = $m[YUI_FULLPATH];
            } else {
                $url = $b . $m[YUI_PATH];
            }
        } else {
            $url = $b . $name;
        }

        if ($this->filter) {

            if (count($this->filterList) > 0 && !isset($this->filterList[$name])) {

                // skip the filter

            } else if (isset($this->filters[$this->filter])) {
                $filter = $this->filters[$this->filter];
                $url = ereg_replace($filter[YUI_SEARCH], $filter[YUI_REPLACE], $url);
            }
        }

        if ($this->version) {
            $pre = (strstr($url, '?')) ? '&' : '?';
            $url .= $pre . $this->versionKey . '=' . $this->version;
        }

        //$this->log("URL: " . $url, 0);

        return $url;
    }

    /**
    * Retrieve the contents of a remote resource
    * @method getRemoteContent
    * @param {string} url URL to fetch data from
    * @return
    */
    function getRemoteContent($url) {

        $remote_content = apc_fetch($url);

        if (!$remote_content) {

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $url); // set url to post to 
            curl_setopt($ch, CURLOPT_FAILONERROR, 1); 
            
            //Doesn't work in safe mode or with openbase_dir enabled, see http://au.php.net/manual/ro/function.curl-setopt.php#71313.
            $open_basedir = ini_get("open_basedir");
            if (empty($open_basedir) && !ini_get('safe_mode')) {
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);// allow redirects
            }
            
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,1); // return into a variable 
            // curl_setopt($ch, CURLOPT_TIMEOUT, 3); // times out after 4s

            $remote_content = curl_exec($ch);

            //$this->log("CONTENT: " . $remote_content);

            // save the contents of the remote url for 30 minutes
            apc_store($url, $remote_content, $this->apcttl);

            curl_close ($ch);
        }

        return $remote_content;
    }

    function getRaw($name) {
        if (!$this->embedAvail) {
            return "cURL and/or APC was not detected, so the content can't be embedded";
        }

        $url = $this->getUrl($name);
        return $this->getRemoteContent($url);
    }

    function getContent($name, $type) {

        if(!$this->embedAvail) {
            return "<!--// cURL was not detected, so the content can't be embedded -->" . $this->getLink($name, $type);
        }

        $url = $this->getUrl($name);

        //$this->log("URL: " . $url);

        if (!$url) {
            return '<!-- PATH FOR "'. $name . '" NOT SPECIFIED -->';
        } else if ($type == YUI_CSS) {
            return '<style type="text/css">' . $this->getRemoteContent($url) . '</style>';
        } else {
            return '<script type="text/javascript">' . $this->getRemoteContent($url) . '</script>'; 
        }

    }

    function getLink($name, $type) {

        $url = $this->getUrl($name);

        if (!$url) {
            return '<!-- PATH FOR "'. $name . '" NOT SPECIFIED -->';
        } else if ($type == YUI_CSS) {
            return '<link rel="stylesheet" type="text/css" href="' . $url . '" />';
        } else {
            return '<script type="text/javascript" src="' . $url . '"></script>';
        }
    }
  
    function getComboLink($type) {
        $url = '';
        
        if ($type == YUI_CSS) {
            if ($this->cssComboLocation !== null) {
                $url = "<link rel=\"stylesheet\" type=\"text/css\" href=\"{$this->cssComboLocation}\" />";
            } else {
                $url = "<!-- NO YUI CSS COMPONENTS IDENTIFIED -->";
            }
        } else if ($type == YUI_JS) {
            if ($this->jsComboLocation !== null) {
                if ($this->cssComboLocation !== null) {
                    $url = "\n";
                }
                $url .= "<script type=\"text/javascript\" src=\"{$this->jsComboLocation}\"></script>";
            } else {
                $url = "<!-- NO YUI JAVASCRIPT COMPONENTS IDENTIFIED -->";
            }
        }
        
        //Allow for RAW & DEBUG over minified default
        if ($this->filter) {
            if (count($this->filterList) > 0 && !isset($this->filterList[$name])) {
                // skip the filter
            } else if (isset($this->filters[$this->filter])) {
                $filter = $this->filters[$this->filter];
                $url = ereg_replace($filter[YUI_SEARCH], $filter[YUI_REPLACE], $url);
            }
        }
        
        return $url;
    }
    
    function addToCombo($name, $type) {
        $pathToModule = $this->comboDefaultVersion . '/build/' . $this->modules[$name][YUI_PATH];
        if ($type == YUI_CSS) {
            //If this is the first css component then add the combo base path
            if ($this->cssComboLocation === null) {
                $this->cssComboLocation = $this->comboBase . $pathToModule;
            } else {
                //Prep for next component
                $this->cssComboLocation .= '&' . $pathToModule;
            }
        } else {
            //If this is the first js component then add the combo base path
            if ($this->jsComboLocation === null) {
                $this->jsComboLocation = $this->comboBase . $pathToModule;
            } else {
                //Prep for next component
                $this->jsComboLocation .= '&' . $pathToModule;
            }
        }
    }
  
    function canJSON() {
        return $this->jsonAvail;
    }

    function getProvides($name) {
        $p = array($name);
        if (isset($this->modules[$name])) {
            $m = $this->modules[$name];
            if (isset($m[YUI_SUPERSEDES])) {
                foreach ($m[YUI_SUPERSEDES] as $i) {
                    $p[] = $i;
                }
            }
        }

        return $p;
    }

    function getLoadedModules() {
        $loaded = array();
        foreach ($this->loaded as $i=>$value) {
            if (isset($this->modules[$i])) {
                $dep = $this->modules[$i];
                $loaded[$dep[YUI_TYPE]][] = array(
                        $this->getUrl($i) => $this->getProvides($i)
                    );
            } else {
                $msg = "YUI_LOADER ERROR: encountered undefined module: " . $i;
                error_log($msg, 0);
            }
        }
        return $loaded;
    }

    function getLoadedModulesAsJSON() {
        if (!$this->canJSON()) {
            return "{\"Error\", \"json library not available\"}";
        }

        return json_encode($this->getLoadedModules());
    }
}

?>
