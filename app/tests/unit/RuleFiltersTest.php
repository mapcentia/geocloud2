<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\exceptions\ServiceException;
use app\ows\RuleFilters;
use Codeception\Test\Unit;

class RuleFiltersTest extends Unit
{
    public function testIsInstantAcceptsIso8601Instants(): void
    {
        $this->assertTrue(RuleFilters::isInstant('2024-01-01'));
        $this->assertTrue(RuleFilters::isInstant('2024-01-01T12:30:00Z'));
        $this->assertTrue(RuleFilters::isInstant('2024-01-01T12:30:00.123+02:00'));
        $this->assertTrue(RuleFilters::isInstant('2024-01-01 12:30:00'));
    }

    public function testIsInstantRejectsIntervalsAndInjection(): void
    {
        $this->assertFalse(RuleFilters::isInstant('2024-01-01/2024-02-01'));
        $this->assertFalse(RuleFilters::isInstant("2024-01-01' OR 1=1 --"));
        $this->assertFalse(RuleFilters::isInstant('..'));
        $this->assertFalse(RuleFilters::isInstant(''));
    }

    public function testVersionFilterWithoutTimeSliceIsCurrentVersion(): void
    {
        $this->assertSame('gc2_version_end_date IS NULL', RuleFilters::versionFilter(null));
        $this->assertSame('gc2_version_end_date IS NULL', RuleFilters::versionFilter(''));
    }

    public function testVersionFilterWithTimeSliceIsParenthesised(): void
    {
        $this->assertSame(
            "(gc2_version_start_date <= '2024-01-01T00:00:00Z' AND (gc2_version_end_date > '2024-01-01T00:00:00Z' OR gc2_version_end_date IS NULL))",
            RuleFilters::versionFilter('2024-01-01T00:00:00Z')
        );
    }

    public function testVersionFilterRejectsInvalidTimeSlice(): void
    {
        $this->expectException(ServiceException::class);
        RuleFilters::versionFilter("2024-01-01'; DROP TABLE x; --");
    }

    public function testTableOfStripsSchema(): void
    {
        $this->assertSame('roads', RuleFilters::tableOf('public.roads'));
        $this->assertSame('roads', RuleFilters::tableOf('roads'));
    }
}
