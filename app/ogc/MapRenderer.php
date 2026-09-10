<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\ogc;

use app\api\v4\Responses\StreamedResponse;
use app\exceptions\ServiceException;
use app\inc\Model;
use app\inc\PublicIdentity;
use app\inc\Util;
use app\ows\LayerGate;
use app\ows\Proxy;
use app\ows\Request as OwsRequest;
use app\ows\RuleFilters;
use Throwable;

/**
 * OGC API Maps Part 1 (Core) on top of the OWS proxy: the request is translated to a WMS 1.3.0
 * GetMap and streamed from MapServer/QGIS Server through app\ows\Proxy, so geofence rules and the
 * versioning filter are patched into the mapfile exactly as for the WMS endpoint.
 */
final class MapRenderer
{
    public const int MAX_SIZE = 16384;
    public const int DEFAULT_WIDTH = 1024;

    public function __construct(private readonly PublicIdentity $id) {}

    /**
     * @param list<string> $tables tables in $schema, in drawing order
     * @param list<float> $defaultBbox4326 [minx,miny,maxx,maxy] used when the request has no bbox
     */
    public function render(string $schema, array $tables, MapParams $p, array $defaultBbox4326): StreamedResponse
    {
        $layers = array_map(fn(string $t) => "$schema.$t", $tables);
        new LayerGate($this->id)->authorizeRead($layers);
        try {
            $filters = new RuleFilters($this->id)->forLayers($layers, $schema, [], $p->datetime);
        } catch (ServiceException $e) {
            throw Problem::toGc2($e);
        }
        $target = Crs::epsg($p->crs);
        if ($p->bboxXY !== null) {
            $bbox = $p->bboxXY;
            $from = Crs::epsg($p->bboxCrs);
        } else {
            $bbox = $defaultBbox4326;
            $from = 4326;
        }
        if ($from !== $target) {
            $bbox = self::transformBbox(new Model(connection: $this->id->connection), $bbox, $from, $target);
        }
        [$w, $h] = self::size($p->width, $p->height, $bbox);
        $query = [
            'SERVICE' => 'WMS',
            'VERSION' => '1.3.0',
            'REQUEST' => 'GetMap',
            'LAYERS' => implode(',', $layers),
            'STYLES' => '',
            'CRS' => Crs::wmsCrs($p->crs),
            'BBOX' => Crs::wmsBbox($bbox, $p->crs),
            'WIDTH' => $w,
            'HEIGHT' => $h,
            'FORMAT' => $p->format === 'jpeg' ? 'image/jpeg' : 'image/png',
            'TRANSPARENT' => $p->transparent ? 'TRUE' : 'FALSE',
        ];
        if ($p->bgcolor !== null) {
            $query['BGCOLOR'] = '0x' . $p->bgcolor;
        }
        $req = OwsRequest::parse('GET', $query, http_build_query($query), null);
        $ctx = $this->id->owsContext($schema);
        return new StreamedResponse(
            contentType: $p->format === 'jpeg' ? 'image/jpeg' : 'image/png', // overridden by upstream headers in Proxy::run
            callback: function () use ($ctx, $req, $filters) {
                Util::disableOb();
                $tmp = null;
                try {
                    $proxy = new Proxy($ctx);
                    [$url, $tmp] = $proxy->resolve($req, $filters);
                    $proxy->run($url, $req);
                } catch (Throwable $e) {
                    Problem::render($e);
                } finally {
                    if ($tmp) {
                        @unlink($tmp);
                    }
                }
            },
        );
    }

    /**
     * Pixel size: a missing dimension follows the bbox aspect ratio; nothing given → DEFAULT_WIDTH.
     *
     * @param list<float> $bboxXY
     * @return array{0:int,1:int}
     */
    public static function size(?int $w, ?int $h, array $bboxXY): array
    {
        $dx = max(abs($bboxXY[2] - $bboxXY[0]), 1e-9);
        $dy = max(abs($bboxXY[3] - $bboxXY[1]), 1e-9);
        if ($w === null && $h === null) {
            $w = self::DEFAULT_WIDTH;
        }
        if ($h === null) {
            $h = (int) round($w * $dy / $dx);
        }
        if ($w === null) {
            $w = (int) round($h * $dx / $dy);
        }
        return [max(1, min(self::MAX_SIZE, $w)), max(1, min(self::MAX_SIZE, $h))];
    }

    /**
     * Reprojects an x/y envelope by densifying its edges first, so a box that is not
     * axis-aligned in the target CRS still covers the requested area.
     *
     * @param list<float> $xy
     * @return list<float>
     */
    public static function transformBbox(Model $model, array $xy, int $from, int $to): array
    {
        $seg = max(($xy[2] - $xy[0]) / 10.0, 1e-9);
        // :t is cast explicitly: ST_Transform has both a (geometry, integer) and a (geometry, text)
        // overload, and PDO's emulated prepares bind every parameter as an unknown-typed quoted
        // literal, which Postgres otherwise resolves to the text (proj4 string) overload instead
        // of the SRID one, failing with "could not parse proj string".
        $res = $model->prepare(
            'SELECT ST_XMin(g) AS xmin, ST_YMin(g) AS ymin, ST_XMax(g) AS xmax, ST_YMax(g) AS ymax FROM ('
            . 'SELECT ST_Transform(ST_Segmentize(ST_MakeEnvelope(:x1, :y1, :x2, :y2, :f), :seg), :t::integer) AS g) s'
        );
        $model->execute($res, ['x1' => $xy[0], 'y1' => $xy[1], 'x2' => $xy[2], 'y2' => $xy[3], 'f' => $from, 'seg' => $seg, 't' => $to]);
        $r = $model->fetchRow($res);
        return [(float)$r['xmin'], (float)$r['ymin'], (float)$r['xmax'], (float)$r['ymax']];
    }
}
