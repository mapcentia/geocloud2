<?php
namespace app\tests\unit\ogc;

use app\exceptions\GC2Exception;
use app\exceptions\OwsException;
use app\exceptions\ServiceException;
use app\ogc\Problem;
use Codeception\Test\Unit;
use RuntimeException;

class ProblemTest extends Unit
{
    public function testGc2ExceptionPassesThroughUnchanged(): void
    {
        $e = new GC2Exception('Custom message', 404, null, 'CUSTOM_CODE');
        $this->assertSame($e, Problem::toGc2($e));
    }

    public function testOwsExceptionDenyIsForbidden(): void
    {
        $g = Problem::toGc2(new OwsException("DENY for 'select' on x"));
        $this->assertSame(403, $g->getCode());
        $this->assertSame('FORBIDDEN', $g->getErrorCode());
    }

    public function testServiceExceptionDenyIsForbidden(): void
    {
        $g = Problem::toGc2(new ServiceException('DENY'));
        $this->assertSame(403, $g->getCode());
        $this->assertSame('FORBIDDEN', $g->getErrorCode());
    }

    public function testRelationDoesNotExistIsCollectionNotFound(): void
    {
        $g = Problem::toGc2(new OwsException("Relation doesn't exist"));
        $this->assertSame(404, $g->getCode());
        $this->assertSame('COLLECTION_NOT_FOUND', $g->getErrorCode());
    }

    public function testLayerNotEnabledIsCollectionNotFound(): void
    {
        $g = Problem::toGc2(new OwsException('Layer is not enabled'));
        $this->assertSame(404, $g->getCode());
        $this->assertSame('COLLECTION_NOT_FOUND', $g->getErrorCode());
    }

    public function testOtherOwsExceptionIsOgcError(): void
    {
        $g = Problem::toGc2(new OwsException('bad'));
        $this->assertSame(400, $g->getCode());
        $this->assertSame('OGC_ERROR', $g->getErrorCode());
    }

    public function testUnknownThrowableIsInternalError(): void
    {
        $g = Problem::toGc2(new RuntimeException('x'));
        $this->assertSame(500, $g->getCode());
        $this->assertSame('INTERNAL_ERROR', $g->getErrorCode());
        $this->assertSame('Internal error', $g->getMessage());
    }
}
