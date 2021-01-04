<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use Exception;

/**
 * Class PgHStore
 * @package app\inc
 */
class PgHStore
{
    /**
     * @param string $data
     * @return array<string|null>
     */
    public static function fromPg(string $data): array
    {
        $split = preg_split('/[,\s]*"([^"]+)"[,\s]*|[,=>\s]+/', $data, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $hstore = array();

        for ($index = 0; $index < count($split); $index = $index + 2) {
            $hstore[$split[$index]] = $split[$index + 1] != 'NULL' ? $split[$index + 1] : null;
        }

        return $hstore;
    }

    /**
     * @param array<string|null> $data
     * @return string
     * @throws Exception
     */
    public static function toPg(array $data): string
    {
        if (!is_array($data)) {
            throw new Exception(sprintf("HStore::toPg takes an associative array as parameter ('%s' given).", gettype($data)));
        }

        $insert_values = array();

        foreach ($data as $key => $value) {
            if (is_null($value)) {
                $insert_values[] = sprintf('"%s" => NULL', $key);
            } else {
                $insert_values[] = sprintf('"%s" => "%s"', $key, $value);
            }
        }

        return sprintf("'%s'::hstore", join(', ', $insert_values));
    }
}
