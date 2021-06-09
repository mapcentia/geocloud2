<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\controllers;

use app\conf\App;
use app\inc\Controller;
use app\inc\Model;
use app\inc\Util;
use app\inc\Input;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use XML_Unserializer;

include __DIR__ . "/../libs/PEAR/XML/Unserializer.php";


/**
 * Class Wms
 * @package app\controllers
 */
class Wms extends Controller
{
    public $service;
    private $layers;
    private $type;
    private $user;

    /**
     * Wms constructor.
     * @throws PhpfastcacheInvalidArgumentException
     */
    function __construct()
    {
        parent::__construct();

        header("Cache-Control: no-store");

        $this->layers = [];
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
            if (empty($typeName)) {
                self::report("Could not get the typeName from the requests");
            }

            // Strip name space if any
            $layer = sizeof(explode(":", $typeName)) > 1 ? explode(":", $typeName)[1] : $typeName;

            // If IP not trusted, when check auth on layer
            if (!$trusted) {
                $this->basicHttpAuthLayer($layer, $db, $subUser);
            }
            $this->post($db, $postgisschema, $request);
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
     * @param $db string
     * @param $postgisschema string
     * @throws PhpfastcacheInvalidArgumentException
     */
    private function get(string $db, string $postgisschema): void
    {
        $model = new Model();
        $useFilters = false;
        $qgs = isset($_GET["qgs"]) ? base64_decode($_GET["qgs"]) : false;
        // Check if WMS filters are set
        if ((isset($_GET["filters"]) || (isset($_GET["labels"]) && $_GET["labels"] == "false")) && $this->service == "wms") {
            // Parse filter. Both base64 and base64url is tried
            $filters = isset($_GET["filters"]) ? json_decode(Util::base64urlDecode($_GET["filters"]), true) : null;
            $layer = $this->layers[0];
            $split = explode(".", $layer);
            $name = md5(rand(1, 999999999) . microtime());
            $disableLabels = isset($_GET["labels"]) && $_GET["labels"] == "false";

            // If QGIS is used
            if ($qgs) {
                $e = $qgs;
                if ($e) {
                    // Read the file
                    $file = fopen($e, "r");
                    $str = fread($file, filesize($e));
                    fclose($file);

                    // Write out a tmp MapFile
                    $mapFile = "/var/www/geocloud2/app/tmp/{$name}.qgs";
                    $newMapFile = fopen($mapFile, "w");
                    fwrite($newMapFile, $str);
                    fclose($newMapFile);

                    $versionWhere = $model->doesColumnExist("{$split[0]}.{$split[1]}", "gc2_version_gid")["exists"] ? "gc2_version_end_date IS NULL" : "";

                    if ($filters) {
                        $useFilters = true;
                        $where = implode(" OR ", $filters[$layer]);
                        if ($versionWhere) {
                            $where = "({$where} AND {$versionWhere})";
                        }
                        $sedCmd = 'sed -i "/table=\"' . $split[0] . '\".\"' . $split[1] . '\"/s/sql=.*</sql=' . self::xmlEscape($where) . '</g" ' . $mapFile;
                        shell_exec($sedCmd);
                    }
                    if ($disableLabels) {
                        $useFilters = true;
                        $sedCmd = 'sed -i "s/labelsEnabled=\"1\"/labelsEnabled=\"0\"/g" ' . $mapFile;
                        shell_exec($sedCmd);
                    }

                    $url = "http://127.0.0.1/cgi-bin/qgis_mapserv.fcgi?map={$mapFile}&" . $_SERVER["QUERY_STRING"];
                }
            } // MapServer is used
            else {
                switch ($this->service) {
                    case "wfs":
                        $mapFile = $db . "_" . $postgisschema . "_wfs.map";
                        break;

                    default:
                        $mapFile = $db . "_" . $postgisschema . "_wms.map";
                        break;
                }

                $path = "/var/www/geocloud2/app/wms/mapfiles/{$mapFile}";

                // Read the file
                $file = fopen($path, "r");
                $str = fread($file, filesize($path));
                fclose($file);

                // Write out a tmp MapFile
                $tmpMapFile = "/var/www/geocloud2/app/tmp/{$name}.map";
                $newMapFile = fopen($tmpMapFile, "w");
                fwrite($newMapFile, $str);
                fclose($newMapFile);
                if ($filters) {
                    $useFilters = true;
                    // Use sed to replace sql= parameter
                    $where = implode(" OR ", $filters[$layer]);
                    $sedCmd = 'sed -i "s;/\*FILTER_' . $split[0] . '.' . $split[1] . '\*/;WHERE ' . $where . ';g" ' . $tmpMapFile;
                    shell_exec($sedCmd);
                }
                if ($disableLabels) {
                    $useFilters = true;
                    $sedCmd = 'sed -i "/#START_LABEL1_' . $split[0] . '.' . $split[1] . '/,/#END_LABEL1_' . $split[0] . '.' . $split[1] . '/c\ " ' . $tmpMapFile;
                    shell_exec($sedCmd);
                    $sedCmd = 'sed -i "/#START_LABEL2_' . $split[0] . '.' . $split[1] . '/,/#END_LABEL2_' . $split[0] . '.' . $split[1] . '/c\ " ' . $tmpMapFile;
                    shell_exec($sedCmd);
                }
                $url = "http://127.0.0.1/cgi-bin/mapserv.fcgi?map={$tmpMapFile}&" . $_SERVER["QUERY_STRING"];
            }
        }

        if (!$useFilters) {
            // Set MapFile for either WMS or WFS
            if ($qgs) {
                $url = "http://127.0.0.1/cgi-bin/qgis_mapserv.fcgi?map={$qgs}&" . $_SERVER["QUERY_STRING"];
            } else {
                switch ($this->service) {
                    case "wfs":
                        $mapFile = $db . "_" . $postgisschema . "_wfs.map";
                        break;

                    default:
                        $mapFile = $db . "_" . $postgisschema . "_wms.map";
                        break;
                }
                $url = "http://127.0.0.1/cgi-bin/mapserv.fcgi?map=/var/www/geocloud2/app/wms/mapfiles/{$mapFile}&" . $_SERVER["QUERY_STRING"];
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
            if ($bits[0] == "Content-Type") {
                $this->type = trim($bits[1]);
            }
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

    private function post($db, $postgisschema, $data)
    {
        // Set MapFile. For now this can only be WFS
        switch ($this->service) {
            case "wfs":
                $mapFile = $db . "_" . $postgisschema . "_wfs.map";
                break;
            default:
                $mapFile = $db . "_" . $postgisschema . "_wms.map";
                break;
        }

        $url = "http://127.0.0.1/cgi-bin/mapserv.fcgi?map=/var/www/geocloud2/app/wms/mapfiles/{$mapFile}";
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
    private static function report(string $value): void
    {
        ob_get_clean();
        ob_start();
        echo '<ServiceExceptionReport
                       version="1.2.0"
                       xmlns="http://www.opengis.net/ogc"
                       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                       xsi:schemaLocation="http://www.opengis.net/ogc http://schemas.opengis.net/ows/1.0.0/owsExceptionReport.xsd">
                       <ServiceException>';
        print $value;
        echo '</ServiceException>
	        </ServiceExceptionReport>';
        header("HTTP/1.0 200 " . Util::httpCodeText("200"));
        exit();
    }
}