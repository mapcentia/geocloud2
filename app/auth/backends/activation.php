<?php

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

if ($_POST['email']) {
    $model = new UserModel();
    $model->connect();
    $model->begin();
    try {
        $model->sendCode($_POST['email']);
    } catch (Error|Exception $e) {
        echo "<div id='alert' hx-swap-oob='true'>" . $twig->render('error.html.twig', ['message' => $e->getMessage()]) . "</div>";
        echo $twig->render('activation.html.twig', ['email' => $_POST['email']]);
        exit();
    }
    $model->commit();
    echo "<div id='alert' hx-swap-oob='true'>" . $twig->render('error.html.twig', ['message' => 'E-mail with activation code is send']) . "</div>";
} else {
    echo $twig->render('activation.html.twig');
}



