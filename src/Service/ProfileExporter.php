<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

use function file_put_contents;
use function json_encode;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const LOCK_EX;

/**
 * Exports profiler snapshots to JSON for CI pipeline integration.
 *
 * This feature is unique to the console profiler — the Symfony profiler
 * provides browser-based inspection, not machine-readable CI artifacts.
 */
final class ProfileExporter
{
    public function __construct(
        private readonly Filesystem $fs = new Filesystem(),
    ) {
    }

    /**
     * Write a JSON profile dump to disk.
     *
     * Uses LOCK_EX to prevent corruption from concurrent writes.
     */
    public function export(MetricsSnapshot $snapshot, string $path): void
    {
        $path = Path::canonicalize($path);
        $dir = Path::getDirectory($path);

        if ($this->fs->exists($dir) === false) {
            $this->fs->mkdir($dir, 0o755);
        }

        $data = [
            'timestamp' => date('c'),
            'command' => $snapshot->commandName,
            'environment' => $snapshot->environment,
            'exit_code' => $snapshot->exitCode,
            'duration_seconds' => round($snapshot->duration, 4),
            'memory' => [
                'usage_bytes' => $snapshot->memoryUsage,
                'peak_bytes' => $snapshot->peakMemory,
                'limit_bytes' => $snapshot->memoryLimit,
                'growth_rate_bytes_per_sec' => round($snapshot->memoryGrowthRate, 2),
            ],
            'cpu' => [
                'user_seconds' => round($snapshot->cpuUserTime, 4),
                'system_seconds' => round($snapshot->cpuSystemTime, 4),
            ],
            'counters' => [
                'sql_queries' => $snapshot->queryCount,
                'loaded_classes' => $snapshot->loadedClasses,
                'declared_functions' => $snapshot->declaredFunctions,
                'included_files' => $snapshot->includedFiles,
                'gc_cycles' => $snapshot->gcCycles,
            ],
            'system' => [
                'php_version' => $snapshot->phpVersion,
                'sapi' => $snapshot->sapiName,
                'pid' => $snapshot->pid,
                'opcache_enabled' => $snapshot->opcacheEnabled,
                'xdebug_enabled' => $snapshot->xdebugEnabled,
            ],
        ];

        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n", LOCK_EX);
    }
}
