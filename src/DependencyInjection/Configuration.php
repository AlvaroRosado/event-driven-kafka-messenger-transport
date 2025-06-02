<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('event_driven_kafka_transport');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('consumer')
                    ->addDefaultsIfNotSet()
                    ->info('Default configuration for Kafka consumers')
                    ->children()
                        ->booleanNode('commit_async')
                            ->defaultTrue()
                            ->info('Use async commit (true/false)')
                        ->end()
                        ->integerNode('consume_timeout_ms')
                            ->defaultNull()
                            ->info('ConsumerConfiguration timeout in milliseconds')
                        ->end()
                        ->arrayNode('config')
                            ->info('Kafka consumer configuration')
                            ->variablePrototype()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('producer')
                    ->addDefaultsIfNotSet()
                    ->info('Default configuration for Kafka producers')
                    ->children()
                        ->arrayNode('config')
                            ->info('Kafka producer configuration')
                            ->variablePrototype()->end()
                            ->defaultValue([
                                'client.id' => 'rms',
                                'linger.ms' => '1'
                            ])
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
