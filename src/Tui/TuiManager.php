<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Tui;

use RcSoftTech\ConsoleProfilerBundle\Enum\ProfilerStatus;
use RcSoftTech\ConsoleProfilerBundle\Service\MetricsSnapshot;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;

use function count;
use function is_resource;

use const PHP_EOL;

/**
 * Premium TUI profiler dashboard orchestrator.
 *
 * Adheres to SRP by delegating low-level terminal interaction to AnsiTerminal,
 * visual layout and styling to DashboardRenderer.
 */
final class TuiManager
{
    private bool $frozen = false;

    private bool $initialized = false;

    private ?OutputInterface $output = null;

    /** @var resource|null */
    private mixed $stream = null;

    private int $profilerHeight = 0;

    private readonly Terminal $term;

    public function __construct(
        private readonly AnsiTerminal $terminal,
        private readonly DashboardRenderer $renderer,
    ) {
        $this->term = new Terminal();
    }

    /** Terminal width via property hook — recalculated on each access. */
    public int $terminalWidth {
        get {
            $width = $this->term->getWidth();

            return $width < 70 ? 70 : $width;
        }
    }

    /** Terminal height via property hook. */
    public int $terminalHeight {
        get {
            $height = $this->term->getHeight();

            return $height < 20 ? 24 : $height;
        }
    }

    /**
     * Initialize the profiler: set up scroll region, render initial bar.
     */
    public function initialize(ConsoleOutput $output): void
    {
        $this->output = $output;
        $this->stream = $output->getStream();
        $this->frozen = false;

        $this->terminal->setStream($this->stream);
        $this->renderer->registerStyles($output);

        // Calculate initial height from an empty/dummy snapshot
        $dummySnapshot = new MetricsSnapshot(
            memoryUsage: 0,
            peakMemory: 0,
            memoryLimit: 0,
            duration: 0.0,
            cpuUserTime: 0.0,
            cpuSystemTime: 0.0,
            memoryGrowthRate: 0.0,
            pid: 0,
            commandName: '',
            environment: '',
            queryCount: 0,
            loadedClasses: 0,
            declaredFunctions: 0,
            includedFiles: 0,
            gcCycles: 0,
            phpVersion: '',
            sapiName: '',
            opcacheEnabled: false,
            xdebugEnabled: false,
        );
        $initialLines = $this->renderer->build($dummySnapshot, ProfilerStatus::Running, $this->terminalWidth);
        $this->profilerHeight = count($initialLines);

        for ($i = 0; $i < $this->profilerHeight; ++$i) {
            if (is_resource($this->stream)) {
                fwrite($this->stream, PHP_EOL);
            }
        }

        $scrollStart = $this->profilerHeight + 1;
        $this->terminal->setScrollRegion($scrollStart, $this->terminalHeight);

        $this->terminal->moveTo($scrollStart);

        $this->initialized = true;
    }

    /**
     * Render the profiler dashboard at the fixed top rows.
     */
    public function render(MetricsSnapshot $snapshot): void
    {
        if ($this->frozen || !$this->initialized) {
            return;
        }

        $this->writeBarToTop($this->renderer->build($snapshot, ProfilerStatus::Running, $this->terminalWidth));
    }

    /**
     * Freeze the dashboard with final metrics and restore full terminal scrolling.
     */
    public function freeze(MetricsSnapshot $snapshot, bool $failed = false, ?int $exitCode = null): void
    {
        if (!$this->initialized) {
            return;
        }

        $status = $failed ? ProfilerStatus::Failed : ProfilerStatus::Completed;
        $this->writeBarToTop($this->renderer->build($snapshot, $status, $this->terminalWidth, $exitCode));

        $this->terminal->resetScrollRegion();
        $this->terminal->moveTo($this->terminalHeight);
        if (is_resource($this->stream)) {
            fwrite($this->stream, PHP_EOL);
        }

        $this->frozen = true;
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Write the profiler bar at fixed top rows using direct cursor addressing.
     *
     * @param array<int, string> $lines
     */
    private function writeBarToTop(array $lines): void
    {
        if ($this->output === null) {
            return;
        }

        $formatter = $this->output->getFormatter();

        $this->terminal->saveCursor();

        foreach ($lines as $i => $line) {
            $this->terminal->moveTo($i + 1);
            $this->terminal->clearLine();
            $this->terminal->writeRaw((string) $formatter->format($line));
        }

        $this->terminal->restoreCursor();
    }
}
