<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\auth\api;

use app\api\v4\AbstractApi;
use app\models\User;
use Exception;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use app\inc\Cache;
use app\inc\Model;
use app\models\Database;
use app\models\User as UserModel;
use app\conf\App;
use Postmark\PostmarkClient;

class Forgot extends AbstractApi
{

    public function __construct(private $twig = new Environment(new FilesystemLoader(__DIR__ . '/templates')))
    {
    }

    public function get_index(): array
    {
        $userObj = new User();

        echo $this->twig->render('header.html.twig');
        echo "<main class='form-signin w-100 m-auto'>";

        if (isset($_GET["key"]) && isset($_GET["user"])) {
            $key = '__forgot_' . md5($_GET['user']);
            try {
                $val = $userObj->getCode($key);
                if ($val[0] !== $_GET['key']) {
                    echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Could not find the key.']) . "</div>";
                    return [];
                }
                if ($_GET['parentdb'] && $val[1] !== $_GET['parentdb']) {
                    echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Wrong parentdb']) . "</div>";
                    return [];
                }
            } catch (Exception $e) {
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Could not find the key. Maybe it has expired']) . "</div>";
                return [];
            }
            echo $this->twig->render("reset.html.twig", $_GET);

        } else {
            echo $this->twig->render("forgot.html.twig", ['parentdb' => $_GET['parentdb'] ?? '']);

        }
        echo "<div id='alert'></div>";
        echo "</main>";

        echo $this->twig->render('footer.html.twig');
        return [];
    }

    public function post_index(): array
    {
        $userObj = new User();
        if (isset($_POST['password']) && isset($_POST['userid']) && isset($_POST['key'])) {

            $key = '__forgot_' . md5($_POST['userid']);
            try {
                $val = $userObj->getCode($key);
                if ($val[0] !== $_POST['key']) {
                    echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Wrong key']) . "</div>";
                    return [];
                }
                if ($_POST['parentdb'] && $val[1] !== $_POST['parentdb']) {
                    echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Wrong parentdb']) . "</div>";
                    return [];
                }
            } catch (Exception $e) {
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Could not find the key. Maybe it has expired']) . "</div>";
                return [];
            }

            $data["user"] = $_POST['userid'];
            $data["password"] = $_POST['password'];
            if (!empty($_POST['parentdb'])) {
                $data["parentdb"] = $_POST['parentdb'];
            }
            try {
                $userObj->updateUser($data);
            } catch (Exception $e) {
                echo $this->twig->render("reset.html.twig", [...$data, 'key' => $_POST['key']]);
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => $e->getMessage()]) . "</div>";
                return [];
            }
            Cache::deleteItem($key);
            echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Password changed']) . "</div>";

        } elseif (isset($_POST['userid'])) {
            try {
                $res =$userObj->getDatabasesForUser($_POST['userid']);
            } catch (Exception $e) {
                echo $this->twig->render("forgot.html.twig", ['parentdb' => $_POST['parentdb'] ?? '']);
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => $e->getMessage()]) . "</div>";
                return [];
            }
            $user = null;
            $email = null;
            $parentdb = null;
            if ($_POST['parentdb']) {
                foreach ($res['databases'] as $db) {
                    if ($db['parentdb'] == $_POST['parentdb']) {
                        $user = $db['screenname'];
                        $email = $db['email'];
                        $parentdb = $db['parentdb'];
                        break;
                    }
                }
            } elseif (sizeof($res['databases']) == 1 && empty($res['databases'][0]['parentdb'])) {
                $user = $res['databases'][0]['screenname'];
                $email = $res['databases'][0]['email'];
            }
            if ($user) {
                echo "<div id='alert' hx-swap-oob='true'></div>";
                // Create key and send mail
                $val = uniqid();
                $key= '__forgot_' . md5($user);
                $userObj->cacheCode($key, [$val, $parentdb]);
                $url = App::$param["host"] . "/forgot?key=$val&user=$user" . (!empty($parentdb) ? "&parentdb=$parentdb" : '');
                try {
                    $client = new PostmarkClient(App::$param["notification"]["key"]);
                    $message = [
                        'To' => $email,
                        'From' => App::$param["notification"]["from"],
                        'TrackOpens' => false,
                        'Subject' => "Reset link",
                        'HtmlBody' => "<a href='$url'>Click here</a> to reset your password.",
                    ];
                    try {
                        $sendResult = $client->sendEmailBatch([$message]);
                    } catch (Exception) {
                        return [];
                    }
                } catch (Exception) {
                    return [];
                }
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'E-mail with reset link is send.']) . "</div>";

            } else if (sizeof($res['databases']) > 1 || !empty($res['databases'][0]['parentdb'])) {
                echo $this->twig->render("forgot.html.twig", ['databases' => $res['databases'], ...$_POST]);
            } else {
                echo $this->twig->render("forgot.html.twig", ['parentdb' => $_POST['parentdb'] ?? '']);
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'User doesn\'t exists']) . "</div>";
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