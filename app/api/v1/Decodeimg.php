<?php
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
        $this->table = new \app\models\Table(Input::getPath()->part(5));
    }

    /**
     *
     */
    public function get_index()
    {
        header("Content-type: image/jpeg");
        $data = $this->table->getRecordByPri(Input::getPath()->part(7));
        $data = explode(",", base64_decode($data["data"][Input::getPath()->part(6)]))[1];
        echo base64_decode($data);
        exit();
    }
}