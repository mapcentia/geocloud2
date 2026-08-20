<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Model;
use Exception;
use PDOException;


/**
 * Class Keyvalue
 * @package app\models
 */
class Keyvalue extends Model
{
    function __construct(?Connection $connection = null)
    {
        parent::__construct(connection: $connection);
    }

    // ---------------------------------------------------------------------
    // v4 owner/public access model
    //
    // settings.key_value has two extra columns: owner (VARCHAR) and public
    // (BOOLEAN). A row with owner = NULL is a legacy row: it is treated as
    // owned by the super user and public, so everyone can read it but only the
    // super user can change it. The owner stored on write always comes from the
    // authenticated principal (JWT uid) — never from client input.
    // ---------------------------------------------------------------------

    /**
     * Reads keys visible to the caller. A super user sees every row; a sub user
     * sees its own rows, any public row, and legacy (owner IS NULL) rows.
     *
     * @param string|null $key Single key to fetch, or null to list all visible keys.
     * @param string $uid Authenticated principal (owner) — the JWT uid.
     * @param bool $isSuperUser
     * @param array<string>|null $paths Optional JSON projection: a list of paths,
     *        each a dot-separated segment sequence (e.g. "user.name"). Only the
     *        named sub-trees are returned, keyed by the path string. Every segment
     *        is bound as a parameter (never interpolated), so the paths are safe
     *        even though they are client-supplied.
     * @return array<mixed> Rows shaped as id,key,value,owner,public. A single-key
     *                      fetch returns one row or [] when not found/visible.
     * @throws Exception
     */
    public function getForUser(?string $key, string $uid, bool $isSuperUser, ?array $paths = null): array
    {
        $params = [];
        $valueSelect = "value";
        if (!empty($paths)) {
            $parts = [];
            foreach ($paths as $i => $path) {
                $segmentPlaceholders = [];
                foreach (explode('.', $path) as $j => $segment) {
                    $ph = "p_{$i}_{$j}";
                    $segmentPlaceholders[] = ":$ph";
                    $params[$ph] = $segment;
                }
                $keyPh = "pk_{$i}";
                $params[$keyPh] = $path;
                // #> takes a text[] path; both the object key (the path string) and
                // every segment are bound, so nothing client-supplied reaches the SQL text.
                $parts[] = ":$keyPh, value #> ARRAY[" . implode(",", $segmentPlaceholders) . "]::text[]";
            }
            $valueSelect = "json_build_object(" . implode(",", $parts) . ")";
        }
        $sql = "SELECT id, key, $valueSelect AS value, owner, public FROM settings.key_value WHERE 1=1";
        if ($key !== null) {
            $sql .= " AND key = :key";
            $params["key"] = $key;
        }
        if (!$isSuperUser) {
            $sql .= " AND (owner = :uid OR public = TRUE OR owner IS NULL)";
            $params["uid"] = $uid;
        }
        $sql .= " ORDER BY updated DESC, id DESC";

        $res = $this->prepare($sql);
        $res->execute($params);
        return $key !== null ? ($this->fetchRow($res) ?: []) : ($this->fetchAll($res, "assoc") ?: []);
    }

    /**
     * Inserts a new key owned by the caller. Fails with a 409 GC2Exception when
     * the (globally unique) key already exists.
     *
     * @return array<mixed> The inserted row (id,key,value,owner,public).
     * @throws GC2Exception
     */
    public function insertForUser(string $key, string $json, string $uid, bool $public): array
    {
        $sql = "INSERT INTO settings.key_value(key, value, owner, public) VALUES (:key, :value, :owner, :public) RETURNING id, key, value, owner, public";
        try {
            $res = $this->prepare($sql);
            // Emulated prepares bind PHP false as '' which Postgres rejects for
            // boolean, so pass an explicit 'true'/'false' literal.
            $res->execute(["key" => $key, "value" => $json, "owner" => $uid, "public" => $public ? 'true' : 'false']);
        } catch (PDOException $e) {
            if ($e->getCode() === "23505") {
                throw new GC2Exception("Key already exists", 409, null, "KEY_EXISTS");
            }
            throw $e;
        }
        return $this->fetchRow($res);
    }

    /**
     * Updates value and/or public flag on a key. A sub user may only touch its
     * own rows; a super user may touch any row. Ownership never changes here.
     *
     * @param string|null $json New JSON value, or null to leave it unchanged.
     * @param bool|null $public New public flag, or null to leave it unchanged.
     * @return array<mixed> The updated row.
     * @throws GC2Exception When no row is updated (not found, or not owned by a sub user).
     */
    public function updateForUser(string $key, ?string $json, ?bool $public, string $uid, bool $isSuperUser): array
    {
        $sets = ["updated = default"];
        $params = ["key" => $key];
        if ($json !== null) {
            $sets[] = "value = :value";
            $params["value"] = $json;
        }
        if ($public !== null) {
            $sets[] = "public = :public";
            // See insertForUser(): bind an explicit boolean literal, not PHP false.
            $params["public"] = $public ? 'true' : 'false';
        }
        $sql = "UPDATE settings.key_value SET " . implode(", ", $sets) . " WHERE key = :key";
        if (!$isSuperUser) {
            $sql .= " AND owner = :uid";
            $params["uid"] = $uid;
        }
        $sql .= " RETURNING id, key, value, owner, public";

        $res = $this->prepare($sql);
        $res->execute($params);
        $row = $this->fetchRow($res);
        if (empty($row)) {
            throw new GC2Exception("Not found", 404, null, "KEY_NOT_FOUND");
        }
        return $row;
    }

    /**
     * Deletes a key. A sub user may only delete its own rows; a super user may
     * delete any row.
     *
     * @throws GC2Exception When no row is deleted (not found, or not owned by a sub user).
     */
    public function deleteForUser(string $key, string $uid, bool $isSuperUser): void
    {
        $sql = "DELETE FROM settings.key_value WHERE key = :key";
        $params = ["key" => $key];
        if (!$isSuperUser) {
            $sql .= " AND owner = :uid";
            $params["uid"] = $uid;
        }
        $sql .= " RETURNING id";

        $res = $this->prepare($sql);
        $res->execute($params);
        if (empty($this->fetchRow($res))) {
            throw new GC2Exception("Not found", 404, null, "KEY_NOT_FOUND");
        }
    }

    /**
     * @param string|null $key
     * @param array<string> $urlVars
     * @return array<mixed>
     * @throws Exception
     */

    public function get(?string $key, array $urlVars): array
    {
        $params = [];
        $tmp = [];

        $fetchingAll = true;

        if ($key) {
            $fetchingAll = false;
        }

        if (isset($urlVars["paths"])) {
            $paths = explode(";", $urlVars["paths"]);

            foreach ($paths as $path) {
                $tmp[] = "'{$path}'::text,value#>'{{$path}}'";
            }
            $value = "json_build_object(" . implode(",", $tmp) . ") as value";

        } else {
            $value = "value";
        }

        if ($fetchingAll) {
            $sql = "SELECT id,key,{$value} FROM settings.key_value WHERE 1=1";
        } else {
            $sql = "SELECT id,key,{$value} FROM settings.key_value WHERE key=:key";
            $params["key"] = $key;
        }

        if (isset($urlVars["like"])) {
            $sql .= " AND key LIKE :where";
            $params["where"] = $urlVars["like"];
        }

        if (isset($urlVars["filter"])) {
            $parsedFilter = preg_replace("/'{\w+}'/", 'value#>>${0}', $urlVars["filter"]);
            $sql .= " AND {$parsedFilter}";
        }

        $sql .= " ORDER BY updated DESC, id DESC"; // Newest first in output

        if (strpos($sql, ';') !== false) {
            $response['success'] = false;
            $response['code'] = 403;
            $response['message'] = "You can't use ';'";
            return $response;
        }
        if (strpos($sql, '--') !== false) {
            $response['success'] = false;
            $response['code'] = 403;
            $response['message'] = "SQL comments '--' are not allowed";
            return $response;
        }
        $response = [];
        try {
            $res = $this->prepare($sql);

            $res->execute($params);

        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }

        if ($fetchingAll) {
            $response["data"] = $this->fetchAll($res, "assoc") ?: [];
        } else {
            $response["data"] = $this->fetchRow($res) ?: [];
        }
        // HACK get rid of unnecessary meta in Vidi snapshots
        if (isset($urlVars["like"]) && $urlVars["like"] == "state_snapshot_%") {
            if (!is_array($response["data"][0])) {
                $parsed = !empty($response["data"]["value"]) ? json_decode($response["data"]["value"], true) : [];
                unset($parsed["snapshot"]);
                if ($parsed)
                    $response["data"]["value"] = json_encode($parsed);
                else
                    $response["data"] = [];
            } else {
                foreach ($response["data"] as $key => $value) {
                    $parsed = json_decode($value["value"], true);
                    unset($parsed["snapshot"]);
                    if ($parsed)
                        $response["data"][$key]["value"] = json_encode($parsed);
                    else
                        $response["data"][$key] = [];
                }
            }
        }
        // HACK end

        $response["success"] = true;
        return $response;
    }

    /**
     * @param string $key
     * @param string $json
     * @return array<mixed>
     */
    public function insert(string $key, string $json): array
    {
        $response = [];
        if (!$key) {
            $response['success'] = false;
            $response['message'] = "Missing key";
            $response['code'] = 401;
            return $response;
        }
        $sql = "INSERT INTO settings.key_value(key, value) VALUES (:key, :value) RETURNING *";
        try {
            $res = $this->prepare($sql);
            $res->execute(["key" => $key, "value" => $json]);
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $response["data"] = $this->fetchRow($res);
        $response["success"] = true;
        return $response;
    }

    /**
     * @param string|null $key
     * @param string $json
     * @return array<mixed>
     */
    public function update(?string $key, string $json): array
    {
        $response = [];
        if (!$key) {
            $response['success'] = false;
            $response['message'] = "Missing key";
            $response['code'] = 401;
            return $response;
        }
        $sql = "UPDATE settings.key_value SET value=:value, updated=default WHERE key=:key RETURNING *";
        try {
            $res = $this->prepare($sql);
            $res->execute(["key" => $key, "value" => $json]);
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $response["data"] = $this->fetchRow($res);
        $response["success"] = true;
        return $response;
    }

    /**
     * @param string|null $key
     * @return array<mixed>
     */
    public function delete(?string $key): array
    {
        $response = [];
        if (!$key) {
            $response['success'] = false;
            $response['message'] = "Missing key";
            $response['code'] = 401;
            return $response;
        }
        $sql = "DELETE FROM settings.key_value WHERE key=:key";
        try {
            $res = $this->prepare($sql);
            $res->execute(["key" => $key]);
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 401;
            return $response;
        }
        $response["success"] = true;
        $response["data"] = $key;
        return $response;
    }
}