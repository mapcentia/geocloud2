<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */
namespace app\wfs\handlers;

use app\exceptions\OwsException;
use app\wfs\Context;
use app\wfs\Request;
use app\wfs\output\GmlWriter;
use PDOException;

final class DescribeFeatureType implements HandlerInterface
{
    public function __construct(private readonly Context $ctx) {}

    /**
     * @throws OwsException
     */
    public function handle(Request $req, GmlWriter $writer): void
    {
        $server          = $this->ctx->host;
        $gmlNameSpace    = $writer->gmlNameSpace;
        $gmlNameSpaceUri = $writer->gmlNameSpaceUri;
        $postgisschema   = $this->ctx->schema;
        $version         = $req->version;
        $postgisObject   = $this->ctx->model();

        $writer->write("<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");

        $atts = [];
        $atts["xmlns:xsd"] = "http://www.w3.org/2001/XMLSchema";
        $atts["xmlns:gml"] = "http://www.opengis.net/gml";
        $atts["xmlns:gc2"] = "http://www.mapcentia.com/gc2";
        $atts["xmlns:{$gmlNameSpace}"] = $gmlNameSpaceUri;
        $atts["elementFormDefault"] = "qualified";
        $atts["targetNamespace"] = $gmlNameSpaceUri;
        $atts["version"] = $version;
        $writer->writeTag("open", "xsd", "schema", $atts, false);
        $atts = [];

        $atts["namespace"] = "http://www.opengis.net/gml";
        $atts["schemaLocation"] = "http://schemas.opengis.net/gml/" . ($version == "1.1.0" ? "3.1.1/base" : "2.1.2") . "/feature.xsd";
        $writer->writeTag("selfclose", "xsd", "import", $atts, false);
        $atts["namespace"] = "http://www.mapcentia.com/gc2";
        $atts["schemaLocation"] = $server . "/xmlschemas/gc2.xsd";
        $writer->writeTag("selfclose", "xsd", "import", $atts, false);
        $atts = [];

        // Resolve type names: if empty, fetch all tables from geometry_columns for this schema
        $typeNames = $req->typeNames;
        if (empty($typeNames)) {
            $typeNames = [];
            $sql = "SELECT f_table_name,f_geometry_column,srid FROM public.geometry_columns WHERE f_table_schema='{$postgisschema}'";
            try {
                $result = $postgisObject->execQuery($sql);
            } catch (PDOException) {
                throw new OwsException("Relation doesn't exist", attributes: ["exceptionCode" => "InvalidParameterValue"]);
            }
            while ($row = $postgisObject->fetchRow($result)) {
                $typeNames[] = $row['f_table_name'];
            }
        }

        $cache = [];
        foreach ($typeNames as $table) {
            if (in_array($table, $cache)) {
                continue;
            }
            $cache[] = $table;

            $tableObj = new \app\models\Table($postgisschema . "." . $table, connection: $this->ctx->connection);
            $primeryKey = $tableObj->primaryKey;

            $simpleType = false;

            $fieldsArr = [];
            foreach ($tableObj->metaData as $key => $value) {
                $fieldsArr[] = $key;
            }
            $fields = implode(",", $fieldsArr);
            $sql = "SELECT '{$fields}' FROM \"" . $postgisschema . "\".\"" . $table . "\" LIMIT 1";
            try {
                $postgisObject->execQuery($sql);
            } catch (PDOException) {
                throw new OwsException("Relation doesn't exist", attributes: ["exceptionCode" => "InvalidParameterValue"]);
            }

            $atts = [];
            $atts["name"] = $table . "Type";
            $writer->writeTag("open", "xsd", "complexType", $atts, false);
            $atts = [];
            $writer->writeTag("open", "xsd", "complexContent", null, false);
            $atts["base"] = "gml:AbstractFeatureType";
            $writer->writeTag("open", "xsd", "extension", $atts, false);
            $writer->writeTag("open", "xsd", "sequence", null, false);
            $atts = [];

            $sql = "SELECT * FROM settings.getColumns('f_table_name=''{$table}'' AND f_table_schema=''{$postgisschema}''',
                    'raster_columns.r_table_name=''{$table}'' AND raster_columns.r_table_schema=''{$postgisschema}''')";
            $fieldConfRow = $postgisObject->fetchRow($postgisObject->execQuery($sql));
            $fieldConf = json_decode($fieldConfRow['fieldconf']);
            $fieldConfArr = json_decode($fieldConfRow['fieldconf'], true);

            // Sort fields by sort_id
            $sortArr = [];
            foreach ($fieldsArr as $value) {
                if (!empty($fieldConfArr[$value]["sort_id"])) {
                    $sortArr[] = [$fieldConfArr[$value]["sort_id"], $value];
                } else {
                    $sortArr[] = [0, $value];
                }
            }
            usort($sortArr, function ($a, $b) {
                return $a[0] - $b[0];
            });
            // Filter out ignored fields
            $sortArr = array_filter($sortArr, function ($item) use (&$fieldConfArr) {
                if (empty($fieldConfArr[$item[1]]['ignore'])) {
                    return $item;
                }
            });
            $fieldsArr = [];
            foreach ($sortArr as $value) {
                $fieldsArr[] = $value[1];
            }

            foreach ($fieldsArr as $hello) {
                $atts = [];
                $atts["nillable"] = !empty($tableObj->metaData[$hello]["is_nullable"]) ? "true" : "false";
                $atts["name"] = $hello;
                $properties = !empty($fieldConf->{$atts["name"]}) ? $fieldConf->{$atts["name"]} : null;
                if (!empty($writer->gmlUseAltFunctions[$table]['changeFieldName'])) {
                    // changeFieldName() not available in this context; skip legacy alt function
                }
                $atts["maxOccurs"] = "1";

                if ($tableObj->metaData[$atts["name"]]['type'] == "geometry") {
                    $sql = "SELECT * FROM settings.getColumns('f_table_name=''{$table}'' AND f_table_schema=''{$postgisschema}'' AND f_geometry_column=''{$atts["name"]}''',
                    'raster_columns.r_table_name=''{$table}'' AND raster_columns.r_table_schema=''{$postgisschema}''')";
                    $typeRow = $postgisObject->fetchRow($postgisObject->execQuery($sql));
                    $def = json_decode($typeRow['def']);
                    if ($def->geotype && $def->geotype !== "Default") {
                        if ($def->geotype == "LINE") {
                            $def->geotype = "LINESTRING";
                        }
                        $typeRow['type'] = "MULTI" . $def->geotype;
                    }
                    switch ($typeRow['type']) {
                        case "POINT":
                            $atts["type"] = "gml:PointPropertyType";
                            break;
                        case "LINESTRING":
                            $atts["type"] = "gml:LineStringPropertyType";
                            break;
                        case "POLYGON":
                            $atts["type"] = "gml:PolygonPropertyType";
                            break;
                        case "MULTIPOINT":
                            $atts["type"] = "gml:MultiPointPropertyType";
                            break;
                        case "MULTILINESTRING":
                            $atts["type"] = "gml:MultiLineStringPropertyType";
                            break;
                        case "MULTIPOLYGON":
                            $atts["type"] = "gml:MultiPolygonPropertyType";
                            break;
                    }
                } elseif ($tableObj->metaData[$atts["name"]]['type'] == "bytea") {
                    if (isset($properties->image) && $properties->image == true) {
                        $atts["type"] = "gc2:imageType";
                        if (isset($fieldConf->{$atts["name"]}->properties)) {
                            $pJson = json_decode($fieldConf->{$atts["name"]}->properties, true);
                            if (!empty($pJson["width"])) {
                                $atts["width"] = $pJson["width"];
                            }
                            if (!empty($pJson["quality"])) {
                                $atts["quality"] = $pJson["quality"];
                            }
                        }
                    }
                } else {
                    $type = $tableObj->metaData[$atts["name"]]['type'];
                    if ($type == "decimal") {
                        $atts["type"] = "xsd:decimal";
                    } elseif ($type == "double") {
                        $atts["type"] = "xsd:double";
                    } elseif ($type == "text") {
                        $atts["type"] = "xsd:string";
                    } elseif ($type == "timestamp") {
                        $atts["type"] = "xsd:string";
                    } elseif ($type == "timestamptz") {
                        $atts["type"] = "xsd:string";
                    } elseif ($type == "date") {
                        $atts["type"] = "xsd:string";
                    } elseif ($type == "time") {
                        $atts["type"] = "xsd:string";
                    } elseif ($type == "timetz") {
                        $atts["type"] = "xsd:string";
                    } elseif ($type == "bytea") {
                        $atts["type"] = "xsd:base64Binary";
                    } elseif ($type == "json") {
                        $atts["type"] = "xsd:string";
                    } elseif ($type == "uuid") {
                        $atts["type"] = "xsd:string";
                    } elseif ($type == "int") {
                        $atts["type"] = "xsd:int";
                    } elseif ($type == "string") {
                        unset($atts["type"]);
                    } else {
                        if (!empty($tableObj->metaData[$atts["name"]]['is_array'])) {
                            $atts["type"] = "xsd:string";
                        } else {
                            $atts["type"] = "xsd:" . $type;
                        }
                    }
                    $simpleType = true;
                }

                $atts["minOccurs"] = "0";
                if (!empty($fieldConf->{$atts["name"]}->properties)) {
                    unset($atts["type"]);
                }
                $writer->writeTag("open", "xsd", "element", $atts, false);

                if ($simpleType) {
                    $minLength = "0";
                    $maxLength = "256";
                    $colType = $tableObj->metaData[$atts["name"]]['type'];
                    if ($colType == "string") {
                        $maxLength = filter_var($tableObj->metaData[$atts["name"]]['full_type'], FILTER_SANITIZE_NUMBER_INT);
                    }
                    if ($colType == "text") {
                        $colType = "string";
                        $maxLength = null;
                    }
                    if ($colType == "uuid") {
                        $colType = "string";
                    }
                    if ($colType == "timestamp") {
                        $colType = "datetime";
                    }
                    if ($colType == "timestamptz") {
                        $colType = "datetime";
                    }
                    if ($colType == "date") {
                        $maxLength = "256";
                    }
                    if ($colType == "bytea") {
                        $colType = "base64Binary";
                    }
                    if ($atts["name"] == $primeryKey['attname']) {
                        $colType = "string";
                    }

                    if (!empty($fieldConf->{$atts["name"]}->properties)) {
                        unset($atts["type"]);
                        $writer->write('<xsd:simpleType><xsd:restriction base="xsd:' . $colType . '">');
                        if ($fieldConf->{$atts["name"]}->properties == "*") {
                            $distinctValues = $tableObj->getGroupByAsArray($atts["name"]);
                            foreach ($distinctValues["data"] as $prop) {
                                $writer->write("<xsd:enumeration value=\"{$prop}\"/>");
                            }
                        } else {
                            foreach (json_decode($properties->properties) as $prop) {
                                $writer->write("<xsd:enumeration value=\"{$prop}\"/>");
                            }
                        }
                        $writer->write('</xsd:restriction></xsd:simpleType>');
                    } elseif ($colType == "string") {
                        $writer->write('<xsd:simpleType><xsd:restriction base="xsd:' . $colType . '">');
                        $writer->write("<xsd:minLength value=\"{$minLength}\"/>");
                        if ($maxLength) $writer->write("<xsd:maxLength value=\"{$maxLength}\"/>");
                        $writer->write('</xsd:restriction></xsd:simpleType>');
                    }
                    $simpleType = false;
                }

                $writer->writeTag("close", "xsd", "element", null, false);
                $atts = [];
            }

            $writer->writeTag("close", "xsd", "sequence", null, false);
            $writer->writeTag("close", "xsd", "extension", null, false);
            $writer->writeTag("close", "xsd", "complexContent", null, false);
            $writer->writeTag("close", "xsd", "complexType", null, false);

            $atts = [];
            $atts["name"] = $table;
            $atts["type"] = $table . "Type";
            if ($gmlNameSpace) $atts["type"] = $gmlNameSpace . ":" . $atts["type"];
            $atts["substitutionGroup"] = "gml:_Feature";
            $writer->writeTag("selfclose", "xsd", "element", $atts, false);
            $atts = [];
        }

        $writer->writeTag("close", "xsd", "schema", null, false);
        $writer->write("\n");
    }
}
