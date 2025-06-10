<?php

namespace ARO\KafkaMessenger\Transport\Configuration;

use ARO\KafkaMessenger\Transport\Configuration\Consumer\ConsumerConfiguration;
use ARO\KafkaMessenger\Transport\Configuration\Consumer\ConsumerConfigurationBuilder;
use ARO\KafkaMessenger\Transport\Configuration\Dsn\DsnConfigurationBuilder;
use ARO\KafkaMessenger\Transport\Configuration\JsonSerialization\JsonSerializationConfiguration;
use ARO\KafkaMessenger\Transport\Configuration\Producer\ProducerConfiguration;
use ARO\KafkaMessenger\Transport\Configuration\Producer\ProducerConfigurationBuilder;

final class ConfigurationBuilder
{
    private DsnConfigurationBuilder $dsn;
    private ConsumerConfigurationBuilder $consumerBuilder;
    private ProducerConfigurationBuilder $producerBuilder;

    private const AVAILABLE_OPTIONS = [
        'json_serialization',
        'consumer',
        'producer',
        'topics',
        'transport_name',
        'retry_topic',
    ];

    public function __construct()
    {
        $this->dsn = new DsnConfigurationBuilder();
        $this->consumerBuilder = new ConsumerConfigurationBuilder();
        $this->producerBuilder = new ProducerConfigurationBuilder();
    }

    public function build(string $dsn, array $globalOptions, array $transportOptions): Configuration
    {
        $parsedDsn = $this->dsn->build($dsn);

        $mergedOptions = array_replace_recursive($globalOptions, $transportOptions);

        $invalidOptions = array_diff(
            array_keys($mergedOptions),
            self::AVAILABLE_OPTIONS,
        );

        if (count($invalidOptions) > 0) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Invalid option(s) "%s" for transport "%s". Available: "%s"',
                    implode('", "', $invalidOptions),
                    $parsedDsn->transportName,
                    implode('", "', self::AVAILABLE_OPTIONS)
                )
            );
        }

        $globalTopics = $mergedOptions['topics'] ?? [];

        $jsonConfig = $this->buildJsonSerializationConfig($mergedOptions);

        $consumerConfig = $this->consumerBuilder->build($mergedOptions, $globalTopics);
        $producerConfig = $this->producerBuilder->build($mergedOptions, $globalTopics);

        $this->validateTopicsConfiguration($consumerConfig, $producerConfig, $parsedDsn->transportName);

        return new Configuration(
            dsn: $parsedDsn,
            producer: $producerConfig,
            consumer: $consumerConfig,
            jsonSerialization: $jsonConfig,
            retryTopic: $mergedOptions['retry_topic'] ?? null
        );
    }

    private function buildJsonSerializationConfig(array $options): JsonSerializationConfiguration
    {
        $jsonOptions = $options['json_serialization'] ?? [];

        return new JsonSerializationConfiguration(
            enabled: $jsonOptions['enabled'] ?? false,
            customSerializer: $jsonOptions['custom_serializer'] ?? null
        );
    }

    private function validateTopicsConfiguration(
        ConsumerConfiguration $consumer,
        ProducerConfiguration $producer,
        string $transportName
    ): void {
        if (empty($consumer->topics) && empty($producer->topics)) {
            throw new \InvalidArgumentException(
                sprintf('At least one topic must be configured for transport "%s"', $transportName)
            );
        }
    }
}
