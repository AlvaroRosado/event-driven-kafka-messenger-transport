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
}