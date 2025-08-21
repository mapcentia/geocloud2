<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v1;

use app\inc\Controller;
use app\models\Database;

/**
 * Class Schema
 * @package app\api\v1
 */
class Schema extends Controller
{
    /**
     * Schema constructor.
     */
    function __construct(public $database = new Database())
    {
        parent::__construct();
    }

    /**
     * Retrieves a list of all schemas from the database.
     *
     * @return array The list of all database schemas.
     */
    public function get_index(): array
    {
        return $this->database->listAllSchemas();
    }
}