<?php

namespace app\inc;

class Log
{

    /**
     *
     *
     * @param unknown $the_string
     * @return unknown
     */

    function write($the_string)
    {
        if ($fh = fopen("/var/log/mygeocloud.log", "a+")) {
            fputs($fh, $the_string, strlen($the_string));
            fclose($fh);
            return true;
        } else {
            return false;
        }

    }

}