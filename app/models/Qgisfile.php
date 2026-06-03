<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\models;

use app\conf\App;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Model;
use Exception;
use SimpleXMLElement;
use Throwable;

class Qgisfile extends Model
{

    /**
     */
    function __construct(?Connection $connection = null)
    {
        parent::__construct(connection: $connection);
    }

    /**
     * Writes a QGIS file to the specified path and generates a WMS URL for the layer.
     *
     * @param string $content The content to be written to the QGIS file.
     * @param string $rel The table or relation name used to generate the file name.
     * @param string $geomField The geometry field used to generate the file name.
     * @return array Returns an array containing the WMS URL and the SRID string.
     */
    public function writeQgisfile(string $content, string $rel, string $geomField): array
    {
        $path = App::$param['path'] . "/app/wms/qgsfiles/";
        $name = $this->connection->database . "_" . $this->connection->schema . "_" . $rel . "_" . $geomField . ".qgs";
        @unlink($path . $name);
        $fh = fopen($path . $name, 'w');
        fwrite($fh, $content);
        fclose($fh);

        $layer = $this->connection->schema . "." . $rel;
        $url = App::$param["mapCache"]["wmsHost"] . "/cgi-bin/qgis_mapserv.fcgi?map=" . $path . $name . "&SERVICE=WMS&VERSION=1.1.1&REQUEST=GetMap&STYLES=&FORMAT=image/png&LAYER=" . $layer . "&transparent=true&";
        return [$url, "EPSG:4326 EPSG:3857 EPSG:25832"];
    }

    /**
     * Retrieves spatial reference system information for the specified SRID and formats it as an XML string.
     *
     * @param int|string $srid The SRID (Spatial Reference System Identifier) for which information is being retrieved.
     * @return string Returns an XML-formatted string containing the spatial reference system details, including WKT, PROJ4, SRID, authority ID, and geographic flag.
     * @throws Throwable
     */
    private function spatialRefSys(int|string $srid): string
    {
        $res = $this->prepare("SELECT srtext, proj4text FROM spatial_ref_sys WHERE srid = :srid");
        $this->execute($res, ["srid" => $srid]);
        $row = $this->fetchRow($res);
        $esc = fn(?string $v): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $v ?? '');
        $srtext = $row['srtext'] ?? '';
        $geographic = (stripos(ltrim($srtext), 'GEOGCS') === 0 || stripos(ltrim($srtext), 'GEOGCRS') === 0) ? 'true' : 'false';
        return '<spatialrefsys nativeFormat="Wkt">'
            . '<wkt>' . $esc($srtext) . '</wkt>'
            . '<proj4>' . $esc($row['proj4text'] ?? '') . '</proj4>'
            . '<srid>' . $srid . '</srid>'
            . '<authid>EPSG:' . $srid . '</authid>'
            . '<geographicflag>' . $geographic . '</geographicflag>'
            . '</spatialrefsys>';
    }

    /**
     * Generates a QGIS project configuration file based on the provided schema, table, and QML content.
     *
     * @param string $qml The QML content used to define layer styling and configuration.
     * @param string $schema The schema name containing the table for which the QGIS project is being generated.
     * @param string $rel The table or relation name used to define the project configuration.
     * @return array Returns an array containing the WMS URL and the SRID string.
     */
    public function project(string $qml, string $schema, string $rel): array
    {
        $pkey = $this->getPrimeryKey("$schema.$rel")['attname'];
        $cols = $this->getColumns($schema, $rel)[0];

        $qgs = '
        <qgis>
          <projectcrs>
            %13$s
          </projectcrs>
          <projectlayers>
            <maplayer type="vector" geometry="%3$s" wkbType="%3$s" labelsEnabled="1" minScale="1000000000">
              <id>%1$s.%2$s</id>
              <datasource>dbname="%4$s" host=%5$s port=%6$s user="%11$s" password="%12$s" key="%7$s" srid=%8$s type=%3$s checkPrimaryKeyUnicity="0" table="%1$s"."%2$s" (%9$s) sql=</datasource>
              <layername>%1$s.%2$s</layername>
              <provider encoding="">postgres</provider>
              <srs>
                %14$s
              </srs>
              <!-- QML -->
              %10$s
            </maplayer>
          </projectlayers>
        </qgis>
        ';
        try {
            $xml = new SimpleXMLElement($qml);
        } catch (Exception $e) {
            throw new GC2Exception($e->getMessage(), 400, null);
        }
        $innerQml = '';
        foreach ($xml->children() as $child) {
            $innerQml .= $child->asXML();
        }
        $type = match ($cols['type']) {
            'POINT', 'MULTIPOINT' => 'Point',
            'POLYGON', 'MULTIPOLYGON' => 'Polygon',
            'LINESTRING', 'MULTILINESTRING' => 'Line',
        };
        $result = sprintf($qgs, $schema, $rel, $type, $this->connection->database, $this->connection->host, $this->connection->port, $pkey, $cols['srid'], $cols['f_geometry_column'], $innerQml, $this->connection->user, $this->connection->password, $this->spatialRefSys("3857"), $this->spatialRefSys($cols['srid']));
        return $this->writeQgisfile($result, $rel, $cols['f_geometry_column']);
    }
}