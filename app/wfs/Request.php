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
}
