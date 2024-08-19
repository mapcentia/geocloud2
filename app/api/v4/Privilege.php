<?php
/**
 * @author     Martin Høgh <shumsan1011@gmail.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use app\inc\Jwt;
use app\inc\Input;
use app\inc\Route2;
use app\models\Layer;
use Override;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;
use StdClass;


/**
 * Class User
 * @package app\api\v2
 */
class Privilege extends AbstractApi
{

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    #[Override] public function validate(): void
    {
        $table = Route2::getParam("table");
        $schema = Route2::getParam("schema");
        $this->jwt = Jwt::validate()["data"];
        // Put and delete on collection is not allowed
        if (empty($table) && in_array(Input::getMethod(), ['put', 'delete'])) {
            throw new GC2Exception("", 406);
        }
        // Throw exception if tried with table resource
        if (Input::getMethod() == 'post' && $table) {
            $this->postWithResource();
        }
        $this->initiate($schema, $table, null, null, null, null, $this->jwt["uid"], $this->jwt["superUser"]);
    }

    /**
     * @return array
     */
    #[Override]
    public function get_index(): array
    {
        $layer = new Layer();
        $key = $this->qualifiedName;
        $res = $layer->getPrivileges($key);
        return ["privileges" => $res['data']];
    }

    #[Override] public function post_index(): array
    {
        // TODO: Implement post_index() method.
        return [];
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     * @throws InvalidArgumentException|GC2Exception
     */
    #[Override] public function put_index(): array
    {
        $layer = new Layer();
        $body = Input::getBody();
        $data = json_decode($body);
        $key = $this->qualifiedName;

        $obj = new StdClass();
        $obj->_key_ = $key;
        $obj->privileges = $data->privileges;
        $obj->subuser = $data->subuser;

        $layer->updatePrivileges($obj);
        return [];
    }

    #[Override] public function delete_index(): array
    {
        // TODO: Implement delete_index() method.
        return[];
    }
}
