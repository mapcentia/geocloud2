<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2022 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\conf\App;


/**
 * Class Session
 * @package app\inc
 */
class Session
{
    /**
     *
     */
    public static function start(): void
    {
        if (session_status() == PHP_SESSION_NONE) {
            $sessionMaxAge = App::$param["sessionMaxAge"] ?? 86400;
            ini_set("session.cookie_lifetime", (string)$sessionMaxAge);
            ini_set("session.gc_maxlifetime", (string)$sessionMaxAge);
            ini_set("session.gc_probability", "1");
            ini_set("session.gc_divisor", "1");
            if (!empty(App::$param["sessionDomain"])) {
                ini_set("session.cookie_domain", App::$param["sessionDomain"]);
            }
            if (Util::protocol() == "https") {
                ini_set("session.cookie_samesite", "None");
                ini_set("session.cookie_secure", 'On');
            }
            session_start();
        }
    }

    /**
     * @param string $key
     * @param mixed $value
     */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * @param string $key
     * @return mixed|null
     */
    public static function getByKey(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    /**
     * @return array|null
     */
    public static function get(): array|null
    {
        return $_SESSION;
    }

    /**
     * @param string|null $redirect
     * @return bool|void
     */
    public static function authenticate(?string $redirect = " / ")
    {
        if (isset($_SESSION['auth']) && $_SESSION['auth']) {
            return true;
        } elseif ($redirect) {
            Redirect::to($redirect);
            exit();
        } else {
            exit();
        }
    }

    /**
     * @return bool
     */
    public static function isAuth(): bool
    {
        return $_SESSION['auth'] ?? false;
    }

    /**
     * @return string
     */
    public static function getId(): string
    {
        return session_id();
    }

    /**
     * @return void
     */
    public static function write(): void
    {
        session_write_close();
    }

    /**
     * @return string|null
     */
    public static function getUser(): ?string
    {
        return $_SESSION['screen_name'] ?? null;
    }

    /**
     * @return string|null
     */
    public static function getDatabase(): ?string
    {
        return $_SESSION['parentdb'] ?? null;
    }

    /**
     * @return bool
     */
    public static function isSubUser(): bool
    {
        return !empty($_SESSION["subuser"]);
    }

    /**
     * @return string
     */
    public static function getFullUseName(): string
    {
        return $_SESSION["subuser"] ? $_SESSION["screen_name"] . "@" . $_SESSION['parentdb'] : $_SESSION['screen_name'];
    }

    /**
     * @return string
     */
    public static function getLog(): string
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
    public static function createLog(array $lines, string $file): string
    {
        $num = 15;
        $plainTxt = "";
        $_SESSION["log"] .= "<br /<br />";
        $_SESSION["log"] .= "<i > Failed upload of $file @ " . date('l jS \of F Y h:i:s A') . " </i ><br />";
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
     * @param array $obj
     */
    public static function createLogEs(array $obj): void
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