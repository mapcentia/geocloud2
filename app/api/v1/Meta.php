<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2022 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v1;

use app\inc\Controller;
use app\inc\Input;
use app\inc\Route;
use app\inc\Session;
use app\models\Layer;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;

/**
 * Class Meta
 * @package app\api\v1
 */
class Meta extends Controller
{
    /**
     * @var Layer
     */
    private $layers;

    /**
     * Meta constructor.
     */
    function __construct()
    {
        parent::__construct();

        $this->layers = new Layer();
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     */
    public function get_index()
    {
        // Get the URI params from request
        // /meta/{user}/[query]
        $db = Route::getParam("user");
        $dbSplit = explode("@", $db);
        if (sizeof($dbSplit) == 2) {
            $db = $dbSplit[1];
        }
        return $this->layers->getAll($db, Session::isAuth(), Route::getParam("query"), Input::get("iex"), Input::get("parse"), Input::get("es"), false);
    }
}