<?php
class GmlParser extends postgis
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
		$this -> gmlCon = new gmlConverter;
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
			$geoObj = geometryfactory::createGeometry($wktArr[0][0],$wktArr[0][1]);
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
						$this -> arr['srid'][] = current($wktArr[1]);

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
	function loadInDB($tableName)
	{

		if ($this -> arr) {
			//print_r($this->arr);
			//First we try to drop table
			$dropSql = "DROP TABLE ".$tableName." CASCADE";

			//When we try to delete row from geometry_columns
			$deleteFromGeometryColumns = "DELETE FROM geometry_columns WHERE f_table_schema='".$this->postgisschema."' AND f_table_name='".$tableName."'";

			//When we insert new row in geometry_columns
			$sqlInsert = "INSERT INTO geometry_columns VALUES ('', '".$this->postgisschema."', '".$tableName."', 'the_geom', 2, {$this->arr['srid'][0]}, '".$this -> geomType."')";

			//Last we create the new table.Must use schema prefix cos search path include public
			$createSql = "
			CREATE TABLE ".$this->postgisschema.".".$tableName." (
			gid serial NOT NULL,
			".$this -> strForSql."  character varying,
			the_geom geometry,
			CONSTRAINT \"$1\" CHECK ((srid(the_geom) = {$this->arr['srid'][0]})),
			CONSTRAINT \"$2\" CHECK (((geometrytype(the_geom) = '".$this -> geomType."'::text) OR (the_geom IS NULL)))
			);";
			
			//echo $createSql."\n";

			// Check if table is already created
			$checkSql = "select * FROM ".$tableName;
			$check = $this -> execQuery($checkSql);
			$this->PDOerror = NULL;

			// Start of transactions block
			$this -> execQuery(BEGIN);

			if ($check) // True and we drop the table
			{
				$result = $this -> execQuery($dropSql);
				$this -> free($result);
				//echo $tableName." dropped\n";
			}

			$result = $this -> execQuery($deleteFromGeometryColumns);
			$this -> free($result);

			$result = $this -> execQuery($sqlInsert);
			$this -> free($result);

			$result = $this -> execQuery($createSql);
			$this -> free($result);

			//echo "\n";
			$countRows = 0;

			for($i=0;$i<sizeof($this -> arr['fields']);$i++)
			{
				$geoObj = geometryfactory::createGeometry($this -> arr['geom'][$i],$this -> arr['srid'][$i]);
				if ($geoObj)
				{
					if ($geoObj -> getGeomType() == "POLYGON" || $geoObj -> getGeomType() == "LINESTRING" || $geoObj -> getGeomType() == "POINT")
					{
						$this -> arr['geom'][$i] = $geoObj -> getAsMulti();
					}
				}
				$sqlInsert = "insert into ".$tableName." (".$this -> arr['fields'][$i].",the_geom) values(".$this -> arr['values'][$i].",geomFromText('".$this -> arr['geom'][$i]."',".$this -> arr['srid'][$i]."))";
				//echo $sqlInsert;
				// Check if feature has geometry
				if ($this -> arr['geom'][$i]!="()")
				{
					$result = $this -> execQuery($sqlInsert);
					if (!$this->PDOerror)
					{
						$countRows++;
						$this -> free($result);
					}
					else {
					    print_r($this->PDOerror);
						print("Error in #".$i."\n");
						print("ROLLBACK\n");
						print($sqlInsert."\n");
						$this -> execQuery(ROLLBACK);
						print("Script terminated\n");
						die();
					}
				}
				else {
					print("#. ".$i." missing geometry.\n");
					print("ROLLBACK\n");
					$this -> execQuery(ROLLBACK);
					print("Script terminated\n");
					die();
					}

				//echo ".";
			}
			$this -> execQuery(COMMIT); // End of transactions block
		}
		else {
			$sql = "DELETE FROM ".$tableName;
			$result = $this -> execQuery($sql);
			$this -> free($result);
			$countRows = "0";
		}
		print(date('l jS \of F Y h:i:s A')." ".$countRows." features loaded in table '".$tableName."'\n");
	}
}