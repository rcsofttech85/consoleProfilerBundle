<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Service;

/**
 * Immutable snapshot of profiler metrics at a point in time.
 *
 * Contains runtime stats, system info, and debug counters
 * for the TUI dashboard.
 */
readonly class MetricsSnapshot
{
    public function __construct(
        public int $memoryUsage,
        public int $peakMemory,
        public int $memoryLimit,
        public float $duration,
        public float $cpuUserTime,
        public float $cpuSystemTime,
        public float $memoryGrowthRate,
        public int $pid,
        public string $commandName,
        public string $environment,
        public int $queryCount,
        public int $loadedClasses,
        public int $declaredFunctions,
        public int $includedFiles,
        public int $gcCycles,
        public string $phpVersion,
        public string $sapiName,
        public bool $opcacheEnabled,
        public bool $xdebugEnabled,
        public int $memoryTrend = 0,
        public int $queryTrend = 0,
        public ?int $exitCode = null,
    ) {
    }

    /**
     * Return a new snapshot with the given exit code stamped.
     *
     * Avoids fragile manual field-by-field copies when only exitCode changes.
     */
    public function withExitCode(int $exitCode): self
    {
        return new self(
            memoryUsage: $this->memoryUsage,
            peakMemory: $this->peakMemory,
            memoryLimit: $this->memoryLimit,
            duration: $this->duration,
            cpuUserTime: $this->cpuUserTime,
            cpuSystemTime: $this->cpuSystemTime,
            memoryGrowthRate: $this->memoryGrowthRate,
            pid: $this->pid,
            commandName: $this->commandName,
            environment: $this->environment,
            queryCount: $this->queryCount,
            loadedClasses: $this->loadedClasses,
            declaredFunctions: $this->declaredFunctions,
            includedFiles: $this->includedFiles,
            gcCycles: $this->gcCycles,
            phpVersion: $this->phpVersion,
            sapiName: $this->sapiName,
            opcacheEnabled: $this->opcacheEnabled,
            xdebugEnabled: $this->xdebugEnabled,
            memoryTrend: $this->memoryTrend,
            queryTrend: $this->queryTrend,
            exitCode: $exitCode,
        );
    }
}
