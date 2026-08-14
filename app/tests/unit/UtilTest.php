<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\inc\Util;
use Codeception\Test\Unit;

class UtilTest extends Unit
{
    /**
     * @var UnitTester
     */
    protected UnitTester $tester;

    protected function _before(): void
    {
    }

    protected function _after(): void
    {
    }

    // tests
    public function testExtractUserFromSubUserString(): void
    {
        // Case: Normal subuser string
        $result = Util::extractUserFromSubUserString("subuser@user");
        $this->assertEquals(["subuser", "user"], $result);

        // Case: No separator - only database name
        $result = Util::extractUserFromSubUserString("user");
        $this->assertEquals([null, "user"], $result);

        // Case: Multiple separators (should split at the last @)
        $result = Util::extractUserFromSubUserString("subuser@domain@user");
        $this->assertEquals(["subuser@domain", "user"], $result);

        // Case: Empty string
        $result = Util::extractUserFromSubUserString("");
        $this->assertEquals([null, ""], $result);

        // Case: Only separator
        $result = Util::extractUserFromSubUserString("@");
        $this->assertEquals(["", ""], $result);

        // Case: Separator at the end
        $result = Util::extractUserFromSubUserString("user@");
        $this->assertEquals(["user", ""], $result);

        // Case: Separator at the beginning
        $result = Util::extractUserFromSubUserString("@user");
        $this->assertEquals(["", "user"], $result);
    }

    public function testThePathStripsQueryString(): void
    {
        $host = ($_SERVER['HTTPS'] ?? null) ? 'https://' : 'http://';
        $host .= $_SERVER['HTTP_HOST'] ?? '';
        $savedRedirect = $_SERVER['REDIRECT_URL'] ?? null;
        $savedRequest = $_SERVER['REQUEST_URI'] ?? null;

        // FrankenPHP/Caddy: only REQUEST_URI is set and it carries the query string.
        // The raw "&" must never reach the WFS Capabilities xlink:href values.
        unset($_SERVER['REDIRECT_URL']);
        $_SERVER['REQUEST_URI'] = '/api/v4/wfs/schema/test/database/mydb/srs/25832?SERVICE=WFS&REQUEST=GetCapabilities';
        $path = Util::thePath();
        $this->assertStringNotContainsString('?', $path);
        $this->assertStringNotContainsString('&', $path);
        $this->assertEquals($host . '/api/v4/wfs/schema/test/database/mydb/srs/25832', $path);

        // Apache mod_rewrite: REDIRECT_URL is already path-only — unchanged.
        $_SERVER['REDIRECT_URL'] = '/api/v4/wfs/schema/test/database/mydb/srs/25832';
        $this->assertEquals($host . '/api/v4/wfs/schema/test/database/mydb/srs/25832', Util::thePath());

        if ($savedRedirect === null) {
            unset($_SERVER['REDIRECT_URL']);
        } else {
            $_SERVER['REDIRECT_URL'] = $savedRedirect;
        }
        if ($savedRequest === null) {
            unset($_SERVER['REQUEST_URI']);
        } else {
            $_SERVER['REQUEST_URI'] = $savedRequest;
        }
    }
}
