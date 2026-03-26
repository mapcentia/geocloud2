<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\inc\ClaimAcl;
use Codeception\Test\Unit;

class ClaimAclTest extends Unit
{
    protected ClaimAcl $auth;
    protected stdClass $claims;

    protected function _before(): void
    {
        $this->claims = (object)[
            "groups" => [
                "_sec_GPkort_Read",
                "_sec_GPkort_User",
                "_sec_GPkort_Write"
            ],
            "roles" => ["admin", "editor"],
            "user" => "martin",
            "nested" => (object)[
                "attr" => "value"
            ]
        ];

        $customMap = [
            "groups->_sec_GPkort_Write" => [
                "__read" => ["schema1.table1", "schema1.table2"],
                "__write" => ["schema1.table1"]
            ],
            "groups->_sec_GPkort_Read" => [
                "__read" => ["schema1.table3"],
            ],
            "roles->admin" => [
                "__read" => ["*"],
                "__write" => ["*"]
            ],
            "roles->editor" => [
                "__read" => ["schema2.*"],
                "__write" => ["schema2.table1"]
            ],
            "user->martin" => [
                "__read" => ["schema3.table1"]
            ],
            "nested->attr->value" => [
                "__read" => ["schema4.table1"]
            ],
            "__default" => [
                "__read" => ["public.table1"],
                "__write" => []
            ]
        ];
        $this->auth = new ClaimAcl($customMap);
    }

    public function testExactTableMatchWinsOverWildcard(): void
    {
        // Exact table listing in a rule takes precedence over wildcard (*) from another rule.
        // groups->_sec_GPkort_Write explicitly lists schema1.table2 in __read but NOT in __write,
        // so the explicit rule governs — even though roles->admin has __write: ["*"].
        $perm = $this->auth->permissionsForTable($this->claims, "schema1.table1");
        $this->assertTrue($perm['read'], "Exact match: read access to schema1.table1");
        $this->assertTrue($perm['write'], "Exact match: write access to schema1.table1");

        $perm = $this->auth->permissionsForTable($this->claims, "schema1.table2");
        $this->assertTrue($perm['read'], "Exact match: read access to schema1.table2");
        $this->assertFalse($perm['write'], "Exact match governs: no write to schema1.table2 despite admin wildcard");
    }

    public function testWildcardMatchesUnknownTables(): void
    {
        // Wildcard (*) matches any table not explicitly listed elsewhere.
        $perm = $this->auth->permissionsForTable($this->claims, "any.table");
        $this->assertTrue($perm['read'], "Admin wildcard: read any unknown table");
        $this->assertTrue($perm['write'], "Admin wildcard: write any unknown table");

        $perm = $this->auth->permissionsForTable($this->claims, "nonexistent.table");
        $this->assertTrue($perm['read'], "Admin wildcard: read nonexistent table");
        $this->assertTrue($perm['write'], "Admin wildcard: write nonexistent table");
    }

    public function testSchemaWildcard(): void
    {
        // Schema wildcard (schema2.*) is more specific than full wildcard (*),
        // so roles->editor governs tables in schema2.
        $perm = $this->auth->permissionsForTable($this->claims, "schema2.any_table");
        $this->assertTrue($perm['read'], "Editor: read any table in schema2");
        $this->assertFalse($perm['write'], "Editor: no write to schema2.any_table");

        // Exact table listing within the same rule still grants write.
        $perm = $this->auth->permissionsForTable($this->claims, "schema2.table1");
        $this->assertTrue($perm['read']);
        $this->assertTrue($perm['write']);
    }

    public function testDefaultRules(): void
    {
        // When no claim matches, __default rules apply.
        $noMatchClaims = (object)["groups" => ["none"]];

        $perm = $this->auth->permissionsForTable($noMatchClaims, "public.table1");
        $this->assertTrue($perm['read'], "Default: read public.table1");
        $this->assertFalse($perm['write'], "Default: no write to public.table1");

        $perm = $this->auth->permissionsForTable($noMatchClaims, "other.table");
        $this->assertFalse($perm['read'], "Default: no read for unlisted table");
        $this->assertFalse($perm['write'], "Default: no write for unlisted table");
    }

    public function testCanReadAndWriteTable(): void
    {
        $this->assertTrue($this->auth->canReadTable($this->claims, "schema1.table1"));
        $this->assertTrue($this->auth->canWriteTable($this->claims, "schema1.table1"));

        $this->assertTrue($this->auth->canReadTable($this->claims, "schema1.table2"));
        $this->assertFalse($this->auth->canWriteTable($this->claims, "schema1.table2"));

        // Wildcard grants access to unknown tables
        $this->assertTrue($this->auth->canReadTable($this->claims, "nonexistent.table"));
        $this->assertTrue($this->auth->canWriteTable($this->claims, "nonexistent.table"));
    }

    public function testClaimSpecificity(): void
    {
        // Longer claim paths supersede shorter ones within the same (claimKey, matcher) group.
        // "groups->A->B" (specificity 1) supersedes "groups->A" (specificity 0).
        $customMap = [
            "groups->A" => [
                "__read" => ["table1"],
            ],
            "groups->A->B" => [
                "__read" => ["table2"],
            ]
        ];

        $auth = new ClaimAcl($customMap);
        $claims = (object)["groups" => ["A"]];

        $perm = $auth->permissionsForTable($claims, "table2");
        $this->assertTrue($perm['read'], "Higher specificity rule grants access to table2");

        $perm = $auth->permissionsForTable($claims, "table1");
        $this->assertFalse($perm['read'], "Lower specificity rule is superseded — table1 not accessible");
    }

    public function testClaimSpecificityDifferentMatchers(): void
    {
        // Rules with different matchers do NOT supersede each other,
        // even if they share the same claimKey.
        $customMap = [
            "groups->A" => [
                "__read" => ["table1"],
            ],
            "groups->B" => [
                "__read" => ["table2"],
            ]
        ];

        $auth = new ClaimAcl($customMap);
        $claims = (object)["groups" => ["A", "B"]];

        $perm = $auth->permissionsForTable($claims, "table1");
        $this->assertTrue($perm['read'], "groups->A still active (different matcher than groups->B)");

        $perm = $auth->permissionsForTable($claims, "table2");
        $this->assertTrue($perm['read'], "groups->B still active (different matcher than groups->A)");
    }

    public function testNoMatchingClaims(): void
    {
        // Claims that match no rule and no default → no access.
        $customMap = [
            "groups->X" => [
                "__read" => ["table1"],
            ],
        ];

        $auth = new ClaimAcl($customMap);
        $claims = (object)["groups" => ["Y"]];

        $perm = $auth->permissionsForTable($claims, "table1");
        $this->assertFalse($perm['read']);
        $this->assertFalse($perm['write']);
    }

    public function testWriteImpliesRead(): void
    {
        $customMap = [
            "roles->writer" => [
                "__write" => ["table1"],
            ],
        ];

        $auth = new ClaimAcl($customMap);
        $claims = (object)["roles" => ["writer"]];

        $perm = $auth->permissionsForTable($claims, "table1");
        $this->assertTrue($perm['read'], "Write access implies read access");
        $this->assertTrue($perm['write']);
    }

    public function testAllMembershipKeys(): void
    {
        $keys = $this->auth->allMembershipKeys($this->claims);
        $this->assertContains("groups->_sec_GPkort_Write", $keys);
        $this->assertContains("groups->_sec_GPkort_Read", $keys);
        $this->assertContains("roles->admin", $keys);
        $this->assertContains("roles->editor", $keys);
        $this->assertContains("user->martin", $keys);
        $this->assertContains("nested->attr->value", $keys);
    }

    public function testAllTablePermissions(): void
    {
        $all = $this->auth->allTablePermissions($this->claims);

        $this->assertArrayHasKey("schema1.table1", $all);
        $this->assertTrue($all["schema1.table1"]["read"]);
        $this->assertTrue($all["schema1.table1"]["write"]);

        $this->assertArrayHasKey("schema1.table2", $all);
        $this->assertTrue($all["schema1.table2"]["read"]);
        $this->assertFalse($all["schema1.table2"]["write"]);

        $this->assertArrayHasKey("schema1.table3", $all);
        $this->assertTrue($all["schema1.table3"]["read"]);

        // Wildcards cannot be enumerated — not in results
        $this->assertArrayNotHasKey("any.table", $all);
    }
}
