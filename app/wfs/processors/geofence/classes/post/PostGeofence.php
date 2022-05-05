<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\wfs\processors\geofence\classes\post;

use app\inc\Input;
use app\inc\Model;
use app\inc\UserFilter;
use app\models\Geofence as GeofenceModel;
use app\wfs\processors\PostInterface;
use app\wfs\processors\geofence\classes\pre\PreGeofence;
use PDOException;

/**
 * Class PostGeofence
 * @package app\wfs\processors\fkg_check\classes\post
 */
class PostGeofence implements PostInterface
{
    /**
     * @var Model
     */
    private $db;

    /**
     * @var string
     */
    private $dbName;

    /**
     * @var string|null
     */
    private $gc2User;

    /**
     * PostGeofence constructor.
     * @param Model $db
     */
    function __construct(Model $db)
    {
        $this->db = $db;
        $urlPart = Input::getPath()->part(2);
        $dbSplit = explode("@", $urlPart);
        if (sizeof($dbSplit) == 2) {
            $this->gc2User = $dbSplit[0];
            $this->dbName = $dbSplit[1];
        } else {
            $this->gc2User = $urlPart;
            $this->dbName = $urlPart;
        }
    }

    /**
     * @return array<mixed>
     */
    public function process(): array
    {
        global $rowIdsChanged;
        $userFilter = new UserFilter($this->dbName, $this->gc2User, "*", "*", "*", "*", PreGeofence::$typeName);
        $geofence = new GeofenceModel($userFilter);
        $rule = $geofence->authorize();

        if ($rule["access"] == GeofenceModel::DENY_ACCESS) {
            $response["success"] = false;
            $response["message"] = "Geofence regler forhindrer dig at ændre dette lag";
            return $response;
        } elseif ($rule["access"] == GeofenceModel::LIMIT_ACCESS) {
            if (!PreGeofence::$isDelete) {
                $response = [];
                $typeName = PreGeofence::$typeName;
                foreach ($rowIdsChanged as $objekt_id) {
                    $filter = "";
                    $spatialFilter = "";
                    $sql = "SELECT objekt_id FROM fkg.{$typeName} WHERE fkg.{$typeName}.objekt_id='{$objekt_id}'";
                    if (!empty($rule["filters"]["write"])) {
                        $filter = " AND {$rule["filters"]["write"]}";
                    }
                    if (!empty($rule["filters"]["write_spatial"])) {
                        $spatialFilter = " AND st_intersects(fkg.{$typeName}.geometri, ST_transform(({$rule["filters"]["write_spatial"]}), 25832))";
                    }

                    $sql = $sql . $filter;
                    $sql = $sql . $spatialFilter;

                    try {
                        $res = $this->db->prepare($sql);
                        $res->execute();
                        $row = $this->db->fetchRow($res);
                        if (!$row) {
                            $response["message"] = "Et eller flere objekter ligger uden for kommunegrænsen (operation: UPDATE/INSERT)";
                            $response["success"] = false;
                            return $response;
                        }
                    } catch (PDOException $e) {
                        $response["message"] = $e->getMessage();
                        $response["success"] = false;
                        return $response;
                    }
                }
            }
        }
        $response["success"] = true;
        return $response;
    }
}
