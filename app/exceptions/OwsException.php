<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */


namespace app\exceptions;

use Exception;
use Throwable;

/**
 * The OwsException class represents a specialized exception used for generating
 * Exception Reports in the OGC (Open Geospatial Consortium) OWS (OGC Web Services) context.
 * It extends the base PHP Exception class, allowing for the inclusion of additional attributes
 * relevant to the exception and formatting the response according to OWS ExceptionReport specifications.
 *
 * This exception class provides mechanisms for storing key-value attributes
 * and generating a structured XML representation compliant with the OWS specification.
 */
class OwsException extends Exception
{
    protected array $attributes;

    public function __construct($message, $code = 0, ?Throwable $previous = null, array $attributes = [])
    {
        parent::__construct($message, $code, $previous);
        $this->attributes = $attributes;
    }

    public function getAttributes(): array {
        return $this->attributes;
    }

    public function getReport(): string
    {
        $str = '';
        if (!empty($this->attributes)) {
            foreach ($this->attributes as $key => $value) {
                $str.= ' ' . $key . '="' . $value . '"';
            }
        }
        return "<ows:ExceptionReport xmlns:xs=\"http://www.w3.org/2001/XMLSchema\" 
                xmlns:ows=\"http://www.opengis.net/ows\" 
                xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" 
                version=\"1.0.0\" 
                xsi:schemaLocation=\"http://www.opengis.net/ows http://bp.schemas.opengis.net/06-080r2/ows/1.0.0/owsExceptionReport.xsd\">
                <ows:Exception $str>
                <ows:ExceptionText>
                $this->message
                </ows:ExceptionText>
                </ows:Exception>
                </ows:ExceptionReport>
                ";
    }
}