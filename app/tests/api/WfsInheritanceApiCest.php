<?php

use Codeception\Util\HttpCode;

/**
 * Tests "Full Inheritance" for HTTP Basic auth against the WFS endpoint:
 * a subuser inherits the HIGHEST ("best parent") layer privilege across ALL of
 * its groups, transitively (multiple + deep inheritance), as computed by
 * User::getFullInheritance + Authorization::extractHighestPrivilege and enforced
 * in BasicAuth::authenticate.
 *
 * Groups in GC2 are themselves subuser accounts; a user's `usergroup` JSONB
 * array names the groups it belongs to. Per-layer privileges are stored keyed
 * by user/group name, so a group is granted a privilege the same way a subuser
 * is. The layer is Read/write, so per-layer Basic auth fires for both reads
 * (GetFeature) and writes (Transaction).
 */
class WfsInheritanceApiCest
{
    private $date;
    private $password;
    private $userId;      // owner / database
    private $token;       // owner bearer token
    private $schemaName;

    private $authUser;    // the authenticating subuser (no direct privilege)
    private $grpRw;       // group with read/write on the layer
    private $grpR;        // group with read on the layer
    private $grpNone;     // group with no privilege
    private $grpMid;      // group with no privilege, member of grpGrand
    private $grpGrand;    // group with read/write, two levels above authUser

    public function __construct()
    {
        $this->date = new DateTime();
        $this->password = 'A1abcabcabc';
        $ts = $this->date->getTimestamp();
        $this->schemaName = 'wfs_inh_test_' . $ts;
    }

    private function endpoint(): string
    {
        return '/api/v4/wfs/schema/' . $this->schemaName . '/database/' . $this->userId . '/srs/4326';
    }

    private function transactionXml(string $name): string
    {
        $ns = 'http://localhost/' . $this->userId . '/' . $this->schemaName;
        return '<Transaction xmlns="http://www.opengis.net/wfs" service="WFS" version="1.1.0"
             xmlns:gml="http://www.opengis.net/gml">
                <Insert xmlns="http://www.opengis.net/wfs">
                    <poi xmlns="' . $ns . '">
                        <name xmlns="' . $ns . '">' . $name . '</name>
                        <the_geom xmlns="' . $ns . '">
                            <gml:Point srsName="urn:ogc:def:crs:EPSG::4326"><gml:pos>55.7 9.5</gml:pos></gml:Point>
                        </the_geom>
                    </poi>
                </Insert>
            </Transaction>';
    }

    /** Create a subuser under the owner and return its screen name. */
    private function createSubUser(ApiTester $I, string $ownerCookie, string $label): string
    {
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $ownerCookie);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => $label . ' ' . $this->date->getTimestamp() . '_' . uniqid(),
            'email' => $label . $this->date->getTimestamp() . uniqid() . '@example.com',
            'password' => $this->password,
            'subuser' => true,
            'createschema' => true,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $id = json_decode($I->grabResponse())->data->screenname;
        $I->deleteHeader('Cookie');
        return $id;
    }

    /** Grant a per-layer privilege to a user/group name (owner session). */
    private function grantPrivilege(ApiTester $I, string $ownerCookie, string $name, string $privilege): void
    {
        $I->deleteHeader('Authorization');
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $ownerCookie);
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPUT('/controllers/layer/privileges', json_encode([
            'data' => [
                'subuser' => $name,
                'privileges' => $privilege,
                '_key_' => $this->schemaName . '.poi.the_geom',
            ],
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Cookie');
    }

    /** Set a subuser's group membership (owner bearer token). Groups are a JSON-string array. */
    private function setGroups(ApiTester $I, string $name, array $groups): void
    {
        $I->deleteHeader('Authorization');
        $I->deleteHeader('Cookie');
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendPATCH('/api/v4/users/' . $name, json_encode(['user_group' => json_encode($groups)]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Authorization');
    }

    private function ownerSession(ApiTester $I): string
    {
        $I->deleteHeader('Authorization');
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => $this->schemaName,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        return $I->capturePHPSESSID();
    }

    public function shouldPrepare(ApiTester $I)
    {
        // Owner + token
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => 'Wfs inh owner ' . $this->date->getTimestamp(),
            'email' => 'wfsinhowner' . $this->date->getTimestamp() . '@example.com',
            'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;

        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $this->userId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;

        // Schema + table (gid PK so WFS-T can insert) + one seeded row
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => $this->schemaName]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables', json_encode([
            'name' => 'poi',
            'columns' => [
                ['name' => 'gid', 'type' => 'serial'],
                ['name' => 'name', 'type' => 'varchar'],
                ['name' => 'the_geom', 'type' => 'geometry(Point,4326)'],
            ],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/schemas/' . $this->schemaName . '/tables/poi/constraints', json_encode([
            'constraint' => 'primary', 'columns' => ['gid'],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/sql', json_encode([
            'q' => "INSERT INTO " . $this->schemaName . ".poi (name, the_geom) "
                . "VALUES ('alpha', ST_SetSRID(ST_MakePoint(9.5, 55.7), 4326))",
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Authorization');

        // Owner session: set the layer to Read/write and the owner viewer password.
        $ownerCookie = $this->ownerSession($I);
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $ownerCookie);
        $I->sendPUT('/controllers/layer/records/' . $this->schemaName . '.poi.the_geom', json_encode([
            'data' => ['authentication' => 'Read/write', '_key_' => $this->schemaName . '.poi.the_geom'],
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPUT('/controllers/setting/pw', 'pw=' . $this->password);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Cookie');

        // Create the authenticating subuser and the group subusers.
        $ownerCookie = $this->ownerSession($I);
        $this->authUser = $this->createSubUser($I, $ownerCookie, 'auth');
        $this->grpRw = $this->createSubUser($I, $ownerCookie, 'grpRw');
        $this->grpR = $this->createSubUser($I, $ownerCookie, 'grpR');
        $this->grpNone = $this->createSubUser($I, $ownerCookie, 'grpNone');
        $this->grpMid = $this->createSubUser($I, $ownerCookie, 'grpMid');
        $this->grpGrand = $this->createSubUser($I, $ownerCookie, 'grpGrand');

        // Grant per-layer privileges to the groups (not to authUser directly).
        $ownerCookie = $this->ownerSession($I);
        $this->grantPrivilege($I, $ownerCookie, $this->grpRw, 'read/write');
        $ownerCookie = $this->ownerSession($I);
        $this->grantPrivilege($I, $ownerCookie, $this->grpR, 'read');
        $ownerCookie = $this->ownerSession($I);
        $this->grantPrivilege($I, $ownerCookie, $this->grpGrand, 'read/write');

        // Transitive chain: grpMid inherits from grpGrand (read/write two levels up).
        $this->setGroups($I, $this->grpMid, [$this->grpGrand]);

        // Set the authenticating subuser's viewer password (via its own session).
        $I->deleteHeader('Authorization');
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->authUser, 'password' => $this->password, 'schema' => 'public',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $authCookie = $I->capturePHPSESSID();
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $authCookie);
        $I->haveHttpHeader('Content-Type', 'application/x-www-form-urlencoded');
        $I->sendPUT('/controllers/setting/pw', 'pw=' . $this->password);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Cookie');
    }

    private function getFeature(ApiTester $I): string
    {
        $I->amHttpAuthenticated($this->authUser, $this->password);
        $I->sendGET($this->endpoint() . '?SERVICE=WFS&REQUEST=GetFeature&VERSION=1.1.0&TYPENAME=poi');
        $I->seeResponseCodeIs(HttpCode::OK);
        return $I->grabResponse();
    }

    private function insert(ApiTester $I, string $name): string
    {
        $I->amHttpAuthenticated($this->authUser, $this->password);
        $I->haveHttpHeader('Content-Type', 'application/xml');
        $I->sendPOST($this->endpoint(), $this->transactionXml($name));
        $I->seeResponseCodeIs(HttpCode::OK);
        return $I->grabResponse();
    }

    // Best parent = read/write (from two groups, one read one read/write):
    // both read and write succeed.
    public function shouldAllowReadAndWriteViaBestParentReadWrite(ApiTester $I)
    {
        $this->setGroups($I, $this->authUser, [$this->grpR, $this->grpRw]);

        $read = $this->getFeature($I);
        $I->assertStringContainsString('FeatureCollection', $read);
        $I->assertStringContainsString('alpha', $read);
        $I->assertStringNotContainsStringIgnoringCase('privileges', $read);

        $write = $this->insert($I, 'inh_rw');
        $I->assertStringContainsString('totalInserted>1', $write);
        $I->assertStringNotContainsStringIgnoringCase('privileges', $write);
    }

    // Best parent = read (groups read + none): read succeeds, write is denied.
    public function shouldAllowReadButDenyWriteViaBestParentRead(ApiTester $I)
    {
        $this->setGroups($I, $this->authUser, [$this->grpR, $this->grpNone]);

        $read = $this->getFeature($I);
        $I->assertStringContainsString('FeatureCollection', $read);
        $I->assertStringContainsString('alpha', $read);
        $I->assertStringNotContainsStringIgnoringCase('privileges', $read);

        $write = $this->insert($I, 'inh_r_denied');
        $I->assertStringContainsStringIgnoringCase('privileges', $write);
        $I->assertStringNotContainsString('totalInserted>1', $write);
    }

    // Transitive/deep inheritance: authUser -> grpMid -> grpGrand (read/write).
    // The privilege is on a grandparent, two levels up.
    public function shouldInheritReadWriteTransitivelyFromGrandparent(ApiTester $I)
    {
        $this->setGroups($I, $this->authUser, [$this->grpMid]);

        $read = $this->getFeature($I);
        $I->assertStringContainsString('FeatureCollection', $read);
        $I->assertStringNotContainsStringIgnoringCase('privileges', $read);

        $write = $this->insert($I, 'inh_transitive');
        $I->assertStringContainsString('totalInserted>1', $write);
        $I->assertStringNotContainsStringIgnoringCase('privileges', $write);
    }

    // No group grants anything: read is denied.
    public function shouldDenyWhenNoGroupGrantsPrivilege(ApiTester $I)
    {
        $this->setGroups($I, $this->authUser, [$this->grpNone]);

        $read = $this->getFeature($I);
        $I->assertStringContainsStringIgnoringCase('privileges', $read);
        $I->assertStringNotContainsString('FeatureCollection', $read);
    }
}
