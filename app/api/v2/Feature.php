<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v2;

use app\conf\App;
use app\inc\Connection;
use app\inc\Controller;
use app\inc\Input;
use app\inc\Route;
use app\inc\Response;
use app\inc\Session;
use app\inc\Util;
use app\inc\geometry\GeometryFactory;
use app\inc\geometry\GmlConverter;
use app\models\Database;
use app\models\Layer;
use Error;
use Exception;
use app\wfs\Context;
use app\wfs\Request as WfsRequest;
use app\wfs\Server;
use app\wfs\output\ExceptionReport;
use app\wfs\output\GmlWriter;
use geoPHP;
use PDOException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Throwable;
use XML_Unserializer;
use function app\wfs\authorizeLayers;

include_once(__DIR__ . "../../../vendor/phayes/geophp/geoPHP.inc");
include_once(__DIR__ . "../../../libs/PEAR/XML/Unserializer.php");
include_once(__DIR__ . "../../../libs/PEAR/XML/Serializer.php");
// The legacy WFS bootstrap, for its per-layer authorizeLayers()
include_once(__DIR__ . "/../../wfs/server.php");


/**
 * Class Feature
 *
 * Drives the in-process WFS engine (app\wfs\Server) the way the v4 Feature API does,
 * instead of requesting http://localhost/wfs/... over Guzzle. The engine gets the
 * identity that internal HTTP request had: the session it carried (none for reads),
 * no Basic credentials, and localhost as the client address.
 *
 * @package app\api\v2
 */
class Feature extends Controller
{
    private string $transactionHeader;
    private GeometryFactory $geometryfactory;
    private ?string $sourceSrid;
    private string $db;
    private string $schema;
    private string $table;
    private string $geom;
    private mixed $field;
    private ?string $key;
    private ?string $user;
    /** The exception the last WFS dispatch rendered as a report, if any */
    private ?Throwable $wfsError = null;

    /**
     * Feature constructor.
     * @throws PhpfastcacheInvalidArgumentException
     */
    function __construct()
    {

        parent::__construct();

        // Set properties
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

        // The GET carried no session
        $ctx = $this->wfsContext($this->db);
        if ($this->wouldChallenge($ctx, ['Read/write'])) {
            return $this->challengeResponse();
        }
        parse_str("service=WFS&version=1.1.0&request=GetFeature&typeName={$this->table}&FEATUREID={$this->table}.{$this->key}", $query);
        $xml = $this->dispatch($ctx, fn(): WfsRequest => WfsRequest::fromQuery($ctx, $query));

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
            $response['code'] = $this->wfsErrorCode() ?? "500";
            return $response;
        }

        // Convert GML to WKT
        $gmlConverter = new GmlConverter();
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
                        $value = "<![CDATA[" . $value . "]]>";
                    } elseif (is_array($value)) {
                        // Create PG array
                        $value = json_encode($value);
                        $value = '{' . substr($value, 1, -1) . '}';
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
                    $value = "<![CDATA[" . $value . "]]>";
                } elseif (is_array($value)) {
                    // Create PG array
                    $value = json_encode($value);
                    $value = '{' . substr($value, 1, -1) . '}';
                }
                if ($value === false) {
                    $value = 'f';
                }
                if ($value === true) {
                    $value = 't';
                }
                $xml .= "<wfs:Property>\n";
                $xml .= "<wfs:Name>$elem</wfs:Name>\n";
                $xml .= "<wfs:Value>$value</wfs:Value>\n";
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
     * @throws PhpfastcacheInvalidArgumentException
     */
    private function commit(string $xml): array
    {
        [$user, $db] = Util::extractUserFromSubUserString($this->user);
        // Check privileges of user on layer
        $rel = $this->schema . "." . $this->table;
        try {
            $response = $this->ApiKeyAuthLayer($rel, true, [$rel], $user, Input::getApiKey());
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

        // The transaction runs in the caller's session, or in a new one for the
        // API key's user
        if (empty(Input::getCookies()["PHPSESSID"])) {
            Session::start();
            Session::set("auth", true);
            Session::set("screen_name", $user ?? $db);
            Session::set("parentdb", $db);
            Session::set("subuser", !empty($user));
            Session::write();
        } else {
            Session::start();
        }
        $ctx = $this->wfsContext(Session::getUser() ?? $this->db);
        if ($this->wouldChallenge($ctx, ['Write', 'Read/write'])) {
            return $this->challengeResponse();
        }
        $transactionResponse = $this->dispatch($ctx, fn(): WfsRequest => WfsRequest::fromHttp($ctx, $xml));

        // Unserialize the transaction response
        $status = $unserializer->unserialize($transactionResponse);

        // Check if transaction response could be unserialized
        if (gettype($status) != "boolean" && $status !== true) {
            $response['success'] = false;
            $response['message'] = "Could not unserialize transaction response";
            $response['body'] = $transactionResponse;
            $response['code'] = 500;
            return $response;
        }

        $arr = $unserializer->getUnserializedData();

        // Check if WFS returned a service exception
        if (isset($arr["ows:Exception"])) {
            $response['success'] = false;
            $response['message'] = $arr;
            $response['code'] = $this->wfsErrorCode() ?? "500";
            return $response;
        }

        $response['success'] = true;
        $response['message'] = $arr;
        return $response;
    }

    /**
     * The WFS context the internal request to /wfs/<user>/<schema>/<srid> had: the
     * database and schema from the layer, and the request came from localhost.
     */
    private function wfsContext(string $user): Context
    {
        $trusted = false;
        foreach ((App::$param['trustedAddresses'] ?? []) as $address) {
            if (Util::ipInRange('127.0.0.1', $address) && getenv('MODE_ENV') !== 'test') {
                $trusted = true;
                break;
            }
        }
        return new Context(
            connection: new Connection(database: $this->db, schema: $this->schema),
            database: $this->db,
            schema: $this->schema,
            user: $user,
            userGroup: null,
            parentUser: $user == $this->db,
            trusted: $trusted,
            host: Util::host(),
            thePath: Util::thePath(),
            startTime: microtime(true),
            srs: ($this->sourceSrid !== null && $this->sourceSrid !== '') ? (int)$this->sourceSrid : null,
        );
    }

    /**
     * BasicAuth::authenticate() meets a request without a login with a 401 challenge
     * and die(). Over HTTP that ended only the internal request; in process it would
     * end this one, so the case is detected up front. Mirrors the layer checks of
     * authorizeLayers() (reads) and the Transaction handler (writes).
     *
     * @param list<string> $levels authentication levels that make the engine authenticate
     */
    private function wouldChallenge(Context $ctx, array $levels): bool
    {
        if ($ctx->trusted || (Session::isAuth() && ($_SESSION['parentdb'] ?? null) == $this->db)) {
            return false;
        }
        $auth = $ctx->model()->getGeometryColumns("$this->schema.$this->table", 'authentication');
        return in_array($auth, $levels, true);
    }

    /**
     * The answer when the engine would have challenged for a login.
     */
    private function challengeResponse(): array
    {
        return [
            'success' => false,
            'message' => 'Attempt to login using Basic Auth was cancelled',
            'code' => 401,
        ];
    }

    /**
     * HTTP status for the exception the engine reported, or null for the generic 500.
     */
    private function wfsErrorCode(): ?int
    {
        $e = $this->wfsError;
        if ($e === null) {
            return null;
        }
        // BasicAuth: "Insufficient privileges to layer: ..."
        if ($e->getCode() === 403) {
            return 403;
        }
        $message = $e->getMessage();
        return match (true) {
            str_contains($message, 'Layer is not editable'),
            str_contains($message, "You don't have a role in the workflow") => 403,
            str_contains($message, 'Layer does not exist'),
            str_contains($message, "Relation doesn't exist") => 404,
            default => null,
        };
    }

    /**
     * Runs a WFS request on the in-process engine and returns the XML the legacy /wfs
     * route would have answered, exception reports included.
     *
     * @param callable(): WfsRequest $request parsed inside the guard, as /wfs did
     */
    private function dispatch(Context $ctx, callable $request): string
    {
        // The legacy WFS route ran without a time limit
        set_time_limit(0);
        $writer = new GmlWriter(
            gmlNameSpace: $this->schema,
            gmlNameSpaceUri: str_replace('https://', 'http://', "$ctx->host/$this->db/$this->schema"),
            suppressFlush: true,
        );
        $captured = '';
        ob_start(function (string $chunk) use (&$captured): string {
            $captured .= $chunk;
            return '';
        }, 0);
        // The internal request carried no Basic credentials
        $authUser = $_SERVER['PHP_AUTH_USER'] ?? null;
        $authPw = $_SERVER['PHP_AUTH_PW'] ?? null;
        unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
        $req = null;
        try {
            $req = $request();
            authorizeLayers($ctx, $req);
            new Server($ctx)->dispatch($req, $writer);
            $writer->writeMemoryFooter();
        } catch (Throwable $e) {
            $this->wfsError = $e;
            ExceptionReport::render($e, $req?->version ?? '1.1.0', $writer);
        } finally {
            if ($authUser !== null) {
                $_SERVER['PHP_AUTH_USER'] = $authUser;
            }
            if ($authPw !== null) {
                $_SERVER['PHP_AUTH_PW'] = $authPw;
            }
            ob_end_flush();
        }
        return $captured;
    }
}
