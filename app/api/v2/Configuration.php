<?php
/**
 * @OA\Info(title="GC2 API", version="0.1")
 */

/**
 * @author     Aleksandr Shumilov <shumsan1011@gmail.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v2;

use \app\inc\Route;
use \app\inc\Input;
use \app\inc\Controller;
use \app\models\Keyvalue;
use \app\inc\Session;
use \OpenApi\Annotations as OA;


/**
 * Class Configuration
 * @package app\api\v2
 */
class Configuration extends Controller
{

    private $keyvalue;

    /**
     * Configuration constructor.
     */
    function __construct()
    {
        parent::__construct();
        $this->keyvalue = new Keyvalue();
        $this->postgisdb = "mapcentia";
        $this->keyValuePrefix = "configuration_";
    }

    /**
     * API section GET router
     */
    function get_index(): array
    {
        if (empty(Route::getParam("configurationId"))) {
            return $this->get_all();
        } else {
            return $this->get_specific();
        }
    }

    /**
     * @return array
     *
     * @OA\Get(
     *   path="/api/v2/configuration/{userId}",
     *   tags={"configuration"},
     *   summary="Returns all configurations (unpublished configurations are returned if authorized user is the author)",
     *   @OA\Parameter(
     *     name="userId",
     *     in="path",
     *     required=true,
     *     description="User identifier",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Operation status"
     *   )
     * )
     */
    function get_all(): array
    {
        $userId = Route::getParam('userId');
        $returnNonPublished = false;

        if (Session::getUser() && empty($userId) === false && $userId === Session::getUser()) {
            $returnNonPublished = true;
        }

        $configurations = $this->keyvalue->get(false, ['like' => $this->keyValuePrefix . '%']);
        $filteredData = [];
        foreach($configurations['data'] as $item) {
            $parsedConfiguration = json_decode($item['value'], true);
            if ($parsedConfiguration) {
                if ($parsedConfiguration['published'] || $returnNonPublished) {
                    $filteredData[] = $item;
                }
            }
        }
        $configurations['data'] = $filteredData;
        return $configurations;
    }

    /**
     * @return array
     *
     * @OA\Get(
     *   path="/api/v2/configuration/{userId}/{configurationId}",
     *   tags={"configuration"},
     *   summary="Returns specific configuration (unpublished configuration is returned if authorized user is the author)",
     *   @OA\Parameter(
     *     name="userId",
     *     in="path",
     *     required=true,
     *     description="User identifier",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="configurationId",
     *     in="path",
     *     required=true,
     *     description="Configuration id (key)",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Operation status"
     *   )
     * )
     */
    function get_specific() : array
    {
        $requestedConfiguration = Route::getParam('configurationId');
        if (empty($requestedConfiguration)) {
            return [
                'success' => false,
                'message' => 'Configuration identifier was not specified',
                'code' => 401
            ];
        }

        $requestedAsFile = false;
        if (substr($requestedConfiguration, -strlen('.json')) === '.json') {
            $requestedAsFile = true;
            $requestedConfiguration = str_replace('.json', '', $requestedConfiguration);
        }

        $userId = Route::getParam('userId');
        $returnNonPublished = false;
        if (Session::getUser() && empty($userId) === false && $userId === Session::getUser()) {
            $returnNonPublished = true;
        }

        $configuration = $this->keyvalue->get($requestedConfiguration, []);
        if (empty($configuration['data'])) {
            return [
                'success' => false,
                'message' => 'Unable to find the configuration with provided identifier (' . $requestedConfiguration . ')',
                'code' => 404
            ];
        }

        $parsedConfiguration = json_decode($configuration['data']['value'], true);
        if ($parsedConfiguration['published'] || $returnNonPublished) {
            if ($requestedAsFile) {
                header('Content-disposition: attachment; filename=' . $requestedConfiguration . '.json');
                header('Content-type: application/json');
                echo $parsedConfiguration['body'];
                exit();
            } else {
                return $configuration;
            }
        } else {
            return [
                'success' => false,
                'code' => 403
            ];
        }
    }

    /**
     * @return array
     *
     * @OA\Post(
     *   path="/api/v2/configuration/{userId}/",
     *   tags={"configuration"},
     *   summary="Creates configuration",
     *   @OA\Parameter(
     *     name="userId",
     *     in="path",
     *     required=true,
     *     description="User identifier",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\RequestBody(
     *     description="Configuration data",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="name",type="string"),
     *         @OA\Property(property="published",type="boolean"),
     *         @OA\Property(property="description",type="string"),
     *         @OA\Property(property="body",type="string"),
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Operation status"
     *   )
     * )
     */
    function post_index(): array
    {
        if (Session::isAuth()) {
            $data = json_decode(Input::getBody(), true) ? : [];
            $missingDataKeys = [];
            $requiredDataKeys = ["name", "published", "description", "body"];
            foreach ($requiredDataKeys as $key) {
                if (!isset($data[$key])) $missingDataKeys[] = $key;
            }

            if (sizeof($missingDataKeys) > 0) {
                return [
                    'success' => false,
                    'message' => 'Some properties are missing: ' . implode(', ', $missingDataKeys),
                    'code' => 400
                ];
            }

            $keyPrefix = $this->keyValuePrefix . $this->keyvalue::toAscii($data["name"], NULL, "_") . '_';
            $key = str_replace('.', '', uniqid($keyPrefix, TRUE));
            $data['key'] = $key;
            return $this->keyvalue->insert($key, json_encode($data));
        } else {
            return [
                'success' => false,
                'code' => 401
            ];
        }
    }

    /**
     * @return array
     *
     * @OA\Put(
     *   path="/apiv2/configuration/{userId}/{configurationId}",
     *   tags={"configuration"},
     *   summary="Creates configuration",
     *   @OA\Parameter(
     *     name="userId",
     *     in="path",
     *     required=true,
     *     description="User identifier",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="configurationId",
     *     in="path",
     *     required=true,
     *     description="Configuration id (key)",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\RequestBody(
     *     description="Configuration data",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="name",type="string"),
     *         @OA\Property(property="published",type="boolean"),
     *         @OA\Property(property="description",type="string"),
     *         @OA\Property(property="body",type="string"),
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Operation status"
     *   )
     * )
     */
    function put_index(): array
    {
        if (Session::isAuth()) {
            $data = json_decode(Input::getBody(), true) ? : [];
            $missingDataKeys = [];
            $requiredDataKeys = ['name', 'published', 'description', 'body'];
            foreach ($requiredDataKeys as $key) {
                if (!isset($data[$key])) $missingDataKeys[] = $key;
            }

            if (sizeof($missingDataKeys) > 0) {
                return [
                    'success' => false,
                    'message' => 'Some properties are missing: ' . implode(', ', $missingDataKeys),
                    'code' => 400
                ];
            }

            $key = Route::getParam('configurationId');
            if (empty($key)) {
                return [
                    'success' => false,
                    'message' => 'Configuration identifier is missing',
                    'code' => 400
                ];
            }

            $data['key'] = $key;
            return $this->keyvalue->update($key, json_encode($data));
        } else {
            return [
                'success' => false,
                'code' => 401
            ];
        }
    }


    /**
     * @return array
     *
     * @OA\Delete(
     *   path="/api/v2/configuration/{userId}/{configurationId}",
     *   tags={"configuration"},
     *   summary="Deletes configuration",
     *   @OA\Parameter(
     *     name="userId",
     *     in="path",
     *     required=true,
     *     description="User identifier",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="configurationId",
     *     in="path",
     *     required=true,
     *     description="Configuration id (key)",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Operation status"
     *   )
     * )
     */
    function delete_index(): array
    {
        if (Session::isAuth()) {
            $requestedConfiguration = Route::getParam('configurationId');
            if (empty($requestedConfiguration)) {
                return [
                    'success' => false,
                    'message' => 'Configuration identifier was not specified',
                    'code' => 401
                ];
            }

            $userId = Route::getParam('userId');
            $isOwner = false;
            if (Session::getUser() && empty($userId) === false && $userId === Session::getUser()) {
                $isOwner = true;
            }

            $configuration = $this->keyvalue->get($requestedConfiguration, []);
            if (empty($configuration['data'])) {
                return [
                    'success' => false,
                    'message' => 'Unable to find the configuration with provided identifier (' . $requestedConfiguration . ')',
                    'code' => 404
                ];
            }

            if ($isOwner) {
                return $this->keyvalue->delete($requestedConfiguration);
            } else {
                return [
                    'success' => false,
                    'message' => 'Only owner can delete the configuration',
                    'code' => 403
                ];
            }
        } else {
            return [
                'success' => false,
                'code' => 401
            ];
        }
    }
}