<?php
include("../../inc/controller.php");
include("../../model/users.php");

/**
 *
 */

class User_c extends Controller
{
    public $user;
    function __construct()
    {
        parent::__construct();
        $parts = $this->getUrlParts();
        switch ($parts[4]) {
            case '' :
                $this->user = new User(null);
                echo $this->toJSON($this->user->getAll());
                break;
            case 'getdata' :
                $this->user = new User($parts[3]);
                echo $this->toJSON($this->user->getData());
                break;
        }
    }
}
new User_c();
