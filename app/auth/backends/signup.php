<?php

use app\models\Database;
use app\models\Session as SessionModel;
use app\models\User as UserModel;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader);
Database::setDb("mapcentia");

if ($_POST['name'] && $_POST['email'] && $_POST['password'] && $_POST['code']) {
    try {
        $model = new UserModel();
        $model->connect();
        $model->begin();
        //$model->checkCode($_POST['code'], $_POST['email']);
        $res = $model->createUser([
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'password' => $_POST['password'],
            'subuser' => !empty($_POST['db']),
            'parentdb' => $_POST['db']
        ]);
        $model->commit();
        $data = (new SessionModel())->start($_POST['name'], $_POST['password'], "public", $res['screenname']);
        $header = "HX-Redirect: " . urldecode($_POST['r']);
        header($header);
    } catch (Exception $e) {
        echo "<div id='alert' hx-swap-oob='true'>" . $twig->render('error.html.twig', ['message' => $e->getMessage()]) . "</div>";
    }
}
echo $twig->render('signup.html.twig', [...$_POST, ...$_GET]);
