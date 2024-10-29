<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\controllers;

use app\conf\App;
use app\exceptions\GC2Exception;
use app\inc\Controller;
use app\inc\Model;
use app\inc\UserFilter;
use app\inc\Util;
use app\inc\Input;
use app\models\Geofence;
use app\models\Rule;
use mapObj;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use XML_Unserializer;

include __DIR__ . "/../libs/PEAR/XML/Unserializer.php";


/**
 * Class Wms
 * @package app\controllers
 */
class Wms extends Controller
{
    public null|string $service;
    private array $layers;
    private null|string $user;
    private Rule $rules;
    private null|string $subUser;

    /**
     * Wms constructor.
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     */
    function __construct()
    {
        parent::__construct();

        header("Cache-Control: no-store");

        $this->layers = [];
        $this->rules = new Rule();
        $postgisschema = Input::getPath()->part(3);
        $db = Input::getPath()->part(2);
        $this->user = $db;
        $dbSplit = explode("@", $db);
        $this->service = null;
        if (sizeof($dbSplit) == 2) {
            $subUser = $dbSplit[0];
            $db = $dbSplit[1];
        } else {
            $subUser = null;
        }
        $this->subUser = $subUser;

        $trusted = false;
        foreach (App::$param["trustedAddresses"] as $address) {
            if (Util::ipInRange(Util::clientIp(), $address)) {
                $trusted = true;
                break;
            }
        }

        // Both WMS and WFS can use GET
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            foreach ($_GET as $k => $v) {
                // Get the layer names from either WMS (layer) or WFS (typename)
                if (strtolower($k) == "layers" || strtolower($k) == "layer" || strtolower($k) == "typename" || strtolower($k) == "typenames") {
                    $this->layers[] = $v;
                }
                // Get the service. wms, wfs or UTFGRID
                if (strtolower($k) == "service") {
                    $this->service = strtolower($v);
                } elseif (strtolower($k) == "format" && ($v == "json" || $v == "mvt")) {
                    $this->service = "utfgrid";
                }
            }
            // If IP not trusted, when check auth on layers
            if (!$trusted) {
                foreach ($this->layers as $layer) {
                    // Strip name space if any
                    $layer = sizeof(explode(":", $layer)) > 1 ? explode(":", $layer)[1] : $layer;
                    $this->basicHttpAuthLayer($layer, $db, $subUser);
                }
            }
            $this->get($db, $postgisschema);
        }
        // Only WFS uses POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Parse the XML request
            $unserializer = new XML_Unserializer(['parseAttributes' => TRUE, 'typeHints' => FALSE]);
            $request = Input::get(null, true);
            $unserializer->unserialize($request);
            $arr = $unserializer->getUnserializedData();
            // Get service. Only WFS for now
            $this->service = strtolower($arr["service"]);
            $typeName = !empty($arr["wfs:Query"]["typeName"]) ? $arr["wfs:Query"]["typeName"] : $arr["Query"]["typeName"];

            if (empty($typeName)) {
                $typeName = !empty($arr["wfs:Query"]["typeNames"]) ? $arr["wfs:Query"]["typeNames"] : $arr["Query"]["typeNames"];
            }
            // In case of a POST DescribeFeatureType
            if (empty($typeName)) {
                $typeName = !empty($arr["wfs:TypeName"]) ? $arr["wfs:TypeName"] : $arr["TypeName"];
            }
            if (empty($typeName)) {
                self::report("Could not get the typeName from the requests");
            }
            // Strip name space if any
            $layer = sizeof(explode(":", $typeName)) > 1 ? explode(":", $typeName)[1] : $typeName;
            // If IP not trusted, when check auth on layer
            if (!$trusted) {
                $this->basicHttpAuthLayer($layer, $db, $subUser);
            }
            $this->post($db, $postgisschema, $request, $typeName);
        }
    }

    /**
     * @param string $string
     * @return string
     */
    private static function xmlEscape(string $string): string
    {
        return str_replace(array('&', '<', '>', '\'', '"', '/'), array('\&amp;', '\&lt;', '\&gt;', '\&apos;', '\&quot;', '\/'), $string);
    }

    /**
     * @param string $db
     * @param string $postgisschema
     * @param array $layers
     * @return string|false
     */
    private function getQGSFilePath(string $db, string $postgisschema, array $layers): string|false
    {
        $path = App::$param['path'] . "/app/wms/mapfiles/";
        $mapFile = $db . "_" . $postgisschema . "_wms.map";
        $qgsFile = null;
        foreach ($layers as $layer) {
            if (file_exists($path . $mapFile)) {
                $map = new mapObj($path . $mapFile, null);
                $layer = $map->getLayerByName($layer);
                if (empty($layer->connection)) {
                    break;
                }
                $conn = $layer->connection;
                $par = parse_url($conn);
                if (!empty($par["query"])) {
                    parse_str($par["query"], $result);
                    if (!empty($result["map"]) && explode(".", $result["map"])[1] == "qgs") {
                        $qgsFile = $result["map"];
                        break;
                    }
                }
            }
        }
        return $qgsFile ?: false;
    }

    /**
     * @param string $db
     * @param string $postgisschema
     * @param array $layers
     * @return string|false
     */
    private function getWmsSource(string $db, string $postgisschema, array $layers): array|false
    {
        if (sizeof($layers) > 1) {
            return false;
        }
        $source = null;
        $path = App::$param['path'] . "/app/wms/mapfiles/";
        $mapFile = $db . "_" . $postgisschema . "_wms.map";
        $layer = $layers[0];
        if (file_exists($path . $mapFile)) {
            $map = new mapObj($path . $mapFile, null);
            $layer = $map->getLayerByName($layer);
            if (empty($layer->connection)) {
                return false;
            }
            // If connect starts with
            if (str_starts_with($layer->connection, 'http')) {
                $conn = $layer->connection;
                $par = parse_url($conn);
                parse_str($par["query"], $result);
                $source = $par;
                $source['query'] = array_change_key_case($result, CASE_UPPER);
            }
        }
        return $source ?: false;

    }

    /**
     * @param $db string
     * @param $postgisschema string
     * @throws PhpfastcacheInvalidArgumentException|GC2Exception
     */
    private function get(string $db, string $postgisschema): never
    {
        $layers = [];
        $model = new Model();
        $useFilters = false;
        if (sizeof($this->layers) > 0) {
            $layers = explode(",", $this->layers[0]);
        }
        $filters = isset($_GET["filters"]) ? json_decode(Util::base64urlDecode($_GET["filters"]), true) : [];
        $qgs = $this->getQGSFilePath($db, $postgisschema, $layers);
        // Filters and multiple layers are a no-go, because layers can be defined in different QGS files.
        if ($filters && $qgs && sizeof($layers) > 1) {
            self::report("One or more layers are served by QGIS Server. Filters don't work with multiple layers, where one or more is QGIS backed.");
        }
        // If multiple layers, then always use MapFile.
        if (sizeof($layers) > 1) {
            $qgs = false;
        }
        // Check rules
        $filters = $this->setFilterFromRules($layers, $filters);
        // Check if WMS filters are set
        if ($filters || (isset($_GET["labels"]) && $_GET["labels"] == "false")) {
            // Parse filter. Both base64 and base64url is tried
            $name = md5(rand(1, 999999999) . microtime());
            $disableLabels = isset($_GET["labels"]) && $_GET["labels"] == "false";
            // If QGIS is used
            if ($qgs && sizeof($layers) == 1) {
                // Read the file
                $file = fopen($qgs, "r");
                $str = fread($file, filesize($qgs));
                fclose($file);
                // Write out a tmp MapFile
                $mapFile = "/var/www/geocloud2/app/tmp/$name.qgs";
                $newMapFile = fopen($mapFile, "w");
                fwrite($newMapFile, $str);
                fclose($newMapFile);
                foreach ($layers as $layer) {
                    $split = explode(".", $layer);
                    $versionWhere = $model->doesColumnExist("$split[0].$split[1]", "gc2_version_gid")["exists"] ? "gc2_version_end_date IS NULL" : "";
                    $where = "1=1";
                    if ($filters) {
                        $useFilters = true;
                        if (!empty($filters[$layer])) {
                            $where = implode(" AND ", $filters[$layer]);
                        }
                    }
                    if ($versionWhere) {
                        $where = "($where AND $versionWhere)";
                    }
                    if (!empty($where)) {
                        $sedCmd = 'sed -i "/table=\"' . $split[0] . '\".\"' . $split[1] . '\"/s/sql=.*</sql=' . self::xmlEscape($where) . '</g" ' . $mapFile;
                        shell_exec($sedCmd);
                    }
                }
                if ($disableLabels) {
                    $useFilters = true;
                    $sedCmd = 'sed -i "s/labelsEnabled=\"1\"/labelsEnabled=\"0\"/g" ' . $mapFile;
                    shell_exec($sedCmd);
                }
                $url = "http://127.0.0.1/cgi-bin/qgis_mapserv.fcgi?map=$mapFile&" . $_SERVER["QUERY_STRING"];
            } // MapServer is used
            else {
                $mapFile = match ($this->service) {
                    "utfgrid", "wfs" => $db . "_" . $postgisschema . "_wfs.map",
                    default => $db . "_" . $postgisschema . "_wms.map",
                };
                $path = "/var/www/geocloud2/app/wms/mapfiles/$mapFile";
                // Write out a tmp MapFile
                $tmpMapFile = $this->writeTmpMapFile($path);
                $split = [];
                foreach ($layers as $layer) {
                    $layer = sizeof(explode(":", $layer)) > 1 ? explode(":", $layer)[1] : $layer;
                    $split = explode(".", $layer);
                    if (!empty($filters[$layer])) {
                        $useFilters = true;
                        // Use sed to replace sql= parameter
                        $where = implode(" AND ", $filters[$layer]);
                        $sedCmd = 'sed -i "s;/\*FILTER_' . $split[0] . '.' . $split[1] . '\*/;WHERE ' . $where . ';g" ' . $tmpMapFile;
                        shell_exec($sedCmd);
                    }
                }
                if ($disableLabels) {
                    $useFilters = true;
                    $sedCmd = 'sed -i "/#START_LABEL1_' . $split[0] . '.' . $split[1] . '/,/#END_LABEL1_' . $split[0] . '.' . $split[1] . '/c\ " ' . $tmpMapFile;
                    shell_exec($sedCmd);
                    $sedCmd = 'sed -i "/#START_LABEL2_' . $split[0] . '.' . $split[1] . '/,/#END_LABEL2_' . $split[0] . '.' . $split[1] . '/c\ " ' . $tmpMapFile;
                    shell_exec($sedCmd);
                }
                $url = "http://127.0.0.1/cgi-bin/mapserv.fcgi?map=$tmpMapFile&{$_SERVER["QUERY_STRING"]}";
            }
        }
        if (!$useFilters) {
            // Set MapFile for either WMS or WFS
            if ($qgs && $this->service != "utfgrid") {
                $url = "http://127.0.0.1/cgi-bin/qgis_mapserv.fcgi?map=$qgs&{$_SERVER["QUERY_STRING"]}";
            } else {
                $mapFile = match ($this->service) {
                    "utfgrid", "wfs" => $db . "_" . $postgisschema . "_wfs.map",
                    default => $db . "_" . $postgisschema . "_wms.map",
                };
                $useWmsSource = false;
                if ($source = $this->getWmsSource($db, $postgisschema, $layers)) {
                    parse_str($_SERVER["QUERY_STRING"], $query);
                    $query = array_change_key_case($query, CASE_UPPER);
                    // Use parameters from WMS source if set and use those from query for not set parameters
                    $mergedQuery = array_merge($query, $source['query']);
                    // Always use these from the query
                    $mergedQuery['BBOX'] = $query['BBOX'];
                    $mergedQuery['WIDTH'] = $query['WIDTH'];
                    $mergedQuery['HEIGHT'] = $query['HEIGHT'];
                    // Set REQUEST TO GetMap
                    $mergedQuery['REQUEST'] = 'GetMap';
                    $url = $source['scheme'] . "://" . $source['host'] . $source['path'] . '?' . http_build_query($mergedQuery);
                    $useWmsSource = true;
                }
                if (!$useWmsSource) {
                    $url = "http://127.0.0.1/cgi-bin/mapserv.fcgi?map=/var/www/geocloud2/app/wms/mapfiles/$mapFile&{$_SERVER["QUERY_STRING"]}";
                }
            }
        }
        if (!isset($url)) {
            echo "Could not create internal URL to MapServer";
            exit();
        }
        header("X-Powered-By: GC2 WMS");
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header_line) {
            $bits = explode(":", $header_line);
            // Send text/xml instead of application/vnd.ogc.se_xml
            if (sizeof($bits) > 1 && $bits[0] == "Content-Type" && trim($bits[1]) == "application/vnd.ogc.se_xml") {
                header("Content-Type: text/xml");
            } elseif (sizeof($bits) > 1 && $bits[0] == "Content-Type" && trim($bits[1]) == "text/xml; charset=UTF-8") {
                header("Content-Type: text/xml");
            } elseif (sizeof($bits) > 1 && $bits[0] != "Content-Encoding" && trim($bits[1]) != "chunked") {
                header($header_line);
            }
            return strlen($header_line);
        });
        $content = curl_exec($ch);
        $content = str_replace("__USER__", $this->user, $content);
        curl_close($ch);
        echo $content;
        exit();
    }

    /**
     * @param string $db
     * @param string $postgisschema
     * @param string $data
     * @param string $layer
     * @return never
     * @throws GC2Exception
     */
    private function post(string $db, string $postgisschema, string $data, string $layer): never
    {
        // Check rules
        $filters = [];
        $layers[] = $layer;
        $filters = $this->setFilterFromRules($layers, $filters);
        // Set MapFile. For now this can only be WFS
        $mapFile = match ($this->service) {
            "wfs" => $db . "_" . $postgisschema . "_wfs.map",
            default => $db . "_" . $postgisschema . "_wms.map",
        };
        if (sizeof($filters) > 0) {
            $path = "/var/www/geocloud2/app/wms/mapfiles/$mapFile";
            // Write out a tmp MapFile
            $tmpMapFile = $this->writeTmpMapFile($path);
            foreach ($layers as $layer) {
                $layer = sizeof(explode(":", $layer)) > 1 ? explode(":", $layer)[1] : $layer;
                $split = explode(".", $layer);
                if (!empty($filters[$layer])) {
                    // Use sed to replace sql= parameter
                    $where = implode(" AND ", $filters[$layer]);
                    $sedCmd = 'sed -i "s;/\*FILTER_' . $split[0] . '.' . $split[1] . '\*/;WHERE ' . $where . ';g" ' . $tmpMapFile;
                    shell_exec($sedCmd);
                }
            }
            $url = "http://127.0.0.1/cgi-bin/mapserv.fcgi?map=$tmpMapFile";
        } else {
            $url = "http://127.0.0.1/cgi-bin/mapserv.fcgi?map=/var/www/geocloud2/app/wms/mapfiles/$mapFile";
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: text/xml',
                'Content-Length: ' . strlen($data))
        );
        $content = curl_exec($ch);
        header("Content-Type: text/xml");
        echo $content;
        exit();
    }

    /**
     * @param string $value
     */
    private static function report(string $value): never
    {
        ob_get_clean();
        ob_start();
        echo '<ServiceExceptionReport
                       version="1.2.0"
                       xmlns="http://www.opengis.net/ogc"
                       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                       xsi:schemaLocation="http://www.opengis.net/ogc https://schemas.opengis.net/ows/1.0.0/owsExceptionReport.xsd">
                       <ServiceException code="InvalidParameterValue">';
        print $value;
        echo '</ServiceException>
	        </ServiceExceptionReport>';
        header("HTTP/1.0 200 " . Util::httpCodeText("200"));
        header('Content-Type:text/xml; charset=UTF-8');
        exit();
    }

    /**
     * @param array $layers
     * @param array $filters
     * @return array
     * @throws GC2Exception
     */
    private function setFilterFromRules(array $layers, array $filters): array
    {
        $rules = $this->rules->get();
        foreach ($layers as $layer) {
            $layer = sizeof(explode(":", $layer)) > 1 ? explode(":", $layer)[1] : $layer;
            $split = explode(".", $layer);
            $userFilter = new UserFilter($this->isAuth() ? $this->subUser ?: $this->user : "*", "ows", "select", "*", $split[0], $split[1]);
            $geofence = new Geofence($userFilter);
            $auth = $geofence->authorize($rules);
            if (isset($auth["access"])) {
                if ($auth["access"] == "deny") {
                    self::report("DENY");
                } elseif ($auth["access"] == "limit" && !empty($auth["filters"]["filter"])) {
                    $filters[$layer][] = "({$auth["filters"]["filter"]})";
                }
            }
        }
        return $filters;
    }

    private function isAuth(): bool
    {
        global $user;
        $auth = false;
        $sess = $_SESSION;
        if (isset($_SESSION) && sizeof($_SESSION) > 0) {
            if ($user == $sess["screen_name"]) {
                $auth = true;
            } elseif (!empty($sess["http_auth"]) && ($user == $sess["http_auth"])) {
                $auth = true;
            }
        } elseif (isset($_SERVER['PHP_AUTH_USER'])) {
            $user = explode("@", $_SERVER['PHP_AUTH_USER'])[0];
            $auth = true;
        }
        return $auth;
    }

    /**
     * @param string $path
     * @return string
     */
    private function writeTmpMapFile(string $path): string
    {
        // Read the file
        $file = fopen($path, "r");
        $str = fread($file, filesize($path));
        fclose($file);
        // Write out a tmp MapFile
        $name = md5(rand(1, 999999999) . microtime());
        $tmpMapFile = "/var/www/geocloud2/app/tmp/$name.map";
        $newMapFile = fopen($tmpMapFile, "w");
        fwrite($newMapFile, $str);
        fclose($newMapFile);
        return $tmpMapFile;
    }
}
