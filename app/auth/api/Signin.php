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
use app\inc\Connection;
use app\inc\Jwt;
use app\inc\Session;
use app\models\Client;
use app\models\User;
use Exception;
use Postmark\PostmarkClient;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use app\models\Database;
use app\models\Session as SessionModel;


class Signin extends AbstractApi
{

    public function __construct(private $twig = new Environment(new FilesystemLoader(__DIR__ . '/templates')))
    {
        Session::start();
    }

    public function get_index(): array
    {
        // TODO: Implement get_index() method.
    }

    public function post_index(): array
    {
        $userObj = new User();
        if ($_POST['database'] && $_POST['user'] && $_POST['password'] && !$_POST['tf_code']) {
            try {
                $res = $userObj->getDatabasesForUser($_POST['user']);
                // Check if user/password is correct
                (new SessionModel())->start($_POST['user'], $_POST['password'], "public", $_POST['database'], true);
                // Check if client has two factor enabled
                $client = new Client(connection: new Connection(database: $_POST['database']));
                $clientData = $client->get($_POST['client_id']);
                if (!$clientData[0]['twofactor']) {
                    Session::start();
                    (new SessionModel())->start($_POST['user'], $_POST['password'], "public", $_POST['database']);
                    header('HX-Refresh: true');
                    return [];
                }
                // Get email from database
                $email = null;
                foreach ($res['databases'] as $db) {
                    if ($db['parentdb'] == $_POST['database'] || empty($db['parentdb'])) {
                        $email = $db['email'];
                        break;
                    }
                }
                // Create key/value
                $val = Jwt::generateUserCode();
                $key = '__twofactor_' . md5($_POST['user']) . '_' . $_POST['database'];
                $userObj->cacheCode($key, $val);
                // Send email
                $client = new PostmarkClient(App::$param["notification"]["key"]);
                if (empty($email)) {
                    echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Could not find E-mail']) . "</div>";
                    return [];
                }
                $message = [
                    'To' => $email,
                    'From' => App::$param["notification"]["from"],
                    'TrackOpens' => false,
                    'Subject' => "Two factor link",
                    'HtmlBody' => "<div>$val</div>",
                ];
                $client->sendEmailBatch([$message]);
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => "E-mail with one-time code is send."]) . "</div>";
            } catch (Exception $e) {
                unset($_POST['password']);
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => $e->getMessage()]) . "</div>";
                echo "<div id='forgot' hx-swap-oob='true'><a href='/forgot?parentdb={$_POST['parentdb']}'>Forgot the password?</a></div>";
            }
            echo $this->twig->render('signin.html.twig', [...$res ?? [], ...$_POST]);
            return [];

        } elseif ($_POST['database'] && $_POST['user'] && $_POST['password'] && $_POST['tf_code']) {
            $res = $userObj->getDatabasesForUser($_POST['user']);
            // Check if key is correct
            $key = '__twofactor_' . md5($_POST['user']) . '_' . $_POST['database'];
            try {
                $val = $userObj->getCode($key);
                if ($val !== $_POST['tf_code']) {
                    echo $this->twig->render('signin.html.twig', [...$res, ...$_POST]);
                    echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'One-time code is wrong']) . "</div>";
                    return [];
                }
            } catch (Exception $e) {
                echo $this->twig->render('signin.html.twig', [...$res, ...$_POST]);
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Could not find the one-time code. Maybe it has expired or already used']) . "</div>";
                return [];
            }
            // Delete key
            Cache::deleteItem($key);
            // Start session and refresh browser
            try {
                Session::start();
                (new SessionModel())->start($_POST['user'], $_POST['password'], "public", $_POST['database']);
                header('HX-Refresh: true');
            } catch (Exception) {
                echo $this->twig->render('signin.html.twig', [...$res, ...$_POST]);
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Wrong password']) . "</div>";
            }
            return [];

        } elseif ($_POST['user']) {
            // Get database for user
            try {
                $res = (new User())->getDatabasesForUser($_POST['user']);
                $check = false;
                foreach ($res['databases'] as $db) {
                    if ($db['parentdb'] == $_POST['database'] || empty($_POST['parentdb'])) {
                        $check = true;
                    }
                }
                if (!$check) {
                    echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'User doesn\'t exists']) . "</div>";
                    echo $this->twig->render('signin.html.twig', [...$_POST]);
                    return [];
                }
                echo $this->twig->render('signin.html.twig', [...$res, ...$_POST]);
            } catch (Exception $e) {
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'User doesn\'t exists']) . "</div>";
                echo $this->twig->render('signin.html.twig', [...$_POST]);
            }
        } else {
            echo $this->twig->render('signin.html.twig', [...$_POST]);
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