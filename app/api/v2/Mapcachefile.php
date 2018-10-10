<?php
/**
 * Long description for file
 *
 * Long description for file (if any)...
 *  
 * @category   API
 * @package    app\api\v2
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 * @since      File available since Release 2013.1
 *  
 */

namespace app\api\v2;

/**
 * Class Files
 * @package app\api\v1
 */
class Mapcachefile extends \app\inc\Controller
{
    /**
     * @var \app\controllers\Mapcachefile 
     */
    private $mapCacheFile;
    
    /**
     * Mapcachefile constructor.
     */
    function __construct()
    {
        parent::__construct();
        $this->mapCacheFile = new \app\controllers\Mapcachefile();
    }

    /**
     * @return array|bool
     */
    public function get_write()
    {
        return $this->mapCacheFile->get_index();

    }
}