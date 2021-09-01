<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v2;

use app\inc\Controller;
use app\inc\Route;
use app\inc\Input;
use app\inc\Util;
use Exception;

/**
 * Class Keyvalue
 * @package app\api\v2
 */
class Keyvalue extends Controller
{
    /**
     * @var \app\models\Keyvalue
     */
    public $keyValue;

    function __construct()
    {
        parent::__construct();
        $this->keyValue = new \app\models\Keyvalue();
    }

    /**
     * @return array<mixed>
     *
     * @OA\Get(
     *   path="/api/v2/keyvalue/{userId}/{key}",
     *   tags={"Keyvalue"},
     *   summary="Get value by key",
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
     *     name="key",
     *     in="path",
     *     required=false,
     *     description="Key to fetch",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Operation status",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="success", type="boolean", example=true),
     *         @OA\Property(property="data", type="object")
     *       )
     *     ),
     *     @OA\MediaType(
     *       mediaType="text/plain",
     *       @OA\Schema(
     *         type="string",
     *       )
     *     )
     *   )
     * )
     * @throws Exception
     */
    public function get_index(): array
    {
        $key = Route::getParam("key");
        $data = $this->keyValue->get($key, gettype(Input::get()) == "array" ? Input::get() : [] );
        if (Input::getAccept() == Input::TEXT_PLAIN) {
            $data = Util::base64urlEncode(json_encode($data));
            return [
                "text" => $data
            ];
        }
        return $data;
    }

    /**
     * @return array<mixed>
     *
     * @OA\Post(
     *   path="/api/v2/keyvalue/{userId}/{key}",
     *   tags={"Keyvalue"},
     *   summary="Create new key/value in store",
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
     *     name="key",
     *     in="path",
     *     required=true,
     *     description="Key to create",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\RequestBody(
     *     description="JSON value to store",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object"
     *       )
     *     ),
     *     @OA\MediaType(
     *       mediaType="text/plain",
     *       @OA\Schema(
     *         type="string"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Operation status",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="success", type="boolean", example=true),
     *         @OA\Property(property="data", type="object")
     *       )
     *     ),
     *     @OA\MediaType(
     *       mediaType="text/plain",
     *       @OA\Schema(
     *         type="string",
     *       )
     *     )
     *   )
     * )
     */
    public function post_index(): array
    {
        $key = Route::getParam("key");
        $json = Input::getBody();
        if (Input::getContentType() == Input::TEXT_PLAIN) {
            $json = Util::base64urlDecode($json);
        }
        $data = $this->keyValue->insert($key, $json);
        if (Input::getAccept() == Input::TEXT_PLAIN) {
            return [
                "text" => Util::base64urlEncode(json_encode($data))
            ];
        }
        return $data;
    }

    /**
     * @return array<mixed>
     *
     * @OA\Put(
     *   path="/api/v2/keyvalue/{userId}/{key}",
     *   tags={"Keyvalue"},
     *   summary="Update value in store",
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
     *     name="key",
     *     in="path",
     *     required=true,
     *     description="Key to update",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\RequestBody(
     *     description="New JSON value to store",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object"
     *       )
     *     ),
     *     @OA\MediaType(
     *       mediaType="text/plain",
     *       @OA\Schema(
     *         type="string"
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Operation status",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="success", type="boolean", example=true),
     *         @OA\Property(property="data", type="object")
     *       )
     *     ),
     *     @OA\MediaType(
     *       mediaType="text/plain",
     *       @OA\Schema(
     *         type="string",
     *       )
     *     )
     *   )
     * )
     */
    public function put_index(): array
    {
        $key = Route::getParam("key");
        $json = Input::getBody();
        if (Input::getContentType() == Input::TEXT_PLAIN) {
            $json = Util::base64urlDecode($json);
        }
        $data = $this->keyValue->update($key, $json);
        if (Input::getAccept() == Input::TEXT_PLAIN) {
            return [
                "text" => Util::base64urlEncode(json_encode($data))
            ];
        }
        return $data;
    }

    /**
     * @return array<mixed>
     *
     * @OA\Delete(
     *   path="/api/v2/keyvalue/{userId}/{key}",
     *   tags={"Keyvalue"},
     *   summary="Delete a key/bvalue in store",
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
     *     name="key",
     *     in="path",
     *     required=true,
     *     description="Key to delete",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Operation status",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="data", type="string", example="my_key"),
     *         @OA\Property(property="success", type="boolean", example=true)
     *       )
     *     )
     *   )
     * )
     */
    public function delete_index(): array
    {
        $key = Route::getParam("key");
        return $this->keyValue->delete($key);
    }
}