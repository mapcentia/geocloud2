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
 * Class Extent
 * @package app\api\v1
 */
class Extent extends \app\inc\Controller
{
    /**
     * @var \app\models\Layer
     */
    private $layers;

    /**
     * Extent constructor.
     */
    function __construct()
    {
        parent::__construct();

        $this->layers = new \app\models\Layer();
    }

    /**
     * @return mixed
     */
    public function get_index()
    {
        $layer = Input::getPath()->part(5);
        $extent = Input::getPath()->part(6) ?: "4326";
        return $this->layers->getEstExtent($layer, $extent);
    }
}