<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger;

use ARO\KafkaMessenger\DependencyInjection\CompilerPass\KafkaCompilerPass;
use ARO\KafkaMessenger\SchemaRegistry\SchemaRegistryHttpClient;
use ARO\KafkaMessenger\SchemaRegistry\SchemaRegistryManager;
use ARO\KafkaMessenger\Transport\KafkaTransportFactory;
use ARO\KafkaMessenger\Transport\KafkaTransportSettingResolver;
use ARO\KafkaMessenger\Transport\Metadata\KafkaMetadataHookInterface;
use ARO\KafkaMessenger\Transport\Setting\SettingManager;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class KafkaBundle extends AbstractBundle
{
    protected string $extensionAlias = 'exoticca_kafka_messenger';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('identifier')->addDefaultsIfNotSet()
                    ->info('Schema registry configuration')
                    ->children()
                        ->scalarNode('staticMethod')
                            ->isRequired()
                            ->info('Base URI of the Schema Registry')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('consumer')->addDefaultsIfNotSet()
                    ->info('Default configuration for Kafka consumers')
                    ->children()
                        ->booleanNode('validate_schema')
                            ->defaultFalse()
                            ->info('Enable or disable schema validation for consumers')
                        ->end()
                        ->booleanNode('commit_async')
                            ->defaultTrue()
                            ->info('Use async commit (true/false)')
                        ->end()
                        ->integerNode('consume_timeout_ms')
                            ->defaultNull()
                            ->info('ConsumerSetting timeout in milliseconds')
                        ->end()
                        ->arrayNode('config')
                            ->info('Kafka consumer configuration')
                            ->variablePrototype()->end()
                        ->end()
                    ->end()
                ->end()

                ->arrayNode('producer')->addDefaultsIfNotSet()
                    ->info('Default configuration for Kafka producers')
                    ->children()
                        ->booleanNode('validate_schema')
                            ->defaultFalse()
                            ->info('Enable or disable schema validation for producers')
                        ->end()
                        ->arrayNode('config')
                            ->info('Kafka producer configuration')
                            ->variablePrototype()->end()
                            ->children()
                                ->scalarNode('client.id')
                                    ->defaultValue('rms')
                                ->end()
                                ->scalarNode('linger.ms')
                                    ->defaultValue('1')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()

                ->arrayNode('schema_registry')->addDefaultsIfNotSet()
                    ->info('Schema registry configuration')
                    ->children()
                        ->scalarNode('base_uri')
                            ->defaultNull()
                            ->info('Base URI of the Schema Registry')
                        ->end()
                        ->scalarNode('api_key')
                            ->defaultNull()
                            ->info('API Key of the Schema Registry')
                        ->end()
                        ->scalarNode('api_secret')
                            ->defaultNull()
                            ->info('API Secret of the Schema Registry')
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('serializer')
                    ->defaultNull()
                    ->info('Serializer class to use')
                ->end()
            ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        $services->set(KafkaTransportSettingResolver::class);

        $builder->registerForAutoconfiguration(KafkaMetadataHookInterface::class)
            ->addTag(KafkaMetadataHookInterface::class);

        $services
            ->set(KafkaTransportFactory::class)
            ->args([
                new Reference(KafkaTransportSettingResolver::class),
                new Reference(SchemaRegistryManager::class),
                null,
                null
            ])
            ->tag('messenger.transport_factory');

        $services
            ->set(SchemaRegistryManager::class)
            ->args(
                [
                    new Reference(SchemaRegistryHttpClient::class)
                ]
            )->tag('exoticca.kafka.transport.schema_registry_manager');

        $services->set(SchemaRegistryHttpClient::class)
            ->args([
                $config['schema_registry']["base_uri"],
                $config['schema_registry']["api_key"],
                $config['schema_registry']["api_secret"],
                new Reference(HttpClientInterface::class)
            ])
            ->tag('exoticca.kafka.transport.schema_registry_manager');

        $kafkaConfigValidator = new SettingManager();
        $kafkaConfigValidator->setupConsumerOptions($config, 'In exoticca_kafka_messenger.consumer configuration');
        $kafkaConfigValidator->setupProducerOptions($config, 'In exoticca_kafka_messenger.producer configuration');

        $kafkaTransportDefinition = $builder->getDefinition(KafkaTransportFactory::class);

        $kafkaTransportDefinition->replaceArgument(3, $config);
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new KafkaCompilerPass());
    }
}
