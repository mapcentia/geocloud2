<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

/**
 * Structural type inference for functions. Unlike SQL methods (whose columns
 * have known PG types), functions are opaque code, so we infer a JSON type tree
 * from a real event/result captured during a dry-run, then render it to
 * TypeScript for the generated client (see /api/v4/function-interfaces).
 */
abstract class FunctionSchema
{
    /**
     * Infer a compact type tree from a decoded JSON value.
     *
     * @return array{type: string, items?: array, properties?: array}
     */
    public static function infer(mixed $value): array
    {
        if (is_null($value)) {
            return ['type' => 'null'];
        }
        if (is_bool($value)) {
            return ['type' => 'boolean'];
        }
        if (is_int($value) || is_float($value)) {
            return ['type' => 'number'];
        }
        if (is_string($value)) {
            return ['type' => 'string'];
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                return [
                    'type' => 'array',
                    'items' => count($value) ? self::infer($value[0]) : ['type' => 'unknown'],
                ];
            }
            $properties = [];
            foreach ($value as $k => $v) {
                $properties[(string)$k] = self::infer($v);
            }
            return ['type' => 'object', 'properties' => $properties];
        }
        return ['type' => 'unknown'];
    }

    /**
     * Render an inferred type tree to a TypeScript type expression.
     */
    public static function toTypeScript(?array $schema): string
    {
        if (!$schema || !isset($schema['type'])) {
            return 'unknown';
        }
        return match ($schema['type']) {
            'null' => 'null',
            'boolean' => 'boolean',
            'number' => 'number',
            'string' => 'string',
            'array' => self::toTypeScript($schema['items'] ?? null) . '[]',
            'object' => self::objectToTypeScript($schema['properties'] ?? []),
            default => 'unknown',
        };
    }

    private static function objectToTypeScript(array $properties): string
    {
        if (!$properties) {
            return 'Record<string, unknown>';
        }
        $parts = [];
        foreach ($properties as $key => $sub) {
            $name = preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/', (string)$key)
                ? (string)$key
                : json_encode((string)$key);
            $parts[] = $name . ': ' . self::toTypeScript($sub);
        }
        return '{ ' . implode('; ', $parts) . ' }';
    }
}
