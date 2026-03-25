<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Tests\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RcSoftTech\ConsoleProfilerBundle\Doctrine\ProfilingConnection;
use RcSoftTech\ConsoleProfilerBundle\Doctrine\ProfilingDriver;
use RcSoftTech\ConsoleProfilerBundle\Doctrine\ProfilingMiddleware;
use RcSoftTech\ConsoleProfilerBundle\Doctrine\ProfilingStatement;
use RcSoftTech\ConsoleProfilerBundle\Doctrine\QueryCounter;

#[CoversClass(ProfilingMiddleware::class)]
#[CoversClass(ProfilingDriver::class)]
#[CoversClass(ProfilingConnection::class)]
#[CoversClass(ProfilingStatement::class)]
final class DoctrineMiddlewareTest extends TestCase
{
    private QueryCounter $counter;

    protected function setUp(): void
    {
        $this->counter = new QueryCounter();
    }

    #[Test]
    public function middlewareWrapReturnsProfilingDriver(): void
    {
        $innerDriver = $this->createMock(Driver::class);
        $middleware = new ProfilingMiddleware($this->counter);

        $driver = $middleware->wrap($innerDriver);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function connectionQueryIncrementsCounter(): void
    {
        $result = $this->createMock(Result::class);
        $innerConnection = $this->createMock(Connection::class);
        $innerConnection->method('query')->willReturn($result);

        $connection = new ProfilingConnection($innerConnection, $this->counter);

        static::assertSame(0, $this->counter->count);

        $connection->query('SELECT 1');
        static::assertSame(1, $this->counter->count);

        $connection->query('SELECT 2');
        static::assertSame(2, $this->counter->count);
    }

    #[Test]
    public function connectionExecIncrementsCounter(): void
    {
        $innerConnection = $this->createMock(Connection::class);
        $innerConnection->method('exec')->willReturn(1);

        $connection = new ProfilingConnection($innerConnection, $this->counter);

        $connection->exec('DELETE FROM users WHERE id = 1');
        static::assertSame(1, $this->counter->count);
    }

    #[Test]
    public function connectionPrepareReturnsProfilingStatement(): void
    {
        $innerStatement = $this->createMock(Statement::class);
        $innerConnection = $this->createMock(Connection::class);
        $innerConnection->method('prepare')->willReturn($innerStatement);

        $connection = new ProfilingConnection($innerConnection, $this->counter);
        $statement = $connection->prepare('SELECT * FROM users WHERE id = ?');

        static::assertInstanceOf(ProfilingStatement::class, $statement);
        // Prepare alone does NOT increment — only execute does
        static::assertSame(0, $this->counter->count);
    }

    #[Test]
    public function statementExecuteIncrementsCounter(): void
    {
        $result = $this->createMock(Result::class);

        // Create a minimal mock that extends AbstractStatementMiddleware
        $innerStatement = $this->createMock(Statement::class);
        $innerStatement->method('execute')->willReturn($result);

        $statement = new ProfilingStatement($innerStatement, $this->counter);

        $statement->execute();
        static::assertSame(1, $this->counter->count);

        $statement->execute();
        static::assertSame(2, $this->counter->count);
    }

    #[Test]
    public function fullMiddlewareChainCountsCorrectly(): void
    {
        $result = $this->createMock(Result::class);
        $innerStatement = $this->createMock(Statement::class);
        $innerStatement->method('execute')->willReturn($result);

        $innerConnection = $this->createMock(Connection::class);
        $innerConnection->method('query')->willReturn($result);
        $innerConnection->method('exec')->willReturn(0);
        $innerConnection->method('prepare')->willReturn($innerStatement);

        $connection = new ProfilingConnection($innerConnection, $this->counter);

        $connection->query('SELECT 1');
        $connection->exec('UPDATE t SET x = 1');
        $stmt = $connection->prepare('INSERT INTO t VALUES (?)');
        $stmt->execute();
        $stmt->execute();

        static::assertSame(4, $this->counter->count);
    }
}
