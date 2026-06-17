<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

namespace app\controllers;

use app\exceptions\GC2Exception;
use app\inc\Controller;
use app\inc\Input;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;

class Classification extends Controller
{
    private \app\models\Classification $class;

    /**
     * @throws PhpfastcacheInvalidArgumentException|GC2Exception
     */
    function __construct()
    {
        parent::__construct();

        $this->class = new \app\models\Classification(Input::getPath()->part(4));
    }

    /**
     * @return array
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    public function get_index(): array
    {
        $id = Input::getPath()->part(5);
        $response = $this->auth(Input::getPath()->part(4), array("read" => true, "write" => true, "all" => true));
        return (!$response['success']) ? $response : (($id !== false && $id !== null && $id !== "") ? $this->class->get($id) : $this->class->getAll());
    }

    /**
     * @return array
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    public function post_index(): array
    {
        $response = $this->auth(Input::getPath()->part(4));
        return (!$response['success']) ? $response : $this->class->insert();
    }

    /**
     * @return array
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    public function put_index(): array
    {
        $response = $this->auth(Input::getPath()->part(4));
        $data = json_decode(Input::get(null, true))->data;
        $data->force = true;
        return (!$response['success']) ? $response : $this->class->update(Input::getPath()->part(5), $data);
    }

    /**
     * @return array
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    public function delete_index(): array
    {
        $response = $this->auth(Input::getPath()->part(4));
        return (!$response['success']) ? $response : $this->class->destroy(json_decode(Input::get())->data);
    }

    /**
     * @return array
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    public function put_unique(): array
    {
        $response = $this->auth(Input::getPath()->part(4));
        return (!$response['success']) ? $response : $this->class->createUnique(Input::getPath()->part(5), json_decode(urldecode(Input::get()))->data);
    }

    /**
     * @return array
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    public function put_single(): array
    {
        $response = $this->auth(Input::getPath()->part(4));
        return (!$response['success']) ? $response : $this->class->createSingle(json_decode(urldecode(Input::get()))->data, Input::getPath()->part(5));
    }

    /**
     * @return array
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    public function put_equal(): array
    {
        $response = $this->auth(Input::getPath()->part(4));
        return (!$response['success']) ? $response : $this->class->createEqualIntervals(Input::getPath()->part(5), Input::getPath()->part(6), Input::getPath()->part(7), Input::getPath()->part(8), json_decode(urldecode(Input::get()))->data);
    }

    /**
     * @return array
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    public function put_quantile(): array
    {
        $response = $this->auth(Input::getPath()->part(4));
        return (!$response['success']) ? $response : $this->class->createQuantile(Input::getPath()->part(5), Input::getPath()->part(6), Input::getPath()->part(7),  Input::getPath()->part(8), json_decode(urldecode(Input::get()))->data);
    }

    /**
     * @return array
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    public function put_cluster(): array
    {
        $response = $this->auth(Input::getPath()->part(4));
        return (!$response['success']) ? $response : $this->class->createCluster(Input::getPath()->part(5), json_decode(urldecode(Input::get()))->data);
    }

    /**
     * @return array
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    public function put_copy(): array
    {
        $response = $this->auth(Input::getPath()->part(4));
        return (!$response['success']) ? $response : $this->class->copyClasses(Input::getPath()->part(4), Input::getPath()->part(5));
    }
}