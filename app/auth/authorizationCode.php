<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\models\Client;
use app\models\Database;
use app\models\Session as SessionModel;
use app\inc\Session;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$loader = new FilesystemLoader(__DIR__ . '/templates');
$twig = new Environment($loader);

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
        $clientData = $client->get($_GET['client_id']);
    } catch (Exception) {
        echo "Client with identifier '{$_GET['client_id']}' was not found in the directory";
        exit();
    }

    $uris = $clientData[0]['redirect_uri'];
    if ($_GET['redirect_uri'] && !in_array($_GET['redirect_uri'], $uris)) {
        echo "Client with identifier '{$_GET['client_id']}' is not registered with redirect uri: {$_GET['redirect_uri']} ";
        exit();
    }
    $redirectUri = $_GET['redirect_uri'] ?? $uris[0];

    $separator = str_contains($redirectUri, '?') ? '&' : '?';

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
    // If error we send user back with error parameters
    if ($gotError) {
        $paramsStr = http_build_query(['error' => $error, 'error_description' => $errorDesc]);
        $header = "Location: $redirectUri$separator$paramsStr";
        //echo $header;
        header($header);
        exit();
    }
    $code = $_GET['response_type'] == 'code';
    $codeChallenge = $_GET['code_challenge'];
    $codeChallengeMethod = $_GET['code_challenge_method'];
    $data = (new SessionModel())->createOAuthResponse($_SESSION['parentdb'], $_SESSION['screen_name'], !$_SESSION['subuser'], $code, $_SESSION['usergroup'], $codeChallenge, $codeChallengeMethod);
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

    $client = $clientData[0]['name'];
    $location =  "$redirectUri$separator$paramsStr";

    if ($client != 'gc2-cli') {
        echo $twig->render('allow.html.twig', ['name' => $client, 'location' => $location]);
    } else {
        $header = "Location: $redirectUri$separator$paramsStr";
        header($header);
    }
    exit();
}

echo $twig->render('header.html.twig');
echo "<main class='form-signin w-100 m-auto'>";
echo "<div hx-trigger='load' hx-target='this' hx-target='this' hx-post='/auth/backends/login.php'></div>";
echo "<div id='alert'></div>";
echo "</main>";

echo $twig->render('footer.html.twig');