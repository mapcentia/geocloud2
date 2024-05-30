<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v1;

use app\exceptions\GC2Exception;
use app\inc\Controller;
use app\inc\Input;
use app\api\v2\Sql as V2Sql;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;

/**
 * Class Sql
 * @package app\api\v1
 */
class Sql extends Controller
{
    private V2Sql $v2;

    function __construct()
    {
        parent::__construct();
        $this->v2 = new V2Sql();
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function get_index() : array
    {
        $db = Input::getPath()->part(4);
        return $this->v2->get_index(['user'=>$db]);
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function post_index(): array
    {
        return $this->get_index();
    }
}