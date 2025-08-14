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
use app\models\User as UserModel;
use Error;
use Exception;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use app\models\Database;


class Activation extends AbstractApi
{

    public function __construct(private $twig = new Environment(new FilesystemLoader(__DIR__ . '/templates')))
    {
//        Session::start();
    }

    public function get_index(): array
    {
        $loader = new FilesystemLoader(__DIR__ . '/templates');
        $twig = new Environment($loader);

        echo $twig->render('header.html.twig');
        echo "<main class='form-signin w-100 m-auto'>";
        echo "<div hx-trigger='load' hx-post='/activation'></div>";
        echo $twig->render('footer.html.twig');
        echo "<div id='alert'></div>";
        echo "</main>";
        return [];
    }

    public function post_index(): array
    {
        Database::setDb("mapcentia");
        if ($_POST['email']) {
            $model = new UserModel();
            $model->connect();
            $model->begin();
            try {
                $model->sendCode($_POST['email']);
            } catch (Error|Exception $e) {
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => $e->getMessage()]) . "</div>";
                echo $this->twig->render('activation.html.twig', ['email' => $_POST['email']]);
                return [];
            }
            $model->commit();
            echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'E-mail with activation code is send']) . "</div>";
        } else {
            echo $this->twig->render('activation.html.twig');
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