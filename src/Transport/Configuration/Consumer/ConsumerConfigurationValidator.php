<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport\Configuration\Consumer;

use ARO\KafkaMessenger\Transport\KafkaOptionList;

class ConsumerConfigurationValidator
{
    private const DEFAULT_CONSUMER_OPTIONS = [
        'commit_async' => true,
        'consume_timeout_ms' => 500,
        'topics' => [],
        'routing' => [],
        'config' => [
            'auto.offset.reset' => 'earliest',
            'enable.auto.commit' => 'false',
            'enable.partition.eof' => 'true',
        ],
    ];

    private const REQUIRED_CONSUMER_CONFIG = [
        'group.id',
    ];

    public function validate(array $configOptions, ?string $contextErrorMessage = ''): array
    {
        if (empty($configOptions['consumer'])) {
            return self::DEFAULT_CONSUMER_OPTIONS;
        }

        $configOptions = $configOptions['consumer'];

        $this->validateOptionKeys($configOptions, $contextErrorMessage);

        $options = array_replace_recursive(
            self::DEFAULT_CONSUMER_OPTIONS,
            $configOptions
        );

        $this->validateConsumerOptionTypes($options, $contextErrorMessage);
        $this->validateKafkaOptions($options['config'], KafkaOptionList::consumer(), $contextErrorMessage);
        $this->validateRequiredConsumerConfig($options['config'], $contextErrorMessage);

        return $options;
    }

    private function validateOptionKeys(array $options, string $contextErrorMessage): void
    {
        $invalidOptions = array_diff(
            array_keys($options),
            array_keys(self::DEFAULT_CONSUMER_OPTIONS)
        );

        if (count($invalidOptions) > 0) {
            throw new \InvalidArgumentException(
                sprintf('Invalid option(s) "%s" %s', implode('", "', $invalidOptions), $contextErrorMessage)
            );
        }
    }

    private function validateConsumerOptionTypes(array $options, string $contextErrorMessage): void
    {
        if (!\is_bool($options['commit_async'])) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The "commit_async" option type must be boolean, "%s" %s',
                    gettype($options['commit_async']),
                    $contextErrorMessage
                )
            );
        }

        if (!\is_int($options['consume_timeout_ms'])) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The "consume_timeout_ms" option type must be integer, "%s" %s.',
                    gettype($options['consume_timeout_ms']),
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
            if (!isset($route['name'], $route['class']) ||
                !\is_string($route['name']) ||
                !\is_string($route['class'])
            ) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Each "routing" entry must contain "%s" and "%s" %s.',
                        'name',
                        'class',
                        $contextErrorMessage
                    )
                );
            }

            if (!class_exists($route['class'])) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'The class "%s" specified in "routing" does not exist %s.',
                        $route['class'],
                        $contextErrorMessage
                    )
                );
            }
        }
    }

    private function validateRequiredConsumerConfig(array $config, string $contextErrorMessage): void
    {
        $missingConfig = array_diff(self::REQUIRED_CONSUMER_CONFIG, array_keys($config));

        if (!empty($missingConfig)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The config(s) "%s" are required %s.',
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
