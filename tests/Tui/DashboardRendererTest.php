<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Tests\Tui;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RcSoftTech\ConsoleProfilerBundle\Enum\ProfilerStatus;
use RcSoftTech\ConsoleProfilerBundle\Service\MetricsSnapshot;
use RcSoftTech\ConsoleProfilerBundle\Tui\DashboardRenderer;
use RcSoftTech\ConsoleProfilerBundle\Util\FormattingUtils;
use Symfony\Component\Console\Output\ConsoleOutput;

#[CoversClass(DashboardRenderer::class)]
#[CoversClass(ProfilerStatus::class)]
final class DashboardRendererTest extends TestCase
{
    use \RcSoftTech\ConsoleProfilerBundle\Tests\TestTrait;

    private DashboardRenderer $renderer;

    #[Override]
    protected function setUp(): void
    {
        $this->renderer = new DashboardRenderer(new FormattingUtils());
    }

    #[Test]
    public function registerStylesDoesNotError(): void
    {
        $output = $this->createMock(ConsoleOutput::class);
        $output->method('getFormatter')->willReturn(new \Symfony\Component\Console\Formatter\OutputFormatter());

        $this->renderer->registerStyles($output);

        static::assertTrue($output->getFormatter()->hasStyle('profiler_title'));
    }

    #[Test]
    public function buildReturnsCorrectNumberOfLines(): void
    {
        $lines = $this->renderer->build($this->createSnapshot(), ProfilerStatus::Running, 80);

        static::assertCount(17, $lines);
    }

    #[Test]
    public function buildIncludesRunningBadge(): void
    {
        $lines = $this->renderer->build($this->createSnapshot(), ProfilerStatus::Running, 80);

        $titleLine = $lines[1];
        static::assertStringContainsString('PROFILING', $titleLine);
        static::assertStringContainsString('profiler_running', $titleLine);
    }

    #[Test]
    public function buildIncludesCompletedBadge(): void
    {
        $lines = $this->renderer->build($this->createSnapshot(), ProfilerStatus::Completed, 80);

        $titleLine = $lines[1];
        static::assertStringContainsString('COMPLETED', $titleLine);
        static::assertStringContainsString('profiler_ok', $titleLine);
    }

    #[Test]
    public function buildIncludesFailedBadge(): void
    {
        $lines = $this->renderer->build($this->createSnapshot(), ProfilerStatus::Failed, 80);

        $titleLine = $lines[1];
        static::assertStringContainsString('FAILED', $titleLine);
        static::assertStringContainsString('profiler_fail', $titleLine);
    }

    #[Test]
    public function buildRendersCommandName(): void
    {
        $lines = $this->renderer->build($this->createSnapshot(), ProfilerStatus::Running, 80);

        $commandLine = $lines[3];
        static::assertStringContainsString('app:import-data', $commandLine);
    }

    #[Test]
    public function buildRendersQueryCount(): void
    {
        $lines = $this->renderer->build($this->createSnapshot(), ProfilerStatus::Running, 80);

        $sqlLine = $lines[14];
        static::assertStringContainsString('142', $sqlLine);
    }

    #[Test]
    public function buildUsesBoxBorders(): void
    {
        $lines = $this->renderer->build($this->createSnapshot(), ProfilerStatus::Running, 80);

        static::assertStringContainsString('╔', $lines[0]);
        static::assertStringContainsString('╚', $lines[16]);
        static::assertStringContainsString('║', $lines[1]);
    }

    #[Test]
    public function buildRendersExitCodeOnFinalRender(): void
    {
        $lines = $this->renderer->build($this->createSnapshot(), ProfilerStatus::Completed, 80, exitCode: 0);

        $titleLine = $lines[1];
        static::assertStringContainsString('Exit:', $titleLine);
        static::assertStringContainsString('0', $titleLine);
    }

    #[Test]
    public function buildRendersFailedExitCode(): void
    {
        $lines = $this->renderer->build($this->createSnapshot(), ProfilerStatus::Failed, 80, exitCode: 1);

        $titleLine = $lines[1];
        static::assertStringContainsString('Exit:', $titleLine);
        static::assertStringContainsString('1', $titleLine);
    }

    #[Test]
    public function buildRendersMemoryGrowthRate(): void
    {
        $lines = $this->renderer->build($this->createSnapshot(), ProfilerStatus::Running, 80);

        $memoryLine = $lines[10];
        static::assertStringContainsString('/s', $memoryLine);
        static::assertStringContainsString('profiler_bar_ok', $memoryLine);
    }

    #[Test]
    public function buildRendersProgressBarForHighMemory(): void
    {
        $snapshot = new MetricsSnapshot(
            memoryUsage: 241_591_910,
            peakMemory: 241_591_910,
            memoryLimit: 268_435_456,
            duration: 1.0,
            cpuUserTime: 0.5,
            cpuSystemTime: 0.01,
            memoryGrowthRate: 0.0,
            pid: 1,
            commandName: 'test',
            environment: 'dev',
            queryCount: 0,
            loadedClasses: 1,
            declaredFunctions: 1,
            includedFiles: 1,
            gcCycles: 0,
            phpVersion: '8.4.0',
            sapiName: 'cli',
            opcacheEnabled: false,
            xdebugEnabled: false,
        );

        $lines = $this->renderer->build($snapshot, ProfilerStatus::Running, 80);
        $memoryLine = $lines[9];

        static::assertStringContainsString('profiler_bar_danger', $memoryLine);
    }

    #[Test]
    public function buildRendersProgressBarForWarningMemory(): void
    {
        $snapshot = new MetricsSnapshot(
            memoryUsage: 187_904_819,
            peakMemory: 187_904_819,
            memoryLimit: 268_435_456,
            duration: 1.0,
            cpuUserTime: 0.5,
            cpuSystemTime: 0.01,
            memoryGrowthRate: 0.0,
            pid: 1,
            commandName: 'test',
            environment: 'dev',
            queryCount: 0,
            loadedClasses: 1,
            declaredFunctions: 1,
            includedFiles: 1,
            gcCycles: 0,
            phpVersion: '8.4.0',
            sapiName: 'cli',
            opcacheEnabled: false,
            xdebugEnabled: false,
        );

        $lines = $this->renderer->build($snapshot, ProfilerStatus::Running, 80);
        $memoryLine = $lines[9];

        static::assertStringContainsString('profiler_bar_warn', $memoryLine);
    }

    #[Test]
    public function buildHandlesZeroMemoryLimit(): void
    {
        $snapshot = new MetricsSnapshot(
            memoryUsage: 10_000,
            peakMemory: 10_000,
            memoryLimit: 0,
            duration: 1.0,
            cpuUserTime: 0.1,
            cpuSystemTime: 0.0,
            memoryGrowthRate: 0.0,
            pid: 1,
            commandName: 'test',
            environment: 'dev',
            queryCount: 0,
            loadedClasses: 1,
            declaredFunctions: 1,
            includedFiles: 1,
            gcCycles: 0,
            phpVersion: '8.4.0',
            sapiName: 'cli',
            opcacheEnabled: true,
            xdebugEnabled: true,
        );

        $lines = $this->renderer->build($snapshot, ProfilerStatus::Running, 80);
        static::assertStringContainsString('∞', $lines[9]);
        static::assertStringContainsString('n/a', $lines[9]);
    }

    #[Test]
    public function buildHandlesZeroDuration(): void
    {
        $snapshot = new MetricsSnapshot(
            memoryUsage: 10_000,
            peakMemory: 10_000,
            memoryLimit: 100_000,
            duration: 0.0,
            cpuUserTime: 0.0,
            cpuSystemTime: 0.0,
            memoryGrowthRate: 0.0,
            pid: 1,
            commandName: 'test',
            environment: 'dev',
            queryCount: 0,
            loadedClasses: 1,
            declaredFunctions: 1,
            includedFiles: 1,
            gcCycles: 0,
            phpVersion: '8.4.0',
            sapiName: 'cli',
            opcacheEnabled: false,
            xdebugEnabled: false,
        );

        $lines = $this->renderer->build($snapshot, ProfilerStatus::Running, 80);
        static::assertCount(17, $lines);
    }

    #[Test]
    public function buildRendersHighGrowthRate(): void
    {
        $snapshot = new MetricsSnapshot(
            memoryUsage: 50_000_000,
            peakMemory: 50_000_000,
            memoryLimit: 268_435_456,
            duration: 2.0,
            cpuUserTime: 0.5,
            cpuSystemTime: 0.01,
            memoryGrowthRate: 15_000_000.0, // > 10MB/s
            pid: 1,
            commandName: 'test',
            environment: 'dev',
            queryCount: 0,
            loadedClasses: 1,
            declaredFunctions: 1,
            includedFiles: 1,
            gcCycles: 0,
            phpVersion: '8.4.0',
            sapiName: 'cli',
            opcacheEnabled: false,
            xdebugEnabled: false,
        );

        $lines = $this->renderer->build($snapshot, ProfilerStatus::Running, 80);
        static::assertStringContainsString('profiler_bar_danger', $lines[10]);
    }

    #[Test]
    public function buildRendersWarnGrowthRate(): void
    {
        $snapshot = new MetricsSnapshot(
            memoryUsage: 50_000_000,
            peakMemory: 50_000_000,
            memoryLimit: 268_435_456,
            duration: 2.0,
            cpuUserTime: 0.5,
            cpuSystemTime: 0.01,
            memoryGrowthRate: 2_000_000.0, // 2MB/s -> Warn
            pid: 1,
            commandName: 'test',
            environment: 'dev',
            queryCount: 0,
            loadedClasses: 1,
            declaredFunctions: 1,
            includedFiles: 1,
            gcCycles: 0,
            phpVersion: '8.4.0',
            sapiName: 'cli',
            opcacheEnabled: false,
            xdebugEnabled: false,
        );

        $lines = $this->renderer->build($snapshot, ProfilerStatus::Running, 80);
        static::assertStringContainsString('profiler_bar_warn', $lines[10]);
    }
}
