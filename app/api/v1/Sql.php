<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2022 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v1;

use \app\inc\Input;
use app\api\v2\Sql as V2Sql;

/**
 * Class Sql
 * @package app\api\v1
 */
class Sql extends \app\inc\Controller
{
    private $v2;

    function __construct()
    {
        parent::__construct();
        $this->v2 = new V2Sql();
    }

    public function get_index() : array
    {
        $db = Input::getPath()->part(4);
        return $this->v2->get_index($db);
    }

    public function post_index()
    {
        return $this->get_index();
    }
}