<?php

namespace ARO\KafkaMessenger\Tests\Fixture\Message;

class AnotherTestMessage
{
    public function __construct(
        public string $description = 'another test',
        public bool $active = true
    ) {
    }
}
