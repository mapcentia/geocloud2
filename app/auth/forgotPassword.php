<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\inc\Cache;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$loader = new FilesystemLoader(__DIR__ . '/templates');
$twig = new Environment($loader);

echo $twig->render('header.html.twig');
echo "<main class='form-signin w-100 m-auto'>";


if (isset($_GET["key"]) && isset($_GET["user"])) {
    $CachedString = Cache::getItem('__forgot_' . $_GET['user']);
    if ($CachedString != null && $CachedString->isHit()) {
        $val = $CachedString->get();
        if ($val !== $_GET['key']) {
            echo "<div id='alert' hx-swap-oob='true'>" . $twig->render('error.html.twig', ['message' => 'Wrong key']) . "</div>";

            exit();
        }
    } else {
        echo "<div id='alert' hx-swap-oob='true'>" . $twig->render('error.html.twig', ['message' => 'Could not find the key. Maybe it has expired']) . "</div>";

        exit();
    }
    echo "<form hx-post=\"/auth/backends/forgot.php\">";
    echo $twig->render("reset.html.twig", $_REQUEST);

} else {
    echo "<form hx-post=\"/auth/backends/forgot.php\">";
    echo $twig->render("forgot.html.twig");

}
echo "</form>";
echo "<div id='alert'></div>";
echo "</main>";

echo $twig->render('footer.html.twig');

