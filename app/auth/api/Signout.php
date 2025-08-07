<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\auth\api;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableMethods;
use app\inc\Input;
use app\inc\Session;


#[AcceptableMethods(['GET', 'HEAD', 'OPTIONS'])]
class Signout extends AbstractApi
{
    public function __construct()
    {
        Session::start();
    }

    public function get_index(): array
    {
        (new \app\models\Session())->stop();
        $r = null;
        $encoded = Input::get('redirect_url');
        if ($encoded) {
            $r = urldecode(Input::get('redirect_url'));
        }
        header("Location: $r");
        return [];
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