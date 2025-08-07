<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\auth\api;

use app\api\v4\AbstractApi;
use app\inc\Route2;
use app\inc\Session;
use Exception;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use app\auth\types\GrantType;
use app\models\Database;
use app\models\Session as SessionModel;


class Signin extends AbstractApi
{

    public function __construct(private $twig = new Environment(new FilesystemLoader(__DIR__ . '/templates')))
    {
        Session::start();
    }

    public function get_index(): never
    {
        // TODO: Implement get_index() method.

    }

    public function post_index(): array
    {
        Database::setDb("mapcentia");

        if ($_POST['database'] && $_POST['user'] && $_POST['password']) {
            // Start session and refresh browser
            try {
                $grantType = match ($_POST['response_type']) {
                    'code' => GrantType::AUTHORIZATION_CODE,
                    'access' => GrantType::PASSWORD,
                    default => null,
                };
                $data = (new SessionModel())->start($_POST['user'], $_POST['password'], "public", $_POST['database']);
                header('HX-Refresh: true');
            } catch (Exception) {
                $res = (new \app\models\User())->getDatabasesForUser($_POST['user']);
                echo $this->twig->render('login.html.twig', [...$res, ...$_POST]);
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Wrong password']) . "</div>";
            }
        } elseif ($user = $_POST['user']) {
            // Get database for user
            $res = [];
            try {
                $res = (new \app\models\User())->getDatabasesForUser($user);
                echo "<div id='alert' hx-swap-oob='true'></div>";

            } catch (Exception $e) {
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'User doesn\'t exists']) ."</div>";

            }
            echo $this->twig->render('login.html.twig', [...$res, ...$_POST]);
        } else {
            echo $this->twig->render('login.html.twig');
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