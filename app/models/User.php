<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\inc\Model;
use app\models\Setting;


class User extends Model
{
    public $userId;

    function __construct($userId = null)
    {
        parent::__construct();
        $this->userId = $userId;
        $this->postgisdb = "mapcentia";
    }

    public function getAll(): array
    {
        $query = "SELECT * FROM users WHERE email<>''";
        $res = $this->execQuery($query);
        $rows = $this->fetchAll($res);
        if (!$this->PDOerror) {
            $response['success'] = true;
            $response['data'] = $rows;
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror;
        }
        return $response;
    }

    public function getData(): array
    {
        $domain = \app\conf\App::$param['domain'];
        $query = "SELECT screenname as userid, zone, '{$domain}' as host FROM users WHERE screenname = :sUserID";
        $res = $this->prepare($query);
        $res->execute(array(":sUserID" => $this->userId));
        $row = $this->fetchRow($res);
        if (!$row['userid']) {
            $response['success'] = false;
            $response['message'] = "Userid not found";
            //$response['code'] = 406;
            return $response;
        }
        if (!$this->PDOerror) {
            $response['success'] = true;
            $response['data'] = $row;
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror;
        }
        return $response;
    }

    public function createUser(array $data): array
    {
        $user = isset($data["user"]) ? Model::toAscii($data["user"], NULL, "_") : null;

        $password = isset($data["password"]) ? Setting::encryptPw($data["password"]) : null;
        $userGroup = $data["usergroup"];

        $sQuery = "INSERT INTO users (screenname,pw,parentdb,usergroup) VALUES(:sUserID, :sPassword, :sParentDb, :sUsergroup) RETURNING screenname,parentdb,usergroup";

        try {
            $res = $this->prepare($sQuery);
            $res->execute(array(":sUserID" => $user, ":sPassword" => $password, ":sParentDb" => $this->userId, ":sUsergroup" => $userGroup));
            $row = $this->fetchRow($res, "assoc");
        } catch (\Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['test'] = \app\inc\Session::getUser();
            $response['code'] = 400;
            return $response;
        }

        $response['success'] = true;
        $response['message'] = "User created";
        $response['data'] = $row;
        return $response;
    }
}