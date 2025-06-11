<?php

namespace ARO\KafkaMessenger\Tests\Fixture\Message;

class TestMessage
{
    public function __construct(
        public string $name = 'test',
        public int $value = 42
    ) {
    }
}
