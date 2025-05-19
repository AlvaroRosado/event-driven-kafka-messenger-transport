<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport\Setting;

final readonly class ConsumerSetting
{
    /**
     * @param array<string, mixed> $routing
     * @param array<string, mixed> $config
     * @param array<string> $topics
     */
    public function __construct(
        public array $routing = [],
        public array $config = [],
        public array $topics = [],
        public int $consumeTimeout = 500,
        public bool $commitAsync = true,
        public bool $validateSchema = false,
    ) {
    }
}
