<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\auth\api;

use app\api\v4\AbstractApi;
use app\inc\Route2;
use app\inc\Session;
use app\models\Client;
use app\models\Database;
use app\models\Session as SessionModel;
use Exception;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;


class Auth extends AbstractApi
{

    public function __construct(private $twig = new Environment(new FilesystemLoader(__DIR__ . '/templates')))
    {
        Session::start();
    }

    public function get_index(): never
    {
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
                    goto error;
                }
                if ($requiredParam == 'response_type' && !($_GET[$requiredParam] == 'token' || $_GET[$requiredParam] == 'code')) {
                    $gotError = true;
                    $error = "unsupported_response_type";
                    $errorDesc = "The application requested an unsupported response type '$_GET[$requiredParam]' when requesting a token";
                    goto error;
                }
            }
            // Check client id
            try {
                $clientData = $client->get($_GET['client_id']);
            } catch (Exception) {
                $gotError = true;
                $error = "invalid_client";
                $errorDesc = "Client with identifier '{$_GET['client_id']}' was not found in the directory";
                goto error;
            }

            $uris = $clientData[0]['redirect_uri'];
            if ($_GET['redirect_uri'] && !in_array($_GET['redirect_uri'], $uris)) {
                $gotError = true;
                $error = "invalid_client";
                $errorDesc = "Client with identifier '{$_GET['client_id']}' is not registered with redirect uri: {$_GET['redirect_uri']} ";
                goto error;
            }
            $redirectUri = $_GET['redirect_uri'] ?? $uris[0];

            $separator = str_contains($redirectUri, '?') ? '&' : '?';

            // Check client secret if is set
            if (!$clientData[0]['public']) {
                try {
                    $client->verifySecret($_GET['client_id'], $_GET['client_secret']);
                } catch (Exception) {
                    $gotError = true;
                    $error = "invalid_client";
                    $errorDesc = "Client secret doesn't match what was expected";
                    goto error;
                }
            }
            // If error we send user back with error parameters
            error:
            if ($gotError) {
                echo "[$error] $errorDesc";
//                $paramsStr = http_build_query(['error' => $error, 'error_description' => $errorDesc]);
//                $header = "Location: $redirectUri$separator$paramsStr";
//                header($header);
                exit();
            }
            $code = $_GET['response_type'] == 'code';
            $codeChallenge = $_GET['code_challenge'];
            $codeChallengeMethod = $_GET['code_challenge_method'];
            $data = (new SessionModel())->createOAuthResponse($_SESSION['parentdb'], $_SESSION['screen_name'], !$_SESSION['subuser'], $code, $_SESSION['usergroup'], $codeChallenge, $codeChallengeMethod, $_SESSION['properties'], $_SESSION['email']);
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

            if ($clientData[0]['confirm']) {
                echo $this->twig->render('allow.html.twig', ['name' => $client, 'location' => $location]);
            } else {
                $header = "Location: $redirectUri$separator$paramsStr";
                header($header);
            }
            exit();
        }

        echo $this->twig->render('header.html.twig');
        echo "<main class='form-signin w-100 m-auto'>";
        echo "<div hx-trigger='load' hx-target='this' hx-target='this' hx-post='/signin'></div>";
        echo "<div id='alert'></div>";
        echo "</main>";

        echo $this->twig->render('footer.html.twig');
        exit();
    }

    public function post_index(): never
    {
        // TODO: Implement post_index() method.
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