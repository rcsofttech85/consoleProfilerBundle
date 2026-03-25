<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Tests\Util;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RcSoftTech\ConsoleProfilerBundle\Util\FormattingUtils;

#[CoversClass(FormattingUtils::class)]
final class FormattingUtilsTest extends TestCase
{
    private FormattingUtils $utils;

    #[Override]
    protected function setUp(): void
    {
        $this->utils = new FormattingUtils();
    }

    #[Test]
    public function itFormatsBytesToHumanReadable(): void
    {
        static::assertSame('0 B', $this->utils->formatBytes(0));
        static::assertSame('1.0 KB', $this->utils->formatBytes(1024));
        static::assertSame('1.5 MB', $this->utils->formatBytes(1572864));
    }

    #[Test]
    public function itFormatsDurationToHumanReadable(): void
    {
        static::assertSame('100μs', $this->utils->formatDuration(0.0001));
        static::assertSame('500ms', $this->utils->formatDuration(0.5));
        static::assertSame('4.25s', $this->utils->formatDuration(4.25));
        static::assertSame('2m 5.0s', $this->utils->formatDuration(125.0));
    }

    #[Test]
    public function itFormatsDurationCompact(): void
    {
        static::assertSame('00:05', $this->utils->formatDurationCompact(5.0));
        static::assertSame('02:05', $this->utils->formatDurationCompact(125.0));
    }

    #[Test]
    public function itTruncatesLongStrings(): void
    {
        static::assertSame('Short', $this->utils->truncate('Short', 10));
        static::assertSame('Short', $this->utils->truncate('Short', 5));
        static::assertSame('Sho…', $this->utils->truncate('Short', 4));
        static::assertSame('Long string…', $this->utils->truncate('Long string here', 12));
    }
}
