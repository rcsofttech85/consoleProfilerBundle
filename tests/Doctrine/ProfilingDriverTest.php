<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Tests\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RcSoftTech\ConsoleProfilerBundle\Doctrine\ProfilingConnection;
use RcSoftTech\ConsoleProfilerBundle\Doctrine\ProfilingDriver;
use RcSoftTech\ConsoleProfilerBundle\Doctrine\QueryCounter;

#[CoversClass(ProfilingDriver::class)]
final class ProfilingDriverTest extends TestCase
{
    #[Test]
    public function itDecoratesConnection(): void
    {
        $innerDriver = $this->createMock(Driver::class);
        $innerConnection = $this->createMock(Connection::class);
        $innerDriver->expects(static::once())
            ->method('connect')
            ->with(['url' => 'sqlite:///:memory:'])
            ->willReturn($innerConnection);

        $queryCounter = new QueryCounter();

        $driver = new ProfilingDriver($innerDriver, $queryCounter);

        $connection = $driver->connect(['url' => 'sqlite:///:memory:']);

        static::assertInstanceOf(ProfilingConnection::class, $connection);
    }
}
