<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Util;

use function count;
use function floor;
use function log;
use function mb_strlen;
use function mb_substr;
use function number_format;
use function sprintf;

/**
 * Utility for consistent formatting across the bundle.
 *
 * Adheres to SRP by centralizing data presentation logic.
 */
final class FormattingUtils
{
    public function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return sprintf('%.1f %s', $bytes / (1024 ** $power), $units[$power]);
    }

    public function formatDuration(float $seconds): string
    {
        if ($seconds < 0.001) {
            return sprintf('%dμs', (int) ($seconds * 1_000_000));
        }

        if ($seconds < 1.0) {
            return sprintf('%dms', (int) ($seconds * 1000));
        }

        if ($seconds < 60.0) {
            return sprintf('%.2fs', $seconds);
        }

        $min = (int) floor($seconds / 60);
        $sec = $seconds - ($min * 60);

        return sprintf('%dm %.1fs', $min, $sec);
    }

    public function formatDurationCompact(float $seconds): string
    {
        $min = (int) floor($seconds / 60);
        $sec = (int) $seconds % 60;

        return sprintf('%02d:%02d', $min, $sec);
    }

    public function num(int $value): string
    {
        return number_format($value);
    }

    public function truncate(string $value, int $maxLength): string
    {
        if ($maxLength >= mb_strlen($value)) {
            return $value;
        }

        return mb_substr($value, 0, $maxLength - 1).'…';
    }
}
