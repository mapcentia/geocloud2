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
use app\conf\App;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Route2;
use app\models\User as UserModel;
use Error;
use Exception;
use Postmark\PostmarkClient;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;


#[Controller(route: 'activation', scope: Scope::PUBLIC)]
class Activation extends AbstractApi
{

    public function __construct(private readonly Route2 $route, Connection $connection, private $twig = new Environment(new FilesystemLoader(__DIR__ . '/../templates')))
    {
//        Session::start();
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function get_index(): Response
    {
        echo $this->twig->render('header.html.twig');
        echo "<main class='form-signin w-100 m-auto'>";
        echo "<div hx-trigger='load' hx-post='/activation'></div>";
        echo $this->twig->render('footer.html.twig');
        echo "<div id='alert'></div>";
        echo "</main>";
        return $this->emptyResponse();
    }

    public function post_index(): Response
    {
        if ($_POST['email']) {
            $model = new UserModel();
            $model->connect();
            $model->begin();
            try {
                $sql = "SELECT * FROM codes WHERE email=:email";
                $res = $model->prepare($sql);
                $model->execute($res, [":email" => $_POST['email']]);
                if ($res->rowCount() > 0) {
                    throw new GC2Exception("E-mail already used", 404, null, "CODE_DOES_NOT_EXISTS");
                }
                $sql = "SELECT code FROM codes WHERE email isnull and used isnull limit 1";
                $res = $model->prepare($sql);
                $model->execute($res);
                if ($res->rowCount() == 0) {
                    throw new GC2Exception("No more available activation codes. We'll release more, so try again later", 404, null, "CODE_DOES_NOT_EXISTS");
                }
                $code = $res->fetchColumn();
                $client = new PostmarkClient(App::$param["notification"]["key"]);
                // Build an attractive HTML email. We avoid adding Twig here to keep models framework-agnostic.
                $html = $this->twig->render('email_activation.html.twig', [
                    'app_name' => App::$param['ap'] ?? 'GC2',
                    'code' => $code,
                    'recipient_email' => $_POST['email'],
                    'expires_in' => '10 minutes',
                    'context_info' => !empty($_POST['parentdb']) ? ('database ' . $_POST['parentdb']) : null,
                    'supportUrl' => App::$param['supportUrl'] ?? 'mailto:support@example.com',
                ]);

                $message = [
                    'To' => $_POST['email'],
                    'From' => App::$param["notification"]["from"],
                    'TrackOpens' => false,
                    'Subject' => "Activation code",
                    'HtmlBody' => $html,
                    'TextBody' => "Your activation code: $code",
                ];
                try {
                    $client->sendEmailBatch([$message]);
                } catch (Exception $generalException) {
                    throw new GC2Exception("Could not send email. Try again or report the problem", 500, $generalException);
                }
                $sql = "UPDATE codes set email=:email where code=:code";
                $res = $model->prepare($sql);
                $model->execute($res, [":code" => $code, ":email" => $_POST['email']]);
            } catch (Error|Exception $e) {
                echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => $e->getMessage()]) . "</div>";
                echo $this->twig->render('activation.html.twig', ['email' => $_POST['email']]);
                return $this->emptyResponse();
            }
            $model->commit();
            echo "<div id='alert' hx-swap-oob='true'>" . $this->twig->render('error.html.twig', ['message' => 'E-mail with activation code is send.']) . "</div>";
        } else {
            echo $this->twig->render('activation.html.twig');
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