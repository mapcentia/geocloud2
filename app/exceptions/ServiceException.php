<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */


namespace app\exceptions;

use Exception;
use Throwable;

class ServiceException extends Exception
{

    public function __construct($message, $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function getReport(): string
    {
        $xml = "
        <ServiceExceptionReport
        	   version=\"1.2.0\"
	           xmlns=\"http://www.opengis.net/ogc\"
	           xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\"
	           xsi:schemaLocation=\"http://www.opengis.net/ogc http://schemas.opengis.net/wfs/1.0.0/OGC-exception.xsd\">
	           <ServiceException>$this->message</ServiceException>
	           </ServiceExceptionReport>
                
                ";

        return $xml;
    }
}