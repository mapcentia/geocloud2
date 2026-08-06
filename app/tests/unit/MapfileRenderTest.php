<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\models\Mapfile;
use Codeception\Test\Unit;

class MapfileRenderTest extends Unit
{
    protected UnitTester $tester;

    public function testRenderStyleBasics(): void
    {
        $s = Mapfile::renderStyle([
            "sortid" => 10, "name" => "Fill",
            "color" => "#008000", "outlinecolor" => "#000000", "width" => "2",
            "symbol" => "circle", "size" => "10", "style_opacity" => "50",
        ]);
        $this->assertStringContainsString("STYLE\n", $s);
        $this->assertStringContainsString("COLOR 0 128 0\n", $s);
        $this->assertStringContainsString("OUTLINECOLOR 0 0 0\n", $s);
        $this->assertStringContainsString("WIDTH 2\n", $s);
        $this->assertStringContainsString("SYMBOL 'circle'\n", $s);
        $this->assertStringContainsString("SIZE 10\n", $s);
        $this->assertStringContainsString("OPACITY 50\n", $s);
        $this->assertStringContainsString("END # style\n", $s);
        // name/sortid never reach the mapfile
        $this->assertStringNotContainsString("Fill", $s);
    }

    public function testRenderStyleEmitsMinMaxSizeForEveryStyle(): void
    {
        $s = Mapfile::renderStyle(["color" => "#008000", "minsize" => "2", "maxsize" => "40"]);
        $this->assertStringContainsString("MINSIZE 2\n", $s);
        $this->assertStringContainsString("MAXSIZE 40\n", $s);
    }

    public function testRenderStyleColumnDrivenValuesGetBrackets(): void
    {
        $s = Mapfile::renderStyle(["width" => "mycol", "style_offsetx" => "xcol"]);
        $this->assertStringContainsString("WIDTH [mycol]\n", $s);
        $this->assertStringContainsString("OFFSET [xcol] 0\n", $s);
    }

    public function testRenderLabelOffReturnsEmpty(): void
    {
        $this->assertSame('', Mapfile::renderLabel(["on" => false, "text" => "[a]"], "s.t", 1));
        $this->assertSame('', Mapfile::renderLabel(["text" => "[a]"], "s.t", 1));
    }

    public function testRenderLabelBasicsAndMarkers(): void
    {
        $s = Mapfile::renderLabel([
            "on" => true, "text" => "[navn]", "size" => "12", "color" => "#112233",
            "position" => "cc", "force" => true,
        ], "myschema.mytable", 3);
        $this->assertStringContainsString("#START_LABEL3_myschema.mytable\n", $s);
        $this->assertStringContainsString("#END_LABEL3_myschema.mytable\n", $s);
        $this->assertStringContainsString("TEXT '[navn]'\n", $s);
        $this->assertStringContainsString("SIZE 12\n", $s);
        $this->assertStringContainsString("COLOR 17 34 51\n", $s);
        $this->assertStringContainsString("POSITION cc\n", $s);
        $this->assertStringContainsString("FORCE true\n", $s);
        $this->assertStringContainsString("FONT arialnormal\n", $s);
    }

    public function testRenderLabelBackgroundAlwaysGetsOutlineAndWidth(): void
    {
        // The old Label2 quirk (outline/width only when padding set) is normalized away
        $s = Mapfile::renderLabel(["on" => true, "backgroundcolor" => "#FFFFFF"], "s.t", 2);
        $this->assertStringContainsString("GEOMTRANSFORM 'labelpoly'\n", $s);
        $this->assertStringContainsString("OUTLINECOLOR 255 255 255\n", $s);
        $this->assertStringContainsString("WIDTH 1\n", $s);
        $sPad = Mapfile::renderLabel(["on" => true, "backgroundcolor" => "#FFFFFF", "backgroundpadding" => "3"], "s.t", 2);
        $this->assertStringContainsString("WIDTH 3\n", $sPad);
    }

    public function testRenderClassesWithNewFormatMultipleStylesAndLabels(): void
    {
        $classes = [[
            "name" => "c1",
            "expression" => "[t]=1",
            "styles" => [
                ["sortid" => 30, "name" => "top", "color" => "#0000FF"],
                ["sortid" => 10, "name" => "bottom", "color" => "#FF0000"],
                ["sortid" => 20, "name" => "middle", "color" => "#00FF00"],
            ],
            "labels" => [
                ["sortid" => 20, "on" => true, "text" => "[b]"],
                ["sortid" => 10, "on" => true, "text" => "[a]"],
                ["sortid" => 30, "on" => false, "text" => "[c]"],
            ],
        ]];
        $s = Mapfile::renderClasses($classes, ["data" => [["theme_column" => ""]]], "s.t");
        $this->assertStringContainsString("CLASS\n", $s);
        $this->assertStringContainsString("NAME 'c1'\n", $s);
        // styles ordered by sortid: red, green, blue
        $red = strpos($s, "COLOR 255 0 0");
        $green = strpos($s, "COLOR 0 255 0");
        $blue = strpos($s, "COLOR 0 0 255");
        $this->assertNotFalse($red);
        $this->assertTrue($red < $green && $green < $blue);
        // labels ordered by sortid, numbered sequentially, off-label skipped
        $a = strpos($s, "TEXT '[a]'");
        $b = strpos($s, "TEXT '[b]'");
        $this->assertTrue($a < $b);
        $this->assertStringContainsString("#START_LABEL1_s.t", $s);
        $this->assertStringContainsString("#START_LABEL2_s.t", $s);
        $this->assertStringNotContainsString("TEXT '[c]'", $s);
        $this->assertStringContainsString("END # Class\n", $s);
    }

    public function testRenderClassesWithRawLegacyFormat(): void
    {
        // Non-converted JSON straight from the DB must render correctly
        $classes = [[
            "sortid" => 10,
            "name" => "legacy",
            "color" => "#FF0000",
            "overlaycolor" => "#00FF00",
            "label" => true,
            "label_text" => "[navn]",
            "label2" => true,
            "label2_text" => "[vejnavn]",
        ]];
        $s = Mapfile::renderClasses($classes, ["data" => [["theme_column" => ""]]], "s.t");
        $this->assertStringContainsString("COLOR 255 0 0", $s);
        $this->assertStringContainsString("COLOR 0 255 0", $s);
        $this->assertStringContainsString("TEXT '[navn]'", $s);
        $this->assertStringContainsString("TEXT '[vejnavn]'", $s);
        $this->assertStringContainsString("#START_LABEL1_s.t", $s);
        $this->assertStringContainsString("#START_LABEL2_s.t", $s);
    }

    public function testRenderClassesEmptyStylesAndLabels(): void
    {
        $s = Mapfile::renderClasses([["name" => "empty", "styles" => [], "labels" => []]],
            ["data" => [["theme_column" => ""]]], "s.t");
        $this->assertStringContainsString("CLASS\n", $s);
        $this->assertStringNotContainsString("STYLE\n", $s);
        $this->assertStringNotContainsString("LABEL\n", $s);
    }
}
