<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Tests\Service;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RcSoftTech\ConsoleProfilerBundle\Service\ProfileExporter;
use Symfony\Component\Filesystem\Filesystem;

use const JSON_THROW_ON_ERROR;

#[CoversClass(ProfileExporter::class)]
final class ProfileExporterTest extends TestCase
{
    use \RcSoftTech\ConsoleProfilerBundle\Tests\TestTrait;

    private string $tmpDir;

    private Filesystem $fs;

    #[Override]
    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir().'/profiler_test_'.bin2hex(random_bytes(4));
        $this->fs = new Filesystem();
    }

    #[Override]
    protected function tearDown(): void
    {
        $path = $this->tmpDir.'/profile.json';
        if ($this->fs->exists($path) === true) {
            $this->fs->remove($path);
        }
        if ($this->fs->exists($this->tmpDir) === true) {
            $this->fs->remove($this->tmpDir);
        }
    }

    #[Test]
    public function itExportsSnapshotToJsonFile(): void
    {
        $exporter = new ProfileExporter();
        $path = $this->tmpDir.'/profile.json';

        $exporter->export($this->createSnapshot(), $path);

        static::assertFileExists($path);

        $json = $this->fs->readFile($path);

        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        static::assertSame('app:import-data', $data['command']);
        static::assertSame('dev', $data['environment']);
        static::assertNull($data['exit_code']);
        static::assertArrayHasKey('memory', $data);
        static::assertArrayHasKey('cpu', $data);
        static::assertArrayHasKey('counters', $data);
        static::assertArrayHasKey('system', $data);
        static::assertArrayHasKey('timestamp', $data);
    }

    #[Test]
    public function itCreatesDirectoryIfNotExists(): void
    {
        $exporter = new ProfileExporter();
        $path = $this->tmpDir.'/profile.json';

        static::assertDirectoryDoesNotExist($this->tmpDir);

        $exporter->export($this->createSnapshot(), $path);

        static::assertDirectoryExists($this->tmpDir);
        static::assertFileExists($path);
    }

    #[Test]
    public function itIncludesMemoryGrowthRate(): void
    {
        $exporter = new ProfileExporter();
        $path = $this->tmpDir.'/profile.json';

        $exporter->export($this->createSnapshot(), $path);

        /** @var array<string, mixed> $data */
        $data = json_decode($this->fs->readFile($path), true, 512, JSON_THROW_ON_ERROR);

        static::assertIsArray($data['memory']);
        static::assertArrayHasKey('growth_rate_bytes_per_sec', $data['memory']);
        static::assertEquals(512000.0, $data['memory']['growth_rate_bytes_per_sec']);
    }

    #[Test]
    public function itIncludesQueryCounters(): void
    {
        $exporter = new ProfileExporter();
        $path = $this->tmpDir.'/profile.json';

        $exporter->export($this->createSnapshot(), $path);

        /** @var array<string, mixed> $data */
        $data = json_decode($this->fs->readFile($path), true, 512, JSON_THROW_ON_ERROR);

        static::assertIsArray($data['counters']);
        static::assertSame(142, $data['counters']['sql_queries']);
        static::assertSame(312, $data['counters']['loaded_classes']);
    }
}
