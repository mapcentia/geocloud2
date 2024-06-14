<?php
/**
 * @author     Martin Høgh <shumsan1011@gmail.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Route2;
use app\models\Client as ClientModel;
use Random\RandomException;


/**
 * Class User
 * @package app\api\v2
 */
#[AcceptableMethods(['POST', 'PUT', 'DELETE', 'GET', 'HEAD', 'OPTIONS'])]
class Client extends AbstractApi
{
    public function __construct()
    {
    }

    public function validate(): void
    {
        // TODO: Implement validate() method.
    }

    /**
     * @throws GC2Exception
     */
    public function get_index(): array
    {
        $id = Route2::getParam("id");
        $client = new ClientModel();
        if (!empty($id)) {
            return $client->get($id)[0];
        } else {
            return ['clients' => $client->get($id)];
        }
    }

    /**
     * @throws RandomException
     * @throws GC2Exception
     */
    public function post_index(): array
    {
        $body = Input::getBody();
        $arr = json_decode($body, true);
        $arr2 = [
            'name' => $arr['name'],
            'redirectUri' => $arr['redirect_uri'],
            'username' => Jwt::validate()['data']['uid'],
            'homepage' => $arr['homepage'] ?? null,
            'description' => $arr['description'] ?? null,
        ];
        return (new ClientModel())->insert(...$arr2);
    }

    /**
     * @throws GC2Exception
     */
    public function put_index(): array
    {
        $id = Route2::getParam("id");
        if (empty($id)) {
            throw new GC2Exception("No client id", 404, null, 'MISSING_ID');
        }
        $body = Input::getBody();
        $arr = json_decode($body, true);
        $arr2 = [
            'id' => $id,
            'name' => $arr['name'] ?? null,
            'redirectUri' => $arr['redirect_uri'] ?? null,
            'homepage' => $arr['homepage'] ?? null,
            'description' => $arr['description'] ?? null,
        ];
        (new ClientModel())->update(...$arr2);
        header("Location: /api/v4/clients/$id");
        return ["code" => "303"];
    }

    /**
     * @throws GC2Exception
     */
    public function delete_index(): array
    {
        $id = Route2::getParam("id");
        if (empty($id)) {
            throw new GC2Exception("No client id", 404, null, 'MISSING_ID');
        }
        (new ClientModel())->delete($id);
        return ["code" => "204"];
    }
}
