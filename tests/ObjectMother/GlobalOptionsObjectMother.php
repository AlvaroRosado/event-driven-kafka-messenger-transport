<?php

namespace ARO\KafkaMessenger\Tests\ObjectMother;

class GlobalOptionsObjectMother
{
    public static function createDefault(): array
    {
        return [
            'consumer' => [
                'commit_async' => true,
                'consume_timeout_ms' => 500,
                'config' => [
                    'group.id' => 'default-group',
                    'auto.offset.reset' => 'earliest'
                ]
            ],
            'producer' => [
                'config' => [
                    'enable.idempotence' => 'false'
                ]
            ]
        ];
    }

    public static function createEmpty(): array
    {
        return [];
    }

    public static function createWithConsumerConfig(): array
    {
        return [
            'consumer' => [
                'commit_async' => true,
                'config' => [
                    'group.id' => 'global-group'
                ]
            ],
            'topics' => ['global_topic']
        ];
    }


    public static function createWithInvalidProducerTypes(): array
    {
        return [
            'producer' => [
                'poll_timeout_ms' => 'invalid_string', // Should be int
                'flush_timeout_ms' => 10000,
                'routing' => [],
                'topics' => [],
                'config' => [],
            ]
        ];
    }

    public static function createWithInvalidConsumerTypes(): array
    {
        return [
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
}
