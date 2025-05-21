<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 *
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use app\auth\api\Device;
use app\auth\api\Signup;
use app\auth\api\Signout;
use app\inc\Route2;
use app\auth\api\Index;
use app\auth\api\Activation;

Route2::add("auth", new Index());
Route2::add("device", new Device());
Route2::add("signup", new Signup());
Route2::add("signout", new Signout());
Route2::add("auth/backends/[backend]", new Index());
Route2::add("activation", new Activation());
