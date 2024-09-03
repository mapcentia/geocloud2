<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use app\inc\Model;
use app\models\Database;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Route2;
use app\models\Table as TableModel;
use Exception;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;


/**
 * Class Sql
 * @package app\api\v4
 */
#[AcceptableMethods(['GET', 'HEAD', 'OPTIONS'])]
class Stat extends AbstractApi
{

    /**
     * @throws Exception
     */
    public function __construct()
    {

    }

    public function get_index(): array
    {


        return (new Model())->getStats();
    }

    public function post_index(): array
    {

    }

    public function put_index(): array
    {

    }

    public function delete_index(): array
    {

    }

    public function validate(): void
    {

    }
}
