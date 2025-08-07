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
        Session::start();
    }

    public function get_index(): array
    {

        echo $this->twig->render('header.html.twig');
        echo "<main class='form-signin w-100 m-auto'>";

        if (isset($_GET["key"]) && isset($_GET["user"])) {
            $CachedString = Cache::getItem('__forgot_' . $_GET['user']);
            if ($CachedString != null && $CachedString->isHit()) {
                $val = $CachedString->get();
                if ($val !== $_GET['key']) {
                    echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Wrong key']) . "</div>";
                    return [];
                }
            } else {
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Could not find the key. Maybe it has expired']) . "</div>";
                return [];
            }
            echo "<form hx-post='/forgot'>";
            echo $this->twig->render("reset.html.twig", $_REQUEST);

        } else {
            echo "<form hx-post='/forgot'>";
            echo $this->twig->render("forgot.html.twig");

        }
        echo "</form>";
        echo "<div id='alert'></div>";
        echo "</main>";

        echo $this->twig->render('footer.html.twig');
        return [];
    }

    public function post_index(): array
    {
        Database::setDb("mapcentia");

        if (isset($_POST['password']) && isset($_POST['userid']) && isset($_POST['key'])) {

            $CachedString = Cache::getItem('__forgot_' . $_POST['userid']);
            if ($CachedString != null && $CachedString->isHit()) {
                $val = $CachedString->get();
                if ($val !== $_POST['key']) {
                    echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Wrong key']) . "</div>";
                    return [];
                }
            } else {
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Could not find the key. Maybe it has expired']) . "</div>";
                return [];
            }
            $data["user"] = $_POST['userid'];
            $data["password"] = $_POST['password'];
            try {
                (new UserModel())->updateUser($data);
            } catch (Exception $e) {
                echo $this->twig->render("reset.html.twig", [...$data, 'key' => $_POST['key']]);
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => $e->getMessage()]) . "</div>";
                return [];
            }
            Cache::deleteItem('__forgot_' . $_POST['userid']);
            echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Password changed']) . "</div>";

        } elseif (isset($_POST['userid'])) {
            $user = strrpos($_POST['userid'], '@') === false ? Model::toAscii($_POST['userid'], null, '_') : $_POST['userid'];
            $res = (new UserModel())->getDatabasesForUser($_POST['userid']);
            if (sizeof($res['databases']) == 1 && empty($res['databases'][0]['parentdb'])) {
                $user = $res['databases'][0]['screenname'];
                echo "<div id='alert' hx-swap-oob='true'></div>";
                // Create key and send mail
                $key = uniqid();
                $CachedString = Cache::getItem('__forgot_' . $user);
                $CachedString->set($key)->expiresAfter(3600);
                Cache::save($CachedString);
                $CachedString = Cache::getItem($key);
                $CachedString->set(1)->expiresAfter(3600);
                Cache::save($CachedString);

                $client = new PostmarkClient(App::$param["notification"]["key"]);
                $url = App::$param["host"] . "/forgot?key=$key&user=$user";
                try {
                    $client = new PostmarkClient(App::$param["notification"]["key"]);
                    $message = [
                        'To' => $res['databases'][0]['email'],
                        'From' => App::$param["notification"]["from"],
                        'TrackOpens' => false,
                        'Subject' => "Reset link",
                        'HtmlBody' => "<a href='$url'>Click here</a> to reset your password.",
                    ];
                    try {
                        $sendResult = $client->sendEmailBatch([$message]);
                    } catch (Exception $generalException) {
                        return [];
                    }
                } catch (Exception $generalException) {
                    return [];
                }
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'E-mail with reset link is send']) . "</div>";
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => $url]) . "</div>";

            } else if (sizeof($res['databases']) > 1 || !empty($res['databases'][0]['parentdb'])) {
                echo $this->twig->render("forgot.html.twig");
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'Only super users can reset password']) . "</div>";

            } else {
                echo $this->twig->render("forgot.html.twig");
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'User doesn\'t exists']) . "</div>";

            }
        }
        end:
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