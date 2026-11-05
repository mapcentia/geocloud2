<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

/**
 * How the scheduler reads the GML a WFS hands it (app/scripts/get.php, getCmd
 * and fetchPart). Both GDAL drivers in play, GML and GMLAS, take these.
 */
final class OgrGml
{
    /**
     * Open options for every GML/GMLAS read the scheduler does.
     *
     * SWAP_COORDINATES=NO: take the coordinates in the order the file writes
     * them and do not apply the CRS's authority axis order. It matters for the
     * lat,lon CRSs -- EPSG:4326 and EPSG:4258 -- and only when the WFS spells
     * the srsName the authority-compliant way (urn:ogc:def:crs:EPSG::4326 or
     * http://www.opengis.net/def/crs/EPSG/0/4326). GDAL's default, AUTO, honours
     * the authority order for those two spellings and ignores it for the legacy
     * ones (EPSG:4326, .../gml/srs/epsg.xml#4326), so a WFS that spells the CRS
     * properly but still emits lon,lat -- the common case among the services
     * GC2 imports -- comes in mirrored. Projected CRSs such as EPSG:25832 are
     * easting,northing in every spelling and cannot move.
     *
     * This replaces a perl pass over the downloaded file ("SRS normalizer")
     * that rewrote every srsName to the short EPSG:<code> form. That rewrite
     * was added to stop GDAL trying to dereference the legacy epsg.xml# URL
     * over the network, and was later widened to the urn: and def/crs/ forms,
     * at which point suppressing the axis swap became its real effect. Saying
     * so with the driver's own option is the same behaviour -- verified form by
     * form in GmlAxisOrderTest -- stated where it can be read, and it applies
     * to the unpaged import too, which the perl pass never touched.
     *
     * The network lookup the rewrite originally prevented is no longer a
     * failure: GDAL 3.12 logs "Cannot import ... due to ALLOW_NETWORK_ACCESS=NO"
     * at ERROR level and then reads the code off the end of the URL and gets
     * the CRS right regardless, and Util::ogrLineIsFatal() knows not to fail a
     * job over that line.
     *
     * @var list<string>
     */
    public const array OPEN_OPTIONS = ['SWAP_COORDINATES=NO'];

    /**
     * The same, as ogr2ogr arguments ready to append to a command line.
     *
     * @return list<string>
     */
    public static function openOptionArgs(): array
    {
        return array_map(
            static fn(string $option): string => '-oo ' . escapeshellarg($option),
            self::OPEN_OPTIONS
        );
    }
}
