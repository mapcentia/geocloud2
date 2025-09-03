<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\conf\App;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Model;
use app\inc\Route2;
use app\inc\Session;
use app\models\Layer;
use Exception;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use stdClass;
use ZipArchive;
use Override;


/**
 * Class Sql
 * @package app\api\v4
 */
#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[AcceptableMethods(['PATCH', 'POST', 'HEAD', 'OPTIONS'])]
class Import extends AbstractApi
{

    public function __construct(private readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
    }

    /**
     * @return array
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/import/{schema}', operationId: 'postImport', description: 'Upload files', tags: ['Import'])]
    #[OA\Parameter(name: 'schema', description: 'Schema', in: 'path', required: true, example: 'my_schema')]
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
    #[OA\Response(response: 201, description: 'Created')]
    #[AcceptableContentTypes(['multipart/form-data'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    public function post_index(): array
    {
        $jwt = Jwt::validate()["data"];
        @set_time_limit(5 * 60);
        $mainDir = App::$param['path'] . "/app/tmp/" . $jwt["database"];
        $targetDir = $mainDir . "/__vectors";
        $maxFileAge = 5 * 3600;

        if (!file_exists($mainDir)) {
            @mkdir($mainDir);
        }
        if (!file_exists($targetDir)) {
            @mkdir($targetDir, 0777, true);
        }

        $fileName = $_FILES["filename"]["name"];

        $filePath = $targetDir . DIRECTORY_SEPARATOR . $fileName;
        $chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
        $chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;
        if (!is_dir($targetDir) || !$dir = opendir($targetDir)) {
            return [
                "success" => false,
                "code" => "400",
                "message" => "Failed to open temp directory.",
            ];
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
            return [
                "success" => false,
                "code" => "400",
                "message" => "Failed to open output stream.",
            ];
        }
        if (!empty($_FILES)) {
            if (!isset($_FILES["filename"]["tmp_name"])) {
                return [
                    "success" => false,
                    "code" => "400",
                    "message" => "Failed to move uploaded file.",
                ];
            }
            if ($_FILES["filename"]["error"] || !is_uploaded_file($_FILES["filename"]["tmp_name"])) {
                return [
                    "success" => false,
                    "code" => "400",
                    "message" => "Failed to move uploaded file.",
                ];
            }

            // Read binary input stream and append it to temp file
            if (!$in = @fopen($_FILES["filename"]["tmp_name"], "rb")) {
                return [
                    "success" => false,
                    "code" => "400",
                    "message" => "Failed to open input stream.",
                ];
            }
        } else {
            if (!$in = @fopen("php://input", "rb")) {
                return [
                    "success" => false,
                    "code" => "400",
                    "message" => "Failed to open input stream.",
                ];
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
        return ["code" => 201, "success" => true, "chunk" => $chunk];
    }

    /**
     * @return array
     * @throws Exception
     */
    #[OA\Patch(path: '/api/v4/import/{schema}/{file}', operationId: 'patchImport', description: 'Import files', tags: ['Import'])]
    #[OA\Parameter(name: 'schema', description: 'Schema', in: 'path', required: true, example: 'my_schema')]
    #[OA\Parameter(name: 'file', description: 'File to import', in: 'path', required: true, example: 'file.csv')]
    #[OA\RequestBody(content: new OA\MediaType('application/json', new OA\Schema(
        properties: [
            new OA\Property(
                property: "import",
                description: "If false, a dry-run will be executed.",
                type: "boolean",
                default: false,
            ),
            new OA\Property(
                property: "t_srs",
                title: "Target srs",
                description: "Fallback target SRS. Will be used if no authority name/code is available.",
                type: "string",
                default: "EPSG:4326",
            ),
            new OA\Property(
                property: "s_srs",
                title: "Source srs",
                description: "Fallback source SRS. Will be used if file doesn't contain projection information.",
                type: "string",
                default: "EPSG:4326",
            ),
            new OA\Property(
                property: "append",
                title: "Append",
                description: "Append to existing table instead of creating new.",
                type: "boolean",
                default: false,
            ),
            new OA\Property(
                property: "p_multi",
                title: "Promote to multi",
                description: "Promote single geometries to multi part.",
                type: "boolean",
                default: false,
            ),
            new OA\Property(
                property: "truncate",
                title: "Truncate",
                description: "Truncate table before appending. Only have effect if --append is set.",
                type: "boolean",
                default: false,
            ),
            new OA\Property(
                property: "timestamp",
                title: "Timestamp",
                description: "Name of timestamp field. Create a timestamp field in the import table. Omit property for no timestamp field.",
                type: "string",
                example: "creation_timestamp",
            ),
            new OA\Property(
                property: "x_possible_names",
                title: "Possible names for X",
                description: "Specify the potential names of the columns that can contain X/longitude. Only effects CSV",
                type: "string",
                default: "lon*,Lon*,x,X",
                example: "Lon*",
            ),
            new OA\Property(
                property: "y_possible_names",
                title: "Possible names for Y",
                description: "Specify the potential names of the columns that can contain Y/latitude. Only effects CSV",
                type: "string",
                default: "lat*,Lat*,y,Y",
                example: "Lat*",
            ),
        ],
        type: 'object',
    )))]
    #[OA\Response(response: 201, description: 'Created')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    public function patch_index(): array
    {
        $schema = $this->route->getParam("schema");
        $fileName = $this->route->getParam("file");
        $body = Input::getBody();
        $data = json_decode($body);
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
            (new Layer(connection: $this->connection))->insertDefaultMeta();

            $response['cmd'] = $result['cmd'];
            $response['data'] = $result['data'];
            $response["success"] = true;
            $response["code"] = 201;
            return $response;
        } catch (\Throwable $e) {
            throw new GC2Exception("Could not read data from file", 400, null, "FILE_IMPORT_ERROR");

        }
    }

    /**
     * @throws \JsonException
     */
    protected function import(string $schema, string $fileName, ?stdClass $args = null): array
    {
        $dir = App::$param['path'] . "app/tmp/" . $this->connection->database . "/__vectors";
        $safeName = Session::getUser() . "_" . md5(microtime() . rand());
        if (is_numeric($safeName[0])) {
            $safeName = "_" . $safeName;
        }
        $fileFullPath = $dir . "/" . $fileName;
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
            "\"PG:host=" . $this->connection->host . " port=" . $this->connection->port . " user=" . $this->connection->user . " password=" .$this->connection->password . " dbname=" . $this->connection->database . "\"";
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
        $args = !empty($out[0]) ? json_decode($out[0], null, 512, JSON_THROW_ON_ERROR) : null
        ;
        return [
            'data' => $args,
            'cmd' => $cmd,
        ];
    }

    #[Override] public function get_index(): array
    {
        // TODO: Implement put_index() method.
    }

    #[Override] public function delete_index(): array
    {
        // TODO: Implement delete_index() method.
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    #[Override] public function validate(): void
    {
        $file = $this->route->getParam("file");
        $schema = $this->route->getParam("schema");
        $body = Input::getBody();

        // Throw exception if tried with resource id
        if (Input::getMethod() == 'post' && $file) {
            $this->postWithResource();
        }

        $collection = new Assert\Collection([
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

        $this->validateRequest($collection, $body, Input::getMethod());

        $this->jwt = Jwt::validate()["data"];
        $this->initiate(userName: $this->jwt["uid"], superUser: $this->jwt["superUser"], schema: $schema);
    }

    public function put_index(): array
    {
        // TODO: Implement patch_index() method.
    }
}
