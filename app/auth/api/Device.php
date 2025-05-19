<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 *
 */

namespace app\auth\api;

use app\api\v4\AbstractApi;
use app\inc\Route2;
use app\inc\Session;


class Device extends AbstractApi
{

    public function __construct()
    {
        Session::start();
    }

    public function get_index(): never
    {
        include_once(__DIR__ . '/../deviceCode.php');
        exit();
    }

    public function post_index(): never
    {
        $backend = Route2::getParam('backend');
        include_once(__DIR__ . "/../backends/" . $backend);
        exit();
    }

    public function put_index(): array
    {
        // TODO: Implement put_index() method.
    }

    public function delete_index(): array
    {
        // TODO: Implement delete_index() method.
    }

    public function validate(): void
    {
        // TODO: Implement validate() method.
    }

    public function patch_index(): array
    {
        // TODO: Implement patch_index() method.
    }
}