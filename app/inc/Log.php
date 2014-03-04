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

    static function write($the_string)
    {
        error_log($the_string);

    }

}