<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\auth\api\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\RedirectResponse;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;


#[AcceptableMethods(['GET', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'signout', scope: Scope::PUBLIC)]
class Signout extends AbstractApi
{
    public function __construct(private readonly Route2 $route, Connection $connection, private $twig = new Environment(new FilesystemLoader(__DIR__ . '/../templates')))
    {
        parent::__construct($connection);
    }

    public function get_index(): Response
    {
        (new \app\models\Session())->stop();
        $r = null;
        $encoded = Input::get('redirect_uri');
        if ($encoded) {
            $r = urldecode(Input::get('redirect_uri'));
        }
        return $this->redirectResponse(location: $r);
    }

    public function post_index(): Response
    {
        // TODO: Implement get_index() method.
    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }

    public function delete_index(): Response
    {
        // TODO: Implement delete_index() method.
    }

    public function validate(): void
    {
        // TODO: Implement validate() method.
    }

    public function patch_index(): Response
    {
        // TODO: Implement patch_index() method.
    }
}