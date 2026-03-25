<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Service;

/**
 * Contract for services providing performance metrics.
 *
 * Adheres to OCP (Open-Closed Principle) by allowing new metric sources
 * to be added without modifying the core TUI orchestration.
 * Adheres to DIP by depending on primitives rather than concrete classes.
 */
interface MetricsProviderInterface
{
    public function start(string $commandName): void;

    public function snapshot(int $queryCount): MetricsSnapshot;
}
