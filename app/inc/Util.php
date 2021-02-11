<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use Exception;


/**
 * Class Util
 * @package app\inc
 */
class Util
{
    /**
     * @param string $class
     * @param object $object
     * @return mixed
     */
    public static function casttoclass(string $class, object $object)
    {
        return unserialize(preg_replace('/^O:\d+:"[^"]++"/', 'O:' . strlen($class) . ':"' . $class . '"', serialize($object)));
    }

    /**
     * @param string $dir
     */
    public static function rrmdir(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . "/" . $object) == "dir") self::rrmdir($dir . "/" . $object); else unlink($dir . "/" . $object);
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }

    /**
     * @param string $hexStr
     * @param bool $returnAsString
     * @param string $seperator
     * @return array<string>|bool|string
     */
    public static function hex2RGB(string $hexStr, bool $returnAsString = false, string $seperator = ',')
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

    /**
     * @return string
     */
    public static function randHexColor(): string
    {
        return sprintf('#%06X', mt_rand(0, 0xFFFFFF));
    }

    /**
     * @param int|string $code
     * @return string|null
     */
    public static function httpCodeText($code): ?string
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
        return $codes[$code] ?? null;
    }

    /**
     * @param string $start
     * @param string $end
     * @param int $steps
     * @return array<mixed>
     */
    public static function makeGradient(string $start, string $end, int $steps): array
    {
        $theColorBegin = hexdec($start);
        $theColorEnd = hexdec($end);

        $theR0 = ($theColorBegin & 0xff0000) >> 16;
        $theG0 = ($theColorBegin & 0x00ff00) >> 8;
        $theB0 = ($theColorBegin & 0x0000ff) >> 0;

        $theR1 = ($theColorEnd & 0xff0000) >> 16;
        $theG1 = ($theColorEnd & 0x00ff00) >> 8;
        $theB1 = ($theColorEnd & 0x0000ff) >> 0;

        $grad = array();
        for ($i = 0; $i <= $steps; $i++) {
            $theR = self::interpolate($theR0, $theR1, $i, $steps);
            $theG = self::interpolate($theG0, $theG1, $i, $steps);
            $theB = self::interpolate($theB0, $theB1, $i, $steps);

            $theVal = ((($theR << 8) | $theG) << 8) | $theB;

            $grad[] = sprintf("#%06X", $theVal);
        }
        return $grad;

    }
    /**
     * @param int $pBegin
     * @param int $pEnd
     * @param int $pStep
     * @param int $pMax
     * @return int
     */
    private static function interpolate(int $pBegin, int $pEnd, int $pStep, int $pMax): int
    {
        if ($pBegin < $pEnd) {
            return (($pEnd - $pBegin) * ($pStep / $pMax)) + $pBegin;
        } else {
            return (($pBegin - $pEnd) * (1 - ($pStep / $pMax))) + $pEnd;
        }
    }

    /**
     * @return float
     */
    public static function microtime_float(): float
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    /**
     * @return string
     */
    public static function protocol(): string
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

    /**
     * @param string $ip
     * @param string $ipWithCidr
     * @return bool
     */
    public static function ipInRange(string $ip, string $ipWithCidr): bool
    {
        if (strpos($ipWithCidr, '/') !== false) {
            // $range is in IP/NETMASK format
            list($range, $netmask) = explode('/', $ipWithCidr, 2);
            if (strpos($netmask, '.') !== false) {
                // $netmask is a 255.255.0.0 format
                $netmask = str_replace('*', '0', $netmask);
                $netmask_dec = ip2long($netmask);
                return ((ip2long($ip) & $netmask_dec) == (ip2long($range) & $netmask_dec));
            } else {
                // $netmask is a CIDR size block
                // fix the range argument
                $x = explode('.', $range);
                while (count($x) < 4) $x[] = '0';
                list($a, $b, $c, $d) = $x;
                $range = sprintf("%u.%u.%u.%u", empty($a) ? '0' : $a, empty($b) ? '0' : $b, empty($c) ? '0' : $c, empty($d) ? '0' : $d);
                $range_dec = ip2long($range);
                $ip_dec = ip2long($ip);

                # Strategy 1 - Create the netmask with 'netmask' 1s and then fill it to 32 with 0s
                #$netmask_dec = bindec(str_pad('', $netmask, '1') . str_pad('', 32-$netmask, '0'));

                # Strategy 2 - Use math to create it
                $wildcard_dec = pow(2, (32 - (int)$netmask)) - 1;
                $netmask_dec = ~$wildcard_dec;

                return (($ip_dec & $netmask_dec) == ($range_dec & $netmask_dec));
            }
        } else {
            // range might be 255.255.*.* or 1.2.3.0-1.2.3.255
            if (strpos($ipWithCidr, '*') !== false) { // a.b.*.* format
                // Just convert to A-B format by setting * to 0 for A and 255 for B
                $lower = str_replace('*', '0', $ipWithCidr);
                $upper = str_replace('*', '255', $ipWithCidr);
                $ipWithCidr = "$lower-$upper";
            }

            if (strpos($ipWithCidr, '-') !== false) { // A-B format
                list($lower, $upper) = explode('-', $ipWithCidr, 2);
                $lower_dec = (float)sprintf("%u", ip2long($lower));
                $upper_dec = (float)sprintf("%u", ip2long($upper));
                $ip_dec = (float)sprintf("%u", ip2long($ip));
                return (($ip_dec >= $lower_dec) && ($ip_dec <= $upper_dec));
            }
            //echo 'Range argument is not in 1.2.3.4/24 or 1.2.3.4/255.255.255.0 format';
            return false;
        }
    }

    /**
     * @return string
     */
    public static function clientIp(): string
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP']))
            $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
        else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
            $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if (!empty($_SERVER['HTTP_X_FORWARDED']))
            $ipAddress = $_SERVER['HTTP_X_FORWARDED'];
        else if (!empty($_SERVER['HTTP_FORWARDED_FOR']))
            $ipAddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if (!empty($_SERVER['HTTP_FORWARDED']))
            $ipAddress = $_SERVER['HTTP_FORWARDED'];
        else if (!empty($_SERVER['REMOTE_ADDR']))
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipAddress = 'UNKNOWN';
        return $ipAddress;
    }

    /**
     * @param string $url
     * @param int $connectTimeout
     * @param int $timeout
     * @return mixed
     * @throws Exception
     */
    public static function wget(string $url, int $connectTimeout = 10, int $timeout = 0)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $connectTimeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        $buffer = curl_exec($ch);
        curl_close($ch);

        if (isset($buffer['curl_error'])) {
            throw new Exception($buffer['curl_error']);
        }
        if (isset($buffer['http_code']) && $buffer['http_code'] != "200") {
            throw new Exception("HTTP Code = " . $buffer['http_code']);
        }

        return $buffer;
    }

    /**
     * @param string $url
     * @param string $payload
     * @return bool
     */
    public static function asyncRequest(string $url, string $payload = ""): bool
    {
        $cmd = "curl -XGET -H 'Content-Type: application/json'";
        $cmd .= " -d '" . $payload . "' " . "'" . $url . "'";
        $cmd .= " > /dev/null 2>&1 &";
        exec($cmd, $output, $exit);
        return $exit == 0;
    }

    /**
     * @return string
     */
    public static function guid(): string
    {
        if (function_exists('com_create_guid') === true) {
            return trim(com_create_guid(), '{}');
        }

        return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
    }

    /**
     *
     */
    public static function disableOb(): void
    {
        // Turn off output buffering
        ini_set('output_buffering', 'off');
        // Turn off PHP output compression
        ini_set('zlib.output_compression', 'false');
        // Implicitly flush the buffer(s)
        ini_set('implicit_flush', 'true');
        ob_implicit_flush(1);
        // Clear, and turn off output buffering
        while (ob_get_level() > 0) {
            // Get the curent level
            $level = ob_get_level();
            // End the buffering
            ob_end_clean();
            // If the current level has not changed, abort
            if (ob_get_level() == $level) break;
        }
    }

    /**
     * @param string $str
     * @return string
     */
    public static function base64urlDecode(string $str): string
    {
        return base64_decode(str_replace(array('-', '_'), array('+', '/'), $str));
    }

    /**
     * @param string $sValue
     * @param bool $bQuotes
     * @return string
     */
    public static function format(string $sValue, bool $bQuotes = false): string
    {
        $sValue = trim($sValue);
        if ($bQuotes xor get_magic_quotes_gpc()) {
            $sValue = $bQuotes ? addslashes($sValue) : stripslashes($sValue);
        }
        return $sValue;
    }

}