<?php

namespace ARO\KafkaMessenger\Transport\Configuration\Dsn;

final class DsnConfigurationBuilder
{
    public function build(string $dsn): DsnConfiguration
    {
        $parsedUrl = parse_url($dsn);

        if (false === $parsedUrl || !isset($parsedUrl['host'], $parsedUrl['port'], $parsedUrl["scheme"])) {
            throw new \InvalidArgumentException(sprintf('Invalid Kafka DSN: "%s"', $dsn));
        }

        if ('kafka' !== $parsedUrl['scheme']) {
            throw new \InvalidArgumentException(sprintf('Kafka DSN must start with "kafka://": "%s"', $dsn));
        }

        return new DsnConfiguration(
            host: $parsedUrl["host"] . ":" . $parsedUrl["port"],
            transportName: $parsedUrl['path'] ?? 'kafka'
        );
    }
}