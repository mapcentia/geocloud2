<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\inc\Session;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

$loader = new FilesystemLoader(__DIR__ . '/templates');
$twig = new Environment($loader);

echo $twig->render('header.html.twig');
$backend = Session::isAuth() ? 'device' : 'login';

echo "<main class='form-signin w-100 m-auto'>";
echo "<div hx-trigger='load' hx-target='this' hx-target='this' hx-post='/auth/backends/$backend.php'></div>";
echo "<div id='alert'></div>";
echo "</main>";

echo $twig->render('footer.html.twig');


