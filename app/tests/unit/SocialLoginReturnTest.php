<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\auth\api\controllers\AbstractSocialLogin;
use app\inc\RedirectUri;
use Codeception\Test\Unit;

/**
 * Where a social login sends the browser once the provider has answered.
 */
class SocialLoginReturnTest extends Unit
{
    public function testDeviceFlowReturnsToDevicePage(): void
    {
        $this->assertSame('/device', AbstractSocialLogin::returnLocation('device', 'action=device', null));
    }

    public function testDeviceFlowIgnoresRedirectUri(): void
    {
        $this->assertSame('/device', AbstractSocialLogin::returnLocation('device', '', 'https://example.com/'));
    }

    public function testSigninReturnsToAuthWithItsQuery(): void
    {
        $this->assertSame('/auth?client_id=x&action=signin',
            AbstractSocialLogin::returnLocation('signin', 'client_id=x&action=signin', null));
        $this->assertSame('/auth', AbstractSocialLogin::returnLocation('signin', '', null));
    }

    public function testOtherActionsKeepTheirRedirectUri(): void
    {
        $this->assertSame('https://example.com/cb', AbstractSocialLogin::returnLocation('signup', 'a=b', 'https://example.com/cb'));
        $this->assertSame('/auth', AbstractSocialLogin::returnLocation(null, 'a=b', null));
    }

    private function allowed(?string $uri, array $registered = []): bool
    {
        return RedirectUri::isAllowed($uri, $registered, 'https://api.example.com', '.example.com', ['partner.test']);
    }

    public function testRedirectToForeignSiteIsRefused(): void
    {
        $this->assertFalse($this->allowed('https://evil.test/'));
        $this->assertFalse($this->allowed('https://api.example.com.evil.test/'));
        $this->assertFalse($this->allowed('https://evilexample.com/'));
        $this->assertFalse($this->allowed('//evil.test/'));
        $this->assertFalse($this->allowed('/\\evil.test/'));
        $this->assertFalse($this->allowed('javascript:alert(1)'));
        $this->assertFalse($this->allowed('https://api.example.com@evil.test/'));
    }

    public function testLocalPathAndOwnHostsAreAllowed(): void
    {
        $this->assertTrue($this->allowed('/app/'));
        $this->assertTrue($this->allowed('https://api.example.com/app'));
        $this->assertTrue($this->allowed('https://app.example.com/start'));
        $this->assertTrue($this->allowed('https://example.com/'));
        $this->assertTrue($this->allowed('https://partner.test/cb'));
    }

    public function testRegisteredClientUriIsAllowedByExactMatch(): void
    {
        $this->assertTrue($this->allowed('https://other.test/cb', ['https://other.test/cb']));
        $this->assertFalse($this->allowed('https://other.test/cb2', ['https://other.test/cb']));
    }

    public function testWithoutSessionDomainOnlyTheHostItselfIsOwn(): void
    {
        $this->assertTrue(RedirectUri::isAllowed('http://localhost:8080/app', [], 'http://localhost:8080', null, []));
        $this->assertFalse(RedirectUri::isAllowed('http://other.localhost/', [], 'http://localhost:8080', null, []));
    }
}
