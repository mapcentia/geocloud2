<?php

use app\inc\Session;
use app\models\User as UserModel;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$loader = new FilesystemLoader(__DIR__ . '/templates');
$twig = new Environment($loader);

echo $twig->render('header.html.twig');

echo "<main class='form-signin w-100 m-auto'>";
echo "<div hx-trigger='load' hx-target='this'  hx-post='/auth/backends/signup.php?db=" . $_GET['db'] ."&r=" . $_GET['redirect_url'] . "'></div>";
echo "<div id='alert'></div>";
echo "</main>";

echo $twig->render('footer.html.twig');