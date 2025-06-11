<?php

namespace ARO\KafkaMessenger\Tests\Fixture\Message;

class CamelCaseMessage
{
    public function __construct(
        public string $firstName = 'John',
        public string $lastName = 'Doe'
    ) {
    }
}
