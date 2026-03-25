<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Doctrine;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Override;

/**
 * Decorates the DBAL Connection to count queries executed via exec(), query(), and prepare().
 */
final class ProfilingConnection extends AbstractConnectionMiddleware
{
    public function __construct(
        Connection $connection,
        private readonly QueryCounter $queryCounter,
    ) {
        parent::__construct($connection);
    }

    #[Override]
    public function prepare(string $sql): Statement
    {
        return new ProfilingStatement(
            parent::prepare($sql),
            $this->queryCounter,
        );
    }

    #[Override]
    public function query(string $sql): Result
    {
        $this->queryCounter->increment();

        return parent::query($sql);
    }

    #[Override]
    public function exec(string $sql): int
    {
        $this->queryCounter->increment();

        return (int) parent::exec($sql);
    }
}
