<?php

use app\inc\Cache;
use app\inc\Jwt;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader);

$code = $_POST['user-code'];
$cachedString = Cache::getItem($code);

if ($cachedString != null && $cachedString->isHit()) {
    $val = $cachedString->get();
    if (!empty($val) && $val == 1) {
        $cachedString->set($_SESSION)->expiresAfter(Jwt::DEVICE_CODE_TTL);
        Cache::save($cachedString);
        echo "<div id='alert' hx-swap-oob='true'>Device code found</div>";
        echo "<div id='alert' hx-swap-oob='true'>" . $twig->render('error.html.twig', ['message' => 'Device code found']) ."</div>";
    } else {
        echo $twig->render("device.html.twig");
        echo "<div id='alert' hx-swap-oob='true'>" . $twig->render('error.html.twig', ['message' => 'Device code already used']) ."</div>";
    }
} else {
    echo $twig->render("device.html.twig");
    if ($code) {
        echo "<div id='alert' hx-swap-oob='true'>" . $twig->render('error.html.twig', ['message' => 'Code doesn\'t exists']) ."</div>";

    }
}
