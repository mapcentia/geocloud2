<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\ogc;

use app\exceptions\GC2Exception;
use app\exceptions\OwsException;
use app\exceptions\ServiceException;
use app\inc\Util;
use Throwable;

/**
 * Maps engine/proxy exceptions to the v4 JSON error contract. Before streaming, throw the result
 * (public/index.php renders it); inside a stream callback call render().
 */
final class Problem
{
    public static function toGc2(Throwable $e): GC2Exception
    {
        if ($e instanceof GC2Exception) {
            return $e;
        }
        $m = $e->getMessage();
        if (str_starts_with($m, 'DENY')) {
            return new GC2Exception('Access denied by rule', 403, $e, 'FORBIDDEN');
        }
        if (str_contains($m, "Relation doesn't exist") || str_contains($m, 'Layer is not enabled')) {
            return new GC2Exception('Collection not found', 404, $e, 'COLLECTION_NOT_FOUND');
        }
        if ($e instanceof OwsException || $e instanceof ServiceException) {
            return new GC2Exception($m, 400, $e, 'OGC_ERROR');
        }
        return new GC2Exception('Internal error', 500, $e, 'INTERNAL_ERROR');
    }

    /** Inside a stream callback: set the status while headers are still open, then the JSON body. */
    public static function render(Throwable $e): void
    {
        error_log((string)$e);
        $g = self::toGc2($e);
        $code = $g->getCode() >= 400 ? $g->getCode() : 500;
        if (!headers_sent()) {
            header('HTTP/1.1 ' . $code . ' ' . Util::httpCodeText($code), true, $code);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'success' => false, 'message' => $g->getMessage(), 'code' => $code, 'errorCode' => $g->getErrorCode(),
        ], JSON_UNESCAPED_UNICODE);
    }
}
