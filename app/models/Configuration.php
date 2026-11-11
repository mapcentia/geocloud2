<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Model;

/**
 * Vidi configurations stored in settings.key_value.
 *
 * Rows are shared with the v2 Configuration API and the Dashboard, so the
 * stored shape is fixed: key = configuration_<name>_<uniqid>, value = a JSON
 * object {name, published, description, body, key}, owner left NULL. `body`
 * is a string holding JSON text and is stored and returned byte for byte.
 *
 * Only rows with owner IS NULL are configurations. v2 and this model never
 * set an owner, while the v4 keyvalue API always does, so a row a sub-user
 * writes there under the prefix is not picked up as a configuration.
 */
class Configuration extends Model
{
    public const string PREFIX = 'configuration_';
    public const string KEY_PATTERN = '/^configuration_[A-Za-z0-9_@+\-]+$/D';

    /** LIKE pattern matching the prefix; the underscore is escaped. */
    private const string LIKE = 'configuration\_%';

    private const array FIELDS = ['name', 'published', 'description', 'body'];

    function __construct(?Connection $connection = null)
    {
        parent::__construct(connection: $connection);
    }

    public static function isValidKey(string $key): bool
    {
        return preg_match(self::KEY_PATTERN, $key) === 1;
    }

    /**
     * @throws GC2Exception
     */
    public static function assertKey(string $key): void
    {
        if (!self::isValidKey($key)) {
            throw new GC2Exception("Invalid configuration key", 400, null, "INVALID_REQUEST");
        }
    }

    /**
     * Builds a key the way v2 does, then drops every character outside
     * [A-Za-z0-9_] (v2 only drops the dot, which lets e-mail-shaped names leak
     * '@', '+' and '-' into the key).
     */
    public static function generateKey(string $name): string
    {
        $key = uniqid(self::PREFIX . self::toAscii($name, null, '_') . '_', true);
        return preg_replace('/[^A-Za-z0-9_]/', '', $key);
    }

    /**
     * `body` must be a string holding JSON that decodes to an object or an
     * array. The Dashboard runs an unguarded JSON.parse on it.
     *
     * @throws GC2Exception
     */
    public static function assertBody(mixed $body): void
    {
        if (!is_string($body) || !is_array(json_decode($body, true))) {
            throw new GC2Exception("'body' must be a string holding a JSON object or array", 400, null, "INVALID_REQUEST");
        }
    }

    /**
     * Shapes a settings.key_value row as a configuration, or null when the
     * row is not one (foreign value under the prefix, or a key outside the
     * accepted pattern).
     *
     * @param array<string,mixed> $row
     * @return array{key: string, name: string, description: string, published: bool, body: string}|null
     */
    public static function decode(array $row): ?array
    {
        if (!self::isValidKey((string)$row['key'])) {
            return null;
        }
        $value = json_decode((string)$row['value'], true);
        if (!is_array($value) || !is_string($value['name'] ?? null) || !is_string($value['body'] ?? null)) {
            return null;
        }
        return [
            'key' => $row['key'],
            'name' => $value['name'],
            'description' => is_string($value['description'] ?? null) ? $value['description'] : '',
            'published' => (bool)($value['published'] ?? false),
            'body' => $value['body'],
        ];
    }

    /**
     * Overlays the patchable fields on a stored value; everything else,
     * including `key`, is kept.
     *
     * @param array<string,mixed> $stored
     * @param array<string,mixed> $patch
     * @return array<string,mixed>
     */
    public static function merge(array $stored, array $patch): array
    {
        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $patch)) {
                $stored[$field] = $patch[$field];
            }
        }
        return $stored;
    }

    /**
     * @return array<int,array<string,mixed>> Newest first.
     */
    public function list(bool $publishedOnly): array
    {
        $sql = "SELECT key, value FROM settings.key_value WHERE key LIKE :like AND owner IS NULL ORDER BY updated DESC, id DESC";
        $res = $this->prepare($sql);
        $this->execute($res, ['like' => self::LIKE]);
        $out = [];
        foreach ($this->fetchAll($res, 'assoc') as $row) {
            $configuration = self::decode($row);
            if ($configuration === null || ($publishedOnly && !$configuration['published'])) {
                continue;
            }
            $out[] = $configuration;
        }
        return $out;
    }

    /**
     * Missing, foreign and (when $publishedOnly) unpublished rows are
     * indistinguishable to the caller.
     *
     * @return array<string,mixed>
     * @throws GC2Exception
     */
    public function get(string $key, bool $publishedOnly): array
    {
        self::assertKey($key);
        $row = $this->fetchStored($key);
        $configuration = $row ? self::decode($row) : null;
        if ($configuration === null || ($publishedOnly && !$configuration['published'])) {
            throw new GC2Exception("Configuration not found", 404, null, "CONFIGURATION_NOT_FOUND");
        }
        return $configuration;
    }

    /**
     * @param array<string,mixed> $data name, published, description, body
     * @return string The new key.
     * @throws GC2Exception
     */
    public function create(array $data): string
    {
        self::assertBody($data['body'] ?? null);
        $key = self::generateKey((string)$data['name']);
        $value = [
            'name' => $data['name'],
            'published' => (bool)$data['published'],
            'description' => $data['description'],
            'body' => $data['body'],
            'key' => $key,
        ];
        // owner and public are left at their defaults, as v2 leaves them
        $res = $this->prepare("INSERT INTO settings.key_value(key, value) VALUES (:key, :value)");
        $this->execute($res, ['key' => $key, 'value' => json_encode($value)]);
        return $key;
    }

    /**
     * @param array<string,mixed> $data Any subset of name, published, description, body.
     * @throws GC2Exception
     */
    public function patch(string $key, array $data): void
    {
        $this->get($key, false);
        if (array_key_exists('body', $data)) {
            self::assertBody($data['body']);
        }
        $stored = json_decode((string)$this->fetchStored($key)['value'], true);
        $stored['key'] = $key;
        $sql = "UPDATE settings.key_value SET value = :value, updated = default WHERE key = :key AND key LIKE :like AND owner IS NULL";
        $res = $this->prepare($sql);
        $this->execute($res, ['key' => $key, 'like' => self::LIKE, 'value' => json_encode(self::merge($stored, $data))]);
    }

    /**
     * Every key is checked before any row is deleted.
     *
     * @param array<string> $keys
     * @throws GC2Exception
     */
    public function remove(array $keys): void
    {
        $keys = array_values(array_unique($keys));
        foreach ($keys as $key) {
            $this->get($key, false);
        }
        $res = $this->prepare("DELETE FROM settings.key_value WHERE key = :key AND key LIKE :like AND owner IS NULL");
        foreach ($keys as $key) {
            $this->execute($res, ['key' => $key, 'like' => self::LIKE]);
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchStored(string $key): ?array
    {
        $res = $this->prepare("SELECT key, value FROM settings.key_value WHERE key = :key AND key LIKE :like AND owner IS NULL");
        $this->execute($res, ['key' => $key, 'like' => self::LIKE]);
        return $this->fetchRow($res) ?: null;
    }
}
