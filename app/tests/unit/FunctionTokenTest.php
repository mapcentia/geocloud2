<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\inc\FunctionToken;
use Codeception\Test\Unit;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class FunctionTokenTest extends Unit
{
    protected UnitTester $tester;

    public function testMintProducesScopedAccessToken(): void
    {
        $secret = 'b5dc23d9b4811bd90737e2f65b9ddbd5'; // 32 chars = 256-bit HS256 key
        $jwtData = [
            'uid' => 'alice',
            'database' => 'alice_db',
            'superUser' => false,
            'userGroup' => 'staff',
            'email' => 'alice@example.com',
            'properties' => null,
        ];
        $token = FunctionToken::mintWithSecret($secret, $jwtData, 'resizeImage', 60);
        $decoded = (array)JWT::decode($token, new Key($secret, 'HS256'));

        // Carries the invoking user's identity, not a privilege escalation.
        $this->assertEquals('alice', $decoded['uid']);
        $this->assertEquals('alice_db', $decoded['database']);
        $this->assertFalse($decoded['superUser']);
        $this->assertEquals('staff', $decoded['userGroup']);
        // Must validate as an access token (Jwt::validate requires this).
        $this->assertEquals('token', $decoded['response_type']);
        // Auditable marker.
        $this->assertEquals('resizeImage', $decoded['function']);
        // Short-lived, ~60s.
        $this->assertEqualsWithDelta(time() + 60, $decoded['exp'], 5);
    }

    public function testTtlIsClampedToBounds(): void
    {
        $long = (array)JWT::decode(
            FunctionToken::mintWithSecret(str_repeat('a', 32), ['uid' => 'u', 'database' => 'd'], 'fn', 99999),
            new Key(str_repeat('a', 32), 'HS256')
        );
        $this->assertEqualsWithDelta(time() + FunctionToken::MAX_TTL, $long['exp'], 5);

        $short = (array)JWT::decode(
            FunctionToken::mintWithSecret(str_repeat('a', 32), ['uid' => 'u', 'database' => 'd'], 'fn', 1),
            new Key(str_repeat('a', 32), 'HS256')
        );
        $this->assertEqualsWithDelta(time() + FunctionToken::MIN_TTL, $short['exp'], 5);
    }

    public function testWrongSecretIsRejected(): void
    {
        $token = FunctionToken::mintWithSecret(str_repeat('r', 32), ['uid' => 'u', 'database' => 'd'], 'fn', 60);
        $this->expectException(\Firebase\JWT\SignatureInvalidException::class);
        JWT::decode($token, new Key(str_repeat('w', 32), 'HS256'));
    }
}
