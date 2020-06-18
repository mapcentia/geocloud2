<?php
/**
 * Long description for file
 *
 * Long description for file (if any)...
 *
 * @category   API
 * @package    app\api\v1
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 * @since      File available since Release 2013.1
 *
 */

namespace app\api\v2;

use \app\inc\Input;
use \app\inc\Route;
use \app\inc\Response;
use \app\models\Database;
use \app\models\Layer;
use \GuzzleHttp\Client;

include_once(__DIR__ . "../../../vendor/phayes/geophp/geoPHP.inc");
include_once(__DIR__ . "../../../libs/phpgeometry_class_namespace.php");
include_once(__DIR__ . "../../../libs/gmlparser.php");
include_once(__DIR__ . "../../../libs/PEAR/XML/Unserializer.php");
include_once(__DIR__ . "../../../libs/PEAR/XML/Serializer.php");

/**
 * Class Feature
 * @package app\api\v2
 */
class Feature extends \app\inc\Controller
{
    private $transactionHeader;
    private $geometryfactory;
    private $sourceSrid;
    private $wfsUrl;
    private $db;
    private $schema;
    private $table;
    private $geom;
    private $field;
    private $key;
    private $user;
    private $client;

    /**
     * Feature constructor.
     */
    function __construct()
    {

        parent::__construct();

        $this->client = new Client([
            'timeout' => 100.0,
        ]);

        // Set properties
        $this->wfsUrl = "http://127.0.0.1/wfs/%s/%s/%s";
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

        // Check privileges of user on layer
        $rel = $this->schema . "." . $this->table;
        try {
            $response = $this->ApiKeyAuthLayer($rel, $this->sUser, true, Input::getApiKey(), [$rel]);
        } catch (\PDOException $e) {
            header("HTTP/1.1 401 Unauthorized");
            die($e->getMessage());
        }

        if (!$response["success"]) {
            header("HTTP/1.1 401 Unauthorized");
            die(Response::toJson($response));
        }

        $layer = new Layer();
        $this->field = $layer->getAll(Route::getParam("layer"), true, false, false, false, $this->db)["data"][0]["pkey"];

        // Init geometryfactory
        $this->geometryfactory = new \mapcentia\geometryfactory();

        // Set transaction xml header
        $this->transactionHeader = "<wfs:Transaction xmlns:wfs=\"http://www.opengis.net/wfs\" service=\"WFS\" version=\"1.0.0\"
                 xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
                 xsi:schemaLocation=\"http://www.opengis.net/wfs http://schemas.opengis.net/wfs/1.0.0/WFS-transaction.xsd\">\n";
    }

    private function decodeCode($code){
        return preg_replace_callback(
            "@\\\(x)?([0-9a-f]{2,3})@",
            function($m){
                return chr($m[1]?hexdec($m[2]):octdec($m[2]));
            },
            $code
        );
    }

    public function get_index()
    {
        $response = [];

        $unserializer = new \XML_Unserializer(array(
            'parseAttributes' => false,
            'typeHints' => false
        ));

        $url = sprintf($this->wfsUrl, $this->user, $this->schema, $this->sourceSrid);

        // GET the transaction
        try {
            $res = $this->client->get($url . "?request=GetFeature&typeName={$this->table}&FEATUREID={$this->table}.{$this->key}");
        } catch (\Exception $e) {
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
        if (isset($arr["ServiceException"])) {
            $response['success'] = false;
            $response['message'] = $arr;
            $response['code'] = "500";
            return $response;
        }

        // Convert GML to WKT
        $gmlConverter = new \mapcentia\gmlConverter();
        $wkt = $gmlConverter->gmlToWKT($xml)[0][0];

        // Convert WKT to GeoJSON
        if ($wkt) {
            try {
                $json = \geoPHP::load($wkt, 'wkt')->out('json');
            } catch (\Exception $e) {
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = "500";
                return $response;
            } catch (\Error $e) {
                $response['success'] = false;
                $response['message'] = $e->getMessage();
                $response['code'] = "500";
                return $response;
            }
       }

        foreach ($arr["gml:featureMember"][$this->db . ":" . $this->table] as $key => $prop) {
            if (!is_array($prop)){
                $props[ explode(":", $key)[1]] = $prop;
            }
        }

        $jArr = [
            "type"=>"FeatureCollection",
            "features" => [[
                "type" => "Feature",
                "properties" => $props,
                "geometry" => json_decode($json)
            ]]
        ];

        return $jArr;
    }

    /**
     * @return array
     */
    public function post_index(): array
    {

        // Decode GeoJSON
        if (!$features = json_decode($this->decodeCode(Input::getBody()), true)["features"]) {
            $response['success'] = false;
            $response['message'] = "Could not decode GeoJSON";
            $response['code'] = 500;
            return $response;
        }

        // Start build the WFS transaction
        $xml = $this->transactionHeader;

        // Loop through features
        foreach ($features as $feature) {

            // Get properties
            $props = $feature["properties"];

            // Create the Insert section
            $xml .= "<wfs:Insert>\n";
            $xml .= "<feature:{$this->table} xmlns:feature=\"http://mapcentia.com/{$this->db}\">\n";

            try {
                // Get GML from WKT geom and catch error if geom is missing
                $wkt = \geoPHP::load(json_encode($feature), 'json')->out('wkt');
                $xml .= "<feature:{$this->geom}>\n";
                $xml .= $this->geometryfactory->createGeometry($wkt, "EPSG:" . $this->sourceSrid)->getGML();
                $xml .= "</feature:{$this->geom}>\n";
            } catch (\Exception $e) {
                // Pass. Geom is not required
            }

            // Create the elements
            foreach ($props as $elem => $value) {
                if (is_string($value)) {
                    $value = "<![CDATA[{$value}]]>";
                }
                $xml .= "<feature:{$elem}>{$value}</feature:{$elem}>\n";
            }

            $xml .= "</feature:{$this->table}>\n";
            $xml .= "</wfs:Insert>\n";

        }
        $xml .= "</wfs:Transaction>\n";
        return $this->commit($xml);
    }

    /**
     * @return array
     */
    public function put_index(): array
    {

        // Decode GeoJSON
        if (!$features = json_decode($this->decodeCode(Input::getBody()), true)["features"]) {
            $response['success'] = false;
            $response['message'] = "Could not decode GeoJSON";
            $response['code'] = 500;
            return $response;
        }

        // Start build the WFS transaction
        $xml = $this->transactionHeader;

        // Loop through features
        foreach ($features as $feature) {

            // Get properties
            $props = $feature["properties"];

            // Check if property with primary key is missing
            if (!isset($props[$this->field])){
                $response['success'] = false;
                $response['message'] = "Property with primary key is missing from at least one GeoJSON feature";
                $response['code'] = 500;
                return $response;
            }

            // Create the Insert section
            $xml .= "<wfs:Update typeName=\"{$this->db}:{$this->table}\">\n";

            // Get GML from WKT geom and catch error if geom is missing
            try {
                $wkt = \geoPHP::load(json_encode($feature), 'json')->out('wkt');
                $xml .= "<wfs:Property>\n";
                $xml .= "<wfs:Name>{$this->geom}</wfs:Name>\n";
                $xml .= "<wfs:Value>\n";
                $xml .= $this->geometryfactory->createGeometry($wkt, "EPSG:" . $this->sourceSrid)->getGML();
                $xml .= "</wfs:Value>\n";
                $xml .= "</wfs:Property>\n";
            } catch (\Exception $e) {
                // Pass. Geom is not required
            }

            // Create the elements
            foreach ($props as $elem => $value) {
                if (is_string($value)) {
                    $value = "<![CDATA[{$value}]]>";
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
     */
    public function delete_index(): array
    {

        // Start build the WFS transaction
        $xml = $this->transactionHeader;

        $xml.="<wfs:Delete typeName=\"{$this->db}:{$this->table}\" xmlns:{$this->db}=\"http://mapcentia.com/{$this->db}\">";
        $xml.="<ogc:Filter xmlns:ogc=\"http://www.opengis.net/ogc\">";
        $xml.="<ogc:FeatureId fid=\"{$this->table}.{$this->key}\"/>";
        $xml.="</ogc:Filter>";
        $xml.="</wfs:Delete>";

        $xml .= "</wfs:Transaction>\n";

        return $this->commit($xml);
    }

    /**
     * @param $xml
     * @return array
     */
    private function commit(string $xml) : array
    {
        //echo $xml;
        $response = [];

        $unserializer = new \XML_Unserializer(array(
            'parseAttributes' => TRUE,
            'typeHints' => FALSE
        ));

        $url = sprintf($this->wfsUrl, $this->user, $this->schema, $this->sourceSrid);

        // POST the transaction
        try {
            $res = $this->client->post($url, ['body' => $xml]);
        } catch (\Exception $e) {
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
        if (isset($arr["ServiceException"])) {
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
