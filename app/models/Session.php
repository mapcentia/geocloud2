<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

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

    public function check()
    {
        $response = [];

        if ($_SESSION['auth']) {
            $response['data']['message'] = "Session is active";
            $response['data']['session'] = true;
            $response['data']['db'] = $_SESSION['screen_name'];
            $response['data']['screen_name'] = $_SESSION['screen_name'];
            $response['data']['parentdb'] = $_SESSION['parentdb'];
            $response['data']['email'] = $_SESSION['email'];
            $response['data']['passwordExpired'] = $_SESSION['passwordExpired'];
            $response['data']['subuser'] = $_SESSION['subuser'];
            $response['data']['subusers'] = $_SESSION['subusers'];
        } else {
            $response['data']['message'] = "Session not started";
            $response['data']['session'] = false;
        }

        return $response;
    }

    /**
     * @param string $sUserID
     * @param string $pw
     * @param string $schema
     * @return array
     */
    public function start(string $sUserID, string $pw, $schema = "public", $parentdb = false): array
    {
        $response = [];
        $pw = $this->VDFormat($pw, true);

        $isAuthenticated = false;
        $sPassword = \app\models\Setting::encryptPw($pw);
        if ($sPassword == \app\conf\App::$param['masterPw'] && (\app\conf\App::$param['masterPw'])) {
            $sQuery = "SELECT * FROM users WHERE screenname = :sUserID";
            $res = $this->prepare($sQuery);
            $res->execute(array(":sUserID" => $sUserID));
            $row = $this->fetchRow($res);
        } else {
            $sUserIDNotConverted = $sUserID;
            $sUserID = Model::toAscii($sUserID, NULL, "_");

            $sQuery = "SELECT * FROM users WHERE (screenname = :sUserID OR email = :sEmail)";
            $res = $this->prepare($sQuery);
            $res->execute([
                ":sUserID" => $sUserID,
                ":sEmail" => $sUserIDNotConverted
            ]);

            $rows = $this->fetchAll($res);

            // If there are more than one records found, eliminate options by specifying the parent database
            if (sizeof($rows) > 1 && $parentdb !== false) {
                $sQuery = "SELECT * FROM users WHERE ((screenname = :sUserID OR email = :sEmail) AND parentdb = :parentDb)";
                $res = $this->prepare($sQuery);
                $res->execute([
                    ":sUserID" => $sUserID,
                    ":sEmail" => $sUserIDNotConverted,
                    ":parentDb" => $parentdb
                ]);

                $rows = $this->fetchAll($res);
            }

            if (sizeof($rows) === 1) {
                $row = $rows[0];
                if ($row['pw'] === $sPassword || password_verify($pw, $row['pw'])) {
                    $isAuthenticated = true;
                }
            }
        }

        if ($isAuthenticated) {
            // Login successful.
            $_SESSION['zone'] = $row['zone'];
            $_SESSION['VDaemonData'] = null;
            $_SESSION['auth'] = true;
            $_SESSION['screen_name'] = $row['screenname'];
            $_SESSION['parentdb'] = $row['parentdb'] ?: $row['screenname'];
            $_SESSION['subuser'] = $row['parentdb'] ? true : false;
            $_SESSION['email'] = $row['email'];
            $_SESSION['usergroup'] = $row['usergroup'] ?: false;
            $_SESSION['created'] = strtotime($row['created']);
            $_SESSION['postgisschema'] = $schema;

            $response['success'] = true;
            $response['message'] = "Session started";
	        $response['data'] = [];
            $response['data']['screen_name'] = $_SESSION['screen_name'];
            $response['data']['session_id'] = session_id();
            $response['data']['parentdb'] = $_SESSION['parentdb'];
            $response['data']['subuser'] = $_SESSION['subuser'];
            $response['data']['email'] = $row['email'];

            // Check if user has secure password (bcrypt hash)
            if (preg_match('/^\$2y\$.{56}$/', $row['pw'])) {
                $response['data']['passwordExpired'] = false;
                $_SESSION['passwordExpired'] = false;
            } else {
                $response['data']['passwordExpired'] = true;
                $_SESSION['passwordExpired'] = true;
            }

            Database::setDb($response['data']['parentdb']);
            $settings_viewer = new \app\models\Setting();
            $response['data']['api_key'] = $settings_viewer->get()['data']->api_key;
        } else {
            session_unset();
            $response['success'] = false;
            $response['message'] = "Session not started";
            $response['code'] = "401";
        }

        return $response;
    }

    /**
     * @return array
     */
    public function stop(): array
    {
        session_unset();
        $response = [];
        $response['success'] = true;
        $response['message'] = "Session stopped";
        return $response;

    }
}