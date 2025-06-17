<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use app\auth\api\Device;
use app\auth\api\Forgot;
use app\auth\api\Signin;
use app\auth\api\Signup;
use app\auth\api\Signout;
use app\inc\Route2;
use app\auth\api\Auth;
use app\auth\api\Activation;

Route2::add("auth", new Auth());
Route2::add("device", new Device());
Route2::add("signin", new Signin());
Route2::add("signup", new Signup());
Route2::add("signout", new Signout());
Route2::add("activation", new Activation());
Route2::add("forgot", new Forgot());
