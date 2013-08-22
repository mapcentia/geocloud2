<?php
class Twitter_c extends Controller
{
    private $tweet;

    function __construct()
    {
        parent::__construct();
        $this->tweet = new tweet();
    }
    public function search($search, $lifetime = 0)
    {
        return ($this->toJSON($this->tweet->search(urldecode($search))));
    }
}