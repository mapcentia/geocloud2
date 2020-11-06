<?php
/**
 * @author     Aleksandr Shumilov <shumsan1011@gmail.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v2;

use \app\inc\Route;
use \app\inc\Controller;
use \app\models\User as UserModel;
use \app\models\Database as DatabaseModel;
use \app\inc\Session;

/**
 * Class Database
 * @package app\api\v2
 */
class Database extends Controller
{

    private $user;

    /**
     * User constructor.
     */
    function __construct()
    {
        parent::__construct();
        $this->user = new UserModel(Session::isAuth() ? Session::getUser() : null);
    }

    /**
     * @return array
     * 
     * @OA\Get(
     *   path="/api/v2/database/schemas",
     *   tags={"Database"},
     *   summary="Returns available schemas",
     *   security={{"cookieAuth":{}}},
     *   @OA\Response(
     *     response="200",
     *     description="Operation status"
     *   )
     * )
     */
    function get_schemas(): array
    {
        if (Session::isAuth()) {
            DatabaseModel::setDb(Session::getDatabase());
            $database = new DatabaseModel();
            return $database->listAllSchemas();
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
     * @OA\Get(
     *   path="/api/v2/database/search",
     *   tags={"Database"},
     *   summary="Returns databases found according to provided filters",
     *   @OA\Parameter(
     *     name="userIdentifier",
     *     in="query",
     *     required=false,
     *     description="Filters databases that have user with specified name registered",
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
    function get_search(): array
    {
        $queryParameters = array();
        parse_str($_SERVER['QUERY_STRING'], $queryParameters);
        if (empty($queryParameters['userIdentifier'])) {
            return [
                'message' => 'No search parameters were specified',
                'success' => false,
                'code' => 400
            ];
        } else {
            $model = new UserModel();
            $res = $model->getDatabasesForUser($queryParameters['userIdentifier']);
            $res["success"] = true;
            return $res;
        }
    }

}