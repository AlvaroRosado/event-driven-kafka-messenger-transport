<?php

namespace ARO\KafkaMessenger\Transport;

use ARO\KafkaMessenger\Transport\Setting\ConsumerSetting;
use ARO\KafkaMessenger\Transport\Setting\GeneralSetting;
use ARO\KafkaMessenger\Transport\Setting\ProducerSetting;
use ARO\KafkaMessenger\Transport\Setting\SettingManager;

final class KafkaTransportSettingResolver
{
    private const array AVAILABLE_OPTIONS = [
        'identifier',
        'consumer',
        'producer',
        'topics',
        'validate_schema',
        'schema_registry',
        'transport_name',
        'serializer'
    ];

    public function resolve(string $dsn, array $globalOptions, array $transportOptions): GeneralSetting
    {
        $parsedUrl = parse_url($dsn);
        if (false === $parsedUrl || !isset($parsedUrl['host'], $parsedUrl['port'], $parsedUrl["scheme"])) {
            throw new \InvalidArgumentException(sprintf('The given Kafka DSN "%s" is invalid.', $dsn));
        }

        if ('kafka' !== $parsedUrl['scheme']) {
            throw new \InvalidArgumentException(sprintf('The given Kafka DSN "%s" must start with "kafka://".', $dsn));
        }

        $options = array_replace_recursive($globalOptions, $transportOptions);
        $transportName = $options["transport_name"];

        $invalidOptions = array_diff(
            array_keys($options),
            self::AVAILABLE_OPTIONS,
        );

        if (0 < \count($invalidOptions)) {
            throw new \InvalidArgumentException(sprintf('Invalid option(s) "%s" passed to the %s transport.', implode('", "', $invalidOptions), $transportName));
        }

        if (
            !\array_key_exists('consumer', $options)
            && !\array_key_exists('producer', $options)
        ) {
            throw new \InvalidArgumentException('At least one of "consumer" or "producer" options is required for the %s transport.'. $transportName);
        }

        if (!isset($options['identifier']['staticMethod']) || !\is_string($options['identifier']['staticMethod'])) {
            throw new \InvalidArgumentException(sprintf('The "staticMethodIdentifier" option type must be string, "%s" %s', \gettype($options['identifier']['staticMethod']), $transportName));
        }

        $configValidator = new SettingManager();
        $consumerOptions = $configValidator->setupConsumerOptions($options, sprintf(" given in the consumer option of transport %s", $transportName));
        $producerOptions = $configValidator->setupProducerOptions($options, sprintf(" given in the producer option of transport %s", $transportName));

        if (empty($consumerOptions['topics']) && empty($producerOptions['topics']) && empty($options['topics'])) {
            throw new \InvalidArgumentException(sprintf('At least one of "consumer.topics", "producer.topics" or "topics" options is required for the %s transport.', $transportName));
        }

        $topics = $options['topics'] ?? null;

        if (empty($consumerOptions['topics'])) {
            $consumerOptions['topics'] = $topics;
        }

        if (empty($producerOptions['topics'])) {
            $producerOptions['topics'] = $topics;
        }

        $validateSchema = $options['validate_schema'] ?? null;

        if (!is_null($validateSchema)) {
            $consumerOptions['validate_schema'] = $validateSchema;
            $producerOptions['validate_schema'] = $validateSchema;
        }

        if (isset($options["serializer"]) && (!is_string($options["serializer"]) || !class_exists($options["serializer"]))) {
            throw new \InvalidArgumentException(sprintf('The "serializer" option type must be a class name string with a valid serializer, "%s" ', $options["serializer"]));
        }

        $consumerOptions['routing'] = array_column($consumerOptions['routing'], 'class', 'name');
        $producerOptions['routing'] = array_column($producerOptions['routing'], 'topic', 'name');

        $consumerConfig = new ConsumerSetting(
            routing: $consumerOptions['routing'],
            config: $consumerOptions['config'],
            topics: $consumerOptions['topics'],
            consumeTimeout: $consumerOptions['consume_timeout_ms'],
            commitAsync: $consumerOptions['commit_async'],
            validateSchema: $consumerOptions['validate_schema'],
        );

        $producerConfig = new ProducerSetting(
            routing: $producerOptions['routing'],
            config: $producerOptions['config'],
            topics: $producerOptions['topics'],
            pollTimeoutMs: $producerOptions['poll_timeout_ms'],
            flushTimeoutMs: $producerOptions['flush_timeout_ms'],
            validateSchema: $producerOptions['validate_schema'],
        );

        return new GeneralSetting(
            host: $parsedUrl["host"].":".$parsedUrl["port"],
            transportName: $options["transport_name"],
            staticMethodIdentifier: $options['identifier']['staticMethod'],
            producer: $producerConfig,
            consumer: $consumerConfig,
            serializer: $options['serializer'] ?? null,
        );
    }
}
