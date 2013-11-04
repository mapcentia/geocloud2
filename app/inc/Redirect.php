<?php
namespace app\inc;

class Redirect
{
    static function to($to)
    {
        header("location: {$to}");
    }
}