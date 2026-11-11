<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\exceptions\GC2Exception;
use app\models\Configuration;
use Codeception\Test\Unit;

class ConfigurationModelTest extends Unit
{
    public function testIsValidKey(): void
    {
        $this->assertTrue(Configuration::isValidKey('configuration_my_map_673211a0b1c2d3'));
        $this->assertTrue(Configuration::isValidKey('configuration_a@bdk_673211a0b1c2d3'));
        $this->assertFalse(Configuration::isValidKey('state_snapshot_1'));
        $this->assertFalse(Configuration::isValidKey('configuration_'));
        $this->assertFalse(Configuration::isValidKey('configuration_a,configuration_b'));
        $this->assertFalse(Configuration::isValidKey("configuration_a'; --"));
        $this->assertFalse(Configuration::isValidKey('configuration_a.json'));
        $this->assertFalse(Configuration::isValidKey("configuration_a\n"));
    }

    public function testGenerateKey(): void
    {
        foreach (['My map', 'a@b.dk', '地図', '!!!', ''] as $name) {
            $key = Configuration::generateKey($name);
            $this->assertMatchesRegularExpression('/^configuration_[A-Za-z0-9_]+$/', $key, $name);
        }
        $this->assertStringStartsWith('configuration_my_map_', Configuration::generateKey('My map'));
        $this->assertNotSame(Configuration::generateKey('x'), Configuration::generateKey('x'));
    }

    public function testAssertBody(): void
    {
        Configuration::assertBody('{"a":1}');
        Configuration::assertBody('[]');
        Configuration::assertBody('{}');
        foreach (['{a:1}', '', 'null', '1', '"x"', 'true', ['a' => 1], null] as $bad) {
            try {
                Configuration::assertBody($bad);
                $this->fail(var_export($bad, true));
            } catch (GC2Exception $e) {
                $this->assertSame(400, $e->getCode());
            }
        }
    }

    public function testDecode(): void
    {
        $value = json_encode(['name' => 'n', 'published' => true, 'description' => '', 'body' => '{ "a":1 }', 'key' => 'configuration_n_1']);
        $this->assertSame(
            ['key' => 'configuration_n_1', 'name' => 'n', 'description' => '', 'published' => true, 'body' => '{ "a":1 }'],
            Configuration::decode(['key' => 'configuration_n_1', 'value' => $value])
        );
        $this->assertNull(Configuration::decode(['key' => 'configuration_x_1', 'value' => '"just a string"']));
        $this->assertNull(Configuration::decode(['key' => 'configuration_x_1', 'value' => '{"foo":1}']));
        $this->assertNull(Configuration::decode(['key' => 'configuration_x y', 'value' => $value]));
    }

    public function testMerge(): void
    {
        $stored = ['name' => 'n', 'published' => false, 'description' => 'd', 'body' => '{ "a":1 }', 'key' => 'configuration_n_1'];
        $merged = Configuration::merge($stored, ['name' => 'n2', 'published' => true]);
        $this->assertSame(['name' => 'n2', 'published' => true, 'description' => 'd', 'body' => '{ "a":1 }', 'key' => 'configuration_n_1'], $merged);
    }
}
