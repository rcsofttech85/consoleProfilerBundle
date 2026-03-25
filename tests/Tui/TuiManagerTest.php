<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Tests\Tui;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RcSoftTech\ConsoleProfilerBundle\Tui\AnsiTerminal;
use RcSoftTech\ConsoleProfilerBundle\Tui\DashboardRenderer;
use RcSoftTech\ConsoleProfilerBundle\Tui\TuiManager;
use RcSoftTech\ConsoleProfilerBundle\Util\FormattingUtils;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

#[CoversClass(TuiManager::class)]
final class TuiManagerTest extends TestCase
{
    use \RcSoftTech\ConsoleProfilerBundle\Tests\TestTrait;

    private TuiManager $manager;

    #[Override]
    protected function setUp(): void
    {
        $this->manager = new TuiManager(
            terminal: new AnsiTerminal(),
            renderer: new DashboardRenderer(new FormattingUtils()),
        );
    }

    #[Test]
    public function itIsNotInitializedByDefault(): void
    {
        static::assertFalse($this->manager->isInitialized());
    }

    #[Test]
    public function terminalWidthPropertyHookReturnsAtLeast70(): void
    {
        static::assertGreaterThanOrEqual(70, $this->manager->terminalWidth);
    }

    #[Test]
    public function terminalHeightPropertyHookReturnsAtLeast20(): void
    {
        static::assertGreaterThanOrEqual(20, $this->manager->terminalHeight);
    }

    #[Test]
    public function renderDoesNothingWhenNotInitialized(): void
    {
        $snapshot = $this->createSnapshot();

        $this->manager->render($snapshot);

        static::assertFalse($this->manager->isInitialized());
    }

    #[Test]
    public function freezeDoesNothingWhenNotInitialized(): void
    {
        $snapshot = $this->createSnapshot();

        $this->manager->freeze($snapshot);

        static::assertFalse($this->manager->isInitialized());
    }

    #[Test]
    public function itInitializesProperlyWithStreamAndFormatter(): void
    {
        $output = $this->createMock(ConsoleOutput::class);
        $stream = fopen('php://memory', 'rw');
        $output->method('getStream')->willReturn($stream);
        $output->method('getFormatter')->willReturn($this->createMock(OutputFormatterInterface::class));

        $this->manager->initialize($output);

        static::assertTrue($this->manager->isInitialized());
    }

    #[Test]
    public function itRendersProfilerDashboardToStream(): void
    {
        $output = $this->createMock(ConsoleOutput::class);
        $stream = fopen('php://memory', 'rw');
        $output->method('getStream')->willReturn($stream);

        $formatter = $this->createMock(OutputFormatterInterface::class);
        $formatter->method('format')->willReturnCallback(static fn (?string $s) => $s ?? '');
        $output->method('getFormatter')->willReturn($formatter);

        $this->manager->initialize($output);
        $this->manager->render($this->createSnapshot());

        static::assertIsResource($stream);
        rewind($stream);
        $contents = stream_get_contents($stream);

        static::assertIsString($contents);
        static::assertStringContainsString('CONSOLE PROFILER', $contents);
        static::assertStringContainsString('app:import-data', $contents);
    }

    #[Test]
    public function itFreezesProfilerDashboardAndShowsCompletedStatus(): void
    {
        $output = $this->createMock(ConsoleOutput::class);
        $stream = fopen('php://memory', 'rw');
        $output->method('getStream')->willReturn($stream);

        $formatter = $this->createMock(OutputFormatterInterface::class);
        $formatter->method('format')->willReturnCallback(static fn (?string $s) => $s ?? '');
        $output->method('getFormatter')->willReturn($formatter);

        $this->manager->initialize($output);
        $this->manager->freeze($this->createSnapshot(), false, 0);

        static::assertIsResource($stream);
        rewind($stream);
        $contents = stream_get_contents($stream);

        static::assertIsString($contents);
        static::assertStringContainsString('COMPLETED', $contents);
    }

    #[Test]
    public function itFreezesProfilerDashboardAndShowsFailedStatus(): void
    {
        $output = $this->createMock(ConsoleOutput::class);
        $stream = fopen('php://memory', 'rw');
        $output->method('getStream')->willReturn($stream);

        $formatter = $this->createMock(OutputFormatterInterface::class);
        $formatter->method('format')->willReturnCallback(static fn (?string $s) => $s ?? '');
        $output->method('getFormatter')->willReturn($formatter);

        $this->manager->initialize($output);
        $this->manager->freeze($this->createSnapshot(), true, 1);

        static::assertIsResource($stream);
        rewind($stream);
        $contents = stream_get_contents($stream);

        static::assertIsString($contents);
        static::assertStringContainsString('FAILED', $contents);
    }
}
