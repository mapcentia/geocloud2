<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2020 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v2;

ini_set('max_execution_time', 0);

use \app\inc\Controller;
use \app\conf\App;
use \OpenApi\Annotations as OA;


class Disk extends Controller
{
    public function get_free(): array
    {
        $bytes = disk_free_space("/");
        $si_prefix = array('B', 'KB', 'MB', 'GB', 'TB', 'EB', 'ZB', 'YB');
        $base = 1024;
        $class = min((int)log($bytes, $base), count($si_prefix) - 1);
        return ["success" => true, "message" => "Free disk space", "data" => ["bytes" => $bytes, "human_readable" => sprintf('%1.2f', $bytes / pow($base, $class)) . ' ' . $si_prefix[$class]]];
    }

    public function get_delete(): array
    {
        $result = [];
        function rrmdir($dir, &$result)
        {
            if (is_dir($dir)) {
                $objects = scandir($dir);
                //print_r($objects);
                foreach ($objects as $object) {
                    if ($object != "." && $object != ".." && $object != ".gitignore") {
                        if (is_dir($dir . "/" . $object) && !is_link($dir . "/" . $object)) {
                            rrmdir($dir . "/" . $object, $result);
                        } else {
                            unlink($dir . "/" . $object);
                        }
                        $result[] = $dir . "/" . $object;
                    }
                }
                rmdir($dir);
            }
        }
        $dirs = [App::$param["path"] . 'app/tmp'];
        foreach ($dirs as $dir) {
            rrmdir($dir, $result);
        }
        return ["success" => true, "message" => "Unlinked files", "data" => $result];
    }
}