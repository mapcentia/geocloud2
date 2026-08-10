<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\controllers;

use app\exceptions\GC2Exception;
use app\inc\Controller;
use app\inc\Response;
use app\inc\Input;
use Psr\Cache\InvalidArgumentException;

class Tile extends Controller
{
    private \app\models\Tile $wmslayer;
    private readonly string $rel;

    function __construct()
    {
        parent::__construct();
        $this->wmslayer = new \app\models\Tile(Input::getPath()->part(4));
        $this->rel = Input::getPath()->part(4);
    }

    /**
     * @return array
     * @throws InvalidArgumentException
     * @throws GC2Exception
     */
    public function get_index(): array
    {
        $response = $this->auth($this->rel, array("read" => true, "write" => true, "all" => true));
        return (!$response['success']) ? $response :  $this->wmslayer->get();
    }

    /**
     * @return array
     * @throws InvalidArgumentException
     * @throws GC2Exception
     */
    public function put_index(): array
    {
        $response = $this->auth($this->rel);
        return (!$response['success']) ? $response : $this->wmslayer->update(json_decode(Input::get(null, true))->data);
    }
}
