<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\models;

use app\conf\App;
use app\conf\Connection;
use app\inc\Model;
use app\inc\Util;
use PDOException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;

class Mapfile extends Model
{
    private array $bbox;

    /**
     * @throws PhpfastcacheInvalidArgumentException|InvalidArgumentException
     */
    function __construct(?\app\inc\Connection $connection = null)
    {
        parent::__construct(connection: $connection);
        $settings = new Setting(connection: $connection);
        $extents = $settings->get()["data"]->extents ?? null;
        $schema = $this->postgisschema;
        $this->bbox = is_object($extents) && property_exists($extents, $schema)
            ? $extents->$schema
            : [-20037508.34, -20037508.34, 20037508.34, 20037508.34];
    }

    /**
     * Transform the bbox to a target SRID. Falls back to the original bbox on failure.
     */
    public function transformBbox(int $targetSrid): array
    {
        $sql = "WITH box AS (SELECT ST_extent(st_transform(ST_MakeEnvelope({$this->bbox[0]},{$this->bbox[1]},{$this->bbox[2]},{$this->bbox[3]},3857),{$targetSrid})) AS a) SELECT ST_xmin(a) AS xmin, ST_ymin(a) AS ymin, ST_xmax(a) AS xmax, ST_ymax(a) AS ymax FROM box";
        $result = $this->prepare($sql);
        try {
            $result->execute();
            $row = $this->fetchRow($result);
            return [$row["xmin"], $row["ymin"], $row["xmax"], $row["ymax"]];
        } catch (PDOException) {
            return $this->bbox;
        }
    }

    /**
     * Fetch all OWS-enabled layers for the current schema.
     */
    public function getOwsLayerRows(): \PDOStatement
    {
        $sql = "SELECT * FROM settings.getColumns('f_table_schema=''{$this->postgisschema}'' AND enableows=true','raster_columns.r_table_schema=''{$this->postgisschema}'' AND enableows=true') ORDER BY sort_id";
        return $this->execQuery($sql);
    }

    /**
     * Prepare all shared layer data needed for both WMS and WFS mapfile generation.
     * Returns null if the layer should be skipped.
     */
    public function prepareLayerData(array $row, bool $filterBytea = false): ?array
    {
        if ($row['srid'] <= 1) {
            return null;
        }

        $extent = $this->transformBbox((int)$row['srid']);

        $rel = "{$row['f_table_schema']}.{$row['f_table_name']}";
        $meta = [];
        $q = !empty($row["data"])
            ? $row["data"]
            : "select * from {$this->doubleQuoteQualifiedName($rel)}";

        $select = $this->prepare("select * from ($q) as foo LIMIT 0");
        try {
            $select->execute();
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return null;
        }
        foreach (range(0, $select->columnCount() - 1) as $column_index) {
            $col = $select->getColumnMeta($column_index);
            $meta[$col["name"]] = $col["native_type"];
        }

        $arr = !empty($row['def']) ? json_decode($row['def'], true) : [];
        foreach (["label_column", "theme_column"] as $field) {
            if (empty($arr[$field]) || $arr[$field] == false) {
                $arr[$field] = "";
            }
        }
        $layerArr = ["data" => [$arr]];

        $classArr = $this->sortClasses($row['class'] ?? '');
        $primeryKey = $this->getPrimeryKey("{$row['f_table_schema']}.{$row['f_table_name']}");

        $fieldConf = !empty($row['fieldconf']) ? json_decode($row['fieldconf'], true) : [];

        uksort($meta, function ($a, $b) use ($fieldConf) {
            if (isset($fieldConf[$a]) && isset($fieldConf[$b])) {
                return (int)$fieldConf[$a]['sort_id'] - (int)$fieldConf[$b]['sort_id'];
            }
            return 0;
        });

        if ($filterBytea) {
            $meta = array_filter($meta, function ($item, $key) use (&$fieldConf) {
                if ($item != 'bytea') {
                    return $item;
                }
                return false;
            }, ARRAY_FILTER_USE_BOTH);
        }

        $selectStr = sizeof($meta) > 0 ? implode("\\\",\\\"", array_keys($meta)) : '';

        $filteredMeta = array_filter($meta, function ($item, $key) use (&$fieldConf) {
            if (empty($fieldConf[$key]['ignore'])) {
                return $item;
            }
            return false;
        }, ARRAY_FILTER_USE_BOTH);

        $includeItemsStr = sizeof($filteredMeta) > 0 ? implode(",", array_keys($filteredMeta)) : 'all';

        return [
            'extent' => $extent,
            'meta' => $meta,
            'filteredMeta' => $filteredMeta,
            'layerArr' => $layerArr,
            'classArr' => $classArr,
            'primeryKey' => $primeryKey,
            'fieldConf' => $fieldConf,
            'selectStr' => $selectStr,
            'includeItemsStr' => $includeItemsStr,
            'layerName' => $row['f_table_schema'] . "." . $row['f_table_name'],
            'dataSql' => $this->buildDataSql($row),
        ];
    }

    private function sortClasses(string $classJson): array
    {
        $arr = $arr2 = !empty($classJson) ? !empty(json_decode($classJson, true)) ? json_decode($classJson, true) : [] : [];
        $sortedArr = [];
        for ($i = 0; $i < sizeof($arr); $i++) {
            $last = 100000;
            $temp = null;
            $del = null;
            foreach ($arr2 as $key => $value) {
                if ($value["sortid"] < $last) {
                    $temp = $value;
                    $del = $key;
                    $last = $value["sortid"];
                }
            }
            $sortedArr[] = $temp;
            unset($arr2[$del]);
        }
        $arrNew = [];
        for ($i = 0; $i < sizeof($sortedArr); $i++) {
            $arrNew[$i] = (array)Util::casttoclass('stdClass', $sortedArr[$i]);
            $arrNew[$i]['id'] = $i;
        }
        return $arrNew;
    }

    public function buildDataSql(array $row): string
    {
        if (empty($row["data"])) {
            if (preg_match('/[A-Z]/', $row['f_geometry_column'])) {
                return "SELECT *,\\\"{$row['f_geometry_column']}\\\" as " . strtolower($row['f_geometry_column']) . " FROM \\\"{$row['f_table_schema']}\\\".\\\"{$row['f_table_name']}\\\"";
            }
            return "SELECT * FROM \\\"{$row['f_table_schema']}\\\".\\\"{$row['f_table_name']}\\\"";
        }
        return $row["data"];
    }

    /**
     * Resolve the MapServer layer TYPE based on geometry type.
     * @param string $defaultGeometryType The type to use for GEOMETRY (WMS uses POINT, WFS uses LINE)
     */
    public function resolveLayerType(array $row, array $layerArr, string $defaultGeometryType = 'POINT'): string
    {
        if (!empty($layerArr['data'][0]['geotype']) && $layerArr['data'][0]['geotype'] != "Default") {
            return $layerArr['data'][0]['geotype'];
        }
        return match ($row['type']) {
            'GEOMETRY' => $defaultGeometryType,
            'POINT', 'MULTIPOINT' => 'POINT',
            'LINESTRING', 'MULTILINESTRING' => 'LINE',
            'POLYGON', 'MULTIPOLYGON', 'MULTISURFACE' => 'POLYGON',
            'RASTER' => 'RASTER',
            default => $defaultGeometryType,
        };
    }

    public function renderPostgisConnection(): string
    {
        $s = "user=" . Connection::$param['postgisuser'];
        $s .= " dbname=" . Connection::$param['postgisdb'];
        if (Connection::$param['postgishost']) {
            $s .= " host=" . (!empty(Connection::$param['mapserverhost']) ? Connection::$param['mapserverhost'] : Connection::$param['postgishost']);
        }
        $s .= " port=" . ((!empty(Connection::$param['mapserverport']) ? Connection::$param['mapserverport'] : Connection::$param['postgisport']) ?: "5432");
        if (Connection::$param['postgispw']) {
            $s .= " password=" . Connection::$param['postgispw'];
        }
        if (!Connection::$param['pgbouncer']) {
            $s .= " options='-c client_encoding=UTF8'";
        }
        return $s;
    }

    public function renderDataLine(array $row, array $layerData): string
    {
        return "DATA \"" . strtolower($row['f_geometry_column']) . " FROM (SELECT \\\"{$layerData['selectStr']}\\\" FROM ({$layerData['dataSql']} /*FILTER_{$layerData['layerName']}*/) as bar) as foo USING UNIQUE {$layerData['primeryKey']['attname']} USING srid={$row['srid']}\"";
    }

    public function renderSymbols(): string
    {
        return <<<'SYMBOLS'
#
# Vector Line Types
#

Symbol
Name 'triangle'
Type VECTOR
Filled TRUE
Points
0 1
.5 0
1 1
0 1
END
END

SYMBOL
NAME "circle"
TYPE ellipse
FILLED true
POINTS
1 1
END
END

Symbol
Name 'square'
Type VECTOR
Filled TRUE
Points
0 1
0 0
1 0
1 1
0 1
END
END

Symbol
Name 'star'
Type VECTOR
Filled TRUE
Points
0 .375
.35 .375
.5 0
.65 .375
1 .375
.75 .625
.875 1
.5 .75
.125 1
.25 .625
END
END

SYMBOL
NAME "hatch1"
TYPE VECTOR
POINTS
0 1 1 0
END
END

SYMBOL
NAME "dashed1"
TYPE ELLIPSE
FILLED TRUE
POINTS
1 1
END
#STYLE 4 2 END
END

SYMBOL

NAME "arrow"
TYPE vector
FILLED true
POINTS
0 0.4
3 0.4
3 0
5 0.8
3 1.6
3 1.2
0 1.2
0 0.4
END
ANCHORPOINT 0 0.5
END # SYMBOL

SYMBOL
NAME "arrow2"
TYPE vector
FILLED true
POINTS
0 0.8
1 0.4
0 0
0 0.8
END
ANCHORPOINT 0 0.5
END

#
# Vector Line Types
#

SYMBOL
NAME "continue"
TYPE ELLIPSE
FILLED TRUE
POINTS
1 1
END
END

SYMBOL
NAME "dashed-line-short"
TYPE ELLIPSE
FILLED TRUE
POINTS
10 1
END
END

SYMBOL
NAME "dashed-line-long"
TYPE ELLIPSE
FILLED TRUE
POINTS
10 10
END
END

SYMBOL
NAME "dash-dot"
TYPE ELLIPSE
FILLED TRUE
POINTS
20 6 2 6
END
END

SYMBOL
NAME "dash-dot-dot"
TYPE ELLIPSE
FILLED TRUE
POINTS
1 1
END
END

SYMBOL
NAME "dot-dot"
TYPE ELLIPSE
FILLED TRUE
POINTS
1 1
END
END
SYMBOLS;
    }

    /**
     * Render a MapServer STYLE block.
     * @param string $prefix '' for primary style, 'overlay' for overlay style
     */
    public function renderStyle(array $class, string $prefix = ''): string
    {
        $p = $prefix;
        $s = "STYLE\n";

        // SYMBOL
        if (!empty($class[$p . 'symbol'])) {
            $sym = $class[$p . 'symbol'];
            $d = str_starts_with($sym, "[") ? "" : "'";
            $s .= "SYMBOL {$d}{$sym}{$d}\n";
        }

        // PATTERN
        if (!empty($class[$p . 'pattern'])) $s .= "PATTERN {$class[$p . 'pattern']} END\n";

        // LINECAP
        if (!empty($class[$p . 'linecap'])) $s .= "LINECAP {$class[$p . 'linecap']}\n";

        // WIDTH
        if (!empty($class[$p . 'width'])) $s .= "WIDTH {$class[$p . 'width']}\n";

        // COLOR
        if (!empty($class[$p . 'color'])) $s .= "COLOR " . Util::hex2RGB($class[$p . 'color'], true, " ") . "\n";

        // OUTLINECOLOR
        if (!empty($class[$p . 'outlinecolor'])) $s .= "OUTLINECOLOR " . Util::hex2RGB($class[$p . 'outlinecolor'], true, " ") . "\n";

        // OPACITY
        if (!empty($class[$p . 'style_opacity'])) $s .= "OPACITY {$class[$p . 'style_opacity']}\n";

        // SIZE
        if (!empty($class[$p . 'size'])) {
            $s .= "SIZE " . (is_numeric($class[$p . 'size']) ? $class[$p . 'size'] : "[{$class[$p . 'size']}]") . "\n";
        }

        // ANGLE
        if (!empty($class[$p . 'angle'])) {
            $angle = $class[$p . 'angle'];
            if (is_numeric($angle) && ((int)$angle > 360 || (int)$angle < -360)) $angle = '0';
            $s .= (is_numeric($angle) || strtolower($angle) == "auto")
                ? "ANGLE {$angle}\n"
                : "ANGLE [{$angle}]\n";
        }

        // GAP
        if (!empty($class[$p . 'gap'])) $s .= "GAP {$class[$p . 'gap']}\n";

        // GEOMTRANSFORM
        if (!empty($class[$p . 'geomtransform'])) $s .= "GEOMTRANSFORM '{$class[$p . 'geomtransform']}'\n";

        // MINSIZE / MAXSIZE (primary style only)
        if ($prefix === '') {
            if (!empty($class['minsize'])) $s .= "MINSIZE {$class['minsize']}\n";
            if (!empty($class['maxsize'])) $s .= "MAXSIZE {$class['maxsize']}\n";
        }

        // OFFSET
        $s .= "OFFSET " . $this->renderOffsetPair($class, $p . 'style_offsetx', $p . 'style_offsety') . "\n";

        // POLAROFFSET
        $s .= "POLAROFFSET " . $this->renderOffsetPair($class, $p . 'style_polaroffsetr', $p . 'style_polaroffsetd') . "\n";

        $s .= "\nEND # style\n";
        return $s;
    }

    private function renderOffsetPair(array $class, string $xKey, string $yKey): string
    {
        $x = !empty($class[$xKey]) ? (is_numeric($class[$xKey]) ? $class[$xKey] : "[{$class[$xKey]}]") : "0";
        $y = !empty($class[$yKey]) ? (is_numeric($class[$yKey]) ? $class[$yKey] : "[{$class[$yKey]}]") : "0";
        return "{$x} {$y}";
    }

    /**
     * Render a MapServer LABEL block.
     * @param string $num '' for label 1, '2' for label 2
     */
    public function renderLabel(array $class, string $layerName, string $num = ''): string
    {
        $enableKey = "label" . $num;
        if (empty($class[$enableKey])) return '';

        $p = "label{$num}_";
        $n = $num ?: '1';

        $s = "#START_LABEL{$n}_{$layerName}\n\n";
        $s .= "LABEL\n";
        if (!empty($class[$p . 'text'])) $s .= "TEXT '{$class[$p . 'text']}'\n";
        $s .= "TYPE truetype\n";
        $s .= "FONT " . ($class[$p . 'font'] ?: "arial") . ($class[$p . 'fontweight'] ?: "normal") . "\n";

        // SIZE
        if (!empty($class[$p . 'size'])) {
            $s .= "SIZE " . (is_numeric($class[$p . 'size']) ? $class[$p . 'size'] : "[{$class[$p . 'size']}]") . "\n";
        } else {
            $s .= "SIZE 11\n";
        }

        $s .= "COLOR " . (!empty($class[$p . 'color']) ? Util::hex2RGB($class[$p . 'color'], true, " ") : "1 1 1") . "\n";
        $s .= "OUTLINECOLOR " . (!empty($class[$p . 'outlinecolor']) ? Util::hex2RGB($class[$p . 'outlinecolor'], true, " ") : "255 255 255") . "\n";
        $s .= "SHADOWSIZE 2 2\n";
        $s .= "ANTIALIAS true\n";
        $s .= "FORCE " . (!empty($class[$p . 'force']) ? "true" : "false") . "\n";
        $s .= "POSITION " . (!empty($class[$p . 'position']) ? $class[$p . 'position'] : "auto") . "\n";
        $s .= "PARTIALS false\n";
        $s .= "MINSIZE 1\n";

        if (!empty($class[$p . 'maxsize'])) $s .= "MAXSIZE {$class[$p . 'maxsize']}\n";
        if (!empty($class[$p . 'maxscaledenom'])) $s .= "MAXSCALEDENOM {$class[$p . 'maxscaledenom']}\n";
        if (!empty($class[$p . 'minscaledenom'])) $s .= "MINSCALEDENOM {$class[$p . 'minscaledenom']}\n";
        if (!empty($class[$p . 'buffer'])) $s .= "BUFFER {$class[$p . 'buffer']}\n";
        if (!empty($class[$p . 'repeatdistance'])) $s .= "REPEATDISTANCE {$class[$p . 'repeatdistance']}\n";
        if (!empty($class[$p . 'minfeaturesize'])) $s .= "MINFEATURESIZE {$class[$p . 'minfeaturesize']}\n";

        if (!empty($class[$p . 'expression'])) {
            $s .= "EXPRESSION ({$class[$p . 'expression']})\n";
        }

        // ANGLE
        if (!empty($class[$p . 'angle'])) {
            $angle = $class[$p . 'angle'];
            if (is_numeric($angle) && ((int)$angle > 360 || (int)$angle < -360)) $angle = '0';
            $s .= (is_numeric($angle) || $angle == 'auto' || $angle == 'auto2' || $angle == 'follow')
                ? "ANGLE {$angle}\n"
                : "ANGLE [{$angle}]\n";
        }

        $s .= "WRAP \"\\n\"\n\n";
        $s .= "OFFSET " . (!empty($class[$p . 'offsetx']) ? $class[$p . 'offsetx'] : "0") . " " . (!empty($class[$p . 'offsety']) ? $class[$p . 'offsety'] : "0") . "\n\n\n";

        // Label background style
        $s .= "STYLE\n";
        if (!empty($class[$p . 'backgroundcolor'])) {
            $bgColor = Util::hex2RGB($class[$p . 'backgroundcolor'], true, " ");
            $s .= "GEOMTRANSFORM 'labelpoly'\n";
            $s .= "COLOR {$bgColor}\n";
            if ($num === '') {
                // Label 1: always output outline + width with default
                $s .= "OUTLINECOLOR {$bgColor}\n";
                $s .= "WIDTH " . ($class[$p . 'backgroundpadding'] ?: "1") . "\n";
            } else {
                // Label 2: only if padding is set
                if (!empty($class[$p . 'backgroundpadding'])) {
                    $s .= "OUTLINECOLOR {$bgColor}\n";
                    $s .= "WIDTH {$class[$p . 'backgroundpadding']}\n";
                }
            }
        }
        $s .= "END # STYLE\n";
        $s .= "END\n";
        $s .= "#END_LABEL{$n}_{$layerName}\n";
        return $s;
    }

    public function renderLeader(array $class): string
    {
        if (empty($class['leader'])) return '';

        $s = "LEADER\n";
        $s .= "GRIDSTEP " . (!empty($class['leader_gridstep']) ? $class['leader_gridstep'] : "5") . "\n";
        $s .= "MAXDISTANCE " . (!empty($class['leader_maxdistance']) ? $class['leader_maxdistance'] : "30") . "\n";
        $s .= "STYLE\n";
        $s .= "COLOR " . (!empty($class['leader_color']) ? Util::hex2RGB($class['leader_color'], true, " ") : "1 1 1") . "\n";
        $s .= "WIDTH 1\n";
        $s .= "END\nEND\n";
        return $s;
    }

    /**
     * Render all CLASS blocks for a layer.
     */
    public function renderClasses(array $classData, array $layerArr, string $layerName): string
    {
        $s = '';
        foreach ($classData as $class) {
            $s .= "CLASS\n";

            // NAME
            if (!empty($class['name'])) $s .= "NAME '" . addslashes($class['name']) . "'\n";

            // EXPRESSION
            if (!empty($class['expression'])) {
                if (!empty($layerArr['data'][0]['theme_column'])) {
                    $s .= "EXPRESSION \"{$class['expression']}\"\n";
                } else {
                    $s .= "EXPRESSION ({$class['expression']})\n";
                }
            } elseif (empty($class['expression']) && !empty($layerArr['data'][0]['theme_column'])) {
                $s .= "EXPRESSION ''\n";
            }

            // Scale denominators
            if (!empty($class['class_maxscaledenom'])) $s .= "MAXSCALEDENOM {$class['class_maxscaledenom']}\n";
            if (!empty($class['class_minscaledenom'])) $s .= "MINSCALEDENOM {$class['class_minscaledenom']}\n";

            // Primary style + overlay style
            $s .= $this->renderStyle($class);
            $s .= $this->renderStyle($class, 'overlay');

            // Labels
            $s .= $this->renderLabel($class, $layerName);
            $s .= "#LABEL2\n";
            $s .= $this->renderLabel($class, $layerName, '2');

            // Leader
            $s .= $this->renderLeader($class);

            $s .= "END # Class\n";
        }
        return $s;
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function generateWms(): string
    {
        $user = Connection::$param['postgisdb'];
        $extent = $this->transformBbox(4326);
        $srs = !empty(App::$param['advertisedSrs']) ? implode(" ", App::$param['advertisedSrs']) : "EPSG:4326 EPSG:3857 EPSG:3044 EPSG:25832";

        $s = "MAP\n";
        $s .= "#\n# Start of map file\n#\n";
        $s .= "NAME \"{$user}\"\n";
        $s .= "STATUS on\n";
        $s .= "EXTENT " . implode(" ", $extent) . "\n";
        $s .= "SIZE 2000 1500\n";
        $s .= "MAXSIZE 16384\n";
        $s .= "FONTSET \"/var/www/geocloud2/app/wms/fonts/fonts.txt\"\n";
        $s .= "IMAGECOLOR 255 2 255\n";
        $s .= "UNITS METERS\n";
        $s .= "#INTERLACE OFF\n\n";

        // Output formats
        $s .= "OUTPUTFORMAT\nNAME \"png\"\nDRIVER AGG/PNG\nMIMETYPE \"image/png\"\nIMAGEMODE RGBA\nEXTENSION \"png\"\nTRANSPARENT ON\nFORMATOPTION \"GAMMA=0.75\"\nEND\n\n";
        $s .= "OUTPUTFORMAT\nNAME \"utfgrid\"\nDRIVER UTFGRID\nMIMETYPE \"application/json\"\nEXTENSION \"json\"\nFORMATOPTION \"UTFRESOLUTION=4\"\nFORMATOPTION \"DUPLICATES=false\"\nEND\n\n";

        $s .= "#CONFIG \"MS_ERRORFILE\" \"/var/www/geocloud2/app/wms/mapfiles/ms_error.txt\"\n";
        $s .= "#DEBUG 5\n\n";

        // WEB
        $s .= "WEB\n";
        $s .= "IMAGEPATH \"" . App::$param['path'] . "/tmp\"\n";
        $s .= "IMAGEURL \"" . App::$param['host'] . "/tmp\"\n";
        $s .= "METADATA\n";
        $s .= "\"wms_title\"    \"{$user}'s OWS\"\n";
        $s .= "\"wms_srs\"    \"{$srs}\"\n";
        $s .= "\"wms_name\"    \"{$user}\"\n";
        $s .= "\"wms_format\"    \"image/png\"\n";
        $s .= "\"wms_onlineresource\"    \"" . App::$param['host'] . "/ows/" . Connection::$param['postgisdb'] . "/" . Connection::$param['postgisschema'] . "/\"\n";
        $s .= "\"wms_enable_request\" \"*\"\n";
        $s .= "\"ows_encoding\" \"UTF-8\"\n";
        $s .= "\"wms_extent\" \"" . implode(" ", $extent) . "\"\n";
        $s .= "\"wms_allow_getmap_without_styles\" \"true\"\n";
        $s .= "END\nEND\n\n";

        // PROJECTION
        $s .= "#\n# Start of reference map\n#\n\n";
        $s .= "PROJECTION\n\"init=EPSG:4326\"\nEND\n\n";

        // LEGEND
        $s .= "#\n# Start of legend\n#\n\n";
        $s .= "LEGEND\nSTATUS off\nIMAGECOLOR 255 255 255\nKEYSIZE 18 12\n";
        $s .= "LABEL\nWRAP \"#\"\nTYPE truetype\nFONT \"arialnormal\"\nSIZE 8\nCOLOR 0 0 0\nEND\nEND\n\n";

        // SCALEBAR
        $s .= "#\n# Start of scalebar\n#\n\n";
        $s .= "SCALEBAR\nSTATUS off\nCOLOR 255 255 255\nOUTLINECOLOR 0 0 0\nBACKGROUNDCOLOR 0 0 0\n";
        $s .= "IMAGECOLOR 255 255 255\nUNITS METERS\nINTERVALS 3\nSIZE 150 5\n";
        $s .= "LABEL\nFONT \"courierb\"\nSIZE SMALL\nCOLOR 0 0 0\nSHADOWSIZE 2 2\nEND\nEND\n\n";

        // Symbols
        $s .= $this->renderSymbols() . "\n\n";

        // Layers
        $s .= "#\n# Start of layers\n#\n";
        $result = $this->getOwsLayerRows();
        while ($row = $this->fetchRow($result)) {
            $layerData = $this->prepareLayerData($row, filterBytea: true);
            if (!$layerData) continue;

            $layerArr = $layerData['layerArr'];
            $layerName = $layerData['layerName'];
            $type = $this->resolveLayerType($row, $layerArr, 'POINT');

            $s .= "LAYER\n";
            $s .= "NAME \"{$layerName}\"\n";
            $s .= "STATUS off\n";

            if ($row['filter']) {
                $s .= "PROCESSING \"NATIVE_FILTER={$row['filter']}\"\n";
            }

            if ($row['wmssource']) {
                $s .= "TYPE RASTER\n";
                $s .= "CONNECTIONTYPE WMS\n";
                $s .= "CONNECTION \"{$row['wmssource']}\"\n";
                $s .= "PROCESSING \"LOAD_WHOLE_IMAGE=YES\"\n";
                $s .= "PROCESSING \"LOAD_FULL_RES_IMAGE=YES\"\n";
                $s .= "PROCESSING \"RESAMPLE=BILINEAR\"\n";
            } elseif ($row['bitmapsource']) {
                $s .= "TYPE RASTER\n";
                $s .= "DATA \"" . App::$param['path'] . "/app/wms/files/" . Connection::$param["postgisdb"] . "/__bitmaps/{$row['bitmapsource']}\"\n";
                $s .= "PROCESSING \"RESAMPLE=AVERAGE\"\n";
                if (!empty($layerArr['data'][0]['bands'])) {
                    $s .= "PROCESSING \"BANDS={$layerArr['data'][0]['bands']}\"\n";
                }
            } else {
                if ($type != "RASTER") {
                    $s .= $this->renderDataLine($row, $layerData) . "\n";
                    $s .= "CONNECTIONTYPE POSTGIS\n";
                    $s .= "CONNECTION \"{$this->renderPostgisConnection()}\"\n";
                    if (!empty($layerArr['data'][0]['label_no_clip'])) $s .= "PROCESSING \"LABEL_NO_CLIP=True\"\n";
                    if (!empty($layerArr['data'][0]['polyline_no_clip'])) $s .= "PROCESSING \"POLYLINE_NO_CLIP=True\"\n";
                } else {
                    $host = Connection::$param['mapserverhost'] ?: Connection::$param['postgishost'];
                    $port = !empty(Connection::$param['mapserverport']) ? Connection::$param['mapserverport'] : (!empty(Connection::$param['postgisport']) ? Connection::$param['postgisport'] : "5432");
                    $s .= "DATA \"PG:host={$host} port={$port} dbname='" . Connection::$param['postgisdb'] . "' user='" . Connection::$param['postgisuser'] . "' password='" . Connection::$param['postgispw'] . "'\n";
                    $s .= "\t\t\t    schema='{$row['f_table_schema']}' table='{$row['f_table_name']}' mode='2'\"\n";
                    $s .= "PROCESSING \"CLOSE_CONNECTION=ALWAYS\" \n";
                }
                $s .= "TYPE {$type}\n\n";
            }

            // OFFSITE
            $s .= "#OFFSITE\n";
            if (!empty($layerArr['data'][0]['offsite'])) $s .= "OFFSITE {$layerArr['data'][0]['offsite']}\n";

            // CLASSITEM
            $s .= "\n#CLASSITEM\n";
            if (!empty($layerArr['data'][0]['theme_column'])) $s .= "CLASSITEM '{$layerArr['data'][0]['theme_column']}'\n";

            // LABELITEM
            $s .= "\n#LABELITEM\n";
            if (!empty($layerArr['data'][0]['label_column'])) $s .= "LABELITEM '{$layerArr['data'][0]['label_column']}'\n";

            // LABELMAXSCALEDENOM
            $s .= "\n#LABELMAXSCALEDENOM\n";
            if (!empty($layerArr['data'][0]['label_max_scale'])) $s .= "LABELMAXSCALEDENOM {$layerArr['data'][0]['label_max_scale']}\n";

            // LABELMINSCALEDENOM
            $s .= "\n#LABELMINSCALEDENOM\n";
            if (!empty($layerArr['data'][0]['label_min_scale'])) $s .= "LABELMINSCALEDENOM {$layerArr['data'][0]['label_min_scale']}\n";

            // COMPOSITE
            $s .= "\nCOMPOSITE\n";
            $s .= "#OPACITY\n";
            if (!empty($layerArr['data'][0]['opacity'])) $s .= "OPACITY  {$layerArr['data'][0]['opacity']}\n";
            $s .= "END\n";

            // MAXSCALEDENOM
            $s .= "\n#MAXSCALEDENOM\n";
            if (!empty($layerArr['data'][0]['maxscaledenom'])) $s .= "MAXSCALEDENOM  {$layerArr['data'][0]['maxscaledenom']}\n";

            // MINSCALEDENOM
            $s .= "\n#MINSCALEDENOM\n";
            if (!empty($layerArr['data'][0]['minscaledenom'])) $s .= "MINSCALEDENOM  {$layerArr['data'][0]['minscaledenom']}\n";

            // SYMBOLSCALEDENOM
            $s .= "\n#SYMBOLSCALEDENOM\n";
            if (!empty($layerArr['data'][0]['symbolscaledenom'])) $s .= "SYMBOLSCALEDENOM {$layerArr['data'][0]['symbolscaledenom']}\n";

            // CLUSTER
            $s .= "\n#MINSCALEDENOM\n";
            if (!empty($layerArr['data'][0]['cluster'])) {
                $s .= "CLUSTER\n";
                $s .= "MAXDISTANCE {$layerArr['data'][0]['cluster']}\n";
                $s .= "REGION \"ellipse\"\n";
                $s .= "END\n";
            }

            // METADATA
            $s .= "\n#LABELMAXSCALE\nMETADATA\n";
            $title = $row['f_table_title'] ? addslashes($row['f_table_title']) : $row['f_table_name'];
            $s .= "\"ows_title\"    \"{$title}\"\n";
            $s .= "\"wms_group_title\" \"{$row['layergroup']}\"\n";
            $s .= "\"wms_group_abstract\" \"{$row['layergroup']}\"\n";
            $s .= "\"ows_srs\"    \"EPSG:{$row['srid']} {$row['wmsclientepsgs']}\"\n";
            $s .= "\"ows_name\"    \"{$layerName}\"\n";
            $abstract = !empty($row['f_table_abstract']) ? addslashes($row['f_table_abstract']) : "";
            $s .= "\"ows_abstract\"    \"{$abstract}\"\n";
            $s .= "\"wms_format\"    \"image/png\"\n";
            $s .= "\"wms_extent\" \"" . implode(" ", $layerData['extent']) . "\"\n";
            $s .= "\"wms_enable_request\"    \"*\"\n";
            $s .= "\"wms_include_items\" \"{$layerData['includeItemsStr']}\"\n";
            $s .= "\"wms_exceptions_format\" \"application/vnd.ogc.se_xml\"\n";
            if ($row['wmssource'] && empty($row['legend_url'])) {
                $wmsCon = str_replace(["layers", "LAYERS"], "LAYER", $row['wmssource']);
                $s .= "\"wms_get_legend_url\" \"{$wmsCon}&REQUEST=getlegendgraphic\"\n";
            }
            if (!empty($row['legend_url'])) {
                $s .= "\"wms_get_legend_url\" \"{$row['legend_url']}\"\n";
            }
            if (!empty($layerArr['data'][0]['query_buffer'])) {
                $s .= "\"appformap_query_buffer\" \"{$layerArr['data'][0]['query_buffer']}\"\n";
            }
            $s .= "END\n\n";

            // PROJECTION + TEMPLATE
            $s .= "PROJECTION\n\"init=EPSG:{$row['srid']}\"\nEND\nTEMPLATE \"test\"\n";

            // Classes (not for WMS source layers)
            if (!empty($layerData['classArr']) && !$row['wmssource']) {
                $s .= $this->renderClasses($layerData['classArr'], $layerArr, $layerName);
            }

            $s .= "END #Layer\n";
        }

        $s .= "END #MapFile\n";
        return $s;
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function generateWfs(): string
    {
        $user = Connection::$param['postgisdb'];
        $extent = $this->transformBbox(4326);
        $srs = !empty(App::$param['advertisedSrs']) ? implode(" ", App::$param['advertisedSrs']) : "EPSG:4326 EPSG:3857 EPSG:3044 EPSG:25832";

        $s = "MAP\n";
        $s .= "#\n# Start of map file\n#\n";
        $s .= "NAME \"{$user}\"\n";
        $s .= "STATUS on\n";
        $s .= "EXTENT " . implode(" ", $extent) . "\n";
        $s .= "SIZE 2000 1500\n";
        $s .= "MAXSIZE 16384\n";
        $s .= "UNITS METERS\n";
        $s .= "FONTSET \"/var/www/geocloud2/app/wms/fonts/fonts.txt\"\n\n";

        // Symbols
        $s .= $this->renderSymbols() . "\n\n";

        // Output formats
        $s .= "OUTPUTFORMAT\nNAME kml\nDRIVER \"OGR/KML\"\nMIMETYPE \"application/vnd.google-earth.kml+xml\"\nIMAGEMODE FEATURE\nEXTENSION \"kml\"\nFORMATOPTION \"FORM=simple\"\nFORMATOPTION 'FILENAME=igmap75.kml'\nFORMATOPTION \"maxfeaturestodraw=1000\"\nEND\n\n";
        $s .= "OUTPUTFORMAT\nNAME \"geojson\"\nDRIVER \"OGR/GEOJSON\"\nMIMETYPE \"application/json\"\nIMAGEMODE FEATURE\nFORMATOPTION \"STORAGE=stream\"\nEND\n\n";

        $s .= "#CONFIG \"MS_ERRORFILE\" \"/var/www/geocloud2/app/wms/mapfiles/ms_error.txt\"\n";
        $s .= "#DEBUG 5\n\n";

        // WEB
        $s .= "WEB\nMETADATA\n";
        $s .= "\"ows_title\"    \"{$user}'s OWS\"\n";
        $s .= "\"ows_srs\"    \"{$srs}\"\n";
        $s .= "\"ows_name\"    \"{$user}\"\n";
        $s .= "\"ows_onlineresource\"    \"" . App::$param['host'] . "/ows/" . Connection::$param['postgisdb'] . "/" . Connection::$param['postgisschema'] . "/\"\n";
        $s .= "\"ows_enable_request\" \"*\"\n";
        $s .= "\"ows_encoding\" \"UTF-8\"\n";
        $s .= "\"ows_namespace_prefix\" \"{$user}\"\n";
        $s .= "\"ows_namespace_uri\" \"" . App::$param['host'] . "\"\n";
        $s .= "\"wfs_getfeature_formatlist\" \"kml,kmz,geojson\"\n";
        $s .= "END\nEND\n\n";

        // PROJECTION
        $s .= "#\n# Start of reference map\n#\n\n";
        $s .= "PROJECTION\n\"init=EPSG:4326\"\nEND\n\n\n";

        // Layers
        $s .= "#\n# Start of layers\n#\n";
        $result = $this->getOwsLayerRows();
        while ($row = $this->fetchRow($result)) {
            $layerData = $this->prepareLayerData($row, filterBytea: false);
            if (!$layerData) continue;

            $layerArr = $layerData['layerArr'];
            $layerName = $layerData['layerName'];
            $type = $this->resolveLayerType($row, $layerArr, 'LINE');

            $s .= "LAYER\n";
            $s .= "NAME \"{$layerName}\"\n";
            $s .= "STATUS off\n";

            $s .= $this->renderDataLine($row, $layerData) . "\n";
            $s .= "CONNECTIONTYPE POSTGIS\n";
            $s .= "CONNECTION \"{$this->renderPostgisConnection()}\"\n";
            $s .= "TYPE {$type}\n";

            // METADATA
            $s .= "METADATA\n";
            $title = $row['f_table_title'] ? (!empty($row['f_table_title']) ? addslashes($row['f_table_title']) : "") : $row['f_table_name'];
            $s .= "\"wfs_title\"    \"{$title}\"\n";
            $s .= "\"wfs_srs\"    \"EPSG:{$row['srid']} {$row['wmsclientepsgs']}\"\n";
            $s .= "\"wfs_name\"    \"{$layerName}\"\n";
            $abstract = !empty($row['f_table_abstract']) ? addslashes($row['f_table_abstract']) : "";
            $s .= "\"wfs_abstract\"    \"{$abstract}\"\n";
            $s .= "\"wfs_extent\" \"" . implode(" ", $layerData['extent']) . "\"\n";
            $s .= "\"gml_include_items\" \"{$layerData['includeItemsStr']}\"\n";
            $s .= "\"wfs_featureid\" \"{$layerData['primeryKey']['attname']}\"\n";
            $s .= "\"gml_types\" \"auto\"\n";
            $geomType = $row['type'] . ($row['coord_dimension'] == 3 ? "25D" : "");
            $s .= "\"wfs_geomtype\" \"{$geomType}\"\n";
            $s .= "\"gml_geometries\"    \"{$row['f_geometry_column']}\"\n";
            $gmlType = (substr($row['type'], 0, 5) == "MULTI" ? "multi" : "") . strtolower($type);
            $s .= "\"gml_{$row['f_geometry_column']}_type\" \"{$gmlType}\"\n";
            $s .= "\"wfs_getfeature_formatlist\" \"kml,kmz,geojson\"\n";
            $s .= "\"wfs_geometry_precision\" \"8\"\n";
            $s .= "END\n";

            // UTFITEM / UTFDATA
            $s .= "UTFITEM   \"{$layerData['primeryKey']['attname']}\"\n";
            $fieldsArr = [];
            $fields = !empty($row['fieldconf']) ? json_decode($row['fieldconf'], true) : null;
            if (!empty($fields)) {
                foreach ($fields as $field => $name) {
                    if (isset($layerData['filteredMeta'][$field]) && !empty($name["mouseover"])) {
                        $fieldsArr[] = "\\\"{$field}\\\":\\\"[{$field}]\\\"";
                    }
                }
            }
            $s .= "UTFDATA \"{" . implode(",", $fieldsArr) . "}\"\n\n";

            // PROJECTION + TEMPLATE
            $s .= "PROJECTION\n\"init=EPSG:{$row['srid']}\"\nEND\n\nTEMPLATE \"test\"\n";

            // Classes
            if (!empty($layerData['classArr'])) {
                $s .= $this->renderClasses($layerData['classArr'], $layerArr, $layerName);
            }

            $s .= "END #Layer\n";
        }

        $s .= "END #MapFile\n";
        return $s;
    }

    public function writeMapfile(string $content, string $type): array
    {
        $path = App::$param['path'] . "app/wms/mapfiles/";
        $name = Connection::$param['postgisdb'] . "_" . Connection::$param['postgisschema'] . "_{$type}.map";
        @unlink($path . $name);
        $fh = fopen($path . $name, 'w');
        fwrite($fh, $content);
        fclose($fh);
        return ["success" => true, "message" => "Mapfile written", "ch" => $path . $name];
    }
}
