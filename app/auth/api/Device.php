<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\auth\api;

use app\api\v4\AbstractApi;
use app\inc\Session;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use app\inc\Cache;
use app\inc\Jwt;


class Device extends AbstractApi
{

    public function __construct(private $twig = new Environment(new FilesystemLoader(__DIR__ . '/templates')))
    {
        Session::start();
    }

    public function get_index(): array
    {
        echo $this->twig->render('header.html.twig');
        $backend = Session::isAuth() ? 'device' : 'signin';

        echo "<main class='form-signin w-100 m-auto'>";
        echo "<div hx-trigger='load' hx-target='this' hx-target='this' hx-post='/$backend'></div>";
        echo "<div id='alert'></div>";
        echo "</main>";

        echo $this->twig->render('footer.html.twig');
        return [];
    }

    public function post_index(): array
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
        return [];
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