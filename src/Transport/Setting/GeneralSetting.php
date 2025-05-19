<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport\Setting;

final readonly class GeneralSetting
{
    public function __construct(
        public string          $host,
        public string          $transportName,
        public string          $staticMethodIdentifier,
        public ProducerSetting $producer,
        public ConsumerSetting $consumer,
        public ?string          $serializer = null,
    ) {
    }
}
