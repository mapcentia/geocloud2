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

    private static function fromXmlPost(\app\wfs\Context $ctx, string $body): self
    {
        // Legacy unserializer-based parsing (mirrors server.php lines 130-191)
        set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__DIR__) . '/libs/PEAR');
        require_once dirname(__DIR__) . '/libs/PEAR/XML/Unserializer.php';

        // HACK from legacy: MapInfo 15 sends invalid XML
        $clean = \app\wfs\helpers\NameSpaces::dropNameSpace($body);
        $clean = str_replace(["\\n", 'xmlns:wfs="http://www.opengis.net/wfs"'], [' ', ' '], $clean);

        $u = new \XML_Unserializer(['parseAttributes' => true, 'contentName' => '_content']);
        $u->unserialize($clean);
        $arr = $u->getUnserializedData();

        $version = $arr['version'] ?? '1.1.0';
        $service = $arr['service'] ?? 'WFS';
        $maxFeatures = isset($arr['maxFeatures']) ? (int) $arr['maxFeatures'] : null;
        $resultType = $arr['resultType'] ?? null;
        $outputFormat = self::normalizeOutputFormat($arr['outputFormat'] ?? null, $version);

        $rootName = strtoupper($u->getRootName());
        $typeNamesStr = '';
        $propertiesStr = '';
        $filter = null;
        $transactionBody = null;
        $srsName = null;

        switch ($rootName) {
            case 'GETFEATURE':
                $queries = $arr['Query'] ?? [];
                if (!isset($queries[0])) $queries = [$queries];
                foreach ($queries as $q) {
                    $srsName = $q['srsName'] ?? $srsName;
                    $tn = \app\wfs\helpers\NameSpaces::dropAllNameSpaces($q['typeName']);
                    $typeNamesStr .= $tn . ',';
                    $propsRaw = $q['PropertyName'] ?? null;
                    if ($propsRaw !== null) {
                        if (!is_array($propsRaw) || !isset($propsRaw[0])) {
                            $propsRaw = [$propsRaw];
                        }
                        foreach ($propsRaw as $p) {
                            $propertiesStr .= (str_contains($p, '.') ? $p : "$tn.$p") . ',';
                        }
                    }
                    if (isset($q['Filter']) && is_array($q['Filter'])) {
                        $filter = $q['Filter'];
                    }
                }
                $operation = 'GETFEATURE';
                break;
            case 'DESCRIBEFEATURETYPE':
                $typeNamesStr = (string)($arr['TypeName'] ?? '');
                $operation = 'DESCRIBEFEATURETYPE';
                break;
            case 'GETCAPABILITIES':
                $operation = 'GETCAPABILITIES';
                break;
            case 'TRANSACTION':
                $operation = 'TRANSACTION';
                $transactionBody = $arr;   // Insert/Update/Delete keys consumed by handler
                break;
            default:
                $operation = '';
        }

        $typeNames = $typeNamesStr ? explode(',', rtrim($typeNamesStr, ',')) : null;
        $properties = $propertiesStr ? explode(',', rtrim($propertiesStr, ',')) : null;
        // WfsFilter::parseEpsgCode returns ?string; Request::$srs is ?int — cast.
        $epsgStr = $srsName ? \app\inc\WfsFilter::parseEpsgCode($srsName) : null;
        $srs = $epsgStr !== null ? (int) $epsgStr : null;

        return new self(
            operation: $operation,
            version: $version,
            service: $service,
            outputFormat: $outputFormat,
            typeNames: $typeNames,
            properties: $properties,
            featureIds: null,
            bbox: null,
            resultType: $resultType,
            srsName: $srsName,
            srs: $srs,
            maxFeatures: $maxFeatures,
            timeSlice: null,
            filter: $filter,
            transactionBody: $transactionBody,
            rawPostBody: $body,
        );
    }

    private static function parseInlineFilter(string $xml): array
    {
        set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__DIR__) . '/libs/PEAR');
        require_once dirname(__DIR__) . '/libs/PEAR/XML/Unserializer.php';
        $u = new \XML_Unserializer(['parseAttributes' => true, 'contentName' => '_content']);
        $u->unserialize(\app\wfs\helpers\NameSpaces::dropNameSpace($xml));
        return $u->getUnserializedData();
    }
}
