<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */
namespace app\wfs\output;

use app\exceptions\OwsException;
use Throwable;

final class ExceptionReport
{
    public static function render(Throwable $e, string $version, GmlWriter $writer): void
    {
        // Discard any pending buffered content so we don't ship half a feature collection
        $writer->bufferDiscard();
        $writer->bufferStart();

        $message = htmlspecialchars($e->getMessage(), ENT_XML1 | ENT_QUOTES);

        if ($version === '1.1.0') {
            // OWS 1.1.0 format. Use OwsException attributes when available;
            // otherwise produce a generic exception report (e.g. PDOException).
            if ($e instanceof OwsException) {
                $atts = $e->getAttributes();
                $code = htmlspecialchars($atts['exceptionCode'] ?? 'NoApplicableCode', ENT_XML1 | ENT_QUOTES);
                $locator = isset($atts['locator']) ? ' locator="' . htmlspecialchars($atts['locator'], ENT_XML1 | ENT_QUOTES) . '"' : '';
            } else {
                $code = 'NoApplicableCode';
                $locator = '';
            }
            $writer->write(
                '<?xml version="1.0" encoding="UTF-8"?>'
                . '<ows:ExceptionReport version="1.0.0" xmlns:ows="http://www.opengis.net/ows">'
                . "<ows:Exception exceptionCode=\"{$code}\"{$locator}>"
                . "<ows:ExceptionText>{$message}</ows:ExceptionText>"
                . '</ows:Exception></ows:ExceptionReport>'
            );
        } else {
            $writer->write(
                '<?xml version="1.0" encoding="UTF-8"?>'
                . '<ServiceExceptionReport version="1.2.0" xmlns="http://www.opengis.net/ogc">'
                . "<ServiceException>{$message}</ServiceException>"
                . '</ServiceExceptionReport>'
            );
        }
        $writer->bufferFlush();
    }
}
