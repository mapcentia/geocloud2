<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\models;

use app\conf\App;
use app\conf\Connection;
use app\exceptions\GC2Exception;
use app\inc\Model;
use app\models\Setting;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;

class Qgisfile extends Model
{

    /**
     * @throws PhpfastcacheInvalidArgumentException|InvalidArgumentException
     */
    function __construct(?\app\inc\Connection $connection = null,
                         private \app\models\Table $table = new Table("settings.geometry_columns_join"),
                         private \app\models\Layer $layer = new Layer(),
                         private string $sridStr = "EPSG:4326 EPSG:3857 EPSG:25832"
    )
    {
        parent::__construct(connection: $connection);
    }

    /**
     * @throws GC2Exception
     * @throws InvalidArgumentException
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
        $layerKey = $layer . "." . $geomField;

        $url = App::$param["mapCache"]["wmsHost"] . "/cgi-bin/qgis_mapserv.fcgi?map=" . $path . $name . "&SERVICE=WMS&VERSION=1.1.1&REQUEST=GetMap&STYLES=&FORMAT=image/png&LAYER=" . $layer . "&transparent=true&";
        $data['_key_'] = $layerKey;
        $data['wmssource'] = $url;
        $data['wmsclientepsgs'] = $this->sridStr;
        $this->table->updateRecord($data, "_key_");

        return ["success" => true, "message" => "Qgisfile written", "ch" => $path . $name];
    }

    /**
     * Build a complete QGIS <spatialrefsys> block from PostGIS spatial_ref_sys.
     *
     * A minimal block containing only <srid>/<authid> does NOT resolve to a valid CRS in
     * QGIS Server: it then treats the layer as already being in the project CRS and skips
     * reprojection, so features render at raw coordinate positions (geographically wrong).
     * Supplying the WKT + proj4 definition lets QGIS reproject the layer to the requested SRS.
     *
     * @throws GC2Exception
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

    public function project($extent = null) {

        $qgs = '
        <qgis version="3.44.7-Solothurn">
          <extent>
            <xmin>%11$f</xmin>
            <ymin>%12$f</ymin>
            <xmax>%13$f</xmax>
            <ymax>%14$f</ymax>
          </extent>
          <projectcrs>
            %17$s
          </projectcrs>
          <projectlayers>
            <maplayer type="vector" geometry="%3$s" wkbType="%3$s" labelsEnabled="0" minScale="100000000">
              <extent>
                <xmin>%11$f</xmin>
                <ymin>%12$f</ymin>
                <xmax>%13$f</xmax>
                <ymax>%14$f</ymax>
              </extent>
              <id>%1$s.%2$s</id>
              <datasource>dbname="%4$s" host=%5$s port=%6$s user="%15$s" password="%16$s" key="%7$s" srid=%8$s type=%3$s checkPrimaryKeyUnicity="0" table="%1$s"."%2$s" (%9$s)</datasource>
              <layername>%1$s.%2$s</layername>
              <provider encoding="">postgres</provider>
              <srs>
                %18$s
              </srs>
              <!-- QML -->
              %10$s
            </maplayer>
          </projectlayers>
        </qgis>
        ';

        $qml = '<flags>
    <Identifiable>1</Identifiable>
    <Removable>1</Removable>
    <Searchable>1</Searchable>
    <Private>0</Private>
  </flags>
  <temporal endExpression="" endField="" durationField="gid" accumulate="0" limitMode="0" startField="" fixedDuration="0" mode="0" enabled="0" durationUnit="min" startExpression="">
    <fixedRange>
      <start></start>
      <end></end>
    </fixedRange>
  </temporal>
  <elevation respectLayerSymbol="1" showMarkerSymbolInSurfacePlots="0" extrusion="0" clamping="Terrain" customToleranceEnabled="1" zscale="1" binding="Centroid" symbology="Line" zoffset="0" extrusionEnabled="0" type="IndividualFeatures">
    <data-defined-properties>
      <Option type="Map">
        <Option name="name" value="" type="QString"/>
        <Option name="properties"/>
        <Option name="type" value="collection" type="QString"/>
      </Option>
    </data-defined-properties>
    <profileLineSymbol>
      <symbol force_rhr="0" frame_rate="10" clip_to_extent="1" name="" alpha="1" is_animated="0" type="line">
        <data_defined_properties>
          <Option type="Map">
            <Option name="name" value="" type="QString"/>
            <Option name="properties"/>
            <Option name="type" value="collection" type="QString"/>
          </Option>
        </data_defined_properties>
        <layer pass="0" locked="0" class="SimpleLine" enabled="1" id="{fb291c3f-c972-4e64-85be-571504fe2b9d}">
          <Option type="Map">
            <Option name="align_dash_pattern" value="0" type="QString"/>
            <Option name="capstyle" value="square" type="QString"/>
            <Option name="customdash" value="5;2" type="QString"/>
            <Option name="customdash_map_unit_scale" value="3x:0,0,0,0,0,0" type="QString"/>
            <Option name="customdash_unit" value="MM" type="QString"/>
            <Option name="dash_pattern_offset" value="0" type="QString"/>
            <Option name="dash_pattern_offset_map_unit_scale" value="3x:0,0,0,0,0,0" type="QString"/>
            <Option name="dash_pattern_offset_unit" value="MM" type="QString"/>
            <Option name="draw_inside_polygon" value="0" type="QString"/>
            <Option name="joinstyle" value="bevel" type="QString"/>
            <Option name="line_color" value="114,155,111,255,rgb:0.4470588,0.6078431,0.4352941,1" type="QString"/>
            <Option name="line_style" value="solid" type="QString"/>
            <Option name="line_width" value="0.6" type="QString"/>
            <Option name="line_width_unit" value="MM" type="QString"/>
            <Option name="offset" value="0" type="QString"/>
            <Option name="offset_map_unit_scale" value="3x:0,0,0,0,0,0" type="QString"/>
            <Option name="offset_unit" value="MM" type="QString"/>
            <Option name="ring_filter" value="0" type="QString"/>
            <Option name="trim_distance_end" value="0" type="QString"/>
            <Option name="trim_distance_end_map_unit_scale" value="3x:0,0,0,0,0,0" type="QString"/>
            <Option name="trim_distance_end_unit" value="MM" type="QString"/>
            <Option name="trim_distance_start" value="0" type="QString"/>
            <Option name="trim_distance_start_map_unit_scale" value="3x:0,0,0,0,0,0" type="QString"/>
            <Option name="trim_distance_start_unit" value="MM" type="QString"/>
            <Option name="tweak_dash_pattern_on_corners" value="0" type="QString"/>
            <Option name="use_custom_dash" value="0" type="QString"/>
            <Option name="width_map_unit_scale" value="3x:0,0,0,0,0,0" type="QString"/>
          </Option>
          <data_defined_properties>
            <Option type="Map">
              <Option name="name" value="" type="QString"/>
              <Option name="properties"/>
              <Option name="type" value="collection" type="QString"/>
            </Option>
          </data_defined_properties>
        </layer>
      </symbol>
    </profileLineSymbol>
    <profileFillSymbol>
      <symbol force_rhr="0" frame_rate="10" clip_to_extent="1" name="" alpha="1" is_animated="0" type="fill">
        <data_defined_properties>
          <Option type="Map">
            <Option name="name" value="" type="QString"/>
            <Option name="properties"/>
            <Option name="type" value="collection" type="QString"/>
          </Option>
        </data_defined_properties>
        <layer pass="0" locked="0" class="SimpleFill" enabled="1" id="{d1cee661-1f33-4f8a-a4c5-791e293b0283}">
          <Option type="Map">
            <Option name="border_width_map_unit_scale" value="3x:0,0,0,0,0,0" type="QString"/>
            <Option name="color" value="114,155,111,255,rgb:0.4470588,0.6078431,0.4352941,1" type="QString"/>
            <Option name="joinstyle" value="bevel" type="QString"/>
            <Option name="offset" value="0,0" type="QString"/>
            <Option name="offset_map_unit_scale" value="3x:0,0,0,0,0,0" type="QString"/>
            <Option name="offset_unit" value="MM" type="QString"/>
            <Option name="outline_color" value="81,111,79,255,rgb:0.3193256,0.434165,0.3109178,1" type="QString"/>
            <Option name="outline_style" value="solid" type="QString"/>
            <Option name="outline_width" value="0.2" type="QString"/>
            <Option name="outline_width_unit" value="MM" type="QString"/>
            <Option name="style" value="solid" type="QString"/>
          </Option>
          <data_defined_properties>
            <Option type="Map">
              <Option name="name" value="" type="QString"/>
              <Option name="properties"/>
              <Option name="type" value="collection" type="QString"/>
            </Option>
          </data_defined_properties>
        </layer>
      </symbol>
    </profileFillSymbol>
    <profileMarkerSymbol>
      <symbol force_rhr="0" frame_rate="10" clip_to_extent="1" name="" alpha="1" is_animated="0" type="marker">
        <data_defined_properties>
          <Option type="Map">
            <Option name="name" value="" type="QString"/>
            <Option name="properties"/>
            <Option name="type" value="collection" type="QString"/>
          </Option>
        </data_defined_properties>
        <layer pass="0" locked="0" class="SimpleMarker" enabled="1" id="{cdabf750-0c41-41be-a99b-6a937218ef0b}">
          <Option type="Map">
            <Option name="angle" value="0" type="QString"/>
            <Option name="cap_style" value="square" type="QString"/>
            <Option name="color" value="114,155,111,255,rgb:0.4470588,0.6078431,0.4352941,1" type="QString"/>
            <Option name="horizontal_anchor_point" value="1" type="QString"/>
            <Option name="joinstyle" value="bevel" type="QString"/>
            <Option name="name" value="diamond" type="QString"/>
            <Option name="offset" value="0,0" type="QString"/>
            <Option name="offset_map_unit_scale" value="3x:0,0,0,0,0,0" type="QString"/>
            <Option name="offset_unit" value="MM" type="QString"/>
            <Option name="outline_color" value="81,111,79,255,rgb:0.3193256,0.434165,0.3109178,1" type="QString"/>
            <Option name="outline_style" value="solid" type="QString"/>
            <Option name="outline_width" value="0.2" type="QString"/>
            <Option name="outline_width_map_unit_scale" value="3x:0,0,0,0,0,0" type="QString"/>
            <Option name="outline_width_unit" value="MM" type="QString"/>
            <Option name="scale_method" value="diameter" type="QString"/>
            <Option name="size" value="3" type="QString"/>
            <Option name="size_map_unit_scale" value="3x:0,0,0,0,0,0" type="QString"/>
            <Option name="size_unit" value="MM" type="QString"/>
            <Option name="vertical_anchor_point" value="1" type="QString"/>
          </Option>
          <data_defined_properties>
            <Option type="Map">
              <Option name="name" value="" type="QString"/>
              <Option name="properties"/>
              <Option name="type" value="collection" type="QString"/>
            </Option>
          </data_defined_properties>
        </layer>
      </symbol>
    </profileMarkerSymbol>
  </elevation>
  <renderer-v2 forceraster="0" enableorderby="0" symbollevels="0" type="singleSymbol" referencescale="-1">
    <symbols>
      <symbol force_rhr="0" frame_rate="10" clip_to_extent="1" name="0" alpha="1" is_animated="0" type="fill">
        <data_defined_properties>
          <Option type="Map">
            <Option name="name" value="" type="QString"/>
            <Option name="properties"/>
            <Option name="type" value="collection" type="QString"/>
          </Option>
        </data_defined_properties>
        <layer pass="0" locked="0" class="SimpleFill" enabled="1" id="{6d0c4fbf-0d22-44c4-b8f7-354daf1ea6a0}">
          <Option type="Map">
            <Option name="border_width_map_unit_scale" value="3x:0,0,0,0,0,0" type="QString"/>
            <Option name="color" value="243,166,178,255,rgb:0.9529412,0.6509804,0.6980392,1" type="QString"/>
            <Option name="joinstyle" value="bevel" type="QString"/>
            <Option name="offset" value="0,0" type="QString"/>
            <Option name="offset_map_unit_scale" value="3x:0,0,0,0,0,0" type="QString"/>
            <Option name="offset_unit" value="MM" type="QString"/>
            <Option name="outline_color" value="35,35,35,255,rgb:0.1372549,0.1372549,0.1372549,1" type="QString"/>
            <Option name="outline_style" value="solid" type="QString"/>
            <Option name="outline_width" value="0.26" type="QString"/>
            <Option name="outline_width_unit" value="MM" type="QString"/>
            <Option name="style" value="solid" type="QString"/>
          </Option>
          <data_defined_properties>
            <Option type="Map">
              <Option name="name" value="" type="QString"/>
              <Option name="properties"/>
              <Option name="type" value="collection" type="QString"/>
            </Option>
          </data_defined_properties>
        </layer>
      </symbol>
    </symbols>
    <rotation/>
    <sizescale/>
    <data-defined-properties>
      <Option type="Map">
        <Option name="name" value="" type="QString"/>
        <Option name="properties"/>
        <Option name="type" value="collection" type="QString"/>
      </Option>
    </data-defined-properties>
  </renderer-v2>
  <selection mode="Default">
    <selectionColor invalid="1"/>
    <selectionSymbol>
      <symbol force_rhr="0" frame_rate="10" clip_to_extent="1" name="" alpha="1" is_animated="0" type="fill">
        <data_defined_properties>
          <Option type="Map">
            <Option name="name" value="" type="QString"/>
            <Option name="properties"/>
            <Option name="type" value="collection" type="QString"/>
          </Option>
        </data_defined_properties>
        <layer pass="0" locked="0" class="SimpleFill" enabled="1" id="{a6c3f1f7-dfcd-4a1b-9f32-be57438fd197}">
          <Option type="Map">
            <Option name="border_width_map_unit_scale" value="3x:0,0,0,0,0,0" type="QString"/>
            <Option name="color" value="0,0,255,255,rgb:0,0,1,1" type="QString"/>
            <Option name="joinstyle" value="bevel" type="QString"/>
            <Option name="offset" value="0,0" type="QString"/>
            <Option name="offset_map_unit_scale" value="3x:0,0,0,0,0,0" type="QString"/>
            <Option name="offset_unit" value="MM" type="QString"/>
            <Option name="outline_color" value="35,35,35,255,rgb:0.1372549,0.1372549,0.1372549,1" type="QString"/>
            <Option name="outline_style" value="solid" type="QString"/>
            <Option name="outline_width" value="0.26" type="QString"/>
            <Option name="outline_width_unit" value="MM" type="QString"/>
            <Option name="style" value="solid" type="QString"/>
          </Option>
          <data_defined_properties>
            <Option type="Map">
              <Option name="name" value="" type="QString"/>
              <Option name="properties"/>
              <Option name="type" value="collection" type="QString"/>
            </Option>
          </data_defined_properties>
        </layer>
      </symbol>
    </selectionSymbol>
  </selection>
  <customproperties>
    <Option type="Map">
      <Option name="embeddedWidgets/count" value="0" type="int"/>
      <Option name="variableNames"/>
      <Option name="variableValues"/>
    </Option>
  </customproperties>
  <blendMode>0</blendMode>
  <featureBlendMode>0</featureBlendMode>
  <layerOpacity>1</layerOpacity>
  <geometryOptions geometryPrecision="0" removeDuplicateNodes="0">
    <activeChecks type="StringList">
      <Option value="" type="QString"/>
    </activeChecks>
    <checkConfiguration/>
  </geometryOptions>
  <legend showLabelLegend="0" type="default-vector"/>
  <referencedLayers/>
  <referencingLayers/>
  <fieldConfiguration>
    <field name="gid" configurationFlags="NoFlag">
      <editWidget type="Range">
        <config>
          <Option/>
        </config>
      </editWidget>
    </field>
    <field name="id" configurationFlags="NoFlag">
      <editWidget type="Range">
        <config>
          <Option/>
        </config>
      </editWidget>
    </field>
  </fieldConfiguration>
  <aliases>
    <alias field="gid" name="" index="0"/>
    <alias field="id" name="" index="1"/>
  </aliases>
  <defaults>
    <default field="gid" applyOnUpdate="0" expression=""/>
    <default field="id" applyOnUpdate="0" expression=""/>
  </defaults>
  <constraints>
    <constraint constraints="0" field="gid" notnull_strength="0" exp_strength="0" unique_strength="0"/>
    <constraint constraints="0" field="id" notnull_strength="0" exp_strength="0" unique_strength="0"/>
  </constraints>
  <constraintExpressions>
    <constraint field="gid" exp="" desc=""/>
    <constraint field="id" exp="" desc=""/>
  </constraintExpressions>
  <expressionfields/>
  <attributeactions>
    <defaultAction key="Canvas" value="{00000000-0000-0000-0000-000000000000}"/>
  </attributeactions>
  <attributetableconfig sortExpression="" actionWidgetStyle="dropDown" sortOrder="0">
    <columns>
      <column name="gid" width="-1" hidden="0" type="field"/>
      <column name="id" width="-1" hidden="0" type="field"/>
      <column width="-1" hidden="1" type="actions"/>
    </columns>
  </attributetableconfig>
  <conditionalstyles>
    <rowstyles/>
    <fieldstyles/>
  </conditionalstyles>
  <storedexpressions/>
  <editform tolerant="1"></editform>
  <editforminit/>
  <editforminitcodesource>0</editforminitcodesource>
  <editforminitfilepath></editforminitfilepath>
  <editforminitcode><![CDATA[# -*- coding: utf-8 -*-
"""
QGIS forms can have a Python function that is called when the form is
opened.

Use this function to add extra logic to your forms.

Enter the name of the function in the "Python Init function"
field.
An example follows:
"""
from qgis.PyQt.QtWidgets import QWidget

def my_form_open(dialog, layer, feature):
    geom = feature.geometry()
    control = dialog.findChild(QWidget, "MyLineEdit")
]]></editforminitcode>
  <featformsuppress>0</featformsuppress>
  <editorlayout>generatedlayout</editorlayout>
  <editable>
    <field editable="1" name="gid"/>
    <field editable="1" name="id"/>
  </editable>
  <labelOnTop>
    <field name="gid" labelOnTop="0"/>
    <field name="id" labelOnTop="0"/>
  </labelOnTop>
  <reuseLastValue>
    <field name="gid" reuseLastValue="0"/>
    <field name="id" reuseLastValue="0"/>
  </reuseLastValue>
  <dataDefinedFieldProperties/>
  <widgets/>
  <previewExpression>"gid"</previewExpression>
  <mapTip enabled="1"></mapTip>
  <layerGeometryType>2</layerGeometryType>';

        if (!$extent) {
            $layer = new Layer();
            $extentResponse = $layer->getEstExtent("public.polygon.the_geom", "25832");
            $extent = $extentResponse['extent'];
        }

        $result = sprintf($qgs, "public", "polygon", "Polygon", $this->connection->database, Connection::$param['postgishost'], Connection::$param['postgisport'], "gid", "25832", "the_geom", $qml, $extent['xmin'], $extent['ymin'], $extent['xmax'], $extent['ymax'], Connection::$param['postgisuser'], Connection::$param['postgispw'], $this->spatialRefSys("3857"), $this->spatialRefSys("25832"));

        $this->writeQgisfile($result, "polygon", "the_geom");

    }
}