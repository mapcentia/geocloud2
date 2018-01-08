<?php

namespace app\api\v2;

use \app\inc\Input;
use \app\inc\Route;
use app\models\Layer;
use \GuzzleHttp\Client;
use \mapcentia;

include_once(__DIR__ . "../../../vendor/phayes/geophp/geoPHP.inc");
include_once(__DIR__ . "../../../libs/phpgeometry_class_namespace.php");
include_once(__DIR__ . "../../../libs/PEAR/XML/Unserializer.php");
include_once(__DIR__ . "../../../libs/PEAR/XML/Serializer.php");

/**
 * Class Feature
 * @package app\api\v2
 */
class Feature extends \app\inc\Controller
{
    private $notAuth;
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

    /**
     * Feature constructor.
     */
    function __construct()
    {

        parent::__construct();

        // Check privileges of user on layer
        try {
            $response = $this->ApiKeyAuthLayer(Route::getParam("layer"), $this->sUser, true, Input::getApiKey(), [Route::getParam("layer")]);
        } catch (\PDOException $e) {
            die($e->getMessage());
        }

        if (!$response["success"]) {
            $this->notAuth = $response;
        }

        // Set properties
        $this->wfsUrl = "http://127.0.0.1/wfs/%s/%s/%s";
        $this->sourceSrid = Route::getParam("srid");
        $this->db = Route::getParam("user");
        $this->schema = explode(".", Route::getParam("layer"))[0];
        $this->table = explode(".", Route::getParam("layer"))[1];
        $this->geom = explode(".", Route::getParam("layer"))[2];
        $this->key = Route::getParam("key");

        $layer = new Layer();
        $this->field = $layer->getAll(Route::getParam("layer"), true)["data"][0]["pkey"];

        // Init geometryfactory
        $this->geometryfactory = new mapcentia\geometryfactory();

        // Set transaction xml header
        $this->transactionHeader = "<wfs:Transaction xmlns:wfs=\"http://www.opengis.net/wfs\" service=\"WFS\" version=\"1.0.0\"
                 xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
                 xsi:schemaLocation=\"http://www.opengis.net/wfs http://schemas.opengis.net/wfs/1.0.0/WFS-transaction.xsd\">\n";
    }

    public function get_index()
    {
        if ($this->notAuth) {
            return $this->notAuth;
        }
    }

    /**
     * @return array
     */
    public function post_index(): array
    {
        // Return if not auth
        if ($this->notAuth) {
            return $this->notAuth;
        }

        // Decode GeoJSON
        if (!$features = json_decode(Input::getBody(), true)["features"]) {
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

            // Create the properties
            foreach ($props as $elem => $prop) {
                $xml .= "<feature:{$elem}>{$prop}</feature:{$elem}>\n";
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
        // Return if not auth
        if ($this->notAuth) {
            return $this->notAuth;
        }

        // Decode GeoJSON
        if (!$features = json_decode(Input::getBody(), true)["features"]) {
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

            // Create the properties
            foreach ($props as $elem => $prop) {
                $xml .= "<wfs:Property>\n";
                $xml .= "<wfs:Name>{$elem}</wfs:Name>\n";
                $xml .= "<wfs:Value>{$prop}</wfs:Value>\n";
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
        // Return if not auth
        if ($this->notAuth) {
            return $this->notAuth;
        }

        // Start build the WFS transaction
        $xml = $this->transactionHeader;

        $xml.="<wfs:Delete typeName=\"{$this->db}:test\" xmlns:{$this->db}=\"http://mapcentia.com/{$this->db}\">";
        $xml.="<ogc:Filter xmlns:ogc=\"http://www.opengis.net/ogc\">";
        $xml.="<ogc:FeatureId fid=\"test.{$this->key}\"/>";
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

        $url = sprintf($this->wfsUrl, $this->db, $this->schema, $this->sourceSrid);

        // Init the Guzzle client
        $client = new Client([
            'timeout' => 10.0,
        ]);

        // POST the transaction
        try {
            $res = $client->post($url, ['body' => $xml]);
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