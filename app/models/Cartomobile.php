<?php

namespace app\models;

use app\inc\Model;

class Cartomobile extends Model
{
    function __construct()
    {
        parent::__construct();
    }

    public function getXml($schema)
    {
        $hostName = \app\conf\App::$param['host'];
        $postgisdb = $this->postgisdb;
        $postgisschema = $schema;
        $tables = new \app\models\Layer();
        $meta = $tables->getAll(false, \app\inc\Session::isAuth());
        $rows = $meta['data'];
        foreach ($rows as $arr) {
            if ($arr['baselayer'] == "t") {
                $baseLayer = $arr['f_table_schema'] . "." . $arr['f_table_name'];
            }
            if ($schema == $arr['f_table_schema']) {
                if ($arr['f_table_title']) {
                    $titles[$arr['f_table_name']] = $arr['f_table_title'];
                } else {
                    $titles[$arr['f_table_name']] = $arr['f_table_name'];
                }
                if ($arr['layergroup']) {
                    $groups[$arr['f_table_name']] = $arr['layergroup'];
                } else {
                    $groups[$arr['f_table_name']] = "Default group";
                }
            }
        }

        $xml .= "<BaseMap>\n";
        $xml .= "
	<BaseMapSource>
	   <Label>Base Map</Label>
        <Extent srs='EPSG:3857'>
          <LowerCorner>-180 -90</LowerCorner>
          <UpperCorner>180 90</UpperCorner>
        </Extent>
        <DefaultBaseMap/>
	</BaseMapSource>";
        if ($baseLayer) {
            $xml .= "<BaseMapSource>
	    <Label>{$baseLayer}</Label>
	    <Extent srs='EPSG:3857'>
		<LowerCorner>-20037508.34 -20037508.34</LowerCorner>
		<UpperCorner>20037508.34 20037508.34</UpperCorner>
	    </Extent>";
            $xml .= "\t\t<WMSServer src='{$hostName}/wms/{$postgisdb}/{$postgisschema}/'>\n";
            $xml .= "\t\t\t<Layer id='{$baseLayer}'></Layer>\n";
            $xml .= "\t\t</WMSServer>";
            $xml .= "</BaseMapSource>";
        }

        foreach ($rows as $row) {
            if ($schema == $row['f_table_schema']) {
                //$table = new \app\models\Table("{$row['f_table_schema']}.{$row['f_table_name']}");
                $xml .= "<BaseMapSource supplemental='true'>\n";
                if ($row['f_table_title']) {
                    $xml .= "\t<Label>{$row['f_table_title']}</Label>\n";
                } else {
                    $xml .= "\t<Label>{$row['f_table_name']}</Label>\n";
                }
                $xml .= "<Extent srs='EPSG:3857'>
		<LowerCorner>-20037508.34 -20037508.34</LowerCorner>
		<UpperCorner>20037508.34 20037508.34</UpperCorner>
	    </Extent>";
                $xml .= "\t\t<WMSServer src='{$hostName}/wms/{$postgisdb}/{$postgisschema}/'>\n";
                if ($baseLayer) {
                    $xml .= "\t\t\t<Layer id='{$baseLayer}'></Layer>\n";
                }
                $xml .= "\t\t\t<Layer id='{$postgisschema}.{$row['f_table_name']}'></Layer>\n";
                $xml .= "\t\t</WMSServer>";
                $xml .= "</BaseMapSource>\n";
            }
        }


        /* End of writing WMS as baselayers */
        $xml .= "</BaseMap>\n";

        /* Start of writing WFS as overlays */
        $xml .= "<Overlays>\n";
        foreach ($rows as $row) {
            if ($schema == $row['f_table_schema'] && (!$row['wmssource'])) {
                $table = new \app\models\Table("{$row['f_table_schema']}.{$row['f_table_name']}");
                switch ($row['type']) {
                    case "POINT":
                        $geoType = "point";
                        break;
                    case "LINESTRING":
                        $geoType = "line";
                        break;
                    case "POLYGON":
                        $geoType = "polygon";
                        break;
                    case "MULTIPOINT":
                        $geoType = "multipoint";
                        break;
                    case "MULTILINESTRING":
                        $geoType = "multiline";
                        break;
                    case "MULTIPOLYGON":
                        $geoType = "multipolygon";
                        break;
                }
                $xml .= "<DataLayer editable='true' canAdd='true' canDelete='true'>\n";
                $xml .= "\t<DataSource>\n";
                $xml .= "\t\t<WFSLayer src='{$hostName}/wfs/{$postgisdb}/{$postgisschema}/4326' shapeType='{$geoType}' typeName='{$postgisdb}:{$row['f_table_name']}'/>\n";
                $xml .= "\t</DataSource>\n";
                $xml .= "\t<GeometryField property='{$row['f_geometry_column']}'/>\n";
                $xml .= "\t<Form>\n";

                $cartomobileArr = (array)json_decode($row['cartomobile']);
                foreach ($table->metaData as $key => $value) {
                    if ($value['type'] != "geometry" && $key != $table->primeryKey['attname'] && $cartomobileArr[$key]->available == 1) {
                        $xml .= "\t\t<FormField property='{$key}'>\n";
                        $xml .= "\t\t\t<Label>{$key}</Label>\n";
                        if (!$cartomobileArr[$key]->cartomobiletype) {
                            switch ($table->metaData[$key]['type']) {
                                case "int":
                                    $type = "Number";
                                    break;
                                case "number":
                                    $type = "Number";
                                    break;
                                case "string":
                                    $type = "SingleText";
                                    break;
                                case "text":
                                    $type = "TextBox";
                                    break;
                            }
                        } elseif ($cartomobileArr[$key]->cartomobiletype == "PictureURL") {
                            $type = "SingleText";
                        } else {
                            $type = $cartomobileArr[$key]->cartomobiletype;
                        }
                        $xml .= "\t\t\t<{$type} ";
                        switch ($type) {
                            case "ChoiceList":
                                $xml .= ">\n";
                                $arr = (array)json_decode($cartomobileArr[$key]->properties);
                                foreach ($arr as $key => $choice) {
                                    $xml .= "\t\t\t\t<Choice value='{$key}'>{$choice}</Choice>\n";
                                }
                                $xml .= "\t\t\t</ChoiceList>\n";
                                break;
                            case "Picture":
                                $arr = (array)json_decode($cartomobileArr[$key]->properties);
                                foreach ($arr as $key => $value) {
                                    $xml .= "{$key}='{$value}' ";
                                }
                                $xml .= "/>\n";
                                break;
                            default:
                                $xml .= "/>\n";
                                break;
                        }
                        $xml .= "\t\t</FormField>\n";
                    }
                }
                $xml .= "\t</Form>\n";
                //$xml.="\t<PinField property='".$table->primeryKey['attname']."'/>\n";
                if ($row['f_table_title']) {
                    $xml .= "\t<Label>{$row['f_table_title']}</Label>\n";
                } else {
                    $xml .= "\t<Label>{$row['f_table_name']}</Label>\n";
                }
                $xml .= "</DataLayer>\n";
            }
        }
        $xml .= "</Overlays>\n";
        /* End of writing WFS as overlays */

        return $xml;
    }
}
