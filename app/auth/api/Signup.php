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
use Exception;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use app\models\Database;
use app\models\Session as SessionModel;
use app\models\User as UserModel;


class Signup extends AbstractApi
{

    public function __construct(private $twig = new Environment(new FilesystemLoader(__DIR__ . '/templates')))
    {
        Session::start();
    }

    public function get_index(): array
    {
        echo $this->twig->render('header.html.twig');
        echo "<main class='form-signin w-100 m-auto'>";
        echo "<div hx-trigger='load' hx-target='this'  hx-post='/signup/?db=" . $_GET['db'] . "&r=" . $_GET['redirect_url'] . "'></div>";
        echo "<div id='alert'></div>";
        echo "</main>";
        echo $this->twig->render('footer.html.twig');
        return [];
    }

    public function post_index(): array
    {
        Database::setDb("mapcentia");
        if ($_POST['name'] && $_POST['email'] && $_POST['password'] && $_POST['code']) {
            try {
                $model = new UserModel();
                $model->connect();
                $model->begin();
                //$model->checkCode($_POST['code'], $_POST['email']);
                $res = $model->createUser([
                    'name' => $_POST['name'],
                    'email' => $_POST['email'],
                    'password' => $_POST['password'],
                    'subuser' => !empty($_POST['db']),
                    'parentdb' => $_POST['db']
                ]);
                $model->commit();
                $data = (new SessionModel())->start($_POST['name'], $_POST['password'], "public", $res['screenname']);
                $header = "HX-Redirect: " . urldecode($_POST['r']);
                header($header);
            } catch (Exception $e) {
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => $e->getMessage()]) . "</div>";
            }
        }
        echo $this->twig->render('signup.html.twig', [...$_POST, ...$_GET]);
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