<?php

use app\auth\types\GrantType;
use app\models\Database;
use app\models\Session as SessionModel;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader);
Database::setDb("mapcentia");

if ($_POST['database'] && $_POST['user'] && $_POST['password']) {
    // Start session and refresh browser
    try {
        $grantType = match ($_POST['response_type']) {
            'code' => GrantType::AUTHORIZATION_CODE,
            'access' => GrantType::PASSWORD,
            default => null,
        };
        $data = (new SessionModel())->start($_POST['user'], $_POST['password'], "public", $_POST['database']);
        header('HX-Refresh: true');
    } catch (Exception) {
        $res = (new \app\models\User())->getDatabasesForUser($_POST['user']);
        echo $twig->render('login.html.twig', [...$res, ...$_POST]);
        echo "<div id='alert' hx-swap-oob='true'>" . $twig->render('error.html.twig', ['message' => 'Wrong password']) . "</div>";
    }
} elseif ($user = $_POST['user']) {
    // Get database for user
    $res = [];
    $res = (new \app\models\User())->getDatabasesForUser($user);
    if (sizeof($res['databases']) > 0) {
        echo "<div id='alert' hx-swap-oob='true'></div>";
    } else {
        echo "<div id='alert' hx-swap-oob='true'>" . $twig->render('error.html.twig', ['message' => 'User doesn\'t exists']) ."</div>";
    }
    echo $twig->render('login.html.twig', [...$res, ...$_POST]);
} else {
    echo $twig->render('login.html.twig');
}
