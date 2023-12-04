<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */


namespace app\api\v4;

use app\models\Table as TableModel;
use Exception;

trait ApiTrait
{
    private TableModel $table;
    private string $qualifiedName;
    private string $unQualifiedName;
    private string $schema;


    /**
     * @param string $layerName
     * @param $userName
     * @param bool $superUser
     * @return void
     * @throws Exception
     */
    public function check(string $layerName, $userName, bool $superUser): void
    {
        // Check if layer has schema prefix and add 'public' if no.
        $exploded = TableModel::explodeTableName($layerName);
        if (empty($exploded["schema"])) {
            $this->schema = "public";
        } else {
            $this->schema = $exploded["schema"];
        }
        $this->unQualifiedName = $exploded["table"];
        $this->qualifiedName = $this->schema . "." . $exploded["table"];
        if ($superUser) {
            $isAuthorized = true;
        } else {
            if ($userName == $this->schema || $this->schema == "public") {
                $isAuthorized = true;
            } else {
                $isAuthorized = false;
            }
        }
        if (!$isAuthorized) {
            throw new Exception("Not authorized");
        }
    }
}
