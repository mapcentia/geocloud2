<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\controllers;

use app\inc\Controller;
use app\inc\Input;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;


/**
 * Class Workflow
 * @package app\controllers
 */
class Workflow extends Controller
{
    /**
     * @var \app\models\Workflow
     */
    public $workflow;

    function __construct()
    {
        parent::__construct();
        $this->workflow = new \app\models\Workflow();
    }

    /**
     * @return array<mixed>
     */
    public function get_index(): array
    {
        return $this->workflow->getRecords($_SESSION["screen_name"], false);
    }

    /**
     * @return array<mixed>
     */
    public function post_index(): array
    {
        return $this->workflow->getRecords($_SESSION["screen_name"], true);
    }

    /**
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function put_index(): array
    {
        return $this->workflow->touch(Input::getPath()->part(3), Input::getPath()->part(4), Input::getPath()->part(5), $_SESSION["screen_name"]);
    }
}