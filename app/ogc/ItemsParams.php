<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\ogc;

use app\exceptions\GC2Exception;

/** Validated query parameters of /collections/{id}/items and /items/{fid}. */
final readonly class ItemsParams
{
    public const int DEFAULT_LIMIT = 10;
    public const int MAX_LIMIT = 10000;
    private const array KNOWN = ['limit', 'offset', 'bbox', 'bbox-crs', 'crs', 'datetime', 'f'];
    private const array KNOWN_SINGLE = ['crs', 'datetime', 'f'];

    public function __construct(
        public int     $limit,
        public int     $offset,
        /** @var list<float>|null exactly as given, in the axis order of $bboxCrs */
        public ?array  $bbox,
        public string  $bboxCrs,
        public string  $crs,
        public ?string $datetime,
    ) {}

    /**
     * @param array<string,mixed> $query
     * @param list<string> $allowedCrs the collection's crs list
     * @throws GC2Exception 400
     */
    public static function fromQuery(array $query, array $allowedCrs, bool $single = false): self
    {
        Params::assertKnown($query, $single ? self::KNOWN_SINGLE : self::KNOWN);
        Params::assertJson($query);
        return new self(
            limit: $single ? 1 : Params::int($query, 'limit', self::DEFAULT_LIMIT, 1, self::MAX_LIMIT),
            offset: $single ? 0 : Params::int($query, 'offset', 0, 0, PHP_INT_MAX),
            bbox: $single ? null : Params::bbox(isset($query['bbox']) ? (string)$query['bbox'] : null),
            bboxCrs: Params::crs(isset($query['bbox-crs']) ? (string)$query['bbox-crs'] : null, $allowedCrs, 'bbox-crs'),
            crs: Params::crs(isset($query['crs']) ? (string)$query['crs'] : null, $allowedCrs, 'crs'),
            datetime: Params::datetime(isset($query['datetime']) ? (string)$query['datetime'] : null),
        );
    }
}
