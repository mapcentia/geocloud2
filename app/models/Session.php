<?php
namespace app\models;

use app\inc\Model;

class Session extends Model
{
    function __construct()
    {
        parent::__construct();
    }

    private function VDFormat($sValue, $bQuotes = false)
    {
        $sValue = trim($sValue);
        if ($bQuotes xor get_magic_quotes_gpc()) {
            $sValue = $bQuotes ? addslashes($sValue) : stripslashes($sValue);
        }
        return $sValue;
    }

    public function start($sUserID, $pw)
    {
        $pw = $this->VDFormat($pw, true);
        $sPassword = \app\models\Setting::encryptPw($pw);
        if ($sPassword == \app\conf\App::$param['masterPw'] && (\app\conf\App::$param['masterPw'])) {
            $sQuery = "SELECT * FROM users WHERE screenname = :sUserID";
            $res = $this->prepare($sQuery);
            $res->execute(array(":sUserID" => $sUserID));
            $row = $this->fetchRow($res);
        } else {
            $sQuery = "SELECT * FROM users WHERE (screenname = :sUserID OR email = :sUserID) AND pw = :sPassword";
            $res = $this->prepare($sQuery);
            $res->execute(array(":sUserID" => $sUserID, ":sPassword" => $sPassword));
            $row = $this->fetchRow($res);
        }

        if ($row['screenname']) {
            // Login successful.
            $_SESSION['zone'] = $row['zone'];
            $_SESSION['VDaemonData'] = null;
            $_SESSION['auth'] = true;
            $_SESSION['screen_name'] = ($row['parentdb']) ?: $sUserID;
            $_SESSION['subuser'] = ($row['parentdb']) ? $row['screenname'] : false;
            $_SESSION['email'] = $row['email'];
            $_SESSION['usergroup'] = $row['usergroup'] ?: false;
            $_SESSION['created'] = strtotime($row['created']);

            $response['success'] = true;
            $response['message'] = "Session started";
            $response['screen_name'] = $_SESSION['screen_name'];
            $response['session_id'] = session_id() ;
            $response['subuser'] = $_SESSION['subuser'];

        } else {
            session_unset();
            $response['success'] = false;
            $response['message'] = "Session not started";
            $response['code'] = "401";
        }
        return $response;
    }

    public function stop()
    {
        session_unset();
        $response['success'] = true;
        $response['message'] = "Session stopped";
        return $response;

    }
}