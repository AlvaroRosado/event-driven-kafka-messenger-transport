<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport\Configuration\Producer;

use ARO\KafkaMessenger\Transport\KafkaOptionList;

class ProducerConfigurationValidator
{
    private const DEFAULT_PRODUCER_OPTIONS = [
        'poll_timeout_ms' => 0,
        'flush_timeout_ms' => 10000,
        'routing' => [],
        'topics' => [],
        'config' => [],
    ];

    private const REQUIRED_PRODUCER_CONFIG = [];


    public function validate(array $configOptions, ?string $contextErrorMessage = ''): array
    {
        if (empty($configOptions['producer'])) {
            return self::DEFAULT_PRODUCER_OPTIONS;
        }

        $configOptions = $configOptions['producer'];

        $this->validateOptionKeys($configOptions, $contextErrorMessage);

        $options = array_replace_recursive(
            self::DEFAULT_PRODUCER_OPTIONS,
            $configOptions
        );

        $this->validateProducerOptionTypes($options, $contextErrorMessage);
        $this->validateKafkaOptions($options['config'], KafkaOptionList::producer(), $contextErrorMessage);
        $this->validateRequiredProducerConfig($options['config'], $contextErrorMessage);

        return $options;
    }

    private function validateOptionKeys(array $options, string $contextErrorMessage): void
    {
        $invalidOptions = array_diff(
            array_keys($options),
            array_keys(self::DEFAULT_PRODUCER_OPTIONS)
        );

        if (count($invalidOptions) > 0) {
            throw new \InvalidArgumentException(
                sprintf('Invalid option(s) "%s" %s', implode('", "', $invalidOptions), $contextErrorMessage)
            );
        }
    }

    private function validateProducerOptionTypes(array $options, string $contextErrorMessage): void
    {
        if (!\is_int($options['poll_timeout_ms'])) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The "poll_timeout_ms" option type must be integer, "%s" %s',
                    gettype($options['poll_timeout_ms']),
                    $contextErrorMessage
                )
            );
        }

        if (!\is_int($options['flush_timeout_ms'])) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The "flush_timeout_ms" option type must be integer, "%s" %s.',
                    gettype($options['flush_timeout_ms']),
                    $contextErrorMessage
                )
            );
        }

        if (!\is_array($options['topics'])) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The "topics" option type must be array, "%s" %s.',
                    gettype($options['topics']),
                    $contextErrorMessage
                )
            );
        }

        $this->validateRouting($options['routing'], $contextErrorMessage);
    }

    private function validateRouting(array $routing, string $contextErrorMessage): void
    {
        if (empty($routing)) {
            return;
        }

        foreach ($routing as $route) {
            if (!isset($route['name'], $route['topic']) ||
                !\is_string($route['name']) ||
                !\is_string($route['topic'])
            ) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Each "routing" entry must contain "%s" and "%s" %s.',
                        'name',
                        'topic',
                        $contextErrorMessage
                    )
                );
            }

            if (!class_exists($route['topic'])) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'The class "%s" specified in "routing" does not exist %s.',
                        $route['topic'],
                        $contextErrorMessage
                    )
                );
            }
        }
    }

    private function validateRequiredProducerConfig(array $config, string $contextErrorMessage): void
    {
        $missingConfig = array_diff(self::REQUIRED_PRODUCER_CONFIG, array_keys($config));

        if (!empty($missingConfig)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The config(s) "%s" are required for the %s.',
                    implode('", "', $missingConfig),
                    $contextErrorMessage
                )
            );
        }
    }

    private function validateKafkaOptions(array $values, array $availableKafkaOptions, string $contextErrorMessage): void
    {
        foreach ($values as $key => $value) {
            if (!isset($availableKafkaOptions[$key])) {
                throw new \InvalidArgumentException(
                    sprintf('Invalid config option "%s" %s', $key, $contextErrorMessage)
                );
            }

            if (!\is_string($value)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Kafka config value "%s" must be a string, %s, %s',
                        $key,
                        get_debug_type($value),
                        $contextErrorMessage
                    )
                );
            }
        }
    }
}
