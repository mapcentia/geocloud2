<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\NoContentResponse;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\exceptions\OwsException;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\inc\Util;
use app\inc\geometry\GeometryFactory;
use app\inc\geometry\GmlConverter;
use app\models\Authorization;
use app\wfs\Context;
use app\wfs\Request as WfsRequest;
use app\wfs\Server;
use app\wfs\output\GmlWriter;
use Error;
use Exception;
use geoPHP;
use OpenApi\Attributes as OA;
use XML_Unserializer;

/**
 * The v4 Feature API: GeoJSON CRUD on single features, the successor of api/v2/feature.
 *
 * Where v2 posted WFS-T transactions to the internal http://localhost/wfs/... endpoint
 * over Guzzle, this controller drives the in-process WFS engine (app\wfs\Server) directly —
 * same transaction semantics (versioning, workflow, geofence rules, tile-cache busting,
 * pre/post processors), no internal HTTP round trip.
 *
 * Auth follows the v4 model: the JWT identity from the dispatcher, authorized per layer with
 * Authorization::check (the token branch of the merged WFS endpoint's auth model).
 */
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[OA\Schema(
    schema: "Feature",
    description: "A GeoJSON Feature or FeatureCollection.",
    required: ["type"],
    properties: [
        new OA\Property(property: "type", title: "Type", description: "'Feature' or 'FeatureCollection'.", type: "string", example: "FeatureCollection"),
        new OA\Property(property: "features", title: "Features", description: "The features (FeatureCollection only).", type: "array", items: new OA\Items(type: "object")),
        new OA\Property(property: "properties", title: "Properties", description: "Feature properties (Feature only).", type: "object"),
        new OA\Property(property: "geometry", title: "Geometry", description: "GeoJSON geometry (Feature only).", type: "object"),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'POST', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/schemas/{schema}/tables/{table}/features/[feature]', scope: Scope::SUB_USER_ALLOWED)]
class Feature extends AbstractApi
{
    private string $featureSchema;
    private string $featureTable;
    private ?string $featureKey;
    private int $srs;

    public function __construct(public Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'feature';
    }

    /**
     * Route params are only available after Route2 has matched the request — i.e.
     * from validate() on (the dispatcher constructs every route candidate up front),
     * so they cannot be read in the constructor.
     */
    private function initParams(): void
    {
        $this->featureSchema = (string)$this->route->getParam('schema');
        $this->featureTable = (string)$this->route->getParam('table');
        $this->featureKey = $this->route->getParam('feature');
        // Source SRS of incoming GeoJSON geometry and output SRS of returned GeoJSON.
        // GeoJSON defaults to EPSG:4326.
        $this->srs = (int)(!empty($_GET['srs']) ? $_GET['srs'] : 4326);
    }

    /**
     * @throws GC2Exception
     */
    public function validate(): void
    {
        $this->initParams();
        $method = Input::getMethod();
        if ($method === 'post' && !empty($this->featureKey)) {
            $this->postWithResource();
        }
        if (in_array($method, ['get', 'delete']) && empty($this->featureKey)) {
            throw new GC2Exception("A feature id is required in the path. Use the SQL or WFS API for reading collections.", 400, null, "FEATURE_ID_REQUIRED");
        }
        // The key ends up in a WFS featureId filter — reject quote characters
        if ($this->featureKey !== null && str_contains($this->featureKey, "'")) {
            throw new GC2Exception("Invalid feature id", 400, null, "INVALID_FEATURE_ID");
        }
        if (in_array($method, ['post', 'patch'])) {
            $body = Input::getBody();
            if (empty($body) || !json_validate($body)) {
                throw new GC2Exception("Invalid JSON. Check your request", 400, null, "INVALID_DATA");
            }
            $this->featuresFromBody($body); // throws on non-GeoJSON payloads
        }
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/schemas/{schema}/tables/{table}/features/{feature}', operationId: 'getFeature', description: "Get one or more features as GeoJSON by primary key. Pass a single key or a comma-separated list (e.g. 1,2,3). Read auth, geofence rules and versioning filters are applied by the WFS engine.", tags: ['Feature'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_table')]
    #[OA\Parameter(name: 'feature', description: 'Primary key value, or a comma-separated list of values (e.g. 1,2,3)', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: '1,2')]
    #[OA\Parameter(name: 'srs', description: 'Output EPSG code (SRID) of the GeoJSON geometry. Defaults to 4326.', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 4326), example: 25832)]
    #[OA\Response(response: 200, description: 'A GeoJSON Feature for a single match, or a FeatureCollection for several')]
    #[OA\Response(response: 404, description: 'No features found')]
    #[AcceptableAccepts(['application/json', 'application/geo+json', '*/*'])]
    public function get_index(): Response
    {
        $ctx = $this->buildContext();
        $this->authorizeLayer($ctx, false);

        // The path key may be a single value or a comma-separated list, matching
        // the other v4 collection GETs (e.g. .../features/1,2,3). Each value ends
        // up in a WFS featureId filter (pkey='1' OR pkey='2' OR ...).
        $keys = array_values(array_filter(
            array_map('trim', explode(',', (string)$this->featureKey)),
            static fn(string $k): bool => $k !== ''
        ));
        if (empty($keys)) {
            throw new GC2Exception("A feature id is required in the path. Use the SQL or WFS API for reading collections.", 400, null, "FEATURE_ID_REQUIRED");
        }

        $req = new WfsRequest(
            operation: 'GETFEATURE',
            version: '1.1.0',
            service: 'WFS',
            outputFormat: 'GML3',
            typeNames: [$this->featureTable],
            properties: null,
            featureIds: array_map(fn(string $k): string => "$this->featureTable.$k", $keys),
            bbox: null,
            resultType: null,
            srsName: null,
            srs: $this->srs,
            maxFeatures: null,
            timeSlice: null,
            filter: null,
            transactionBody: null,
            rawPostBody: null,
        );

        $xml = $this->captureDispatch($ctx, $req);

        // Lazy-load the PEAR unserializer (same pattern as app\wfs\Request) so the
        // class file itself stays loadable outside runtime (swagger generation etc.)
        set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__DIR__, 3) . '/libs/PEAR');
        require_once dirname(__DIR__, 3) . '/libs/PEAR/XML/Unserializer.php';

        $unserializer = new XML_Unserializer([
            'parseAttributes' => false,
            'typeHints' => false,
        ]);
        $status = $unserializer->unserialize($xml);
        if ($status !== true) {
            throw new GC2Exception("Could not unserialize the WFS response", 500, null, "WFS_ERROR");
        }
        $arr = $unserializer->getUnserializedData();
        $memberKey = "$this->featureSchema:$this->featureTable";
        $members = $arr["gml:featureMembers"][$memberKey] ?? null;
        if (empty($members)) {
            throw new GC2Exception("No features found", 404, null, "FEATURE_NOT_FOUND");
        }
        // The unserializer yields a single assoc array for one member and a numeric
        // list for many. Normalize to a list of members, in document order.
        $memberList = array_is_list($members) ? $members : [$members];

        // One WKT per member, in the same document order. The GML is split on the
        // feature element (the member is <schema:table> for 1.1.0, with no per-
        // feature gml:featureMember wrapper), so each member gets its own slot;
        // members without geometry produce an empty "()" placeholder at their index.
        $wkts = new GmlConverter()->gmlToWKT($xml, [strtoupper($this->featureTable)])[0] ?? [];

        $features = [];
        foreach ($memberList as $i => $member) {
            $wkt = $wkts[$i] ?? null;
            // A member without geometry renders as "()" (empty type) — treat as null.
            if ($wkt !== null && str_starts_with($wkt, '(')) {
                $wkt = null;
            }
            // The engine emits GML in lat/lon axis order for 1.1.0 + EPSG:4326 (ST_AsGml
            // flag 16 in the GetFeature handler) and gmlConverter does not flip it back.
            // GeoJSON is lon/lat, so swap the coordinate pairs for that case.
            if ($wkt && $this->srs === 4326) {
                $wkt = preg_replace_callback(
                    '/(-?[0-9.eE+\-]+)\s+(-?[0-9.eE+\-]+)/',
                    static fn(array $m) => "$m[2] $m[1]",
                    $wkt
                );
            }
            $json = null;
            if ($wkt) {
                try {
                    $json = geoPHP::load($wkt, 'wkt')->out('json');
                } catch (Exception|Error $e) {
                    throw new GC2Exception($e->getMessage(), 500, null, "WFS_ERROR");
                }
            }

            $props = [];
            foreach ($member as $key => $prop) {
                if (!is_array($prop)) {
                    $props[explode(":", $key)[1] ?? $key] = $prop;
                }
            }

            $features[] = [
                "type" => "Feature",
                "properties" => $props,
                "geometry" => $json !== null ? json_decode($json) : null,
            ];
        }

        // A single matched feature is returned as a bare GeoJSON Feature; several
        // as a FeatureCollection (mirrors the single-vs-list shape of the other v4
        // collection GETs).
        if (count($features) === 1) {
            return $this->getResponse($features[0]);
        }
        return $this->getResponse([
            "type" => "FeatureCollection",
            "features" => $features,
        ]);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/schemas/{schema}/tables/{table}/features', operationId: 'postFeature', description: "Insert features from a GeoJSON Feature or FeatureCollection through a WFS-T transaction. A primary-key value in properties is used as the new key; otherwise one is generated.", tags: ['Feature'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_table')]
    #[OA\Parameter(name: 'srs', description: 'EPSG code (SRID) of the incoming GeoJSON geometry. Defaults to 4326.', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 4326), example: 25832)]
    #[OA\RequestBody(description: 'GeoJSON Feature or FeatureCollection.', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Feature"))]
    #[OA\Response(response: 201, description: 'Created. Location header points at the new feature(s).')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[AcceptableContentTypes(['application/json', 'application/geo+json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    public function post_index(): Response
    {
        $ctx = $this->buildContext();
        $this->authorizeLayer($ctx, true);
        $features = $this->featuresFromBody(Input::getBody());
        $pkey = $this->primaryKey($ctx);
        $geomColumn = $ctx->model()->getGeometryColumns("$this->featureSchema.$this->featureTable", 'f_geometry_column');

        $xml = $this->transactionHeader();
        foreach ($features as $feature) {
            $props = $feature["properties"] ?? [];
            $gmlId = !empty($props[$pkey]) ? "gml:id=\"{$props[$pkey]}\"" : "";

            $xml .= "<wfs:Insert>\n";
            $xml .= "<feature:$this->featureTable $gmlId xmlns:feature=\"http://mapcentia.com/{$ctx->database}/$this->featureSchema\">\n";

            try {
                // Get GML from WKT geom and catch error if geom is missing
                $wkt = geoPHP::load(json_encode($feature), 'json')->out('wkt');
                $xml .= "<feature:$geomColumn>\n";
                $xml .= (new GeometryFactory())->createGeometry($wkt, "EPSG:$this->srs")->toGML();
                $xml .= "</feature:$geomColumn>\n";
            } catch (Exception) {
                // Pass. Geom is not required
            }

            foreach ($props as $elem => $value) {
                if ($pkey != $elem) {
                    $xml .= "<feature:$elem>" . $this->encodeValue($value) . "</feature:$elem>\n";
                }
            }

            $xml .= "</feature:$this->featureTable>\n";
            $xml .= "</wfs:Insert>\n";
        }
        $xml .= "</wfs:Transaction>\n";

        $result = $this->commit($ctx, $xml);
        $keys = array_map(fn(string $fid) => explode('.', $fid, 2)[1] ?? $fid, $result['inserted']);
        if (empty($keys)) {
            // The engine silently skips non-editable layers
            throw new GC2Exception("No features were inserted. Is the layer editable?", 400, null, "NOTHING_INSERTED");
        }
        return $this->postResponse("/api/v4/schemas/$this->featureSchema/tables/$this->featureTable/features/", $keys);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Patch(path: '/api/v4/schemas/{schema}/tables/{table}/features/{feature}', operationId: 'patchFeature', description: "Update features from a GeoJSON Feature or FeatureCollection through a WFS-T transaction. Each feature must carry its primary-key value in properties.", tags: ['Feature'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_table')]
    #[OA\Parameter(name: 'feature', description: 'Primary key value. Optional — when omitted, each feature must carry its key in properties.', in: 'path', required: false, schema: new OA\Schema(type: 'string'), example: '1')]
    #[OA\Parameter(name: 'srs', description: 'EPSG code (SRID) of the incoming GeoJSON geometry. Defaults to 4326.', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 4326), example: 25832)]
    #[OA\RequestBody(description: 'GeoJSON Feature or FeatureCollection.', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Feature"))]
    #[OA\Response(response: 303, description: 'Updated. Location header points at the feature(s).')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Feature not found')]
    #[AcceptableContentTypes(['application/json', 'application/geo+json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    public function patch_index(): Response
    {
        $ctx = $this->buildContext();
        $this->authorizeLayer($ctx, true);
        $features = $this->featuresFromBody(Input::getBody());
        $pkey = $this->primaryKey($ctx);
        $geomColumn = $ctx->model()->getGeometryColumns("$this->featureSchema.$this->featureTable", 'f_geometry_column');

        if (count($features) > 1 && !empty($this->featureKey)) {
            throw new GC2Exception("You can't PATCH a single feature with a collection of features", 400, null, "INVALID_DATA");
        }

        $keys = [];
        $xml = $this->transactionHeader();
        foreach ($features as $feature) {
            $props = $feature["properties"] ?? [];
            // The path key wins; otherwise the key must come from the properties
            $key = $this->featureKey ?? ($props[$pkey] ?? null);
            if (!isset($key)) {
                throw new GC2Exception("Property with primary key is missing from at least one GeoJSON feature", 400, null, "PRIMARY_KEY_MISSING");
            }
            // The key ends up in a WFS featureId filter — reject quote characters
            if (str_contains((string)$key, "'")) {
                throw new GC2Exception("Invalid feature id", 400, null, "INVALID_FEATURE_ID");
            }
            $keys[] = (string)$key;

            $xml .= "<wfs:Update typeName=\"$this->featureSchema:$this->featureTable\">\n";

            try {
                // Get GML from WKT geom and catch error if geom is missing
                $wkt = geoPHP::load(json_encode($feature), 'json')->out('wkt');
                $xml .= "<wfs:Property>\n";
                $xml .= "<wfs:Name>$geomColumn</wfs:Name>\n";
                $xml .= "<wfs:Value>\n";
                $xml .= (new GeometryFactory())->createGeometry($wkt, "EPSG:$this->srs")->toGML();
                $xml .= "</wfs:Value>\n";
                $xml .= "</wfs:Property>\n";
            } catch (Exception) {
                // Pass. Geom is not required
            }

            foreach ($props as $elem => $value) {
                $xml .= "<wfs:Property>\n";
                $xml .= "<wfs:Name>$elem</wfs:Name>\n";
                $xml .= "<wfs:Value>" . $this->encodeValue($value) . "</wfs:Value>\n";
                $xml .= "</wfs:Property>\n";
            }

            $xml .= "<ogc:Filter xmlns:ogc=\"http://www.opengis.net/ogc\">";
            $xml .= "<ogc:FeatureId fid=\"$this->featureTable.$key\"/>";
            $xml .= "</ogc:Filter>\n";
            $xml .= "</wfs:Update>\n";
        }
        $xml .= "</wfs:Transaction>\n";

        $result = $this->commit($ctx, $xml);
        if ($result['updated'] === 0) {
            throw new GC2Exception("No features were updated", 404, null, "FEATURE_NOT_FOUND");
        }
        return $this->patchResponse("/api/v4/schemas/$this->featureSchema/tables/$this->featureTable/features/", $keys);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Delete(path: '/api/v4/schemas/{schema}/tables/{table}/features/{feature}', operationId: 'deleteFeature', description: "Delete a single feature by its primary key through a WFS-T transaction.", tags: ['Feature'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_table')]
    #[OA\Parameter(name: 'feature', description: 'Primary key value of the feature', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: '1')]
    #[OA\Response(response: 204, description: 'Deleted')]
    #[OA\Response(response: 404, description: 'Feature not found')]
    public function delete_index(): Response
    {
        $ctx = $this->buildContext();
        $this->authorizeLayer($ctx, true);

        $xml = $this->transactionHeader();
        $xml .= "<wfs:Delete typeName=\"$this->featureSchema:$this->featureTable\" xmlns:$this->featureSchema=\"http://mapcentia.com/{$ctx->database}/$this->featureSchema\">";
        $xml .= "<ogc:Filter xmlns:ogc=\"http://www.opengis.net/ogc\">";
        $xml .= "<ogc:FeatureId fid=\"$this->featureTable.$this->featureKey\"/>";
        $xml .= "</ogc:Filter>";
        $xml .= "</wfs:Delete>";
        $xml .= "</wfs:Transaction>\n";

        $result = $this->commit($ctx, $xml);
        if ($result['deleted'] === 0) {
            throw new GC2Exception("Feature not found", 404, null, "FEATURE_NOT_FOUND");
        }
        return new NoContentResponse();
    }

    // PUT is not part of the v4 Feature API (use PATCH). Required by ApiInterface,
    // rejected upstream by AcceptableMethods.
    public function put_index(): Response
    {
        return new NoContentResponse();
    }

    /**
     * Builds the WFS context for the JWT identity. tokenAuth: true makes the
     * transaction handler skip HTTP Basic auth — authorization already ran via
     * Authorization::check in authorizeLayer().
     */
    private function buildContext(): Context
    {
        $jwt = $this->route->jwt['data'];
        $connection = new Connection(user: $jwt['uid'], database: $jwt['database'], schema: $this->featureSchema);
        return new Context(
            connection: $connection,
            database: $jwt['database'],
            schema: $this->featureSchema,
            user: $jwt['uid'],
            userGroup: $jwt['userGroup'] ?? null,
            parentUser: (bool)$jwt['superUser'],
            trusted: false,
            host: Util::host(),
            thePath: Util::thePath(),
            startTime: microtime(true),
            srs: $this->srs,
            tokenAuth: true,
        );
    }

    /**
     * Per-layer authorization for the JWT identity, mirroring the token branch of the
     * v4 WFS endpoint: 'Read/write' layers require authorization for reads, 'Write'
     * layers for transactions. Sub-user privileges are checked by Authorization::check.
     *
     * @throws GC2Exception
     */
    private function authorizeLayer(Context $ctx, bool $transaction): void
    {
        $rel = "$this->featureSchema.$this->featureTable";
        $auth = $ctx->model()->getGeometryColumns($rel, 'authentication');
        if ($auth === 'Read/write' || ($transaction && $auth === 'Write')) {
            (new Authorization(connection: $ctx->connection))->check(
                relName: $rel, transaction: $transaction, isAuth: true,
                subUser: $ctx->parentUser ? null : $ctx->user, userGroup: $ctx->userGroup, rels: []
            );
        }
    }

    /**
     * Dispatches a WFS request on the in-process server and returns the XML it
     * would have streamed. The writer is built with suppressFlush, so it only
     * echoes (never physically flushes); the output buffer callback swallows every
     * chunk into $captured and forwards nothing, and no HTTP headers are committed.
     *
     * @throws GC2Exception
     */
    private function captureDispatch(Context $ctx, WfsRequest $req): string
    {
        $writer = new GmlWriter(
            gmlNameSpace: $this->featureSchema,
            gmlNameSpaceUri: str_replace('https://', 'http://', Util::host() . "/$ctx->database/$this->featureSchema"),
            // We capture the writer's output in an output buffer below and convert
            // it to JSON. Suppress the writer's physical flush() so the streaming
            // GetFeature path does not commit the HTTP headers (with the default
            // text/html) before the response layer sets application/json.
            suppressFlush: true,
        );
        $captured = '';
        ob_start(function (string $chunk) use (&$captured): string {
            $captured .= $chunk;
            return '';
        }, 0);
        try {
            (new Server($ctx))->dispatch($req, $writer);
        } catch (OwsException $e) {
            throw $this->mapOwsException($e);
        } finally {
            ob_end_flush();
        }
        return $captured;
    }

    /**
     * Runs a WFS-T transaction XML through the in-process server and returns the
     * parsed summary. This replaces v2's POST to http://localhost/wfs/...
     *
     * @return array{inserted: list<string>, updated: int, deleted: int}
     * @throws GC2Exception
     */
    private function commit(Context $ctx, string $xml): array
    {
        $req = WfsRequest::fromHttp($ctx, $xml);
        $responseXml = $this->captureDispatch($ctx, $req);

        $sx = @simplexml_load_string($responseXml);
        if ($sx === false) {
            throw new GC2Exception("Could not parse the transaction response", 500, null, "WFS_ERROR");
        }
        $sx->registerXPathNamespace('wfs', 'http://www.opengis.net/wfs');
        $sx->registerXPathNamespace('ogc', 'http://www.opengis.net/ogc');
        return [
            'inserted' => array_map(fn($e) => (string)$e['fid'], $sx->xpath('//ogc:FeatureId') ?: []),
            'updated' => (int)(($sx->xpath('//wfs:totalUpdated') ?: [0])[0]),
            'deleted' => (int)(($sx->xpath('//wfs:totalDeleted') ?: [0])[0]),
        ];
    }

    private function mapOwsException(OwsException $e): GC2Exception
    {
        $message = $e->getMessage();
        if (str_contains($message, "Relation doesn't exist")) {
            return new GC2Exception("Table not found", 404, $e, "TABLE_NOT_FOUND");
        }
        if (str_contains($message, 'Layer is not enabled')) {
            return new GC2Exception("WFS is not enabled for the layer", 400, $e, "WFS_NOT_ENABLED");
        }
        return new GC2Exception($message, 400, $e, "WFS_TRANSACTION_ERROR");
    }

    private function transactionHeader(): string
    {
        return "<wfs:Transaction xmlns:wfs=\"http://www.opengis.net/wfs\" service=\"WFS\" version=\"1.1.0\"
                 xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
                 xsi:schemaLocation=\"http://www.opengis.net/wfs http://schemas.opengis.net/wfs/1.1.0/WFS-transaction.xsd\">\n";
    }

    /**
     * Encodes a GeoJSON property value as WFS-T element content: strings as CDATA,
     * arrays as PostgreSQL array literals, booleans as t/f (same as v2).
     */
    private function encodeValue(mixed $value): mixed
    {
        if (is_string($value)) {
            return "<![CDATA[" . $value . "]]>";
        }
        if (is_array($value)) {
            // Create PG array
            return '{' . substr(json_encode($value), 1, -1) . '}';
        }
        if ($value === false) {
            return 'f';
        }
        if ($value === true) {
            return 't';
        }
        return $value;
    }

    /**
     * @throws GC2Exception
     */
    private function primaryKey(Context $ctx): string
    {
        $pkey = $ctx->model()->getPrimeryKey("$this->featureSchema.$this->featureTable")['attname'] ?? null;
        if (!$pkey) {
            throw new GC2Exception("The table has no primary key", 400, null, "NO_PRIMARY_KEY");
        }
        return $pkey;
    }

    /**
     * Normalizes the request body to a list of GeoJSON features: a Feature becomes a
     * one-element list, a FeatureCollection contributes its features.
     *
     * @return array<int, array<string, mixed>>
     * @throws GC2Exception
     */
    private function featuresFromBody(?string $body): array
    {
        $data = json_decode((string)$body, true);
        $type = $data['type'] ?? null;
        if ($type === 'Feature') {
            return [$data];
        }
        if ($type === 'FeatureCollection' && is_array($data['features'] ?? null) && count($data['features']) > 0) {
            return $data['features'];
        }
        throw new GC2Exception("The body must be a GeoJSON Feature or a non-empty FeatureCollection", 400, null, "INVALID_GEOJSON");
    }
}
