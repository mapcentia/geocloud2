<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\ogc;

final class Conformance
{
    public const array CLASSES = [
        'http://www.opengis.net/spec/ogcapi-common-1/1.0/conf/core',
        'http://www.opengis.net/spec/ogcapi-common-1/1.0/conf/landing-page',
        'http://www.opengis.net/spec/ogcapi-common-1/1.0/conf/json',
        'http://www.opengis.net/spec/ogcapi-common-2/1.0/conf/collections',
        'http://www.opengis.net/spec/ogcapi-features-1/1.0/conf/core',
        'http://www.opengis.net/spec/ogcapi-features-1/1.0/conf/oas30',
        'http://www.opengis.net/spec/ogcapi-features-1/1.0/conf/geojson',
        'http://www.opengis.net/spec/ogcapi-features-2/1.0/conf/crs',
        'http://www.opengis.net/spec/ogcapi-maps-1/1.0/conf/core',
        'http://www.opengis.net/spec/ogcapi-maps-1/1.0/conf/spatial-subsetting',
        'http://www.opengis.net/spec/ogcapi-maps-1/1.0/conf/crs',
        'http://www.opengis.net/spec/ogcapi-maps-1/1.0/conf/datetime',
        'http://www.opengis.net/spec/ogcapi-maps-1/1.0/conf/png',
        'http://www.opengis.net/spec/ogcapi-maps-1/1.0/conf/jpeg',
        'http://www.opengis.net/spec/ogcapi-maps-1/1.0/conf/collections-selection',
    ];
}
