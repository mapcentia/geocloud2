<?php

namespace app\model;

use app\inc\Model;

class cartomobile extends Model
{
    /**
     *
     *
     * @param unknown $schema
     * @return unknown
     */
    public function getXml($schema)
    {
        global $hostName;
        $tables = new GeometryColumns();
        foreach ($tables->rows as $arr) {
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
            $xml .= "\t\t<WMSServer src='{$hostName}/wms/{$this->postgisdb}/{$this->postgisschema}/'>\n";
            $xml .= "\t\t\t<Layer id='{$baseLayer}'></Layer>\n";
            $xml .= "\t\t</WMSServer>";
            $xml .= "</BaseMapSource>";
        }

        /* Start of writing WMS as overlays */

        /*
     foreach (array_unique($groups) as $group) {
         $xmltmp.="<BaseMapSource supplemental='true'>\n";
         $xmltmp.="\t<Label>{$group} ({$titleStr})</Label>\n";
         $xmltmp.="<Extent srs='EPSG:3857'>
     <LowerCorner>-20037508.34 -20037508.34</LowerCorner>
     <UpperCorner>20037508.34 20037508.34</UpperCorner>
     </Extent>";
         $xmltmp.="\t\t<WMSServer src='{$hostName}/wms/{$this->postgisdb}/{$this->postgisschema}/'>\n";
         $titleArr=array();
         if ($baseLayer){
             $xmltmp.="\t\t\t<Layer id='{$baseLayer}'></Layer>\n";
         }
         foreach ($titles as $layer=>$title) {
             foreach ($tables->rows as $arr) {
                 if ($arr['f_table_name']==$layer) {
                     if (!$arr['layergroup']) {
                         //$arr['layergroup'] = "Default group";
                     }
                     $layerGroup = $arr['layergroup'];
                     if ($layerGroup == $group && ($layerGroup)) {
                         $xmltmp.="\t\t\t<Layer id='{$this->postgisschema}.{$layer}'></Layer>\n";
                         $titleArr[]=$title;
                     }
                 }
             }
         }
         $titleStr = implode(",", $titleArr);
         $xmltmp.="\t\t</WMSServer>";
         $xmltmp.="</BaseMapSource>\n";
         if (sizeof($titleArr)<2) {
             $xmltmp="";
         }
         $xml.=$xmltmp;
         $xmltmp="";
     }
     */
        foreach ($tables->rows as $row) {
            if ($schema == $row['f_table_schema']) {
                //$table = new table("{$row['f_table_schema']}.{$row['f_table_name']}");
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
                $xml .= "\t\t<WMSServer src='{$hostName}/wms/{$this->postgisdb}/{$this->postgisschema}/'>\n";
                if ($baseLayer) {
                    $xml .= "\t\t\t<Layer id='{$baseLayer}'></Layer>\n";
                }
                $xml .= "\t\t\t<Layer id='{$this->postgisschema}.{$row['f_table_name']}'></Layer>\n";
                $xml .= "\t\t</WMSServer>";
                $xml .= "</BaseMapSource>\n";
            }
        }


        /* End of writing WMS as baselayers */
        $xml .= "</BaseMap>\n";

        /* Start of writing WFS as overlays */
        $xml .= "<Overlays>\n";
        foreach ($tables->rows as $row) {
            if ($schema == $row['f_table_schema'] && (!$row['wmssource'])) {
                $table = new table("{$row['f_table_schema']}.{$row['f_table_name']}");
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
                $xml .= "\t\t<WFSLayer src='{$hostName}/wfs/{$this->postgisdb}/{$this->postgisschema}/4326' shapeType='{$geoType}' typeName='{$this->postgisdb}:{$row['f_table_name']}'/>\n";
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
