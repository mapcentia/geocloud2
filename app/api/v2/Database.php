<?php
/**
 * @author     Aleksandr Shumilov <shumsan1011@gmail.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v2;

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
     *   path="/v2/database/schemas",
     *   tags={"database"},
     *   summary="Returns available schemas",
     *   @OA\Response(
     *     response="200",
     *     description="Operation status"
     *   )
     * )
     */
    function get_schemas(): array
    {
        if (Session::isAuth()) {
            DatabaseModel::setDb(Session::getUser());
            $database = new DatabaseModel();
            return $database->listAllSchemas();
        } else {
            return [
                'success' => false,
                'code' => 401
            ];
        }
    }
}