<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Tui;

use function is_resource;

/**
 * Encapsulates ANSI escape sequences for terminal manipulation.
 *
 * Adheres to SRP by separating low-level drawing from content orchestration.
 */
final class AnsiTerminal
{
    /** @var resource|null */
    private mixed $stream = null;

    public function setStream(mixed $stream): void
    {
        if (is_resource($stream) || $stream === null) {
            $this->stream = $stream;
        }
    }

    public function saveCursor(): void
    {
        $this->write("\033[s");
    }

    public function restoreCursor(): void
    {
        $this->write("\033[u");
    }

    public function moveTo(int $row, int $col = 1): void
    {
        $this->write("\033[{$row};{$col}H");
    }

    public function clearLine(): void
    {
        $this->write("\033[2K");
    }

    public function setScrollRegion(int $top, int $bottom): void
    {
        $this->write("\033[{$top};{$bottom}r");
    }

    public function resetScrollRegion(): void
    {
        $this->write("\033[r");
    }

    public function writeRaw(string $text): void
    {
        $this->write($text);
    }

    private function write(string $sequence): void
    {
        if (is_resource($this->stream)) {
            fwrite($this->stream, $sequence);
        }
    }
}
