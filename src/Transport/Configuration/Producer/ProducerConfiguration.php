<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport\Configuration\Producer;

final class ProducerConfiguration
{
    /**
     * @param array<string, mixed> $config
     * @param array<string> $topics
     */
    public function __construct(
        public array $routing = [],
        public array $config = [],
        public array $topics = [],
        public int $pollTimeoutMs = 0,
        public int $flushTimeoutMs = 10000
    ) {
    }
}
