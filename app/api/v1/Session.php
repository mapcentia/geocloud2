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
 * Class Session
 * @package app\api\v1
 */
class Session extends \app\inc\Controller
{
    /**
     * @var \app\models\Session
     */
    private $session;

    /**
     * Session constructor.
     */
    function __construct()
    {
        parent::__construct();

        $this->session = new \app\models\Session();
    }

    /**
     * @return array
     */
    public function post_start()
    {
        return $this->session->start(Input::get("u"), Input::get("p"), Input::get("s"));
    }

    /**
     * @return array
     */
    public function get_index()
    {
        return $this->session->check();
    }

    /**
     * @return array
     */
    public function get_stop()
    {
        return $this->session->stop();
    }
}