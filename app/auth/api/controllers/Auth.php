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
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\inc\Connection;
use app\inc\Route2;
use app\inc\Session;
use app\models\Client;
use app\models\Session as SessionModel;
use Exception;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

#[Controller(route: 'auth', scope: Scope::PUBLIC)]
class Auth extends AbstractApi
{

    public function __construct(private readonly Route2 $route, Connection $connection, private $twig = new Environment(new FilesystemLoader(__DIR__ . '/../templates')))
    {
        parent::__construct($connection);
        Session::start();
    }

    public function get_index(): Response
    {
        $requiredParams = ['response_type', 'client_id'];
        foreach ($requiredParams as $requiredParam) {
            if (!array_key_exists($requiredParam, $_GET)) {
                $error = "invalid_request";
                $errorDesc = "The request must contain the following parameter '$requiredParam'";
                return $this->error($error, $errorDesc);
            }
            if ($requiredParam == 'response_type' && !($_GET[$requiredParam] == 'token' || $_GET[$requiredParam] == 'code')) {
                $error = "unsupported_response_type";
                $errorDesc = "The application requested an unsupported response type '$_GET[$requiredParam]' when requesting a token";
                return $this->error($error, $errorDesc);
            }
        }

        if (Session::isAuth()) {
            $client = new Client(connection: new Connection(database: $_SESSION['parentdb']));
            // Check client id
            try {
                $clientData = $client->get($_GET['client_id']);
            } catch (Exception $e) {
                $error = "invalid_client";
                $errorDesc = "Client with identifier '{$_GET['client_id']}' was not found in the directory";
                return $this->error($error, $errorDesc);
            }

            $uris = $clientData[0]['redirect_uri'];
            if ($_GET['redirect_uri'] && !in_array($_GET['redirect_uri'], $uris)) {
                $error = "invalid_client";
                $errorDesc = "Client with identifier '{$_GET['client_id']}' is not registered with redirect uri: {$_GET['redirect_uri']} ";
                return $this->error($error, $errorDesc);
            }
            $redirectUri = $_GET['redirect_uri'] ?? $uris[0];
            $separator = str_contains($redirectUri, '?') ? '&' : '?';

            // Check client secret
            if (!$clientData[0]['public']) {
                try {
                    $client->verifySecret($_GET['client_id'], $_GET['client_secret']);
                } catch (Exception) {
                    $error = "invalid_client";
                    $errorDesc = "Client secret doesn't match what was expected";
                    return $this->error($error, $errorDesc);
                }
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
            $location = "$redirectUri$separator$paramsStr";

            if ($clientData[0]['confirm']) {
                echo $this->twig->render('allow.html.twig', ['name' => $client, 'location' => $location]);
                return $this->emptyResponse();
            } else {
                return $this->redirectResponse("$redirectUri$separator$paramsStr");

            }
        }
        $vals = [
            'parentdb' => $_GET['parentdb'] ?? '',
            'client_id' => $_GET['client_id'],
            'response_type' => $_GET['response_type'] ?? null,
            'redirect_uri' => $_GET['redirect_uri'] ?? null,
            'state' => $_GET['state'] ?? null,
            'code_challenge' => $_GET['code_challenge'] ?? null,
            'code_challenge_method' => $_GET['code_challenge_method'] ?? null,
        ];
        $hxVals = htmlspecialchars(json_encode($vals, JSON_UNESCAPED_SLASHES), ENT_QUOTES);

        echo $this->twig->render('header.html.twig');
        echo "<main class='form-signin w-100 m-auto flex-grow-1'>";
        echo "<div hx-trigger='load' hx-target='this' hx-target='this' hx-post='/signin' hx-vals='{$hxVals}'></div>";
        echo "<div id='alert'></div>";
        echo "<div id='forgot'></div>";
        echo "</main>";
        echo $this->twig->render('footer.html.twig', ['app_name' => 'Centia.io', 'help_url' => 'https://centia.io/docs/intro']);
        return $this->emptyResponse();
    }

    private function error(string $error, string $errorDesc): Response
    {
            echo "[$error] $errorDesc";
//                $paramsStr = http_build_query(['error' => $error, 'error_description' => $errorDesc]);
//                $header = "Location: $redirectUri$separator$paramsStr";
//                header($header);
        return $this->emptyResponse();


    }

    public function post_index(): Response
    {
        return [];
    }

    public function put_index(): Response
    {
        return [];
    }

    public function delete_index(): Response
    {
        return [];
    }

    public function validate(): void
    {
        // no-op
    }

    public function patch_index(): Response
    {
        return [];
    }
}