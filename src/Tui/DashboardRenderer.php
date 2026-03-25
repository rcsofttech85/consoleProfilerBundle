<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Tui;

use RcSoftTech\ConsoleProfilerBundle\Enum\ProfilerStatus;
use RcSoftTech\ConsoleProfilerBundle\Service\MetricsSnapshot;
use RcSoftTech\ConsoleProfilerBundle\Util\FormattingUtils;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;

use function max;
use function min;
use function round;
use function sprintf;
use function str_repeat;

/**
 * Handles the visual layout, rendering logic, and custom styles of the dashboard.
 *
 * Owns all presentation concerns — both style registration and layout building —
 * decoupled from terminal management and data collection.
 */
final class DashboardRenderer
{
    private const int BAR_WIDTH = 20;

    public function __construct(
        private readonly FormattingUtils $formatter,
    ) {
    }

    /**
     * Register all custom output formatter styles.
     */
    public function registerStyles(OutputInterface $output): void
    {
        $f = $output->getFormatter();

        $f->setStyle('profiler_border', new OutputFormatterStyle('blue'));
        $f->setStyle('profiler_dim', new OutputFormatterStyle(null));
        $f->setStyle('profiler_title', new OutputFormatterStyle('white', null, ['bold']));
        $f->setStyle('profiler_label', new OutputFormatterStyle('cyan'));
        $f->setStyle('profiler_value', new OutputFormatterStyle('white'));
        $f->setStyle('profiler_value_bright', new OutputFormatterStyle('bright-white', null, ['bold']));
        $f->setStyle('profiler_icon', new OutputFormatterStyle('yellow'));
        $f->setStyle('profiler_running', new OutputFormatterStyle('black', 'yellow', ['bold']));
        $f->setStyle('profiler_ok', new OutputFormatterStyle('black', 'green', ['bold']));
        $f->setStyle('profiler_fail', new OutputFormatterStyle('white', 'red', ['bold']));
        $f->setStyle('profiler_ok_text', new OutputFormatterStyle('green', null, ['bold']));
        $f->setStyle('profiler_warn', new OutputFormatterStyle('bright-yellow', null, ['bold']));
        $f->setStyle('profiler_bar_ok', new OutputFormatterStyle('green'));
        $f->setStyle('profiler_bar_warn', new OutputFormatterStyle('yellow'));
        $f->setStyle('profiler_bar_danger', new OutputFormatterStyle('red'));
    }

    /**
     * @return array<int, string>
     */
    public function build(MetricsSnapshot $snapshot, ProfilerStatus $status, int $terminalWidth, ?int $exitCode = null): array
    {
        $w = $terminalWidth - 2; // inner width
        $dbl = str_repeat('═', $w);
        $sng = str_repeat('─', $w);

        $statusBadge = $this->getStatusBadge($status);
        $timecode = $this->formatter->formatDurationCompact($snapshot->duration);

        $exitStr = $this->formatExitStr($exitCode);

        $memRatio = $this->calculateRatio($snapshot->memoryUsage, $snapshot->memoryLimit);
        $peakRatio = $this->calculateRatio($snapshot->peakMemory, $snapshot->memoryLimit);

        $memBar = $this->progressBar($memRatio);
        $peakBar = $this->progressBar($peakRatio);

        $memPctStr = $this->formatPercentage($memRatio);
        $peakPctStr = $this->formatPercentage($peakRatio);
        $memLimitStr = $this->formatMemoryLimitStr($snapshot->memoryLimit);

        // Memory growth rate — real-time leak detection
        $growthStr = $this->formatGrowthRate($snapshot->memoryGrowthRate);

        $qps = $this->calculateQps($snapshot->queryCount, $snapshot->duration);
        $opcache = $snapshot->opcacheEnabled === true ? '<profiler_ok_text>✓</profiler_ok_text>' : '<profiler_dim>✗</profiler_dim>';
        $xdebug = $snapshot->xdebugEnabled === true ? '<profiler_warn>✓ active</profiler_warn>' : '<profiler_dim>✗</profiler_dim>';

        $cmd = $this->formatter->truncate($snapshot->commandName, 35);
        $memTrend = $this->getTrendIcon($snapshot->memoryTrend);
        $queryTrend = $this->getTrendIcon($snapshot->queryTrend);

        return [
            "<profiler_border>╔═{$dbl}═╗</>",
            $this->boxLine("  <profiler_title>CONSOLE PROFILER</>          {$statusBadge}   <profiler_dim>{$timecode}</>{$exitStr}", $w),
            "<profiler_border>╠═{$dbl}═╣</>",
            $this->boxLine("  <profiler_label>COMMAND  </> <profiler_value_bright>{$cmd}</>", $w),
            $this->boxLine("  <profiler_label>PROCESS  </> <profiler_label>PID:</> <profiler_value>{$snapshot->pid}</>  <profiler_label>ENV:</> <profiler_value>{$snapshot->environment}</>  <profiler_label>PHP:</> <profiler_value>{$snapshot->phpVersion}</> <profiler_dim>({$snapshot->sapiName})</>", $w),
            $this->boxLine("  <profiler_label>DEBUG    </> <profiler_label>XDEBUG:</> {$xdebug}  <profiler_label>OPCACHE:</> {$opcache}", $w),
            "<profiler_border>╟─{$sng}─╢</>",
            $this->boxLine('  <profiler_title>RESOURCES</>', $w),
            $this->boxLine("  <profiler_label>DURATION </> <profiler_value_bright>{$this->formatter->formatDuration($snapshot->duration)}</>  <profiler_dim>(CPU: {$this->formatter->formatDuration($snapshot->cpuUserTime)} u, {$this->formatter->formatDuration($snapshot->cpuSystemTime)} s)</>", $w),
            $this->boxLine("  <profiler_label>MEMORY   </> <profiler_value_bright>{$this->formatter->formatBytes($snapshot->memoryUsage)}</> <profiler_dim>/ {$memLimitStr}</>  {$memBar} <profiler_value>{$memPctStr}</> {$memTrend}", $w),
            $this->boxLine("  <profiler_label>GROWTH   </> {$growthStr} <profiler_dim>per second</>", $w),
            $this->boxLine("  <profiler_label>PEAK     </> <profiler_value_bright>{$this->formatter->formatBytes($snapshot->peakMemory)}</>           {$peakBar} <profiler_value>{$peakPctStr}</> <profiler_title>▲</>", $w),
            "<profiler_border>╟─{$sng}─╢</>",
            $this->boxLine('  <profiler_title>DATABASE & APP</>', $w),
            $this->boxLine("  <profiler_label>SQL      </> <profiler_value_bright>{$this->formatter->num($snapshot->queryCount)}</> <profiler_dim>queries</>  {$queryTrend}  <profiler_label>VELOCITY:</> <profiler_value>".sprintf('%.1f', $qps).'</> <profiler_dim>q/s</>', $w),
            $this->boxLine("  <profiler_label>LOADED   </> <profiler_label>CLASSES:</> <profiler_value>{$this->formatter->num($snapshot->loadedClasses)}</>  <profiler_label>FILES:</> <profiler_value>{$this->formatter->num($snapshot->includedFiles)}</>  <profiler_label>GC:</> <profiler_value>{$snapshot->gcCycles}</> <profiler_dim>runs</>", $w),
            "<profiler_border>╚═{$dbl}═╝</>",
        ];
    }

    private function getStatusBadge(ProfilerStatus $status): string
    {
        return match ($status) {
            ProfilerStatus::Running => '<profiler_running> ● PROFILING </profiler_running>',
            ProfilerStatus::Completed => '<profiler_ok> ✓ COMPLETED </profiler_ok>',
            ProfilerStatus::Failed => '<profiler_fail> ✗ FAILED </profiler_fail>',
        };
    }

    /**
     * Format memory growth rate with color coding.
     *
     * Green < 1 MB/s, Yellow < 10 MB/s, Red >= 10 MB/s.
     */
    private function formatGrowthRate(float $bytesPerSec): string
    {
        if ($bytesPerSec <= 0.0) {
            return '';
        }

        $formatted = $this->formatter->formatBytes((int) $bytesPerSec).'/s';

        $colorTag = match (true) {
            $bytesPerSec >= 10_485_760 => 'profiler_bar_danger',  // >= 10 MB/s
            $bytesPerSec >= 1_048_576 => 'profiler_bar_warn',     // >= 1 MB/s
            default => 'profiler_bar_ok',
        };

        return "<{$colorTag}>+{$formatted}</>";
    }

    private function progressBar(float $ratio): string
    {
        $ratio = max(0.0, min(1.0, $ratio));
        $filled = (int) round($ratio * self::BAR_WIDTH);
        $empty = self::BAR_WIDTH - $filled;

        $colorTag = match (true) {
            $ratio > 0.90 => 'profiler_fail',
            $ratio > 0.75 => 'profiler_bar_danger',
            $ratio > 0.50 => 'profiler_bar_warn',
            default => 'profiler_bar_ok',
        };

        return "<profiler_dim>▏</><{$colorTag}>".str_repeat('█', $filled).'</><profiler_dim>'.str_repeat('░', $empty).'▕</>';
    }

    private function getTrendIcon(int $trend): string
    {
        return match ($trend) {
            1 => '<profiler_fail>↑</>',
            -1 => '<profiler_ok_text>↓</>',
            default => '<profiler_dim>→</>',
        };
    }

    private function boxLine(string $content, int $innerWidth): string
    {
        $visibleLength = mb_strlen((string) preg_replace('/<[^>]*>/', '', $content));
        $padding = max(0, $innerWidth - $visibleLength);

        return '<profiler_border>║</>'.$content.str_repeat(' ', $padding).'<profiler_border>║</>';
    }

    private function formatExitStr(?int $exitCode): string
    {
        if ($exitCode === null) {
            return '';
        }

        return '  <profiler_label>Exit:</>  '.($exitCode === 0 ? '<profiler_ok_text>0</>' : '<profiler_fail>'.$exitCode.'</>');
    }

    private function calculateRatio(int $usage, int $limit): float
    {
        if ($limit <= 0) {
            return 0.0;
        }

        return $usage / $limit;
    }

    private function formatPercentage(float $ratio): string
    {
        if ($ratio <= 0.0) {
            return 'n/a';
        }

        return sprintf('%.1f%%', $ratio * 100);
    }

    private function formatMemoryLimitStr(int $limit): string
    {
        if ($limit <= 0) {
            return '∞';
        }

        return $this->formatter->formatBytes($limit);
    }

    private function calculateQps(int $queryCount, float $duration): float
    {
        if ($duration <= 0.0) {
            return 0.0;
        }

        return $queryCount / $duration;
    }
}
