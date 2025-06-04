<?php

namespace ARO\KafkaMessenger\Transport\Configuration\JsonSerialization;

final class JsonSerializationConfiguration
{
    public function __construct(
        public bool $enabled = false,
        public ?string $customSerializer = null
    ) {
    }
}
