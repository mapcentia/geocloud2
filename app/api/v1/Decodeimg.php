<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */


namespace app\api\v1;

use app\exceptions\GC2Exception;
use app\inc\Controller;
use app\inc\Input;
use app\inc\Util;
use app\models\Table;
use Error;
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
     * @throws GC2Exception
     */
    function __construct()
    {
        parent::__construct();
        $this->table = new Table(Input::getPath()->part(5));
    }

    /**
     *
     * @throws GC2Exception
     */
    public function get_index(): never
    {
        $record = $this->table->getRecordByPri(Input::getPath()->part(7))["data"];
        $dataUri = $record[Input::getPath()->part(6)];
        if (!$dataUri) {
            header("HTTP/1.0 404 " . Util::httpCodeText("404"));
            exit();
        }
        // Extract and set content type
        if (preg_match('/^data:(.*?);/', $dataUri, $matches)) {
            header("Content-type: " . $matches[1]);
        }
        // PHP can read data URIs directly
        try {
            readfile($dataUri);
        } catch (Error $e) {
            throw new GC2Exception($e->getMessage(), 404, null, "VALUE_PARSE_ERROR");
        }
        exit();
    }
}