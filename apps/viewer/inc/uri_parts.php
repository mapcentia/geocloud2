<?php
$parts = explode("/", str_replace("?".$_SERVER['QUERY_STRING'],"",$_SERVER['REQUEST_URI']));
