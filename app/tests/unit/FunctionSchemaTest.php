<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\inc\FunctionSchema;
use Codeception\Test\Unit;

class FunctionSchemaTest extends Unit
{
    protected UnitTester $tester;

    public function testInferScalars(): void
    {
        $this->assertEquals(['type' => 'string'], FunctionSchema::infer('x'));
        $this->assertEquals(['type' => 'number'], FunctionSchema::infer(42));
        $this->assertEquals(['type' => 'number'], FunctionSchema::infer(1.5));
        $this->assertEquals(['type' => 'boolean'], FunctionSchema::infer(true));
        $this->assertEquals(['type' => 'null'], FunctionSchema::infer(null));
    }

    public function testInferObjectAndArray(): void
    {
        $schema = FunctionSchema::infer(['a' => 1, 'tags' => ['x', 'y'], 'ok' => true]);
        $this->assertEquals('object', $schema['type']);
        $this->assertEquals(['type' => 'number'], $schema['properties']['a']);
        $this->assertEquals('array', $schema['properties']['tags']['type']);
        $this->assertEquals(['type' => 'string'], $schema['properties']['tags']['items']);
        $this->assertEquals(['type' => 'boolean'], $schema['properties']['ok']);
    }

    public function testEmptyArrayItemsUnknown(): void
    {
        $this->assertEquals(['type' => 'unknown'], FunctionSchema::infer([])['items']);
    }

    public function testToTypeScriptScalars(): void
    {
        $this->assertEquals('string', FunctionSchema::toTypeScript(['type' => 'string']));
        $this->assertEquals('number', FunctionSchema::toTypeScript(['type' => 'number']));
        $this->assertEquals('unknown', FunctionSchema::toTypeScript(null));
    }

    public function testToTypeScriptNested(): void
    {
        $schema = FunctionSchema::infer(['id' => 1, 'name' => 'a', 'items' => [['n' => 2]]]);
        $ts = FunctionSchema::toTypeScript($schema);
        $this->assertStringContainsString('id: number', $ts);
        $this->assertStringContainsString('name: string', $ts);
        $this->assertStringContainsString('items: { n: number }[]', $ts);
    }

    public function testRoundTripEventToTypeScript(): void
    {
        // What a dry-run does: infer from a real result, render to TS.
        $result = ['sum' => 42, 'ok' => true, 'label' => 'x'];
        $ts = FunctionSchema::toTypeScript(FunctionSchema::infer($result));
        $this->assertEquals('{ sum: number; ok: boolean; label: string }', $ts);
    }

    public function testEmptyObjectIsRecord(): void
    {
        $this->assertEquals('Record<string, unknown>', FunctionSchema::toTypeScript(['type' => 'object', 'properties' => []]));
    }
}
