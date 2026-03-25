<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Override;
use SensitiveParameter;

/**
 * Decorates the DBAL Driver to inject a profiling connection.
 */
final class ProfilingDriver extends AbstractDriverMiddleware
{
    public function __construct(
        Driver $driver,
        private readonly QueryCounter $queryCounter,
    ) {
        parent::__construct($driver);
    }

    #[Override]
    public function connect(
        #[SensitiveParameter]
        array $params,
    ): Connection {
        return new ProfilingConnection(
            parent::connect($params),
            $this->queryCounter,
        );
    }
}
