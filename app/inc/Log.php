<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;


/**
 * Class Log
 * @package app\inc
 */
class Log
{
    /**
     * @param string $path
     * @param string|null $body
     */
    static function write(string $path, ?string $body = null): void
    {
        $logFile = fopen($path, "a");
        fwrite($logFile, Util::clientIp() . " - - [" . date('Y-m-d H:i:s') . "] ");
        fwrite($logFile, "\"" . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER["REQUEST_URI"] . " " . $_SERVER['SERVER_PROTOCOL'] . "\" \"" . ($_SERVER['HTTP_USER_AGENT'] ?? null) . "\"\n");
        if (!empty($body)) fwrite($logFile,$body . "\n");
    }
}