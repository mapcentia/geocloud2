<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\opensearch;

use app\conf\App;

class SettingsComposer
{
    /**
     * Composes the OpenSearch index settings from the static default (the live
     * per-install `elasticsearch_settings.json` when present, else the tracked
     * `.dist`), optionally replacing `settings.analysis` with a per-database
     * analysis block.
     *
     * @param array|null $perDbAnalysis when non-null, replaces `settings.analysis`
     * @return array the composed settings document, e.g. ['settings' => [...]]
     */
    public static function compose(?array $perDbAnalysis): array
    {
        $base = App::$param['path'] . 'app/conf/elasticsearch_settings.json';
        $file = is_file($base) ? $base : $base . '.dist';
        $default = json_decode(file_get_contents($file), true);
        if ($perDbAnalysis !== null) {
            $default['settings']['analysis'] = $perDbAnalysis;
        }
        return $default;
    }

    /**
     * The default `settings.analysis` block.
     */
    public static function defaultAnalysis(): array
    {
        return self::compose(null)['settings']['analysis'];
    }
}
