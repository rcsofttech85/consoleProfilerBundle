<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Service;

use Override;

use function count;
use function extension_loaded;
use function function_exists;
use function ini_get;
use function is_array;

use const PHP_SAPI;
use const PHP_VERSION;

/**
 * Collects runtime metrics using PHP 8.4 property hooks for computed values.
 *
 * Property hooks eliminate traditional getters — accessing $collector->memoryUsage
 * always returns the live value without caching stale data.
 */
final class MetricsCollector implements MetricsProviderInterface
{
    private float $startTime = 0.0;

    private string $commandName = 'unknown';

    private float $startCpuUserTime = 0.0;

    private float $startCpuSystemTime = 0.0;

    private int $startLoadedClasses = 0;

    private int $startDeclaredFunctions = 0;

    private int $startIncludedFiles = 0;

    private int $startGcCycles = 0;

    private int $startMemory = 0;

    private int $previousMemory = -1;

    private int $previousQueryCount = -1;

    /** Live memory usage via property hook — always returns current value. */
    public int $memoryUsage {
        get => memory_get_usage(true);
    }

    /** Peak memory usage via property hook. */
    public int $peakMemory {
        get => memory_get_peak_usage(true);
    }

    /** Elapsed time in seconds with microsecond precision. */
    public float $elapsed {
        get => $this->startTime > 0.0
        ? microtime(true) - $this->startTime
        : 0.0;
    }

    /** Number of classes loaded during this command's execution. */
    public int $loadedClasses {
        get => $this->getCurrentLoadedClasses() - $this->startLoadedClasses;
    }

    /** Number of user+internal functions declared during this command. */
    public int $declaredFunctions {
        get => $this->getCurrentDeclaredFunctions() - $this->startDeclaredFunctions;
    }

    /** Number of files included/required during this command. */
    public int $includedFiles {
        get => $this->getCurrentIncludedFiles() - $this->startIncludedFiles;
    }

    /** CPU user time in seconds consumed by this command. */
    public float $cpuUserTime {
        get => $this->getCurrentCpuUserTime() - $this->startCpuUserTime;
    }

    /** CPU system time in seconds consumed by this command. */
    public float $cpuSystemTime {
        get => $this->getCurrentCpuSystemTime() - $this->startCpuSystemTime;
    }

    /** Memory limit parsed from ini. */
    public int $memoryLimit {
        get => $this->parseMemoryLimit((string) ini_get('memory_limit'));
    }

    private function parseMemoryLimit(string $limit): int
    {
        if ($limit === '' || $limit === '-1') {
            return -1;
        }

        if (!preg_match('/^(\d+)([gmkb])?$/i', $limit, $matches)) {
            return (int) $limit;
        }

        $value = (int) $matches[1];
        $unit = strtolower($matches[2] ?? '');

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    /**
     * Live memory growth rate in bytes per second.
     *
     * This is a real-time-only metric — the Symfony profiler cannot provide this
     * because it only captures post-mortem snapshots. A positive rate during
     * long-running commands indicates potential memory leaks.
     */
    public float $memoryGrowthRate {
        get {
            $elapsed = $this->elapsed;
            if ($elapsed <= 0.0) {
                return 0.0;
            }

            $delta = $this->memoryUsage - $this->startMemory;

            return $delta / $elapsed;
        }
    }

    public function __construct(
        private readonly string $environment,
    ) {
    }

    /**
     * Begin profiling — records the start timestamps and baseline counts.
     */
    #[Override]
    public function start(string $commandName): void
    {
        $this->startTime = microtime(true);
        $this->commandName = $commandName;

        $rusage = getrusage();
        $this->startMemory = memory_get_usage(true);
        $this->startCpuUserTime = $this->formatCpuTime($rusage, 'ru_utime');
        $this->startCpuSystemTime = $this->formatCpuTime($rusage, 'ru_stime');
        $this->startLoadedClasses = $this->getCurrentLoadedClasses();
        $this->startDeclaredFunctions = $this->getCurrentDeclaredFunctions();
        $this->startIncludedFiles = $this->getCurrentIncludedFiles();
        $this->startGcCycles = $this->getCurrentGcCycles();
    }

    /**
     * Capture an immutable snapshot of all current metrics.
     */
    #[Override]
    public function snapshot(int $queryCount): MetricsSnapshot
    {
        $rusage = getrusage();

        $snapshot = new MetricsSnapshot(
            memoryUsage: $this->memoryUsage,
            peakMemory: $this->peakMemory,
            memoryLimit: $this->memoryLimit,
            duration: $this->elapsed,
            cpuUserTime: $this->cpuUserTime,
            cpuSystemTime: $this->cpuSystemTime,
            memoryGrowthRate: $this->memoryGrowthRate,
            pid: (int) getmypid(),
            commandName: $this->commandName,
            environment: $this->environment,
            queryCount: $queryCount,
            loadedClasses: $this->loadedClasses,
            declaredFunctions: $this->declaredFunctions,
            includedFiles: $this->includedFiles,
            gcCycles: $this->getCurrentGcCycles() - $this->startGcCycles,
            phpVersion: PHP_VERSION,
            sapiName: PHP_SAPI,
            opcacheEnabled: function_exists('opcache_get_status') && (opcache_get_status(false)['opcache_enabled'] ?? false),
            xdebugEnabled: extension_loaded('xdebug'),
            memoryTrend: $this->calculateTrend($this->memoryUsage, $this->previousMemory, 1024 * 1024), // 1MB threshold
            queryTrend: $this->calculateTrend($queryCount, $this->previousQueryCount, 0),
        );

        $this->previousMemory = $this->memoryUsage;
        $this->previousQueryCount = $queryCount;

        return $snapshot;
    }

    private function calculateTrend(float|int $current, float|int $previous, float|int $threshold): int
    {
        if ($previous === -1 || $previous === -1.0) {
            return 0;
        }

        $diff = $current - $previous;

        if ($threshold >= abs($diff)) {
            return 0;
        }

        return $diff > 0 ? 1 : -1;
    }

    private function getCurrentCpuUserTime(): float
    {
        return $this->formatCpuTime(getrusage(), 'ru_utime');
    }

    private function getCurrentCpuSystemTime(): float
    {
        return $this->formatCpuTime(getrusage(), 'ru_stime');
    }

    /**
     * @param array<mixed>|false $rusage Result of getrusage()
     */
    private function formatCpuTime(array|false $rusage, string $key): float
    {
        if (!is_array($rusage)) {
            return 0.0;
        }

        /** @var int $sec */
        $sec = $rusage["{$key}.tv_sec"] ?? 0;
        /** @var int $usec */
        $usec = $rusage["{$key}.tv_usec"] ?? 0;

        return (float) $sec + (float) $usec / 1_000_000;
    }

    private function getCurrentLoadedClasses(): int
    {
        return count(get_declared_classes())
            + count(get_declared_interfaces())
            + count(get_declared_traits());
    }

    private function getCurrentDeclaredFunctions(): int
    {
        $funcs = get_defined_functions();

        return count($funcs['user']);
    }

    private function getCurrentIncludedFiles(): int
    {
        return count(get_included_files());
    }

    private function getCurrentGcCycles(): int
    {
        $status = gc_status();

        return $status['runs'];
    }
}
