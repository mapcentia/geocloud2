<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 * @OA\Info(title="Geocloud API", version="0.1")
 *
 */

namespace app\api\v2;

use \app\inc\Route;
use \app\inc\Input;


class Keyvalue extends \app\inc\Controller
{
    public $keyValue;

    function __construct()
    {
        parent::__construct();
        $this->keyValue = new \app\models\Keyvalue();
    }

    /**
     * @return array
     *
     * @OA\Get(
     *   path="/api/v2/keyvalue/{userId}",
     *   tags={"keyvalue"},
     *   summary="Coming",
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
    public function get_index(): array
    {
        $key = Route::getParam("key");
        return $this->keyValue->get($key,  Input::get());
    }

    /**
     * @return array
     */
    public function post_index(): array
    {
        $key = Route::getParam("key");
        $json = Input::getBody();
        return $this->keyValue->insert($key, $json);
    }

    /**
     * @return array
     */
    public function put_index(): array
    {
        $key = Route::getParam("key");
        $json = Input::getBody();
        return $this->keyValue->update($key, $json);
    }

    /**
     * @return array
     */
    public function delete_index(): array
    {
        $key = Route::getParam("key");
        return $this->keyValue->delete($key);
    }
}