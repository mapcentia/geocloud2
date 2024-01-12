<?php

use mapcentia\gmlConverter;

/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

class GmlParser extends \app\inc\Model
{
	var $gmlArray;
	var $gmlSource;
	var $gmlCon;
	var $strForSql;
	var $arr;
	var $geomType;
	function __construct($gmlSource)
	{
		parent::__construct();

		include_once("libs/class_xml_check.php");
		$check = new XML_check();
		if($check->check_string($gmlSource)) {
			print("GML is well-formed\n");
			//print("Elements      : ".$check->get_xml_elements());
			//print("Attributes    : ".$check->get_xml_attributes());
			//print("Size          : ".$check->get_xml_size());
			//print("Text sections : ".$check->get_xml_text_sections());
			//print("Text size     : ".$check->get_xml_text_size());
		}
		else {
			print("GML is not well-formed. ");
			print($check->get_full_error()."\n");
			print("Script terminated\n");
			die();
		}

		$this -> gmlSource = $gmlSource;
		$this -> gmlCon = new gmlConverter();
		require_once("XML/Unserializer.php");
		$unserializer_options = array ('parseAttributes' => TRUE);

		$unserializer = new XML_Unserializer($unserializer_options);

		// Serialize the data structure
		$status = $unserializer->unserialize($this -> gmlSource);

		$this -> gmlArray=$unserializer->getUnserializedData();
		print(date('l jS \of F Y h:i:s A')." GML serialized\n");
		// Check if XML is a ServiceException
		if ($unserializer->getRootName()=="ServiceExceptionReport"){
			print("The server returned an exception:\n");
			print($this -> gmlSource."\n");
			print("Script terminated\n");
			die();
		}
	}
	function unserializeGml()
	{
		$wktArr = $this -> gmlCon -> gmlToWKT($this -> gmlSource);
		//print_r($wktArr);
		$allFields = array();
		if ($wktArr[0]){
			ksort($wktArr[0]);
			// Check the geom type of first feature
			$geoObj = Geometryfactory::createGeometry($wktArr[0][0],$wktArr[0][1]);
			$this -> geomType = $geoObj -> getGeomType();

			// If NOT multi feature, set type to multi
			if ($this -> geomType == "POINT") $this -> geomType = "MULTIPOINT";
			if ($this -> geomType == "LINESTRING") $this -> geomType = "MULTILINESTRING";
			if ($this -> geomType == "POLYGON") $this -> geomType = "MULTIPOLYGON";

			if (sizeof($this -> gmlArray['gml:featureMember'])>1)
			{
				foreach ($this -> gmlArray['gml:featureMember'] as $featureMember)
				{
					foreach ($featureMember as $feature)
					{
						foreach ($feature as $field => $value)
						{
							if (!is_array($value))
							{
								$fieldWithOutDomain = preg_replace("/[a-z]*:/","",$field);
								$fields[] = $fieldWithOutDomain;
								$values[] = "'".pg_escape_string($value)."'";

								//Build field array
								if (!in_array($fieldWithOutDomain,$allFields)) $allFields[] = $fieldWithOutDomain;
							}
						}
						$fieldsStr = implode(",",$fields);
						$valuesStr = implode(",",$values);
						$this -> arr['fields'][] = $fieldsStr;
						$this -> arr['values'][] = $valuesStr;
						$this -> arr['geom'][] = current($wktArr[0]);
						$this -> arr['srid'][] = gmlConverter::parseEpsgCode(current($wktArr[1]));

						// Reset vars
						$fields = array();
						$values = array();
						$field = "";
						$value = "";
						$fieldsStr = "";
						$valuesStr = "";

					}
					next($wktArr[0]);
				}
			}
			else
			{
				foreach ($this -> gmlArray['gml:featureMember'] as $featureMember)
				{
					foreach ($featureMember as $field => $value)
					{
						if (!is_array($value))
						{
							$fieldWithOutDomain = preg_replace("/[a-z]*:/","",$field);
							$fields[] = $fieldWithOutDomain;
							$values[] = "'".pg_escape_string($value)."'";

							//Build field array
							if (!in_array($fieldWithOutDomain,$allFields)) $allFields[] = $fieldWithOutDomain;
						}
					}
					$fieldsStr = implode(",",$fields);
					$valuesStr = implode(",",$values);
					$this -> arr['fields'][] = $fieldsStr;
					$this -> arr['values'][] = $valuesStr;
					$this -> arr['geom'][] = current($wktArr[0]);
					$this -> arr['srid'][] = current($wktArr[1]);
				}
			}
			$this -> strForSql = implode(" character varying,",$allFields);

			print(date('l jS \of F Y h:i:s A')." GML geometry converted to WKT geometry (".$this -> geomType.")\n");

		}
		else{
			$this -> arr = false;
		}
	}
}
