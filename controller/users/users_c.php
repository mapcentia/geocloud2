<?php
include ("../../inc/controller.php");
include ("../../model/users.php");

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
        $this->user = new User($parts[3]);
        switch ($parts[4]) {
            case 'getdata' :
                echo $this->toJSON($this->user->getData());
                break;
        }
    }
}

new User_c();
