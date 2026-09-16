<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use DOMDocument;

/**
 * Pure logic for the scheduler's automatic WFS 2.0.0 paging (get.php,
 * getCmdWfsPaging): decides when a job URL should be paged, builds the page
 * and DescribeFeatureType URLs, reads numberMatched/numberReturned off a
 * FeatureCollection and picks a stable sortBy property.
 *
 * A URL is paged only when it is a plain WFS 2.0.0 GetFeature URL: the
 * grid notation ("grid,id|http…") and WFS 1.x keep their existing behaviour,
 * and an explicit startIndex means the caller pages by hand.
 */
final class WfsPaging
{
    public const int DEFAULT_PAGE_SIZE = 10000;

    /** Query keys (lower case) that pageUrl() replaces with canonical values. */
    private const array PAGING_KEYS = ['startindex', 'count', 'sortby'];

    /** Property names that are taken as the row identity, best first. */
    private const array ID_NAMES = ['id', 'fid', 'gid', 'objectid', 'ogc_fid', 'identifier', 'gml_id'];

    private function __construct(
        public readonly string  $typeNames,
        public readonly int     $pageSize,
        public readonly ?string $sortBy,
        private readonly string $baseWithoutQuery,
        /** @var list<string> raw "key=value" pairs of the original query, paging keys removed */
        private readonly array  $otherPairs,
        private readonly string $version,
    )
    {
    }

    /**
     * Returns paging for a plain WFS 2.0.0 GetFeature URL, null for anything
     * else (grid notation, other versions/requests, explicit startIndex).
     */
    public static function detect(string $url): ?self
    {
        if (count(explode('|http', $url)) > 1) {
            return null;
        }
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host']) || empty($parts['query'])) {
            return null;
        }
        $pairs = explode('&', $parts['query']);
        $params = [];
        foreach ($pairs as $pair) {
            if ($pair === '') {
                continue;
            }
            [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
            $params[strtolower(urldecode($k))] = urldecode($v);
        }
        if (strtolower($params['service'] ?? '') !== 'wfs'
            || strtolower($params['request'] ?? '') !== 'getfeature'
            || ($params['version'] ?? '') !== '2.0.0'
            || isset($params['startindex'])) {
            return null;
        }
        $typeNames = $params['typenames'] ?? $params['typename'] ?? '';
        if ($typeNames === '') {
            return null;
        }
        $pageSize = (int)($params['count'] ?? 0);
        if ($pageSize <= 0) {
            $pageSize = self::DEFAULT_PAGE_SIZE;
        }
        $sortBy = isset($params['sortby']) && $params['sortby'] !== '' ? $params['sortby'] : null;

        $base = $parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . ($parts['path'] ?? '');
        $other = array_values(array_filter($pairs, function (string $pair) {
            if ($pair === '') {
                return false;
            }
            $k = strtolower(urldecode(explode('=', $pair, 2)[0]));
            return !in_array($k, self::PAGING_KEYS, true);
        }));
        return new self($typeNames, $pageSize, $sortBy, $base, $other, $params['version']);
    }

    public function withSortBy(?string $sortBy): self
    {
        return new self($this->typeNames, $this->pageSize, $sortBy, $this->baseWithoutQuery, $this->otherPairs, $this->version);
    }

    /**
     * The GetFeature URL for one page: the original parameters (minus any
     * startIndex/count/sortBy) plus canonical paging parameters.
     */
    public function pageUrl(int $startIndex): string
    {
        $pairs = $this->otherPairs;
        $pairs[] = 'startIndex=' . $startIndex;
        $pairs[] = 'count=' . $this->pageSize;
        if ($this->sortBy !== null) {
            $pairs[] = 'sortBy=' . rawurlencode($this->sortBy);
        }
        return $this->baseWithoutQuery . '?' . implode('&', $pairs);
    }

    /** DescribeFeatureType for the job's type names, used to pick a sortBy property. */
    public function describeFeatureTypeUrl(): string
    {
        return $this->baseWithoutQuery . '?' . http_build_query([
                'service' => 'WFS',
                'version' => $this->version,
                'request' => 'DescribeFeatureType',
                'typeNames' => $this->typeNames,
            ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * numberMatched / numberReturned from the start of a WFS 2.0 response.
     * Non-numeric values (e.g. numberMatched="unknown") and missing
     * attributes come back as null.
     *
     * @return array{matched: ?int, returned: ?int}
     */
    public static function parseCounts(string $head): array
    {
        $read = function (string $attr) use ($head): ?int {
            if (preg_match('/\b' . $attr . '\s*=\s*"(\d+)"/', $head, $m)) {
                return (int)$m[1];
            }
            return null;
        };
        return ['matched' => $read('numberMatched'), 'returned' => $read('numberReturned')];
    }

    /**
     * Whether the page that started at $startIndex was the last one. Without a
     * numberReturned the loop cannot tell whether more exists, so it stops.
     */
    public static function isLastPage(int $startIndex, ?int $returned, ?int $matched, int $pageSize): bool
    {
        if ($returned === null || $returned === 0 || $returned < $pageSize) {
            return true;
        }
        return $matched !== null && $startIndex + $returned >= $matched;
    }

    /**
     * Picks a property to sort by from a DescribeFeatureType response: the
     * first element (document order) with an exact identity name, else the
     * first whose name ends in "_id". Null when nothing looks like an id or
     * the document does not parse.
     */
    public static function pickSortProperty(string $xsd): ?string
    {
        if (trim($xsd) === '') {
            return null;
        }
        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $doc->loadXML($xsd);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            return null;
        }
        $names = [];
        foreach ($doc->getElementsByTagNameNS('http://www.w3.org/2001/XMLSchema', 'element') as $el) {
            $name = $el->getAttribute('name');
            if ($name !== '') {
                $names[] = $name;
            }
        }
        foreach ($names as $name) {
            if (in_array(strtolower($name), self::ID_NAMES, true)) {
                return $name;
            }
        }
        foreach ($names as $name) {
            if (str_ends_with(strtolower($name), '_id')) {
                return $name;
            }
        }
        return null;
    }
}
