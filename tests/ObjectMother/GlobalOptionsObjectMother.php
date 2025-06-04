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
}