<?php

namespace ARO\KafkaMessenger\Tests\ObjectMother;

class TransportOptionsObjectMother
{
    public static function createWithJsonSerializationEnabled(): array
    {
        return [
            'topics' => ['user_events'],
            'json_serialization' => [
                'enabled' => true
            ],
            'consumer' => [
                'routing' => [
                    [
                        'name' => 'user_registered',
                        'class' => 'ARO\KafkaMessenger\Tests\Fixture\UserRegistered'
                    ],
                    [
                        'name' => 'user_updated',
                        'class' => 'ARO\KafkaMessenger\Tests\Fixture\UserUpdated'
                    ]
                ],
                'config' => [
                    'group.id' => 'test-app-events'
                ]
            ]
        ];
    }

    public static function createMinimal(): array
    {
        return [
            'topics' => ['minimal_topic']
        ];
    }

    public static function createWithJsonSerializationDisabled(): array
    {
        return [
            'topics' => ['test_topic'],
            'json_serialization' => [
                'enabled' => false,
                'custom_serializer' => 'CustomSerializer'
            ]
        ];
    }

    public static function createWithoutJsonSerialization(): array
    {
        return [
            'topics' => ['test_topic']
        ];
    }

    public static function createWithInvalidOptions(): array
    {
        return [
            'topics' => ['test_topic'],
            'invalid_option' => 'some_value',
            'another_invalid_option' => 'another_value'
        ];
    }

    public static function createWithoutTopics(): array
    {
        return [
            'json_serialization' => [
                'enabled' => true
            ]
        ];
    }

    public static function createWithOverridingConsumerConfig(): array
    {
        return [
            'consumer' => [
                'config' => [
                    'auto.offset.reset' => 'earliest'
                ]
            ],
            'topics' => ['transport_topic']
        ];
    }

    public static function createWithProducerOnlyTopics(): array
    {
        return [
            'topics' => ['producer_topic'],
            'producer' => [
                'config' => [
                    'batch.size' => '1000'
                ]
            ]
        ];
    }


    public static function createWithInvalidProducerPollTimeout(): array
    {
        return [
            'topics' => ['test_topic'],
            'producer' => [
                'poll_timeout_ms' => 'invalid_string',
                'flush_timeout_ms' => 10000,
                'routing' => [],
                'topics' => [],
                'config' => [],
            ]
        ];
    }

    public static function createWithInvalidProducerFlushTimeout(): array
    {
        return [
            'topics' => ['test_topic'],
            'producer' => [
                'poll_timeout_ms' => 0,
                'flush_timeout_ms' => 'invalid_string',
                'routing' => [],
                'topics' => [],
                'config' => [],
            ]
        ];
    }

    public static function createWithInvalidProducerTopicsType(): array
    {
        return [
            'topics' => ['test_topic'],
            'producer' => [
                'poll_timeout_ms' => 0,
                'flush_timeout_ms' => 10000,
                'routing' => [],
                'topics' => 'invalid_string', // Should be array
                'config' => [],
            ]
        ];
    }

    public static function createWithInvalidProducerRouting(): array
    {
        return [
            'topics' => ['test_topic'],
            'producer' => [
                'poll_timeout_ms' => 0,
                'flush_timeout_ms' => 10000,
                'routing' => [
                    [
                        'topic' => 'SomeValidClass', // Missing 'name' field
                    ]
                ],
                'topics' => [],
                'config' => [],
            ]
        ];
    }

    public static function createWithNonExistentProducerClass(): array
    {
        return [
            'topics' => ['test_topic'],
            'producer' => [
                'poll_timeout_ms' => 0,
                'flush_timeout_ms' => 10000,
                'routing' => [
                    [
                        'name' => 'test_route',
                        'topic' => 'NonExistentClass',
                    ]
                ],
                'topics' => [],
                'config' => [],
            ]
        ];
    }

    public static function createWithInvalidConsumerCommitAsync(): array
    {
        return [
            'topics' => ['test_topic'],
            'consumer' => [
                'commit_async' => 'invalid_string', // Should be bool
                'consume_timeout_ms' => 500,
                'commit_on_error' => true,
                'topics' => [],
                'routing' => [],
                'config' => ['group.id' => 'test_group'],
            ]
        ];
    }

    public static function createWithInvalidConsumerTimeout(): array
    {
        return [
            'topics' => ['test_topic'],
            'consumer' => [
                'commit_async' => true,
                'consume_timeout_ms' => 'invalid_string', // Should be int
                'commit_on_error' => true,
                'topics' => [],
                'routing' => [],
                'config' => ['group.id' => 'test_group'],
            ]
        ];
    }

    public static function createWithInvalidConsumerTopicsType(): array
    {
        return [
            'topics' => ['test_topic'],
            'consumer' => [
                'commit_async' => true,
                'consume_timeout_ms' => 500,
                'commit_on_error' => true,
                'topics' => 'invalid_string', // Should be array
                'routing' => [],
                'config' => ['group.id' => 'test_group'],
            ]
        ];
    }

    public static function createWithInvalidConsumerRouting(): array
    {
        return [
            'topics' => ['test_topic'],
            'consumer' => [
                'commit_async' => true,
                'consume_timeout_ms' => 500,
                'commit_on_error' => true,
                'topics' => [],
                'routing' => [
                    [
                        'class' => 'SomeValidClass', // Missing 'name' field
                    ]
                ],
                'config' => ['group.id' => 'test_group'],
            ]
        ];
    }

    public static function createWithNonExistentConsumerClass(): array
    {
        return [
            'topics' => ['test_topic'],
            'consumer' => [
                'commit_async' => true,
                'consume_timeout_ms' => 500,
                'commit_on_error' => true,
                'topics' => [],
                'routing' => [
                    [
                        'name' => 'test_route',
                        'class' => 'NonExistentClass',
                    ]
                ],
                'config' => ['group.id' => 'test_group'],
            ]
        ];
    }

    public static function createWithMissingRequiredConsumerConfig(): array
    {
        return [
            'topics' => ['test_topic'],
            'consumer' => [
                'commit_async' => true,
                'consume_timeout_ms' => 500,
                'commit_on_error' => true,
                'topics' => [],
                'routing' => [],
                'config' => [], // Missing required 'group.id'
            ]
        ];
    }
}
