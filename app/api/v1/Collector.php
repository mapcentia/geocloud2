<?php
/**
 * Long description for file
 *
 * Long description for file (if any)...
 *  
 * @category   API
 * @package    app\api\v1
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 * @since      File available since Release 2013.1
 *  
 */

namespace app\api\v1;

use \app\inc\Input;

/**
 * Class Collector
 * @package app\api\v1
 */
class Collector extends \app\inc\Controller
{
    /**
     * @var \app\models\Collector
     */
    private $collector;

    /**
     * Collector constructor.
     */
    function __construct()
    {
        parent::__construct();

        $this->collector = new \app\models\Collector();
    }

    /**
     * @return array
     */
    public function post_index()
    {
        $content = json_decode(Input::get(), true);
        return $this->collector->store($content);

    }
}