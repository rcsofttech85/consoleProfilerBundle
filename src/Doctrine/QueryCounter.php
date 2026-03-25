<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Doctrine;

/**
 * Thread-safe SQL query counter with asymmetric visibility.
 *
 * The count is publicly readable but only privately writable,
 * enforcing encapsulation at the language level (PHP 8.4).
 */
final class QueryCounter
{
    /** Publicly readable, privately writable query count. */
    public private(set) int $count = 0;

    /**
     * Increment the query counter by one.
     */
    public function increment(): void
    {
        ++$this->count;
    }

    /**
     * Reset the counter to zero (e.g., between commands).
     */
    public function reset(): void
    {
        $this->count = 0;
    }
}
