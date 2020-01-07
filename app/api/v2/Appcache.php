<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2020 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v2;


use app\inc\Cache;
use app\inc\Controller;

class Appcache extends Controller
{
    public function get_stats(): array
    {
        return ["stats" => Cache::getStats()];
    }

    public function get_clear(): array
    {
        return Cache::clear();
    }

    public function get_items(): array
    {
        return ["items" => json_decode(Cache::getItemsByTagsAsJsonString())];
    }
}