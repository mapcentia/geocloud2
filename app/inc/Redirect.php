<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2020 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

namespace app\inc;

class Redirect
{
    /**
     * @param string $to
     */
    static function to(string $to): void
    {
        header("location: {$to}");
    }
}