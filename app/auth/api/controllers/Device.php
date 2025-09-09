<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\auth\api\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\Controller;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\inc\Cache;
use app\inc\Connection;
use app\inc\Jwt;
use app\inc\Route2;
use app\inc\Session;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;


#[Controller(route: 'device', scope: Scope::PUBLIC)]
class Device extends AbstractApi
{

    public function __construct(private readonly Route2 $route, Connection $connection, private $twig = new Environment(new FilesystemLoader(__DIR__ . '/../templates')))
    {
        parent::__construct($connection);
    }

    public function get_index(): Response
    {
        echo $this->twig->render('header.html.twig');
        $backend = Session::isAuth() ? 'device' : 'signin';

        echo "<main class='form-signin w-100 m-auto'>";
        echo "<div hx-trigger='load' hx-target='this' hx-target='this' hx-post='/$backend'></div>";
        echo "<div id='alert'></div>";
        echo "</main>";

        echo $this->twig->render('footer.html.twig');
        return $this->emptyResponse();
    }

    public function post_index(): Response
    {
        $code = $_POST['user-code'];
        $cachedString = Cache::getItem($code);

        if ($cachedString != null && $cachedString->isHit()) {
            $val = $cachedString->get();
            if (!empty($val) && $val == 1) {
                $cachedString->set($_SESSION)->expiresAfter(Jwt::DEVICE_CODE_TTL);
                Cache::save($cachedString);
                echo "<div id='alert' hx-swap-oob='true'>Device code found</div>";
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Device code found']) . "</div>";
            } else {
                echo $this->twig->render("device.html.twig");
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Device code already used']) . "</div>";
            }
        } else {
            echo $this->twig->render("device.html.twig");
            if ($code) {
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Code doesn\'t exists']) . "</div>";

            }
        }
        return $this->emptyResponse();
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