<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v2;

use app\inc\Controller;
use app\inc\Input;
use app\inc\Route;
use app\inc\Response;
use app\inc\Session;
use app\libs\GeometryFactory;
use app\libs\gmlConverter;
use app\models\Database;
use app\models\Layer;
use Error;
use Exception;
use geoPHP;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use PDOException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use XML_Unserializer;

include_once(__DIR__ . "../../../vendor/phayes/geophp/geoPHP.inc");
include_once(__DIR__ . "../../../libs/phpgeometry_class.php");
include_once(__DIR__ . "../../../libs/gmlparser.php");
include_once(__DIR__ . "../../../libs/PEAR/XML/Unserializer.php");
include_once(__DIR__ . "../../../libs/PEAR/XML/Serializer.php");


/**
 * Class Feature
 * @package app\api\v2
 */
class Feature extends Controller
{
    private string $transactionHeader;
    private GeometryFactory $geometryfactory;
    private ?string $sourceSrid;
    private string $wfsUrl;
    private string $db;
    private string $schema;
    private string $table;
    private string $geom;
    private mixed $field;
    private ?string $key;
    private ?string $user;
    private Client $client;

    /**
     * Feature constructor.
     * @throws PhpfastcacheInvalidArgumentException
     */
    function __construct()
    {

        parent::__construct();

        $this->client = new Client([
            'timeout' => 100.0,
        ]);

        // Set properties
        $this->wfsUrl = "http://localhost/wfs/%s/%s/%s";
        $this->sourceSrid = Route::getParam("srid");
        $this->db = Database::getDb();
        $this->schema = explode(".", Route::getParam("layer"))[0];
        $this->table = explode(".", Route::getParam("layer"))[1];
        $this->geom = explode(".", Route::getParam("layer"))[2];
        $this->key = Route::getParam("key");
        $this->user = Route::getParam("user");

        if ((!$this->schema) || (!$this->table) || (!$this->geom)) {
            $response['success'] = false;
            $response['message'] = "The layer must be in the form schema.table.geom_field";
            $response['code'] = 400;
            header("HTTP/1.1 400 Bad Request");
            die(Response::toJson($response));
        }

        $layer = new Layer();
        $this->field = $layer->getAll($this->db, true, Route::getParam("layer"), false, false, false)["data"][0]["pkey"];

        // Init geometryfactory
        $this->geometryfactory = new GeometryFactory();

        // Set transaction xml header
        $this->transactionHeader = "<wfs:Transaction xmlns:wfs=\"http://www.opengis.net/wfs\" service=\"WFS\" version=\"1.1.0\"
                 xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
                 xsi:schemaLocation=\"http://www.opengis.net/wfs http://schemas.opengis.net/wfs/1.1.0/WFS-transaction.xsd\">\n";
    }

    public function get_index(): array
    {
        $response = [];

        $unserializer = new XML_Unserializer(array(
            'parseAttributes' => false,
            'typeHints' => false
        ));

        $url = sprintf($this->wfsUrl, $this->user, $this->schema, $this->sourceSrid);

        // GET the transaction
        try {
            $res = $this->client->get($url . "?service=WFS&version=1.1.0&request=GetFeature&typeName={$this->table}&FEATUREID={$this->table}.{$this->key}");
        } catch (GuzzleException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 500;
            return $response;
        }

        $xml = (string)$res->getBody();

        // Unserialize the transaction response
        $status = $unserializer->unserialize($xml);

        // Check if transaction response could be unserialized
        if (gettype($status) != "boolean" && $status !== true) {
            $response['success'] = false;
            $response['message'] = "Could not unserialize transaction response";
            $response['code'] = 500;
            return $response;
        }

        // Get unserialized data
        $arr = $unserializer->getUnserializedData();

        // Check if WFS returned a service exception
        if (isset($arr["ows:Exception"])) {
            $response['success'] = false;
            $response['message'] = $arr;
            $response['code'] = "500";
            return $response;
        }

        // Convert GML to WKT
        $gmlConverter = new gmlConverter();
        $wkt = $gmlConverter->gmlToWKT($xml)[0][0];

        // Convert WKT to GeoJSON
        if ($wkt) {
            try {
                $json = geoPHP::load($wkt, 'wkt')->out('json');
            } catch (Exception $e) {
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = "500";
                return $response;
            } catch (Error $e) {
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = "500";
                return $response;
            }
        }

        foreach ($arr["gml:featureMembers"][$this->schema . ":" . $this->table] as $key => $prop) {
            if (!is_array($prop)) {
                $props[explode(":", $key)[1]] = $prop;
            }
        }

        return [
            "type" => "FeatureCollection",
            "features" => [[
                "type" => "Feature",
                "properties" => $props,
                "geometry" => json_decode($json)
            ]]
        ];
    }

    /**
     * @return array
     * @throws GuzzleException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws \JsonException
     */
    public function post_index(): array
    {

        // Decode GeoJSON
        $features = json_decode(Input::getBody(false), true, 512, JSON_THROW_ON_ERROR)["features"];

        // Start build the WFS transaction
        $xml = $this->transactionHeader;

        // Loop through features
        foreach ($features as $feature) {

            // Get properties
            $props = $feature["properties"];

            $gmlId = !empty($props[$this->field]) ? "gml:id=\"{$props[$this->field]}\"" : "";

            // Create the Insert section
            $xml .= "<wfs:Insert>\n";
            $xml .= "<feature:{$this->table} {$gmlId} xmlns:feature=\"http://mapcentia.com/{$this->db}/{$this->schema}\">\n";

            try {
                // Get GML from WKT geom and catch error if geom is missing
                $wkt = geoPHP::load(json_encode($feature), 'json')->out('wkt');
                $xml .= "<feature:{$this->geom}>\n";
                $xml .= $this->geometryfactory->createGeometry($wkt, "EPSG:" . $this->sourceSrid)->toGML();
                $xml .= "</feature:{$this->geom}>\n";
            } catch (Exception $e) {
                // Pass. Geom is not required
            }

            // Create the elements
            foreach ($props as $elem => $value) {
                if (isset($this->field) && $this->field != $elem) {
                    if (is_string($value)) {
                        $value = "<![CDATA[" . urldecode($value) . "]]>";
                    }
                    if ($value === false) {
                        $value = 'f';
                    }
                    if ($value === true) {
                        $value = 't';
                    }
                    $xml .= "<feature:{$elem}>{$value}</feature:{$elem}>\n";
                }
            }

            $xml .= "</feature:{$this->table}>\n";
            $xml .= "</wfs:Insert>\n";
        }
        $xml .= "</wfs:Transaction>\n";
        return $this->commit($xml);
    }

    /**
     * @return array
     * @throws GuzzleException
     * @throws \JsonException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function put_index(): array
    {

        // Decode GeoJSON
        $features = json_decode(Input::getBody(false), true, 512, JSON_THROW_ON_ERROR)["features"];

        // Start build the WFS transaction
        $xml = $this->transactionHeader;

        // Loop through features
        foreach ($features as $feature) {

            // Get properties
            $props = $feature["properties"];

            // Check if property with primary key is missing
            if (!isset($props[$this->field])) {
                $response['success'] = false;
                $response['message'] = "Property with primary key is missing from at least one GeoJSON feature";
                $response['code'] = 500;
                return $response;
            }

            // Create the Insert section
            $xml .= "<wfs:Update typeName=\"{$this->schema}:{$this->table}\">\n";

            // Get GML from WKT geom and catch error if geom is missing
            try {
                $wkt = geoPHP::load(json_encode($feature), 'json')->out('wkt');
                $xml .= "<wfs:Property>\n";
                $xml .= "<wfs:Name>{$this->geom}</wfs:Name>\n";
                $xml .= "<wfs:Value>\n";
                $xml .= $this->geometryfactory->createGeometry($wkt, "EPSG:" . $this->sourceSrid)->toGML();
                $xml .= "</wfs:Value>\n";
                $xml .= "</wfs:Property>\n";
            } catch (Exception $e) {
                // Pass. Geom is not required
            }

            // Create the elements
            foreach ($props as $elem => $value) {
                if (is_string($value)) {
                    $value = "<![CDATA[" . urldecode($value) . "]]>";
                }
                if ($value === false) {
                    $value = 'f';
                }
                if ($value === true) {
                    $value = 't';
                }
                $xml .= "<wfs:Property>\n";
                $xml .= "<wfs:Name>{$elem}</wfs:Name>\n";
                $xml .= "<wfs:Value>{$value}</wfs:Value>\n";
                $xml .= "</wfs:Property>\n";
            }

            // Filter
            $xml .= "<ogc:Filter xmlns:ogc=\"http://www.opengis.net/ogc\">";
            $xml .= "<ogc:FeatureId fid=\"{$this->table}." . $props[$this->field] . "\"/>";
            $xml .= "</ogc:Filter>\n";

            // Close update
            $xml .= "</wfs:Update>\n";

        }
        $xml .= "</wfs:Transaction>\n";

        return $this->commit($xml);
    }

    /**
     * @return array
     * @throws GuzzleException
     */
    public function delete_index(): array
    {
        // Start build the WFS transaction
        $xml = $this->transactionHeader;

        $xml .= "<wfs:Delete typeName=\"{$this->schema}:{$this->table}\" xmlns:{$this->schema}=\"http://mapcentia.com/{$this->db}/{$this->schema}\">";
        $xml .= "<ogc:Filter xmlns:ogc=\"http://www.opengis.net/ogc\">";
        $xml .= "<ogc:FeatureId fid=\"{$this->table}.{$this->key}\"/>";
        $xml .= "</ogc:Filter>";
        $xml .= "</wfs:Delete>";

        $xml .= "</wfs:Transaction>\n";

        return $this->commit($xml);
    }

    /**
     * @param string $xml
     * @return array
     * @throws GuzzleException
     * @throws PhpfastcacheInvalidArgumentException
     */
    private function commit(string $xml): array
    {
        $split = explode("@", $this->user);
        if (sizeof($split) > 1) {
            $user = $split[0];
            $db = $split[1];
        } else {
            $user = $db = $this->user;
        }
        // Check privileges of user on layer
        $rel = $this->schema . "." . $this->table;
        try {
            $response = $this->ApiKeyAuthLayer($rel, true, [$rel], sizeof($split) > 1 ? $user : null, Input::getApiKey());
        } catch (PDOException $e) {
            header("HTTP/1.1 401 Unauthorized");
            die($e->getMessage());
        }

        if (!$response["success"]) {
            header("HTTP/1.1 401 Unauthorized");
            die(Response::toJson($response));
        }
        $response = [];

        $unserializer = new XML_Unserializer(array(
            'parseAttributes' => true,
            'typeHints' => false,
        ));

        $url = sprintf($this->wfsUrl, $this->user, $this->schema, $this->sourceSrid);

        if (empty(Input::getCookies()["PHPSESSID"])) {
            Session::start();
            Session::set("auth", true);
            Session::set("screen_name", $user);
            Session::set("parentdb", $db);
            Session::set("subuser", sizeof($split) > 1);
            Session::write();
            $id = Session::getId();
        } else {
            $id = Input::getCookies()["PHPSESSID"];
        }
        // POST the transaction
        try {
            $res = $this->client->post($url, ['body' => $xml, 'cookies' => CookieJar::fromArray([
                "PHPSESSID" => $id,
                "XDEBUG_SESSION" => "XDEBUG_ECLIPSE"
            ], 'localhost')]);
        } catch (Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 500;
            return $response;
        }

        $transactionResponse = $res->getBody();

        // Unserialize the transaction response
        $status = $unserializer->unserialize($transactionResponse);

        // Check if transaction response could be unserialized
        if (gettype($status) != "boolean" && $status !== true) {
            $response['success'] = false;
            $response['message'] = "Could not unserialize transaction response";
            $response['code'] = 500;
            return $response;
        }

        $arr = $unserializer->getUnserializedData();

        // Check if WFS returned a service exception
        if (isset($arr["ows:Exception"])) {
            $response['success'] = false;
            $response['message'] = $arr;
            $response['code'] = "500";
            return $response;
        }

        $response['success'] = true;
        $response['message'] = $arr;
        return $response;
    }
}
