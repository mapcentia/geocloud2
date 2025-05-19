<?php

use app\inc\Session;
use app\models\User as UserModel;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$loader = new FilesystemLoader(__DIR__ . '/templates');
$twig = new Environment($loader);

echo $twig->render('header.html.twig');
echo "<main class='form-signin w-100 m-auto'>";

echo "<main hx-trigger='load' hx-post='/auth/backends/activation.php' class='form-signin w-100 m-auto'></main>";
echo $twig->render('footer.html.twig');
echo "<div id='alert'></div>";
echo "</main>";