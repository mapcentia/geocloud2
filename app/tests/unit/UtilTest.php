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
}
