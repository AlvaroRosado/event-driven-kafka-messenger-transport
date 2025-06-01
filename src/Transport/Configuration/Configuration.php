<?php

declare(strict_types=1);

namespace ARO\KafkaMessenger\Transport\Configuration;

use ARO\KafkaMessenger\Transport\Configuration\Consumer\ConsumerConfiguration;
use ARO\KafkaMessenger\Transport\Configuration\JsonSerialization\JsonSerializationConfiguration;
use ARO\KafkaMessenger\Transport\Configuration\Producer\ProducerConfiguration;

final class Configuration
{
    public function __construct(
        private string                $host,
        private string                $transportName,
        private ProducerConfiguration $producer,
        private ConsumerConfiguration $consumer,
        private JsonSerializationConfiguration $jsonSerialization,
    ) {
    }
    
    public function getHost(): string
    {
        return $this->host;
    }
    
    public function getTransportName(): string
    {
        return $this->transportName;
    }
    
    public function isJsonSerializationEnabled(): bool
    {
        return $this->jsonSerialization->enabled;
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
