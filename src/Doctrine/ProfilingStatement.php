<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Doctrine;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Override;

/**
 * Decorates the DBAL Statement to count prepared query executions.
 */
final class ProfilingStatement extends AbstractStatementMiddleware
{
    public function __construct(
        Statement $statement,
        private readonly QueryCounter $queryCounter,
    ) {
        parent::__construct($statement);
    }

    #[Override]
    public function execute(): Result
    {
        $this->queryCounter->increment();

        return parent::execute();
    }
}
