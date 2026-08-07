<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\ows;

use app\conf\App;

/**
 * Patches a tmp copy of a MapServer mapfile or a QGIS project with per-layer
 * WHERE filters and optional label removal. Pure PHP string work — no shell_exec
 * (the legacy sed pipeline was a shell-injection surface).
 */
final class MapfilePatcher
{
    public static function patchMapfileContent(string $content, array $filters, bool $disableLabels, array $layers): string
    {
        foreach ($layers as $layer) {
            [$schema, $table] = self::split($layer);
            if (!empty($filters[$layer])) {
                $where = 'WHERE ' . implode(' AND ', $filters[$layer]);
                $content = str_replace("/*FILTER_$schema.$table*/", $where, $content);
            }
            if ($disableLabels) {
                // Remove every numbered label block for this layer, inclusive of the
                // START/END marker lines. Anchored directly on the markers (rather than
                // a leading/trailing ".*") so a greedy dotall match can't swallow past
                // its own #END_LABEL line into a neighbouring block or the file tail.
                $needle = preg_quote("$schema.$table", '/');
                $pattern = '/[ \t]*#START_LABEL[0-9]*_' . $needle
                         . '.*?#END_LABEL[0-9]*_' . $needle . '[^\r\n]*\R?/s';
                $content = preg_replace($pattern, '', $content);
            }
        }
        return $content;
    }

    public static function patchQgsContent(string $content, array $filters, bool $disableLabels, array $layers): string
    {
        foreach ($layers as $layer) {
            [$schema, $table] = self::split($layer);
            if (!empty($filters[$layer])) {
                $where = self::xmlEscape(implode(' AND ', $filters[$layer]));
                // Replace sql=...< on the datasource line for table="schema"."table"
                $pattern = '/(table="' . preg_quote($schema, '/') . '"\."' . preg_quote($table, '/')
                         . '"[^>]*?sql=)[^<]*(<)/';
                $content = preg_replace($pattern, '${1}' . $where . '${2}', $content);
            }
        }
        if ($disableLabels) {
            $content = str_replace('labelsEnabled="1"', 'labelsEnabled="0"', $content);
        }
        return $content;
    }

    public static function xmlEscape(string $string): string
    {
        return str_replace(
            ['&', '<', '>', '\'', '"'],
            ['&amp;', '&lt;', '&gt;', '&apos;', '&quot;'],
            $string
        );
    }

    /**
     * Writes patched content to a unique tmp file next to the source's kind
     * (.map or .qgs based on the source path extension). Returns the tmp path.
     */
    public function writeTmp(string $sourcePath, string $patchedContent): string
    {
        $ext = str_ends_with($sourcePath, '.qgs') ? 'qgs' : 'map';
        $name = bin2hex(random_bytes(16));
        $tmp = rtrim(App::$param['path'], '/') . "/app/tmp/$name.$ext";
        file_put_contents($tmp, $patchedContent);
        return $tmp;
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function split(string $layer): array
    {
        $bits = explode('.', $layer);
        return [$bits[0] ?? '', $bits[1] ?? ($bits[0] ?? '')];
    }
}
