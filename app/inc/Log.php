<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
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
     * @param string $the_string
     */
    static function write($the_string)
    {
        error_log($the_string);
    }
}