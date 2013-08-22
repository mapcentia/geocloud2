<?php
set_time_limit(0);
include '../../../conf/main.php';
include "inc/controller.php";
include "inc/TwitterAPIExchange.php";
include "model/tweets.php";
//$parts = Controller::getUrlParts();

include "twitter_c.php";
$twitter = new Twitter_c();
echo $twitter->search($_GET['search'],0);