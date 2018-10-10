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
 * Class Ckan
 * @package app\api\v1
 */
class Ckan extends \app\inc\Controller
{
    /**
     * @var \app\models\Layer
     */
    public $layers;

    /**
     * Ckan constructor.
     */
    function __construct()
    {
        parent::__construct();

        $this->layers = new \app\models\Layer();
    }

    /**
     * @return array|bool
     */
    public function get_index()
    {
        return ($this->authApiKey(Input::getPath()->part(4), Input::get("key") ?: "")) ? $this->layers->updateCkan(Input::get("id"), Input::get("host")) : false;
    }
}