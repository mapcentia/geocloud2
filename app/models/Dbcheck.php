<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\inc\Connection;
use app\inc\Model;

class Dbcheck extends Model
{
    function __construct(?Connection $connection = null)
    {
        parent::__construct(connection: $connection);
    }

    public function isSchemaInstalled()
    {
        $sql = "select 1 from settings.viewer";
        $this->execQuery($sql);
        return true;
    }

    public function isPostGISInstalled()
    {
        $sql = "select postgis_version()";
        $this->execQuery($sql);
        return true;
    }

    public function isViewInstalled()
    {
        $sql = "select * from settings.geometry_columns_view";
        $this->execQuery($sql);
        return true;
    }
}