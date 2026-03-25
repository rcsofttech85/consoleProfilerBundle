<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Enum;

/**
 * Type-safe profiler lifecycle status.
 *
 * Prevents invalid status strings from reaching the TUI renderer.
 */
enum ProfilerStatus: string
{
    case Running = 'RUNNING';
    case Completed = 'COMPLETED';
    case Failed = 'FAILED';
}
