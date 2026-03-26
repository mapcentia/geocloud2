<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\PostResponse;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\conf\App;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Model;
use app\inc\Route2;
use app\inc\Session;
use app\models\Layer;
use Exception;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use stdClass;
use Throwable;
use ZipArchive;
use Override;


/**
 * Class Sql
 * @package app\api\v4
 */
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "FileProcessRequest",
    description: "Upload files to temporary storage and then import them into database tables.",
    required: ["file", "schema"],
    properties: [
        new OA\Property(
            property: "file",
            title: "File",
            description: "Uploaded file name to import.",
            type: "string",
        ),
        new OA\Property(
            property: "schema",
            title: "Schema",
            description: "Destination schema name.",
            type: "string",
        ),
        new OA\Property(
            property: "import",
            title: "Import",
            description: "If false, run a dry-run (no changes).",
            type: "boolean",
            default: false,
        ),
        new OA\Property(
            property: "t_srs",
            title: "Target srs",
            description: "Fallback target SRS. Used if no authority name/code is available.",
            type: "string",
            default: "EPSG:4326",
        ),
        new OA\Property(
            property: "s_srs",
            title: "Source srs",
            description: "Fallback source SRS. Used if the file has no projection information.",
            type: "string",
            default: "EPSG:4326",
        ),
        new OA\Property(
            property: "append",
            title: "Append",
            description: "Append to an existing table instead of creating a new one.",
            type: "boolean",
            default: false,
        ),
        new OA\Property(
            property: "p_multi",
            title: "Promote to multi",
            description: "Promote single-part geometries to multi-part.",
            type: "boolean",
            default: false,
        ),
        new OA\Property(
            property: "truncate",
            title: "Truncate",
            description: "Truncate table before appending. Only applies when append is true.",
            type: "boolean",
            default: false,
        ),
        new OA\Property(
            property: "timestamp",
            title: "Timestamp",
            description: "Name of timestamp field to create. Omit to skip creating a timestamp field.",
            type: "string",
            example: "creation_timestamp",
        ),
        new OA\Property(
            property: "x_possible_names",
            title: "Possible names for X",
            description: "Possible column names for X/longitude (CSV only).",
            type: "string",
            default: "lon*,Lon*,x,X",
            example: "Lon*",
        ),
        new OA\Property(
            property: "y_possible_names",
            title: "Possible names for Y",
            description: "Possible column names for Y/latitude (CSV only).",
            type: "string",
            default: "lat*,Lat*,y,Y",
            example: "Lat*",
        ),
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "FileProcessResponse",
    description: "Upload files to temporary storage and then import them into database tables.",
    required: ["file", "schema"],
    properties: [
        new OA\Property(
            property: "driver",
            title: "Driver",
            description: "Driver name.",
            type: "string",
        ),
        new OA\Property(
            property: "count",
            title: "Count",
            description: "Count of rows.",
            type: "integer",
        ),
        new OA\Property(
            property: "geom_type",
            title: "Geometry type",
            description: "Type of geometry if PostGIS table is created.",
            type: "string",
            example: "Multipolygon",
        ),
        new OA\Property(
            property: "index",
            title: "Index",
            description: "Index of multi layered geometry files like GML and GeoPackage",
            type: "integer",
        ),
        new OA\Property(
            property: "name",
            title: "Name",
            description: "Name of created table.",
            type: "string",
        ),
        new OA\Property(
            property: "has_wkt",
            title: "Has WKT",
            description: "If file has WKT projection information.",
            type: "boolean"
        ),
        new OA\Property(
            property: "auth_str",
            title: "Authority string",
            description: "EPSG Authority string.",
            type: "string",
            example: "EPSG:25832",
        ),
        new OA\Property(
            property: "error",
            title: "Error",
            description: "Error message.",
            type: "string",
        ),
    ],
    type: "object"
)]
#[AcceptableMethods(['PATCH', 'POST', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/file/(action)', scope: Scope::SUB_USER_ALLOWED)]
class File extends AbstractApi
{

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = '_file';
    }

    /**
     * @return Response
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/file/upload', operationId: 'postFileUpload', description: 'Upload file(s) via multipart/form-data and stage them on the server.', tags: ['File'])]
    #[OA\RequestBody(content: new OA\MediaType('multipart/form-data', new OA\Schema(
        properties: [
            new OA\Property(
                property: "filename",
                type: "string",
                format: "binary",
                example: 'file',
            ),
        ],
        type: 'object',
    )))]
    #[OA\Response(response: 201, description: 'File uploaded successfully.')]
    #[AcceptableContentTypes(['multipart/form-data'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    public function post_upload(): Response
    {
        @set_time_limit(5 * 60);
        $mainDir = App::$param['path'] . "/app/tmp/" . $this->route->jwt["data"]["database"];
        $targetDir = $mainDir . "/__vectors";
        $maxFileAge = 5 * 3600;

        if (!file_exists($mainDir)) {
            @mkdir($mainDir);
        }
        if (!file_exists($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }
        $fileName = $_FILES["filename"]["name"];
        if (!$fileName) {
            throw new GC2Exception("File does not exists.", 400, null, "FILE_IMPORT_ERROR");
        }
        $filePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;

        $chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
        $chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;
        if (!is_dir($targetDir) || !$dir = opendir($targetDir)) {
            throw new GC2Exception("Failed to open temp directory.", 400, null, "FILE_IMPORT_ERROR");
        }
        while (($file = readdir($dir)) !== false) {
            $tmpFilePath = $targetDir . DIRECTORY_SEPARATOR . $file;

            // If temp file is current file proceed to the next
            if ($tmpFilePath == "$filePath.part") {
                continue;
            }

            // Remove temp file if it is older than the max age and is not the current file
            if (preg_match('/\.part$/', $file) && (filemtime($tmpFilePath) < time() - $maxFileAge)) {
                @unlink($tmpFilePath);
            }
        }
        closedir($dir);
        // Open temp file
        if (!$out = @fopen("$filePath.part", $chunks ? "ab" : "wb")) {
            throw new GC2Exception("Failed to open output stream.", 400, null, "FILE_IMPORT_ERROR");
        }
        if (!empty($_FILES)) {
            if (!isset($_FILES["filename"]["tmp_name"])) {
                throw new GC2Exception("Failed to move uploaded file.", 400, null, "FILE_IMPORT_ERROR");
            }
            if ($_FILES["filename"]["error"] || !is_uploaded_file($_FILES["filename"]["tmp_name"])) {
                throw new GC2Exception("Failed to move uploaded file.", 400, null, "FILE_IMPORT_ERROR");
            }

            // Read binary input stream and append it to temp file
            if (!$in = @fopen($_FILES["filename"]["tmp_name"], "rb")) {
                throw new GC2Exception("Failed to move uploaded file.", 400, null, "FILE_IMPORT_ERROR");
            }
        } else {
            if (!$in = @fopen("php://input", "rb")) {
                throw new GC2Exception("Failed to open input stream.", 400, null, "FILE_IMPORT_ERROR");
            }
        }
        while ($buff = fread($in, 4096)) {
            fwrite($out, $buff);
        }
        @fclose($out);
        @fclose($in);
        // Check if file has been uploaded
        if (!$chunks || $chunk == $chunks - 1) {
            // Strip the temp .part suffix off
            rename("$filePath.part", $filePath);
        }
        $data = ["success" => true, "chunk" => $chunk];
        return new PostResponse(data: $data);
    }

    /**
     * @return Response
     * @throws Exception
     */
    #[OA\Post(path: '/api/v4/file/process', operationId: 'postFileProcess', description: 'Import a staged file into a target schema/table. Supports dry-run, append, SRS, and more.', tags: ['File'])]
    #[OA\RequestBody(description: 'Import options and target information.', required: true, content: new OA\JsonContent(ref: "#/components/schemas/FileProcessRequest"))]
    #[OA\Response(response: 200, description: 'Files processed.', content: new OA\JsonContent(type: "array", items: new OA\Items(ref: "#/components/schemas/FileProcessResponse")))]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    public function post_process(): Response
    {
        $body = Input::getBody();
        $data = json_decode($body);
        $fileName = $data->file;
        $schema = $data->schema;
        // Make dry run to check how many tables would be created
        try {
            if ($data->import) {
                $data->import = false;
                $result = $this->import($schema, $fileName, $data);
                $result['schema'] = $schema;
                $this->runPreExtension('processImport', (new Model(connection: $this->connection)), $result);
                $data->import = true;
            }
            $result = $this->import($schema, $fileName, $data);
            new Layer(connection: $this->connection)->insertDefaultMeta();
            $response = [];
            foreach ($result['data'] as $k => $v) {
                $r['driver'] = $v['driver'];
                $r['count'] = $v['featureCount'];
                $r['geom_type'] = $v['type'];
                $r['index'] = $v['layerIndex'];
                $r['name'] = $v['layerName'];
                $r['has_wkt'] = $v['hasWkt'];
                $r['auth_str'] = $v['authStr'];
                $r['error'] = $v['error'];
                $response[] = $r;
            }
            return new PostResponse(data: $response);
        } catch (Throwable $e) {
            throw new GC2Exception($e->getMessage(), $e->getCode(), null, "FILE_IMPORT_ERROR");
        }
    }

    /**
     * @throws GC2Exception|\JsonException
     */
    protected function import(?string $schema, string $fileName, ?stdClass $args = null): array
    {
        $dir = App::$param['path'] . "app/tmp/" . $this->connection->database . "/__vectors";
        $safeName = Session::getUser() . "_" . md5(microtime() . rand());
        if (is_numeric($safeName[0])) {
            $safeName = "_" . $safeName;
        }
        $fileFullPath = $dir . "/" . $fileName;
        if (!file_exists($fileFullPath)) {
            throw new GC2Exception("File not found: {$fileName}", 404, null, "FILE_IMPORT_ERROR");
        }
        // Check if file is .zip
        $zipCheck1 = explode(".", $fileName);
        $zipCheck2 = array_reverse($zipCheck1);
        if (strtolower($zipCheck2[0]) == "zip") {
            $zip = new ZipArchive;
            $res = $zip->open($dir . "/" . $fileName);
            if ($res !== true) {
                $response['success'] = false;
                $response['message'] = $res;
                return $response;
            }
            $zip->extractTo($dir . "/" . $safeName);
            $zip->close();
            $fileFullPath = $dir . "/" . $safeName;
        }
        $connectionStr =
            "\"PG:host=" . $this->connection->host . " port=" . $this->connection->port . " user=" . $this->connection->user . " password=" . $this->connection->password . " dbname=" . $this->connection->database . "\"";
        $cmd = "ogr2postgis" .
            " --json" .
            ($args && property_exists($args, 's_srs') ? " --s_srs " . $args->s_srs : "") .
            ($args && property_exists($args, 't_srs') ? " --t_srs " . $args->t_srs : "") .
            ($args && property_exists($args, 'import') && $args->import === true ? " --schema $schema" : "") .
            ($args && property_exists($args, 'p_multi') && $args->import === true ? " --p_multi" : "") .
            ($args && property_exists($args, 'import') && $args->import === true ? " --import" : "") .
            ($args && property_exists($args, 'append') && $args->import === true ? " --append" : "") .
            ($args && property_exists($args, 'truncate') && $args->import === true ? " --truncate" : "") .
            ($args && property_exists($args, 'timestamp') && $args->import === true ? " --timestamp " . $args->timestamp : "") .
            ($args && property_exists($args, 'x_possible_names') && $args->import === true ? " --x_possible_names " . $args->x_possible_names : "") .
            ($args && property_exists($args, 'y_possible_names') && $args->import === true ? " --y_possible_names " . $args->y_possible_names : "") .
            ($args && property_exists($args, 'import') && $args->import === true ? " --connection $connectionStr" : "") .
            ($args && property_exists($args, 'table_name') ? " --nln " . $args->table_name : "") .
            " '" . $fileFullPath . "'";

        exec($cmd, $out);
        $args = !empty($out[0]) ? json_decode($out[0], true, 512, JSON_THROW_ON_ERROR) : null;
        return [
            'data' => $args,
            'cmd' => $cmd,
        ];
    }

    #[Override]
    public function post_index(): Response
    {
        // TODO: Implement put_index() method.
    }

    #[Override]
    public function patch_index(): Response
    {
        // TODO: Implement put_index() method.
    }

    #[Override]
    public function get_index(): Response
    {
        // TODO: Implement put_index() method.
    }

    #[Override]
    public function delete_index(): Response
    {
        // TODO: Implement delete_index() method.
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    #[Override]
    public function validate(): void
    {
        $body = Input::getBody();
        $data = json_decode($body);
        $schema = $data->schema;

        $collection = new Assert\Collection([
            'file' => new Assert\Required(
                new Assert\Type('string'),
            ),
            'import' => new Assert\Optional(
                new Assert\Type('boolean'),
            ),
            'append' => new Assert\Optional(
                new Assert\Type('boolean'),
            ),
            'truncate' => new Assert\Optional(
                new Assert\Type('boolean'),
            ),
            'p_multi' => new Assert\Optional(
                new Assert\Type('boolean'),
            ),
            't_srs' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ]),
            's_srs' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ]),
            'timestamp' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ]),
            'x_possible_names' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ]),
            'y_possible_names' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ]),
            'table_name' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ]),
        ]);
        // If import is not set, set it to true
        if (!empty($data) && !property_exists($data, 'import')) {
            $data->import = true;
        }
        // If import is set, set schema to required
        if ($data->import === true) {
            $collection->fields['schema'] = new Assert\Required(
                new Assert\Type('string')
            );
        } else {
            $collection->fields['schema'] = new Assert\Optional(
                new Assert\Type('string')
            );
        }
        $this->validateRequest($collection, $body, Input::getMethod());
        $this->initiate(schema: $schema);
    }

    public function put_index(): Response
    {
        // TODO: Implement patch_index() method.
    }

    public function options_upload(): void
    {
    }
    public function options_process(): void
    {
    }
}