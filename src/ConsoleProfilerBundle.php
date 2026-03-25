<?php

declare(strict_types=1);

namespace RcSoftTech\ConsoleProfilerBundle;

use Doctrine\DBAL\Driver\Middleware;
use Override;
use RcSoftTech\ConsoleProfilerBundle\Doctrine\ProfilingMiddleware;
use RcSoftTech\ConsoleProfilerBundle\Doctrine\QueryCounter;
use RcSoftTech\ConsoleProfilerBundle\EventListener\ConsoleProfilerListener;
use RcSoftTech\ConsoleProfilerBundle\Service\MetricsCollector;
use RcSoftTech\ConsoleProfilerBundle\Service\MetricsProviderInterface;
use RcSoftTech\ConsoleProfilerBundle\Service\ProfileExporter;
use RcSoftTech\ConsoleProfilerBundle\Tui\AnsiTerminal;
use RcSoftTech\ConsoleProfilerBundle\Tui\DashboardRenderer;
use RcSoftTech\ConsoleProfilerBundle\Tui\TuiManager;
use RcSoftTech\ConsoleProfilerBundle\Util\FormattingUtils;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

final class ConsoleProfilerBundle extends AbstractBundle
{
    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        /** @var \Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition $rootNode */
        $rootNode = $definition->rootNode();

        $rootNode->children()
            ->booleanNode('enabled')
            ->defaultTrue()
            ->info('Enable or disable the console profiler globally.')
            ->end()
            ->booleanNode('exclude_in_prod')
            ->defaultTrue()
            ->info('Automatically disable the profiler when kernel.debug is false (production).')
            ->end()
            ->integerNode('refresh_interval')
            ->defaultValue(1)
            ->min(1)
            ->max(10)
            ->info('Refresh interval in seconds for the TUI dashboard (pcntl_alarm granularity).')
            ->end()
            ->arrayNode('excluded_commands')
            ->scalarPrototype()->end()
            ->defaultValue(['list', 'help', 'completion', '_complete'])
            ->info('Commands to exclude from profiling.')
            ->end()
            ->scalarNode('profile_dump_path')
            ->defaultNull()
            ->info('Path to write JSON profile dump for CI integration. Null = disabled.')
            ->end()
            ->end();
    }

    /**
     * @param array{enabled: bool, exclude_in_prod: bool, refresh_interval: int, excluded_commands: list<string>, profile_dump_path: ?string} $config
     */
    #[Override]
    public function loadExtension(
        array $config,
        ContainerConfigurator $container,
        ContainerBuilder $builder,
    ): void {
        if ($config['enabled'] === false) {
            return;
        }

        if ($config['exclude_in_prod'] === true && $builder->getParameter('kernel.debug') === false) {
            return;
        }

        $services = $container->services();

        $services->defaults()
            ->autowire()
            ->autoconfigure();

        $services->set(FormattingUtils::class);
        $services->set(AnsiTerminal::class);
        $services->set(DashboardRenderer::class)
            ->arg('$formatter', service(FormattingUtils::class));

        $services->set(QueryCounter::class);

        $services->set(MetricsProviderInterface::class, MetricsCollector::class)
            ->arg('$environment', '%kernel.environment%');
        $services->alias(MetricsCollector::class, MetricsProviderInterface::class);

        $services->set(ProfileExporter::class);

        $services->set(TuiManager::class)
            ->arg('$terminal', service(AnsiTerminal::class))
            ->arg('$renderer', service(DashboardRenderer::class));

        $listenerDef = $services->set(ConsoleProfilerListener::class)
            ->arg('$metricsCollector', service(MetricsProviderInterface::class))
            ->arg('$tuiManager', service(TuiManager::class))
            ->arg('$queryCounter', service(QueryCounter::class))
            ->arg('$refreshInterval', $config['refresh_interval'])
            ->arg('$excludedCommands', $config['excluded_commands']);

        if ($config['profile_dump_path'] !== null) {
            $listenerDef
                ->arg('$profileExporter', service(ProfileExporter::class))
                ->arg('$profileDumpPath', $config['profile_dump_path']);
        }

        if (interface_exists(Middleware::class) === true) {
            $services->set(ProfilingMiddleware::class)
                ->tag('doctrine.middleware');
        }
    }
}
