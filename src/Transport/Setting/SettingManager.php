<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport\Setting;

class SettingManager
{
    private const array DEFAULT_CONSUMER_OPTIONS = [
        'commit_async' => true,
        'consume_timeout_ms' => 500,
        'validate_schema' => false,
        'topics' => [],
        'routing' => [],
        'config' => [
            'auto.offset.reset' => 'earliest',
            'enable.auto.commit' => 'false',
            'enable.partition.eof' => 'true',
        ],
    ];

    private const array DEFAULT_PRODUCER_OPTIONS = [
        'validate_schema' => false,
        'poll_timeout_ms' => 0,
        'flush_timeout_ms' => 10000,
        'routing' => [],
        'topics' => [],
        'config' => [],
    ];

    private const array REQUIRED_CONSUMER_CONFIG = [
        'group.id',
    ];

    private const array REQUIRED_PRODUCER_CONFIG = [];

    /**
     * @param array $configOptions Configuration options
     * @param string $contextErrorMessage Context message for errors
     * @return array Configured options
     * @throws \InvalidArgumentException If configuration is invalid
     */
    public function setupConsumerOptions(array $configOptions, string $contextErrorMessage): array
    {
        if (empty($configOptions['consumer'])) {
            return self::DEFAULT_CONSUMER_OPTIONS;
        }

        $configOptions = $configOptions['consumer'];

        $this->validateOptionKeys($configOptions, self::DEFAULT_CONSUMER_OPTIONS, $contextErrorMessage);

        $options = array_replace_recursive(
            self::DEFAULT_CONSUMER_OPTIONS,
            $configOptions
        );

        $this->validateConsumerOptionTypes($options, $contextErrorMessage);
        $this->validateKafkaOptions($options['config'], KafkaOptionList::consumer(), $contextErrorMessage);
        $this->validateRequiredConsumerConfig($options['config'], $contextErrorMessage);

        return $options;
    }

    /**
     * @param array $configOptions Configuration options
     * @param string $contextErrorMessage Context message for errors
     * @return array Configured options
     * @throws \InvalidArgumentException If configuration is invalid
     */
    public function setupProducerOptions(array $configOptions, string $contextErrorMessage): array
    {
        if (empty($configOptions['producer'])) {
            return self::DEFAULT_PRODUCER_OPTIONS;
        }

        $configOptions = $configOptions['producer'];

        $this->validateOptionKeys($configOptions, self::DEFAULT_PRODUCER_OPTIONS, $contextErrorMessage);

        $options = array_replace_recursive(
            self::DEFAULT_PRODUCER_OPTIONS,
            $configOptions
        );

        $this->validateProducerOptionTypes($options, $contextErrorMessage);
        $this->validateKafkaOptions($options['config'], KafkaOptionList::producer(), $contextErrorMessage);
        $this->validateRequiredProducerConfig($options['config'], $contextErrorMessage);

        return $options;
    }

    private function validateOptionKeys(array $options, array $defaultOptions, string $contextErrorMessage): void
    {
        $invalidOptions = array_diff(
            array_keys($options),
            array_keys($defaultOptions)
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

        $this->validateRouting($options['routing'], 'name', 'class', $contextErrorMessage);
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

        $this->validateRouting($options['routing'], 'name', 'topic', $contextErrorMessage);
    }


    private function validateRouting(array $routing, string $nameKey, string $targetKey, string $contextErrorMessage): void
    {
        if (empty($routing)) {
            return;
        }

        if (!\is_array($routing)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'The "routing" option type must be array, "%s" %s.',
                    gettype($routing),
                    $contextErrorMessage
                )
            );
        }

        foreach ($routing as $route) {
            if (!isset($route[$nameKey], $route[$targetKey]) ||
                !\is_string($route[$nameKey]) ||
                !\is_string($route[$targetKey])
            ) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Each "routing" entry must contain "%s" and "%s" %s.',
                        $nameKey,
                        $targetKey,
                        $contextErrorMessage
                    )
                );
            }

            if ($targetKey === 'class' && !class_exists($route[$targetKey])) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'The class "%s" specified in "routing" does not exist %s.',
                        $route[$targetKey],
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
