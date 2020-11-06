<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\wfs;


abstract class ServiceException
{
    static public function report($value)
    {

        ob_get_clean();
        ob_start();
        echo '<ServiceExceptionReport
                       version="1.2.0"
                       xmlns="http://www.opengis.net/ogc"
                       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                       xsi:schemaLocation="http://www.opengis.net/ogc http://wfs.plansystem.dk/geoserver/schemas//wfs/1.0.0/OGC-exception.xsd">
                       <ServiceException>';
        if (is_array($value)) {
            if (sizeof($value) == 1) {
                print $value[0];
            } else {
                print_r($value);
            }
        } else {
            print $value;
        }
        echo '</ServiceException>
	        </ServiceExceptionReport>';
        header("HTTP/1.0 200 " . \app\inc\Util::httpCodeText("200"));
        die();
    }
}