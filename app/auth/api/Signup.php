<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\auth\api;

use app\api\v4\AbstractApi;
use app\conf\App;
use app\inc\Cache;
use app\inc\Jwt;
use app\models\User;
use Exception;
use Postmark\PostmarkClient;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use app\models\Database;
use app\models\Session as SessionModel;


class Signup extends AbstractApi
{

    public function __construct(private $twig = new Environment(new FilesystemLoader(__DIR__ . '/templates')))
    {
//        Session::start();
    }

    public function get_index(): array
    {
        echo $this->twig->render('header.html.twig');
        echo "<main class='form-signin w-100 m-auto'>";
        echo "<div hx-trigger='load' hx-target='this'  hx-post='/signup/?parentdb=" . $_GET['parentdb'] . "&r=" . $_GET['redirect_url'] . "'></div>";
        echo "<div id='alert'></div>";
        echo "</main>";
        echo $this->twig->render('footer.html.twig');
        return [];
    }

    public function post_index(): array
    {
        Database::setDb("mapcentia");
        $userObj = new User();

        if ($_POST['name'] && $_POST['email'] && $_POST['password'] && $_POST['code'] && !$_POST['tf_code']) {
            $userObj->connect();
            $userObj->begin();
            try {
                $userObj->checkCode($_POST['code'], $_POST['email']);
                $userObj->createUser([
                    'name' => $_POST['name'],
                    'email' => $_POST['email'],
                    'password' => $_POST['password'],
                    'subuser' => !empty($_POST['parentdb']),
                    'parentdb' => $_POST['parentdb']
                ]);
            } catch (Exception $e) {
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => $e->getMessage()]) . "</div>";
                unset($_POST['password']);
                echo $this->twig->render('signup.html.twig', $_POST);
                return [];
            } finally {
                $userObj->rollback();
            }

            try {
                // Create key/value
                $val = Jwt::generateUserCode();
                $key = '__twofactor_' . md5($_POST['name']) . '_' . $_POST['parentdb'];
                $userObj->cacheCode($key, $val);
                // Send email
                $client = new PostmarkClient(App::$param["notification"]["key"]);
                $message = [
                    'To' => $_POST['email'],
                    'From' => App::$param["notification"]["from"],
                    'TrackOpens' => false,
                    'Subject' => "Two factor link",
                    'HtmlBody' => "<div>$val</div>",
                ];
                $client->sendEmailBatch([$message]);
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => "E-mail with one-time code is send [$val]"]) . "</div>";
            } catch (Exception $e) {
                unset($_POST['password']);
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => $e->getMessage()]) . "</div>";
            }
            echo $this->twig->render('signup.html.twig', [...$_POST, ...$_GET]);
            return [];

        } elseif ($_POST['name'] && $_POST['email'] && $_POST['password'] && $_POST['code'] && $_POST['tf_code']) {
            try {
                $userObj->connect();
                $userObj->begin();
                $userObj->checkCode($_POST['code'], $_POST['email']);
                // Check if two factor key is correct
                $key = '__twofactor_' . md5($_POST['name']) . '_' . $_POST['parentdb'];
                try {
                    $val = $userObj->getCode($key);
                    if ($val !== $_POST['tf_code']) {
                        echo $this->twig->render('signup.html.twig', [...$_POST, ...$_GET]);
                        echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Wrong one-time code']) . "</div>";
                        return [];
                    }
                } catch (Exception) {
                    echo $this->twig->render('signup.html.twig', [...$_POST, ...$_GET]);
                    echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Could not find the key. Maybe it has expired']) . "</div>";
                    return [];
                }
                // Create user
                $res = $userObj->createUser([
                    'name' => $_POST['name'],
                    'email' => $_POST['email'],
                    'password' => $_POST['password'],
                    'subuser' => !empty($_POST['parentdb']),
                    'parentdb' => $_POST['parentdb']
                ]);
                $userObj->commit();
                // Delete key
                Cache::deleteItem($key);
                (new SessionModel())->start($res['data']['screenname'], $_POST['password'], "public", $res['data']['parentdb']);
                // Redirect
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