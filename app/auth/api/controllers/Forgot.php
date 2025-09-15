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
use app\api\v4\Responses\EmptyResponse;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\conf\App;
use app\inc\Cache;
use app\inc\Connection;
use app\inc\Route2;
use app\models\User;
use Exception;
use Postmark\PostmarkClient;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

#[Controller(route: 'forgot', scope: Scope::PUBLIC)]
class Forgot extends AbstractApi
{

    public function __construct(private readonly Route2 $route, Connection $connection, private $twig = new Environment(new FilesystemLoader(__DIR__ . '/../templates')))
    {
        parent::__construct($connection);
    }

    public function get_index(): Response
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
                    return $this->emptyResponse();
                }
                if ($_GET['parentdb'] && $val[1] !== $_GET['parentdb']) {
                    echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Wrong parentdb']) . "</div>";
                    return $this->emptyResponse();
                }
            } catch (Exception $e) {
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Could not find the key. Maybe it has expired']) . "</div>";
                return $this->emptyResponse();
            }
            echo $this->twig->render("reset.html.twig", $_GET);

        } else {
            echo $this->twig->render("forgot.html.twig", ['parentdb' => $_GET['parentdb'] ?? '']);

        }
        echo "<div id='alert'></div>";
        echo "</main>";

        echo $this->twig->render('footer.html.twig');
        return $this->emptyResponse();
    }

    public function post_index(): Response
    {
        $userObj = new User();
        if (isset($_POST['password']) && isset($_POST['userid']) && isset($_POST['key'])) {

            $key = '__forgot_' . md5($_POST['userid']);
            try {
                $val = $userObj->getCode($key);
                if ($val[0] !== $_POST['key']) {
                    echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Wrong key']) . "</div>";
                    return $this->emptyResponse();
                }
                if ($_POST['parentdb'] && $val[1] !== $_POST['parentdb']) {
                    echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Wrong parentdb']) . "</div>";
                    return $this->emptyResponse();
                }
            } catch (Exception $e) {
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Could not find the key. Maybe it has expired']) . "</div>";
                return $this->emptyResponse();
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
                return $this->emptyResponse();
            }
            Cache::deleteItem($key);
            echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Password changed']) . "</div>";

        } elseif (isset($_POST['userid'])) {
            try {
                $res =$userObj->getDatabasesForUser($_POST['userid']);
            } catch (Exception $e) {
                echo $this->twig->render("forgot.html.twig", ['parentdb' => $_POST['parentdb'] ?? '']);
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => $e->getMessage()]) . "</div>";
                return $this->emptyResponse();
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
                    $html = $this->twig->render('email_reset.html.twig', [
                        'app_name' => App::$param['appName'] ?? 'GC2',
                        'recipient_email' => $email,
                        'reset_url' => $url,
                        'expires_in' => '30 minutes',
                        'context_info' => !empty($parentdb) ? ('database ' . $parentdb) : null,
                    ]);
                    $message = [
                        'To' => $email,
                        'From' => App::$param["notification"]["from"],
                        'TrackOpens' => false,
                        'Subject' => "Reset your password",
                        'HtmlBody' => $html,
                        'TextBody' => "Reset your password using this link: $url\nThis link will expire in 30 minutes.",
                    ];
                    try {
                        $sendResult = $client->sendEmailBatch([$message]);
                    } catch (Exception) {
                        return $this->emptyResponse();
                    }
                } catch (Exception) {
                    return $this->emptyResponse();
                }
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'E-mail with reset link is send.']) . "</div>";

            } else if (sizeof($res['databases']) > 1 || !empty($res['databases'][0]['parentdb'])) {
                echo $this->twig->render("forgot.html.twig", ['databases' => $res['databases'], ...$_POST]);
            } else {
                echo $this->twig->render("forgot.html.twig", ['parentdb' => $_POST['parentdb'] ?? '']);
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'User doesn\'t exists']) . "</div>";
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