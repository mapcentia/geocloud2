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
 * Class Decodeimg
 * @package app\api\v1
 */
class Decodeimg extends \app\inc\Controller
{

    /**
     * @var \app\models\Table
     */
    private $table;

    /**
     * Decodeimg constructor.
     */
    function __construct()
    {
        parent::__construct();
        $this->table = new \app\models\Table(Input::getPath()->part(5));
    }

    /**
     *
     */
    public function get_index()
    {
        header("Content-type: image/jpeg");
        $data = $this->table->getRecordByPri(Input::getPath()->part(7));
        $data = explode(",", $data["data"][Input::getPath()->part(6)])[1];
        echo base64_decode($data);
        exit();
    }
}