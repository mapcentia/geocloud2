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
 * Class Senti
 * @package app\api\v1
 */
class Senti extends \app\inc\Controller
{

    /**
     * Senti constructor.
     */
    function __construct()
    {
        parent::__construct();
    }

    /**
     * @return array
     */
    public function post_index()
    {
        $data = json_decode(Input::get(), true);
        $senti = new \app\models\Senti();
        return $senti->insert($data,Input::getPath()->part(5));
    }
}