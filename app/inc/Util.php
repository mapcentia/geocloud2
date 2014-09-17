<?php
namespace app\inc;
class Util
{
    static function casttoclass($class, $object)
    {
        return unserialize(preg_replace('/^O:\d+:"[^"]++"/', 'O:' . strlen($class) . ':"' . $class . '"', serialize($object)));
    }

    static function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . "/" . $object) == "dir") rrmdir($dir . "/" . $object); else unlink($dir . "/" . $object);
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }

    static function replace_unicode_escape_sequence($match)
    {
        return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
    }

    static function hex2RGB($hexStr, $returnAsString = false, $seperator = ',')
    {
        $hexStr = preg_replace("/[^0-9A-Fa-f]/", '', $hexStr);
        // Gets a proper hex string
        $rgbArray = array();
        if (strlen($hexStr) == 6) { //If a proper hex code, convert using bitwise operation. No overhead... faster
            $colorVal = hexdec($hexStr);
            $rgbArray['red'] = 0xFF & ($colorVal >> 0x10);
            $rgbArray['green'] = 0xFF & ($colorVal >> 0x8);
            $rgbArray['blue'] = 0xFF & $colorVal;
        } elseif (strlen($hexStr) == 3) { //if shorthand notation, need some string manipulations
            $rgbArray['red'] = hexdec(str_repeat(substr($hexStr, 0, 1), 2));
            $rgbArray['green'] = hexdec(str_repeat(substr($hexStr, 1, 1), 2));
            $rgbArray['blue'] = hexdec(str_repeat(substr($hexStr, 2, 1), 2));
        } else {
            return false;
            //Invalid hex color code
        }
        return $returnAsString ? implode($seperator, $rgbArray) : $rgbArray;
        // returns the rgb string or the associative array
    }

    static function randHexColor()
    {
        return sprintf('#%06X', mt_rand(0, 0xFFFFFF));
    }

    static function httpCodeText($code)
    {
        $codes = array(
            200 => "OK",
            304 => "Not Modified",
            400 => "Bad Request",
            401 => "Unauthorized",
            403 => "Forbidden",
            404 => "Not Found",
            406 => "Not Acceptable",
            410 => "Gone",
            420 => "Enhance Your Calm",
            422 => "Unprocessable Entity",
            429 => "Too Many Requests",
            500 => "Internal Server Error",
            502 => "Bad Gateway",
            503 => "Service Unavailable",
            504 => "Gateway timeout"
        );
        return $codes[$code];
    }

    static function makeGradient($start, $end, $steps)
    {

        $theColorBegin = hexdec($start);
        $theColorEnd = hexdec($end);

        $theR0 = ($theColorBegin & 0xff0000) >> 16;
        $theG0 = ($theColorBegin & 0x00ff00) >> 8;
        $theB0 = ($theColorBegin & 0x0000ff) >> 0;

        $theR1 = ($theColorEnd & 0xff0000) >> 16;
        $theG1 = ($theColorEnd & 0x00ff00) >> 8;
        $theB1 = ($theColorEnd & 0x0000ff) >> 0;

        function interpolate($pBegin, $pEnd, $pStep, $pMax)
        {
            if ($pBegin < $pEnd) {
                return (($pEnd - $pBegin) * ($pStep / $pMax)) + $pBegin;
            } else {
                return (($pBegin - $pEnd) * (1 - ($pStep / $pMax))) + $pEnd;
            }
        }

        $grad = array();
        for ($i = 0; $i <= $steps; $i++) {
            $theR = interpolate($theR0, $theR1, $i, $steps);
            $theG = interpolate($theG0, $theG1, $i, $steps);
            $theB = interpolate($theB0, $theB1, $i, $steps);

            $theVal = ((($theR << 8) | $theG) << 8) | $theB;

            $grad[] = sprintf("#%06X", $theVal);
        }
        return $grad;

    }

    static function microtime_float()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    static function protocol()
    {
        if (isset($_SERVER['HTTPS']) &&
            ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) ||
            isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
            $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https'
        ) {
            $protocol = 'https';
        } else {
            $protocol = 'http';
        }
        return $protocol;
    }
}