<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */


namespace app\inc;

final class WfsFilter
{

    /**
     * Removes specific namespaces from the given XML tag string.
     *
     * @param string $tag The XML tag string from which namespaces should be removed.
     * @return string The XML tag string with specified namespaces removed.
     */
    public static function dropNameSpace(string $tag): string
    {
        $tag = preg_replace('/ \w*(?:\:\w*?)?(?<!gml)(?<!service)(?<!version)(?<!outputFormat)(?<!maxFeatures)(?<!resultType)(?<!typeName)(?<!srsName)(?<!fid)(?<!id)=(\".*?\"|\'.*?\')/s', "", $tag);
        $tag = preg_replace('/\<[a-z|0-9]*(?<!gml):(?:.*?)/', "<", $tag);
        return preg_replace('/\<\/[a-z|0-9]*(?<!gml):(?:.*?)/', "</", $tag);
    }

    /**
     * Removes all namespaces from the given XML tag string.
     *
     * @param string $tag The XML tag string from which all namespaces should be removed.
     * @return string The XML tag string with all namespaces removed and trimmed of double quotes if present.
     */
    public static function dropAllNameSpaces(string $tag): string
    {
        $tag = preg_replace("/[\w-]*:/", "", $tag); // remove any namespaces
        // Trim double qoutes. Openlayers adds them to ogc:PropertyName in WFS requets
        $tag = trim($tag, '"');
        return ($tag);
    }

    /**
     * Parses the provided EPSG code string and extracts the cleaned code value.
     *
     * @param string|null $epsg The EPSG code string to be parsed. Can be null.
     * @return string|null The cleaned EPSG code, or null if the input is null.
     */
    public static function parseEpsgCode(?string $epsg): ?string
    {
        if (!$epsg) {
            return null;
        }
        if (str_contains($epsg, "#")) {
            $separator = "#";
        } else {
            $separator = ":";
        }

        $split = explode($separator, $epsg);
        $clean = end($split);
        return preg_replace("/\w\./", "", $clean);
    }

    /**
     * EPSG:4326 longitude/latitude assumption
     * http://www.opengis.net/gml/srs/epsg.xml#xxxx longitude/latitude strict
     * urn:x-ogc:def:crs:EPSG:xxxx latitude/longitude strict
     * urn:ogc:def:crs:EPSG::4326 latitude/longitude strict
     *
     * @param string|null $epsg
     * @return string|null
     */
    public static function getAxisOrder(?string $epsg): ?string
    {
        if (!$epsg) return null;
        if ($epsg == "urn:ogc:def:crs:EPSG::4326" || str_starts_with($epsg, "urn:x-ogc:def:crs:EPSG:")) {
            $first = "latitude";
        } else {
            $first = "longitude";
        }
        return $first;
    }

    /**
     * Determines if a given array is an associative array.
     *
     * @param array $arr The array to check.
     * @return bool True if the array is associative, false otherwise.
     */
    public static function isAssoc(array $arr): bool
    {
        if (array() === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Adds the "gml:" namespace to the keys of an array unless the keys are "srsName" or "_content".
     * Recursively applies the transformation to nested arrays.
     *
     * @param array $arr The input array whose keys will be updated with the namespace.
     * @return array The array with updated keys, including namespaces where applicable.
     */
    public static function addNs(array $arr): array
    {
        return array_combine(
            array_map(function ($k) {
                if ($k == "srsName" || $k == "_content") {
                    return $k;
                } else {
                    return 'gml:' . $k;
                }
            }, array_keys($arr)),
            array_map(function ($k) {
                if (is_array($k)) {
                    return self::addNs($k);
                } else {
                    return $k;
                }
            }, $arr)
        );
    }

    /**
     * Removes namespace prefixes from the keys of an associative array.
     *
     * For each key in the array, the namespace prefix (if present) will be stripped. If an element
     * of the array is itself an array, the operation will be applied recursively to all levels.
     *
     * @param array $arr The associative array from which namespace prefixes should be removed.
     * @return array The array with namespace prefixes removed from all keys.
     */
    public static function removeNs(array $arr): array
    {
        return array_combine(
            array_map(function ($k) {
                return preg_replace("/[\w-]*:/", "", $k);
            }, array_keys($arr)),
            array_map(function ($k) {
                if (is_array($k)) {
                    return self::removeNs($k);
                } else {
                    return $k;
                }
            }, $arr)
        );
    }

    /**
     * Converts a GML-like array representation of geometries into a WKT (Well-Known Text) format.
     *
     * @param array $arr The array representing the GML structure of the geometry.
     * @param bool|null $coordsOnly Optional. If true, only coordinate data will be included in the output. Defaults to false.
     * @param string|null $axisOrder Optional. Specifies the axis order for the coordinates (e.g., "lat,lon" or "lon,lat"). Defaults to null.
     * @param string|null $globalSrid Optional. The global SRID to use if no SRID is specified in the input array. Defaults to null.
     * @return array An array containing WKT string, SRID code, and feature ID if available.
     */
    public static function toWkt(array $arr, ?bool $coordsOnly = false, ?string $axisOrder = null, ?string $globalSrid = null): array
    {
        $arr = self::removeNs($arr);
        $arr = self::addNs($arr);
        $strEnd = "";
        $srid = null;
        $fid = null;
        $str = "";
        foreach ($arr as $key => $value) {
            $str = "";
            $strEnd = ")";
            $srid = isset($value["srsName"]) ? self::parseEpsgCode($value["srsName"]) : $globalSrid;
            $fid = $value["gml:id"] ?? null;
            if (isset($value["srsName"])) {
                $axisOrder = self::getAxisOrder($value["srsName"]);
            }
            if (sizeof(explode(":", $key)) == 1) {
                $key = "gml:" . $key;
            }
            switch ($key) {
                case "gml:Point":
                case "gml:LineString":

                    $str .= $coordsOnly ? "(" : ($key == "gml:Point" ? "POINT" : "LINESTRING") . "(";
                    if (isset($value["gml:coordinates"]) && is_array($value["gml:coordinates"])) {
                        $str .= self::coordinatesToWKT($value["gml:coordinates"]["_content"], $axisOrder);
                    } elseif (isset($value["gml:coordinates"])) {
                        $str .= self::coordinatesToWKT($value["gml:coordinates"], $axisOrder);
                    } elseif (isset($value["gml:pos"]) && is_array($value["gml:pos"])) {
                        $str .= self::postListToWKT($value["gml:pos"]["_content"], $axisOrder);
                    } elseif (isset($value["gml:pos"])) {
                        $str .= self::postListToWKT($value["gml:pos"], $axisOrder);
                    } elseif (isset($value["gml:posList"]) && is_array($value["gml:posList"])) {
                        $str .= self::postListToWKT($value["gml:posList"]["_content"], $axisOrder);
                    } elseif (isset($value["gml:posList"])) {
                        $str .= self::postListToWKT($value["gml:posList"], $axisOrder);
                    }
                    break;
                case "gml:Polygon":

                    $str .= $coordsOnly ? "((" : "POLYGON((";
                    $v = $value["gml:outerBoundaryIs"]["gml:LinearRing"] ?? $value["gml:exterior"]["gml:LinearRing"];
                    if (isset($v["gml:coordinates"]) && is_array($v["gml:coordinates"])) {
                        $str .= self::coordinatesToWKT($v["gml:coordinates"]["_content"], $axisOrder);
                    } elseif (isset($v["gml:coordinates"])) {
                        $str .= self::coordinatesToWKT($v["gml:coordinates"], $axisOrder);
                    } elseif (isset($v["gml:posList"]) && is_array($v["gml:posList"])) {
                        $str .= self::postListToWKT($v["gml:posList"]["_content"], $axisOrder);
                    } elseif (isset($v["gml:posList"])) {
                        $str .= self::postListToWKT($v["gml:posList"], $axisOrder);
                    }
                    $str .= ")";
                    $inner = $value["gml:innerBoundaryIs"] ?? $value["gml:interior"] ?? null;
                    if (isset($inner)) {
                        $inner = self::addDimensionOnArray($inner);
                    }
                    if (isset($inner[0]["gml:LinearRing"])) {
                        foreach ($inner as $linearRing) {
                            $v = $linearRing["gml:LinearRing"];
                            if (isset($v["gml:coordinates"]) && is_array($v["gml:coordinates"])) {
                                $str .= ",(" . self::coordinatesToWKT($v["gml:coordinates"]["_content"], $axisOrder) . ")";
                            } elseif (isset($v["gml:coordinates"])) {
                                $str .= ",(" . self::coordinatesToWKT($v["gml:coordinates"], $axisOrder) . ")";
                            } elseif (isset($v["gml:posList"]) && is_array($v["gml:posList"])) {
                                $str .= ",(" . self::postListToWKT($v["gml:posList"]["_content"], $axisOrder) . ")";
                            } elseif (isset($v["gml:posList"])) {
                                $str .= ",(" . self::postListToWKT($v["gml:posList"], $axisOrder) . ")";
                            }
                        }
                    }
                    break;
                case "gml:MultiPoint":
                    $str .= "MULTIPOINT(";
                    $arr = [];
                    if (isset(reset($value["gml:pointMember"])["gml:Point"])) {
                        foreach ($value["gml:pointMember"] as $member) {
                            $arr[] = self::toWkt($member, true, $axisOrder)[0];
                        }
                    } elseif (isset($value["gml:pointMember"])) {
                        $arr[] = self::toWkt($value["gml:pointMember"], true, $axisOrder)[0];
                    }
                    // MapInfo v15 uses pointMembers instead of pointMember
                    if (isset($value["gml:pointMembers"]) && is_array($value["gml:pointMembers"]) && isset(reset($value["gml:pointMembers"])["gml:Point"])) {
                        foreach ($value["gml:pointMembers"] as $member) {
                            $arr[] = self::toWkt($member, true, $axisOrder)[0];
                        }
                    } elseif (isset($value["gml:pointMembers"])) {
                        $arr[] = self::toWkt($value["gml:pointMembers"], true, $axisOrder)[0];
                    }

                    $str .= implode(",", $arr);
                    break;
                case "gml:MultiLineString":
                    $str .= "MULTILINESTRING(";
                    $arr = [];
                    if (isset(reset($value["gml:lineStringMember"])["gml:LineString"])) {
                        foreach ($value["gml:lineStringMember"] as $member) {
                            $arr[] = self::toWkt($member, true, $axisOrder)[0];
                        }
                    } else {
                        $arr[] = self::toWkt($value["gml:lineStringMember"], true, $axisOrder)[0];
                    }
                    $str .= implode(",", $arr);
                    break;
                case "gml:MultiCurve":
                    $str .= "MULTILINESTRING(";
                    $arr = [];
                    if (isset(reset($value["gml:curveMember"])["gml:LineString"])) {
                        foreach ($value["gml:curveMember"] as $member) {
                            $arr[] = self::toWkt($member, true, $axisOrder)[0];
                        }
                    } else {
                        $arr[] = self::toWkt($value["gml:curveMember"], true, $axisOrder)[0];
                    }
                    $str .= implode(",", $arr);
                    break;
                case "gml:MultiPolygon":
                    $str .= "MULTIPOLYGON(";
                    $arr = [];
                    if (isset(reset($value["gml:polygonMember"])["gml:Polygon"])) {
                        foreach ($value["gml:polygonMember"] as $member) {
                            $arr[] = self::toWkt($member, true, $axisOrder)[0];
                        }
                    } else {
                        $arr[] = self::toWkt($value["gml:polygonMember"], true, $axisOrder)[0];
                    }
                    $str .= implode(",", $arr);
                    break;
                case "gml:MultiSurface":
                    $str .= "MULTIPOLYGON(";
                    $arr = [];
                    if (isset(reset($value["gml:surfaceMember"])["gml:Polygon"])) {
                        foreach ($value["gml:surfaceMember"] as $member) {
                            $arr[] = self::toWkt($member, true, $axisOrder)[0];
                        }
                    } else {
                        $arr[] = self::toWkt($value["gml:surfaceMember"], true, $axisOrder)[0];
                    }
                    $str .= implode(",", $arr);
                    break;
            }
        }
        return [$str . $strEnd, $srid, $fid];
    }

    /**
     * Converts a position list into a Well-Known Text (WKT) formatted string.
     *
     * @param string $str The input string containing space-separated coordinate values.
     * @param string $axisOrder Defines the axis order; if set to "latitude", the coordinates will be reversed.
     * @return string The coordinates formatted as a Well-Known Text (WKT) string.
     */
    public static function postListToWKT(string $str, string $axisOrder): string
    {
        $str = trim(preg_replace('/\s\s+/', ' ', $str));
        $arr = explode(" ", trim($str));
        $i = 1;
        $newStr = "";
        $reversedArr = [];
        foreach ($arr as $value) {
            $newStr .= $value;
            if (is_int($i / 2)) {
                $newStr .= ",";
            } else {
                $newStr .= " ";
            }
            $i++;
        }
        $newStr = substr($newStr, 0, strlen($newStr) - 1);
        // If urn EPSG reverse the axixOrder
        if ($axisOrder == "latitude") {
            $split = explode(",", $newStr);
            foreach ($split as $value) {
                $splitCoord = explode(" ", $value);
                $reversedArr[] = $splitCoord[1] . " " . $splitCoord[0];
            }
            $newStr = implode(",", $reversedArr);

        }
        return $newStr;
    }

    /**
     * Converts a string of coordinates to a Well-Known Text (WKT) format.
     *
     * @param string $str The input string containing coordinates.
     * @param string $axisOrder The axis order to interpret the coordinates, either as "latitude"
     *                           (reverses the coordinate order) or other values (keeps the order).
     * @return string The resulting string formatted in WKT.
     */
    public static function coordinatesToWKT(string $str, string $axisOrder): string
    {
        $str = trim(preg_replace('/\s\s+/', ' ', $str));
        $str = str_replace(" ", "&", $str);
        $str = str_replace(",", " ", $str);
        $str = str_replace("&", ",", $str);
        $reversedArr = [];
        // If urn EPSG reverse the axixOrder
        if ($axisOrder == "latitude") {
            $split = explode(",", $str);
            foreach ($split as $value) {
                $splitCoord = explode(" ", $value);
                $reversedArr[] = $splitCoord[1] . " " . $splitCoord[0];
            }
            $str = implode(",", $reversedArr);
        }
        return $str;
    }

    /**
     * Adds a new dimension to an array if it does not already have one.
     *
     * @param array $array The array to modify.
     * @return array|null The modified array with an added dimension, or null if input is null.
     */
    public static function addDimensionOnArray(array $array): ?array
    {
        if (!isset($array[0])) {
            $array = array(0 => $array);
        }
        return $array;
    }

    /**
     * Generates an SQL WHERE clause based on a given filter structure.
     *
     * @param array $filter The associative array representing filter criteria.
     * @param string|null $sridOfTable The spatial reference identifier (SRID) of the table.
     * @param string|null $srs The spatial reference system (SRS) used for the filter.
     * @param string|null $primaryKey The primary key column name of the table.
     * @param string|null $geomField The geometry field of the table.
     * @return string A SQL WHERE clause constructed based on the provided filter.
     */
    public static function explode(array $filter, ?string $sridOfTable = null, ?string $srs = null, ?string $primaryKey = null, ?string $geomField = null): string
    {
        if (!isset($filter[0]) && !(isset($filter['And']) || isset($filter['Or']))) {
            $filter = array(0 => $filter);
        }
        $boolOperator = null;
        $where = [];
        foreach ($filter as $key => $arr) {
            // Skip xmlns:gml key
            if (!is_array($arr)) {
                continue;
            }
            if ($key == "And" || $key == "Or") {
                $boolOperator = $key;
            }
            $first = array_key_first($arr);
            if ($first !== "Not" && is_array($arr[$first]) && !self::isAssoc($arr[$first])) {
                foreach ($arr[$first] as $f) {
                    $where[] = self::explode([$first => $f], $sridOfTable, $srs, $primaryKey, $geomField);
                }
            } elseif (isset($arr["And"]) || isset($arr["Or"]) && $key != "Not") {
                if (isset($arr["And"]) && !self::isAssoc($arr["And"])) {
                    foreach ($arr["And"] as $f) {
                        $where[] = self::explode(["And" => $f], $sridOfTable, $srs, $primaryKey, $geomField);
                    }
                } elseif (isset($arr["Or"]) && !self::isAssoc($arr["Or"])) {
                    foreach ($arr["Or"] as $f) {
                        $where[] = self::explode(["Or" => $f], $sridOfTable, $srs, $primaryKey, $geomField);
                    }
                } else {
                    $where[] = self::explode($arr, $sridOfTable, $srs, $primaryKey, $geomField);
                }
            }

            $prop = "Not";
            if (isset($arr[$prop]) && self::isAssoc($arr[$prop])) {
                $where[] = "NOT" . self::explode($arr[$prop], $sridOfTable, $srs, $primaryKey, $geomField);
            }

            $prop = "PropertyIsEqualTo";
            if (isset($arr[$prop]) && self::isAssoc($arr[$prop]) && $key != "Not") {
                $value = $arr[$prop];
                $matchCase = !(isset($value["matchCase"]) && $value["matchCase"] == "false");
                $value["PropertyName"] = $value["PropertyName"] == "gml:name" ? $primaryKey : $value["PropertyName"];
                $where[] = "\"" . self::dropAllNameSpaces($value['PropertyName']) . ($matchCase ? "\"=" : "\" ILIKE ") . (new Model())->quote($value['Literal']);
            }

            $prop = "PropertyIsNotEqualTo";
            if (isset($arr[$prop]) && self::isAssoc($arr[$prop]) && $key != "Not") {
                $value = $arr[$prop];
                $where[] = "\"" . self::dropAllNameSpaces($value['PropertyName']) . "\"<>'" . $value['Literal'] . "'";
            }

            $prop = "PropertyIsLessThan";
            if (isset($arr[$prop]) && self::isAssoc($arr[$prop]) && $key != "Not") {
                $value = $arr[$prop];
                $where[] = "\"" . self::dropAllNameSpaces($value['PropertyName']) . "\"<'" . $value['Literal'] . "'";
            }

            $prop = "PropertyIsGreaterThan";
            if (isset($arr[$prop]) && self::isAssoc($arr[$prop]) && $key != "Not") {
                $value = $arr[$prop];
                $where[] = "\"" . self::dropAllNameSpaces($value['PropertyName']) . "\">'" . $value['Literal'] . "'";
            }

            $prop = "PropertyIsLessThanOrEqualTo";
            if (isset($arr[$prop]) && self::isAssoc($arr[$prop]) && $key != "Not") {
                $value = $arr[$prop];
                $where[] = "\"" . self::dropAllNameSpaces($value['PropertyName']) . "\"<='" . $value['Literal'] . "'";
            }

            $prop = "PropertyIsGreaterThanOrEqualTo";
            if (isset($arr[$prop]) && self::isAssoc($arr[$prop]) && $key != "Not") {
                $value = $arr[$prop];
                $where[] = "\"" . self::dropAllNameSpaces($value['PropertyName']) . "\">='" . $value['Literal'] . "'";
            }

            $prop = "PropertyIsLike";
            if (isset($arr[$prop]) && self::isAssoc($arr[$prop]) && $key != "not") {
                $value = $arr[$prop];
                $where[] = "\"" . self::dropAllNameSpaces($value['PropertyName']) . "\" LIKE '%" . $value['Literal'] . "%'";
            }

            $prop = "PropertyIsBetween";
            if (isset($arr[$prop]) && self::isAssoc($arr[$prop]) && $key != "not") {
                $value = $arr[$prop];
                $w = [];
                $value['PropertyName'] = self::dropAllNameSpaces($value['PropertyName']);
                if ($value['LowerBoundary']) {
                    $w[] = "\"" . $value['PropertyName'] . "\" > '" . $value['LowerBoundary']['Literal'] . "'";
                }
                if ($value['UpperBoundary']) {
                    $w[] = "\"" . $value['PropertyName'] . "\" < '" . $value['UpperBoundary']['Literal'] . "'";
                }
                $where[] = implode(" AND ", $w);
            }

            $prop = "PropertyIsNull";
            if (isset($arr[$prop]) && self::isAssoc($arr[$prop]) && $key != "not") {
                $value = $arr[$prop];
                $where[] = "\"" . self::dropAllNameSpaces($value['PropertyName']) . "\" isnull";
            }

            $prop = "FeatureId";
            if (isset($arr[$prop])) {
                $arr[$prop] = self::addDimensionOnArray($arr[$prop]);
                if (isset($arr[$prop])) foreach ($arr[$prop] as $value) {
                    $value['fid'] = preg_replace("/.*\./", "", $value['fid']); // remove table name
                    $where[] = "\"$primaryKey\"='" . $value['fid'] . "'";
                }
            }

            $prop = "GmlObjectId";
            if (isset($arr[$prop])) {
                $arr[$prop] = self::addDimensionOnArray($arr[$prop]);
                if (is_array($arr[$prop])) foreach ($arr[$prop] as $value) {
                    $value['gml:id'] = preg_replace("/.*\./", "", $value['gml:id']); // remove table name
                    $where[] = "\"$primaryKey\"='" . $value['gml:id'] . "'";
                }
            }

            $prop = "Intersects";
            if (isset($arr[$prop]) && self::isAssoc($arr[$prop]) && $key != "not") {
                $value = $arr[$prop];
                $value['PropertyName'] = self::dropAllNameSpaces($value['PropertyName']);
                $wktArr = self::toWkt($value);
                $sridOfFilter = $wktArr[1];
                if (empty($sridOfFilter)) $sridOfFilter = $srs; // If no filter on BBOX we think it must be same as the requested srs
                if (empty($sridOfFilter)) $sridOfFilter = $sridOfTable; // If still no filter on BBOX we set it to native srs
                $g = "ST_Transform(ST_GeometryFromText('" . $wktArr[0] . "'," . $sridOfFilter . "),$sridOfTable)";
                $where[] =
                    "ST_Intersects"
                    . "($g,"
                    . self::dropAllNameSpaces($value['PropertyName']) . ")";
                unset($wktArr);
            }

            $prop = "BBOX";
            if (!empty($arr[$prop])) {
                $axisOrder = null;
                $sridOfFilter = null;
                $coordsArr = null;
                if (isset($arr[$prop]['gml:Box']['gml:coordinates'])) {
                    if (is_array($arr[$prop]['gml:Box']['gml:coordinates'])) {
                        $arr[$prop]['gml:Box']['gml:coordinates']['_content'] = str_replace(" ", ",", $arr[$prop]['gml:Box']['gml:coordinates']['_content']);
                        $coordsArr = explode(",", $arr[$prop]['gml:Box']['gml:coordinates']['_content']);
                    } else {
                        $arr[$prop]['gml:Box']['gml:coordinates'] = str_replace(" ", ",", $arr[$prop]['gml:Box']['gml:coordinates']);
                        $coordsArr = explode(",", $arr[$prop]['gml:Box']['gml:coordinates']);

                    }
                }
                if (isset($arr[$prop]['gml:Box']) && is_array($arr[$prop]['gml:Box'])) {
                    $sridOfFilter = self::parseEpsgCode($arr[$prop]['gml:Box']['srsName']);
                    $axisOrder = self::getAxisOrder($arr[$prop]['gml:Box']['srsName']);
                    $sridOfFilter = $sridOfFilter ?? $srs ?? $sridOfTable; // If no filter on BBOX we think it must be same as the requested srs. If still no filter on BBOX we set it to native srs.
                }
                if (isset($arr[$prop]['gml:Envelope'])) {
                    $coordsArr = array_merge(explode(" ", $arr[$prop]['gml:Envelope']['gml:lowerCorner']), explode(" ", $arr[$prop]['gml:Envelope']['gml:upperCorner']));
                    $sridOfFilter = self::parseEpsgCode($arr[$prop]['gml:Envelope']['srsName']);
                    $axisOrder = self::getAxisOrder($arr[$prop]['gml:Envelope']['srsName']);
                    $sridOfFilter = $sridOfFilter ?? $srs ?? $sridOfTable; // If no filter on BBOX we think it must be same as the requested srs. If still no filter on BBOX we set it to native srs.
                }
                if ($axisOrder == "longitude") {
                    $where[] = "ST_Intersects"
                        . "(ST_Transform(ST_GeometryFromText('POLYGON((" . $coordsArr[0] . " " . $coordsArr[1] . "," . $coordsArr[0] . " " . $coordsArr[3] . "," . $coordsArr[2] . " " . $coordsArr[3] . "," . $coordsArr[2] . " " . $coordsArr[1] . "," . $coordsArr[0] . " " . $coordsArr[1] . "))',"
                        . $sridOfFilter
                        . "),$sridOfTable),"
                        . "\"" . (self::dropAllNameSpaces($arr[$prop]['PropertyName']) ?: $geomField) . "\")";
                } else {
                    $where[] = "ST_Intersects"
                        . "(ST_Transform(ST_GeometryFromText('POLYGON((" . $coordsArr[1] . " " . $coordsArr[0] . "," . $coordsArr[3] . " " . $coordsArr[0] . "," . $coordsArr[3] . " " . $coordsArr[2] . "," . $coordsArr[1] . " " . $coordsArr[2] . "," . $coordsArr[1] . " " . $coordsArr[0] . "))',"
                        . $sridOfFilter
                        . "),$sridOfTable),"
                        . "\"" . (self::dropAllNameSpaces($arr[$prop]['PropertyName']) ?: $geomField) . "\")";
                }
            }
        }
        if (empty($boolOperator)) {
            $boolOperator = "OR";
        }
        return "(" . implode(" " . $boolOperator . " ", $where) . ")";
    }
}