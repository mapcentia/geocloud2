<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\wfs\processors\geofence\classes\pre;

use app\inc\Input;
use app\inc\Model;
use app\models\Rules;
use app\wfs\processors\PreInterface;
use app\models\Geofence as GeofenceModel;
use app\inc\UserFilter;
use PDOException;
use phpDocumentor\Reflection\Types\This;


/**
 * Class PreGeofence
 * @package app\wfs\processors\geofence\classes\pre
 */
class PreGeofence implements PreInterface
{
    /**
     * @var string
     */
    static public $typeName;

    /**
     * @var bool
     */
    static public $isDelete;

    /**
     * @var Model
     */
    private $db;

    /**
     * @var string
     */
    private $gc2User;

    /**
     * @var string
     */
    private $dbName;

    /**
     * PreGeofence constructor.
     * @param Model $db
     */
    function __construct(Model $db)
    {
        $this->db = $db;
        $urlPart =Input::getPath()->part(2);
        $dbSplit = explode("@", $urlPart);
        if (sizeof($dbSplit) == 2) {
            $this->gc2User = $dbSplit[0];
            $this->dbName = $dbSplit[1];
        } else {
            $this->gc2User = $urlPart;
            $this->dbName = $urlPart;
        }
        self::$isDelete = false;
    }

    /**
     * The main function called by the WFS prior to the single UPDATE transaction.
     * @param array<mixed> $arr
     * @param string $typeName
     * @return array<mixed>
     */
    public function processUpdate(array $arr, string $typeName): array
    {
        self::$typeName = $typeName;
        $res["arr"] = $arr;
        $res["success"] = true;
        $res["message"] = $arr;
        return $res;
    }

    /**
     * The main function called by the WFS prior to the single INSERT transaction.
     * @param array<mixed> $arr
     * @param string $typeName
     * @return array<mixed>
     */
    public function processInsert(array $arr, string $typeName): array
    {
        self::$typeName = $typeName;
        $res["arr"] = $arr;
        $res["success"] = true;
        $res["message"] = $arr;
        return $res;
    }

    /**
     * The main function called by the WFS prior to the single DELETE transaction.
     * @param array<mixed> $arr
     * @param string $typeName
     * @return array<mixed>
     */
    public function processDelete(array $arr, string $typeName): array
    {
        global $postgisschema;
        self::$typeName = $typeName;
        self::$isDelete = true;

        $userFilter = new UserFilter($this->gc2User, "wfs-t", "delete", "*", $postgisschema, $typeName);
//        print_r($userFilter);
        $geofence = new GeofenceModel($userFilter);
        // Get rules and set them
        $rules = new Rules();
        $rule = $geofence->authorize($rules->getRules());
//        die(print_r($rule, true));

        if ($rule["access"] == GeofenceModel::DENY_ACCESS) {
            $response["success"] = false;
            $response["message"] = "Geofence regler forhindrer dig at ændre dette lag";
            return $response;
        } elseif ($rule["access"] == GeofenceModel::LIMIT_ACCESS) {
            $filters = $this->addDiminsionOnArray($arr["Filter"]["FeatureId"]);
            foreach ($filters as $filter) {
                $fid = explode(".", $filter["fid"])[1];
                $sql = "SELECT gid FROM \"{$postgisschema}\".\"{$typeName}\" " .
                    "WHERE {$postgisschema}.{$typeName}.gid='{$fid}'" .
                    " AND {$rule["filters"]["write"]}";
                try {
                    $res = $this->db->prepare($sql);
                    $res->execute();
                    $row = $this->db->fetchRow($res);
                    if (!$row) {
                        $response["messaged"] = "Et eller flere objekter ligger uden for kommunegrænsen (Operation: DELETE)";
                        $response["message"] = print_r($arr, true);
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
        $response["arr"] = $arr;
        $response["success"] = true;
        $response["message"] = $arr;
        return $response;
    }

    /**
     * @param array<mixed>|null $array $array
     * @return array|array[]|null
     */
    private function addDiminsionOnArray(?array $array): ?array
    {
        if (!is_array($array[0]) && isset($array)) {
            $array = array(0 => $array);
        }
        return $array;
    }
}
