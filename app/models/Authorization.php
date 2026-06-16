<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Model;

class Authorization extends Model
{
    const string USED_RELS_KEY = "checked_relations";

    public function __construct(public ?Connection $connection)
    {
        parent::__construct(connection: $connection);
    }

    /**
     * Authorization check for API key access to a layer/relation.
     *
     * @param string $relName Relation name (optionally qualified with schema)
     * @param bool $transaction Whether this is a write/edit transaction
     * @param bool $isAuth Whether the request is authenticated
     * @param string|null $subUser Subuser identifier
     * @param array|null $userGroup User group identifier
     * @param array $rels Relations checked along the way
     *
     * @return array                   Structured response with success, code, and details
     * @throws GC2Exception On forbidden or insufficient privileges
     */
    public function check(string $relName, bool $transaction, bool $isAuth, ?string $subUser, ?array $userGroup, array $rels = []): array
    {
        // Ensure the relation is schema-qualified (default to public)
        $bits = explode('.', $relName);
        if (count($bits) === 1) {
            $schema = 'public';
            $unQualifiedName = $relName;
        } else {
            $schema = $bits[0];
            $unQualifiedName = $bits[1];
        }
        $qualifiedName = $schema . '.' . $unQualifiedName;
        $auth = $this->getGeometryColumns($qualifiedName, 'authentication');

        // Check if the relation is a real table/view. If so and authentication is not set, deny.
        try {
            $this->isTableOrView($qualifiedName);
            $isRelation = true;
        } catch (GC2Exception) {
            $isRelation = false;
        }
        if (empty($auth) && $isRelation) {
            throw new GC2Exception($qualifiedName . " is a relation, but authentication is not set. It might be that the relation is not registered.", 403);
        }

        if ($auth === "Read/write" || $auth === "Write") {
            $rows = $this->getColumns($schema, $unQualifiedName);
            foreach ($rows as $row) {
                // Ensure we operate on the correct layer from the database
                if ($row["f_table_schema"] != $schema || $row["f_table_name"] != $unQualifiedName) {
                    continue;
                }
                if ($subUser) {
                    $privileges = !empty($row["privileges"]) ? json_decode($row["privileges"], true) : [];
                    $response = [
                        'auth_level' => $auth,
                        self::USED_RELS_KEY => $rels,
                    ];
                    $extractedPrivilege = $this->extractHighestPrivilege($privileges, $subUser, $userGroup, $schema);
                    $hasNone = $extractedPrivilege['privilege'] === "none";
                    $isOwner = $extractedPrivilege['isOwner'];
                    $response['privileges'] = $extractedPrivilege['privilege'];
                    if ($isAuth) {
                        if (!$transaction) {
                            // Always let subusers read from layers open to all
                            if ($hasNone && !$isOwner) {
                                if ($auth === "Write") {
                                    return $this->success($response);
                                }
                                throw new GC2Exception("Insufficient privileges to select: $qualifiedName", 403, null, "INSUFFICIENT_PRIVILEGES");
                            }
                            return $this->success($response);
                        }
                        // transaction = write/edit
                        $insufficient = ($extractedPrivilege['privilege'] === "none" || $extractedPrivilege['privilege'] === "read");
                        if ($insufficient && !$isOwner) {
                            throw new GC2Exception("Insufficient privileges to insert/update/delete: $qualifiedName", 403, null, "INSUFFICIENT_PRIVILEGES");
                        }
                        return $this->success($response);
                    }
                    // Not authenticated
                    if ($auth === "Read/write" || $transaction) {
                        throw new GC2Exception("Forbidden", 403);
                    }
                    return $this->success($response);
                }

                // No subuser context
                $response = [
                    'auth_level' => $auth,
                    self::USED_RELS_KEY => $rels,
                ];

                if ($auth === "Read/write" || $transaction) {
                    if ($isAuth) {
                        return $this->success($response);
                    }
                    throw new GC2Exception("Forbidden", 403);
                }
                return $this->success($response);
            }

            // Fallback if no matching row found
            return $this->success([
                'auth_level' => $auth,
                self::USED_RELS_KEY => $rels,
            ]);
        }

        // For other auth levels (e.g., Read), return minimal info
        return $this->success([
            'auth_level' => $auth,
            self::USED_RELS_KEY => $rels,
        ]);
    }

    /**
     * Extracts the highest privilege level for a given user or group of users.
     *
     * @param array $privileges An associative array mapping users or groups to their privilege levels.
     * @param string $subUser The specific user whose privileges are being evaluated.
     * @param array|null $groups An optional array of groups to which the user belongs.
     * @param string $schema The identifier used to determine ownership.
     *
     * @return array An associative array containing:
     *               - 'privilege': the highest privilege level among the user and their groups.
     *               - 'isOwner': a boolean indicating whether the user or any group they belong to is the owner.
     */
    public function extractHighestPrivilege(array $privileges, string $subUser, ?array $groups, string $schema): array
    {
        $values[] = $privileges[$subUser] ?? 'none';
        $isOwner = $subUser === $schema;
        if (is_array($groups) && count($groups) > 0) {
            foreach ($groups as $group) {
                $values[] = $privileges[$group] ?? 'none';
                $isOwner = $isOwner ?: $group === $schema;
            }
        }
        $rank = [
            'none'  => 0,
            'read'  => 1,
            'write' => 2,
        ];
        $highest = array_reduce(
            $values,
            fn($carry, $item) => $rank[$item] > $rank[$carry] ? $item : $carry,
            'none'
        );

        $res['privilege'] = $highest;
        $res['isOwner'] = $isOwner;
        return $res;
    }

    private function success(array $data): array
    {
        $data['success'] = true;
        $data['code'] = 200;
        return $data;
    }
}