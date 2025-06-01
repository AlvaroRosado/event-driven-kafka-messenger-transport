<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger;

use ARO\KafkaMessenger\DependencyInjection\CompilerPass\KafkaCompilerPass;
use ARO\KafkaMessenger\Transport\Configuration\Consumer\ConsumerConfigurationValidator;
use ARO\KafkaMessenger\Transport\Configuration\ConfigurationBuilder;
use ARO\KafkaMessenger\Transport\Configuration\Producer\ProducerConfigurationValidator;
use ARO\KafkaMessenger\Transport\Configuration\SettingManager;
use ARO\KafkaMessenger\Transport\KafkaTransportFactory;
use ARO\KafkaMessenger\Transport\KafkaTransportSettingResolver;
use ARO\KafkaMessenger\Transport\Hook\KafkaTransportHookInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class KafkaBundle extends AbstractBundle
{
    protected string $extensionAlias = 'kafka_messenger';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('consumer')->addDefaultsIfNotSet()
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

                ->arrayNode('producer')->addDefaultsIfNotSet()
                    ->info('Default configuration for Kafka producers')
                    ->children()
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
            ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $services = $container->services();

        $services->set(ConfigurationBuilder::class);

        $builder->registerForAutoconfiguration(KafkaTransportHookInterface::class)
            ->addTag(KafkaTransportHookInterface::class);

        $services
            ->set(KafkaTransportFactory::class)
            ->args([
                new Reference(ConfigurationBuilder::class),
                null,
                null
            ])
            ->tag('messenger.transport_factory');

        $consumerValidator = new ConsumerConfigurationValidator();
        $consumerValidator->validate($config, 'In kafka_messenger.consumer configuration');

        $producerValidator = new ProducerConfigurationValidator();
        $producerValidator->validate($config, 'In kafka_messenger.consumer configuration');

        $kafkaTransportDefinition = $builder->getDefinition(KafkaTransportFactory::class);
        $kafkaTransportDefinition->replaceArgument(2, $config);
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new KafkaCompilerPass());
    }
}
