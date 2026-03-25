<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Tests\Service;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RcSoftTech\ConsoleProfilerBundle\Service\MetricsCollector;
use RcSoftTech\ConsoleProfilerBundle\Service\MetricsSnapshot;
use ReflectionClass;

use function ini_get;

use const PHP_SAPI;
use const PHP_VERSION;

#[CoversClass(MetricsCollector::class)]
#[CoversClass(MetricsSnapshot::class)]
final class MetricsCollectorTest extends TestCase
{
    private MetricsCollector $collector;

    #[Override]
    protected function setUp(): void
    {
        $this->collector = new MetricsCollector('test');
    }

    #[Test]
    public function memoryUsagePropertyHookReturnsPositiveValue(): void
    {
        static::assertGreaterThan(0, $this->collector->memoryUsage);
    }

    #[Test]
    public function peakMemoryPropertyHookReturnsPositiveValue(): void
    {
        static::assertGreaterThan(0, $this->collector->peakMemory);
    }

    #[Test]
    public function elapsedIsZeroBeforeStart(): void
    {
        static::assertSame(0.0, $this->collector->elapsed);
    }

    #[Test]
    public function elapsedIncreasesAfterStart(): void
    {
        $this->collector->start('test:command');
        usleep(10_000);

        static::assertGreaterThan(0.0, $this->collector->elapsed);
    }

    #[Test]
    public function loadedClassesPropertyHookReturnsPositiveValue(): void
    {
        static::assertGreaterThan(0, $this->collector->loadedClasses);
    }

    #[Test]
    public function declaredFunctionsPropertyHookReturnsPositiveValue(): void
    {
        static::assertGreaterThanOrEqual(0, $this->collector->declaredFunctions);
    }

    #[Test]
    public function includedFilesPropertyHookReturnsPositiveValue(): void
    {
        static::assertGreaterThan(0, $this->collector->includedFiles);
    }

    #[Test]
    public function cpuUserTimePropertyHookReturnsNonNegative(): void
    {
        static::assertGreaterThanOrEqual(0.0, $this->collector->cpuUserTime);
    }

    #[Test]
    public function memoryLimitPropertyHookReturnsValue(): void
    {
        // Either -1 (unlimited) or a positive byte count
        $limit = $this->collector->memoryLimit;
        static::assertTrue($limit === -1 || $limit > 0, "memory_limit should be -1 or positive, got {$limit}");
    }

    #[Test]
    public function memoryLimitParsesUnitsCorrectly(): void
    {
        $original = ini_get('memory_limit');

        try {
            ini_set('memory_limit', '512M');
            static::assertSame(512 * 1024 * 1024, $this->collector->memoryLimit);

            ini_set('memory_limit', '1G');
            static::assertSame(1024 * 1024 * 1024, $this->collector->memoryLimit);

            ini_set('memory_limit', '2G');
            static::assertSame(2 * 1024 * 1024 * 1024, $this->collector->memoryLimit);

            ini_set('memory_limit', '-1');
            static::assertSame(-1, $this->collector->memoryLimit);
        } finally {
            ini_set('memory_limit', $original);
        }
    }

    #[Test]
    public function snapshotReturnsCorrectReadonlyDto(): void
    {
        $this->collector->start('app:import');

        usleep(5_000);

        $snapshot = $this->collector->snapshot(2);

        static::assertSame('app:import', $snapshot->commandName);
        static::assertSame('test', $snapshot->environment);
        static::assertSame(2, $snapshot->queryCount);
        static::assertGreaterThan(0, $snapshot->memoryUsage);
        static::assertGreaterThan(0, $snapshot->peakMemory);
        static::assertGreaterThan(0.0, $snapshot->duration);
        static::assertSame((int) getmypid(), $snapshot->pid);
        static::assertSame(PHP_VERSION, $snapshot->phpVersion);
        static::assertSame(PHP_SAPI, $snapshot->sapiName);
        static::assertGreaterThanOrEqual(0, $snapshot->loadedClasses);
        static::assertGreaterThanOrEqual(0, $snapshot->includedFiles);
        static::assertGreaterThanOrEqual(0, $snapshot->gcCycles);
    }

    #[Test]
    public function snapshotIsImmutable(): void
    {
        $this->collector->start('app:test');
        $this->collector->snapshot(0);

        $reflection = new ReflectionClass(MetricsSnapshot::class);
        static::assertTrue($reflection->isReadOnly());
    }

    #[Test]
    public function multipleSnapshotsAreIndependent(): void
    {
        $this->collector->start('app:test');

        $snapshot1 = $this->collector->snapshot(0);
        usleep(10_000);
        $snapshot2 = $this->collector->snapshot(1);

        static::assertNotSame($snapshot1->duration, $snapshot2->duration);
        static::assertSame(0, $snapshot1->queryCount);
        static::assertSame(1, $snapshot2->queryCount);
    }
}
