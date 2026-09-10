<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\Connection;
use app\inc\PublicIdentity;
use Codeception\Test\Unit;

class PublicIdentityTest extends Unit
{
    private function identity(bool $anonymous, string $user): PublicIdentity
    {
        return new PublicIdentity(
            connection: new Connection(user: $user, database: 'mydb', schema: 'public'),
            database: 'mydb', schema: 'public', user: $user, userGroup: null,
            parentUser: $user === 'mydb', trusted: false, bearer: null, anonymous: $anonymous,
        );
    }

    public function testAnonymousGeofenceUserIsWildcard(): void
    {
        // Anonymous requests carry the database name as connection user; the geofence must see "*"
        $this->assertSame('*', $this->identity(true, 'mydb')->geofenceUser());
    }

    public function testNamedGeofenceUserIsTheUser(): void
    {
        $this->assertSame('alice', $this->identity(false, 'alice')->geofenceUser());
    }

    public function testWfsContextCarriesIdentityAndSrs(): void
    {
        $ctx = $this->identity(false, 'alice')->wfsContext('roads', 25832);
        $this->assertSame('roads', $ctx->schema);
        $this->assertSame('alice', $ctx->user);
        $this->assertSame(25832, $ctx->srs);
        $this->assertFalse($ctx->parentUser);
        $this->assertFalse($ctx->tokenAuth);
    }

    public function testOwsContextCarriesIdentity(): void
    {
        $ctx = $this->identity(true, 'mydb')->owsContext('roads');
        $this->assertSame('mydb', $ctx->database);
        $this->assertSame('roads', $ctx->schema);
        $this->assertTrue($ctx->parentUser);
    }
}
