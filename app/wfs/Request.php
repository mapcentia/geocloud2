<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */
namespace app\wfs;

final readonly class Request
{
    public function __construct(
        public string  $operation,
        public string  $version,
        public string  $service,
        public string  $outputFormat,
        public ?array  $typeNames,
        public ?array  $properties,
        public ?array  $featureIds,
        public ?array  $bbox,
        public ?string $resultType,
        public ?string $srsName,
        public ?int    $srs,
        public ?int    $maxFeatures,
        public ?string $timeSlice,
        public ?array  $filter,
        public ?array  $transactionBody,
        public ?string $rawPostBody,
    ) {}

    public static function fromHttp(\app\wfs\Context $ctx, ?string $rawBody = null): self
    {
        $rawBody = $rawBody ?? (string) file_get_contents('php://input');

        if ($rawBody === '') {
            return self::fromGet($ctx);
        }
        return self::fromXmlPost($ctx, $rawBody);
    }

    /** @internal */
    private static function fromGet(\app\wfs\Context $ctx): self
    {
        $h = array_change_key_case($_GET ?? [], CASE_UPPER);
        $typeRaw = $h['TYPENAME'] ?? null;
        $typeNames = $typeRaw ? explode(',', \app\wfs\helpers\NameSpaces::dropAllNameSpaces($typeRaw)) : null;
        $properties = !empty($h['PROPERTYNAME']) ? explode(',', \app\wfs\helpers\NameSpaces::dropAllNameSpaces($h['PROPERTYNAME'])) : null;
        $featureIds = !empty($h['FEATUREID']) ? explode(',', $h['FEATUREID']) : null;
        $bbox = !empty($h['BBOX']) ? explode(',', $h['BBOX']) : null;
        $srsName = $h['SRSNAME'] ?? null;
        $version = $h['VERSION'] ?? '1.1.0';
        $service = $h['SERVICE'] ?? (($h['REQUEST'] ?? null) === 'GetFeature' ? 'WFS' : '');
        $outputFormat = self::normalizeOutputFormat($h['OUTPUTFORMAT'] ?? null, $version);
        $maxFeatures = isset($h['MAXFEATURES']) ? (int) $h['MAXFEATURES'] : null;
        $resultType = $h['RESULTTYPE'] ?? null;
        $epsgStr = $srsName ? \app\inc\WfsFilter::parseEpsgCode($srsName) : null;
        $srs = $epsgStr !== null ? (int) $epsgStr : null;
        $filter = null;
        if (!empty($h['FILTER'])) {
            $filter = self::parseInlineFilter($h['FILTER']);
        }

        return new self(
            operation: strtoupper((string)($h['REQUEST'] ?? '')),
            version: $version,
            service: $service,
            outputFormat: $outputFormat,
            typeNames: $typeNames,
            properties: $properties,
            featureIds: $featureIds,
            bbox: $bbox,
            resultType: $resultType,
            srsName: $srsName,
            srs: $srs,
            maxFeatures: $maxFeatures,
            timeSlice: null,
            filter: $filter,
            transactionBody: null,
            rawPostBody: null,
        );
    }

    private static function normalizeOutputFormat(?string $fmt, string $version): string
    {
        $fmt = $fmt ?: ($version === '1.1.0' ? 'GML3' : 'GML2');
        if (str_contains($fmt, 'gml/3')) $fmt = 'GML3';
        if (strcasecmp($fmt, 'XMLSCHEMA') !== 0
            && strcasecmp($fmt, 'GML2') !== 0
            && strcasecmp($fmt, 'GML3') !== 0
        ) {
            $fmt = 'GML2';
        }
        return strtoupper($fmt);
    }

    /** @internal Stub for Task 8 — XML POST path; here for forward-reference. */
    private static function fromXmlPost(\app\wfs\Context $ctx, string $body): self
    {
        throw new \LogicException('fromXmlPost not yet implemented (Task 8)');
    }

    /** @internal Stub for Task 8. */
    private static function parseInlineFilter(string $xml): array
    {
        throw new \LogicException('parseInlineFilter not yet implemented (Task 8)');
    }
}
