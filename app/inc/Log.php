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
     * @param string $string
     */
    static function write(string $string): void
    {
        error_log($string);
    }
}