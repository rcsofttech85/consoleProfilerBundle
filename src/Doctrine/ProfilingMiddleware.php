<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;
use Override;

/**
 * Doctrine DBAL 4 Middleware entry point.
 *
 * Wraps the driver to intercept all SQL activity and feed query counts
 * to the shared QueryCounter singleton.
 */
final class ProfilingMiddleware implements Middleware
{
    public function __construct(
        private readonly QueryCounter $queryCounter,
    ) {
    }

    #[Override]
    public function wrap(Driver $driver): ProfilingDriver
    {
        return new ProfilingDriver($driver, $this->queryCounter);
    }
}
