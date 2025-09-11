<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\conf\App;
use app\conf\Connection;
use app\inc\Model;
use PDOException;

/**
 * Class Qgis
 * @package app\models
 */
class Qgis extends Model
{
    function __construct()
    {
        parent::__construct();
    }

    /**
     * Inserts data into the qgis_files table in the settings database.
     *
     * @param array $data An associative array containing the data to be inserted,
     *                    where keys should correspond to the named placeholders in the SQL statement.
     * @return array An associative array containing the success status and a message indicating the result of the operation.
     */
    public function insert(array $data): array
    {
        $response = [];
        $sql = "INSERT INTO settings.qgis_files(id, xml, db) VALUES (:id, :xml, :db)";
        $res = $this->prepare($sql);
        $this->execute($res, $data);
        $response['success'] = true;
        $response['message'] = "QGIS file stored";
        return $response;
    }

    /**
     * Writes all non-deprecated QGIS file records from the database to files in a specified directory.
     *
     * @param string $db The name of the database to query for QGIS file records.
     * @return array An associative array containing the success status and a list of file IDs that were written.
     * @throws PDOException If an error occurs while opening or writing any of the files.
     */
    public function writeAll(string $db): array
    {
        $response = [];
        $files = [];
        $sql = "SELECT *,extract(EPOCH FROM timestamp) AS unixtimestamp FROM settings.qgis_files WHERE db=:db AND old !=TRUE ORDER BY timestamp";
        $res = $this->prepare($sql);
        $this->execute($res, ["db" => $db]);
        $path = App::$param['path'] . "/app/wms/qgsfiles/";

        while ($row = $this->fetchRow($res, "assoc")) {
            @unlink($path . $row["id"]);
            @$fh = fopen($path . $row["id"], 'w');
            if (!$fh) {
                throw new PDOException("Couldn't open file for writing: " . $row["id"], 401);
            }
            @$w = fwrite($fh, $this->parse($row["xml"]));
            if (!$w) {
                throw new PDOException("Couldn't write the file: " . $row["id"], 401);
            } else {
                touch($path . $row["id"], (int)$row["unixtimestamp"]);
            }
            fclose($fh);
            $files[] = $row["id"];
        }
        $response['success'] = true;
        $response['data'] = $files;
        return $response;
    }

    /**
     * Parses the given XML string, modifying specific connection parameters
     * if they are found in the content. Updates parameters such as port, user,
     * password, host, and database name with predefined values.
     *
     * @param string $xml The XML string to parse and modify.
     * @return string The modified XML string, or the original string if parsing fails.
     */
    private function parse(string $xml): string
    {
        // Split into lines, process only lines containing all five keys
        $lines = preg_split("/\R/u", $xml);
        if ($lines === false) {
            // Fallback: return original if split failed
            return $xml;
        }
        foreach ($lines as $i => $line) {
            // Cheap containment check to avoid regex work unless needed
            if (
                str_contains($line, 'port') &&
                str_contains($line, 'user') &&
                str_contains($line, 'password') &&
                str_contains($line, 'host') &&
                str_contains($line, 'dbname')
            ) {
                $line = preg_replace("/port='?[0-9]*'?/", "port=" . Connection::$param["postgisport"], $line);
                $line = preg_replace("/user=\'?[^\s\\\\]*\'?/", "user=" . Connection::$param["postgisuser"], $line);
                $line = preg_replace("/password=\'?[^\s\\\\]*\'?/", "password=" . Connection::$param["postgispw"], $line);
                $line = preg_replace("/host=\'?[^\s\\\\]*\'?/", "host=" . Connection::$param["postgishost"], $line);
                $line = preg_replace("/dbname=\'?[^\s\\\\]*\'?/", "dbname=" . Database::getDb(), $line);
                $lines[$i] = $line;
            }
        }
        // Join back with \n (XML parsers accept mixed EOL; keeps it simple and safe)
        return implode("\n", $lines);
    }
}