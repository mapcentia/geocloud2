<?php

use app\exceptions\GC2Exception;
use app\inc\Cache;
use app\inc\Jwt;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$loader = new FilesystemLoader(__DIR__ . '/../templates');
$twig = new Environment($loader);

$code = $_POST['user-code'];
$CachedString = Cache::getItem($code);

if ($CachedString != null && $CachedString->isHit()) {
    $val = $CachedString->get();
    if (!empty($val) && $val == 1) {
        $CachedString->set($_SESSION)->expiresAfter(Jwt::DEVICE_CODE_TTL);
        Cache::save($CachedString);
        echo "<div id='alert' hx-swap-oob='true'>Device code founded</div>";
    } else {
        echo $twig->render("device.html.twig");
        echo "<div id='alert' hx-swap-oob='true'>Device code already used</div>";
    }
} else {
    echo $twig->render("device.html.twig");
    echo "<div id='alert' hx-swap-oob='true'>Code doesn't exists</div>";
}
