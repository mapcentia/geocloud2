<?php

namespace app\inc;

/**
 * Class Log
 * @package app\inc
 */
class Log
{
    /**
     * @param string $the_string
     */
    static function write($the_string)
    {
        error_log($the_string);
    }
}