<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport\Setting;

final readonly class ProducerSetting
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
        public int $flushTimeoutMs = 10000,
        public bool $validateSchema = false,
    ) {
    }
}
