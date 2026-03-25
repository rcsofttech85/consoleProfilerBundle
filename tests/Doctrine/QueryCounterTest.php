<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Tests\Doctrine;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RcSoftTech\ConsoleProfilerBundle\Doctrine\QueryCounter;
use ReflectionProperty;

#[CoversClass(QueryCounter::class)]
final class QueryCounterTest extends TestCase
{
    private QueryCounter $counter;

    #[Override]
    protected function setUp(): void
    {
        $this->counter = new QueryCounter();
    }

    #[Test]
    public function itStartsAtZero(): void
    {
        static::assertSame(0, $this->counter->count);
    }

    #[Test]
    public function itIncrementsCorrectly(): void
    {
        $this->counter->increment();
        static::assertSame(1, $this->counter->count);

        $this->counter->increment();
        $this->counter->increment();
        static::assertSame(3, $this->counter->count);
    }

    #[Test]
    public function itResetsToZero(): void
    {
        $this->counter->increment();
        $this->counter->increment();
        static::assertSame(2, $this->counter->count);

        $this->counter->reset();
        static::assertSame(0, $this->counter->count);
    }

    #[Test]
    public function itCanIncrementAfterReset(): void
    {
        $this->counter->increment();
        $this->counter->reset();
        $this->counter->increment();

        static::assertSame(1, $this->counter->count);
    }

    #[Test]
    public function countPropertyIsPubliclyReadable(): void
    {
        $this->counter->increment();

        // Asymmetric visibility: public read, private(set) write
        $reflection = new ReflectionProperty(QueryCounter::class, 'count');
        static::assertTrue($reflection->isPublic());
    }
}
