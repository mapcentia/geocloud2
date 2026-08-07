<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\ows;

use app\exceptions\ServiceException;
use app\inc\Input;
use app\inc\Util;
use SimpleXMLElement;

final readonly class Request
{
    public function __construct(
        public string  $method,
        public string  $service,
        public array   $layers,
        public array   $filters,
        public bool    $disableLabels,
        public string  $queryString,
        public array   $query,
        public ?string $rawPostBody,
    ) {}

    /**
     * Single point of GET/POST/XML parsing. Reads all inputs via Input:: helpers,
     * then delegates the pure logic to parse() (unit-tested directly).
     */
    public static function fromHttp(): self
    {
        $method = strtoupper(Input::getMethod());
        $queryString = Input::getQueryString();
        if ($method === 'GET') {
            return self::parse('GET', (array) Input::get(), $queryString, null);
        }
        return self::parse('POST', (array) Input::get(), $queryString, Input::getBody());
    }

    /**
     * @throws ServiceException
     */
    public static function parse(string $method, array $query, string $queryString, ?string $body): self
    {
        // Upper-case query keys for consistent access
        $query = array_change_key_case($query, CASE_UPPER);

        $service = 'wms';
        $layers = [];
        $rawPostBody = null;

        if ($method === 'POST' && !empty($body)) {
            $rawPostBody = $body;
            $xml = @simplexml_load_string($body);
            if ($xml === false) {
                throw new ServiceException('Could not parse the request body');
            }
            $service = strtolower((string) $xml['service']) ?: 'wfs';
            $layers = self::layersFromXml($xml);
            if (count($layers) === 0) {
                throw new ServiceException('Could not get the typeName from the requests');
            }
        } else {
            // Service detection from query params
            $svc = strtolower((string) ($query['SERVICE'] ?? ''));
            $format = strtolower((string) ($query['FORMAT'] ?? ''));
            if ($format === 'json' || $format === 'mvt') {
                $service = 'utfgrid';
            } elseif ($svc !== '') {
                $service = $svc;
            }
            $layerParam = $query['LAYERS'] ?? $query['LAYER'] ?? $query['TYPENAME'] ?? $query['TYPENAMES'] ?? '';
            if ($layerParam !== '') {
                $layers = array_map([self::class, 'stripNamespace'], explode(',', (string) $layerParam));
            }
        }

        $filters = [];
        if (!empty($query['FILTERS'])) {
            $decoded = json_decode(Util::base64urlDecode((string) $query['FILTERS']), true) ?: [];
            // Enclose each filter in parentheses (legacy behavior)
            $filters = array_map(fn($f) => array_map(fn($i) => "($i)", (array) $f), $decoded);
        }

        $disableLabels = isset($query['LABELS']) && strtolower((string) $query['LABELS']) === 'false';

        return new self(
            method: $method,
            service: $service,
            layers: $layers,
            filters: $filters,
            disableLabels: $disableLabels,
            queryString: $queryString,
            query: $query,
            rawPostBody: $rawPostBody,
        );
    }

    private static function stripNamespace(string $name): string
    {
        $bits = explode(':', $name);
        return count($bits) > 1 ? $bits[1] : $name;
    }

    /**
     * @return array<string>
     */
    private static function layersFromXml(SimpleXMLElement $xml): array
    {
        $layers = [];
        $namespaces = $xml->getNamespaces(true);
        $queries = isset($namespaces['wfs'])
            ? $xml->children($namespaces['wfs'])->Query
            : $xml->Query;
        foreach ($queries as $query) {
            $attrs = $query->attributes();
            if (!empty($attrs['typeName'][0])) {
                $layers[] = self::stripNamespace((string) $attrs['typeName'][0]);
            }
            if (!empty($attrs['typeNames'][0])) {
                foreach (explode(',', (string) $attrs['typeNames'][0]) as $tn) {
                    $layers[] = self::stripNamespace($tn);
                }
            }
        }
        return $layers;
    }
}
