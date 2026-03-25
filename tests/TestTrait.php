<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Tests;

use RcSoftTech\ConsoleProfilerBundle\Service\MetricsSnapshot;

trait TestTrait
{
    private function createSnapshot(): MetricsSnapshot
    {
        return new MetricsSnapshot(
            memoryUsage: 13_107_200,
            peakMemory: 19_136_512,
            memoryLimit: 268_435_456,
            duration: 3.42,
            cpuUserTime: 1.20,
            cpuSystemTime: 0.08,
            memoryGrowthRate: 512_000.0,
            pid: 12345,
            commandName: 'app:import-data',
            environment: 'dev',
            queryCount: 142,
            loadedClasses: 312,
            declaredFunctions: 1204,
            includedFiles: 89,
            gcCycles: 2,
            phpVersion: '8.4.12',
            sapiName: 'cli',
            opcacheEnabled: true,
            xdebugEnabled: false,
            memoryTrend: 0,
            queryTrend: 0,
            exitCode: null,
        );
    }
}
