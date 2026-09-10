<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\ogc;

use app\exceptions\GC2Exception;

/** Validated query parameters of /collections/{id}/map and /map. */
final readonly class MapParams
{
    public const int MAX_SIZE = 16384;
    private const array KNOWN = ['bbox', 'bbox-crs', 'crs', 'width', 'height', 'f', 'transparent', 'bgcolor', 'datetime'];

    public function __construct(
        /** @var list<float>|null [minx,miny,maxx,maxy] in $bboxCrs (already x/y) */
        public ?array  $bboxXY,
        public string  $bboxCrs,
        public string  $crs,
        public ?int    $width,
        public ?int    $height,
        /** 'png' | 'jpeg' */
        public string  $format,
        public bool    $transparent,
        public ?string $bgcolor,
        public ?string $datetime,
        /** @var list<string> collection ids (only for /map) */
        public array   $collections,
    ) {}

    /**
     * @param array<string,mixed> $query
     * @param list<string> $allowedCrs
     * @param bool $multi true for the dataset-level /map, which requires `collections`
     * @throws GC2Exception 400
     */
    public static function fromQuery(array $query, array $allowedCrs, bool $multi): self
    {
        Params::assertKnown($query, $multi ? [...self::KNOWN, 'collections'] : self::KNOWN);
        $f = isset($query['f']) ? strtolower((string)$query['f']) : 'png';
        if (!in_array($f, ['png', 'jpeg'], true)) {
            throw new GC2Exception("Unsupported format '$f' (png or jpeg)", 400, null, 'UNSUPPORTED_FORMAT');
        }
        $bboxCrs = Params::crs(isset($query['bbox-crs']) ? (string)$query['bbox-crs'] : null, $allowedCrs, 'bbox-crs');
        $bbox = Params::bbox(isset($query['bbox']) ? (string)$query['bbox'] : null);
        $collections = [];
        if ($multi) {
            $collections = array_values(array_filter(array_map('trim', explode(',', (string)($query['collections'] ?? ''))), fn($c) => $c !== ''));
            if ($collections === []) {
                throw new GC2Exception("Parameter 'collections' is required", 400, null, 'INVALID_PARAMETER');
            }
        }
        return new self(
            bboxXY: $bbox === null ? null : Crs::toXY($bbox, $bboxCrs),
            bboxCrs: $bboxCrs,
            crs: Params::crs(isset($query['crs']) ? (string)$query['crs'] : null, $allowedCrs, 'crs'),
            width: isset($query['width']) && $query['width'] !== '' ? self::size($query, 'width') : null,
            height: isset($query['height']) && $query['height'] !== '' ? self::size($query, 'height') : null,
            format: $f,
            transparent: Params::bool($query, 'transparent', $f === 'png'),
            bgcolor: Params::bgcolor(isset($query['bgcolor']) ? (string)$query['bgcolor'] : null),
            datetime: Params::datetime(isset($query['datetime']) ? (string)$query['datetime'] : null),
            collections: $collections,
        );
    }

    private static function size(array $query, string $name): int
    {
        $v = Params::int($query, $name, 0, 1, PHP_INT_MAX);
        if ($v > self::MAX_SIZE) {
            throw new GC2Exception("Parameter '$name' must be at most " . self::MAX_SIZE, 400, null, 'INVALID_PARAMETER');
        }
        return $v;
    }
}
