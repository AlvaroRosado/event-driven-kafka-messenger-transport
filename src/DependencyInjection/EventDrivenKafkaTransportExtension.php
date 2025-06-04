<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\DependencyInjection;

use ARO\KafkaMessenger\Transport\Configuration\Consumer\ConsumerConfigurationValidator;
use ARO\KafkaMessenger\Transport\Configuration\ConfigurationBuilder;
use ARO\KafkaMessenger\Transport\Configuration\Producer\ProducerConfigurationValidator;
use ARO\KafkaMessenger\Transport\KafkaTransportFactory;
use ARO\KafkaMessenger\Transport\Hook\KafkaTransportHookInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

class EventDrivenKafkaTransportExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->register(ConfigurationBuilder::class, ConfigurationBuilder::class);

        $container->registerForAutoconfiguration(KafkaTransportHookInterface::class)
            ->addTag(KafkaTransportHookInterface::class);

        $container->register(KafkaTransportFactory::class, KafkaTransportFactory::class)
            ->setArguments([
                new Reference(ConfigurationBuilder::class),
                null,
                $config
            ])
            ->addTag('messenger.transport_factory');

        $consumerValidator = new ConsumerConfigurationValidator();
        $consumerValidator->validate($config, 'In kafka_messenger.consumer configuration');

        $producerValidator = new ProducerConfigurationValidator();
        $producerValidator->validate($config, 'In kafka_messenger.producer configuration');
    }

    public function getAlias(): string
    {
        return 'event_driven_kafka_transport';
    }
}
