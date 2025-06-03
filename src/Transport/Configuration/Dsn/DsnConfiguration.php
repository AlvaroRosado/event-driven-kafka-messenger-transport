<?php

namespace ARO\KafkaMessenger\Transport\Configuration\Dsn;

final class DsnConfiguration
{
    public function __construct(
        public string $host,
        public string $transportName,
        public array $securityConfig = [],
    ) {}
}