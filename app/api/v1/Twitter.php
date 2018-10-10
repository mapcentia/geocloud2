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

class Twitter extends \app\inc\Controller
{
    private $tweet;

    function __construct()
    {
        parent::__construct();

        $this->tweet = new \app\models\Twitter();
    }
    public function get_index($lifetime = 0)
    {
        return $this->tweet->search(urldecode(Input::get('search')),Input::get('store'),Input::getPath()->part(5));
    }
}