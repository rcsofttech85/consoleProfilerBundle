<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Tests\EventListener;

use Exception;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RcSoftTech\ConsoleProfilerBundle\Doctrine\QueryCounter;
use RcSoftTech\ConsoleProfilerBundle\EventListener\ConsoleProfilerListener;
use RcSoftTech\ConsoleProfilerBundle\Service\MetricsCollector;
use RcSoftTech\ConsoleProfilerBundle\Service\MetricsProviderInterface;
use RcSoftTech\ConsoleProfilerBundle\Service\ProfileExporter;
use RcSoftTech\ConsoleProfilerBundle\Tui\AnsiTerminal;
use RcSoftTech\ConsoleProfilerBundle\Tui\DashboardRenderer;
use RcSoftTech\ConsoleProfilerBundle\Tui\TuiManager;
use RcSoftTech\ConsoleProfilerBundle\Util\FormattingUtils;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\NullOutput;

use function count;

#[CoversClass(ConsoleProfilerListener::class)]
final class ConsoleProfilerListenerTest extends TestCase
{
    use \RcSoftTech\ConsoleProfilerBundle\Tests\TestTrait;

    private MetricsProviderInterface&MockObject $metricsCollector;

    private TuiManager $tuiManager;

    private QueryCounter $queryCounter;

    private ProfileExporter $profileExporter;

    #[Override]
    protected function setUp(): void
    {
        $this->metricsCollector = $this->createMock(MetricsProviderInterface::class);
        $this->tuiManager = new TuiManager(new AnsiTerminal(), new DashboardRenderer(new FormattingUtils()));
        $this->queryCounter = new QueryCounter();
        $this->profileExporter = new ProfileExporter();
    }

    #[Test]
    public function itHasAsEventListenerAttributes(): void
    {
        $ref = new ReflectionClass(ConsoleProfilerListener::class);
        $attributes = $ref->getAttributes(\Symfony\Component\EventDispatcher\Attribute\AsEventListener::class);

        static::assertCount(3, $attributes);
    }

    #[Test]
    public function itCanBeInstantiatedWithDefaults(): void
    {
        $listener = new ConsoleProfilerListener(
            metricsCollector: new MetricsCollector('test'),
            tuiManager: new TuiManager(new AnsiTerminal(), new DashboardRenderer(new FormattingUtils())),
            queryCounter: new QueryCounter(),
        );

        $ref = new ReflectionClass($listener);
        static::assertTrue($ref->isFinal());
    }

    #[Test]
    public function itCanBeInstantiatedWithCustomExcludedCommands(): void
    {
        $listener = new ConsoleProfilerListener(
            metricsCollector: new MetricsCollector('test'),
            tuiManager: new TuiManager(new AnsiTerminal(), new DashboardRenderer(new FormattingUtils())),
            queryCounter: new QueryCounter(),
            refreshInterval: 2,
            excludedCommands: ['list', 'help', 'custom:skip'],
        );

        $ref = new ReflectionClass($listener);
        static::assertSame(3, count($ref->getAttributes(\Symfony\Component\EventDispatcher\Attribute\AsEventListener::class)));
    }

    #[Test]
    public function itSkipsExcludedCommands(): void
    {
        $listener = new ConsoleProfilerListener(
            $this->metricsCollector,
            $this->tuiManager,
            $this->queryCounter,
            1,
            ['list']
        );

        $this->metricsCollector->expects(static::never())->method('start');

        $command = new Command('list');
        $event = new ConsoleCommandEvent($command, new StringInput(''), new ConsoleOutput());

        $listener->onCommand($event);
    }

    #[Test]
    public function itSkipsNonConsoleOutput(): void
    {
        $listener = new ConsoleProfilerListener(
            $this->metricsCollector,
            $this->tuiManager,
            $this->queryCounter,
        );

        $this->metricsCollector->expects(static::never())->method('start');

        $command = new Command('app:test');
        $event = new ConsoleCommandEvent($command, new StringInput(''), new NullOutput());

        $listener->onCommand($event);
    }

    #[Test]
    public function itStartsProfilingOnValidCommand(): void
    {
        $listener = new ConsoleProfilerListener(
            $this->metricsCollector,
            $this->tuiManager,
            $this->queryCounter,
        );

        $this->metricsCollector->expects(static::once())->method('start')->with('app:test');
        $this->metricsCollector->method('snapshot')->willReturn($this->createSnapshot());

        $command = new Command('app:test');

        $output = $this->createMock(ConsoleOutput::class);
        $stream = fopen('php://memory', 'rw');
        $output->method('getStream')->willReturn($stream);
        $output->method('getFormatter')->willReturn(new \Symfony\Component\Console\Formatter\OutputFormatter());

        $event = new ConsoleCommandEvent($command, new StringInput(''), $output);

        $listener->onCommand($event);

        static::assertTrue($this->tuiManager->isInitialized());
    }

    #[Test]
    public function itFreezesOnTerminate(): void
    {
        $listener = new ConsoleProfilerListener(
            $this->metricsCollector,
            $this->tuiManager,
            $this->queryCounter,
            1,
            [],
            $this->profileExporter,
            '/tmp/dump.json'
        );

        $command = new Command('app:test');
        $output = $this->createMock(ConsoleOutput::class);
        $stream = fopen('php://memory', 'rw');
        $output->method('getStream')->willReturn($stream);
        $output->method('getFormatter')->willReturn(new \Symfony\Component\Console\Formatter\OutputFormatter());

        $snapshot = $this->createSnapshot();
        $this->metricsCollector->method('snapshot')->willReturn($snapshot);

        $eventStart = new ConsoleCommandEvent($command, new StringInput(''), $output);
        $listener->onCommand($eventStart);

        if (file_exists('/tmp/dump.json')) {
            unlink('/tmp/dump.json');
        }

        $eventTerminate = new ConsoleTerminateEvent($command, new StringInput(''), $output, 0);
        $listener->onTerminate($eventTerminate);

        static::assertFileExists('/tmp/dump.json');
        unlink('/tmp/dump.json');

        static::assertIsResource($stream);
        rewind($stream);
        $contents = stream_get_contents($stream);
        static::assertStringContainsString('COMPLETED', $contents);

        // Calling terminate again should be a no-op due to !$this->active
        $listener->onTerminate($eventTerminate);
    }

    #[Test]
    public function itFreezesAsFailedOnErrorThenTerminate(): void
    {
        $listener = new ConsoleProfilerListener(
            $this->metricsCollector,
            $this->tuiManager,
            $this->queryCounter,
        );

        $command = new Command('app:test');
        $output = $this->createMock(ConsoleOutput::class);
        $stream = fopen('php://memory', 'rw');
        $output->method('getStream')->willReturn($stream);
        $output->method('getFormatter')->willReturn(new \Symfony\Component\Console\Formatter\OutputFormatter());

        $snapshot = $this->createSnapshot();
        $this->metricsCollector->method('snapshot')->willReturn($snapshot);

        $eventStart = new ConsoleCommandEvent($command, new StringInput(''), $output);
        $listener->onCommand($eventStart);

        // ERROR only sets the failure flag, does NOT freeze
        $eventError = new ConsoleErrorEvent(new StringInput(''), $output, new Exception(), $command);
        $eventError->setExitCode(1);
        $listener->onError($eventError);

        // TERMINATE freezes with FAILED status and the correct exit code
        $eventTerminate = new ConsoleTerminateEvent($command, new StringInput(''), $output, 1);
        $listener->onTerminate($eventTerminate);

        static::assertIsResource($stream);
        rewind($stream);
        $contents = stream_get_contents($stream);
        static::assertStringContainsString('FAILED', $contents);
    }

    #[Test]
    public function itHandlesPcntlFallback(): void
    {
        $listener = new ConsoleProfilerListener(
            $this->metricsCollector,
            $this->tuiManager,
            $this->queryCounter,
        );

        $reflection = new ReflectionMethod($listener, 'callPcntl');
        $reflection->setAccessible(true);

        static::assertNull($reflection->invoke($listener, 'non_existent_function'));
        static::assertTrue($reflection->invoke($listener, 'is_bool', true));
    }
}
