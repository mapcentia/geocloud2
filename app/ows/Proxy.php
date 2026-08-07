<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\ows;

use app\conf\App;
use app\exceptions\ServiceException;

final class Proxy
{
    private const MAPSERV = 'http://127.0.0.1/cgi-bin/mapserv.fcgi';
    private const QGIS = 'http://127.0.0.1/cgi-bin/qgis_mapserv.fcgi';

    public function __construct(private readonly Context $ctx) {}

    private function mapfileName(Request $req): string
    {
        return match ($req->service) {
            'utfgrid', 'wfs' => "{$this->ctx->database}_{$this->ctx->schema}_wfs.map",
            default => "{$this->ctx->database}_{$this->ctx->schema}_wms.map",
        };
    }

    private function mapfilesDir(): string
    {
        return App::$param['path'] . '/app/wms/mapfiles/';
    }

    /**
     * @return array{0:string,1:?string} [backend URL, tmp file path or null]
     * @throws ServiceException
     */
    public function resolve(Request $req, array $filters): array
    {
        $resolver = SourceResolver::fromLayers($this->ctx->model(), $this->ctx->schema, $req->layers);

        // POST (WFS-T XML): always MapServer against the static/tmp mapfile, patched
        // only for rule-derived filters. Never QGS, external WMS, labels, or query string.
        if ($req->method === 'POST') {
            $path = $this->mapfilesDir() . $this->mapfileName($req);
            if (!empty($filters)) {
                $content = file_get_contents($path);
                $patched = MapfilePatcher::patchMapfileContent($content, $filters, false, $req->layers);
                $tmp = new MapfilePatcher()->writeTmp($path, $patched);
                return [self::MAPSERV . "?map=$tmp", $tmp];
            }
            return [self::MAPSERV . "?map=$path", null];
        }

        // GET decision matrix. Compute the QGS path over ALL layers first (legacy order)
        // so the multi-layer guard is reachable, then disable QGS for multi-layer.
        $qgsAll = $resolver->qgsFilePath();
        $useFilters = !empty($filters) || $req->disableLabels;

        // Legacy guard: filters/rules + QGS + multiple layers is not allowed.
        if ($useFilters && $qgsAll && count($req->layers) > 1) {
            throw new ServiceException(
                "One or more layers are served by QGIS Server. Filters and rules don't work "
                . "with multiple layers, where one or more is QGIS backed."
            );
        }
        $qgs = count($req->layers) > 1 ? null : $qgsAll;

        if ($useFilters) {
            if ($qgs && count($req->layers) === 1) {
                $content = file_get_contents($qgs);
                $patched = MapfilePatcher::patchQgsContent($content, $filters, $req->disableLabels, $req->layers);
                $tmp = new MapfilePatcher()->writeTmp($qgs, $patched);
                return [self::QGIS . "?map=$tmp&{$req->queryString}", $tmp];
            }
            $path = $this->mapfilesDir() . $this->mapfileName($req);
            $content = file_get_contents($path);
            $patched = MapfilePatcher::patchMapfileContent($content, $filters, $req->disableLabels, $req->layers);
            $tmp = new MapfilePatcher()->writeTmp($path, $patched);
            return [self::MAPSERV . "?map=$tmp&{$req->queryString}", $tmp];
        }

        // No filters/labels
        if ($qgs && $req->service !== 'utfgrid') {
            return [self::QGIS . "?map=$qgs&{$req->queryString}", null];
        }

        // External WMS source passthrough (single layer only)
        if ($source = $resolver->wmsSource(count($req->layers))) {
            return [$this->externalWmsUrl($source, $req), null];
        }

        $path = $this->mapfilesDir() . $this->mapfileName($req);
        return [self::MAPSERV . "?map=$path&{$req->queryString}", null];
    }

    private function externalWmsUrl(array $source, Request $req): string
    {
        parse_str(parse_url($req->queryString)['path'] ?? $req->queryString, $query);
        $query = array_change_key_case($query, CASE_UPPER);
        $merged = array_merge($query, $source['query']);
        foreach (['BBOX', 'WIDTH', 'HEIGHT'] as $k) {
            if (isset($query[$k])) {
                $merged[$k] = $query[$k];
            } else {
                unset($merged[$k]);
            }
        }
        $bits = explode('.', $source['query']['VERSION'] ?? '1.1.0');
        if ((int) ($bits[1] ?? 1) < 3) {
            $merged['SRS'] = $query['SRS'] ?? $query['CRS'] ?? '';
            unset($merged['CRS']);
        } else {
            $merged['CRS'] = $query['SRS'] ?? $query['CRS'] ?? '';
            unset($merged['SRS']);
        }
        $merged['REQUEST'] = 'GetMap';
        $credentials = (!empty($source['user']) && !empty($source['pass']))
            ? $source['user'] . ':' . $source['pass'] . '@' : '';
        return $source['scheme'] . '://' . $credentials . $source['host'] . $source['path']
             . '?' . http_build_query($merged);
    }

    /**
     * Streams the backend response to php://output. Forwards headers with legacy
     * filtering; never buffers the whole body.
     */
    public function run(string $url, Request $req): void
    {
        header('X-Powered-By: GC2 WMS');
        header('Cache-Control: no-store');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $line) {
            $bits = explode(':', $line);
            if (count($bits) > 1 && $bits[0] === 'Content-Type'
                && (trim($bits[1]) === 'application/vnd.ogc.se_xml' || trim($bits[1]) === 'text/xml; charset=UTF-8')) {
                header('Content-Type: text/xml');
            } elseif (count($bits) > 1 && $bits[0] !== 'Content-Encoding' && trim($bits[1]) !== 'chunked') {
                header($line);
            }
            return strlen($line);
        });
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $data) {
            echo $data;
            flush();
            if (ob_get_level() > 0) {
                ob_flush();
            }
            return strlen($data);
        });
        if ($req->method === 'POST' && $req->rawPostBody !== null) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $req->rawPostBody);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: text/xml',
                'Content-Length: ' . strlen($req->rawPostBody),
            ]);
        }
        curl_exec($ch);
        curl_close($ch);
    }
}
