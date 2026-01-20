<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;


final class ClaimAcl
{
    public function __construct(
        private readonly array $customMap,
        private readonly array $defaultRules = [
            '__membership' => [],
            '__read' => [],
            '__write' => [],
        ]
    )
    {
    }

    /**
     * Return effective permissions for a table "schema.table".
     * The most specific claim-path wins (longest key path).
     * Write wins over read (and implies read).
     *
     * @return array{read: bool, write: bool, matched: array}
     */
    public function permissionsForTable(object $claims, string $table): array
    {
        $matches = $this->collectMatches($claims);

        $bestRead = $this->pickBest($matches, $table, '__read');
        $bestWrite = $this->pickBest($matches, $table, '__write');

        $writeAllowed = $bestWrite !== null;
        $readAllowed = $writeAllowed || ($bestRead !== null);

        $matched = array_values(array_filter([
            'read' => $bestRead,
            'write' => $bestWrite,
        ]));

        return [
            'read' => $readAllowed,
            'write' => $writeAllowed,
            'matched' => $matched,
        ];
    }

    public function canReadTable(object $claims, string $table): bool
    {
        return $this->permissionsForTable($claims, $table)['read'];
    }

    public function canWriteTable(object $claims, string $table): bool
    {
        return $this->permissionsForTable($claims, $table)['write'];
    }

    /**
     * Collect all rule blocks where:
     * - Claim-path exists and is "truthy" (or optionally matches something)
     * - Membership passes (if set)
     *
     * Each match has a specificity score = number of segments in the claim-path key.
     */
    private function collectMatches(object $claims): array
    {
        $out = [];

        foreach ($this->customMap as $key => $rules) {
            $segs = $this->splitPath($key);
            if (count($segs) < 2) continue;

            $claimKey = $segs[0];
            $matcher = $segs[1];
            $resourceSegs = array_slice($segs, 2); // hvis du vil bruge det senere

            // Claim match: array-contains, scalar equals, '*' wildcard
            if (!$this->claimMatches($claims, $claimKey, $matcher)) {
                continue;
            }

            $rules = array_merge($this->defaultRules, is_array($rules) ? $rules : []);

            $out[] = [
                'claimPath' => $key,
                'specificity' => count($resourceSegs), // eller count($segs) hvis du vil vægte claimKey/matcher også
                'rules' => $rules,
            ];
        }

        return $out;
    }


    /**
     * Pick the winner for a given op and table:
     * - Rule must list the table in __read or __write (or contain wildcard "*")
     * - Most specific claim-path wins
     * - Tie broken deterministically (lexicographic claimPath)
     */
    private function pickBest(array $matches, string $table, string $op): ?array
    {
        $candidates = [];

        foreach ($matches as $m) {
            $list = $m['rules'][$op] ?? [];

            if (!is_array($list)) {
                continue;
            }

            if ($this->tableInList($table, $list)) {
                $candidates[] = $m;
            }
        }

        if (empty($candidates)) {
            return null;
        }

        usort($candidates, function ($a, $b) {
            $cmp = $b['specificity'] <=> $a['specificity']; // higher first
            if ($cmp !== 0) return $cmp;
            return strcmp($a['claimPath'], $b['claimPath']); // stable order-independent tie-break
        });

        return $candidates[0];
    }

    private function tableInList(string $table, array $list): bool
    {
        if (in_array('*', $list, true)) {
            return true;
        }

        // Exact match
        if (in_array($table, $list, true)) {
            return true;
        }

        // Optional: support "schema.*"
        [$schema, $name] = array_pad(explode('.', $table, 2), 2, null);
        if ($schema !== null && in_array($schema . '.*', $list, true)) {
            return true;
        }

        return false;
    }

    private function getClaimByPath(object $claims, array $pathSegs): mixed
    {
        $cur = $claims;

        foreach ($pathSegs as $seg) {
            if (is_object($cur) && property_exists($cur, $seg)) {
                $cur = $cur->{$seg};
                continue;
            }
            if (is_array($cur) && array_key_exists($seg, $cur)) {
                $cur = $cur[$seg];
                continue;
            }
            return null; // path not found
        }

        return $cur;
    }

    private function splitPath(string $path): array
    {
        $path = trim($path);
        if ($path === '') return [];
        return array_values(array_filter(explode('->', $path), fn($s) => $s !== ''));
    }

    public function allTablePermissions(object $claims): array
    {
        $matches = $this->collectMatches($claims);

        // Kandidater pr tabel pr op, med specificity
        $candidates = []; // [table => ['__read' => [match...], '__write' => [match...]]]

        foreach ($matches as $m) {
            foreach (['__read', '__write'] as $op) {
                $list = $m['rules'][$op] ?? [];
                if (!is_array($list)) continue;

                foreach ($this->expandTableList($list) as $table) {
                    // Vi kan ikke enumerere "*" eller "*.table" sikkert uden table-catalog,
                    // så vi skipper dem her (se note nederst).
                    if ($table === '*' || str_contains($table, '*')) {
                        continue;
                    }

                    $candidates[$table][$op][] = $m;
                }
            }
        }

        $out = [];

        foreach ($candidates as $table => $perOp) {
            $bestRead = $this->pickBestAmong($perOp['__read'] ?? []);
            $bestWrite = $this->pickBestAmong($perOp['__write'] ?? []);

            $writeAllowed = $bestWrite !== null;
            $readAllowed = $writeAllowed || ($bestRead !== null);

            // "source" peger på vinderen (write hvis findes, ellers read)
            $source = $bestWrite['claimPath'] ?? ($bestRead['claimPath'] ?? null);

            $out[$table] = [
                'read' => $readAllowed,
                'write' => $writeAllowed,
                'source' => $source,
                'matched' => array_values(array_filter([
                    'read' => $bestRead,
                    'write' => $bestWrite,
                ])),
            ];
        }

        ksort($out);
        return $out;
    }

    public function allMemberships(object $claims): array
    {
        $memberships = [];

        foreach ($this->customMap as $key => $rules) {
            $segs = $this->splitPath($key);
            if (count($segs) < 2) {
                continue;
            }

            $claimKey = $segs[0];
            $matcher = $segs[1];

            // 1) Claim match (samme semantik som permissions)
            if (!$this->claimMatches($claims, $claimKey, $matcher)) {
                continue;
            }

            $memberships[] = [
                'key' => $key,
                'claim' => $claimKey,
                'matcher' => $matcher,
            ];
        }

        return $memberships;
    }

    public function allMembershipKeys(object $claims): array
    {
        return array_map(
            fn($m) => $m['key'],
            $this->allMemberships($claims)
        );
    }


    /**
     * Deterministic "most specific wins" among already-matching candidates.
     */
    private function pickBestAmong(array $candidates): ?array
    {
        if (empty($candidates)) return null;

        usort($candidates, function ($a, $b) {
            $cmp = $b['specificity'] <=> $a['specificity'];
            if ($cmp !== 0) return $cmp;
            return strcmp($a['claimPath'], $b['claimPath']);
        });

        return $candidates[0];
    }

    /**
     * Normalizer/expander for table lists.
     * Right now it returns the list as-is, but this is where you could:
     * - trim spaces
     * - normalize case
     * - support "schema.table" strings, etc.
     */
    private function expandTableList(array $list): array
    {
        $out = [];
        foreach ($list as $item) {
            if (!is_string($item)) continue;
            $item = trim($item);
            if ($item === '') continue;
            $out[] = $item;
        }
        return $out;
    }

    private function claimMatches(object $claims, string $claimKey, string $matcher): bool
    {
        if (!property_exists($claims, $claimKey)) {
            return false;
        }

        $val = $claims->{$claimKey};

        // Wildcard matcher
        if ($matcher === '*') {
            return true;
        }

        // Array claim (typical Keycloak groups / roles / org arrays)
        if (is_array($val)) {
            // exact membership
            return in_array($matcher, $val, true);
        }

        // Object claim
        if (is_object($val)) {
            // key existence match
            if (property_exists($val, $matcher)) {
                return true;
            }
            // or match any value (stringified)
            foreach (get_object_vars($val) as $v) {
                if ((string)$v === $matcher) {
                    return true;
                }
            }
            return false;
        }

        // Scalar claim
        return (string)$val === $matcher;
    }

}
