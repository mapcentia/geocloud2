<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\ogc;

use app\inc\Util;

final class Links
{
    /** Absolute base of the OGC API for a database, e.g. https://host/api/v4/ogc/database/mydb */
    public static function base(string $database): string
    {
        return Util::host() . '/api/v4/ogc/database/' . rawurlencode($database);
    }

    public static function collection(string $base, string $collectionId): string
    {
        return $base . '/collections/' . rawurlencode($collectionId);
    }
}
