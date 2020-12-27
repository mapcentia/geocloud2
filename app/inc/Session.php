<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2020 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use \app\conf\App;

class Session
{
    /**
     *
     */
    static function start(): void
    {
        ini_set("session.cookie_lifetime", "86400");
        ini_set("session.gc_maxlifetime", "86400");
        ini_set("session.gc_probability", "1");
        ini_set("session.gc_divisor", "1");
        if (isset(App::$param['domain'])) {
            session_name("PHPSESSID");
            session_set_cookie_params(0, '/', "." . App::$param['domain']);
        }
        session_start();
    }

    /**
     * @param string $redirect
     * @return bool|void
     */
    static function authenticate($redirect = " / ")
    {
        if (isset($_SESSION['auth']) && $_SESSION['auth'] == true) {
            return true;
        } elseif ($redirect) {
            Redirect::to($redirect);
            exit();
        } else {
            //\app\inc\Redirect::to("/nosession.php");
            exit();
        }
    }

    /**
     * @return bool
     */
    static function isAuth(): bool
    {
        return isset($_SESSION['auth']) ? $_SESSION['auth'] : false;
    }

    /**
     * @return string|null
     */
    static function getUser(): ?string
    {
        return $_SESSION['screen_name'];
    }

    /**
     * @return string|null
     */
    static function getDatabase(): ?string
    {
        return $_SESSION['parentdb'];
    }

    /**
     * @return bool
     */
    static function isSubUser(): bool
    {
        return !empty($_SESSION["subuser"]);
    }

    /**
     * @return string
     */
    static function getFullUseName(): string
    {
        return $_SESSION["subuser"] ? $_SESSION["screen_name"] . "@" . $_SESSION['parentdb'] : $_SESSION['screen_name'];
    }

    /**
     * @return string
     */
    static function getLog(): string
    {
        if (!$_SESSION["log"]) {
            $_SESSION["log"] = "<i > Session log started @ " . date('l jS \of F Y h:i:s A') . " </i ><br />";
        }

        return $_SESSION["log"];
    }

    /**
     * @param array<string> $lines
     * @param string $file
     * @return string
     */
    static function createLog(array $lines, string $file): string
    {
        $num = 15;
        $plainTxt = "";
        $_SESSION["log"] .= "<br /<br />";
        $_SESSION["log"] .= "<i > Failed upload of {$file} @ " . date('l jS \of F Y h:i:s A') . " </i ><br />";
        //$plainTxt .= "Failed upload of {$file} @ " . date('l jS \of F Y h:i:s A') . " \n";
        $sizeOfLines = sizeof($lines);
        for ($i = 0; $i < $sizeOfLines; $i++) {
            $_SESSION["log"] .= htmlentities($lines[$i]) . "</br > ";
            $plainTxt .= htmlentities($lines[$i]) . "\n";
            if ($i >= $num) {
                $_SESSION["log"] .= "<i > " . (sizeof($lines) - $num - 1) . " more lines </i ><br />";
                $plainTxt .= "" . (sizeof($lines) - $num - 1) . " more lines\n";
                return $plainTxt;
            }
        }
        return $plainTxt;
    }

    /**
     * @param array<mixed> $obj
     */
    static function createLogEs(array $obj): void
    {
        $num = 35;
        $_SESSION["log"] .= "<br /<br />";
        $_SESSION["log"] .= "<i > Failed indexing of records @ " . date('l jS \of F Y h:i:s A') . " </i ><br />";
        $sizeOfObj = sizeof($obj);
        for ($i = 0; $i < $sizeOfObj; $i++) {
            $_SESSION["log"] .= $obj[$i]["id"] . ": " . $obj[$i]["error"]["type"] . ": " . $obj[$i]["error"]["reason"] . ". caused_by: " . implode(": ", $obj[$i]["error"]["caused_by"]) . " </br > ";
            if ($i >= $num) {
                $_SESSION["log"] .= "<i > " . (sizeof($obj) - $num - 1) . " more lines </i ><br />";
                return;
            }
        }
    }
}