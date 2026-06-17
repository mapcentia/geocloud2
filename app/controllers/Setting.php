<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\controllers;

use app\inc\Controller;
use app\inc\Input;
use Psr\Cache\InvalidArgumentException;

class Setting extends Controller
{
    private \app\models\Setting $settings;

    function __construct()
    {
        parent::__construct();
        $this->settings = new \app\models\Setting();
    }

    /**
     * @return array
     * @throws InvalidArgumentException
     */
    public function get_index(): array
    {
        return $this->settings->get();
    }

    /**
     * @return array
     * @throws InvalidArgumentException
     */
    public function put_pw(): array
    {
        return $this->settings->updatePw(Input::get('pw'));
    }

    /**
     * @return array
     * @throws InvalidArgumentException
     */
    public function put_apikey(): array
    {
        return $this->settings->updateApiKey();
    }

    /**
     * @return array
     * @throws InvalidArgumentException
     */
    public function put_extent(): array
    {
        $response = $this->isSuperUser();
        return (!$response['success']) ? $response : $this->settings->updateExtent(json_decode(Input::get())->data);
    }

    /**
     * @return array
     * @throws InvalidArgumentException
     */
    public function put_extentrestrict(): array
    {
        $response = $this->isSuperUser();
        return (!$response['success']) ? $response : $this->settings->updateExtentRestrict(json_decode(Input::get())->data);
    }

    /**
     * @return array
     * @throws InvalidArgumentException
     */
    public function get_usergroups(): array // Will update - used with jsonp
    {
        return $this->settings->updateUserGroups(json_decode(Input::get("q"))->data);
    }
}
