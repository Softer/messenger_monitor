<?php

declare(strict_types=1);

namespace SoftLab\MessengerMonitorBundle;

use SoftLab\MessengerMonitorBundle\History\DoctrineMessageHistoryProvider;
use SoftLab\MessengerMonitorBundle\History\MessageHistoryRecorder;
use SoftLab\MessengerMonitorBundle\Queue\DoctrineQueueStatsProvider;
use SoftLab\MessengerMonitorBundle\Queue\QueueStatsProviderInterface;
use SoftLab\MessengerMonitorBundle\Supervisor\ProcessSupervisorManager;
use SoftLab\MessengerMonitorBundle\Supervisor\SupervisorManagerInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class SoftLabMessengerMonitorBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                if (!$container->hasDefinition(DoctrineQueueStatsProvider::class)) {
                    return;
                }

                $transportNames = [];

                foreach ($container->findTaggedServiceIds('messenger.receiver') as $tags) {
                    foreach ($tags as $tag) {
                        if (isset($tag['alias'])) {
                            $transportNames[] = $tag['alias'];
                        }
                    }
                }

                $failureTransports = [];

                foreach ($container->getExtensionConfig('framework') as $frameworkConfig) {
                    if (isset($frameworkConfig['messenger']['failure_transport'])) {
                        $failureTransports[] = $frameworkConfig['messenger']['failure_transport'];
                    }
                }

                $transportNames = array_filter(
                    $transportNames,
                    static fn(string $n) => !\in_array($n, $failureTransports, true),
                );

                $definition = $container->getDefinition(DoctrineQueueStatsProvider::class);
                $existing = $definition->getArgument('$knownQueues');

                $definition->setArgument(
                    '$knownQueues',
                    array_values(array_unique([...$transportNames, ...$existing])),
                );
            }
        });
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('supervisor')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('supervisorctl_path')
                            ->defaultValue('supervisorctl')
                            ->info('Path to supervisorctl binary')
                        ->end()
                        ->scalarNode('process_group')
                            ->defaultNull()
                            ->info('Filter by supervisor group name (null = show all)')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('queue')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('connection')
                            ->defaultValue('default')
                            ->info('Doctrine DBAL connection name for messenger_messages table')
                        ->end()
                        ->scalarNode('table_name')
                            ->defaultValue('messenger_messages')
                            ->info('Messenger messages table name')
                        ->end()
                        ->arrayNode('names')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                            ->info('Extra queue names to show (auto-detected from Messenger transports by default)')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('history')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Enable message processing history recording')
                        ->end()
                        ->scalarNode('table_name')
                            ->defaultValue('messenger_monitor_history')
                            ->info('Table name for history records')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('api')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultFalse()
                            ->info('Enable JSON API endpoints')
                        ->end()
                        ->scalarNode('prefix')
                            ->defaultValue('/api/messenger-monitor')
                            ->info('API route prefix')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /** @param array<string, mixed> $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        $container->services()
            ->get(ProcessSupervisorManager::class)
            ->arg('$supervisorctlPath', $config['supervisor']['supervisorctl_path'])
            ->arg('$processGroup', $config['supervisor']['process_group'])
        ;

        $container->services()
            ->get(DoctrineQueueStatsProvider::class)
            ->arg('$connectionName', $config['queue']['connection'])
            ->arg('$tableName', $config['queue']['table_name'])
            ->arg('$knownQueues', $config['queue']['names'])
        ;

        $container->services()
            ->get(DoctrineMessageHistoryProvider::class)
            ->arg('$connectionName', $config['queue']['connection'])
            ->arg('$tableName', $config['history']['table_name'])
        ;

        $container->services()
            ->get(MessageHistoryRecorder::class)
            ->arg('$connectionName', $config['queue']['connection'])
            ->arg('$tableName', $config['history']['table_name'])
        ;

        if (!$config['history']['enabled']) {
            $builder->removeDefinition(MessageHistoryRecorder::class);
        }

        $builder->setAlias(SupervisorManagerInterface::class, ProcessSupervisorManager::class);
    }

}
