<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RcSoftTech\ConsoleProfilerBundle\ConsoleProfilerBundle;
use RcSoftTech\ConsoleProfilerBundle\EventListener\ConsoleProfilerListener;
use ReflectionObject;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Loader\DefinitionFileLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

#[CoversClass(ConsoleProfilerBundle::class)]
final class ConsoleProfilerBundleTest extends TestCase
{
    #[Test]
    public function itConfiguresTheDefinition(): void
    {
        $bundle = new ConsoleProfilerBundle();
        $treeBuilder = new TreeBuilder('console_profiler');
        $loader = $this->createMock(DefinitionFileLoader::class);
        $configurator = new DefinitionConfigurator($treeBuilder, $loader, __DIR__, __FILE__);

        $bundle->configure($configurator);

        $rootNode = $treeBuilder->getRootNode();
        $reflection = new ReflectionObject($rootNode);
        $childrenProperty = $reflection->getProperty('children');
        $childrenProperty->setAccessible(true);
        $children = $childrenProperty->getValue($rootNode);

        static::assertIsArray($children);
        static::assertArrayHasKey('enabled', $children);
        static::assertArrayHasKey('refresh_interval', $children);
    }

    #[Test]
    public function itLoadsExtensionWhenEnabled(): void
    {
        $bundle = new ConsoleProfilerBundle();
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => true,
            'kernel.environment' => 'dev',
            'kernel.project_dir' => __DIR__,
        ]));

        $loader = $this->createMock(PhpFileLoader::class);
        $instanceof = [];
        $configurator = new ContainerConfigurator($container, $loader, $instanceof, __DIR__, __FILE__);

        $config = [
            'enabled' => true,
            'exclude_in_prod' => true,
            'refresh_interval' => 1,
            'excluded_commands' => ['list'],
            'profile_dump_path' => '/tmp/prof.json',
        ];

        $bundle->loadExtension(
            $config,
            $configurator,
            $container
        );

        static::assertTrue($container->has(ConsoleProfilerListener::class));

        $listenerDef = $container->getDefinition(ConsoleProfilerListener::class);
        static::assertSame(1, $listenerDef->getArgument('$refreshInterval'));
    }

    #[Test]
    public function itSkipsLoadingWhenDisabled(): void
    {
        $bundle = new ConsoleProfilerBundle();
        $container = new ContainerBuilder();

        $loader = $this->createMock(PhpFileLoader::class);
        $instanceof = [];
        $configurator = new ContainerConfigurator($container, $loader, $instanceof, __DIR__, __FILE__);

        $bundle->loadExtension(
            [
                'enabled' => false,
                'exclude_in_prod' => true,
                'refresh_interval' => 1,
                'excluded_commands' => [],
                'profile_dump_path' => null,
            ],
            $configurator,
            $container
        );

        static::assertFalse($container->has(ConsoleProfilerListener::class));
    }

    #[Test]
    public function itSkipsLoadingInProdDebugFalse(): void
    {
        $bundle = new ConsoleProfilerBundle();
        $container = new ContainerBuilder(new ParameterBag(['kernel.debug' => false]));

        $loader = $this->createMock(PhpFileLoader::class);
        $instanceof = [];
        $configurator = new ContainerConfigurator($container, $loader, $instanceof, __DIR__, __FILE__);

        $bundle->loadExtension(
            [
                'enabled' => true,
                'exclude_in_prod' => true,
                'refresh_interval' => 1,
                'excluded_commands' => [],
                'profile_dump_path' => null,
            ],
            $configurator,
            $container
        );

        static::assertFalse($container->has(ConsoleProfilerListener::class));
    }
}
