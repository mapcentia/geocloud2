<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\auth\GrantType;
use app\inc\Util;
use app\models\Client;
use app\models\Database;
use app\models\Session as SessionModel;
use app\inc\Session;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$loader = new FilesystemLoader(__DIR__ . '/templates');
$twig = new Environment($loader);
Database::setDb("mapcentia");

if (Session::isAuth()) {
    Database::setDb($_SESSION['parentdb']);
    $client = new Client();
    $requiredParams = ['response_type', 'client_id'];
    $gotError = false;
    $error = "";
    $errorDesc = "";
    foreach ($requiredParams as $requiredParam) {
        if (!array_key_exists($requiredParam, $_GET)) {
            $gotError = true;
            $error = "invalid_request";
            $errorDesc = "The request must contain the following parameter '$requiredParam'";
            break;
        }
        if ($requiredParam == 'response_type' && !($_GET[$requiredParam] == 'token' || $_GET[$requiredParam] == 'code')) {
            $gotError = true;
            $error = "unsupported_response_type";
            $errorDesc = "The application requested an unsupported response type '$_GET[$requiredParam]' when requesting a token";
            break;
        }
    }
    // Check client id
    try {
        $redirectUri = $client->get($_GET['client_id'])[0]['redirect_uri'];
    } catch (Exception) {
        echo "Client with identifier '{$_GET['client_id']}' was not found in the directory";
        exit();
    }
    // Check client secret if is set
    if (isset($_GET['client_secret'])) {
        try {
            $client->verifySecret($_GET['client_id'], $_GET['client_secret']);
        } catch (Exception $e) {
            $gotError = true;
            $error = "invalid_client";
            $errorDesc = "Client secret doesn't match what was expected";
        }
    }
    if ($gotError) {
        $paramsStr = http_build_query(['error' => $error, 'error_description' => $errorDesc]);
        $header = "Location: $redirectUri?$paramsStr";
        header($header);
        exit();
    }
    $code = $_GET['response_type'] == 'code';
    $data = (new SessionModel())->createOAuthResponse($_SESSION['parentdb'], $_SESSION['screen_name'], $_SESSION['subuser'], $_SESSION['usergroup'], $code);
    $params = [];
    if ($code) {
        $params['code'] = $data['code'];
    } else {
        $params['access_token'] = $data['access_token'];
        $params['token_type'] = $data['token_type'];
        $params['expires_in'] = $data['expires_in'];
    }
    if ($_GET['state']) {
        $params['state'] = $_GET['state'];
    }
    $paramsStr = http_build_query($params);
    $header = "Location: $redirectUri?$paramsStr";
    echo $header;
    //header($header);

    exit();
}

if ($_POST['database'] && $_POST['user'] && $_POST['password']) {
    // Start session and refresh browser
    try {
        $grantType = match ($_POST['response_type']) {
            'code' => GrantType::AUTHORIZATION_CODE,
            'access' => GrantType::PASSWORD,
            default => null,
        };
        $data = (new SessionModel())->start($_POST['user'], $_POST['password'], "public", $_POST['database']);
        header('HX-Refresh:true');

    } catch (Exception) {
        $res = (new \app\models\User())->getDatabasesForUser($_POST['user']);
        echo $twig->render('login.html.twig', [...$res, ...$_POST]);
        echo "<div id='alert' hx-swap-oob='true'>Wrong password</div>";
    }
} elseif ($user = $_POST['user']) {
    // Get database for user
    $res = [];
    $res = (new \app\models\User())->getDatabasesForUser($user);
    if (sizeof($res['databases']) > 0) {
        echo "<div id='alert' hx-swap-oob='true'></div>";
    } else {
        echo "<div id='alert' hx-swap-oob='true'>User doesn't exists</div>";
    }
    echo $twig->render('login.html.twig', [...$res, ...$_POST]);
} else {
    // Start
    ?>
    <script src="https://unpkg.com/htmx.org@1.9.11"></script>
    <form hx-post="/auth/">
        <?php
        echo $twig->render('login.html.twig', $_GET);
        ?>
    </form>
    <div id="alert"></div>
    <?php
}
