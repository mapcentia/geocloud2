<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\exceptions\GC2Exception;
use Throwable;

/**
 * On a PUBLIC v4 route the database comes from the URL, and the connection is opened lazily,
 * so an unknown database surfaces as a driver error on the first query. That is a missing
 * resource, not a bad request, and the driver's message is not for the caller.
 */
final class DatabaseNotFound
{
    /**
     * A 404 DATABASE_NOT_FOUND when $e says the database is unknown or does not accept
     * connections; otherwise $e itself.
     */
    public static function translate(Throwable $e, string $database): Throwable
    {
        $message = $e->getMessage();
        if (str_contains($message, 'database "' . $database . '" does not exist')
            || str_contains($message, 'not currently accepting connections')) {
            return new GC2Exception("Database not found", 404, null, 'DATABASE_NOT_FOUND');
        }
        return $e;
    }
}
