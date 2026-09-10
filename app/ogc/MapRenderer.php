<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\ogc;

use app\api\v4\Responses\StreamedResponse;
use app\exceptions\GC2Exception;
use app\inc\PublicIdentity;

/**
 * Stub for the OGC API Maps renderer (Task 11 replaces this with the real WMS-backed
 * implementation). Until then every map request fails with 501.
 */
final class MapRenderer
{
    public function __construct(private readonly PublicIdentity $id) {}

    /** @param list<string> $tables @param list<float> $defaultBbox4326 */
    public function render(string $schema, array $tables, MapParams $p, array $defaultBbox4326): StreamedResponse
    {
        throw new GC2Exception('Maps not implemented yet', 501, null, 'NOT_IMPLEMENTED');
    }
}
