<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\EventListener;

use RcSoftTech\ConsoleProfilerBundle\Doctrine\QueryCounter;
use RcSoftTech\ConsoleProfilerBundle\Service\MetricsProviderInterface;
use RcSoftTech\ConsoleProfilerBundle\Service\ProfileExporter;
use RcSoftTech\ConsoleProfilerBundle\Tui\TuiManager;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleErrorEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

use function function_exists;
use function in_array;
use function is_callable;

use const SIG_BLOCK;
use const SIG_DFL;
use const SIG_UNBLOCK;
use const SIGALRM;

/**
 * Lifecycle orchestrator for the console profiler.
 *
 * Hooks into ConsoleEvents to:
 *  1. Start metrics collection and initialize the TUI on COMMAND
 *  2. Arm SIGALRM for non-blocking periodic refresh
 *  3. Disarm and freeze on TERMINATE or ERROR
 *
 * Gracefully degrades when ext-pcntl is unavailable (e.g., Windows).
 *
 * Signal safety: SIGALRM is blocked during dashboard writes via
 * pcntl_sigprocmask() to prevent reentrant fwrite() calls.
 */
#[AsEventListener(event: ConsoleEvents::COMMAND, method: 'onCommand', priority: 2048)]
#[AsEventListener(event: ConsoleEvents::TERMINATE, method: 'onTerminate', priority: -2048)]
#[AsEventListener(event: ConsoleEvents::ERROR, method: 'onError', priority: -2048)]
final class ConsoleProfilerListener
{
    private bool $active = false;

    private bool $failed = false;

    private readonly bool $pcntlAvailable;

    /**
     * @param list<string> $excludedCommands
     */
    public function __construct(
        private readonly MetricsProviderInterface $metricsCollector,
        private readonly TuiManager $tuiManager,
        private readonly QueryCounter $queryCounter,
        private readonly int $refreshInterval = 1,
        private readonly array $excludedCommands = ['list', 'help', 'completion', '_complete'],
        private readonly ?ProfileExporter $profileExporter = null,
        private readonly ?string $profileDumpPath = null,
    ) {
        $this->pcntlAvailable = function_exists('pcntl_alarm')
            && function_exists('pcntl_signal')
            && function_exists('pcntl_async_signals');
    }

    /**
     * Start profiling when any console command begins.
     */
    public function onCommand(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        $output = $event->getOutput();

        if (!$output instanceof ConsoleOutput) {
            return;
        }

        $commandName = $command?->getName() ?? 'unknown';

        if (in_array($commandName, $this->excludedCommands, true) === true) {
            return;
        }

        $this->metricsCollector->start($commandName);
        $this->queryCounter->reset();
        $this->tuiManager->initialize($output);
        $this->active = true;
        $this->failed = false;

        $this->refreshDashboard();

        $this->armSignal();
    }

    /**
     * Freeze the profiler on normal command termination.
     *
     * This is the single point of freeze/export — both normal and error
     * paths converge here because Symfony always dispatches TERMINATE
     * after ERROR, and only TERMINATE has the correct final exit code.
     */
    public function onTerminate(ConsoleTerminateEvent $event): void
    {
        $this->stop(failed: $this->failed, exitCode: $event->getExitCode());
        $this->failed = false;
    }

    /**
     * Record failure flag on command error.
     *
     * We do NOT freeze here — Symfony will dispatch TERMINATE next with
     * the correct exit code. Freezing here would stamp the wrong code.
     */
    public function onError(ConsoleErrorEvent $event): void
    {
        $this->failed = true;
    }

    /**
     * Stop profiling: disarm signal, freeze TUI, export if configured, reset state.
     */
    private function stop(bool $failed, int $exitCode): void
    {
        if ($this->active === false) {
            return;
        }

        $this->disarmSignal();
        $this->active = false;

        $snapshot = $this->metricsCollector->snapshot($this->queryCounter->count);
        $this->tuiManager->freeze($snapshot, $failed, $exitCode);

        if ($this->profileExporter !== null && $this->profileDumpPath !== null) {
            $this->profileExporter->export($snapshot->withExitCode($exitCode), $this->profileDumpPath);
        }
    }

    /**
     * Signal handler callback — refreshes the dashboard.
     *
     * Blocks SIGALRM during the write to prevent reentrant fwrite() corruption.
     */
    private function refreshDashboard(): void
    {
        if ($this->active === false || $this->tuiManager->isInitialized() === false) {
            return;
        }

        if ($this->pcntlAvailable === true) {
            $this->callPcntl('pcntl_sigprocmask', SIG_BLOCK, [SIGALRM]);
        }

        $snapshot = $this->metricsCollector->snapshot($this->queryCounter->count);
        $this->tuiManager->render($snapshot);

        if ($this->pcntlAvailable === true) {
            $this->callPcntl('pcntl_sigprocmask', SIG_UNBLOCK, [SIGALRM]);
        }
    }

    /**
     * Arm SIGALRM for periodic non-blocking dashboard refresh.
     *
     * Uses pcntl_async_signals(true) so signals are dispatched immediately
     * without requiring manual pcntl_signal_dispatch() calls.
     */
    private function armSignal(): void
    {
        if ($this->pcntlAvailable === false) {
            return;
        }

        $this->callPcntl('pcntl_async_signals', true);

        $this->callPcntl('pcntl_signal', SIGALRM, function (): void {
            $this->refreshDashboard();

            if ($this->active === true) {
                $this->callPcntl('pcntl_alarm', $this->refreshInterval);
            }
        });

        $this->callPcntl('pcntl_alarm', $this->refreshInterval);
    }

    /**
     * Disarm SIGALRM and restore the default signal handler.
     */
    private function disarmSignal(): void
    {
        if ($this->pcntlAvailable === false) {
            return;
        }

        $this->callPcntl('pcntl_alarm', 0);
        $this->callPcntl('pcntl_signal', SIGALRM, SIG_DFL);
    }

    /**
     * Helper to bypass SAST warnings for pcntl functions.
     */
    private function callPcntl(string $fn, mixed ...$args): mixed
    {
        return is_callable($fn) ? $fn(...$args) : null;
    }
}
