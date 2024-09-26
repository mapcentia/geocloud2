<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\conf\App;
use app\conf\Connection;
use app\exceptions\GC2Exception;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Model;
use app\inc\Route2;
use app\inc\Session;
use Exception;
use Override;
use stdClass;
use ZipArchive;


/**
 * Class Sql
 * @package app\api\v4
 */
#[AcceptableMethods(['PUT', 'POST', 'HEAD', 'OPTIONS'])]
class Import extends AbstractApi
{

    /**
     * @return array
     * @OA\Post(
     *   path="/api/v4/import",
     *   tags={"Import"},
     *   summary="Import files. Must be zipped and can contain multiple files in sub-dirs",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     description="Parameters",
     *     @OA\MediaType(
     *       mediaType="multipart/form-data",
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object"
     *       )
     *     )
     *   )
     * )
     * @throws GC2Exception
     */
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
        if (isset($_REQUEST["name"])) {
            $fileName = $_REQUEST["name"];
        } elseif (!empty($_FILES)) {
            $fileName = $_FILES["file"]["name"];
        } else {
            $fileName = uniqid("file_");
        }
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
            if ($_FILES["file"]["error"] || !is_uploaded_file($_FILES["file"]["tmp_name"])) {
                return [
                    "success" => false,
                    "code" => "400",
                    "message" => "Failed to move uploaded file.",
                ];
            }

            // Read binary input stream and append it to temp file
            if (!$in = @fopen($_FILES["file"]["tmp_name"], "rb")) {
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
     *
     * @OA\Get(
     *   path="/api/v4/import/{file}",
     *   tags={"Import"},
     *   summary="Import uploades zip file",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="file",
     *     in="path",
     *     required=false,
     *     description="Name of uploaded zip file",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="data", type="object", @OA\Schema(type="string")),
     *         @OA\Property(property="success",type="boolean", example=true)
     *       )
     *     )
     *   )
     * )
     */
    public function put_index(): array
    {
        $schema = Route2::getParam("schema");
        $fileName = Route2::getParam("file");
        $body = Input::getBody();
        $data = json_decode($body);
        // Make dry run to check how many tables would be created
        if ($data->import) {
            $data->import = false;
            $result = $this->import($schema, $fileName, $data);
            $result['schema'] = $schema;
            $this->runExtension('processImport', (new Model()), $result);
            $data->import = true;
        }
        $result = $this->import($schema, $fileName, $data);
        $response['cmd'] = $result['cmd'];
        $response['data'] = $result['data'];
        $response["success"] = true;
        $response["code"] = 201;
        return $response;
    }

    /**
     * @throws \JsonException
     */
    protected function import(string $schema, string $fileName, stdClass $args = null): array
    {
        $dir = App::$param['path'] . "app/tmp/" . Connection::$param["postgisdb"] . "/__vectors";
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
            "\"PG:host=" . Connection::$param["postgishost"] . " user=" . Connection::$param["postgisuser"] . " password=" . Connection::$param["postgispw"] . " dbname=" . Connection::$param["postgisdb"] . "\"";
        $cmd = "ogr2postgis" .
            " --json" .
            ($args && property_exists($args, 's_srs') ? " --s_srs " . $args->s_srs : "") .
            ($args && property_exists($args, 't_srs') ? " --t_srs " . $args->t_srs : "") .
            ($args && property_exists($args, 'import') && $args->import === true ? " --schema $schema" : "") .
            ($args && property_exists($args, 'import') && $args->import === true ? " --p_multi" : "") .
            ($args && property_exists($args, 'import') && $args->import === true ? " --import" : "") .
            ($args && property_exists($args, 'import') && $args->import === true ? " --connection $connectionStr" : "") .
            " '" . $fileFullPath . "'";

        exec($cmd, $out);
        $args = json_decode($out[0], null, 512, JSON_THROW_ON_ERROR);
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

    #[Override] public function validate(): void
    {
        $file = Route2::getParam("file");
        $schema = Route2::getParam("schema");
        // Throw exception if tried with resource id
        if (Input::getMethod() == 'post' && $file) {
            $this->postWithResource();
        }
        $this->jwt = Jwt::validate()["data"];
        $this->initiate($schema, null, null, null, null, null, $this->jwt["uid"], $this->jwt["superUser"]);
    }
}
