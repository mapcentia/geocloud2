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
    #[AcceptableAccepts(['application/json', '*/*'])]
    public function get_index(): array
    {
        $r = [];
        $client = new ClientModel();

        if (!empty(Route2::getParam("id"))) {
            $ids = explode(',', Route2::getParam("id"));
            foreach ($ids as $id) {
                $r[] = $client->get($id)[0];
            }
        } else {
            $r = $client->get();
        }
        if (count($r) > 1) {
            return ["clients" => $r];
        } else {
            return $r[0];
        }
    }

    /**
     * @throws RandomException
     */
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json'])]
    public function post_index(): array
    {
        $list = [];
        $model = new ClientModel();
        $body = Input::getBody();
        $data = json_decode($body, true);
        $model->connect();
        $model->begin();
        if (!isset($data['clients'])) {
            $data['clients'] = [$data];
        }
        foreach ($data['clients'] as $datum) {
            $arr = [
                'name' => $datum['name'],
                'redirectUri' => json_encode($datum['redirect_uri']),
                'homepage' => $datum['homepage'] ?? null,
                'description' => $datum['description'] ?? null,
            ];
            $list[] = $model->insert(...$arr);
        }
        $model->commit();
        if (count($list) > 1) {
            return ["clients" => $list];
        } else {
            return $list[0];
        }
    }

    /**
     * @throws GC2Exception
     */
    #[AcceptableContentTypes(['application/json'])]
    public function put_index(): array
    {
        $id = Route2::getParam("id");
        if (empty($id)) {
            throw new GC2Exception("No client id", 404, null, 'MISSING_ID');
        }
        $ids = explode(',', Route2::getParam("id"));
        $body = Input::getBody();
        $data = json_decode($body, true);
        $model = new ClientModel();
        $model->connect();
        $model->begin();
        foreach ($ids as $id) {
            $arr = [
                'id' => $id,
                'name' => $data['name'] ?? null,
                'redirectUri' => json_encode($data['redirect_uri']) ?? null,
                'homepage' => $data['homepage'] ?? null,
                'description' => $data['description'] ?? null,
            ];
            $model->update(...$arr);
        }
        $model->commit();
        header("Location: /api/v4/clients/" . implode(",", $ids));
        return ["code" => "303"];
    }

    /**
     * @throws GC2Exception
     */
    public function delete_index(): array
    {
        $ids = explode(',', Route2::getParam("id"));
        if (empty($ids)) {
            throw new GC2Exception("No client id", 404, null, 'MISSING_ID');
        }
        $model = new ClientModel();
        $model->connect();
        $model->begin();
        foreach ($ids as $id) {
            $model->delete($id);
        }
        $model->commit();
        return ["code" => "204"];
    }
}
