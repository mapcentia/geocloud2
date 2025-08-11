<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\exceptions\GC2Exception;
use app\inc\Cache;
use app\inc\Globals;
use app\inc\Model;
use PDO;
use Psr\Cache\InvalidArgumentException;


/**
 * Class Preparedstatement
 *
 * Provides methods to manage prepared statements stored in the database.
 */
class Preparedstatement extends Model
{

    const string CACHE_TYPE = 'prepared_statement';

    /**
     * @throws InvalidArgumentException
     */
    private function clearCacheOnSchemaChanges(string $key): void
    {
        $patterns = [
            $this->postgisdb . '_' . self::CACHE_TYPE . '_' . $key,
        ];
        Cache::deleteByPatterns($patterns);
    }

    /**
     * @param string $uuid
     * @return array
     * @throws GC2Exception
     */
    public function getByUuid(string $uuid): array
    {
        $cacheType = self::CACHE_TYPE;
        $cacheId = $this->postgisdb . '_' . $cacheType . '_' . $uuid;
        $CachedString = Cache::getItem($cacheId);
        if ($CachedString != null && $CachedString->isHit()) {
            $row = $CachedString->get();
        } else {
            $sql = "SELECT * FROM settings.prepared_statements WHERE uuid=:uuid";
            $res = $this->prepare($sql);
            $res->execute(["uuid" => $uuid]);
            if ($res->rowCount() == 0) {
                throw new GC2Exception("No statements with that uuid", 404, null, "NO_STATEMENT_ERROR");
            }
            $row = $this->fetchRow($res);
            $CachedString->set($row)->expiresAfter(Globals::$cacheTtl);
            Cache::save($CachedString);
        }
        $response['success'] = true;
        $response['message'] = "Statement fetched";
        $response['data'] = $row;
        return $response;
    }

    /**
     * Fetches a prepared statement by its name from the database.
     *
     * @param string $name The name of the prepared statement to fetch.
     * @return array An associative array containing the fetch status, message, and data.
     * @throws GC2Exception if no statements with the given name are found.
     */
    public function getByName(string $name): array
    {
        $cacheType = self::CACHE_TYPE;
        $cacheId = $this->postgisdb . '_' . $cacheType . '_' . md5($name);
        $CachedString = Cache::getItem($cacheId);
        if ($CachedString != null && $CachedString->isHit()) {
            $row = $CachedString->get();
        } else {
            $sql = "SELECT * FROM settings.prepared_statements WHERE name=:name";
            $res = $this->prepare($sql);
            $res->execute(["name" => $name]);
            if ($res->rowCount() == 0) {
                throw new GC2Exception("No method with that name", 404, null, "NO_METHOD_ERROR");
            }
            $row = $this->fetchRow($res);
            $CachedString->set($row)->expiresAfter(Globals::$cacheTtl);
            Cache::save($CachedString);
        }
        $response['success'] = true;
        $response['message'] = "Statement fetched";
        $response['data'] = $row;
        return $response;
    }

    /**
     * Retrieves all records from the settings.prepared_statements table.
     *
     * @return array An associative array containing the query result.
     * @throws GC2Exception If no statements are found.
     */
    public function getAll(): array
    {
        $sql = "SELECT * FROM settings.prepared_statements";
        $res = $this->prepare($sql);
        $res->execute();
        if ($res->rowCount() == 0) {
            throw new GC2Exception("No statements", 404, null, "NO_STATEMENT_ERROR");
        }
        $rows = $this->fetchAll($res, 'assoc');
        $response['success'] = true;
        $response['message'] = "Statements fetched";
        $response['data'] = $rows;
        return $response;
    }

    /**
     * Creates or updates a prepared statement in the settings.prepared_statements table.
     *
     * @param string $name The name of the prepared statement.
     * @param string $statement The SQL statement to be prepared.
     * @return string The UUID of the created or updated prepared statement.
     */
    public function createPreparedStatement(string $name, string $statement, ?array $typeHints, ?array $typeFormats, string $outputFormat, ?int $srs, string $userName): string
    {
        $sql = "INSERT INTO settings.prepared_statements (name, statement, type_hints, type_formats, output_format, srs, username) VALUES (:name, :statement, :type_hints, :type_formats, :output_format, :srs, :username) returning name";
        $res = $this->prepare($sql);
        $res->execute(['name' => $name, 'statement' => $statement, 'type_hints' => json_encode($typeHints), 'type_formats' => json_encode($typeFormats), 'output_format' => $outputFormat, 'srs' => $srs, 'username' => $userName]);
        return $res->fetchColumn();
    }

    public function updatePreparedStatement(string $name, ?string $newName, ?string $statement, ?array $typeHints, ?array $typeFormats, ?string $outputFormat, ?int $srs, string $userName, bool $isSuperUser = false): void
    {
        $this->clearCacheOnSchemaChanges(md5($name));
        $old = $this->getByName($name)['data'];
        $params = [
            'name' => $name,
            'newName' => $newName ?? $old['name'],
            'statement' => $statement ?? $old['statement'],
            'type_hints' => $typeHints ? json_encode($typeHints) : $old['type_hints'],
            'type_formats' => $typeFormats ? json_encode($typeFormats) : $old['type_formats'],
            'output_format' => $outputFormat ?? $old['output_format'],
            'srs' => $srs ?? $old['srs'],
        ];
        if ($isSuperUser) {
            $sql = "UPDATE settings.prepared_statements SET name=:newName, statement=:statement,type_hints=:type_hints,type_formats=:type_formats,output_format=:output_format,srs=:srs where name=:name RETURNING uuid,name";

        } else {
            $sql = "UPDATE settings.prepared_statements SET name=:newName, statement=:statement,type_hints=:type_hints,type_formats=:type_formats,output_format=:output_format,srs=:srs where name=:name and username=:username RETURNING uuid,name";
            $params['username'] = $userName;
        }
        $res = $this->prepare($sql);

        $res->execute($params);
        $row = $res->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new GC2Exception("Not allowed to patch method", 404, null, "NOT_ALLOWED_ERROR");
        }
        $this->clearCacheOnSchemaChanges(md5($row['name']));
        $this->clearCacheOnSchemaChanges(md5($row['uuid']));
    }

    /**
     * Deletes a prepared statement with the given name from the settings.prepared_statements table.
     *
     * @param string $name The name of the prepared statement to be deleted.
     * @return void
     * @throws GC2Exception If no statements are found with the given name.
     * @throws InvalidArgumentException
     */
    public function deletePreparedStatement(string $name, string $userName, bool $isSuperUser = false): void
    {
        $this->getByName($name);
        $params = ['name' => $name];
        if ($isSuperUser) {
            $sql = "DELETE FROM settings.prepared_statements WHERE name=:name RETURNING uuid,name";
        } else {
            $sql = "DELETE FROM settings.prepared_statements WHERE name=:name and username=:username RETURNING uuid,name";
            $params['username'] = $userName;
        }
        $res = $this->prepare($sql);
        $res->execute($params);
        $row = $res->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new GC2Exception("Not allowed to delete method", 404, null, "NOT_ALLOWED_ERROR");
        }
        $this->clearCacheOnSchemaChanges(md5($row['name']));
        $this->clearCacheOnSchemaChanges(md5($row['uuid']));
    }
}