<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */


namespace app\api\v1;

use app\inc\Controller;
use app\inc\Input;
use app\models\Table;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;

/**
 * Class Decodeimg
 * @package app\api\v1
 */
class Decodeimg extends Controller
{

    /**
     * @var Table
     */
    private Table $table;

    /**
     * Decodeimg constructor.
     * @throws PhpfastcacheInvalidArgumentException
     */
    function __construct()
    {
        parent::__construct();
        $this->table = new Table(Input::getPath()->part(5));
    }

    /**
     *
     */
    public function get_index(): never
    {
        header("Content-type: image/jpeg");
        $data = $this->table->getRecordByPri(Input::getPath()->part(7));
        $data = explode(",", $data["data"][Input::getPath()->part(6)])[1];
        echo base64_decode($data);
        exit();
    }
}