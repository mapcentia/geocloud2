<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\inc\Cache;
use app\inc\Model;
use app\models\Database;
use app\models\User as UserModel;
use app\conf\App;
use Postmark\PostmarkClient;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader);

Database::setDb("mapcentia");

if (isset($_POST['password']) && isset($_POST['userid']) && isset($_POST['key'])) {

    $CachedString = Cache::getItem('__forgot_' . $_POST['userid']);
    if ($CachedString != null && $CachedString->isHit()) {
        $val = $CachedString->get();
        if ($val !== $_POST['key']) {
            echo "<div id='alert' hx-swap-oob='true'>" . $twig->render('error.html.twig', ['message' => 'Wrong key']) . "</div>";
            exit();
        }
    } else {
        echo "<div id='alert' hx-swap-oob='true'>" . $twig->render('error.html.twig', ['message' => 'Could not find the key. Maybe it has expired']) . "</div>";
        exit();
    }
    $data["user"] = $_POST['userid'];
    $data["password"] = $_POST['password'];
    try {
        (new UserModel())->updateUser($data);
    } catch (Exception $e) {
        echo $twig->render("reset.html.twig", [...$data, 'key' => $_POST['key']]);
        echo "<div id='alert' hx-swap-oob='true'>" . $twig->render('error.html.twig', ['message' => $e->getMessage()]) . "</div>";
        exit();
    }
    Cache::deleteItem('__forgot_' . $_POST['userid']);
    echo "<div id='alert' hx-swap-oob='true'>" . $twig->render('error.html.twig', ['message' => 'Password changed']) . "</div>";

} elseif (isset($_POST['userid'])) {
    $user =  Model::toAscii($_POST['userid'], null, '_');
    $res = (new UserModel())->getDatabasesForUser($user);
    if (sizeof($res['databases']) == 1 && empty($res['databases'][0]['parentdb'])) {
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
                exit(1);
            }
        } catch (Exception $generalException) {
            exit(1);
        }
        echo "<div id='alert' hx-swap-oob='true'>" . $twig->render('error.html.twig', ['message' => 'E-mail with reset link is send']) . "</div>";
        echo "<div id='alert' hx-swap-oob='true'>" . $twig->render('error.html.twig', ['message' => $url]) . "</div>";

    } else if (sizeof($res['databases']) > 1 || !empty($res['databases'][0]['parentdb'])) {
        echo $twig->render("forgot.html.twig");
        echo "<div id='alert' hx-swap-oob='true'>" . $twig->render('error.html.twig', ['message' => 'Only super users can reset password']) . "</div>";

    } else {
        echo $twig->render("forgot.html.twig");
        echo "<div id='alert' hx-swap-oob='true'>" . $twig->render('error.html.twig', ['message' => 'User doesn\'t exists']) . "</div>";

    }
}