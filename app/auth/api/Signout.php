<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 *
 */

namespace app\auth\api;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableMethods;
use app\inc\Input;
use app\inc\Route2;
use app\inc\Session;


#[AcceptableMethods(['GET', 'HEAD', 'OPTIONS'])]
class Signout extends AbstractApi
{

    public function __construct()
    {
        Session::start();
    }

    public function get_index(): never
    {
        (new \app\models\Session())->stop();

        $r = null;
        $encoded = Input::get('redirect_url');
        if ($encoded) {
            $r = urldecode(Input::get('redirect_url'));
        }
        header("Location: $r");
        exit();
    }

    public function post_index(): array
    {
        // TODO: Implement get_index() method.
    }

    public function put_index(): array
    {
        // TODO: Implement put_index() method.
    }

    public function delete_index(): array
    {
        // TODO: Implement delete_index() method.
    }

    public function validate(): void
    {
        // TODO: Implement validate() method.
    }

    public function patch_index(): array
    {
        // TODO: Implement patch_index() method.
    }
}