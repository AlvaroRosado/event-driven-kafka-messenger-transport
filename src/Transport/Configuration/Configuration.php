<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport\Configuration;

use ARO\KafkaMessenger\Transport\Configuration\Consumer\ConsumerConfiguration;
use ARO\KafkaMessenger\Transport\Configuration\Dsn\DsnConfiguration;
use ARO\KafkaMessenger\Transport\Configuration\JsonSerialization\JsonSerializationConfiguration;
use ARO\KafkaMessenger\Transport\Configuration\Producer\ProducerConfiguration;

final class Configuration
{
    public function __construct(
        private DsnConfiguration $dsn,
        private ProducerConfiguration $producer,
        private ConsumerConfiguration $consumer,
        private JsonSerializationConfiguration $jsonSerialization,
    ) {
    }
    
    public function getHost(): string
    {
        return $this->dsn->host;
    }

    public function getSecurityConfig(): array
    {
        return $this->dsn->securityConfig;
    }
    
    public function getTransportName(): string
    {
        return $this->dsn->transportName;
    }
    
    public function isJsonSerializationEnabled(): bool
    {
        return $this->jsonSerialization->enabled;
    }

    public function customSymfonySerializer(): string
    {
        return $this->jsonSerialization->customSerializer;
    }
    
    public function getConsumer(): ConsumerConfiguration
    {
        return $this->consumer;
    }
    
    public function getProducer(): ProducerConfiguration
    {
        return $this->producer;
    }
    
}
